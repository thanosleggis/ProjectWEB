// JavaScript to filter tables based on dropdown selection
document.addEventListener('DOMContentLoaded', () => {
	// Select all filter dropdowns
	const dropdowns = document.querySelectorAll('.filter-dropdown');

	// Add event listener for each dropdown
	dropdowns.forEach(dropdown => {
		dropdown.addEventListener('change', function () {
			const selectedFilter = this.value;
			const tableId = this.getAttribute('data-filter');
			const rows = document.querySelectorAll(`#${tableId} tbody tr`);

				rows.forEach(row => {
					const katastash = row.getAttribute('data-katastash') || '';
					if (selectedFilter === 'all' || katastash === selectedFilter) {
						row.style.display = '';
					} else {
						row.style.display = 'none';
					}
				});
			});
		});

	// Initial load: fetch the topics list and free topics so the table and dropdown are populated
	try {
		refreshListaThematwn();
		refreshDiathesimaThemata();
		refreshAllDiplwmatikes();
		refreshDiplwmatikesAsMelos1();
		refreshDiplwmatikesAsMelos2();
		refreshOldDiplwmatikes();
		refreshNewProsklhseis();
		refreshActiveDiplwmatikes();
		refreshEnergesProsklhseis();
		refreshProsklhseisHistory();
	} catch (e) {
		console.error('Error initiating refresh functions:', e);
	}

	// Delegated click handlers for accept/reject in the active invitations table
	const prosklhseisActiveBody = document.getElementById('prosklhseis-active-body');
	if (prosklhseisActiveBody) {
		prosklhseisActiveBody.addEventListener('click', (e) => {
			const btn = e.target.closest('button');
			if (!btn) return;
			const pid = btn.dataset.prosklhshId;
			if (!pid) return;

			const confirmMsg = btn.classList.contains('accept-button')
				? 'Θέλετε να αποδεχθείτε αυτή την πρόσκληση;'
				: btn.classList.contains('reject-button')
					? 'Θέλετε να απορρίψετε αυτή την πρόσκληση;'
					: null;
			if (!confirmMsg) return;
			if (!confirm(confirmMsg)) return;

			const payload = new URLSearchParams();
			payload.append('action', btn.classList.contains('accept-button') ? 'accept_prosklhsh' : 'reject_prosklhsh');
			payload.append('prosklhsh_id', pid);

			fetch('homepage_d.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: payload.toString()
			})
			.then(r => r.text())
			.then(t => {
				try {
					const d = JSON.parse(t);
					if (d.success) {
						refreshAllTables();
					} else {
						alert('Σφάλμα: ' + (d.error || 'Άγνωστο'));
					}
				} catch(e) {
					console.error('Invalid response', t);
					alert('Σφάλμα διακομιστή');
				}
			})
			.catch(err => { console.error(err); alert('Σφάλμα δικτύου'); });
		});
	}

	// Helper: current diplwmatiki id from any visible detail panel
	const getCurrentDiplId = () => {
		const p = document.querySelector('.ypo-eksetasi-diplwmatiki') || document.querySelector('.energi-diplwmatiki') || document.querySelector('.ypo-anathesi-diplwmatiki');
		return p ? p.dataset.diplwmatikiId : null;
	};

	// Add event listener for back button
	const backToThemataBtn = document.getElementById('back-to-themata');
	if (backToThemataBtn) {
		backToThemataBtn.addEventListener('click', (e) => {
			e.preventDefault();
			// Show all sections
			document.querySelector('.lista-thematwn').style.display = 'block';
			document.querySelector('.dhmiourgia-thematos').style.display = 'block';
			document.querySelector('.anathesi-thematos').style.display = 'block';
			
			// Hide edit section
			document.getElementById('edit-thema').style.display = 'none';
		});
	}

	// Handle edit PDF file selection
	const editFileInput = document.getElementById('edit-pdf');
	const editFileNameSpan = document.getElementById('edit-file-name');
	if (editFileInput && editFileNameSpan) {
		editFileInput.addEventListener('change', (event) => {
			if (event.target.files.length > 0) {
				editFileNameSpan.textContent = event.target.files[0].name;
			} else {
				editFileNameSpan.textContent = '';
			}
		});
	}

	// Row click handling for diplwmatikes (delegated). Works for rows added dynamically by AJAX.
	function openDiplwmatikiDetail(diplwmatikiId, source) {
		if (!diplwmatikiId) return;
		fetch(`homepage_d.php?diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}&source=${encodeURIComponent(source || '')}`)
			.then(response => response.text())
			.then(text => {
				const data = JSON.parse(text);

				// Show invitations panel only if the diplwmatiki status allows assignments
				const prosklSection = document.querySelector('.ypo-anathesi-diplwmatiki');
				const tbody = document.querySelector('#ypo-prosklhseis-table tbody');
				if (prosklSection && data.katastash === 'ΥΠΟ ΑΝΑΘΕΣΗ') {
					prosklSection.style.display = 'block';
					// store selected diplwmatiki id so cancel button can use it
					prosklSection.dataset.diplwmatikiId = diplwmatikiId;
					const cancelBtnLocal = document.getElementById('cancel-anathesi-btn');
					if (cancelBtnLocal) cancelBtnLocal.dataset.diplwmatikiId = diplwmatikiId;

					// Ensure energia-management panel is hidden for this status
					const energiSectionHide = document.querySelector('.energi-diplwmatiki');
					if (energiSectionHide) energiSectionHide.style.display = 'none';

					if (tbody) {
						tbody.innerHTML = '<tr><td colspan="5">Φόρτωση...</td></tr>';
						fetch(`homepage_d.php?action=related_prosklhseis&diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}`)
							.then(res => res.json())
							.then(rows => {
								tbody.innerHTML = '';
								if (Array.isArray(rows) && rows.length > 0) {
									rows.forEach(r => {
										const tr = document.createElement('tr');
										const prof = `${r.didaskwn_name || ''} ${r.didaskwn_lastname || ''}`.trim() || '—';
										tr.innerHTML = `
											<td>${escapeHtml(prof)}</td>
											<td>${escapeHtml(r.type || '')}</td>
											<td>${escapeHtml(r.datetime_created || '')}</td>
											<td>${escapeHtml(r.datetime_answer || '—')}</td>
											<td>${escapeHtml(r.katastash || '')}</td>
										`;
										tbody.appendChild(tr);
									});
								} else {
									tbody.innerHTML = '<tr><td colspan="5">Δεν βρέθηκαν προσκλήσεις που σχετίζονται με αυτή τη διπλωματική.</td></tr>';
								}
							})
							.catch(err => {
								console.error('Error loading related prosklhseis:', err);
								tbody.innerHTML = '<tr><td colspan="5">Σφάλμα κατά τη φόρτωση.</td></tr>';
							});
					}
				} else {
					// hide and clear invitations panel when status doesn't allow assignments
					if (prosklSection) prosklSection.style.display = 'none';
					if (tbody) tbody.innerHTML = '';
				}

				// Show management panel when diplwmatiki is ΕΝΕΡΓΗ
				const energiSection = document.querySelector('.energi-diplwmatiki');
				if (energiSection && data.katastash === 'ΕΝΕΡΓΗ') {
					energiSection.style.display = 'block';
					energiSection.dataset.diplwmatikiId = diplwmatikiId;
					const cancelDiplBtn = document.getElementById('cancel-diplwmatiki-btn');
					const eksetasiBtn = document.getElementById('eksetasi-diplwmatiki-btn');
					if (cancelDiplBtn) cancelDiplBtn.dataset.diplwmatikiId = diplwmatikiId;
					if (eksetasiBtn) eksetasiBtn.dataset.diplwmatikiId = diplwmatikiId;
					const neaShmeiwshBtn = document.getElementById('new-shmeiwsh-btn');
					if (neaShmeiwshBtn) {
						// Remove any existing handler to avoid duplicate listeners when opening multiple diplwmatiki
						neaShmeiwshBtn.replaceWith(neaShmeiwshBtn.cloneNode(true));
						const btn = document.getElementById('new-shmeiwsh-btn');
						btn.addEventListener('click', (e) => {
							e.preventDefault();
							const textarea = document.getElementById('new-shmeiwsh-text');
							const shmeiwsh = (textarea || {}).value || '';
							if (!shmeiwsh.trim()) return; // don't submit empty

							fetch(`homepage_d.php?action=add_shmeiwsh&diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}`, {
								method: 'POST',
								headers: { 'Content-Type': 'application/json' },
								body: JSON.stringify({ shmeiwsh: shmeiwsh.trim() })
							})
							.then(r => r.json())
							.then(data => {
								if (data.success) {
									// refresh notes and clear textarea
									loadShmeiwseis(diplwmatikiId);
									if (textarea) { textarea.value = ''; textarea.focus(); }
								} else {
									alert('Σφάλμα: ' + (data.error || 'Αποτυχία αποθήκευσης.'));
								}
							})
							.catch(err => { console.error('Error adding note:', err); alert('Σφάλμα δικτύου.'); });
						});
					}
					// Load notes for this diplwmatiki via AJAX
					// Render notes using reusable function
					loadShmeiwseis(diplwmatikiId);
				} else if (energiSection) {
					energiSection.style.display = 'none';
				}

				// Show panel for 'ΥΠΟ ΕΞΕΤΑΣΗ' status if present
				const ypoEksetasiDiv = document.querySelector('.ypo-eksetasi-diplwmatiki');
				if (ypoEksetasiDiv && data.katastash === 'ΥΠΟ ΕΞΕΤΑΣΗ') {
					// hide other panels to avoid UI clashes 
					if (prosklSection) prosklSection.style.display = 'none';
					if (energiSection) energiSection.style.display = 'none';
					// Show the section but only reveal the grade-assignment area when bathmos_enable is true
					ypoEksetasiDiv.style.display = 'block';
					ypoEksetasiDiv.dataset.diplwmatikiId = diplwmatikiId;
					const anathesiDiv = ypoEksetasiDiv.querySelector('.anathesi-bathmou');
					if (anathesiDiv) {
						const enabled = (data.bathmos_enable == 1 || data.bathmos_enable === true || data.bathmos_enable === '1' || data.bathmos_enable === 'true');
						const userAlready = !!data.user_has_bathmos;
						if (enabled && !userAlready) {
							anathesiDiv.style.display = '';
						} else {
							anathesiDiv.style.display = 'none';
						}
					}
				} else if (ypoEksetasiDiv) {
					ypoEksetasiDiv.style.display = 'none';
					const anathesiDiv = ypoEksetasiDiv.querySelector('.anathesi-bathmou');
					if (anathesiDiv) anathesiDiv.style.display = 'none';
				}

				// Wire submit of bathmoi: calculate mean and send to server
				(function(){
					const submitBtn = document.getElementById('submit-bathmoi-btn');
					if (!submitBtn) return;
					// remove previous handlers to avoid duplicates
					submitBtn.replaceWith(submitBtn.cloneNode(true));
					const newBtn = document.getElementById('submit-bathmoi-btn');
					newBtn.addEventListener('click', function(){
						const p = document.querySelector('.ypo-eksetasi-diplwmatiki') || document.querySelector('.energi-diplwmatiki');
						const diplId = p ? p.dataset.diplwmatikiId : null;
						if (!diplId) return alert('Δεν βρέθηκε επιλεγμένη διπλωματική.');
						const v1 = parseFloat(document.getElementById('bathmos-1').value) || 0;
						const v2 = parseFloat(document.getElementById('bathmos-2').value) || 0;
						const v3 = parseFloat(document.getElementById('bathmos-3').value) || 0;
						const mean = Math.round(((v1 + v2 + v3) / 3) * 10) / 10; // one decimal
						if (isNaN(mean) || mean < 0 || mean > 10) return alert('Εισάγετε έγκυρους βαθμούς μεταξύ 0 και 10.');
						const payload = new URLSearchParams();
						payload.append('action', 'submit_bathmoi');
						payload.append('diplwmatiki_id', diplId);
						payload.append('mean', mean);
						fetch('homepage_d.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
							.then(r => r.text())
							.then(t => {
								try {
									const d = JSON.parse(t);
									if (d.success) {
										// hide and clear inputs
										const anathesi = document.querySelector('.anathesi-bathmou');
										if (anathesi) {
											const inputs = anathesi.querySelectorAll('input[type="number"]');
											inputs.forEach(i => i.value = '');
											anathesi.style.display = 'none';
										}
										alert('Ο μέσος όρος καταχωρήθηκε.');
										refreshAllTables();
										// re-fetch detail view to reflect latest data
										openDiplwmatikiDetail(diplId);
									} else {
										alert('Σφάλμα: ' + (d.error || 'Άγνωστο'));
									}
								} catch(e) { console.error('Invalid response', t); alert('Σφάλμα διακομιστή'); }
							})
							.catch(err => { console.error(err); alert('Σφάλμα δικτύου'); });
					});
				})();

				// wire energopoihsh-bathmou-btn (enable grade entry)
				const energBtn = document.getElementById('energopoihsh-bathmou-btn');
				if (energBtn) {
					energBtn.onclick = function () {
						const parent = document.querySelector('.ypo-eksetasi-diplwmatiki') || document.querySelector('.energi-diplwmatiki');
						const diplId = parent ? parent.dataset.diplwmatikiId || document.querySelector('.energi-diplwmatiki')?.dataset.diplwmatikiId : null;
						if (!diplId) return alert('Δεν βρέθηκε επιλεγμένη διπλωματική.');
						if (!confirm('Επιβεβαιώστε ενεργοποίηση καταχώρησης βαθμού για αυτή τη διπλωματική.')) return;
						const payload = new URLSearchParams();
						payload.append('action', 'energopoihsh_bathmou');
						payload.append('diplwmatiki_id', diplId);
						fetch('homepage_d.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
							.then(r => r.text()).then(t => {
								try { const d = JSON.parse(t); if (d.success) { alert('Ενεργοποιήθηκε η καταχώρηση βαθμού.'); refreshAllTables(); } else { alert('Σφάλμα: ' + (d.error || 'Άγνωστο')); } } catch(e){ console.error('Invalid response', t); alert('Σφάλμα διακομιστή'); }
								// re-fetch detail to update UI state
								openDiplwmatikiDetail(diplId);
							}).catch(err => { console.error(err); alert('Σφάλμα δικτύου'); });
					};
				}

				// Populate details
				document.getElementById("detail-title").textContent = data.diplwmatiki_title || "N/A";
				document.getElementById("detail-foititis-name").textContent = data.foititis_name  || "N/A";
				document.getElementById("detail-foititis-lastname").textContent = data.foititis_lastname || "N/A";
				document.getElementById("detail-epivlepwn-name").textContent = data.epivlepwn_name  || "N/A";
				document.getElementById("detail-epivlepwn-lastname").textContent = data.epivlepwn_lastname || "N/A";
				document.getElementById("detail-melos1-name").textContent = data.melos1_name || "N/A";
				document.getElementById("detail-melos1-lastname").textContent = data.melos1_lastname || "N/A";
				document.getElementById("detail-melos2-name").textContent = data.melos2_name || "N/A";
				document.getElementById("detail-melos2-lastname").textContent = data.melos2_lastname || "N/A";
				document.getElementById("detail-start-date").textContent = data.start_date || "N/A";
				document.getElementById("detail-end-date").textContent = data.end_date || "N/A";
				document.getElementById("detail-katastash").textContent = data.katastash || "N/A";
				document.getElementById("detail-bathmos").textContent = data.bathmos || "N/A";
				document.getElementById("detail-bathmos-epivlepwn").textContent = (data.bathmos_epivlepwn != null && data.bathmos_epivlepwn !== '') ? data.bathmos_epivlepwn : 'N/A';
				document.getElementById("detail-bathmos-melos1").textContent = (data.bathmos_melos1 != null && data.bathmos_melos1 !== '') ? data.bathmos_melos1 : 'N/A';
				document.getElementById("detail-bathmos-melos2").textContent = (data.bathmos_melos2 != null && data.bathmos_melos2 !== '') ? data.bathmos_melos2 : 'N/A';
				document.getElementById("detail-link").textContent = data.link || "N/A";
				document.getElementById("detail-praktiko").textContent = data.praktiko || "N/A";

				// Always show the proxeiro download button; if missing, alert on click
				(function(){
					const kateBtn = document.getElementById('katebasma-proxeirou');
					if (!kateBtn) return;
					// ensure visible in the detail panel
					kateBtn.style.display = '';
					// store current diplwmatiki id so click handler can use it
					const parent = document.querySelector('.ypo-eksetasi-diplwmatiki') || document.querySelector('.energi-diplwmatiki');
					if (parent) parent.dataset.diplwmatikiId = parent.dataset.diplwmatikiId || diplwmatikiId;

					kateBtn.onclick = function () {
						const p = document.querySelector('.ypo-eksetasi-diplwmatiki') || document.querySelector('.energi-diplwmatiki');
						const diplId = p ? p.dataset.diplwmatikiId || document.querySelector('.energi-diplwmatiki')?.dataset.diplwmatikiId : null;
						if (!diplId) return alert('Δεν βρέθηκε επιλεγμένη διπλωματική.');
						if (!data.proxeiro || data.proxeiro === '') {
							alert('Δεν υπάρχει διαθέσιμο πρόχειρο για λήψη.');
							return;
						}
						// Initiate download by navigating to the download URL
						window.location.href = `homepage_d.php?action=download_proxeiro&diplwmatiki_id=${encodeURIComponent(diplId)}`;
					};
				})();

				// Show energopoihsh-bathmou-btn only if current page user is the epivlepwn of this diplwmatiki
				(function(){
					const energBtn = document.getElementById('energopoihsh-bathmou-btn');
					if (!energBtn) return;
					// header contains two .header-user spans; pick the one that isn't the logout label
					const headerUsers = Array.from(document.querySelectorAll('.header-user'));
					const hdr = headerUsers.find(s => s.textContent && s.textContent.trim() !== 'Αποσύνδεση');
					const currentFull = hdr ? hdr.textContent.trim() : '';
					const epivlepwnFull = ((data.epivlepwn_name || '') + ' ' + (data.epivlepwn_lastname || '')).trim();
					const bathmosEnabled = (data.bathmos_enable == 1 || data.bathmos_enable === true || data.bathmos_enable === '1' || data.bathmos_enable === 'true');
					if (currentFull && epivlepwnFull && currentFull === epivlepwnFull && !bathmosEnabled) {
						energBtn.style.display = '';
					} else {
						energBtn.style.display = 'none';
					}
				})();

				document.querySelector(".diplwmatikes-tables").style.display = 'none';
				document.querySelector(".anaskopisi-diplwmatikis").style.display = 'block';
			})
			.catch(error => console.error('Error fetching data:', error));
	}

	// Delegated click listener so dynamically-added rows in tables 1-4 work reliably
	const diplContainer = document.querySelector('.diplwmatikes-tables');
	if (diplContainer) {
		diplContainer.addEventListener('click', (e) => {
			const tr = e.target.closest('tr');
			if (!tr || !diplContainer.contains(tr)) return;
			// Determine diplwmatiki id and optional source
			const diplwmatikiId = tr.dataset.diplwmatikiId || tr.getAttribute('data-diplwmatiki-id');
			let source = tr.dataset.source || tr.getAttribute('data-source');
			if (!source) {
				const tbl = tr.closest('table');
				source = tbl ? tbl.id : '';
			}
			if (!diplwmatikiId) return; // nothing to do
			openDiplwmatikiDetail(diplwmatikiId, source);
		});
	}

	// Back button functionality
	const backButton = document.getElementById("back-button");
	if (backButton) {
		backButton.addEventListener("click", function () {
			// Show the tables and hide the details section
			document.querySelector(".anaskopisi-diplwmatikis").style.display = 'none';
			document.querySelector(".diplwmatikes-tables").style.display = 'block';
		});
	}

	// Cancel assignment button (in diplwmatiki detail view)
	const cancelAnathesiBtn = document.getElementById('cancel-anathesi-btn');
	if (cancelAnathesiBtn) {
		cancelAnathesiBtn.addEventListener('click', function () {
			const diplwId = this.dataset.diplwmatikiId || document.querySelector('.ypo-anathesi-diplwmatiki')?.dataset.diplwmatikiId;
			if (!diplwId) {
				// fallback: just hide UI
				const panel = document.querySelector('.ypo-anathesi-diplwmatiki');
				if (panel) panel.style.display = 'none';
				const tables = document.querySelector('.diplwmatikes-tables');
				if (tables) tables.style.display = 'block';
				return;
			}

			if (!confirm('Θέλετε να ακυρώσετε όλες τις προσκλήσεις που σχετίζονται με αυτή τη διπλωματική; Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.')) return;

			const payload = new URLSearchParams();
			payload.append('action', 'cancel_anathesi');
			payload.append('diplwmatiki_id', diplwId);

			fetch('homepage_d.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: payload.toString()
			})
			.then(res => res.text().then(t => ({ status: res.status, text: t })))
			.then(obj => {
				try {
					const data = JSON.parse(obj.text);
					if (data.success) {
						// refresh all tables to reflect deleted invitations
							refreshAllTables();
							// re-open current detail with fresh data
							openDiplwmatikiDetail(diplwId);
					} else {
						alert('Σφάλμα κατά την ακύρωση: ' + (data.error || 'Άγνωστο σφάλμα'));
					}
				} catch (err) {
					console.error('Unexpected response:', obj.text);
					alert('Σφάλμα επικοινωνίας με τον διακομιστή. Δείτε την κονσόλα για λεπτομέρειες.');
				}
			})
			.catch(err => {
				console.error('Network error:', err);
				alert('Σφάλμα δικτύου. Δοκιμάστε ξανά.');
			});
		});
	}

	// Cancel diplwmatiki button (in energi-diplwmatiki panel)
	const cancelDiplwBtn = document.getElementById('cancel-diplwmatiki-btn');
	if (cancelDiplwBtn) {
		cancelDiplwBtn.addEventListener('click', function () {
			const diplwId = this.dataset.diplwmatikiId || document.querySelector('.energi-diplwmatiki')?.dataset.diplwmatikiId;
			if (!diplwId) return;
			const num = prompt('Εισάγετε Αριθμό και Έτος Γενικής Συνέλευσης:');
			if (num === null) return; // cancelled
			const payload = new URLSearchParams();
			payload.append('action', 'cancel_diplwmatiki');
			payload.append('diplwmatiki_id', diplwId);
			payload.append('cancel_number', num);

			fetch('homepage_d.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: payload.toString()
			})
			.then(r => r.text())
			.then(t => {
				try {
					const d = JSON.parse(t);
					if (d.success) {
						alert('Η διπλωματική ακυρώθηκε.');
						// refresh UI
						refreshAllTables();
						// re-open detail to reflect new status
						openDiplwmatikiDetail(diplwId);
					} else {
						alert('Σφάλμα: ' + (d.error || 'Άγνωστο'));
					}
				} catch (err) {
					console.error('Invalid response', t);
					alert('Σφάλμα επικοινωνίας με διακομιστή.');
				}
			})
			.catch(err => { console.error(err); alert('Σφάλμα δικτύου'); });
		});
	}

	// Convert to ΥΠΟ ΕΞΕΤΑΣΗ button
	const eksetasiBtn = document.getElementById('eksetasi-diplwmatiki-btn');
	if (eksetasiBtn) {
		eksetasiBtn.addEventListener('click', function () {
			const diplwId = this.dataset.diplwmatikiId || document.querySelector('.energi-diplwmatiki')?.dataset.diplwmatikiId;
			if (!diplwId) return;
			if (!confirm('Επιβεβαιώστε μετατροπή σε Διπλωματική Υπο Εξέταση;')) return;
			const payload = new URLSearchParams();
			payload.append('action', 'eksetasi_diplwmatiki');
			payload.append('diplwmatiki_id', diplwId);
			fetch('homepage_d.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
				.then(r => r.text()).then(t => {
					try { const d = JSON.parse(t); if (d.success) { refreshAllTables(); } else { alert('Σφάλμα: ' + (d.error || 'Άγνωστο')); } } catch(e){ console.error(e); alert('Invalid response'); }
					// re-open detail with updated status
					openDiplwmatikiDetail(diplwId);
				}).catch(err => { console.error(err); alert('Network error'); });
		});
	}

	// File input handling
	const fileInput = document.getElementById('pdf');
	const fileNameSpan = document.getElementById('file-name');
	if (fileInput && fileNameSpan) {
		fileInput.addEventListener('change', (event) => {
			if (event.target.files.length > 0) {
				const fileName = event.target.files[0].name;
				fileNameSpan.textContent = fileName;
			} else {
				fileNameSpan.textContent = '';
			}
		});
	} else {
		console.error('Required elements not found');
	}

	// THEMATA: Create Thema via AJAX
	const createThemaForm = document.getElementById('create-thema-form');
	if (createThemaForm) {
		createThemaForm.addEventListener('submit', (e) => {
			e.preventDefault();
			const fd = new FormData(createThemaForm);
			fd.append('action', 'create_thema');
			fetch('homepage_d.php', { method: 'POST', body: fd })
				.then(r => r.text())
				.then(t => { let d; try { d = JSON.parse(t); } catch { throw new Error('Invalid JSON'); }
					if (d.success) {
						alert('Το θέμα δημιουργήθηκε.');
						try { refreshListaThematwn(); } catch {}
						try { refreshDiathesimaThemata(); } catch {}
						// reset form UI
						createThemaForm.reset();
						const span = document.getElementById('file-name'); if (span) span.textContent = '';
					} else { alert('Αποτυχία δημιουργίας: ' + (d.error || 'Άγνωστο σφάλμα')); }
				})
				.catch(err => { console.error(err); alert('Σφάλμα δικτύου/διακομιστή.'); });
		});
	}

	// THEMATA: Assign Thema via AJAX
	const assignThemaForm = document.getElementById('assign-thema-form');
	if (assignThemaForm) {
		// Wire search input & button to query backend and render selectable results
		const searchInput = document.getElementById('search-foititis');
		const searchBtn = document.getElementById('search-foititis-button');
		const resultsDiv = document.getElementById('foititis-results');
		const hiddenFoititisId = document.getElementById('foititis-id');
		const selectedInfo = document.getElementById('foititis-selected');

		const renderResults = (rows) => {
			if (!resultsDiv) return;
			resultsDiv.innerHTML = '';
			if (!Array.isArray(rows) || rows.length === 0) {
				resultsDiv.innerHTML = '<div>Δεν βρέθηκαν αποτελέσματα.</div>';
				return;
			}
			const list = document.createElement('ul');
			list.style.listStyle = 'none';
			list.style.padding = '0';
			rows.forEach(r => {
				const li = document.createElement('li');
				li.style.cursor = 'pointer';
				li.style.padding = '6px 8px';
				li.style.border = '1px solid #ddd';
				li.style.marginBottom = '6px';
				li.textContent = `#${r.id} - ${r.lastname || ''} ${r.name || ''}`.trim();
				li.addEventListener('click', () => {
					if (hiddenFoititisId) hiddenFoititisId.value = r.id;
					if (selectedInfo) selectedInfo.textContent = `Επιλεγμένος φοιτητής: #${r.id} - ${(r.lastname || '')} ${(r.name || '')}`.trim();
					// keep results visible for change, or clear them for a cleaner UX:
					// resultsDiv.innerHTML = '';
				});
				list.appendChild(li);
			});
			resultsDiv.appendChild(list);
		};

		const doSearch = () => {
			const q = (searchInput && searchInput.value || '').trim();
			if (!q) { if (resultsDiv) resultsDiv.innerHTML = ''; return; }
			// Call backend search endpoint that returns JSON list of students
			fetch(`homepage_d.php?query=${encodeURIComponent(q)}`)
				.then(r => r.text())
				.then(t => { let data; try { data = JSON.parse(t); } catch { data = []; } renderResults(data); })
				.catch(err => { console.error('Search error:', err); if (resultsDiv) resultsDiv.innerHTML = '<div>Σφάλμα αναζήτησης.</div>'; });
		};

		if (searchBtn) {
			searchBtn.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
		}
		if (searchInput) {
			searchInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
		}

		assignThemaForm.addEventListener('submit', (e) => {
			e.preventDefault();
			const hidden = document.getElementById('foititis-id');
			const sel = document.getElementById('thema-id');
			if (!hidden || !hidden.value) { alert('Παρακαλώ επιλέξτε Φοιτητή από τα αποτελέσματα.'); return; }
			if (!sel || !sel.value) { alert('Παρακαλώ επιλέξτε Θέμα.'); return; }
			const fd = new FormData();
			fd.append('action', 'assign_thema');
			fd.append('foititis-id', hidden.value);
			fd.append('thema-id', sel.value);
			fetch('homepage_d.php', { method: 'POST', body: fd })
				.then(r => r.text())
				.then(t => { let d; try { d = JSON.parse(t); } catch { throw new Error('Invalid JSON'); }
					if (d.success) {
						alert('Η ανάθεση ολοκληρώθηκε.');
						try { refreshListaThematwn(); } catch {}
						try { refreshDiathesimaThemata(); } catch {}
						try { refreshAllTables(); } catch {}
						// Reset Assign form state: clear selected student, search input, and results
						try {
							const searchInput = document.getElementById('search-foititis');
							const resultsDiv = document.getElementById('foititis-results');
							const selectedInfo = document.getElementById('foititis-selected');
							const hiddenStudent = document.getElementById('foititis-id');
							if (searchInput) searchInput.value = '';
							if (resultsDiv) resultsDiv.innerHTML = '';
							if (selectedInfo) selectedInfo.textContent = '';
							if (hiddenStudent) hiddenStudent.value = '';
						} catch {}
					} else { alert('Αποτυχία ανάθεσης: ' + (d.error || 'Άγνωστο σφάλμα')); }
				})
				.catch(err => { console.error(err); alert('Σφάλμα δικτύου/διακομιστή.'); });
		});
	}

	// THEMATA: Edit Thema via AJAX
	const editThemaForm = document.getElementById('edit-thema-form');
	if (editThemaForm) {
		editThemaForm.addEventListener('submit', (e) => {
			e.preventDefault();
			const fd = new FormData(editThemaForm);
			fd.append('action', 'edit_thema');
			fetch('homepage_d.php', { method: 'POST', body: fd })
				.then(r => r.text())
				.then(t => { let d; try { d = JSON.parse(t); } catch { throw new Error('Invalid JSON'); }
					if (d.success) {
						alert('Οι αλλαγές αποθηκεύτηκαν.');
						try { refreshListaThematwn(); } catch {}
						try { refreshDiathesimaThemata(); } catch {}
						// navigate back to main themata section
						try { goBackToThemata(); } catch {}
					} else { alert('Αποτυχία αποθήκευσης: ' + (d.error || 'Άγνωστο σφάλμα')); }
				})
				.catch(err => { console.error(err); alert('Σφάλμα δικτύου/διακομιστή.'); });
		});
	}

	// Export diplwmatikes list in selected format (JSON or CSV)
	const exportBtn = document.getElementById('export-download-btn');
	const exportSelect = document.getElementById('export-format');
	if (exportBtn && exportSelect) {
		exportBtn.addEventListener('click', () => {
			const fmt = exportSelect.value || 'json';
			// Fetch all diplwmatikes (active + finished) via export endpoint
			fetch('homepage_d.php?action=export_all_diplwmatikes')
				.then(res => {
					if (!res.ok) return res.text().then(t => { throw new Error('Network error: ' + res.status + ' ' + t); });
					const ct = res.headers.get('content-type') || '';
					if (ct.includes('application/json')) return res.json();
					return res.text().then(t => { try { return JSON.parse(t); } catch(e) { throw new Error('Invalid JSON from server'); } });
				})
				.then(payload => {
					let rows = [];
					if (Array.isArray(payload)) rows = payload;
					else if (payload && Array.isArray(payload.data)) rows = payload.data;
					else rows = [];

					if (!Array.isArray(rows) || rows.length === 0) {
						alert('Δεν υπήρξαν διπλωματικές για εξαγωγή');
						return;
					}

					if (fmt === 'csv') {
						const headers = Object.keys(rows[0] || {});
						const csvRows = [headers.join(',')];
						rows.forEach(r => {
							const vals = headers.map(h => {
								let v = r[h];
								if (v === null || v === undefined) return '';
								v = String(v).replace(/"/g, '""');
								if (v.search(/,|\n|\r|\"/) !== -1) v = '"' + v + '"';
								return v;
							});
							csvRows.push(vals.join(','));
						});
						const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
						const url = URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'diplwmatikes_export.csv';
						document.body.appendChild(a);
						a.click();
						a.remove();
						URL.revokeObjectURL(url);
					} else {
						const blob = new Blob([JSON.stringify(rows, null, 2)], { type: 'application/json;charset=utf-8;' });
						const url = URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'diplwmatikes_export.json';
						document.body.appendChild(a);
						a.click();
						a.remove();
						URL.revokeObjectURL(url);
					}
				})
				.catch(err => {
					console.error('Export failed', err);
					alert('Αποτυχία εξαγωγής: δείτε την κονσόλα για λεπτομέρειες.');
				});
		});
	}

});


// HOME SECTION ===========================================================================

// Fetch and render "Νέες Προσκλήσεις" (recent invitations shown on home)
function refreshNewProsklhseis() {
	const tbody = document.getElementById('new-prosklhseis-body');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="6">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_new_prosklhseis')
		.then(res => {
			if (!res.ok) throw new Error('Network response was not ok');
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('Expected JSON but got:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					tr.innerHTML = `
						<td>${escapeHtml(r.foititis_name || '')}</td>
						<td>${escapeHtml(r.foititis_lastname || '')}</td>
						<td>${escapeHtml(r.foititis_phone || '')}</td>
						<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
						<td>${escapeHtml(r.type || '')}</td>
						<td>${escapeHtml(r.datetime || '')}</td>
					`;
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="6">Δεν υπάρχουν νέες προσκλήσεις.</td></tr>';
			}
		})
		.catch(err => {
			console.error('Error fetching new prosklhseis:', err);
			tbody.innerHTML = '<tr><td colspan="6">Σφάλμα κατά τη φόρτωση.</td></tr>';
		});
}

// Fetch and render "Ενεργές Διπλωματικές Υπό Επίβλεψη" (active diplwmatikes)
function refreshActiveDiplwmatikes() {
	const tbody = document.getElementById('active-diplwmatikes-body');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="5">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_active_diplwmatikes')
		.then(res => {
			if (!res.ok) return res.text().then(text => { console.error('refresh_active_diplwmatikes: non-OK', res.status, text); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refresh_active_diplwmatikes: expected JSON but received:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					tr.innerHTML = `
						<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
						<td>${escapeHtml(r.foititis_name || '')}</td>
						<td>${escapeHtml(r.foititis_lastname || '')}</td>
						<td>${escapeHtml(r.start_date || '')}</td>
						<td>${escapeHtml(r.katastash || '')}</td>
					`;
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="5">Δεν υπάρχουν ενεργές διπλωματικές.</td></tr>';
			}
		})
		.catch(err => { console.error('Error fetching active diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="5">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// THEMATA SECTION ======================================================================

function refreshListaThematwn() {
	fetch('homepage_d.php?action=refresh_lista_thematwn')
		.then(response => {
			if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
			const ct = response.headers.get('content-type') || '';
			if (!ct.includes('application/json')) {
				return response.text().then(t => { console.error('Expected JSON but got:', t); throw new Error('Invalid JSON response'); });
			}
			return response.json();
		})
		.then(data => {
			const tableBody = document.querySelector('.lista-thematwn tbody');
			if (!tableBody) return;
			tableBody.innerHTML = '';
			data.forEach(thema => {
				const row = document.createElement('tr');
				row.style.cursor = 'pointer';
				row.onclick = () => showEditThema(thema);
				row.innerHTML = `
					<td>${thema.title}</td>
					<td>${thema.description}</td>
					<td>${thema.pdf || 'N/A'}</td>
					<td>${thema.katastash}</td>
					<td>${thema.foititis_lastname}</td>
				`;
				tableBody.appendChild(row);
			});
		})
		.catch(error => console.error('Error refreshing "Λίστα Θεμάτων":', error));
}

function showEditThema(thema) {
	const lista = document.querySelector('.lista-thematwn');
	const dhm = document.querySelector('.dhmiourgia-thematos');
	const anath = document.querySelector('.anathesi-thematos');
	if (lista) lista.style.display = 'none';
	if (dhm) dhm.style.display = 'none';
	if (anath) anath.style.display = 'none';

	const editSection = document.getElementById('edit-thema');
	if (!editSection) return;
	editSection.style.display = 'block';

	const idEl = document.getElementById('edit-thema-id');
	const tEl = document.getElementById('edit-title');
	const dEl = document.getElementById('edit-description');
	const kEl = document.getElementById('edit-katastash');
	if (idEl) idEl.value = thema.id;
	if (tEl) tEl.value = thema.title;
	if (dEl) dEl.value = thema.description;
	if (kEl) kEl.value = thema.katastash;
	const fileNameSpan = document.getElementById('edit-file-name');
	if (fileNameSpan) fileNameSpan.textContent = thema.pdf || '';
}

function editThema(thema) {
    const themataSection = document.getElementById('themata');
    const editThemaSection = document.getElementById('edit-thema');

    if (!themataSection || !editThemaSection) {
        console.error('Required elements not found in the DOM.');
        return;
    }

    themataSection.classList.add('hidden');
    editThemaSection.classList.remove('hidden');

    document.getElementById('edit-thema-id').value = thema.id;
    document.getElementById('edit-title').value = thema.title;
    document.getElementById('edit-description').value = thema.description;
    document.getElementById('edit-katastash').value = thema.katastash;
}

function goBackToThemata() {
    const themataSection = document.getElementById('themata');
    const editThemaSection = document.getElementById('edit-thema');

    if (!themataSection || !editThemaSection) {
        console.error('Required elements not found in the DOM.');
        return;
    }

    editThemaSection.classList.add('hidden');
    themataSection.classList.remove('hidden');
}

function refreshDiathesimaThemata() {
	fetch('homepage_d.php?action=refresh_diathesima_themata')
		.then(response => {
			if (!response.ok) throw new Error('Network response was not ok');
			return response.json();
		})
		.then(data => {
			const select = document.getElementById('thema-id');
			if (!select) return;
			select.innerHTML = '';
			if (Array.isArray(data) && data.length > 0) {
				data.forEach(t => {
					const opt = document.createElement('option');
					opt.value = t.title;
					opt.textContent = t.title;
					select.appendChild(opt);
				});
			} else {
				const opt = document.createElement('option');
				opt.value = '';
				opt.textContent = 'Δεν υπάρχουν διαθέσιμα θέματα';
				select.appendChild(opt);
			}
		})
		.catch(err => console.error('Error fetching free themata:', err));
}

// DILPWMATIKES SECTION ======================================================================

// Fetch and render all diplwmatikes under supervision (table-1)
function refreshAllDiplwmatikes() {
	const table = document.getElementById('table-1');
	if (!table) return;
	const tbody = table.querySelector('tbody');
	if (!tbody) return;

	tbody.innerHTML = '<tr><td colspan="7">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_all_diplwmatikes')
		.then(res => {
			if (!res.ok) return res.text().then(text => { console.error('refresh_all_diplwmatikes: non-OK', res.status, text); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refresh_all_diplwmatikes: expected JSON but received:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
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
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="7">Δεν υπάρχουν διπλωματικές.</td></tr>';
			}
		})
		.catch(err => { console.error('Error fetching all diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="7">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// Fetch and render diplwmatikes where the current user is 1ο Μέλος (table-2)
function refreshDiplwmatikesAsMelos1() {
	const table = document.getElementById('table-2');
	if (!table) return;
	const tbody = table.querySelector('tbody');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="7">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_melos1_diplwmatikes')
		.then(res => {
			if (!res.ok) return res.text().then(text => { console.error('refresh_melos1_diplwmatikes: non-OK', res.status, text); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refresh_melos1_diplwmatikes: expected JSON but received:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					tr.dataset.diplwmatikiId = r.diplwmatiki_id || '';
					tr.dataset.katastash = r.katastash || '';
					tr.dataset.source = 'table-2';
					tr.innerHTML = `
						<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
						<td>${escapeHtml(r.foititis_name || '')}</td>
						<td>${escapeHtml(r.foititis_lastname || '')}</td>
						<td>${escapeHtml(r.epivlepwn_lastname || '')}</td>
						<td>${escapeHtml(r.melos2_lastname || '')}</td>
						<td>${escapeHtml(r.start_date || '')}</td>
						<td>${escapeHtml(r.katastash || '')}</td>
					`;
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="7">Δεν υπάρχουν διπλωματικές.</td></tr>';
			}
		})
		.catch(err => { console.error('Error fetching melos1 diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="7">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// Fetch and render diplwmatikes where the current user is 2ο Μέλος (table-3)
function refreshDiplwmatikesAsMelos2() {
	const table = document.getElementById('table-3');
	if (!table) return;
	const tbody = table.querySelector('tbody');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="7">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_melos2_diplwmatikes')
		.then(res => {
			if (!res.ok) return res.text().then(text => { console.error('refresh_melos2_diplwmatikes: non-OK', res.status, text); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refresh_melos2_diplwmatikes: expected JSON but received:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					tr.dataset.diplwmatikiId = r.diplwmatiki_id || '';
					tr.dataset.katastash = r.katastash || '';
					tr.dataset.source = 'table-3';
					tr.innerHTML = `
						<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
						<td>${escapeHtml(r.foititis_name || '')}</td>
						<td>${escapeHtml(r.foititis_lastname || '')}</td>
						<td>${escapeHtml(r.epivlepwn_lastname || '')}</td>
						<td>${escapeHtml(r.melos1_lastname || '')}</td>
						<td>${escapeHtml(r.start_date || '')}</td>
						<td>${escapeHtml(r.katastash || '')}</td>
					`;
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="7">Δεν υπάρχουν διπλωματικές.</td></tr>';
			}
		})
		.catch(err => { console.error('Error fetching melos2 diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="7">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// Fetch and render old/finished diplwmatikes (table-4)
function refreshOldDiplwmatikes() {
	const table = document.getElementById('table-4');
	if (!table) return;
	const tbody = table.querySelector('tbody');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="8">Φόρτωση...</td></tr>';
	fetch('homepage_d.php?action=refresh_old_diplwmatikes')
		.then(res => {
			if (!res.ok) return res.text().then(text => { console.error('refresh_old_diplwmatikes: non-OK', res.status, text); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refresh_old_diplwmatikes: expected JSON but received:', t); throw new Error('Invalid JSON response'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					tr.dataset.diplwmatikiId = r.diplwmatiki_id || '';
					tr.dataset.source = 'table-4';
					tr.innerHTML = `
						<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
						<td>${escapeHtml(r.foititis_name || '')}</td>
						<td>${escapeHtml(r.foititis_lastname || '')}</td>
						<td>${escapeHtml(r.epivlepwn_lastname || '')}</td>
						<td>${escapeHtml(r.melos1_lastname || '')}</td>
						<td>${escapeHtml(r.melos2_lastname || '')}</td>
						<td>${escapeHtml(r.bathmos || '')}</td>
					`;
					tbody.appendChild(tr);
				});
			} else {
				tbody.innerHTML = '<tr><td colspan="8">Δεν υπάρχουν διπλωματικές.</td></tr>';
			}
		})
		.catch(err => { console.error('Error fetching old diplwmatikes:', err); tbody.innerHTML = '<tr><td colspan="8">Σφάλμα κατά τη φόρτωση.</td></tr>'; });
}

// Fetch and render "Σημειώσεις" related to a specific diplwmatiki
function loadShmeiwseis(diplwmatikiId) {
	const shmeiwseisTbody = document.querySelector('#shmeiwseis-table tbody');
	if (!shmeiwseisTbody) return;
	shmeiwseisTbody.innerHTML = '<tr><td colspan="2">Φόρτωση...</td></tr>';
	fetch(`homepage_d.php?action=related_shmeiwseis&diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}`)
		.then(res => {
			if (!res.ok) throw new Error('Network response was not ok');
			return res.json();
		})
		.then(rows => {
			shmeiwseisTbody.innerHTML = '';
			if (Array.isArray(rows) && rows.length > 0) {
				rows.forEach(r => {
					const tr = document.createElement('tr');
					const date = r.datetime_created ? new Date(r.datetime_created).toLocaleString() : '';
					const author = (r.didaskwn_name || '') + ' ' + (r.didaskwn_lastname || '');
					tr.innerHTML = `\n\t\t<td>${escapeHtml(date)}</td>\n\t\t<td><strong>${escapeHtml(author)}</strong><br>${escapeHtml(r.content)}</td>\n`;
					shmeiwseisTbody.appendChild(tr);
				});
			} else {
				shmeiwseisTbody.innerHTML = '<tr><td colspan="2">Δεν υπάρχουν σημειώσεις για αυτή τη διπλωματική.</td></tr>';
			}
		})
		.catch(err => {
			console.error('Error loading shmeiwseis:', err);
			shmeiwseisTbody.innerHTML = '<tr><td colspan="2">Σφάλμα κατά τη φόρτωση.</td></tr>';
		});
}

// PROSKLHSEIS SECTION ======================================================================

function refreshProsklhseisHistory() {
	const tbody = document.getElementById('prosklhseis-history-body');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="8">Φόρτωση...</td></tr>';

	fetch('homepage_d.php?action=refresh_prosklhseis_history')
		.then(res => {
			if (!res.ok) return res.text().then(t => { console.error('refreshProsklhseisHistory: non-OK', res.status, t); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refreshProsklhseisHistory: expected JSON but got:', t); throw new Error('Invalid JSON'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (!Array.isArray(rows) || rows.length === 0) {
				tbody.innerHTML = '<tr><td colspan="8">Δεν υπάρχουν καταγεγραμμένες προσκλήσεις.</td></tr>';
				return;
			}
			rows.forEach(r => {
				const tr = document.createElement('tr');
				tr.innerHTML = `
					<td>${escapeHtml(r.foititis_name || '')}</td>
					<td>${escapeHtml(r.foititis_lastname || '')}</td>
					<td>${escapeHtml(r.foititis_phone || '')}</td>
					<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
					<td>${escapeHtml(r.type || '')}</td>
					<td>${escapeHtml(r.datetime_created || r.datetime || '')}</td>
					<td>${escapeHtml(r.datetime_answer || '—')}</td>
					<td>${escapeHtml(r.katastash || '')}</td>
				`;
				tbody.appendChild(tr);
			});
		})
		.catch(err => {
			console.error('Error loading prosklhseis history:', err);
			tbody.innerHTML = '<tr><td colspan="8">Σφάλμα κατά τη φόρτωση.</td></tr>';
		});
}

function refreshEnergesProsklhseis() {
	const tbody = document.getElementById('prosklhseis-active-body');
	if (!tbody) return;
	tbody.innerHTML = '<tr><td colspan="7">Φόρτωση...</td></tr>';

	fetch('homepage_d.php?action=refresh_energes_prosklhseis')
		.then(res => {
			if (!res.ok) return res.text().then(t => { console.error('refreshEnergesProsklhseis: non-OK', res.status, t); throw new Error('Network error'); });
			const ct = res.headers.get('content-type') || '';
			if (!ct.includes('application/json')) return res.text().then(t => { console.error('refreshEnergesProsklhseis: expected JSON but got:', t); throw new Error('Invalid JSON'); });
			return res.json();
		})
		.then(rows => {
			tbody.innerHTML = '';
			if (!Array.isArray(rows) || rows.length === 0) {
				tbody.innerHTML = '<tr><td colspan="7">Δεν υπάρχουν Προσκλήσεις.</td></tr>';
				return;
			}
			rows.forEach(r => {
				const tr = document.createElement('tr');
				tr.dataset.prosklhshId = r.id || '';
				tr.innerHTML = `
					<td>${escapeHtml(r.foititis_name || '')}</td>
					<td>${escapeHtml(r.foititis_lastname || '')}</td>
					<td>${escapeHtml(r.foititis_phone || '')}</td>
					<td>${escapeHtml(r.diplwmatiki_title || '')}</td>
					<td>${escapeHtml(r.type || '')}</td>
					<td>${escapeHtml(formatDateTime(r.datetime || r.datetime_created || '—'))}</td>
					<td>
						<button class="accept-button" data-prosklhsh-id="${escapeHtml(r.id || '')}">Αποδοχή</button>
						<button class="reject-button" data-prosklhsh-id="${escapeHtml(r.id || '')}">Απόρριψη</button>
					</td>
				`;
				tbody.appendChild(tr);
			});
		})
		.catch(err => {
			console.error('Error loading active prosklhseis:', err);
			tbody.innerHTML = '<tr><td colspan="7">Σφάλμα κατά τη φόρτωση.</td></tr>';
		});
}

// STATISTIKA SECTION ======================================================================

// Fetch and render statistics: average completion duration per finish year
let __avgDurationChart = null;
function refreshStatistika() {
	const canvas = document.getElementById('avg-duration-chart');
	if (!canvas) return;

	fetch('homepage_d.php?action=avg_duration_by_year')
		.then(res => res.text().then(t => ({ status: res.status, text: t })))
		.then(obj => {
			try {
				const parsed = JSON.parse(obj.text);
				if (!parsed || !parsed.success || !Array.isArray(parsed.data)) {
					console.error('Invalid stats response', parsed);	
					return;
				}

				const rows = parsed.data;
				const labels = rows.map(r => r.year);
				const dataEpivlepwn = rows.map(r => r.avg_days_epivlepwn !== null ? parseFloat(r.avg_days_epivlepwn) : null);
				const dataMelos = rows.map(r => r.avg_days_melos !== null ? parseFloat(r.avg_days_melos) : null);

				const cfg = {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [
							{
								label: 'Ως επιβλέπων (ημέρες)',
								data: dataEpivlepwn,
								borderColor: 'rgb(54, 162, 235)',
								backgroundColor: 'rgba(54, 162, 235, 0.6)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							},
							{
								label: 'Ως μέλος τριμελούς (ημέρες)',
								data: dataMelos,
								borderColor: 'rgb(255, 99, 132)',
								backgroundColor: 'rgba(255, 99, 132, 0.6)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							x: { title: { display: true, text: 'Έτος ολοκλήρωσης' } },
							y: { title: { display: true, text: 'Μέση διάρκεια (ημέρες)' }, beginAtZero: true }
						},
						plugins: {
							legend: { display: true },
							title: { display: true, text: 'Μέσος χρόνος περάτωσης διπλωματικών ως Επιβλέπων και ως Μέλος Τριμελούς' }
						}
					}
				};

				if (__avgDurationChart) {
					__avgDurationChart.data = cfg.data;
					__avgDurationChart.options = cfg.options;
					__avgDurationChart.update();
				} else {
					__avgDurationChart = new Chart(canvas.getContext('2d'), cfg);
				}
			} catch (e) {
				console.error('Failed to parse/render stats JSON; raw response follows:\n', obj.text, e);
			}
		})
		.catch(err => console.error('Failed to load statistics:', err));
}

// Fetch and render statistics: average grade per finish year
let __avgGradeChart = null;
function refreshAvgGradeStatistika() {
	const canvas = document.getElementById('avg-grade-chart');
	if (!canvas) return;

	fetch('homepage_d.php?action=avg_grade_by_year')
		.then(res => res.text().then(t => ({ status: res.status, text: t })))
		.then(obj => {
			try {
				const parsed = JSON.parse(obj.text);
				if (!parsed || !parsed.success || !Array.isArray(parsed.data)) {
					console.error('Invalid avg-grade response', parsed);
					return;
				}

				const rows = parsed.data;
				const labels = rows.map(r => r.year);
				const dataEpivlepwn = rows.map(r => r.avg_grade_epivlepwn !== null ? parseFloat(r.avg_grade_epivlepwn) : null);
				const dataMelos = rows.map(r => r.avg_grade_melos !== null ? parseFloat(r.avg_grade_melos) : null);

				const cfg = {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [
							{
								label: 'Μέσος Βαθμός ως Επιβλέπων',
								data: dataEpivlepwn,
								borderColor: 'rgb(75, 192, 192)',
								backgroundColor: 'rgba(75, 192, 192, 0.6)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							},
							{
								label: 'Μέσος Βαθμός ως Μέλος Τριμελούς',
								data: dataMelos,
								borderColor: 'rgb(153, 102, 255)',
								backgroundColor: 'rgba(153, 102, 255, 0.6)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							x: { title: { display: true, text: 'Έτος ολοκλήρωσης' } },
							y: { title: { display: true, text: 'Μέσος Βαθμός' }, beginAtZero: true, suggestedMax: 10 }
						},
						plugins: {
							legend: { display: true },
							title: { display: true, text: 'Μέσος Βαθμός Διπλωματικών ως Επιβλέπων και ως Μέλος Τριμελούς' }
						}
					}
				};

				if (__avgGradeChart) {
					__avgGradeChart.data = cfg.data;
					__avgGradeChart.options = cfg.options;
					__avgGradeChart.update();
				} else {
					__avgGradeChart = new Chart(canvas.getContext('2d'), cfg);
				}
			} catch (e) {
				console.error('Failed to parse/render avg-grade JSON; raw response follows:\n', obj.text, e);
			}
		})
		.catch(err => console.error('Failed to load avg-grade statistics:', err));
}

// Fetch and render statistics: total count of diplwmatikes per finish year (epivlepwn vs melos)
let __totalThesisChart = null;
function refreshTotalThesisChart() {
	const canvas = document.getElementById('total-thesis-chart');
	if (!canvas) return;

	fetch('homepage_d.php?action=count_diplwmatikes_by_year')
		.then(res => res.text().then(t => ({ status: res.status, text: t })))
		.then(obj => {
			try {
				const parsed = JSON.parse(obj.text);
				if (!parsed || !parsed.success || !Array.isArray(parsed.data)) {
					console.error('Invalid total-thesis response', parsed);
					return;
				}

				const rows = parsed.data;
				const labels = rows.map(r => r.year);
				const dataEpivlepwn = rows.map(r => parseInt(r.count_epivlepwn || 0, 10));
				const dataMelos = rows.map(r => parseInt(r.count_melos || 0, 10));

				const cfg = {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [
							{
								label: 'Πλήθος ως Επιβλέπων',
								data: dataEpivlepwn,
								backgroundColor: 'rgba(255, 159, 64, 0.7)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							},
							{
								label: 'Πλήθος ως Μέλος Τριμελούς',
								data: dataMelos,
								backgroundColor: 'rgba(54, 162, 235, 0.7)',
								barPercentage: 0.5,
								categoryPercentage: 0.6,
								maxBarThickness: 24
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							x: { title: { display: true, text: 'Έτος ολοκλήρωσης' } },
							y: { title: { display: true, text: 'Πλήθος Διπλωματικών' }, beginAtZero: true }
						},
						plugins: {
							legend: { display: true },
							title: { display: true, text: 'Συνολικό πλήθος διπλωματικών ανά έτος (Επιβλέπων vs Μέλος)' }
						}
					}
				};

				if (__totalThesisChart) {
					__totalThesisChart.data = cfg.data;
					__totalThesisChart.options = cfg.options;
					__totalThesisChart.update();
				} else {
					__totalThesisChart = new Chart(canvas.getContext('2d'), cfg);
				}

			} catch (e) {
				console.error('Failed to parse/render total-thesis JSON; raw response follows:\n', obj.text, e);
			}
		})
		.catch(err => console.error('Failed to load total-thesis statistics:', err));
}


function refreshAllTables() {
	try {
		refreshListaThematwn();
		refreshDiathesimaThemata();
		refreshAllDiplwmatikes();
		refreshDiplwmatikesAsMelos1();
		refreshDiplwmatikesAsMelos2();
		refreshOldDiplwmatikes();
		refreshNewProsklhseis();
		refreshActiveDiplwmatikes();
		refreshEnergesProsklhseis();
		refreshProsklhseisHistory();
		refreshStatistika();
	} catch (e) {
		console.error('Error while refreshing all tables:', e);
	}
}

function setActive(button) {
    const buttons = document.querySelectorAll('.nav-button');
    buttons.forEach(btn => btn.classList.remove('active')); // Remove active from all
    button.classList.add('active'); // Add active to clicked button
}

function loadContent(divId) {
    // Get all content divs
    const divs = document.querySelectorAll('.content');

    // Hide all content divs
    divs.forEach(div => {
        div.classList.remove('visible');
        div.classList.add('hidden');
    });

    // Show the selected div
    const selectedDiv = document.getElementById(divId);
    selectedDiv.classList.remove('hidden');
    selectedDiv.classList.add('visible');

	// If user opened the statistics tab, refresh the chart (useful if canvas was hidden earlier)
	try {
		if (divId === 'statistika') {
			// small timeout to allow CSS transition/layout
			setTimeout(() => { 
				try { refreshStatistika(); } catch (e) { console.error(e); }
				try { refreshAvgGradeStatistika(); } catch (e) { console.error(e); }
				try { refreshTotalThesisChart(); } catch (e) { console.error(e); }
			}, 50);
		}
	} catch (e) { console.error('Error while triggering statistika refresh', e); }


}

// small helper to avoid injecting raw HTML
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Format datetime strings (ISO or MySQL TIMESTAMP) into a human-friendly form
function formatDateTime(dt) {
	if (!dt || dt === '—') return '—';
	// try to parse with Date
	const parsed = new Date(dt);
	if (!isNaN(parsed.getTime())) {
		// e.g., 2024-08-19 12:34:56 -> locale string
		return parsed.toLocaleString();
	}
	// fallback: return original trimmed
	return String(dt).trim();
}
 
