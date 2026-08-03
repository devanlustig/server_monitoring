<!-- System Information -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white pt-4 pb-3 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>System Specifications</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle info-table mb-0">
                    <tbody>
                        <tr>
                            <th><i class="bi bi-pc-display me-2 text-muted"></i>System Hostname</th>
                            <td>{{ $server->system_hostname ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-terminal me-2 text-muted"></i>Operating System</th>
                            <td>{{ $server->operating_system ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-gear-wide-connected me-2 text-muted"></i>Kernel Version</th>
                            <td><code>{{ $server->kernel_version ?: '-' }}</code></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-cpu me-2 text-muted"></i>CPU Model</th>
                            <td>{{ $server->cpu_model ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-boxes me-2 text-muted"></i>CPU Cores</th>
                            <td><span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1">{{ $server->cpu_core_count ?: '-' }} Cores</span></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-memory me-2 text-muted"></i>Total RAM</th>
                            <td>{{ $server->total_ram_bytes ? number_format($server->total_ram_bytes / 1073741824, 2) . ' GB' : '-' }}</td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-hdd me-2 text-muted"></i>Total Disk Space</th>
                            <td>{{ $server->total_disk_bytes ? number_format($server->total_disk_bytes / 1073741824, 2) . ' GB' : '-' }}</td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-shield-check me-2 text-muted"></i>Last SSH Sync</th>
                            <td>{{ $server->last_successful_connection_at?->format('d M Y H:i:s') ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
