// Minimal script for grammateia page
// Keep only helpers and the function used by the current markup

// Small helper to avoid injecting raw HTML
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Generic fetch helper that attempts to parse JSON or fall back to text
function fetchJson(url) {
    return fetch(url).then(res => {
        if (!res.ok) return res.text().then(t => { throw new Error('Network error: ' + res.status + ' ' + t); });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) return res.text().then(t => { try { return JSON.parse(t); } catch (e) { throw new Error('Invalid JSON: ' + t); } });
        return res.json();
    });
}

// Sidebar active state
function setActive(button) {
    const buttons = document.querySelectorAll('.nav-button');
    buttons.forEach(btn => btn.classList.remove('active'));
    if (!button) return;
    button.classList.add('active');
}

// Show/hide content panes. Accepts either the actual element id or a logical key
function loadContent(keyOrId) {
    const mapping = {
        // logical key -> actual element id
        'dedomena': 'eisagwgh-dedomenwn',
        'diplwmatikes': 'diplwmatikes'
    };
    const targetId = mapping[keyOrId] || keyOrId;
    const all = document.querySelectorAll('#content-container .content');
    all.forEach(el => {
        el.classList.remove('visible');
        el.classList.add('hidden');
    });
    const target = document.getElementById(targetId);
    if (target) {
        target.classList.remove('hidden');
        target.classList.add('visible');
        setTimeout(() => { window.scrollTo({ top: 0, behavior: 'smooth' }); }, 0);
    }
}

// Notes UI removed: diplwmatiki detail is handled via an inline detail panel or external page.
// openDiplwmatikiDetail returns minimal data or navigates to full detail page.
function openDiplwmatikiDetail(diplwmatikiId, source) {
    if (!diplwmatikiId) return;
    // Option A: navigate to a dedicated detail page (uncomment if you have one)
    // window.location.href = `diplwmatiki_detail.php?id=${encodeURIComponent(diplwmatikiId)}`;

    // Option B: fetch minimal JSON and show an alert (non-blocking, replace with your UI)
    fetchJson(`homepage_g.php?diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}&source=${encodeURIComponent(source || '')}`)
        .then(data => {
            const title = data.diplwmatiki_title || data.title || '—';
            const foit = (data.foititis_name || '') + ' ' + (data.foititis_lastname || '');
            alert(`Διπλωματική:\nΤίτλος: ${title}\nΦοιτητής: ${foit}`);
        })
        .catch(err => { console.error('Error fetching diplwmatiki detail:', err); alert('Σφάλμα κατά τη φόρτωση λεπτομερειών.'); });
}

