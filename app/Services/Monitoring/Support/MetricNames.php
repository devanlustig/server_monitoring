<?php

namespace App\Services\Monitoring\Support;

class MetricNames
{
    public const TOTAL_REQUESTS = 'total_requests';
    public const REQUESTS_PER_MINUTE = 'requests_per_minute';
    public const REQUESTS_PER_HOUR = 'requests_per_hour';
    public const TOTAL_TRAFFIC_BYTES = 'total_traffic_bytes';
    public const HTTP_2XX = 'http_2xx_count';
    public const HTTP_3XX = 'http_3xx_count';
    public const HTTP_4XX = 'http_4xx_count';
    public const HTTP_5XX = 'http_5xx_count';
    public const ERROR_RATE = 'error_rate';
    public const SUCCESS_RATE = 'success_rate';
    public const SLOW_REQUEST_COUNT = 'slow_request_count';
    public const AVERAGE_RESPONSE_TIME = 'average_response_time';
}