let daftarBarangMaster = [];
let fotoSudahDiKompres = null; // Menyimpan hasil foto yang sudah kecil

document.addEventListener('DOMContentLoaded', () => {
    // 1. LOAD DRAFT (Mengembalikan data yang belum disempat disave)
    loadDraft();

    // 2. Ambil Master Data Barang
    fetch('get_barang.php')
    .then(res => res.json())
    .then(data => {
        daftarBarangMaster = data;
        tambahBarisBarang(); // Tambah baris kosong pertama kali
    })
    .catch(err => console.error("Gagal mengambil data barang:", err));

    // 3. AUTO-SAVE TRIGGER: Simpan ke draf tiap kali user ngetik/milih
    // PERUBAHAN: Ganti #teknisi menjadi #langganan
    const formInputs = document.querySelectorAll('#pelanggan, #tanggal, #langganan, #alamat, #link_maps, #koordinat_rumah');
    formInputs.forEach(input => {
        input.addEventListener('input', saveDraft);
        input.addEventListener('change', saveDraft);
    });

    // 3b. Muat ODP aktif dan port kosong untuk hubungan pemasangan.
    const odpSelect = document.getElementById('odp_id');
    const portSelect = document.getElementById('port_id');
    if (odpSelect && portSelect) {
        odpSelect.addEventListener('change', async () => {
            portSelect.value = '';
            saveDraft();
            await loadAvailablePorts(odpSelect.value, '');
        });
        portSelect.addEventListener('change', saveDraft);

        const coordinateField = document.getElementById('koordinat_rumah');
    const coordinateInfo = document.getElementById('koordinatInfo');
    const mapsField = document.getElementById('link_maps');
    mapsField.addEventListener('change', () => {
        if (!coordinateField.value.trim()) {
            const detected = parseCoordinates(mapsField.value);
            if (detected) {
                coordinateField.value = `${detected.latitude.toFixed(7)}, ${detected.longitude.toFixed(7)}`;
                coordinateInfo.textContent = 'Koordinat berhasil dibaca dari link Google Maps.';
                saveDraft();
            }
        }
    });
    coordinateField.addEventListener('input', () => {
        const text = coordinateField.value.trim();
        const parsed = parseCoordinates(text);
        coordinateInfo.textContent = !text
            ? 'Boleh dikosongkan; lokasi dapat ditambahkan nanti melalui menu Lokasi Pelanggan.'
            : (parsed ? `Koordinat terdeteksi: ${parsed.latitude.toFixed(7)}, ${parsed.longitude.toFixed(7)}` : 'Format belum terbaca. Gunakan latitude, longitude atau link Google Maps yang memuat koordinat.');
    });
    document.getElementById('searchAddressBtn').addEventListener('click', () => {
        const address = document.getElementById('alamat').value.trim();
        if (!address) {
            alert('Isi alamat pelanggan terlebih dahulu.');
            document.getElementById('alamat').focus();
            return;
        }
        const searchUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
        window.open(searchUrl, '_blank', 'noopener,noreferrer');
    });

        loadOdpOptions();
    } else {
        console.error('Form ODP tidak ditemukan pada halaman Pemasangan.');
    }

    // 4. Script Drag and Drop Gambar
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('foto_lokasi');

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('bg-blue-50', 'border-blue-400');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('bg-blue-50', 'border-blue-400');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('bg-blue-50', 'border-blue-400');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            previewImage(fileInput); 
        }
    });

    // 5. Handle Submit Form Pemasangan
    document.getElementById('formPemasangan').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const items = [];
        let errorDeteksi = false;

        document.querySelectorAll('#containerBarang tr').forEach(row => {
            const selectKatalog = row.querySelector('.pilih-katalog');
            const barangId = selectKatalog.value;
            
            if (!barangId) return;

            const barangItem = daftarBarangMaster.find(b => b.id == barangId);
            const isSerialized = barangItem && barangItem.is_serialized === 'Y';
            const keterangan = row.querySelector('.ket-barang').value;

            if (isSerialized) {
                const selectSN = row.querySelector('.pilih-sn');
                const serialNumber = selectSN ? selectSN.value : '';

                if (!serialNumber) {
                    alert(`Silakan pilih Serial Number untuk alat ${barangItem.nama_barang}!`);
                    errorDeteksi = true; return;
                }
                
                items.push({
                    barang_id: barangId,
                    jenis: 'Unit',
                    serial_number: serialNumber,
                    jumlah: 1, 
                    keterangan: keterangan
                });

            } else {
                const inputJumlah = row.querySelector('.jumlah-pasang');
                const jumlah = inputJumlah ? parseFloat(inputJumlah.value) : 0;

                if (jumlah <= 0 || isNaN(jumlah)) {
                    alert(`Silakan masukkan jumlah yang valid untuk ${barangItem.nama_barang}!`);
                    errorDeteksi = true; return;
                }

                items.push({
                    barang_id: barangId,
                    jenis: 'Meter',
                    serial_number: '', 
                    jumlah: jumlah,
                    keterangan: keterangan
                });
            }
        });

        if (errorDeteksi) return;

        if (items.length === 0) {
            alert("Silakan pilih minimal satu barang yang dipasang!");
            return;
        }

        const odpId = document.getElementById('odp_id').value;
        const portId = document.getElementById('port_id').value;
        if ((odpId && !portId) || (!odpId && portId)) {
            alert("Untuk menghubungkan pemasangan ke jaringan, pilih ODP dan port yang tersedia. Jika belum tahu lokasinya, pilih 'Tidak dihubungkan sekarang'.");
            return;
        }

        const koordinatText = document.getElementById('koordinat_rumah').value.trim();
        let koordinat = parseCoordinates(koordinatText);
        if (koordinatText && !koordinat) {
            alert('Format koordinat rumah belum benar. Gunakan format latitude, longitude, misalnya -7.4200000, 109.2500000.');
            document.getElementById('koordinat_rumah').focus();
            return;
        }
        if (!koordinat) koordinat = parseCoordinates(document.getElementById('link_maps').value);

        const formData = new FormData();
        formData.append('pelanggan', document.getElementById('pelanggan').value);
        formData.append('alamat', document.getElementById('alamat').value);
        formData.append('link_maps', document.getElementById('link_maps').value);
        formData.append('latitude', koordinat ? koordinat.latitude.toFixed(7) : '');
        formData.append('longitude', koordinat ? koordinat.longitude.toFixed(7) : '');
        formData.append('tanggal', document.getElementById('tanggal').value);
        // PERUBAHAN: Tangkap nilai dari id langganan
        formData.append('langganan', document.getElementById('langganan').value);
        formData.append('odp_id', odpId);
        formData.append('port_id', portId);
        formData.append('items', JSON.stringify(items));

        if(fotoSudahDiKompres) {
            formData.append('foto_lokasi', fotoSudahDiKompres, fotoSudahDiKompres.name);
        } else if(fileInput.files.length > 0) {
            formData.append('foto_lokasi', fileInput.files[0]);
        }

        const btnSubmit = this.querySelector('button[type="submit"]');
        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        btnSubmit.disabled = true;

        fetch('proses_pemasangan.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if(res.success) {
                // BERSIHKAN DRAF SETELAH BERHASIL DISIMPAN!
                localStorage.removeItem('draftPemasangan'); 
                
                alert('Pemasangan berhasil dicatat! Laporan dan Stok otomatis diperbarui.');
                window.location.href = 'laporan.html';
            } else {
                alert('Gagal: ' + res.message);
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert('Gagal terhubung ke server database!');
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        });
    });
});

