<?php

require __DIR__ . '/../vendor/autoload.php';

defined('LINKDOC_APP_NAME') or define('LINKDOC_APP_NAME', 'TEST_APP');
putenv('REDIS_HOST=127.0.0.1');

use Linkdoc\Metrics\Metrics;

// 配置redis适配器
Metrics::registry(['REDIS_HOST' => '127.0.0.1']);

// 创建累加指标 handle_jobs_total
Metrics::counter('handle_jobs_total', ['script' => '/oneTime']);

// 创建测量指标 cpu_usage_rate
Metrics::gauge('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.67);

// 测量指标 cpu_usage_rate 自增 0.1
Metrics::gaugeInc('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.1);

// 测量指标 cpu_usage_rate 自减 0.1
Metrics::gaugeDec('cpu_usage_rate', ['ip' => '127.0.0.1'], 0.1);

// 常规指标-应用HTTP请求总量
Metrics::httpRequestsTotal('/metrics', 'GET');

// 常规指标-应用当前处理中的请求总量-开始处理时+1
Metrics::httpInprogressRequests('/metrics', 'GET');

// 常规指标-应用当前处理中的请求总量-完成响应后-1
Metrics::httpInprogressRequests('/metrics', 'GET', false);

// 手动推送全部指标到prometheus-pushgateway
Metrics::push();

// 打印全部指标,供 prometheus 主动拉取
Metrics::print();