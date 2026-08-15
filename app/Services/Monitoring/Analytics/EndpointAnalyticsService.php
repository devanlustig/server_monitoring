<?php

namespace App\Services\Monitoring\Analytics;

use Illuminate\Support\Collection;

class EndpointAnalyticsService
{
    public function topRequests(Collection $collection,int $limit=10): array
    {
        return $collection->sortByDesc('requests')->take($limit)->values()->all();
    }
    public function topSlow(Collection $collection,int $limit=10): array
    {
        return $collection->sortByDesc('avgResponseMs')->take($limit)->values()->all();
    }
    public function topTraffic(Collection $collection,int $limit=10): array
    {
        return $collection->sortByDesc('bytes')->take($limit)->values()->all();
    }
    public function topErrors(Collection $collection,int $limit=10): array
    {
        return $collection->sortByDesc('5xx')->take($limit)->values()->all();
    }
}