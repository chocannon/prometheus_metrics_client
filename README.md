# PHP-SDK-Metrics

指标采集SDK

## 环境依赖

- PHP >= 7.0
- ext-json
- ext-curl
- ext-redis

## 依赖常量

依赖自定义常量 *LINKDOC_APP_NAME* ，该常量值会作为指标名称前缀，需满足 *变量命名规范*

## 环境变量

默认读取环境变量 *REDIS_HOST*，*REDIS_PORT*， *REDIS_PASSWORD*，支持参数传入
```php
$config = [
	'REDIS_HOST'     => '127.0.0.1',
	'REDIS_PORT'     => 6379,
	'REDIS_PASSWORD' => null,
];
```

Pushgateway 使用默认OP提供的地址，支持应用内配置环境变量 *METRICS_PUSH_GATEWAY_HOST* 更改该地址

## 预留关键字

* *lk_app_name* 该关键字会作为默认标签加入到指标中

## 使用示例

```php
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

// 删除全部数据指标
Metrics::flush();
```

## 结果示例

```
# HELP test_app_cpu_usage_rate 
# TYPE test_app_cpu_usage_rate gauge
test_app_cpu_usage_rate{ip="127.0.0.1",lk_app_name="TEST_APP"} 0.67
# HELP test_app_handle_jobs_total 
# TYPE test_app_handle_jobs_total counter
test_app_handle_jobs_total{script="/oneTime",lk_app_name="TEST_APP"} 1
# HELP test_app_http_inprogress_requests http_inprogress_requests
# TYPE test_app_http_inprogress_requests gauge
test_app_http_inprogress_requests{lk_app_name="TEST_APP",path="/metrics",method="GET"} 0
# HELP test_app_http_requests_total http_requests_total
# TYPE test_app_http_requests_total counter
test_app_http_requests_total{lk_app_name="TEST_APP",path="/metrics",method="GET",status="success"} 1
```

