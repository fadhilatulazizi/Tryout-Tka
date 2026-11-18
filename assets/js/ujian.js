/**
 * CBT Demak - Client Engine Ujian ANBK / Pusmendik (Versi Terpadu & Teroptimasi AJAX)
 * Dilengkapi Debounced Queued Save, Real-time Sync Status, Keyboard Shortcuts, & Zero-Lag Offline Sync
 */

(function () {
    'use strict';

    // Ambil konfigurasi dari window
    const config = window.CBT_CONFIG || {};
    const totalQuestions = parseInt(config.totalQuestions || 50, 10);
    let savedLastQ = parseInt(localStorage.getItem('cbt_last_q_' + config.studentId) || 0, 10);
    let currentNumber = (savedLastQ >= 1 && savedLastQ <= totalQuestions) 
        ? savedLastQ 
        : parseInt(config.initialNumber || 1, 10);
    let answers = config.answers || {};
    let doubts = new Set(config.doubts || []);
    let remainingSeconds = parseInt(config.remainingSeconds || 7200, 10);
    const apiUrl = config.apiUrl || 'api_jawaban.php';
    const finishUrl = config.finishUrl || 'selesai.php';
    let violationCount = 0;
    let lastBroadcastId = parseInt(localStorage.getItem('cbt_last_bc_' + config.studentId) || 0, 10);

    // Save Queue & Debounce State
    let saveQueue = {};
    let isSaving = false;
    let debounceTimer = null;
    let lastSaveTime = Date.now();

    // DOM Elements
    const timerDisplay = document.getElementById('timer-display');
    const timerPill = document.getElementById('timer-pill');
    const qBadge = document.getElementById('q-current-badge');
    const qIframe = document.getElementById('question-iframe');
    const iframeLoader = document.getElementById('iframe-loader');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnFinish = document.getElementById('btn-finish');
    const btnDoubt = document.getElementById('btn-doubt');
    const paletteGrid = document.getElementById('palette-grid');
    const answeredCountEl = document.getElementById('answered-count');
    const syncPill = document.getElementById('sync-pill');
    const syncText = document.getElementById('sync-text');
    const modalFinish = document.getElementById('modal-finish');
    const modalCheat = document.getElementById('modal-cheat');
    const modalBroadcast = document.getElementById('modal-broadcast');
    const broadcastContent = document.getElementById('broadcast-content');
    const broadcastTime = document.getElementById('broadcast-time');

    // 1. Inisialisasi Tampilan & Modul
    function init() {
        restoreOfflineBackup();
        renderPalette();
        loadQuestion(currentNumber);
        startTimer();
        setupAntiCheat();
        setupKeyboardShortcuts();
        setupBroadcast();
        setupThemeAndFont();
        setupOfflineSync();
        setSyncState('synced');

        // Setup listener iframe load untuk hide spinner & sembunyikan form legacy
        if (qIframe) {
            qIframe.addEventListener('load', () => {
                if (iframeLoader) iframeLoader.style.display = 'none';
                cleanLegacyIframeForm();
            });
        }
    }

    // Bersihkan form pilihan jawaban dan tombol kirim legacy di dalam iframe, serta auto-sinkronkan soal Benar/Salah & Checkbox
    function cleanLegacyIframeForm() {
        try {
            if (!qIframe) return;
            const doc = qIframe.contentDocument || qIframe.contentWindow?.document;
            if (!doc) return;

            // 0. Sinkronkan nomor butir soal di badge .nomor agar selalu persis sama dengan nomor di aplikasi CBT
            doc.querySelectorAll('.nomor').forEach(el => {
                el.textContent = currentNumber;
            });

            // Sembunyikan tombol submit lawas
            doc.querySelectorAll('input[type="submit"], input[name="jawab"], button[type="submit"], input[value="Kirim Jawaban"]').forEach(el => {
                el.style.display = 'none';
            });

            // Sembunyikan radio PG tunggal karena ditangani oleh panel A-E di toolbar bawah
            doc.querySelectorAll('input[type="radio"][name="jawaban"]').forEach(el => {
                el.style.display = 'none';
            });

            // Deteksi model soal di dalam iframe (Mendukung segala varian format TKA / ANBK)
            const isBS = doc.querySelector('input[name^="jawaban"]') !== null && doc.querySelector('input[name="jawaban1"]') !== null;
            const isCheckbox = doc.querySelector('input[type="checkbox"][name="jawaban[]"]') !== null;
            const isText = doc.querySelector('input[type="text"][name="jawaban"], textarea[name="jawaban"]');
            const isSelect = doc.querySelector('select[name="jawaban"]');
            const isLewati = (doc.body.textContent || '').includes('Lewati soal ini');

            const qInstruction = document.getElementById('q-instruction');
            const pgBtns = document.getElementById('pg-buttons-container');
            const cbBtns = document.getElementById('checkbox-buttons-container');
            const bsBtns = document.getElementById('bs-buttons-container');
            const complexHint = document.getElementById('complex-hint-container');
            const optTitle = document.getElementById('opt-bar-title');
            const optHint = document.getElementById('opt-bar-hint');

            // 0. Tipe Soal Dilewati (Placeholder)
            if (isLewati) {
                if (qInstruction) qInstruction.textContent = 'Nomor soal ini dilewati (silakan klik Soal Berikutnya)';
                if (pgBtns) pgBtns.style.display = 'none';
                if (cbBtns) cbBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'none';
                if (complexHint) {
                    complexHint.style.display = 'block';
                    complexHint.innerHTML = '⏩ <strong>Nomor Soal Dilewati:</strong> Nomor ini tidak berisi materi ujian. Silakan klik <strong>Soal Berikutnya ▶</strong>.';
                }
                if (optTitle) optTitle.textContent = 'STATUS: SOAL DILEWATI';
                if (optHint) optHint.textContent = 'Lanjutkan ke nomor berikutnya';
            }
            // 1. Tipe Benar / Salah (Tabel Matriks) -> Kontrol Penuh oleh Aplikasi CBT
            else if (isBS) {
                if (qInstruction) qInstruction.textContent = 'Tentukan pernyataan Benar atau Salah pada panel di bawah';
                if (pgBtns) pgBtns.style.display = 'none';
                if (cbBtns) cbBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'flex';
                if (complexHint) complexHint.style.display = 'none';

                if (optTitle) optTitle.textContent = 'PILIHAN PERNYATAAN (BENAR / SALAH):';
                if (optHint) optHint.textContent = 'Pilih tombol [✓ Benar] atau [✗ Salah] pada setiap pernyataan';

                // Render tombol interaktif Benar/Salah di panel aplikasi CBT
                renderBSButtons(doc);

                // Pulihkan pilihan di iframe jika ada
                if (answers[currentNumber]) {
                    const savedVals = answers[currentNumber].split(',');
                    savedVals.forEach(val => {
                        const targetRadio = doc.querySelector('input[type="radio"][value="' + val + '"]');
                        if (targetRadio) targetRadio.checked = true;
                    });
                }

                // Listener dua arah dari iframe ke panel aplikasi
                doc.querySelectorAll('input[name^="jawaban"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        let parts = [];
                        for (let k = 1; k <= 5; k++) {
                            const checked = doc.querySelector('input[name="jawaban' + k + '"]:checked');
                            if (checked) parts.push(checked.value);
                        }
                        const bsAns = parts.join(',');
                        answers[currentNumber] = bsAns;
                        renderBSButtons(doc);
                        updatePaletteItem(currentNumber);
                        updateAnsweredCount();
                        queueSave(currentNumber, bsAns, doubts.has(currentNumber));
                    });
                });
            } 
            // 2. Tipe Pilihan Ganda Kompleks (Centang Kotak / Checkbox) -> Kontrol Penuh oleh Aplikasi CBT
            else if (isCheckbox) {
                if (qInstruction) qInstruction.textContent = 'Pilihlah pernyataan yang sesuai pada panel di bawah (dapat centang lebih dari satu)';
                if (pgBtns) pgBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'none';
                if (cbBtns) cbBtns.style.display = 'flex';
                if (complexHint) complexHint.style.display = 'none';

                if (optTitle) optTitle.textContent = 'CENTANG PERNYATAAN YANG BENAR:';
                if (optHint) optHint.textContent = 'Klik kotak pernyataan untuk memilih / membatalkan pilihan (⌨ angka 1, 2, 3, 4)';

                // Render tombol interaktif Checkbox di panel aplikasi CBT
                renderCheckboxButtons(doc);

                // Pulihkan centang di iframe jika ada
                if (answers[currentNumber]) {
                    const savedVals = answers[currentNumber].split(',');
                    doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]').forEach(chk => {
                        chk.checked = savedVals.includes(chk.value);
                    });
                }

                // Listener dua arah dari iframe ke panel aplikasi
                doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]').forEach(chk => {
                    chk.addEventListener('change', () => {
                        let vals = [];
                        doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]:checked').forEach(c => {
                            vals.push(c.value);
                        });
                        vals.sort();
                        const cbAns = vals.join(',');
                        answers[currentNumber] = cbAns;
                        renderCheckboxButtons(doc);
                        updatePaletteItem(currentNumber);
                        updateAnsweredCount();
                        queueSave(currentNumber, cbAns, doubts.has(currentNumber));
                    });
                });
            }
            // 3. Tipe Isian Singkat / Uraian Angka
            else if (isText) {
                if (qInstruction) qInstruction.textContent = 'Ketikkan jawaban singkat Anda pada kotak yang tersedia';
                if (pgBtns) pgBtns.style.display = 'none';
                if (cbBtns) cbBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'none';
                if (complexHint) {
                    complexHint.style.display = 'block';
                    complexHint.innerHTML = '⌨ <strong>Soal Isian Singkat:</strong> Ketikkan jawaban atau angka langsung pada kotak isian di atas.';
                }
                if (optTitle) optTitle.textContent = 'MODEL SOAL: ISIAN SINGKAT';
                if (optHint) optHint.textContent = 'Ketikkan jawaban pada kotak isian';

                if (answers[currentNumber]) {
                    isText.value = answers[currentNumber];
                }
                const handleInput = () => {
                    const txtVal = isText.value.trim();
                    answers[currentNumber] = txtVal;
                    updatePaletteItem(currentNumber);
                    updateAnsweredCount();
                    queueSave(currentNumber, txtVal, doubts.has(currentNumber));
                };
                isText.addEventListener('input', handleInput);
                isText.addEventListener('change', handleInput);
            }
            // 4. Tipe Dropdown Pilihan
            else if (isSelect) {
                if (qInstruction) qInstruction.textContent = 'Pilihlah salah satu opsi dari menu tarik-turun (dropdown)';
                if (pgBtns) pgBtns.style.display = 'none';
                if (cbBtns) cbBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'none';
                if (complexHint) {
                    complexHint.style.display = 'block';
                    complexHint.innerHTML = '🔽 <strong>Menu Pilihan:</strong> Pilih salah satu jawaban dari menu dropdown di atas.';
                }
                if (optTitle) optTitle.textContent = 'MODEL SOAL: MENU PILIHAN';
                if (optHint) optHint.textContent = 'Pilih pada menu dropdown';

                if (answers[currentNumber]) {
                    isSelect.value = answers[currentNumber];
                }
                isSelect.addEventListener('change', () => {
                    const selVal = isSelect.value;
                    answers[currentNumber] = selVal;
                    updatePaletteItem(currentNumber);
                    updateAnsweredCount();
                    queueSave(currentNumber, selVal, doubts.has(currentNumber));
                });
            }
            // 5. Tipe Pilihan Ganda Biasa
            else {
                if (qInstruction) qInstruction.textContent = 'Pilihlah salah satu jawaban yang paling tepat';
                if (pgBtns) pgBtns.style.display = 'flex';
                if (cbBtns) cbBtns.style.display = 'none';
                if (bsBtns) bsBtns.style.display = 'none';
                if (complexHint) complexHint.style.display = 'none';
                if (optTitle) optTitle.textContent = 'PILIHAN JAWABAN ANDA:';
                if (optHint) optHint.textContent = '⌨ Gunakan tombol keyboard A, B, C, D, E';
            }

            // Sembunyikan seluruh form dan tombol input lawas di dalam lembar soal agar siswa 100% menggunakan aplikasi CBT
            doc.querySelectorAll('form[name="form1"], form, input[type="submit"], input[name="jawab"], button[type="submit"], input[type="radio"], input[type="checkbox"]').forEach(el => {
                el.style.display = 'none';
            });
        } catch (e) {}
    }

    // Render Tombol Interaktif Benar / Salah di Panel Aplikasi CBT
    function renderBSButtons(doc) {
        const bsContainer = document.getElementById('bs-buttons-container');
        if (!bsContainer) return;
        bsContainer.innerHTML = '';

        const rows = [];
        doc.querySelectorAll('table tr').forEach(tr => {
            const radios = tr.querySelectorAll('input[type="radio"][name^="jawaban"]');
            if (radios.length > 0) {
                const name = radios[0].name;
                const k = name.replace('jawaban', '');
                let txt = '';
                const td1 = tr.querySelector('td:first-child');
                if (td1) {
                    txt = td1.innerText.replace(/[\r\n\t]+/g, ' ').trim();
                }
                if (!txt || txt.length < 3 || txt.toLowerCase() === 'pernyataan') {
                    txt = 'Pernyataan ' + k;
                }
                rows.push({ k: k, text: txt });
            }
        });

        if (rows.length === 0) {
            for (let k = 1; k <= 3; k++) {
                rows.push({ k: String(k), text: 'Pernyataan ' + k });
            }
        }

        const currentSaved = (answers[currentNumber] || '').split(',').map(s => s.trim());

        rows.forEach(r => {
            const rowEl = document.createElement('div');
            rowEl.className = 'bs-app-row';
            rowEl.id = 'bs-row-' + r.k;
            rowEl.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: var(--exam-card-bg); border: 1.5px solid var(--exam-border); border-radius: 10px; padding: 10px 16px; gap: 14px;';

            const isB = currentSaved.includes(r.k + 'B');
            const isS = currentSaved.includes(r.k + 'S');

            rowEl.innerHTML = `
                <div style="font-size: 13px; font-weight: 600; color: #1e293b; flex: 1; line-height: 1.4;">
                    <span style="font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 2px 7px; border-radius: 6px; margin-right: 8px;">P${r.k}</span>
                    <span>${r.text}</span>
                </div>
                <div style="display: flex; gap: 8px; flex-shrink: 0;">
                    <button type="button" class="btn-bs-choice btn-bs-b" onclick="window.selectBS('${r.k}', 'B')" style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: 2px solid ${isB ? '#10b981' : '#cbd5e1'}; background: ${isB ? '#10b981' : '#ffffff'}; color: ${isB ? '#ffffff' : '#334155'}; transition: all 0.15s;">
                        ✓ Benar
                    </button>
                    <button type="button" class="btn-bs-choice btn-bs-s" onclick="window.selectBS('${r.k}', 'S')" style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: 2px solid ${isS ? '#ef4444' : '#cbd5e1'}; background: ${isS ? '#ef4444' : '#ffffff'}; color: ${isS ? '#ffffff' : '#334155'}; transition: all 0.15s;">
                        ✗ Salah
                    </button>
                </div>
            `;
            bsContainer.appendChild(rowEl);
        });
    }

    // Handler Pemilihan Benar / Salah lewat Tombol Aplikasi
    window.selectBS = function (k, status) {
        const targetCode = k + status;
        let parts = (answers[currentNumber] || '').split(',').map(s => s.trim()).filter(x => x.length > 0);
        // Hapus pilihan baris k sebelumnya
        parts = parts.filter(x => !x.startsWith(k));
        parts.push(targetCode);
        parts.sort();
        const newAns = parts.join(',');
        answers[currentNumber] = newAns;

        // Update styling tombol di panel aplikasi
        const row = document.getElementById('bs-row-' + k);
        if (row) {
            const btnB = row.querySelector('.btn-bs-b');
            const btnS = row.querySelector('.btn-bs-s');
            if (btnB && btnS) {
                if (status === 'B') {
                    btnB.style.background = '#10b981';
                    btnB.style.color = '#ffffff';
                    btnB.style.borderColor = '#10b981';
                    btnS.style.background = '#ffffff';
                    btnS.style.color = '#334155';
                    btnS.style.borderColor = '#cbd5e1';
                } else {
                    btnS.style.background = '#ef4444';
                    btnS.style.color = '#ffffff';
                    btnS.style.borderColor = '#ef4444';
                    btnB.style.background = '#ffffff';
                    btnB.style.color = '#334155';
                    btnB.style.borderColor = '#cbd5e1';
                }
            }
        }

        // Sinkronisasi otomatis ke iframe
        try {
            const doc = qIframe.contentDocument || qIframe.contentWindow?.document;
            if (doc) {
                const radio = doc.querySelector('input[type="radio"][value="' + targetCode + '"]');
                if (radio) radio.checked = true;
            }
        } catch (e) {}

        updatePaletteItem(currentNumber);
        updateAnsweredCount();
        queueSave(currentNumber, newAns, doubts.has(currentNumber));
    };

    // Render Tombol Interaktif Centang Kotak (Checkbox) di Panel Aplikasi CBT
    function renderCheckboxButtons(doc) {
        const cbContainer = document.getElementById('checkbox-buttons-container');
        if (!cbContainer) return;
        cbContainer.innerHTML = '';

        const items = [];
        doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]').forEach((chk, idx) => {
            const val = chk.value || String(idx + 1);
            let txt = '';
            const parentLabel = chk.closest('label');
            if (parentLabel) {
                const clone = parentLabel.cloneNode(true);
                clone.querySelectorAll('input, span').forEach(el => el.remove());
                txt = (clone.textContent || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
            }
            if (!txt || txt.length < 3) txt = 'Pernyataan ' + val;
            items.push({ val: val, text: txt });
        });

        if (items.length === 0) {
            for (let i = 1; i <= 4; i++) {
                items.push({ val: String(i), text: 'Pernyataan ' + i });
            }
        }

        const currentSaved = (answers[currentNumber] || '').split(',').map(s => s.trim());

        items.forEach(item => {
            const isChecked = currentSaved.includes(item.val);
            const itemEl = document.createElement('div');
            itemEl.className = 'cb-app-item cb-item-' + item.val;
            itemEl.id = 'cb-item-' + item.val;
            itemEl.style.cssText = `display: flex; align-items: center; gap: 14px; padding: 12px 18px; border: 2px solid ${isChecked ? '#0284c7' : 'var(--exam-border)'}; border-radius: 10px; cursor: pointer; background: ${isChecked ? '#e0f2fe' : 'var(--exam-card-bg)'}; transition: all 0.15s;`;
            itemEl.onclick = () => window.toggleCheckboxApp(item.val);

            itemEl.innerHTML = `
                <div class="cb-app-box" style="width: 24px; height: 24px; border-radius: 6px; border: 2px solid ${isChecked ? '#0284c7' : '#94a3b8'}; background: ${isChecked ? '#0284c7' : '#ffffff'}; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 800; font-size: 14px; flex-shrink: 0;">
                    ${isChecked ? '✓' : ''}
                </div>
                <div style="font-size: 13.5px; font-weight: 600; color: #1e293b; flex: 1; line-height: 1.4;">
                    <span style="font-weight: 800; color: #0284c7; margin-right: 6px;">[${item.val}]</span>
                    <span>${item.text}</span>
                </div>
            `;
            cbContainer.appendChild(itemEl);
        });
    }

    // Handler Centang Kotak lewat Tombol Aplikasi
    window.toggleCheckboxApp = function (val) {
        let parts = (answers[currentNumber] || '').split(',').map(s => s.trim()).filter(x => x.length > 0);
        if (parts.includes(val)) {
            parts = parts.filter(x => x !== val);
        } else {
            parts.push(val);
        }
        parts.sort();
        const newAns = parts.join(',');
        answers[currentNumber] = newAns;

        // Update styling tombol di panel aplikasi
        const itemEl = document.getElementById('cb-item-' + val);
        if (itemEl) {
            const isChecked = parts.includes(val);
            itemEl.style.borderColor = isChecked ? '#0284c7' : 'var(--exam-border)';
            itemEl.style.background = isChecked ? '#e0f2fe' : 'var(--exam-card-bg)';
            const box = itemEl.querySelector('.cb-app-box');
            if (box) {
                box.style.borderColor = isChecked ? '#0284c7' : '#94a3b8';
                box.style.background = isChecked ? '#0284c7' : '#ffffff';
                box.innerHTML = isChecked ? '✓' : '';
            }
        }

        // Sinkronisasi otomatis ke iframe
        try {
            const doc = qIframe.contentDocument || qIframe.contentWindow?.document;
            if (doc) {
                doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]').forEach(chk => {
                    chk.checked = parts.includes(chk.value);
                });
            }
        } catch (e) {}

        updatePaletteItem(currentNumber);
        updateAnsweredCount();
        queueSave(currentNumber, newAns, doubts.has(currentNumber));
    };

    // 2. Status Sinkronisasi Real-Time (UI/UX)
    function setSyncState(state) {
        if (!syncPill || !syncText) return;
        syncPill.className = 'sync-pill ' + state;
        if (state === 'saving') {
            syncText.textContent = 'Menyimpan...';
        } else if (state === 'synced') {
            syncText.textContent = 'Tersimpan';
        } else if (state === 'offline') {
            syncText.textContent = 'Tersimpan di Perangkat (Offline)';
        }
    }

    // 3. Muat Soal Nomor X
    function loadQuestion(num) {
        if (num < 1) num = 1;
        if (num > totalQuestions) num = totalQuestions;
        currentNumber = num;
        try {
            localStorage.setItem('cbt_last_q_' + config.studentId, currentNumber);
        } catch (e) {}

        // Update Badge
        if (qBadge) qBadge.textContent = 'Soal Nomor ' + currentNumber + ' dari ' + totalQuestions;

        // Navigasi Button State
        if (btnPrev) btnPrev.disabled = (currentNumber === 1);
        if (currentNumber === totalQuestions) {
            if (btnNext) btnNext.style.display = 'none';
            if (btnFinish) btnFinish.style.display = 'inline-flex';
        } else {
            if (btnNext) btnNext.style.display = 'inline-flex';
            if (btnFinish) btnFinish.style.display = 'none';
        }

        // Doubt Button State
        if (btnDoubt) {
            if (doubts.has(currentNumber)) {
                btnDoubt.classList.add('active');
                btnDoubt.innerHTML = '✓ Ragu-ragu';
            } else {
                btnDoubt.classList.remove('active');
                btnDoubt.innerHTML = '☐ Ragu-ragu';
            }
        }

        // Tampilkan loading spinner & Muat URL Soal ke Iframe
        if (qIframe) {
            if (iframeLoader) iframeLoader.style.display = 'flex';
            const folder = config.folderSoal || 'conf31';
            const src = config.basePath + 'soal/' + folder + '/' + currentNumber + '.php';
            qIframe.src = src;
        }

        updatePaletteActive();
        updateSelectedOptionUI();
    }

    // 4. Update Pilihan Jawaban Aktif pada Tombol A - E
    function updateSelectedOptionUI() {
        const currentAns = answers[currentNumber] || '';
        document.querySelectorAll('.option-item').forEach(el => {
            const val = el.getAttribute('data-value');
            if (val === currentAns) {
                el.classList.add('selected');
            } else {
                el.classList.remove('selected');
            }
        });
    }

    // 5. Simpan Jawaban (Optimistic Update + Debounced Queue)
    window.selectAnswer = function (char) {
        answers[currentNumber] = char;
        updateSelectedOptionUI();
        updatePaletteItem(currentNumber);
        updateAnsweredCount();
        queueSave(currentNumber, char, doubts.has(currentNumber));
    };

    // 6. Toggle Ragu-ragu
    window.toggleDoubt = function () {
        if (doubts.has(currentNumber)) {
            doubts.delete(currentNumber);
        } else {
            doubts.add(currentNumber);
        }

        if (btnDoubt) {
            if (doubts.has(currentNumber)) {
                btnDoubt.classList.add('active');
                btnDoubt.innerHTML = '✓ Ragu-ragu';
            } else {
                btnDoubt.classList.remove('active');
                btnDoubt.innerHTML = '☐ Ragu-ragu';
            }
        }

        updatePaletteItem(currentNumber);
        queueSave(currentNumber, answers[currentNumber] || '', doubts.has(currentNumber));
    };

    // 7. Debounced & Queued AJAX Save Engine (Mencegah Beban Berlebih di Server)
    function queueSave(no, answer, isDoubt) {
        // Cadangan lokal darurat seketika
        saveOfflineBackup(no, answer, isDoubt);

        // Masukkan antrean
        saveQueue[no] = {
            no: no,
            answer: answer,
            is_doubt: isDoubt ? 1 : 0
        };

        setSyncState('saving');
        lastSaveTime = Date.now();

        // Debounce: jika peserta klik cepat berulang kali, coalescing ke 1 request per 180ms
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            processSaveQueue();
        }, 180);
    }

    function processSaveQueue() {
        if (isSaving) return;

        const keys = Object.keys(saveQueue);
        if (keys.length === 0) {
            setSyncState('synced');
            return;
        }

        // Ambil butir pertama dari antrean
        const nextNo = keys[0];
        const item = saveQueue[nextNo];
        delete saveQueue[nextNo];

        isSaving = true;

        const payload = {
            no: item.no,
            answer: item.answer,
            is_doubt: item.is_doubt,
            csrf_token: config.csrfToken
        };

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            isSaving = false;
            // Bersihkan item dari backup offline jika berhasil disimpan ke MySQL server
            try {
                const key = 'cbt_backup_' + config.studentId;
                let current = JSON.parse(localStorage.getItem(key) || '{}');
                delete current[item.no];
                localStorage.setItem(key, JSON.stringify(current));
            } catch (e) {}

            // Jika ada siaran baru dari proktor, tangani seketika
            if (data.broadcast) {
                handleBroadcast(data.broadcast);
            }

            // Lanjutkan butir berikutnya dalam antrean
            if (Object.keys(saveQueue).length > 0) {
                processSaveQueue();
            } else {
                setSyncState('synced');
            }
        })
        .catch(() => {
            isSaving = false;
            // Jika gagal kirim karena sinyal/offline, simpan kembali ke antrean
            saveQueue[item.no] = item;
            setSyncState('offline');
        });
    }

    // 8. Keyboard Shortcuts (ANBK / CAT Pusmendik Standard)
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Abaikan jika fokus di modal selesai atau sedang mengetik teks
            const targetTag = (e.target.tagName || '').toLowerCase();
            if (targetTag === 'input' || targetTag === 'textarea' || targetTag === 'select') return;
            if (modalFinish && modalFinish.style.display === 'flex') return;

            const key = e.key.toUpperCase();

            // Pilihan Opsi A, B, C, D, E atau 1, 2, 3, 4, 5
            if (['A', 'B', 'C', 'D', 'E'].includes(key)) {
                e.preventDefault();
                selectAnswer(key);
            } else if (['1', '2', '3', '4', '5'].includes(key)) {
                e.preventDefault();
                const cbContainer = document.getElementById('checkbox-buttons-container');
                if (cbContainer && cbContainer.style.display !== 'none') {
                    window.toggleCheckboxApp(key);
                } else {
                    const mapKey = { '1': 'A', '2': 'B', '3': 'C', '4': 'D', '5': 'E' };
                    selectAnswer(mapKey[key]);
                }
            } else if (key === 'BACKSPACE' || key === 'DELETE') {
                e.preventDefault();
                selectAnswer('');
            } else if (key === 'R' || key === ' ') {
                e.preventDefault();
                toggleDoubt();
            } else if (e.key === 'ArrowLeft' || key === 'P') {
                e.preventDefault();
                prevQuestion();
            } else if (e.key === 'ArrowRight' || key === 'N') {
                e.preventDefault();
                nextQuestion();
            }
        });
    }

    // 9. Render Palette Nomor Soal
    function renderPalette() {
        if (!paletteGrid) return;
        paletteGrid.innerHTML = '';

        for (let i = 1; i <= totalQuestions; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'palette-btn';
            btn.textContent = i;
            btn.setAttribute('data-no', i);

            applyPaletteStyle(btn, i);

            btn.addEventListener('click', () => {
                loadQuestion(i);
            });

            paletteGrid.appendChild(btn);
        }

        updatePaletteActive();
        updateAnsweredCount();
    }

    function applyPaletteStyle(btn, i) {
        const hasAnswer = (answers[i] && answers[i] !== '');
        const isDoubt = doubts.has(i);

        btn.classList.remove('answered', 'doubt', 'active');

        if (isDoubt) {
            btn.classList.add('doubt');
        } else if (hasAnswer) {
            btn.classList.add('answered');
        }
    }

    function updatePaletteItem(num) {
        const btn = document.querySelector('.palette-btn[data-no="' + num + '"]');
        if (btn) {
            applyPaletteStyle(btn, num);
            if (num === currentNumber) btn.classList.add('active');
        }
    }

    function updatePaletteActive() {
        document.querySelectorAll('.palette-btn').forEach((btn, idx) => {
            const num = idx + 1;
            if (num === currentNumber) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    function updateAnsweredCount() {
        if (!answeredCountEl) return;
        let count = 0;
        for (let i = 1; i <= totalQuestions; i++) {
            if (answers[i] && answers[i] !== '') count++;
        }
        answeredCountEl.textContent = count + '/' + totalQuestions;
    }

    // 10. Countdown Timer Ujian
    function startTimer() {
        if (!timerDisplay) return;

        const interval = setInterval(() => {
            remainingSeconds--;
            if (remainingSeconds <= 0) {
                clearInterval(interval);
                timerDisplay.textContent = '00:00:00';
                alert('Waktu ujian telah berakhir! Jawaban Anda telah tersimpan otomatis.');
                window.location.href = finishUrl;
                return;
            }

            const h = Math.floor(remainingSeconds / 3600);
            const m = Math.floor((remainingSeconds % 3600) / 60);
            const s = remainingSeconds % 60;

            timerDisplay.textContent = 
                String(h).padStart(2, '0') + ':' + 
                String(m).padStart(2, '0') + ':' + 
                String(s).padStart(2, '0');

            if (remainingSeconds < 300) {
                timerPill.className = 'timer-pill danger';
            } else if (remainingSeconds < 900) {
                timerPill.className = 'timer-pill warning';
            }
        }, 1000);
    }

    // 11. Navigasi Soal
    window.nextQuestion = function () {
        if (currentNumber < totalQuestions) {
            loadQuestion(currentNumber + 1);
        }
    };

    window.prevQuestion = function () {
        if (currentNumber > 1) {
            loadQuestion(currentNumber - 1);
        }
    };

    // 12. Modal Selesai Ujian
    window.openFinishModal = function () {
        let answered = 0;
        let doubtCount = doubts.size;
        for (let i = 1; i <= totalQuestions; i++) {
            if (answers[i] && answers[i] !== '') answered++;
        }
        let unanswered = totalQuestions - answered;

        document.getElementById('sum-answered').textContent = answered;
        document.getElementById('sum-doubt').textContent = doubtCount;
        document.getElementById('sum-unanswered').textContent = unanswered;

        modalFinish.style.display = 'flex';
    };

    window.closeFinishModal = function () {
        modalFinish.style.display = 'none';
    };

    window.confirmFinishExam = function () {
        const chk = document.getElementById('chk-confirm-finish');
        if (chk && !chk.checked) {
            alert('Harap centang persetujuan konfirmasi terlebih dahulu.');
            return;
        }

        fetch(finishUrl + '?action=submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: config.csrfToken })
        })
        .then(() => {
            window.location.href = finishUrl;
        })
        .catch(() => {
            window.location.href = finishUrl;
        });
    };

    // 13. Proteksi Anti-Curang (Security Hardened)
    function setupAntiCheat() {
        // Blokir Klik Kanan
        document.addEventListener('contextmenu', e => e.preventDefault());

        // Blokir Shortcut DevTools, View Source, Print & Copy-Paste
        document.addEventListener('keydown', e => {
            const k = e.key.toLowerCase();
            if (
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && ['i', 'j', 'c'].includes(k)) ||
                (e.ctrlKey && ['c', 'v', 'x', 'u', 's', 'p', 'a'].includes(k)) ||
                e.key === 'PrintScreen'
            ) {
                e.preventDefault();
                return false;
            }
        });

        // Deteksi Pindah Tab / Kehilangan Fokus Window
        let blurTriggered = false;
        function triggerViolation() {
            if (blurTriggered) return;
            blurTriggered = true;
            setTimeout(() => { blurTriggered = false; }, 1500);

            violationCount++;
            if (modalCheat) {
                document.getElementById('cheat-count').textContent = violationCount;
                modalCheat.style.display = 'flex';
            }

            // Laporkan ke server proktor via AJAX
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'report_violation', csrf_token: config.csrfToken })
            }).catch(() => {});
        }

        window.addEventListener('blur', triggerViolation);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) triggerViolation();
        });

        // Deteksi keluar dari Fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && modalFinish.style.display !== 'flex') {
                triggerViolation();
            }
        });
    }

    window.closeCheatModal = function () {
        if (modalCheat) modalCheat.style.display = 'none';
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        }
    };

    // 14. Penerima Siaran Pesan Proktor (Chime Audio & Jittered Polling)
    function playChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.25); // A5
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) {}
    }

    function handleBroadcast(bc) {
        if (!bc || !bc.id || bc.id <= lastBroadcastId) return;
        lastBroadcastId = bc.id;
        localStorage.setItem('cbt_last_bc_' + config.studentId, bc.id);

        if (broadcastContent) broadcastContent.textContent = bc.message;
        if (broadcastTime) broadcastTime.textContent = 'Diterima dari Proktor: pukul ' + (bc.time || '--:--') + ' WIB';
        if (modalBroadcast) modalBroadcast.style.display = 'flex';

        playChime();
    }

    window.closeBroadcastModal = function () {
        if (modalBroadcast) modalBroadcast.style.display = 'none';
    };

    function setupBroadcast() {
        // Polling cerdas (Smart Jittered Polling):
        // 1. Jawaban yang dikirim siswa otomatis membawa siaran terbaru, jadi tidak perlu polling jika sedang aktif.
        // 2. Hanya polling jika siswa diam (idle) lebih dari 25 detik.
        // 3. Tambahkan acak (jitter) 0-6 detik agar 300 siswa tidak membombardir server pada detik yang sama.
        function scheduleNextPoll() {
            const jitter = Math.floor(Math.random() * 6000);
            const interval = 24000 + jitter;

            setTimeout(() => {
                // Jangan kirim polling jika tab sedang tidak dilihat atau sedang aktif menyimpan jawaban
                const isIdle = (Date.now() - lastSaveTime) > 20000;
                if (!document.hidden && isIdle) {
                    fetch(apiUrl + '?action=broadcast&last_id=' + lastBroadcastId)
                        .then(r => r.json())
                        .then(d => {
                            if (d.broadcast) handleBroadcast(d.broadcast);
                        })
                        .catch(() => {})
                        .finally(() => {
                            scheduleNextPoll();
                        });
                } else {
                    scheduleNextPoll();
                }
            }, interval);
        }

        scheduleNextPoll();
    }

    // 15. Offline Storage Synchronization
    function saveOfflineBackup(no, ans, isDoubt) {
        try {
            const key = 'cbt_backup_' + config.studentId;
            let current = JSON.parse(localStorage.getItem(key) || '{}');
            current[no] = { answer: ans, doubt: isDoubt, time: Date.now() };
            localStorage.setItem(key, JSON.stringify(current));
        } catch (e) {}
    }

    function restoreOfflineBackup() {
        const key = 'cbt_backup_' + config.studentId;
        try {
            const backup = JSON.parse(localStorage.getItem(key) || '{}');
            let hasNew = false;
            for (const no in backup) {
                const item = backup[no];
                const qNo = parseInt(no, 10);
                if (item && item.answer) {
                    if (!answers[qNo] || answers[qNo] === '') {
                        answers[qNo] = item.answer;
                        if (item.doubt) doubts.add(qNo);
                        queueSave(qNo, item.answer, item.doubt);
                        hasNew = true;
                    }
                }
            }
            if (hasNew) {
                processSaveQueue();
            }
        } catch (e) {}
    }

    function setupOfflineSync() {
        window.addEventListener('online', () => {
            setSyncState('saving');
            // Coba sinkronisasi antrean yang tertunda saat koneksi pulih
            const key = 'cbt_backup_' + config.studentId;
            try {
                const backup = JSON.parse(localStorage.getItem(key) || '{}');
                for (const no in backup) {
                    if (backup[no]) {
                        queueSave(parseInt(no, 10), backup[no].answer, backup[no].doubt);
                    }
                }
            } catch (e) {}
            processSaveQueue();
        });

        window.addEventListener('offline', () => {
            setSyncState('offline');
        });
    }

    // 16. Font & Theme Controller
    function setupThemeAndFont() {
        const savedTheme = localStorage.getItem('cbt_theme') || 'light';
        const savedFont = localStorage.getItem('cbt_font') || 'normal';

        if (savedTheme === 'dark') document.body.classList.add('theme-dark');
        document.body.classList.add('font-' + savedFont);

        window.toggleTheme = function () {
            const isDark = document.body.classList.toggle('theme-dark');
            localStorage.setItem('cbt_theme', isDark ? 'dark' : 'light');
        };

        window.setFontSize = function (size) {
            document.body.classList.remove('font-small', 'font-normal', 'font-large');
            document.body.classList.add('font-' + size);
            localStorage.setItem('cbt_font', size);
        };

        window.toggleFullscreen = function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        };
    }

    // Inisialisasi
    document.addEventListener('DOMContentLoaded', init);
})();
