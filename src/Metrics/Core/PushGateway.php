<?php
declare(strict_types = 1);

namespace Linkdoc\Metrics\Core;

use RuntimeException;
use Linkdoc\Metrics\Core\RenderTextFormat;

class PushGateway
{
    /**
     * Push gateway address
     *
     * @var string
     */
    private $defaultAddress = 'http://172.16.0.165:9091/metrics/job/';

    /**
     * Response http code mapping
     *
     * @var array
     */
    private static $respHttpCodeMap = [
        'PUT'    => 200,
        'POST'   => 200,
        'DELETE' => 202,
    ];


    /**
     * PushGateway constructor.
     * @param $address string host:port of the push gateway
     */
    public function __construct(string $address = null)
    {
        if ($address) {
            $this->defaultAddress = $address;
        }
    }


    /**
     * Pushes all metrics in a Collector, replacing all those with the same job.
     * Uses HTTP PUT.
     * @param CollectorRegistry $collectorRegistry
     * @param string $job
     * @param array $groupingKey
     * @throws GuzzleException
     */
    public function push(CollectorRegistry $collectorRegistry, string $job, array $groupingKey = []): void
    {
        $render = new RenderTextFormat();
        $metric = $render->render($collectorRegistry->getMetricFamilySamples());
        $this->doRequest('PUT', $job, $groupingKey, $metric);
    }


    /**
     * Pushes all metrics in a Collector, replacing only previously pushed metrics of the same name and job.
     * Uses HTTP POST.
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     * @throws RuntimeException
     */
    public function pushAdd(CollectorRegistry $collectorRegistry, string $job, array $groupingKey = []): void
    {
        $render = new RenderTextFormat();
        $metric = $render->render($collectorRegistry->getMetricFamilySamples());
        $this->doRequest('POST', $job, $groupingKey, $metric);
    }

    /**
     * Deletes metrics from the Push Gateway.
     * Uses HTTP POST.
     * @param string $job
     * @param array $groupingKey
     * @throws RuntimeException
     */
    public function delete(string $job, array $groupingKey = []): void
    {
        $this->doRequest('DELETE', $job, $groupingKey);
    }


    /**
     * @param string $method
     * @param string $job
     * @param array $groupingKey
     * @param string $requestBody
     * @throws RuntimeException
     */
    private function doRequest(string $method, string $job, array $groupingKey, string $requestBody = ''): void
    {
        $url = $this->defaultAddress . $job;
        if ($groupingKey) {
            foreach ($groupingKey as $label => $value) {
                $url .= '/' . $label . '/' . $value;
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != self::$respHttpCodeMap[$method]) {
            throw new RuntimeException("Unexpected status code {$code} received from push gateway");
        }
        curl_close($ch);
    }
}