// Helper: fetch API tanpa memakai cache, dengan batas waktu agar UI tidak berputar terus.
async function fetchJsonOdp(url, options = {}, timeoutMs = 10000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(url, {
            ...options,
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal
        });
        const raw = await response.text();
        let result;
        try {
            result = JSON.parse(raw);
        } catch (_) {
            throw new Error(`Server tidak mengirim JSON (HTTP ${response.status}). Periksa path API atau error PHP.`);
        }
        if (!response.ok || result.success !== true) {
            throw new Error(result.message || `Permintaan API gagal (HTTP ${response.status}).`);
        }
        return result;
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('Server terlalu lama merespons. Coba muat ulang halaman atau periksa XAMPP.');
        }
        throw error;
    } finally {
        clearTimeout(timer);
    }
}

// Memuat daftar ODP aktif dari API Port & Pelanggan.
async function loadOdpOptions() {
    const select = document.getElementById('odp_id');
    const portSelect = document.getElementById('port_id');
    const info = document.getElementById('odpAssignmentInfo');
    if (!select || !portSelect || !info) {
        console.error('Elemen ODP tidak ditemukan. Pastikan pemasangan.html yang terbaru sudah dipasang.');
        return;
    }

    const draft = getPemasanganDraft();
    select.disabled = true;
    select.innerHTML = '<option value="">Sedang memuat ODP...</option>';
    info.textContent = 'Menghubungi server untuk mengambil daftar ODP...';

    try {
        const apiUrl = new URL('api_port.php', window.location.href);
        apiUrl.searchParams.set('action', 'odps');
        apiUrl.searchParams.set('_ts', String(Date.now()));
        const result = await fetchJsonOdp(apiUrl.toString());
        const odps = Array.isArray(result.data) ? result.data : [];

        select.innerHTML = '<option value="">Tidak dihubungkan sekarang</option>' +
            odps.map(odp => {
                const status = String(odp.status || '');
                const disabled = status !== 'Aktif' ? 'disabled' : '';
                const label = `${escapeHtml(odp.kode_odp)} — ${escapeHtml(odp.nama_odp)}${status !== 'Aktif' ? ` (${escapeHtml(status)})` : ''}`;
                return `<option value="${Number(odp.id)}" ${disabled}>${label}</option>`;
            }).join('');
        select.disabled = false;

        if (odps.length === 0) {
            info.textContent = 'Koneksi berhasil, tetapi belum ada data ODP. Tambahkan ODP di menu Data ODP.';
            portSelect.innerHTML = '<option value="">Pilih ODP terlebih dahulu</option>';
            portSelect.disabled = true;
            return;
        }

        // Update status langsung setelah ODP diterima, supaya pesan loading tidak menggantung
        // ketika ada pilihan ODP lama tersimpan di draf browser.
        info.textContent = `Berhasil memuat ${odps.length} data ODP. Pilih ODP jika lokasi pelanggan sudah diketahui.`;
        portSelect.innerHTML = '<option value="">Pilih ODP terlebih dahulu</option>';
        portSelect.disabled = true;

        if (draft.odp_id && odps.some(odp => String(odp.id) === String(draft.odp_id) && String(odp.status) === 'Aktif')) {
            select.value = String(draft.odp_id);
            await loadAvailablePorts(select.value, draft.port_id || '');
        }
    } catch (error) {
        console.error('Gagal memuat ODP:', error);
        select.innerHTML = '<option value="">ODP tidak berhasil dimuat</option>';
        select.disabled = true;
        portSelect.innerHTML = '<option value="">Daftar port tidak tersedia</option>';
        portSelect.disabled = true;
        info.textContent = `Daftar ODP gagal dimuat: ${error.message} Pemasangan tetap bisa dilakukan tanpa pemetaan ODP.`;
    }
}

