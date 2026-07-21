<?php

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\Data\ProbeResult;
use App\Models\MonitoredServer;

/** Contract implemented by protocol-specific monitoring probes. */
interface ServerProbe
{
    public function supports(MonitoredServer $server): bool;

    public function probe(MonitoredServer $server): ProbeResult;
}
