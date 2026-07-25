

    {{-- Server Information --}}
    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                <strong>System Information</strong>
            </div>

            <div class="card-body">

                <table class="table table-sm mb-0">

                    <tr>
                        <th width="35%">Hostname</th>
                        <td>{{ $server->system_hostname }}</td>
                    </tr>

                    <tr>
                        <th>Operating System</th>
                        <td>{{ $server->operating_system }}</td>
                    </tr>

                    <tr>
                        <th>Kernel</th>
                        <td>{{ $server->kernel_version }}</td>
                    </tr>

                    <tr>
                        <th>CPU Model</th>
                        <td>{{ $server->cpu_model }}</td>
                    </tr>

                    <tr>
                        <th>CPU Core</th>
                        <td>{{ $server->cpu_core_count }}</td>
                    </tr>

                    <tr>
                        <th>Total RAM</th>
                        <td>{{ number_format($server->total_ram_bytes / 1024 / 1024 /1024,2) }} GB</td>
                    </tr>

                    <tr>
                        <th>Total Disk</th>
                        <td>{{ number_format($server->total_disk_bytes / 1024 /1024 /1024,2) }} GB</td>
                    </tr>

                    <tr>
                        <th>Last SSH</th>
                        <td>

                            {{ $server->last_successful_connection_at?->format('d M Y H:i:s') }}

                        </td>
                    </tr>

                </table>

            </div>

        </div>

    </div>