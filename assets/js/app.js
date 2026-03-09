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

    function showToast(message, type) {
        type = type || 'success';
        const c = document.getElementById('toastContainer');
        if (!c) return;
        const id = 't' + Date.now();
        const bgMap = { success:'bg-success', danger:'bg-danger', warning:'bg-warning text-dark', info:'bg-info' };
        const bg = bgMap[type] || 'bg-info';
        c.insertAdjacentHTML('beforeend',
            '<div id="'+id+'" class="toast '+bg+' text-white" role="alert">' +
            '<div class="d-flex"><div class="toast-body">'+esc(message)+'</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
            '</div></div>'
        );
        var el = document.getElementById(id);
        var toast = new bootstrap.Toast(el, { delay: 6000 });
        toast.show();
        el.addEventListener('hidden.bs.toast', function() { el.remove(); });
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

    function formatApiTime(raw) {
        if (!raw) return '—';
        return raw.replace('T', ' ').replace(/\+00:00$/, ' UTC').replace(/\+(\d{2}):(\d{2})$/, ' UTC+$1:$2');
    }

    function calcDuration(started, finished) {
        if (!started || !finished) return '';
        try {
            var ms = new Date(finished).getTime() - new Date(started).getTime();
            if (isNaN(ms) || ms < 0) return '';
            var sec = Math.floor(ms / 1000);
            if (sec < 1) return '<1s';
            if (sec < 60) return sec + 's';
            if (sec < 3600) return Math.floor(sec/60) + 'm ' + (sec%60) + 's';
            return Math.floor(sec/3600) + 'h ' + Math.floor((sec%3600)/60) + 'm';
        } catch(e) { return ''; }
    }

    function calcAgo(dateStr) {
        if (!dateStr) return '';
        try {
            var ms = Date.now() - new Date(dateStr).getTime();
            if (isNaN(ms) || ms < 0) return '';
            var sec = Math.floor(ms/1000);
            var min = Math.floor(sec/60);
            var hr = Math.floor(min/60);
            var day = Math.floor(hr/24);
            if (sec < 60) return sec + 's ago';
            if (min < 60) return min + 'm ago';
            if (hr < 24) return hr + 'h ago';
            return day + 'd ago';
        } catch(e) { return ''; }
    }

    // ============ API ============

    async function api(action, data, btn) {
        data = data || {};
        btn = btn || null;
        setBtn(btn, true);
        try {
            var res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
                body: JSON.stringify(Object.assign({ action: action, csrf_token: csrfToken }, data)),
                credentials: 'same-origin'
            });
            var json = await res.json();

            // Cập nhật CSRF nếu server trả về
            if (json.csrf_token) {
                csrfToken = json.csrf_token;
                var ci = document.getElementById('csrfToken');
                if (ci) ci.value = csrfToken;
            }

            if (!res.ok || !json.success) {
                throw new Error(json.error || 'Request failed (HTTP ' + res.status + ')');
            }
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
        return new Promise(function(resolve) {
            var m = document.getElementById('confirmModal');
            if (!m) return resolve(confirm(msg));

            document.getElementById('confirmModalTitle').textContent = title;
            document.getElementById('confirmModalBody').textContent = msg;

            var bm = new bootstrap.Modal(m);
            var cb = document.getElementById('confirmModalBtn');

            function yes() { bm.hide(); cb.removeEventListener('click', yes); resolve(true); }
            function closed() { m.removeEventListener('hidden.bs.modal', closed); cb.removeEventListener('click', yes); resolve(false); }

            cb.addEventListener('click', yes);
            m.addEventListener('hidden.bs.modal', closed);
            bm.show();
        });
    }

    // ============ DASHBOARD POWER ============

    document.querySelectorAll('.power-action').forEach(function(btn) {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            var action = this.dataset.action;
            var sid = this.dataset.server;
            var labels = { boot:'Boot', restart:'Restart', shutdown:'Shutdown', powerOff:'Force Power Off' };

            if (!(await confirm2('Confirm', (labels[action]||action) + ' this server?'))) return;

            try {
                var r = await api(action, { server_id: sid }, this);
                var tid = r.data && r.data.data && r.data.data.task ? r.data.data.task.id : null;
                showToast((labels[action]||action) + ' sent' + (tid ? ' — Task #'+tid : ''), 'success');
            } catch(e) {}
        });
    });

    // ============ SERVER ACTIONS ============

    document.querySelectorAll('.server-action').forEach(function(btn) {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            var action = this.dataset.action;
            var sid = document.getElementById('serverId');
            if (!sid) return;
            sid = sid.value;

            var cm = this.dataset.confirm;
            if (cm && !(await confirm2('Confirm', cm))) return;

            try {
                var r = await api(action, { server_id: sid }, this);

                if (action === 'resetPassword' && r.data && r.data.data && r.data.data.expectedPassword) {
                    var pw = r.data.data.expectedPassword;
                    showToast('Password reset successful!', 'success');
                    var box = document.createElement('div');
                    box.className = 'alert alert-warning alert-dismissible fade show mt-3';
                    box.innerHTML = '<strong><i class="bi bi-key"></i> New Password:</strong> ' +
                        '<code style="font-size:1.1em;user-select:all;cursor:pointer" ' +
                        'onclick="navigator.clipboard&&navigator.clipboard.writeText(this.textContent);alert(\'Copied!\')">' +
                        esc(pw) + '</code>' +
                        '<small class="d-block mt-1" style="color:var(--accent-yellow)">Click code to copy. Save now!</small>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    var container = document.querySelector('.main-content .container-fluid');
                    if (container.children[1]) {
                        container.insertBefore(box, container.children[1]);
                    } else {
                        container.appendChild(box);
                    }
                } else {
                    var tid = r.data && r.data.data && r.data.data.task ? r.data.data.task.id : null;
                    showToast('"' + action + '" sent' + (tid ? ' — Task #'+tid : ''), 'success');
                }

                setTimeout(loadTasks, 2000);
            } catch(e) {}
        });
    });

    // ============ RENAME ============

    var changeNameBtn = document.getElementById('changeNameBtn');
    if (changeNameBtn) {
        changeNameBtn.addEventListener('click', async function() {
            var inp = document.getElementById('newServerName');
            var name = inp ? inp.value.trim() : '';
            var sid = document.getElementById('serverId');
            if (!name || !sid) return showToast('Enter a name', 'warning');

            try {
                await api('changeName', { server_id: sid.value, name: name }, this);
                showToast('Name updated', 'success');
                var el = document.getElementById('serverName');
                if (el) el.textContent = name;
            } catch(e) {}
        });
    }

    // ============ SETTINGS ============

    var saveSettingsBtn = document.getElementById('saveSettingsBtn');
    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            if (!sid) return;
            try {
                await api('updateSettings', {
                    server_id: sid.value,
                    bootType: document.getElementById('bootType').value
                }, this);
                showToast('Settings saved', 'success');
            } catch(e) {}
        });
    }

    var saveBootOrderBtn = document.getElementById('saveBootOrderBtn');
    if (saveBootOrderBtn) {
        saveBootOrderBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            if (!sid) return;
            try {
                await api('setBootOrder', {
                    server_id: sid.value,
                    order: document.getElementById('bootOrder').value
                }, this);
                showToast('Boot order updated', 'success');
            } catch(e) {}
        });
    }

    // ============ VNC DETAILS ============

    var vncDetailsBtn = document.getElementById('vncDetailsBtn');
    if (vncDetailsBtn) {
        vncDetailsBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            if (!sid) return;

            var card = document.getElementById('vncDetailsCard');
            var body = document.getElementById('vncDetailsBody');

            body.innerHTML = '<div class="text-center py-3" style="color:var(--text-muted)"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
            card.classList.remove('d-none');

            try {
                var r = await api('vncDetails', { server_id: sid.value }, this);
                var v = r.data ? r.data.data : null;

                if (!v) {
                    body.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> VNC not enabled.</div>';
                    return;
                }

                body.innerHTML =
                    '<table class="table table-sm mb-0">' +
                    '<tr><th>IP</th><td><code>' + esc(v.ip||'—') + '</code></td></tr>' +
                    '<tr><th>Port</th><td><code>' + esc(String(v.port||'—')) + '</code></td></tr>' +
                    '<tr><th>Password</th><td><code style="user-select:all;cursor:pointer;font-size:1em" ' +
                    'onclick="navigator.clipboard&&navigator.clipboard.writeText(this.textContent);alert(\'Copied!\')" ' +
                    'title="Click to copy">' + esc(v.password||'—') + '</code></td></tr>' +
                    '<tr><th>Status</th><td><span class="badge ' + (v.enabled?'bg-success':'bg-danger') + '">' +
                    (v.enabled?'Enabled':'Disabled') + '</span></td></tr>' +
                    (v.wss && v.wss.url ? '<tr><th>Web VNC</th><td><a href="'+esc(v.wss.url)+'" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info"><i class="bi bi-box-arrow-up-right"></i> Open noVNC</a></td></tr>' : '') +
                    '</table>' +
                    (v.enabled ? '<div class="mt-3"><small style="color:var(--text-muted)"><i class="bi bi-info-circle"></i> VNC Client: <code>'+esc(v.ip)+':'+esc(String(v.port))+'</code></small></div>' : '');
            } catch(e) {
                body.innerHTML = '<div class="alert alert-danger mb-0">' + esc(e.message) + '</div>';
            }
        });
    }

    // ============ ISO MANAGEMENT ============

    var loadIsosBtn = document.getElementById('loadIsosBtn');
    if (loadIsosBtn) {
        loadIsosBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            if (!sid) return;

            var container = document.getElementById('isoListContainer');
            var select = document.getElementById('isoSelect');

            try {
                var r = await api('getISOs', { server_id: sid.value }, this);
                var isos = r.data && r.data.data ? r.data.data : [];

                select.innerHTML = '<option value="">— Select ISO —</option>';
                if (isos.length === 0) {
                    select.innerHTML = '<option value="">No ISOs available</option>';
                } else {
                    isos.forEach(function(iso) {
                        var opt = document.createElement('option');
                        opt.value = iso.id || iso.name || iso;
                        opt.textContent = iso.name || iso.id || iso;
                        if (iso.description) opt.title = iso.description;
                        select.appendChild(opt);
                    });
                }

                container.classList.remove('d-none');
            } catch(e) {}
        });
    }

    var mountIsoBtn = document.getElementById('mountIsoBtn');
    if (mountIsoBtn) {
        mountIsoBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            var select = document.getElementById('isoSelect');
            if (!sid || !select || !select.value) return showToast('Select an ISO first', 'warning');

            if (!(await confirm2('Mount ISO', 'Mount this ISO to the server?'))) return;

            try {
                var r = await api('mountISO', { server_id: sid.value, iso: select.value }, this);
                var tid = r.data && r.data.data && r.data.data.task ? r.data.data.task.id : null;
                showToast('ISO mount initiated' + (tid ? ' — Task #'+tid : ''), 'success');
                setTimeout(loadTasks, 2000);
            } catch(e) {}
        });
    }

    var mountIsoUrlBtn = document.getElementById('mountIsoUrlBtn');
    if (mountIsoUrlBtn) {
        mountIsoUrlBtn.addEventListener('click', async function() {
            var sid = document.getElementById('serverId');
            var inp = document.getElementById('isoUrlInput');
            var url = inp ? inp.value.trim() : '';
            if (!sid || !url) return showToast('Enter ISO URL', 'warning');

            if (!(await confirm2('Mount ISO', 'Mount ISO from URL: ' + url + '?'))) return;

            try {
                var r = await api('mountISO', { server_id: sid.value, iso: url }, this);
                var tid = r.data && r.data.data && r.data.data.task ? r.data.data.task.id : null;
                showToast('ISO mount initiated' + (tid ? ' — Task #'+tid : ''), 'success');
                inp.value = '';
                setTimeout(loadTasks, 2000);
            } catch(e) {}
        });
    }

    // ============ TASKS ============

    async function loadTasks() {
        var container = document.getElementById('tasksContainer');
        var sidEl = document.getElementById('serverId');
        if (!container || !sidEl) return;
        var sid = sidEl.value;

        container.innerHTML = '<div class="text-center py-4" style="color:var(--text-muted)"><div class="spinner-border spinner-border-sm"></div> Loading tasks...</div>';

        try {
            var r = await api('getTasks', { server_id: sid });
            var tasks = r.data && r.data.data ? r.data.data : [];

            if (!tasks.length) {
                container.innerHTML = '<div class="text-center py-4" style="color:var(--text-muted)"><i class="bi bi-inbox"></i> No tasks found</div>';
                return;
            }

            var html = '';
            var max = Math.min(tasks.length, 20);
            for (var i = 0; i < max; i++) {
                var task = tasks[i];
                var ok = task.success === true;
                var done = task.completed === true;
                var pending = !done;

                var iconClass, statusBg, statusText, iconColor;
                if (pending) {
                    iconClass = 'bi-hourglass-split'; statusBg = 'bg-warning'; statusText = task.status || 'pending'; iconColor = '--accent-yellow';
                } else if (ok) {
                    iconClass = 'bi-check-circle-fill'; statusBg = 'bg-success'; statusText = 'complete'; iconColor = '--accent-green';
                } else {
                    iconClass = 'bi-x-circle-fill'; statusBg = 'bg-danger'; statusText = 'failed'; iconColor = '--accent-red';
                }

                var actionName = (task.action || 'unknown').replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });

                var startDisplay = formatApiTime(task.started);
                var finishDisplay = task.finished ? formatApiTime(task.finished) : '';
                var duration = calcDuration(task.started, task.finished);
                var ago = calcAgo(task.finished || task.started);

                html += '<div class="task-item">' +
                    '<div class="d-flex align-items-start gap-3 flex-grow-1" style="min-width:0">' +
                    '<i class="bi ' + iconClass + ' mt-1 flex-shrink-0" style="font-size:1.1rem;color:var(' + iconColor + ')"></i>' +
                    '<div class="flex-grow-1" style="min-width:0">' +
                    '<div class="d-flex justify-content-between align-items-center gap-2">' +
                    '<span class="task-action">' + esc(actionName) + '</span>' +
                    '<div class="d-flex align-items-center gap-2 flex-shrink-0">' +
                    (duration ? '<span style="color:var(--text-muted);font-size:0.6875rem"><i class="bi bi-stopwatch"></i> ' + esc(duration) + '</span>' : '') +
                    (ago ? '<span style="color:var(--accent-blue);font-size:0.6875rem">' + esc(ago) + '</span>' : '') +
                    '<span class="badge ' + statusBg + '">' + esc(statusText) + '</span>' +
                    '</div></div>' +
                    '<div class="task-time mt-1">' +
                    '<span><i class="bi bi-play-circle me-1"></i>Start: ' + esc(startDisplay) + '</span>' +
                    (finishDisplay ? '<span class="ms-3"><i class="bi bi-stop-circle me-1"></i>End: ' + esc(finishDisplay) + '</span>' : '') +
                    '</div></div></div></div>';
            }

            container.innerHTML = html;
        } catch(e) {
            container.innerHTML = '<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle"></i> Failed to load tasks</div>';
        }
    }

    if (document.getElementById('tasksContainer')) {
        setTimeout(loadTasks, 500);
    }

    var refreshTasksBtn = document.getElementById('refreshTasks');
    if (refreshTasksBtn) {
        refreshTasksBtn.addEventListener('click', loadTasks);
    }
    window.loadTasks = loadTasks;

    // ============ BUILD ============

    var confirmBuild = document.getElementById('confirmBuild');
    var buildBtn = document.getElementById('buildBtn');

    if (confirmBuild && buildBtn) {
        confirmBuild.addEventListener('change', function() { buildBtn.disabled = !confirmBuild.checked; });
    }

    var methodSel = document.getElementById('buildMethod');
    var tplGroup = document.getElementById('templateGroup');

    if (methodSel && tplGroup) {
        methodSel.addEventListener('change', function() {
            tplGroup.style.display = this.value === 'self' ? 'none' : '';
            var tplId = document.getElementById('templateId');
            if (tplId) tplId.required = this.value !== 'self';
        });
    }

    var buildForm = document.getElementById('buildForm');
    if (buildForm) {
        buildForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!(await confirm2('⚠️ Build Server', 'ALL DATA will be erased. Cannot be undone. Continue?'))) return;

            var sidEl = document.getElementById('buildServerId');
            if (!sidEl) return;
            var sid = sidEl.value;

            var keys = [];
            document.querySelectorAll('.ssh-key-check:checked').forEach(function(cb) { keys.push(parseInt(cb.value)); });

            try {
                var r = await api('build', {
                    server_id: sid,
                    method: document.getElementById('buildMethod').value,
                    templateId: parseInt(document.getElementById('templateId') ? document.getElementById('templateId').value : 0) || 0,
                    hostname: (document.getElementById('buildHostname') ? document.getElementById('buildHostname').value.trim() : ''),
                    name: (document.getElementById('buildName') ? document.getElementById('buildName').value.trim() : ''),
                    timezone: (document.getElementById('buildTimezone') ? document.getElementById('buildTimezone').value : 'UTC'),
                    swap: parseInt(document.getElementById('buildSwap') ? document.getElementById('buildSwap').value : 0) || 0,
                    ipv6: document.getElementById('buildIpv6') ? document.getElementById('buildIpv6').checked : false,
                    sshKeys: keys,
                    userData: document.getElementById('buildUserData') ? document.getElementById('buildUserData').value : ''
                }, buildBtn);

                var tid = r.data && r.data.data && r.data.data.task ? r.data.data.task.id : null;
                showToast('Build started' + (tid ? ' — Task #'+tid : ''), 'success');
                setTimeout(function() { window.location.href = 'index.php?page=server-detail&id=' + sid; }, 3000);
            } catch(e) {}
        });
    }

    // ============ TEST CONNECTION ============

    var testConnBtn = document.getElementById('testConnection');
    if (testConnBtn) {
        testConnBtn.addEventListener('click', async function() {
            var rd = document.getElementById('connectionResult');
            try {
                await api('testConnection', {}, this);
                rd.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Connected!</div>';
            } catch(e) {
                rd.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Failed</div>';
            }
        });
    }

})();