// Membuat catatan port jika belum ada lalu menampilkan status port di ODP terpilih.
async function loadAvailablePorts(odpId, requestedPortId = '') {
    const select = document.getElementById('port_id');
    const info = document.getElementById('odpAssignmentInfo');
    if (!select || !info) return;

    select.disabled = true;
    select.innerHTML = '<option value="">Memuat daftar port...</option>';

    if (!odpId) {
        select.innerHTML = '<option value="">Pilih ODP terlebih dahulu</option>';
        info.textContent = 'Pemilihan ODP dan port opsional. Jika dipilih, keduanya harus ditentukan.';
        saveDraft();
        return;
    }

    try {
        const ensureBody = new URLSearchParams({ action: 'ensure_ports', odp_id: String(odpId) });
        await fetchJsonOdp('api_port.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: ensureBody
        });

        const apiUrl = new URL('api_port.php', window.location.href);
        apiUrl.searchParams.set('action', 'ports');
        apiUrl.searchParams.set('odp_id', String(odpId));
        apiUrl.searchParams.set('_ts', String(Date.now()));
        const result = await fetchJsonOdp(apiUrl.toString());
        const ports = Array.isArray(result.data) ? result.data : [];

        select.innerHTML = '<option value="">-- Pilih port kosong --</option>' + ports.map(port => {
            const used = port.pemasangan_id !== null && port.pemasangan_id !== '';
            const active = String(port.status) === 'Aktif';
            const disabled = (used || !active) ? 'disabled' : '';
            const label = used
                ? `Port ${Number(port.nomor_port)} — Terpakai (${escapeHtml(port.pelanggan_nama || 'pelanggan')})`
                : (!active ? `Port ${Number(port.nomor_port)} — ${escapeHtml(port.status)}` : `Port ${Number(port.nomor_port)} — Kosong`);
            return `<option value="${Number(port.id)}" ${disabled}>${label}</option>`;
        }).join('');
        select.disabled = false;

        const requested = ports.find(port => String(port.id) === String(requestedPortId) &&
            (port.pemasangan_id === null || port.pemasangan_id === '') && String(port.status) === 'Aktif');
        if (requested) select.value = String(requested.id);

        const emptyCount = ports.filter(port =>
            (port.pemasangan_id === null || port.pemasangan_id === '') && String(port.status) === 'Aktif'
        ).length;
        info.textContent = emptyCount
            ? `Daftar ODP berhasil dimuat. ${emptyCount} port aktif masih kosong. Pastikan port sesuai kondisi lapangan.`
            : 'ODP berhasil dimuat, tetapi tidak ada port aktif yang kosong. Pilih ODP lain atau lanjutkan tanpa pemetaan.';
        saveDraft();
    } catch (error) {
        console.error('Gagal memuat port:', error);
        select.innerHTML = '<option value="">Port gagal dimuat</option>';
        select.disabled = true;
        info.textContent = `ODP berhasil dibaca, tetapi daftar port gagal dimuat: ${error.message}`;
    }
}

