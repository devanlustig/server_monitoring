<!-- Memory Usage Detail Modal -->
<div class="modal fade" id="memoryDetailModal" tabindex="-1" aria-labelledby="memoryDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold" id="memoryDetailModalLabel">
                    <i class="bi bi-memory text-info me-2"></i>RAM Usage Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0">
                <!-- Loading State -->
                <div id="memory-modal-spinner" class="py-5 text-center">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3 text-muted">Retrieving live memory information from server...</div>
                </div>

                <!-- Error State -->
                <div id="memory-modal-error" class="p-4" style="display: none;">
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="memory-modal-error-text"></span>
                    </div>
                </div>

                <!-- Content State -->
                <div id="memory-modal-content" class="p-4" style="display: none;">
                    
                    <!-- Analysis Summary -->
                    <div class="alert mb-4 border" id="memory-analysis-alert">
                        <div class="d-flex align-items-center">
                            <i class="bi fs-3 me-3" id="memory-analysis-icon"></i>
                            <div>
                                <h6 class="fw-bold mb-1" id="memory-analysis-title">Memory Status</h6>
                                <div class="mb-0 text-dark" id="memory-analysis-summary"></div>
                                <div class="small text-muted mt-1" id="memory-analysis-top-process"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- System Memory Breakdown -->
                        <div class="col-lg-5">
                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">SYSTEM MEMORY BREAKDOWN</h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm border align-middle">
                                    <tbody id="memory-breakdown-tbody">
                                        <!-- Populated via JS -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Free RAM</strong> is completely unused memory. <strong>Available RAM</strong> is the memory ready for new applications (includes reclaimable Cache).
                            </div>
                        </div>

                        <!-- Top Processes -->
                        <div class="col-lg-7">
                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">TOP 15 MEMORY-CONSUMING PROCESSES</h6>
                            <div class="table-responsive border rounded">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="small text-muted">PID</th>
                                            <th class="small text-muted">PROCESS NAME</th>
                                            <th class="small text-muted">USER</th>
                                            <th class="small text-muted text-end">RSS</th>
                                            <th class="small text-muted text-end">% MEM</th>
                                        </tr>
                                    </thead>
                                    <tbody id="memory-processes-tbody">
                                        <!-- Populated via JS -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>RSS (Resident Set Size)</strong> is the non-swapped physical memory used. Summing RSS may exceed Total RAM due to shared memory pages between processes.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.openMemoryDetailModal = function(serverId) {
    const modalEl = document.getElementById('memoryDetailModal');
    const modal = new bootstrap.Modal(modalEl);
    
    // Reset views
    document.getElementById('memory-modal-spinner').style.display = 'block';
    document.getElementById('memory-modal-error').style.display = 'none';
    document.getElementById('memory-modal-content').style.display = 'none';
    
    modal.show();

    fetch(`/servers/${serverId}/memory/detail`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP error ${res.status}`);
            return res.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Unknown error occurred.');
            }

            // Populate Breakdown
            const breakdown = data.breakdown;
            const tbodyBreakdown = document.getElementById('memory-breakdown-tbody');
            tbodyBreakdown.innerHTML = `
                <tr><td class="text-muted">Total RAM</td><td class="text-end fw-bold">${formatBytes(breakdown.total)}</td></tr>
                <tr><td class="text-muted">Used RAM</td><td class="text-end fw-bold">${formatBytes(breakdown.used)}</td></tr>
                <tr class="bg-light"><td class="text-muted">Available RAM</td><td class="text-end fw-bold text-success">${formatBytes(breakdown.available)}</td></tr>
                <tr><td class="text-muted">Free RAM</td><td class="text-end">${formatBytes(breakdown.free)}</td></tr>
                <tr><td class="text-muted">Cache</td><td class="text-end">${formatBytes(breakdown.cache)}</td></tr>
                <tr><td class="text-muted">Buffers</td><td class="text-end">${formatBytes(breakdown.buffers)}</td></tr>
                <tr><td class="text-muted">Shared Memory</td><td class="text-end">${formatBytes(breakdown.shared)}</td></tr>
                <tr class="border-top"><td class="text-muted">Swap Total</td><td class="text-end">${formatBytes(breakdown.swap_total)}</td></tr>
                <tr><td class="text-muted">Swap Used</td><td class="text-end ${breakdown.swap_used > 0 ? 'text-warning fw-bold' : ''}">${formatBytes(breakdown.swap_used)}</td></tr>
                <tr><td class="text-muted">Swap Available</td><td class="text-end">${formatBytes(breakdown.swap_free)}</td></tr>
            `;

            // Populate Processes
            const tbodyProcesses = document.getElementById('memory-processes-tbody');
            tbodyProcesses.innerHTML = '';
            
            if (!data.processes || data.processes.length === 0) {
                tbodyProcesses.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No process data available</td></tr>`;
            } else {
                data.processes.forEach(proc => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-muted small">${escapeHtml(proc.pid)}</td>
                        <td class="fw-semibold">${escapeHtml(proc.name)}</td>
                        <td class="text-muted small">${escapeHtml(proc.user)}</td>
                        <td class="text-end fw-semibold text-danger">${formatBytes(proc.rss_bytes)}</td>
                        <td class="text-end text-muted">${proc.mem_percent}%</td>
                    `;
                    tbodyProcesses.appendChild(tr);
                });
            }

            // Populate Analysis
            const alertBox = document.getElementById('memory-analysis-alert');
            const icon = document.getElementById('memory-analysis-icon');
            const summary = document.getElementById('memory-analysis-summary');
            const topProc = document.getElementById('memory-analysis-top-process');

            if (data.analysis.has_pressure) {
                alertBox.className = 'alert mb-4 border alert-warning';
                icon.className = 'bi fs-3 me-3 bi-exclamation-triangle-fill text-warning';
            } else {
                alertBox.className = 'alert mb-4 border alert-success';
                icon.className = 'bi fs-3 me-3 bi-check-circle-fill text-success';
            }

            summary.innerText = data.analysis.summary;
            if (data.analysis.top_process_note) {
                topProc.style.display = 'block';
                topProc.innerHTML = `<i class="bi bi-info-circle me-1"></i>${escapeHtml(data.analysis.top_process_note)}`;
            } else {
                topProc.style.display = 'none';
            }

            // Show Content
            document.getElementById('memory-modal-spinner').style.display = 'none';
            document.getElementById('memory-modal-content').style.display = 'block';
        })
        .catch(err => {
            document.getElementById('memory-modal-spinner').style.display = 'none';
            document.getElementById('memory-modal-error-text').innerText = err.message;
            document.getElementById('memory-modal-error').style.display = 'block';
        });
};

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
