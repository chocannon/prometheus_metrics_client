<?php
declare(strict_types=1);

namespace Linkdoc\Metrics\Core;

use Linkdoc\Metrics\Core\Exception\MetricNotFoundException;
use Linkdoc\Metrics\Core\Exception\MetricsRegistrationException;
use Linkdoc\Metrics\Core\Storage\Adapter;
use Linkdoc\Metrics\Core\Storage\Redis;

class CollectorRegistry
{
    /**
     * @var CollectorRegistry
     */
    private static $defaultRegistry;

    /**
     * @var Adapter
     */
    private $storageAdapter;

    /**
     * @var Gauge[]
     */
    private $gauges = [];

    /**
     * @var Counter[]
     */
    private $counters = [];

    /**
     * CollectorRegistry constructor.
     * @param Adapter $redisAdapter
     */
    public function __construct(Adapter $redisAdapter)
    {
        $this->storageAdapter = $redisAdapter;
    }

    /**
     * @return CollectorRegistry
     */
    public static function getDefault(): CollectorRegistry
    {
        if (!self::$defaultRegistry) {
            return self::$defaultRegistry = new static(new Redis());
        }
        return self::$defaultRegistry;
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function getMetricFamilySamples(): array
    {
        return $this->storageAdapter->collect();
    }

    /**
     * @return Adapter|Redis
     */
    public function getStorageAdapter(): Adapter
    {
        return $this->storageAdapter;
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     * @throws MetricsRegistrationException
     */
    public function registerGauge($namespace, $name, $help, $labels = []): Gauge
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (isset($this->gauges[$metricIdentifier])) {
            throw new MetricsRegistrationException("Metric already registered");
        }
        $this->gauges[$metricIdentifier] = new Gauge(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->gauges[$metricIdentifier];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @return Gauge
     * @throws MetricNotFoundException
     */
    public function getGauge($namespace, $name): Gauge
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (!isset($this->gauges[$metricIdentifier])) {
            throw new MetricNotFoundException("Metric not found:" . $metricIdentifier);
        }
        return $this->gauges[$metricIdentifier];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     * @throws MetricsRegistrationException
     */
    public function getOrRegisterGauge($namespace, $name, $help, $labels = []): Gauge
    {
        try {
            $gauge = $this->getGauge($namespace, $name);
        } catch (MetricNotFoundException $e) {
            $gauge = $this->registerGauge($namespace, $name, $help, $labels);
        }
        return $gauge;
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. requests
     * @param string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     * @throws MetricsRegistrationException
     */
    public function registerCounter($namespace, $name, $help, $labels = []): Counter
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (isset($this->counters[$metricIdentifier])) {
            throw new MetricsRegistrationException("Metric already registered");
        }
        $this->counters[$metricIdentifier] = new Counter(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->counters[self::metricIdentifier($namespace, $name)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @return Counter
     * @throws MetricNotFoundException
     */
    public function getCounter($namespace, $name): Counter
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (!isset($this->counters[$metricIdentifier])) {
            throw new MetricNotFoundException("Metric not found:" . $metricIdentifier);
        }
        return $this->counters[self::metricIdentifier($namespace, $name)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. requests
     * @param string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     * @throws MetricsRegistrationException
     */
    public function getOrRegisterCounter($namespace, $name, $help, $labels = []): Counter
    {
        try {
            $counter = $this->getCounter($namespace, $name);
        } catch (MetricNotFoundException $e) {
            $counter = $this->registerCounter($namespace, $name, $help, $labels);
        }
        return $counter;
    }

    /**
     * @param $namespace
     * @param $name
     * @return string
     */
    private static function metricIdentifier($namespace, $name): string
    {
        return $namespace . ":" . $name;
    }
}