function parseCoordinates(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const decoded = raw.replace(/%2C/ig, ',').replace(/%20/ig, ' ');
    const number = '(-?\\d{1,2}(?:\\.\\d+)?)';
    const longitudeNumber = '(-?\\d{1,3}(?:\\.\\d+)?)';
    const patterns = [
        new RegExp('^\\s*' + number + '\\s*[,;]\\s*' + longitudeNumber + '\\s*$'),
        new RegExp('@' + number + '\\s*,\\s*' + longitudeNumber),
        new RegExp('!3d' + number + '!4d' + longitudeNumber, 'i'),
        new RegExp('!2d' + longitudeNumber + '!3d' + number, 'i')
    ];
    for (let i = 0; i < patterns.length; i++) {
        const match = decoded.match(patterns[i]);
        if (!match) continue;
        let latitude, longitude;
        if (i === 3) { longitude = Number(match[1]); latitude = Number(match[2]); }
        else { latitude = Number(match[1]); longitude = Number(match[2]); }
        if (Number.isFinite(latitude) && Number.isFinite(longitude) && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180) {
            return { latitude, longitude };
        }
    }
    try {
        const url = new URL(raw);
        for (const key of ['q', 'query', 'll', 'center', 'destination']) {
            const candidate = url.searchParams.get(key);
            if (candidate) {
                const match = candidate.match(/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/);
                if (match) {
                    const latitude = Number(match[1]), longitude = Number(match[2]);
                    if (Number.isFinite(latitude) && Number.isFinite(longitude) && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180) return { latitude, longitude };
                }
            }
        }
    } catch (_) {}
    return null;
}

function getPemasanganDraft() {
    try {
        return JSON.parse(localStorage.getItem('draftPemasangan') || '{}') || {};
    } catch (error) {
        return {};
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
}

// FITUR BARU: Menyimpan teks ke memori browser
function saveDraft() {
    const draftData = {
        pelanggan: document.getElementById('pelanggan').value,
        tanggal: document.getElementById('tanggal').value,
        // PERUBAHAN: Simpan draft langganan
        langganan: document.getElementById('langganan').value,
        alamat: document.getElementById('alamat').value,
        link_maps: document.getElementById('link_maps').value,
        koordinat_rumah: document.getElementById('koordinat_rumah').value,
        odp_id: document.getElementById('odp_id').value,
        port_id: document.getElementById('port_id').value
    };
    localStorage.setItem('draftPemasangan', JSON.stringify(draftData));
}

// FITUR BARU: Memanggil teks dari memori browser
function loadDraft() {
    const savedDraft = localStorage.getItem('draftPemasangan');
    if (savedDraft) {
        try {
            const draftData = JSON.parse(savedDraft);
            if(draftData.pelanggan) document.getElementById('pelanggan').value = draftData.pelanggan;
            if(draftData.tanggal) document.getElementById('tanggal').value = draftData.tanggal;
            if(draftData.langganan) document.getElementById('langganan').value = draftData.langganan;
            if(draftData.alamat) document.getElementById('alamat').value = draftData.alamat;
            if(draftData.link_maps) document.getElementById('link_maps').value = draftData.link_maps;
            if(draftData.koordinat_rumah) document.getElementById('koordinat_rumah').value = draftData.koordinat_rumah;
            // ODP dan port dipulihkan setelah opsi dari database selesai dimuat.
        } catch (error) {
            console.warn('Draf pemasangan tidak dapat dibaca:', error);
        }
    }
}

async function previewImage(input) {
    if (input.files && input.files[0]) {
        const imageFile = input.files[0];
        
        const options = {
            maxSizeMB: 2,
            maxWidthOrHeight: 1920,
            useWebWorker: true
        };

        const uploadContent = document.getElementById('uploadContent');
        const originalUploadHtml = uploadContent.innerHTML;

        try {
            uploadContent.innerHTML = '<i class="fas fa-spinner fa-spin text-4xl mb-3 text-blue-500"></i><p class="text-xs font-bold text-blue-500">Mengompresi foto...</p>';
            
            fotoSudahDiKompres = await browserImageCompression(imageFile, options);
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').classList.remove('hidden');
                document.getElementById('uploadContent').classList.add('opacity-0');
                
                setTimeout(() => { uploadContent.innerHTML = originalUploadHtml; }, 500);
            }
            reader.readAsDataURL(fotoSudahDiKompres);
            
        } catch (error) {
            console.error("Kompresi gagal:", error);
            fotoSudahDiKompres = imageFile; 
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').classList.remove('hidden');
                document.getElementById('uploadContent').classList.add('opacity-0');
                uploadContent.innerHTML = originalUploadHtml;
            }
            reader.readAsDataURL(imageFile);
        }
    }
}

