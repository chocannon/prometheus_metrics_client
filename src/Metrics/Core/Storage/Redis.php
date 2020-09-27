<?php
namespace Linkdoc\Metrics\Core\Storage;

use InvalidArgumentException;
use Linkdoc\Metrics\Core\Counter;
use Linkdoc\Metrics\Core\Exception\StorageException;
use Linkdoc\Metrics\Core\Gauge;
use Linkdoc\Metrics\Core\MetricFamilySamples;

class Redis implements Adapter
{
    const PROMETHEUS_METRIC_KEYS_SUFFIX = 'METRIC_KEYS';

    /**
     * @var array
     */
    private static $defaultOptions = [
        'host'                   => '127.0.0.1',
        'port'                   => 6379,
        'timeout'                => 0.1,
        'read_timeout'           => 10,
        'persistent_connections' => false,
        'password'               => null,
    ];

    /**
     * @var string
     */
    private static $prefix = 'PROMETHEUS_';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * Redis constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$defaultOptions, $options);
        $this->redis = new \Redis();
    }

    /**
     * @param array $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    /**
     * @param $prefix
     */
    public static function setPrefix($prefix): void
    {
        self::$prefix = $prefix;
    }

    /**
     * @throws StorageException
     */
    public function flushRedis(): void
    {
        $this->openConnection();
        $gaugeSkey  = $this->toGatherKey(Gauge::TYPE);
        $gaugeKeys  = $this->redis->smembers($gaugeSkey);
        $couterSkey = $this->toGatherKey(Counter::TYPE);
        $couterKeys = $this->redis->smembers($couterSkey);
        $metricKeys = array_merge($gaugeKeys, $couterKeys, [$gaugeSkey, $couterSkey]);
        $this->redis->del($metricKeys);
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect(): array
    {
        $this->openConnection();
        $metrics = $this->collectGauges();
        $metrics = array_merge($metrics, $this->collectCounters());
        return array_map(
            function (array $metric) {
                return new MetricFamilySamples($metric);
            },
            $metrics
        );
    }

    /**
     * @throws StorageException
     */
    private function openConnection(): void
    {
        $connectionStatus = $this->connectToServer();
        if ($connectionStatus === false) {
            throw new StorageException("Can't connect to Redis server", 0);
        }

        if ($this->options['password']) {
            $this->redis->auth($this->options['password']);
        }

        if (isset($this->options['database'])) {
            $this->redis->select($this->options['database']);
        }

        $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->options['read_timeout']);
    }

    /**
     * @return bool
     */
    private function connectToServer(): bool
    {
        try {
            if ($this->options['persistent_connections']) {
                return $this->redis->pconnect(
                    $this->options['host'],
                    $this->options['port'],
                    $this->options['timeout']
                );
            }

            return $this->redis->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        } catch (\RedisException $e) {
            return false;
        }
    }

    /**
     * @param array $data
     * @throws StorageException
     */
    public function updateGauge(array $data): void
    {
        $this->openConnection();
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['labelValues']);
        unset($metaData['command']);
        $this->redis->eval(
            <<<LUA
local result = redis.call(KEYS[2], KEYS[1], KEYS[4], ARGV[1])

if KEYS[2] == 'hSet' then
    if result == 1 then
        redis.call('hSet', KEYS[1], '__meta', ARGV[2])
        redis.call('sAdd', KEYS[3], KEYS[1])
    end
else
    if result == ARGV[1] then
        redis.call('hSet', KEYS[1], '__meta', ARGV[2])
        redis.call('sAdd', KEYS[3], KEYS[1])
    end
end
LUA
            ,
            [
                $this->toMetricKey($data),
                $this->getRedisCommand($data['command']),
                $this->toGatherKey(Gauge::TYPE),
                json_encode($data['labelValues']),
                $data['value'],
                json_encode($metaData),
            ],
            4
        );
    }

    /**
     * @param array $data
     * @throws StorageException
     */
    public function updateCounter(array $data): void
    {
        $this->openConnection();
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['labelValues']);
        unset($metaData['command']);
        $this->redis->eval(
            <<<LUA
local result = redis.call(KEYS[2], KEYS[1], KEYS[4], ARGV[1])
if result == tonumber(ARGV[1]) then
    redis.call('hMSet', KEYS[1], '__meta', ARGV[2])
    redis.call('sAdd', KEYS[3], KEYS[1])
end
return result
LUA
            ,
            [
                $this->toMetricKey($data),
                $this->getRedisCommand($data['command']),
                $this->toGatherKey(Counter::TYPE),
                json_encode($data['labelValues']),
                $data['value'],
                json_encode($metaData),
            ],
            4
        );
    }

    /**
     * @return array
     */
    private function collectGauges(): array
    {
        $gkey = $this->toGatherKey(Gauge::TYPE);
        $keys = $this->redis->sMembers($gkey);
        sort($keys);
        $gauges = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll($key);
            $gauge = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $gauge['samples'] = [];
            foreach ($raw as $k => $value) {
                $gauge['samples'][] = [
                    'name' => $gauge['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($gauge['samples'], function ($a, $b) {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $gauges[] = $gauge;
        }
        return $gauges;
    }

    /**
     * @return array
     */
    private function collectCounters(): array
    {
        $gkey = $this->toGatherKey(Counter::TYPE);
        $keys = $this->redis->sMembers($gkey);
        sort($keys);
        $counters = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll($key);
            $counter = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $counter['samples'] = [];
            foreach ($raw as $k => $value) {
                $counter['samples'][] = [
                    'name' => $counter['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($counter['samples'], function ($a, $b) {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $counters[] = $counter;
        }
        return $counters;
    }

    /**
     * @param int $cmd
     * @return string
     */
    private function getRedisCommand(int $cmd): string
    {
        switch ($cmd) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                return 'hIncrBy';
            case Adapter::COMMAND_INCREMENT_FLOAT:
                return 'hIncrByFloat';
            case Adapter::COMMAND_SET:
                return 'hSet';
            default:
                throw new InvalidArgumentException("Unknown command");
        }
    }

    /**
     * @param array $data
     * @return string
     */
    private function toMetricKey(array $data): string
    {
        return implode(':', [self::$prefix, $data['type'], $data['name']]);
    }


    /**
     * @param string $type
     * @return string
     */
    private function toGatherKey(string $type)
    {
        return implode(':', [self::$prefix, $type, self::PROMETHEUS_METRIC_KEYS_SUFFIX]);
    }
}
