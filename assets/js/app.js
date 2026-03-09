(function () {
    'use strict';

    const API_URL = 'ajax/handler.php';
    let csrfToken = document.getElementById('csrfToken')?.value || '';

    // ============ HELPERS ============

    function esc(str) {
        if (typeof str !== 'string') return String(str ?? '');
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function showToast(message, type = 'success') {
        const c = document.getElementById('toastContainer');
        if (!c) return;
        const id = 't' + Date.now();
        const bg = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info' }[type] || 'bg-info';
        c.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast ${bg} text-white" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${esc(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 6000 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function setBtn(btn, loading) {
        if (!btn) return;
        if (loading) {
            btn._html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        } else {
            btn.disabled = false;
            if (btn._html) btn.innerHTML = btn._html;
        }
    }

    /**
     * Format thời gian từ API - giữ nguyên thời gian gốc từ server
     * API trả về dạng: "2025-10-23T09:50:56+00:00"
     * Chỉ format hiển thị, không chuyển đổi timezone
     */
    function formatTaskTime(dateStr) {
        if (!dateStr) return '—';
        
        try {
            // Parse trực tiếp string để lấy các phần
            // Format: "2025-10-23T09:50:56+00:00"
            const match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/);
            if (!match) return dateStr;

            const [, year, month, day, hour, min, sec] = match;

            // Lấy phần timezone offset nếu có
            const tzMatch = dateStr.match(/([+-]\d{2}):?(\d{2})$/);
            let tzStr = 'UTC';
            if (tzMatch) {
                const tzHour = tzMatch[1];
                const tzMin = tzMatch[2];
                if (tzHour === '+00' && tzMin === '00') {
                    tzStr = 'UTC';
                } else {
                    tzStr = 'UTC' + tzHour + ':' + tzMin;
                }
            }

            return `${day}/${month}/${year} ${hour}:${min}:${sec} ${tzStr}`;
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Tính thời gian đã trôi qua kể từ task
     */
    function timeAgo(dateStr) {
        if (!dateStr) return '';
        
        try {
            const taskDate = new Date(dateStr);
            const now = new Date();
            const diffMs = now - taskDate;
            
            if (isNaN(diffMs) || diffMs < 0) return '';

            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHour = Math.floor(diffMin / 60);
            const diffDay = Math.floor(diffHour / 24);

            if (diffSec < 60) return diffSec + 's ago';
            if (diffMin < 60) return diffMin + 'm ago';
            if (diffHour < 24) return diffHour + 'h ago';
            if (diffDay < 30) return diffDay + 'd ago';
            return '';
        } catch (e) {
            return '';
        }
    }

    /**
     * Tính duration giữa started và finished
     */
    function taskDuration(started, finished) {
        if (!started || !finished) return '';
        
        try {
            const s = new Date(started);
            const f = new Date(finished);
            const diffMs = f - s;
            
            if (isNaN(diffMs) || diffMs < 0) return '';

            const diffSec = Math.floor(diffMs / 1000);
            
            if (diffSec < 1) return '<1s';
            if (diffSec < 60) return diffSec + 's';
            if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ' + (diffSec % 60) + 's';
            return Math.floor(diffSec / 3600) + 'h ' + Math.floor((diffSec % 3600) / 60) + 'm';
        } catch (e) {
            return '';
        }
    }

    // ============ API CALL ============

    async function api(action, data = {}, btn = null) {
        setBtn(btn, true);
        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action, csrf_token: csrfToken, ...data }),
                credentials: 'same-origin',
            });
            const json = await res.json();

            if (json.csrf_token) csrfToken = json.csrf_token;
            if (json.new_csrf) csrfToken = json.new_csrf;

            const ci = document.getElementById('csrfToken');
            if (ci) ci.value = csrfToken;

            if (!res.ok || !json.success) throw new Error(json.error || 'Request failed');
            return json;
        } catch (e) {
            showToast(e.message, 'danger');
            throw e;
        } finally {
            setBtn(btn, false);
        }
    }

    // ============ CONFIRM ============

    function confirm2(title, msg) {
        return new Promise(resolve => {
            const m = document.getElementById('confirmModal');
            if (!m) return resolve(confirm(msg));

            document.getElementById('confirmModalTitle').textContent = title;
            document.getElementById('confirmModalBody').textContent = msg;

            const bm = new bootstrap.Modal(m);
            const cb = document.getElementById('confirmModalBtn');

            function yes() { bm.hide(); cb.removeEventListener('click', yes); resolve(true); }
            function closed() { m.removeEventListener('hidden.bs.modal', closed); cb.removeEventListener('click', yes); resolve(false); }

            cb.addEventListener('click', yes);
            m.addEventListener('hidden.bs.modal', closed);
            bm.show();
        });
    }

    // ============ DASHBOARD POWER ============

    document.querySelectorAll('.power-action').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const action = this.dataset.action;
            const sid = this.dataset.server;
            const labels = { boot: 'Boot', restart: 'Restart', shutdown: 'Shutdown', powerOff: 'Force Power Off' };

            if (!await confirm2('Confirm', `${labels[action] || action} this server?`)) return;

            try {
                const r = await api(action, { server_id: sid }, this);
                const tid = r.data?.data?.task?.id;
                showToast(`${labels[action]} sent${tid ? ' — Task #' + tid : ''}`, 'success');
            } catch (e) {}
        });
    });

    // ============ SERVER DETAIL ACTIONS ============

    document.querySelectorAll('.server-action').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const action = this.dataset.action;
            const sid = document.getElementById('serverId')?.value;
            if (!sid) return;

            const cm = this.dataset.confirm;
            if (cm && !await confirm2('Confirm', cm)) return;

            try {
                const r = await api(action, { server_id: sid }, this);

                if (action === 'resetPassword' && r.data?.data?.expectedPassword) {
                    const pw = r.data.data.expectedPassword;
                    showToast('Password reset successful!', 'success');

                    const box = document.createElement('div');
                    box.className = 'alert alert-warning alert-dismissible fade show mt-3';
                    box.innerHTML = `
                        <strong><i class="bi bi-key"></i> New Password:</strong>
                        <code style="font-size:1.1em;user-select:all;cursor:pointer" 
                              onclick="navigator.clipboard?.writeText('${esc(pw)}');alert('Copied!')">${esc(pw)}</code>
                        <small class="d-block mt-1" style="color:var(--accent-yellow)">Click to copy. Save this password now!</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    const container = document.querySelector('.main-content .container-fluid');
                    container.insertBefore(box, container.children[1]);
                } else {
                    const tid = r.data?.data?.task?.id;
                    showToast(`"${action}" sent${tid ? ' — Task #' + tid : ''}`, 'success');
                }

                setTimeout(loadTasks, 2000);
            } catch (e) {}
        });
    });

    // ============ RENAME ============

    document.getElementById('changeNameBtn')?.addEventListener('click', async function () {
        const name = document.getElementById('newServerName')?.value?.trim();
        const sid = document.getElementById('serverId')?.value;
        if (!name || !sid) return showToast('Enter a name', 'warning');

        try {
            await api('changeName', { server_id: sid, name }, this);
            showToast('Name updated', 'success');
            const el = document.getElementById('serverName');
            if (el) el.textContent = name;
        } catch (e) {}
    });

    // ============ SETTINGS ============

    document.getElementById('saveSettingsBtn')?.addEventListener('click', async function () {
        const sid = document.getElementById('serverId')?.value;
        if (!sid) return;
        try {
            await api('updateSettings', {
                server_id: sid,
                bootType: document.getElementById('bootType')?.value,
            }, this);
            showToast('Settings saved', 'success');
        } catch (e) {}
    });

    document.getElementById('saveBootOrderBtn')?.addEventListener('click', async function () {
        const sid = document.getElementById('serverId')?.value;
        if (!sid) return;
        try {
            await api('setBootOrder', {
                server_id: sid,
                order: document.getElementById('bootOrder')?.value,
            }, this);
            showToast('Boot order updated', 'success');
        } catch (e) {}
    });

    // ============ VNC DETAILS ============

    document.getElementById('vncDetailsBtn')?.addEventListener('click', async function () {
        const sid = document.getElementById('serverId')?.value;
        if (!sid) return;

        const card = document.getElementById('vncDetailsCard');
        const body = document.getElementById('vncDetailsBody');

        body.innerHTML = '<div class="text-center py-3" style="color:var(--text-muted)"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
        card.classList.remove('d-none');

        try {
            const r = await api('vncDetails', { server_id: sid }, this);
            const v = r.data?.data;

            if (!v) {
                body.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> VNC not enabled. Enable it first.</div>';
                return;
            }

            body.innerHTML = `
                <table class="table table-sm mb-0">
                    <tr><th>IP</th><td><code>${esc(v.ip || '—')}</code></td></tr>
                    <tr><th>Port</th><td><code>${esc(String(v.port || '—'))}</code></td></tr>
                    <tr>
                        <th>Password</th>
                        <td>
                            <code style="user-select:all;cursor:pointer;font-size:1em" 
                                  onclick="navigator.clipboard?.writeText('${esc(v.password || '')}');alert('Copied!')"
                                  title="Click to copy">${esc(v.password || '—')}</code>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge ${v.enabled ? 'bg-success' : 'bg-danger'}">${v.enabled ? 'Enabled' : 'Disabled'}</span></td>
                    </tr>
                    ${v.wss?.url ? `<tr><th>Web VNC</th><td><code style="word-break:break-all;font-size:0.7rem">${esc(v.wss.url)}</code></td></tr>` : ''}
                </table>
                ${v.enabled ? `<div class="mt-3"><small style="color:var(--text-muted)"><i class="bi bi-info-circle"></i> Connect: <code>${esc(v.ip)}:${esc(String(v.port))}</code></small></div>` : 
                `<div class="mt-3"><small style="color:var(--accent-yellow)"><i class="bi bi-exclamation-triangle"></i> VNC disabled. Toggle to enable.</small></div>`}
            `;
        } catch (e) {
            body.innerHTML = `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`;
        }
    });

    // ============ TASKS - FIXED TIME ============

    async function loadTasks() {
        const container = document.getElementById('tasksContainer');
        const sid = document.getElementById('serverId')?.value;
        if (!container || !sid) return;

        container.innerHTML = '<div class="text-center py-4" style="color:var(--text-muted)"><div class="spinner-border spinner-border-sm"></div> Loading tasks...</div>';

        try {
            const r = await api('getTasks', { server_id: sid });
            const tasks = r.data?.data || [];

            if (!tasks.length) {
                container.innerHTML = '<div class="text-center py-4" style="color:var(--text-muted)"><i class="bi bi-inbox"></i> No tasks found</div>';
                return;
            }

            let html = '';
            tasks.slice(0, 20).forEach(task => {
                const ok = task.success === true;
                const done = task.completed === true;
                const pending = !done;

                let iconClass, statusBg, statusText;
                if (pending) {
                    iconClass = 'bi-hourglass-split';
                    statusBg = 'bg-warning';
                    statusText = task.status || 'pending';
                } else if (ok) {
                    iconClass = 'bi-check-circle-fill';
                    statusBg = 'bg-success';
                    statusText = 'complete';
                } else {
                    iconClass = 'bi-x-circle-fill';
                    statusBg = 'bg-danger';
                    statusText = 'failed';
                }

                // Format action name đẹp hơn
                const actionName = (task.action || 'unknown')
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());

                // Thời gian bắt đầu - giữ nguyên từ API
                const startTime = formatTaskTime(task.started);
                
                // Thời gian kết thúc
                const endTime = task.finished ? formatTaskTime(task.finished) : '';
                
                // Duration
                const duration = taskDuration(task.started, task.finished);
                
                // Time ago
                const ago = timeAgo(task.finished || task.started);

                html += `
                    <div class="task-item">
                        <div class="d-flex align-items-start gap-3 flex-grow-1">
                            <i class="bi ${iconClass} mt-1" style="font-size:1.1rem;color:var(${
                                pending ? '--accent-yellow' : ok ? '--accent-green' : '--accent-red'
                            })"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="task-action">${esc(actionName)}</span>
                                    <span class="badge ${statusBg}">${esc(statusText)}</span>
                                </div>
                                <div class="task-time mt-1">
                                    <i class="bi bi-clock me-1"></i>${esc(startTime)}
                                    ${duration ? `<span class="ms-2"><i class="bi bi-stopwatch me-1"></i>${esc(duration)}</span>` : ''}
                                    ${ago ? `<span class="ms-2" style="color:var(--accent-blue)">${esc(ago)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = '<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle"></i> Failed to load tasks</div>';
        }
    }

    // Load tasks 1 lần duy nhất khi mở trang, không auto refresh
    if (document.getElementById('tasksContainer')) {
        setTimeout(loadTasks, 500);
    }

    // Chỉ refresh khi bấm nút
    document.getElementById('refreshTasks')?.addEventListener('click', loadTasks);
    window.loadTasks = loadTasks;

    // ============ BUILD FORM ============

    const confirmBuild = document.getElementById('confirmBuild');
    const buildBtn = document.getElementById('buildBtn');

    if (confirmBuild && buildBtn) {
        confirmBuild.addEventListener('change', () => { buildBtn.disabled = !confirmBuild.checked; });
    }

    const buildForm = document.getElementById('buildForm');
    const methodSel = document.getElementById('buildMethod');
    const tplGroup = document.getElementById('templateGroup');

    if (methodSel && tplGroup) {
        methodSel.addEventListener('change', function () {
            tplGroup.style.display = this.value === 'self' ? 'none' : '';
            const tplId = document.getElementById('templateId');
            if (tplId) tplId.required = this.value !== 'self';
        });
    }

    if (buildForm) {
        buildForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!await confirm2('⚠️ Build Server', 'ALL DATA will be erased. This cannot be undone. Continue?')) return;

            const sid = document.getElementById('buildServerId')?.value;
            if (!sid) return;

            const keys = [];
            document.querySelectorAll('.ssh-key-check:checked').forEach(cb => keys.push(parseInt(cb.value)));

            try {
                const r = await api('build', {
                    server_id: sid,
                    method: document.getElementById('buildMethod').value,
                    templateId: parseInt(document.getElementById('templateId')?.value) || 0,
                    hostname: document.getElementById('buildHostname')?.value?.trim() || '',
                    name: document.getElementById('buildName')?.value?.trim() || '',
                    timezone: document.getElementById('buildTimezone')?.value || 'UTC',
                    swap: parseInt(document.getElementById('buildSwap')?.value) || 0,
                    ipv6: document.getElementById('buildIpv6')?.checked || false,
                    sshKeys: keys,
                    userData: document.getElementById('buildUserData')?.value || '',
                }, buildBtn);

                const tid = r.data?.data?.task?.id;
                showToast(`Build started${tid ? ' — Task #' + tid : ''}`, 'success');
                setTimeout(() => { window.location.href = 'index.php?page=server-detail&id=' + sid; }, 3000);
            } catch (e) {}
        });
    }

    // ============ TEST CONNECTION ============

    document.getElementById('testConnection')?.addEventListener('click', async function () {
        const rd = document.getElementById('connectionResult');
        try {
            await api('testConnection', {}, this);
            rd.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Connected!</div>';
        } catch (e) {
            rd.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Failed</div>';
        }
    });

})();