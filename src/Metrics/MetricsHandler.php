<?php
declare(strict_types = 1);

namespace Linkdoc\Metrics;

use InvalidArgumentException;
use Linkdoc\Metrics\Core\PushGateway;
use Linkdoc\Metrics\Core\Storage\Redis;
use Linkdoc\Metrics\Core\RenderTextFormat;
use Linkdoc\Metrics\Core\CollectorRegistry;

class MetricsHandler
{
    const METRICS_NS = '';
    const LK_APP_KEY = 'lk_app_name';

    /**
     * @var CollectorRegistry
     */
    private static $registry;

    /**
     * MetricsHandler constructor.
     */
    public function __construct()
    {
        if (!defined('LINKDOC_APP_NAME')) {
            throw new InvalidArgumentException('Unknown Application Name');
        }
    }

    /**
     * 实例化redis,存储指标数据
     *
     * @param array $config e.g. ['REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => 6379, 'REDIS_PASSWORD' => null]
     * @return MetricsHandler
     */
    public function registry(array $config = []): MetricsHandler
    {
        if (!isset($config['REDIS_HOST'])) {
            throw new InvalidArgumentException('Unknown Config Redis Host');
        }
        Redis::setPrefix(LINKDOC_APP_NAME . '_METRICS');
        Redis::setDefaultOptions([
            'host'     => $config['REDIS_HOST'],
            'port'     => ($config['REDIS_PORT'] ?? 6379),
            'password' => ($config['REDIS_PASSWORD'] ?? null),
        ]);
        self::$registry = new CollectorRegistry(new Redis());
        return $this;
    }

    /**
     * 设置累加类型指标
     *
     * @param string $name 指标名称 e.g. http_requests_total
     * @param array $labels 标签字典 e.g. ['path' => '/metrcis', 'method' => 'GET']
     * @param integer $count 自增值 e.g. 1
     * @param string $help 指标描述 e.g. 'Http请求总数量'
     * @return void
     */
    public function counter(string $name, array $labels = [], int $count = 1, string $help = ''): void
    {
        $labels  = self::appendDefaultLabels($labels);
        $counter = $this->getRegistry()->getOrRegisterCounter(
            self::METRICS_NS, $name, $help, array_keys($labels)
        );
        $counter->incBy($count, array_values($labels));
    }

    /**
     * 设置测量类型指标
     *
     * @param string $name 指标名称 e.g. http_inprogress_requests
     * @param array $labels 标签字典 e.g. ['path' => '/metrcis', 'method' => 'GET']
     * @param float $value 指标值 e.g. 1
     * @param string $help 指标描述 e.g. '处理中的Http请求数量'
     * @return void
     */
    public function gauge(string $name, array $labels = [], float $value = 1, string $help = ''): void
    {
        $labels = self::appendDefaultLabels($labels);
        $gauge  = $this->getRegistry()->getOrRegisterGauge(
            self::METRICS_NS, $name, $help, array_keys($labels)
        );
        $gauge->set($value, array_values($labels));
    }

    /**
     * 设置测量类型指标-自增
     *
     * @param string $name 指标名称 e.g. http_inprogress_requests
     * @param array $labels 标签字典 e.g. ['path' => '/metrcis', 'method' => 'GET']
     * @param float $value 自增值 e.g. 1
     * @param string $help 指标描述 e.g. '处理中的Http请求数量'
     * @return void
     */
    public function gaugeInc(string $name, array $labels = [], float $value = 1, string $help = ''): void
    {
        $labels = self::appendDefaultLabels($labels);
        $gauge  = $this->getRegistry()->getOrRegisterGauge(
            self::METRICS_NS, $name, $help, array_keys($labels)
        );
        $gauge->incBy($value, array_values($labels));
    }

    /**
     * 设置测量类型指标-自减
     *
     * @param string $name 指标名称 e.g. http_inprogress_requests
     * @param array $labels 标签字典 e.g. ['path' => '/metrcis', 'method' => 'GET']
     * @param float $value $value 自增值 e.g. 1
     * @param string $help
     * @return void
     */
    public function gaugeDec(string $name, array $labels = [], float $value = 1, string $help = ''): void
    {
        $labels = self::appendDefaultLabels($labels);
        $gauge  = $this->getRegistry()->getOrRegisterGauge(
            self::METRICS_NS, $name, $help, array_keys($labels)
        );
        $gauge->decBy($value, array_values($labels));
    }

