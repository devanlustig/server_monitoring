<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\DiskMetricData;
use RuntimeException;

class DiskParser
{
    public function parse(
        string $output
    ): DiskMetricData
    {
        $parts = preg_split(
            '/\s+/',
            trim($output)
        );

        if(count($parts)<6){

            throw new RuntimeException(
                'Invalid disk output.'
            );

        }

        $total=(int)$parts[1];

        $used=(int)$parts[2];

        $available=(int)$parts[3];

        $usage=(float)str_replace(
            '%',
            '',
            $parts[4]
        );

        return new DiskMetricData(

            total:$total,

            used:$used,

            available:$available,

            usagePercent:$usage,

        );
    }
}