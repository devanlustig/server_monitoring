<!-- Storage Growth Detail Modal (Stable Root-Level Element) -->
<div class="modal fade" id="disk-growth-detail-modal" tabindex="-1" aria-labelledby="diskGrowthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-white border-bottom">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="diskGrowthModalLabel">
                        <i class="bi bi-folder-symlink-fill text-primary me-2"></i>Storage Growth Detail
                    </h5>
                    <small class="text-muted" id="disk-modal-subtitle">Loading...</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Breadcrumb Navigation for Drilldown -->
                <div class="d-flex align-items-center justify-content-between mb-3" id="disk-modal-nav" style="display: none !important;">
                    <button class="btn btn-sm btn-outline-secondary fw-semibold" id="btn-disk-back-to-dirs">
                        <i class="bi bi-arrow-left me-1"></i> Back to Top Directories
                    </button>
                    <span class="badge bg-light text-dark border px-2 py-1 text-truncate" style="max-width: 400px;" id="disk-current-dir-label">/</span>
                </div>

                <!-- Spinner -->
                <div id="disk-modal-spinner" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted small mt-2">Analyzing filesystem growth delta...</div>
                </div>

                <!-- Error Alert Area -->
                <div id="disk-modal-error" class="alert alert-danger mb-0" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="disk-modal-error-text">Unable to load storage growth details.</span>
                </div>

                <!-- Directories View (Level 1) -->
                <div id="disk-modal-level1" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 border rounded">
                            <thead class="table-light">
                                <tr>
                                    <th>Top Directory</th>
                                    <th class="text-end">Current Size</th>
                                    <th class="text-end">Previous Size</th>
                                    <th class="text-end">Growth Delta</th>
                                    <th class="text-center" style="width: 70px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="disk-directories-tbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Files View (Level 2) -->
                <div id="disk-modal-level2" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 border rounded">
                            <thead class="table-light">
                                <tr>
                                    <th>Top File Growth</th>
                                    <th class="text-end">Current Size</th>
                                    <th class="text-end">Previous Size</th>
                                    <th class="text-end">Growth Delta</th>
                                </tr>
                            </thead>
                            <tbody id="disk-files-tbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentServerId = null;
    let currentDate = null;

    // Document-level event delegation for opening modal on growth click
    document.addEventListener('click', function (e) {
        const growthBtn = e.target.closest('.btn-disk-growth-detail');
        if (growthBtn) {
            currentServerId = growthBtn.getAttribute('data-server-id');
            currentDate = growthBtn.getAttribute('data-date');
            const dateFormatted = growthBtn.getAttribute('data-date-formatted');
            const growthFormattedVal = growthBtn.getAttribute('data-growth-formatted');

            const modalEl = document.getElementById('disk-growth-detail-modal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                document.getElementById('disk-modal-subtitle').innerText = `Date: ${dateFormatted} | Growth: ${growthFormattedVal}`;
                loadLevel1Directories(currentServerId, currentDate);
                modal.show();
            }
            return;
        }

        // Document-level event delegation for Level 2 action button click [>]
        const inspectBtn = e.target.closest('.btn-inspect-dir');
        if (inspectBtn) {
            const dirPath = inspectBtn.getAttribute('data-path');
            if (dirPath && currentServerId && currentDate) {
                loadLevel2Files(currentServerId, currentDate, dirPath);
            }
            return;
        }

        // Back to top directories button
        const backBtn = e.target.closest('#btn-disk-back-to-dirs');
        if (backBtn) {
            showLevel1();
            return;
        }
    });

    function loadLevel1Directories(serverId, date) {
        showSpinner();

        fetch(`/servers/${serverId}/disk/growth-detail?date=${date}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP error ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);

                const tbody = document.getElementById('disk-directories-tbody');
                tbody.innerHTML = '';

                if (!data.directories || data.directories.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-info-circle me-1"></i>No directory growth snapshot available for this date.</td></tr>`;
                } else {
                    data.directories.forEach(dir => {
                        const tr = document.createElement('tr');
                        const isOther = dir.path.startsWith('Other');
                        
                        let growthHtml = '';
                        if (dir.growthBytes === null) {
                            growthHtml = `<span class="badge bg-light text-secondary border">N/A</span>`;
                        } else if (dir.growthBytes > 0) {
                            growthHtml = `<span class="text-danger fw-bold"><i class="bi bi-arrow-up-right me-1"></i>${dir.growthFormatted}</span>`;
                        } else if (dir.growthBytes < 0) {
                            growthHtml = `<span class="text-success fw-bold"><i class="bi bi-arrow-down-right me-1"></i>${dir.growthFormatted}</span>`;
                        } else {
                            growthHtml = `<span class="text-muted">${dir.growthFormatted}</span>`;
                        }

                        let prevSizeHtml = dir.previousSizeBytes === null ? `<span class="badge bg-light text-secondary border">N/A</span>` : dir.previousSizeFormatted;

                        tr.innerHTML = `
                            <td class="fw-semibold text-dark text-break">${dir.path}</td>
                            <td class="text-end text-muted small">${dir.currentSizeFormatted}</td>
                            <td class="text-end text-muted small">${prevSizeHtml}</td>
                            <td class="text-end">${growthHtml}</td>
                            <td class="text-center">
                                ${!isOther ? `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-inspect-dir" data-path="${escapeHtml(dir.path)}" title="Inspect Top Files"><i class="bi bi-chevron-right"></i></button>` : ''}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                showLevel1();
            })
            .catch(err => {
                console.error('Storage Growth Detail Level 1 Error:', err);
                showError('Unable to load directory growth details. ' + err.message);
            });
    }

    function loadLevel2Files(serverId, date, directory) {
        showSpinner();

        fetch(`/servers/${serverId}/disk/growth-detail/files?date=${date}&directory=${encodeURIComponent(directory)}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP error ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);

                const tbody = document.getElementById('disk-files-tbody');
                tbody.innerHTML = '';

                document.getElementById('disk-current-dir-label').innerText = directory;

                if (!data.files || data.files.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-info-circle me-1"></i>No file growth snapshot available for this directory.</td></tr>`;
                } else {
                    data.files.forEach(f => {
                        const tr = document.createElement('tr');
                        
                        let growthHtml = '';
                        if (f.growthBytes === null) {
                            growthHtml = `<span class="badge bg-light text-secondary border">N/A</span>`;
                        } else if (f.growthBytes > 0) {
                            growthHtml = `<span class="text-danger fw-bold"><i class="bi bi-arrow-up-right me-1"></i>${f.growthFormatted}</span>`;
                        } else if (f.growthBytes < 0) {
                            growthHtml = `<span class="text-success fw-bold"><i class="bi bi-arrow-down-right me-1"></i>${f.growthFormatted}</span>`;
                        } else {
                            growthHtml = `<span class="text-muted">${f.growthFormatted}</span>`;
                        }

                        let prevSizeHtml = f.previousSizeBytes === null ? `<span class="badge bg-light text-secondary border">N/A</span>` : f.previousSizeFormatted;

                        tr.innerHTML = `
                            <td>
                                <div class="fw-semibold text-dark text-break">${f.filename}</div>
                                <div class="text-muted small text-truncate" style="max-width: 380px;" title="${escapeHtml(f.path)}">${f.path}</div>
                                ${f.database_name ? `<div class="text-muted small mt-1"><i class="bi bi-database me-1"></i>Database: ${escapeHtml(f.database_name)}</div>` : ''}
                            </td>
                            <td class="text-end text-muted small">${f.currentSizeFormatted}</td>
                            <td class="text-end text-muted small">${prevSizeHtml}</td>
                            <td class="text-end">${growthHtml}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                showLevel2();
            })
            .catch(err => {
                console.error('Storage Growth Detail Level 2 Error:', err);
                showError('Unable to load file growth details. ' + err.message);
            });
    }

    function showSpinner() {
        document.getElementById('disk-modal-spinner').style.display = 'block';
        document.getElementById('disk-modal-error').style.display = 'none';
        document.getElementById('disk-modal-level1').style.display = 'none';
        document.getElementById('disk-modal-level2').style.display = 'none';
        document.getElementById('disk-modal-nav').setAttribute('style', 'display: none !important;');
    }

    function showLevel1() {
        document.getElementById('disk-modal-spinner').style.display = 'none';
        document.getElementById('disk-modal-error').style.display = 'none';
        document.getElementById('disk-modal-level1').style.display = 'block';
        document.getElementById('disk-modal-level2').style.display = 'none';
        document.getElementById('disk-modal-nav').setAttribute('style', 'display: none !important;');
    }

    function showLevel2() {
        document.getElementById('disk-modal-spinner').style.display = 'none';
        document.getElementById('disk-modal-error').style.display = 'none';
        document.getElementById('disk-modal-level1').style.display = 'none';
        document.getElementById('disk-modal-level2').style.display = 'block';
        document.getElementById('disk-modal-nav').setAttribute('style', 'display: flex !important;');
    }

    function showError(message) {
        document.getElementById('disk-modal-spinner').style.display = 'none';
        document.getElementById('disk-modal-level1').style.display = 'none';
        document.getElementById('disk-modal-level2').style.display = 'none';
        document.getElementById('disk-modal-nav').setAttribute('style', 'display: none !important;');
        document.getElementById('disk-modal-error-text').innerText = message;
        document.getElementById('disk-modal-error').style.display = 'block';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
})();
</script>