// Fetch and show only diplwmatikes with desired statuses
function refreshAllDiplwmatikes() {
    const table = document.getElementById('table-1'); if (!table) return; const tbody = table.querySelector('tbody'); if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7">Φόρτωση...</td></tr>';
    fetchJson('homepage_g.php?action=refresh_all_diplwmatikes')
        .then(rows => {
            console.debug('refreshAllDiplwmatikes: received', rows && rows.length ? rows.length : 0, 'rows');
            tbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];

            // make status matching tolerant: lowercase and match substrings (handles accents/casing)
            function norm(s) {
                if (!s) return '';
                return String(s).toLowerCase().replace(/\s+/g, '');
            }

            const filtered = list.filter(r => {
                const ks = norm(r.katastash);
                // match common greek fragments for the desired statuses
                return ks.includes('ενεργ') || ks.includes('εξετ') && ks.includes('υπο') || ks.includes('υποεξετ') || ks.includes('υπο');
            });

            console.debug('refreshAllDiplwmatikes: filtered ->', filtered.length, 'rows');
            const emptyMsg = document.getElementById('table-1-empty');
            if (filtered.length > 0) {
                if (emptyMsg) emptyMsg.style.display = 'none';
                filtered.forEach(r => {
                    // helpful debug for unexpected fields
                    // console.debug('row katastash', r.katastash, 'normalized', norm(r.katastash));
                    const tr = document.createElement('tr');
                    tr.dataset.diplwmatikiId = r.diplwmatiki_id || '';
                    tr.dataset.katastash = r.katastash || '';
                    tr.dataset.source = 'table-1';
                    tr.innerHTML = `
                        <td>${escapeHtml(r.diplwmatiki_title || '')}</td>
                        <td>${escapeHtml(r.foititis_name || '')}</td>
                        <td>${escapeHtml(r.foititis_lastname || '')}</td>
                        <td>${escapeHtml(r.melos1_lastname || '')}</td>
                        <td>${escapeHtml(r.melos2_lastname || '')}</td>
                        <td>${escapeHtml(r.start_date || '')}</td>
                        <td>${escapeHtml(r.katastash || '')}</td>
                    `;
                    tr.addEventListener('click', function() {
                        const id = this.dataset.diplwmatikiId;
                        if (!id) return;
                        // prefer the static inline detail panel if present
                        const detailPanel = document.querySelector('.anaskopisi-diplwmatikis');
                        const tableWrapper = document.querySelector('.diplwmatikes-table-wrapper');
                            if (detailPanel) {
                                // fetch full details from server and populate the detail panel
                                fetchJson(`homepage_g.php?diplwmatiki_id=${encodeURIComponent(id)}`)
                                    .then(data => {
                                        const setText = (selector, val) => {
                                            const el = detailPanel.querySelector(selector);
                                            if (el) el.textContent = val || '';
                                        };
                                        setText('#detail-title', data.diplwmatiki_title || r.diplwmatiki_title || '');
                                        setText('#detail-foititis-name', data.foititis_name || r.foititis_name || '');
                                        setText('#detail-foititis-lastname', data.foititis_lastname || r.foititis_lastname || '');
                                        setText('#detail-epivlepwn-name', data.epivlepwn_name || '');
                                        setText('#detail-epivlepwn-lastname', data.epivlepwn_lastname || '');
                                        setText('#detail-melos1-name', data.melos1_name || '');
                                        setText('#detail-melos1-lastname', data.melos1_lastname || '');
                                        setText('#detail-melos2-name', data.melos2_name || '');
                                        setText('#detail-melos2-lastname', data.melos2_lastname || '');
                                        setText('#detail-start-date', data.start_date || r.start_date || '');
                                        // compute human-friendly time since start date
                                        const computeTimeSince = (start) => {
                                            if (!start) return '';
                                            const s = new Date(start);
                                            if (isNaN(s)) return '';
                                            const now = new Date();
                                            let diff = Math.floor((now - s) / 1000); // seconds
                                            if (diff < 0) return 'Στο μέλλον';
                                            const years = Math.floor(diff / (3600*24*365));
                                            diff -= years * 3600*24*365;
                                            const months = Math.floor(diff / (3600*24*30));
                                            diff -= months * 3600*24*30;
                                            const days = Math.floor(diff / (3600*24));
                                            diff -= days * 3600*24;
                                            const hours = Math.floor(diff / 3600);
                                            diff -= hours * 3600;
                                            const minutes = Math.floor(diff / 60);
                                            const parts = [];
                                            if (years) parts.push(years + ' έτη');
                                            if (months) parts.push(months + ' μήνες');
                                            if (days) parts.push(days + ' ημέρες');
                                            if (!years && !months && days === 0 && hours) parts.push(hours + ' ώρες');
                                            if (!years && !months && days === 0 && hours === 0 && minutes) parts.push(minutes + ' λεπτά');
                                            if (parts.length === 0) return 'Λιγότερο από ένα λεπτό';
                                            return parts.slice(0,2).join(', ');
                                        };
                                        const timeSince = computeTimeSince(data.start_date || r.start_date || '');
                                        setText('#detail-time-since-start', timeSince);
                                        setText('#detail-katastash', data.katastash || r.katastash || '');
                                        setText('#detail-bathmos-epivlepwn', data.bathmos_epivlepwn || '');
                                        setText('#detail-bathmos-melos1', data.bathmos_melos1 || '');
                                        setText('#detail-bathmos-melos2', data.bathmos_melos2 || '');

                                        // show/hide the active-management block when status is 'ΕΝΕΡΓΗ'
                                        try {
                                            const energiBlock = detailPanel.querySelector('.energi-diplwmatiki');
                                            if (energiBlock) {
                                                const ks = (data.katastash || r.katastash || '').toString().toLowerCase();
                                                if (ks.includes('ενεργ')) energiBlock.style.display = 'block';
                                                else energiBlock.style.display = 'none';
                                            }
                                        } catch (e) { console.error('Error toggling energi-diplwmatiki:', e); }

                                        // show/hide the 'ΥΠΟ ΕΞΕΤΑΣΗ' block when status indicates under examination
                                        try {
                                            const ypoBlock = detailPanel.querySelector('.ypo-eksetasi-diplwmatiki');
                                            if (ypoBlock) {
                                                const ks = (data.katastash || r.katastash || '').toString().toLowerCase();
                                                // tolerant matching: look for 'υπο' + 'εξετ' (covers 'ΥΠΟ ΕΞΕΤΑΣΗ' and variations)
                                                if (ks.includes('υπο') && (ks.includes('εξετ') || ks.includes('εξετα') || ks.includes('υποεξετ'))) {
                                                    ypoBlock.style.display = 'block';
                                                } else {
                                                    ypoBlock.style.display = 'none';
                                                }
                                            }
                                        } catch (e) { console.error('Error toggling ypo-eksetasi-diplwmatiki:', e); }

                                        if (tableWrapper) tableWrapper.style.display = 'none';
                                        // store current diplwmatiki id for later actions (approve/cancel)
                                        detailPanel.dataset.currentDiplwmatikiId = id;
                                        detailPanel.style.display = 'block';
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    })
                                    .catch(err => {
                                        console.error('Error loading diplwmatiki detail:', err);
                                        // show row-populated minimal info if server fails
                                        const setText = (selector, val) => {
                                            const el = detailPanel.querySelector(selector);
                                            if (el) el.textContent = val || '';
                                        };
                                        setText('#detail-title', r.diplwmatiki_title || '');
                                        setText('#detail-foititis-name', r.foititis_name || '');
                                        setText('#detail-foititis-lastname', r.foititis_lastname || '');
                                        setText('#detail-start-date', r.start_date || '');
                                        setText('#detail-katastash', r.katastash || '');
                                        // fallback: toggle energi block based on row status
                                        try {
                                            const energiBlock = detailPanel.querySelector('.energi-diplwmatiki');
                                            if (energiBlock) {
                                                const ks = (r.katastash || '').toString().toLowerCase();
                                                if (ks.includes('ενεργ')) energiBlock.style.display = 'block';
                                                else energiBlock.style.display = 'none';
                                            }
                                        } catch (e) { console.error('Error toggling energi-diplwmatiki (fallback):', e); }
                                        // fallback: toggle ypo-eksetasi-diplwmatiki based on row status
                                        try {
                                            const ypoBlock = detailPanel.querySelector('.ypo-eksetasi-diplwmatiki');
                                            if (ypoBlock) {
                                                const ks = (r.katastash || '').toString().toLowerCase();
                                                if (ks.includes('υπο') && (ks.includes('εξετ') || ks.includes('εξετα') || ks.includes('υποεξετ'))) ypoBlock.style.display = 'block';
                                                else ypoBlock.style.display = 'none';
                                            }
                                        } catch (e) { console.error('Error toggling ypo-eksetasi-diplwmatiki (fallback):', e); }
                                        if (tableWrapper) tableWrapper.style.display = 'none';
                                        // store current diplwmatiki id from row for fallback
                                        detailPanel.dataset.currentDiplwmatikiId = r.diplwmatiki_id || '';
                                        detailPanel.style.display = 'block';
                                    });
                            } else {
                                // fallback: use the existing detail fetch/alert
                                const source = this.dataset.source || 'table-1';
                                openDiplwmatikiDetail(id, source);
                            }
                    });
                    tbody.appendChild(tr);
                });
            } else {
                if (emptyMsg) emptyMsg.style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="7">Δεν υπάρχουν διπλωματικές με επιθυμητές καταστάσεις.</td></tr>';
            }
        })
        .catch(err => { console.error('Error fetching all diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="7">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// Minimal initializer: wire nav buttons and load data
document.addEventListener('DOMContentLoaded', () => {
    // ensure inline onclick still works; add listener as fallback
    const navButtons = document.querySelectorAll('.nav-button');
    navButtons.forEach(btn => btn.addEventListener('click', () => { setActive(btn); }));

    // load diplwmatikes table
    try { refreshAllDiplwmatikes(); } catch (e) { console.error(e); }
    // Import JSON file handler (eisagwgh-dedomenwn)
    const importBtn = document.getElementById('import-json-btn');
    if (importBtn) {
        importBtn.addEventListener('click', () => {
            const input = document.getElementById('personal-json-file');
            const status = document.getElementById('import-json-status');
            if (!input || !input.files || input.files.length === 0) { if (status) status.textContent = 'Επιλέξτε αρχείο JSON.'; return; }
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const parsed = JSON.parse(e.target.result);

                    // Client-side validation: require a non-empty password for every imported record
                    const missing = [];
                    if (parsed && Array.isArray(parsed.foititis)) {
                        parsed.foititis.forEach((rec, idx) => {
                            if (!rec || typeof rec.password !== 'string' || rec.password.trim() === '') {
                                missing.push({ table: 'foititis', index: idx, record: rec });
                            }
                        });
                    }
                    if (parsed && Array.isArray(parsed.didaskwn)) {
                        parsed.didaskwn.forEach((rec, idx) => {
                            if (!rec || typeof rec.password !== 'string' || rec.password.trim() === '') {
                                missing.push({ table: 'didaskwn', index: idx, record: rec });
                            }
                        });
                    }

                    if (missing.length > 0) {
                        // Show a concise message and abort upload; backend also enforces this.
                        status.textContent = `Το αρχείο περιέχει ${missing.length} εγγραφές χωρίς πεδίο password. Προσθέστε password σε όλες τις εγγραφές πριν την αποστολή.`;
                        console.warn('Import aborted due to missing passwords:', missing.slice(0,10));
                        return;
                    }

                    status.textContent = 'Αποστολή...';
                    fetch('homepage_g.php?action=import_personal_json', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json; charset=utf-8' },
                        body: JSON.stringify(parsed)
                    }).then(r => r.json()).then(resp => {
                        // server now returns inserted_foititis and inserted_didaskwn plus errors[]
                        const insertedF = (resp && resp.inserted_foititis) ? resp.inserted_foititis : 0;
                        const insertedD = (resp && resp.inserted_didaskwn) ? resp.inserted_didaskwn : 0;
                        const total = insertedF + insertedD;
                        if (resp && resp.success) {
                            if (status) status.textContent = `Επιτυχία. Εισήχθησαν ${total} εγγραφές (φοιτητές: ${insertedF}, διδάσκοντες: ${insertedD}).`;
                        } else if (resp) {
                            if (status) status.textContent = `Ολοκληρώθηκε με σφάλματα. Εισήχθησαν ${total} εγγραφές (φοιτητές: ${insertedF}, διδάσκοντες: ${insertedD}). Ελέγξτε το αρχείο που ανεβάσατε.`;
                            console.warn('import_personal_json had errors:', resp.errors || []);
                        } else {
                            if (status) status.textContent = 'Σφάλμα εισαγωγής. Δείτε κονσόλα για λεπτομέρειες.';
                        }
                        console.debug('import_personal_json response', resp);
                    }).catch(err => { console.error(err); if (status) status.textContent = 'Σφάλμα δικτύου κατά την εισαγωγή.'; });
                } catch (e) { if (status) status.textContent = 'Μη έγκυρο JSON αρχείο.'; }
            };
            reader.readAsText(file);
        });
    }
    // wire static back-button if the inline detail panel exists
    const staticBack = document.querySelector('#back-button');
    if (staticBack) {
        staticBack.addEventListener('click', () => {
            const detailPanel = document.querySelector('.anaskopisi-diplwmatikis');
            const tableWrapper = document.querySelector('.diplwmatikes-table-wrapper');
            if (detailPanel) {
                try { const energi = detailPanel.querySelector('.energi-diplwmatiki'); if (energi) energi.style.display = 'none'; } catch(e) {}
                try { const ypo = detailPanel.querySelector('.ypo-eksetasi-diplwmatiki'); if (ypo) ypo.style.display = 'none'; } catch(e) {}
                detailPanel.style.display = 'none';
            }
            if (tableWrapper) tableWrapper.style.display = '';
        });
    }
    // wire approve button (egkrisi)
    try {
        const approveBtn = document.getElementById('egkrisi-diplwmatiki-btn');
        if (approveBtn) {
            approveBtn.addEventListener('click', () => {
                const detailPanel = document.querySelector('.anaskopisi-diplwmatikis');
                if (!detailPanel) return;
                const id = detailPanel.dataset.currentDiplwmatikiId;
                const input = document.getElementById('arithmos-prwtokolou-anathesi');
                const status = document.getElementById('import-json-status');
                if (!input || !input.value || input.value.trim() === '') {
                    if (status) status.textContent = 'Συμπληρώστε τον αριθμό πρωτοκόλλου πριν την έγκριση.';
                    return;
                }
                const arith = input.value.trim();
                if (!/^[0-9]+$/.test(arith)) {
                    if (status) status.textContent = 'Ο αριθμός πρωτοκόλλου πρέπει να είναι αριθμός.';
                    return;
                }
                if (!id) {
                    if (status) status.textContent = 'Μη έγκυρη διπλωματική.';
                    return;
                }
                if (status) status.textContent = 'Αποστολή έγκρισης...';
                fetch('homepage_g.php?action=approve_diplwmatiki', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ diplwmatiki_id: id, arithmos_anathesis: arith })
                }).then(r => r.json()).then(resp => {
                    if (resp && resp.success) {
                        if (status) status.textContent = 'Η διπλωματική ενημερώθηκε.';
                        // refresh table and hide detail
                        try { refreshAllDiplwmatikes(); } catch (e) { console.error(e); }
                        const tableWrapper = document.querySelector('.diplwmatikes-table-wrapper');
                        if (tableWrapper) tableWrapper.style.display = '';
                        if (detailPanel) detailPanel.style.display = 'none';
                    } else {
                        if (status) status.textContent = 'Σφάλμα κατά την ενημέρωση.';
                        console.warn('approve_diplwmatiki failed', resp);
                    }
                }).catch(err => { console.error(err); if (status) status.textContent = 'Σφάλμα δικτύου κατά την έγκριση.'; });
            });
        }
    } catch (e) { console.error('Error wiring approve button:', e); }
    // wire cancel button
    try {
        const cancelBtn = document.getElementById('cancel-diplwmatiki-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                const detailPanel = document.querySelector('.anaskopisi-diplwmatikis');
                if (!detailPanel) return;
                const id = detailPanel.dataset.currentDiplwmatikiId;
                const arithInput = document.getElementById('arithmos-sineleusis-cancel');
                const etosInput = document.getElementById('etos-cancel');
                const status = document.getElementById('import-json-status');
                if (!arithInput || !arithInput.value || arithInput.value.trim() === '' || !etosInput || !etosInput.value || etosInput.value.trim() === '') {
                    if (status) status.textContent = 'Συμπληρώστε αριθμό και έτος πριν την ακύρωση.';
                    return;
                }
                const arith = arithInput.value.trim();
                const etos = etosInput.value.trim();
                if (!/^[0-9]+$/.test(arith) || !/^[0-9]{4}$/.test(etos)) {
                    if (status) status.textContent = 'Δώστε έγκυρο αριθμό και τετραψήφιο έτος.';
                    return;
                }
                if (!id) {
                    if (status) status.textContent = 'Μη έγκυρη διπλωματική.';
                    return;
                }
                if (status) status.textContent = 'Αποστολή ακύρωσης...';
                fetch('homepage_g.php?action=cancel_diplwmatiki', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ diplwmatiki_id: id, arithmos_akyrwshs: arith, etos_akyrwshs: etos })
                }).then(r => r.json()).then(resp => {
                    if (resp && resp.success) {
                        if (status) status.textContent = 'Η διπλωματική ακυρώθηκε.';
                        try { refreshAllDiplwmatikes(); } catch (e) { console.error(e); }
                        const tableWrapper = document.querySelector('.diplwmatikes-table-wrapper');
                        if (tableWrapper) tableWrapper.style.display = '';
                        if (detailPanel) detailPanel.style.display = 'none';
                    } else {
                        if (status) status.textContent = 'Σφάλμα κατά την ακύρωση.';
                        console.warn('cancel_diplwmatiki failed', resp);
                    }
                }).catch(err => { console.error(err); if (status) status.textContent = 'Σφάλμα δικτύου κατά την ακύρωση.'; });
            });
        }
    } catch (e) { console.error('Error wiring cancel button:', e); }
    // wire peratwsi (finish) button
    try {
        const peratwsiBtn = document.getElementById('peratwsi-diplwmatikis-btn');
        if (peratwsiBtn) {
            peratwsiBtn.addEventListener('click', () => {
                const detailPanel = document.querySelector('.anaskopisi-diplwmatikis');
                if (!detailPanel) return;
                const id = detailPanel.dataset.currentDiplwmatikiId;
                // Prefer the global import status element, but if it's not visible create/use a detail-specific status
                let status = document.getElementById('import-json-status');
                const isGlobalVisible = status && status.offsetParent !== null;
                if (!isGlobalVisible) {
                    // create or reuse a detail-specific status element inside the detail panel
                    let detailStatus = detailPanel.querySelector('#detail-action-status');
                    if (!detailStatus) {
                        detailStatus = document.createElement('p');
                        detailStatus.id = 'detail-action-status';
                        detailStatus.style.paddingTop = '8px';
                        detailStatus.style.color = '#900';
                        // place it near the action buttons
                        const actionContainer = detailPanel.querySelector('.peratwsi-inputs') || detailPanel;
                        actionContainer.appendChild(detailStatus);
                    }
                    status = detailStatus;
                }
                if (!id) { if (status) status.textContent = 'Μη έγκυρη διπλωματική.'; return; }
                if (status) status.textContent = 'Αποστολή περατώσεως...';
                fetch('homepage_g.php?action=finish_diplwmatiki', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ diplwmatiki_id: id })
                }).then(r => r.json()).then(resp => {
                    if (resp && resp.success) {
                        if (status) status.textContent = `Η διπλωματική περατώθηκε (τελικός βαθμός: ${resp.final_bathmos}).`;
                        try { refreshAllDiplwmatikes(); } catch (e) { console.error(e); }
                        const tableWrapper = document.querySelector('.diplwmatikes-table-wrapper');
                        if (tableWrapper) tableWrapper.style.display = '';
                        if (detailPanel) detailPanel.style.display = 'none';
                        return;
                    }

                    // show specific server error messages when available
                    let msg = 'Σφάλμα κατά την περάτωση.';
                    try {
                        const code = resp && resp.error ? String(resp.error) : '';
                        switch (code) {
                            case 'missing_db_fields':
                                msg = 'Η διπλωματική δεν έχει συμπληρωμένους και τους τρεις βαθμούς και/ή το σύνδεσμο πρακτικού.';
                                break;
                            case 'invalid_grades':
                                msg = 'Οι αποθηκευμένοι βαθμοί δεν είναι έγκυροι αριθμοί.';
                                break;
                            case 'grade_out_of_range':
                                msg = 'Οι βαθμοί πρέπει να είναι αριθμοί μεταξύ 0 και 10.';
                                break;
                            case 'link_too_long':
                                msg = 'Ο αποθηκευμένος σύνδεσμος πρακτικού είναι πολύ μεγάλος.';
                                break;
                            case 'not_found':
                                msg = 'Η διπλωματική δεν βρέθηκε.';
                                break;
                            case 'missing_id':
                                msg = 'Μη έγκυρο αναγνωριστικό διπλωματικής.';
                                break;
                            default:
                                // if server returned a textual error, include it for debugging
                                if (resp && resp.error) msg = `Σφάλμα: ${resp.error}`;
                                break;
                        }
                    } catch (e) { console.warn('Error parsing finish response:', e); }

                    if (status) status.textContent = msg;
                    console.warn('finish_diplwmatiki failed', resp);
                }).catch(err => { console.error(err); if (status) status.textContent = 'Σφάλμα δικτύου κατά την περάτωση.'; });
            });
        }
    } catch (e) { console.error('Error wiring peratwsi button:', e); }
});
