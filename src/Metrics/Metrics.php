<?php
declare(strict_types = 1);

namespace Linkdoc\Metrics;

/**
 * @method static \Linkdoc\Metrics\MetricsHandler registry(array $config = [])
 * @method static \Linkdoc\Metrics\MetricsHandler counter(string $name, array $labels = [], int $count = 1, string $help = '')
 * @method static \Linkdoc\Metrics\MetricsHandler gauge(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static \Linkdoc\Metrics\MetricsHandler gaugeInc(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static \Linkdoc\Metrics\MetricsHandler gaugeDec(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static \Linkdoc\Metrics\MetricsHandler print()
 * @method static \Linkdoc\Metrics\MetricsHandler push()
 * @method static \Linkdoc\Metrics\MetricsHandler httpRequestsTotal(string $path, string $method, string $status = 'success', array $extLabels = [])
 * @method static \Linkdoc\Metrics\MetricsHandler httpInprogressRequests(string $path, string $method, bool $state = true)
 */
class Metrics
{
    protected $handler;

    public function __construct()
    {
        $this->handler = new MetricsHandler();
    }


    public function __call($name, $params) {
        return call_user_func_array([$this->handler, $name], $params);
    }


    public static function __callStatic($name, $params) {
        return call_user_func_array([new static(), $name], $params);
    }
}
