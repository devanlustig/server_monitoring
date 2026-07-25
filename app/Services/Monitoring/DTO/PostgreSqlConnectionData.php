<?php

namespace App\Services\Monitoring\DTO;

class PostgreSqlConnectionData
{
    public function __construct(
        public int $pid,
        public string $database,
        public string $user,
        public string $application,
        public string $client,
        public string $backendType,
        public string $state,
        public string $waitEventType,
        public string $waitEvent,
        public string $backendStart,
        public string $stateChange,
        public string $connectionAge,
        public string $activityDuration,
        public string $query,
    ) {
    }

    public function canTerminate(): bool
    {
        return strtolower($this->backendType) === 'client backend';
    }

    public function badgeColor(): string
    {
        return match(strtolower($this->state)) {
            'active' => 'success',
            'idle' => 'warning',
            'idle in transaction' => 'danger',
            default => 'secondary',

        };
    }

    public function connectionAgeInSeconds(): int
    {
        return $this->durationToSeconds($this->connectionAge);
    }

    public function activityDurationInSeconds(): int
    {
        return $this->durationToSeconds($this->activityDuration);
    }

    private function durationToSeconds(string $duration): int
    {
        $duration = trim($duration);
        $days = 0;

        if (preg_match('/(\d+)\s+Day/', $duration, $match)) {
            $days = (int) $match[1];
            $duration = trim(
                preg_replace('/\d+\s+Day/', '', $duration)
            );
        }

        [$hour, $minute, $second] = array_pad(
            explode(':', $duration),
            3,
            0
        );
        return
            ($days * 86400)
            + ((int)$hour * 3600)
            + ((int)$minute * 60)
            + (int)$second;
    }

    public function connectionAgeLabel(): string
    {
        return $this->humanDuration(
            $this->connectionAgeInSeconds()
        );
    }

    public function activityDurationLabel(): string
    {
        return $this->humanDuration(
            $this->activityDurationInSeconds()
        );
    }

    private function humanDuration(int $seconds): string
    {
        $days = intdiv($seconds,86400);
        $seconds %= 86400;
        $hours = intdiv($seconds,3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds,60);

        if($days>0){
            return "{$days} Day {$hours} Hour";
        }

        if($hours>0){
            return "{$hours} Hour {$minutes} Min";
        }

        if($minutes>0){
            return "{$minutes} Min";
        }

        return "{$seconds} Sec";
    }

    public function activityDurationColor(): string
    {
        $seconds = $this->activityDurationInSeconds();
        if ($this->state === 'active') {
            return 'success';
        }
        if ($seconds < 1800) {
            return 'success';
        }
        if ($seconds < 7200) {
            return 'warning';
        }
        if ($seconds < 21600) {
            return 'orange';
        }

        return 'danger';
    }

    public function stateBadgeColor(): string
    {
        return strtolower($this->state) === 'active'
            ? 'success'
            : 'warning';
    }

    public function activityBadgeColor(): string
    {
        if (strtolower($this->state) === 'active') {
            return 'success';
        }
        $seconds = $this->activityDurationInSeconds();
        if ($seconds < 1800) {
            return 'success';
        }
        if ($seconds < 7200) {
            return 'warning';
        }
        if ($seconds < 21600) {
            return 'orange';
        }
        return 'danger';
    }
}