    /**
     * 打印全部指标数据,应用可开放'/metrics'接口调用此方法,供prometheus采集
     *
     * @return void
     */
    public function print(): void
    {
        $render = new RenderTextFormat();
        $result = $render->render($this->getRegistry()->getMetricFamilySamples());
        header('Content-type: ' . RenderTextFormat::MIME_TYPE);
        echo $result;
    }

    /**
     * 主动推送指标数据至pushgateway
     *
     * @return void
     */
    public function push(): void
    {
        static $gateway;
        if (!$gateway instanceof PushGateway) {
            $address = getenv('METRICS_PUSH_GATEWAY_HOST') ?: null;
            $gateway = new PushGateway($address);
        }
        $gateway->pushAdd($this->getRegistry(), 'active_push_metrics', ['instance' => LINKDOC_APP_NAME]);
    }

    /**
     * 删除全部数据指标
     *
     * @return void
     */
    public function flush(): void
    {
        /**
         * @var \Linkdoc\Metrics\Core\Storage\Redis
         */
        $adapter = $this->getRegistry()->getStorageAdapter();
        $adapter->flushRedis();
    }

    /**
     * 常规指标-应用HTTP请求总量,建议放在请求响应后调用次方法
     *
     * @param string $path 请求路径 e.g. '/metrics'
     * @param string $method 请求方法 e.g. 'GET'
     * @param string $status 响应状态 e.g. 'success'
     * @param array $extLabels 标签扩展字典 e.g. []
     * @return void
     */
    public function httpRequestsTotal(string $path, string $method, string $status = 'success', array $extLabels = []): void
    {
        $labels = [self::LK_APP_KEY, 'path', 'method', 'status'];
        $values = [LINKDOC_APP_NAME, $path, $method, $status];
        if ($extLabels) {
            $labels = array_merge($labels, array_keys($extLabels));
            $values = array_merge($labels, array_values($extLabels));
        }
        $counter = $this->getRegistry()->getOrRegisterCounter(
            self::METRICS_NS, 'http_requests_total', 'http_requests_total', $labels
        );
        $counter->inc($values);
    }

    /**
     * 常规指标-应用正在处理中的HTTP请求量,建议请求开始与结束时分别调用此方法
     *
     * @param string $path 请求路径 e.g. '/metrics'
     * @param string $method 请求方法 e.g. 'GET'
     * @param boolean $state 指标状态,true-自增|false-自减
     * @return void
     */
    public function httpInprogressRequests(string $path, string $method, bool $state = true): void
    {
        static $guidMap = [];
        $labels = [self::LK_APP_KEY, 'path', 'method'];
        $values = [LINKDOC_APP_NAME, $path, $method];
        $guid   = md5($method . $path);
        $gauge  = $this->getRegistry()->getOrRegisterGauge(
            self::METRICS_NS, 'http_inprogress_requests', 'http_inprogress_requests', $labels
        );
        if ($state && !isset($guidMap[$guid])) {
            $guidMap[$guid] = $values;
            $gauge->inc($values);
        }
        if (!$state && isset($guidMap[$guid])) {
            unset($guidMap[$guid]);
            $gauge->dec($values);
        }
    }

    /**
     * 获取指标存储适配器
     *
     * @return CollectorRegistry
     */
    private function getRegistry(): CollectorRegistry
    {
        if (!self::$registry instanceof CollectorRegistry) {
            Redis::setPrefix(LINKDOC_APP_NAME . '_METRICS');
            Redis::setDefaultOptions([
                'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port'     => getenv('REDIS_PORT') ?: 6379,
                'password' => getenv('REDIS_PASSWORD') ?: null,
            ]);
            self::$registry = new CollectorRegistry(new Redis());
        }
        return self::$registry;
    }


    /**
     * 追加默认标签
     *
     * @param array $labels
     * @return array
     */
    private static function appendDefaultLabels(array $labels): array
    {
        $labels[self::LK_APP_KEY] = LINKDOC_APP_NAME;
        return $labels;
    }
}
