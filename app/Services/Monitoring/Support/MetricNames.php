<?php

namespace App\Services\Monitoring\Support;

class MetricNames
{
    public const TOTAL_REQUESTS = 'total_requests';
    public const REQUESTS_PER_MINUTE = 'requests_per_minute';
    public const REQUESTS_PER_HOUR = 'requests_per_hour';
    public const TOTAL_TRAFFIC = 'total_traffic_bytes';
    public const ERROR_RATE = 'error_rate';
    public const SUCCESS_RATE = 'success_rate';
    public const SLOW_REQUEST_COUNT = 'slow_request_count';
    public const AVERAGE_RESPONSE_TIME = 'average_response_time';

    /**
     * Metric yang dapat dipilih pada Historical Analytics.
     */
    public static function apacheSelectable(): array
    {
        return [

            self::AVERAGE_RESPONSE_TIME => 'Average Response Time',
            self::REQUESTS_PER_MINUTE => 'Requests / Minute',
            self::REQUESTS_PER_HOUR => 'Requests / Hour',
            self::TOTAL_REQUESTS => 'Total Requests',
            self::TOTAL_TRAFFIC => 'Total Traffic',
            self::ERROR_RATE => 'Error Rate',
            self::SUCCESS_RATE => 'Success Rate',
            self::SLOW_REQUEST_COUNT => 'Slow Requests',
        ];
    }

    public static function nginxSelectable(): array
    {
        return [
            self::REQUESTS_PER_MINUTE => 'Requests / Minute',
            self::REQUESTS_PER_HOUR => 'Requests / Hour',
            self::TOTAL_REQUESTS => 'Total Requests',
            self::TOTAL_TRAFFIC => 'Total Traffic',
            self::ERROR_RATE => 'Error Rate',
            self::SUCCESS_RATE => 'Success Rate',
        ];
    }
}