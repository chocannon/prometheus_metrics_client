<?php
declare(strict_types = 1);

namespace Linkdoc\Metrics;

/**
 * @method static \Linkdoc\Metrics\MetricsHandler registry(array $config = [])
 * @method static void counter(string $name, array $labels = [], int $count = 1, string $help = '')
 * @method static void gauge(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static void gaugeInc(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static void gaugeDec(string $name, array $labels = [], float $value = 0, string $help = '')
 * @method static void print()
 * @method static void push()
 * @method static void flush()
 * @method static void httpRequestsTotal(string $path, string $method, string $status = 'success', array $extLabels = [])
 * @method static void httpInprogressRequests(string $path, string $method, bool $state = true)
 */
class Metrics
{
    /**
     * @var MetricsHandler
     */
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
