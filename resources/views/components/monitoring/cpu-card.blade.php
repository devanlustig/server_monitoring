{{-- CPU Card --}}
    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">

                <strong>CPU Monitoring</strong>

            </div>

            <div class="card-body">

                @php

                    $usage = $latestMetric?->usage_percent ?? 0;

                    if($usage < 60){

                        $color = "success";
                        $status = "Healthy";

                    }elseif($usage < 85){

                        $color = "warning";
                        $status = "Warning";

                    }else{

                        $color = "danger";
                        $status = "Critical";

                    }

                @endphp


                <div class="text-center mb-4">

                    <h1 class="display-3 fw-bold">

                        {{ $latestMetric?->usage_percent !== null ? number_format($latestMetric->usage_percent,2).'%' : '--' }}

                    </h1>

                    <span class="badge bg-{{ $color }}">

                        {{ $status }}

                    </span>

                </div>


                <div class="progress mb-4" style="height:25px;">

                    <div

                        class="progress-bar bg-{{ $color }}"

                        role="progressbar"

                        style="width: {{ $usage }}%;">

                        {{ number_format($usage,2) }}%

                    </div>

                </div>


                <table class="table table-bordered">

                    <tr>

                        <th width="40%">Load 1 Minute</th>

                        <td>{{ $latestMetric->load_1 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Load 5 Minutes</th>

                        <td>{{ $latestMetric->load_5 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Load 15 Minutes</th>

                        <td>{{ $latestMetric->load_15 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Last Update</th>

                        <td>

                            {{ $latestMetric?->collected_at?->format('d M Y H:i:s') }}

                        </td>

                    </tr>

                </table>

            </div>

        </div>

    </div>