<?php

namespace Tests;

use Linkdoc\Metrics\Metrics;
use PHPUnit\Framework\TestCase;
use Linkdoc\Metrics\MetricsHandler;

class MetricsTest extends TestCase
{
    public function setUp()
    {
        defined('LINKDOC_APP_NAME') or define('LINKDOC_APP_NAME', 'TEST_APP');
        putenv('REDIS_HOST=127.0.0.1');
    }


    public function testRegistry()
    {
        $result = Metrics::registry(['REDIS_HOST' => '127.0.0.1']);
        $this->assertTrue($result instanceof MetricsHandler);
    }


    public function testCounter()
    {
        $result = Metrics::counter('handle_jobs_total', ['script' => '/oneTime']);
        $this->assertTrue(is_null($result));
    }


    public function testGauge()
    {
        $result = Metrics::gauge('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.67);
        $this->assertTrue(is_null($result));
    }


    public function testGaugeInc()
    {
        $result = Metrics::gaugeInc('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.1);
        $this->assertTrue(is_null($result));
    }


    public function testguageDec()
    {
        $result = Metrics::gaugeDec('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.1);
        $this->assertTrue(is_null($result));
    }


    public function testHttpRequestsTotal()
    {
        $result = Metrics::httpRequestsTotal('/metrics', 'GET');
        $this->assertTrue(is_null($result));
    }


    public function testHttpInprogressRequests()
    {
        $befor = Metrics::httpInprogressRequests('/metrics', 'GET');
        $after = Metrics::httpInprogressRequests('/metrics', 'GET', false);
        $this->assertTrue(is_null($befor) && is_null($after));
    }


    public function testPush()
    {
        $result = Metrics::push();
        $this->assertTrue(is_null($result));
    }


    public function testFlush()
    {
        $result = Metrics::flush();
        $this->assertTrue(is_null($result));
    }
}