// LOGIKA DINAMIS TAMBAH BARIS
function tambahBarisBarang() {
    const container = document.getElementById('containerBarang');
    const rowCount = container.children.length + 1;
    
    const tr = document.createElement('tr');
    tr.className = "border-b border-slate-50 group transition-all hover:bg-slate-50/50";
    
    tr.innerHTML = `
        <td class="py-4 px-2 text-center text-slate-400 text-sm font-medium nomor-baris">${rowCount}</td>
        <td class="py-4 px-2">
            <select class="pilih-katalog w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500 transition-all" onchange="handlePilihBarang(this)" required>
                <option value="">-- Pilih Katalog Barang --</option>
                ${daftarBarangMaster.map(b => {
                    const statusText = parseFloat(b.stok) <= 0 ? '(STOK HABIS)' : `(Tersedia: ${b.stok} ${b.satuan})`;
                    return `<option value="${b.id}" ${parseFloat(b.stok) <= 0 ? 'disabled' : ''}>${b.nama_barang} ${statusText}</option>`;
                }).join('')}
            </select>
        </td>
        <td class="py-4 px-2 wadah-input-dinamis text-center">
            <span class="text-xs text-slate-400 italic font-medium">Pilih barang dulu...</span>
        </td>
        <td class="py-4 px-2">
            <input type="text" class="ket-barang w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500" placeholder="Catatan pemakaian...">
        </td>
        <td class="py-4 px-2 text-center">
            <button type="button" onclick="hapusBaris(this)" class="text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all p-2 rounded-lg">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
}

// FUNGSI AJAIB: Mengubah inputan tergantung jenis barang yang dipilih
function handlePilihBarang(selectElement) {
    const tr = selectElement.closest('tr');
    const wadahInput = tr.querySelector('.wadah-input-dinamis');
    const barangId = selectElement.value;

    if (!barangId) {
        wadahInput.innerHTML = '<span class="text-xs text-slate-400 italic">Pilih barang dulu...</span>';
        return;
    }

    const barangItem = daftarBarangMaster.find(b => b.id == barangId);
    
    wadahInput.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-500"></i> <span class="text-xs text-blue-500">Mengecek gudang...</span>';

    if (barangItem.is_serialized === 'Y') {
        fetch(`get_sn_ready.php?barang_id=${barangId}`)
        .then(res => res.json())
        .then(snData => {
            if (snData.length === 0) {
                wadahInput.innerHTML = `<span class="text-xs font-bold text-red-500">Tidak ada SN tersisa!</span>`;
                selectElement.value = ''; 
            } else {
                let dropdownHtml = `
                    <select class="pilih-sn w-full px-3 py-2.5 bg-orange-50 border border-orange-200 rounded-lg text-xs font-mono font-bold text-orange-700 outline-none focus:ring-2 focus:ring-orange-500 transition-all" required>
                        <option value="">-- Pilih SN yg dipasang --</option>
                        ${snData.map(sn => `<option value="${sn.serial_number}">${sn.serial_number}</option>`).join('')}
                    </select>
                `;
                wadahInput.innerHTML = dropdownHtml;
            }
        })
        .catch(err => {
            wadahInput.innerHTML = `<span class="text-xs text-red-500">Gagal load SN</span>`;
        });

    } else {
        wadahInput.innerHTML = `
            <div class="relative">
                <input type="number" class="jumlah-pasang w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500" placeholder="10" min="0.1" step="0.1" required>
                <span class="absolute right-3 top-3 text-[10px] font-black text-slate-400 uppercase">${barangItem.satuan}</span>
            </div>
        `;
    }
}

function hapusBaris(btn) {
    const container = document.getElementById('containerBarang');
    if(container.children.length > 1) {
        btn.closest('tr').remove();
        Array.from(container.children).forEach((row, index) => {
            row.querySelector('.nomor-baris').innerText = index + 1;
        });
    } else {
        alert("Minimal harus ada satu baris pemasangan!");
    }
}