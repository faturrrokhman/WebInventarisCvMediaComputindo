// 1. Deklarasi Variabel Global
let dataBarangAsli = [];
let idBarangAkanDihapus = null;
let currentRiwayatBarang = null;
let userRole = 'superadmin'; // Akan diisi otomatis oleh session

// 2. Fungsi Manajemen Modal
function openModal() {
    const modal = document.getElementById('modalTambah');
    if(modal) {
        modal.style.display = 'flex';
        document.getElementById('formTambah').reset();
        document.getElementById('containerSNDinamis').innerHTML = '';
        
        // Default ke Material Bebas (Kabel/Klem) saat buka modal
        const radioBulk = document.querySelector('input[name="jenisPelacakan"][value="N"]');
        if(radioBulk) radioBulk.checked = true;
        
        handleJenisChange();
        updateBadgeSatuan();
    }
}
function closeModal() { document.getElementById('modalTambah').style.display = 'none'; }
function openEditModal() { document.getElementById('modalEdit').style.display = 'flex'; }
function closeEditModal() { document.getElementById('modalEdit').style.display = 'none'; }
function closeDeleteModal() { document.getElementById('modalHapus').style.display = 'none'; idBarangAkanDihapus = null; }

function tutupModalRiwayat() {
    const modal = document.getElementById('modalRiwayatStok');
    const content = document.getElementById('modalRiwayatContent');
    if(modal && content) {
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}

// 3. Inisialisasi Halaman & Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    closeModal(); closeEditModal(); closeDeleteModal();
    const modalRiwayat = document.getElementById('modalRiwayatStok');
    if(modalRiwayat) modalRiwayat.style.display = 'none';

    // CEK SESSION LOGIN SEBELUM MEMUAT BARANG
    fetch('cek_session.php')
    .then(res => {
        if (!res.ok) throw new Error("File cek_session.php bermasalah");
        return res.json();
    })
    .then(session => {
        if (session.logged_in) {
            userRole = session.role; 
        }

        // Jika Admin Biasa, Sembunyikan Tombol Tambah Barang Utama
        if (userRole === 'admin_biasa') {
            const btnTambah = document.getElementById('btnTambahBarangUtama');
            if (btnTambah) btnTambah.style.display = 'none';
        }

        loadBarang(); 
    })
    .catch(err => {
        console.warn("Info Peringatan: Session terganggu. Menggunakan mode Superadmin sementara agar data tetap tampil.", err);
        loadBarang(); 
    });

    // Listener Konfirmasi Hapus (Smart Delete)
    const btnConfirm = document.getElementById('btnConfirmDelete');
    if (btnConfirm) {
        btnConfirm.onclick = function() {
            if (!idBarangAkanDihapus) return;
            fetch('hapus-barang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idBarangAkanDihapus })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) { closeDeleteModal(); loadBarang(); }
                else { alert(data.message || 'Gagal menghapus data.'); closeDeleteModal(); }
            })
            .catch(err => console.error("Error:", err));
        };
    }

    // Auto-detect satuan dan jenis saat ngetik nama
    const inputNama = document.getElementById('namaBarang');
    if (inputNama) {
        inputNama.addEventListener('input', function(e) {
            const val = e.target.value.toLowerCase();
            const radioSN = document.querySelector('input[name="jenisPelacakan"][value="Y"]');
            const radioBulk = document.querySelector('input[name="jenisPelacakan"][value="N"]');
            const selectSatuan = document.getElementById('satuan');
            
            if (val.includes('kabel') || val.includes('fo') || val.includes('dropcore')) {
                if (radioBulk && !radioBulk.checked) { radioBulk.checked = true; handleJenisChange(); }
                if (selectSatuan) { selectSatuan.value = 'Meter'; updateBadgeSatuan(); }
            } else if (val.includes('router') || val.includes('ont') || val.includes('stb') || val.includes('modem')) {
                if (radioSN && !radioSN.checked) { radioSN.checked = true; handleJenisChange(); }
                if (selectSatuan) { selectSatuan.value = 'Unit'; updateBadgeSatuan(); }
            } else if (val.includes('klem') || val.includes('baut') || val.includes('kabel ties') || val.includes('isolasi')) {
                if (radioBulk && !radioBulk.checked) { radioBulk.checked = true; handleJenisChange(); }
                if (selectSatuan) { selectSatuan.value = 'Pack'; updateBadgeSatuan(); }
            }
        });
    }

    // Trigger update dinamis di Modal Riwayat (saat angka/radio diganti)
    const inputJumlahUpdate = document.getElementById('jumlahUpdate');
    const radiosJenisUpdate = document.querySelectorAll('input[name="jenisUpdate"]');
    
    if (inputJumlahUpdate) {
        inputJumlahUpdate.addEventListener('input', renderSnUpdateDinamis);
    }
    radiosJenisUpdate.forEach(radio => {
        radio.addEventListener('change', renderSnUpdateDinamis);
    });

    // Listener Submit Modal Update Stok Manual (Box Hijau)
    const formUpdateStok = document.getElementById('formUpdateStok');
    if(formUpdateStok) {
        formUpdateStok.onsubmit = function(e) {
            e.preventDefault();
            
            let arraySN = [];
            if (currentRiwayatBarang && currentRiwayatBarang.is_serialized === 'Y') {
                const inputSNs = document.querySelectorAll('input[name="sn_update_list[]"], select[name="sn_update_list[]"]');
                inputSNs.forEach(input => {
                    if(input.value.trim() !== '') arraySN.push(input.value.trim());
                });
                
                if (arraySN.length !== inputSNs.length) {
                    alert('Pastikan semua kolom SN (Masuk/Keluar) sudah terisi!');
                    return;
                }
            }

            const formData = {
                barang_id: document.getElementById('riwayatBarangId').value,
                jenis_update: document.querySelector('input[name="jenisUpdate"]:checked').value,
                jumlah: document.getElementById('jumlahUpdate').value,
                catatan: document.getElementById('catatanUpdate').value,
                sn_list: arraySN
            };

            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            btnSubmit.disabled = true;

            fetch('simpan_riwayat_stok.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    formUpdateStok.reset();
                    const wadahSn = document.getElementById('wadahSnUpdate');
                    if(wadahSn) wadahSn.innerHTML = '';
                    bukaModalRiwayat(formData.barang_id);
                    loadBarang(); 
                } else {
                    alert('Info: ' + data.message);
                }
            })
            .catch(err => alert("Terjadi kesalahan server!"))
            .finally(() => {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            });
        };
    }
});

// 4. Logika Tampilan UI & Form
function updateBadgeSatuan() {
    const isEdit = document.getElementById('modalEdit').style.display === 'flex';
    const satuanVal = document.getElementById(isEdit ? 'editSatuan' : 'satuan')?.value;
    
    const badgeMin = document.getElementById(isEdit ? 'badgeEditMinSatuan' : 'badgeMinSatuan');
    const badgeStok = document.getElementById('badgeSatuan');
    
    if (badgeMin && satuanVal) badgeMin.innerText = satuanVal;
    if (badgeStok && satuanVal) badgeStok.innerText = satuanVal;
}

function handleJenisChange() {
    const radioChecked = document.querySelector('input[name="jenisPelacakan"]:checked');
    if(!radioChecked) return;
    const jenisVal = radioChecked.value;
    
    const wrapperStokBulk = document.getElementById('wrapperStokBulk');
    const wrapperStokSerial = document.getElementById('wrapperStokSerial');

    if (jenisVal === 'N') {
        if(wrapperStokBulk) wrapperStokBulk.style.display = 'block';
        if(wrapperStokSerial) wrapperStokSerial.style.display = 'none';
    } else {
        if(wrapperStokBulk) wrapperStokBulk.style.display = 'none';
        if(wrapperStokSerial) wrapperStokSerial.style.display = 'block';
    }
}

function generateKolomSN() {
    const jumlahAlat = parseInt(document.getElementById('jumlahAlatMasuk').value);
    const container = document.getElementById('containerSNDinamis');
    if (isNaN(jumlahAlat) || jumlahAlat < 1) { alert('Masukkan jumlah alat yang valid!'); return; }
    if (jumlahAlat > 50) { alert('Maksimal input 50 unit.'); return; }

    let html = '<p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 mt-4">Scan / Ketik SN Satu Per Satu:</p><div class="space-y-2">';
    for (let i = 1; i <= jumlahAlat; i++) {
        html += `
            <div class="relative">
                <i class="fas fa-barcode absolute left-3 top-3.5 text-slate-400"></i>
                <input type="text" name="sn_array[]" placeholder="SN Alat ke-${i}" required class="w-full pl-9 pr-3 py-3 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white">
            </div>
        `;
    }
    html += '</div>';
    container.innerHTML = html;
}

// 5. Riwayat Update SN Dinamis (di dalam Box Hijau)
function renderSnUpdateDinamis() {
    const wadah = document.getElementById('wadahSnUpdate');
    
    if (!currentRiwayatBarang || currentRiwayatBarang.is_serialized !== 'Y') {
        if(wadah) wadah.innerHTML = '';
        return;
    }
    
    const jumlah = parseInt(document.getElementById('jumlahUpdate').value) || 0;
    const jenis = document.querySelector('input[name="jenisUpdate"]:checked').value;
    
    if (jumlah <= 0) { wadah.innerHTML = ''; return; }
    if (jumlah > 50) { alert('Maksimal proses 50 unit sekaligus!'); return; }

    if (jenis === 'masuk') {
        let html = '<p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-2 mt-2">Scan SN Baru Yang Masuk:</p>';
        for(let i = 1; i <= jumlah; i++){
            html += `
                <div class="relative mb-2">
                    <i class="fas fa-barcode absolute left-3 top-2.5 text-blue-300"></i>
                    <input type="text" name="sn_update_list[]" placeholder="SN Alat ke-${i}" required class="w-full pl-9 pr-3 py-2 bg-blue-50/50 border border-blue-200 rounded-lg text-xs font-mono outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            `;
        }
        wadah.innerHTML = html;
    } else {
        wadah.innerHTML = '<div class="text-xs text-orange-500 font-bold mt-2"><i class="fas fa-spinner fa-spin"></i> Memuat daftar SN di gudang...</div>';
        
        fetch(`get_sn_ready.php?barang_id=${currentRiwayatBarang.id}`)
        .then(res => res.json())
        .then(snData => {
            if(snData.length < jumlah) {
                wadah.innerHTML = `<p class="text-[10px] font-bold text-red-500 bg-red-50 p-2 rounded-lg mt-2">Stok fisik di sistem hanya sisa ${snData.length} unit. Angka jumlah tidak valid!</p>`;
            } else {
                let html = '<p class="text-[10px] font-bold text-orange-500 uppercase tracking-wider mb-2 mt-2">Pilih SN Yang Dikeluarkan/Rusak:</p>';
                for(let i = 1; i <= jumlah; i++){
                    html += `
                        <select name="sn_update_list[]" required class="w-full px-3 py-2 mb-2 bg-orange-50 border border-orange-200 rounded-lg text-xs font-mono font-bold text-orange-700 outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">-- Pilih SN Fisik ke-${i} --</option>
                            ${snData.map(s => `<option value="${s.serial_number}">${s.serial_number}</option>`).join('')}
                        </select>
                    `;
                }
                wadah.innerHTML = html;
            }
        });
    }
}

// 6. CRUD: Load & Render Table Utama
function loadBarang() {
    fetch('get_barang.php')
    .then(res => res.json())
    .then(data => { dataBarangAsli = data; renderTable(dataBarangAsli); })
    .catch(err => console.error("Error load data:", err));
}

function renderTable(data) {
    const tbody = document.getElementById('tabelBarang');
    const noData = document.getElementById('noData');
    
    if (!data || data.length === 0) {
        tbody.innerHTML = ''; noData.style.display = 'block';
    } else {
        noData.style.display = 'none';
        let html = '';
        data.forEach(b => {
            const isLowStock = parseFloat(b.stok) <= parseFloat(b.min_stok);
            const statusColor = isLowStock ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600';
            const statusText = isLowStock ? 'Hampir Habis' : 'Aman';
            const iconBarang = (b.is_serialized === 'Y') ? '<i class="fas fa-router text-blue-500 mr-1"></i>' : '<i class="fas fa-box-open text-amber-500 mr-1"></i>';
            
            // PERUBAHAN NAMA BADGE AGAR LEBIH AKRAB SAAT DEMO BOS
            const badgeTipe = (b.is_serialized === 'Y') ? '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[9px] rounded font-bold ml-2">PAKAI SN</span>' : '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[9px] rounded font-bold ml-2">TANPA SN</span>';

            // KONDISI RBAC: Atur Action HTML berdasarkan Role
            let actionHtml = '';
            if (userRole === 'superadmin') {
                actionHtml = `
                    <button onclick="bukaModalRiwayat('${b.id}')" class="p-2 text-emerald-500 hover:bg-emerald-100 rounded-lg transition-all" title="Kelola Stok">
                        <i class="fas fa-box-open"></i>
                    </button>
                    <button onclick='prepareEdit(${JSON.stringify(b)})' class="p-2 text-blue-500 hover:bg-blue-100 rounded-lg transition-all" title="Edit Info">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="confirmDelete('${b.id}')" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Hapus Barang">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            } else {
                actionHtml = `<span class="text-[10px] text-slate-400 italic font-bold bg-slate-100 px-2 py-1 rounded-lg"><i class="fas fa-lock"></i> Terkunci</span>`;
            }

            html += `
                <tr class="hover:bg-slate-50 transition-all border-b last:border-0">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800 uppercase text-xs">${iconBarang} ${b.nama_barang} ${badgeTipe}</div>
                        <div class="text-[10px] text-slate-400 font-medium italic mt-1">Kategori: ${b.kategori} | Min: ${b.min_stok} ${b.satuan}</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="text-sm font-black text-slate-700">${b.stok}</div>
                        <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">${b.satuan}</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="${statusColor} text-[9px] px-2.5 py-1 rounded-lg font-black uppercase tracking-widest">
                            ${statusText}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center space-x-1 whitespace-nowrap">
                        ${actionHtml}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }
}

function confirmDelete(id) {
    idBarangAkanDihapus = id; document.getElementById('modalHapus').style.display = 'flex';
}

function prepareEdit(barang) {
    document.getElementById('editId').value = barang.id;
    document.getElementById('editNamaBarang').value = barang.nama_barang;
    document.getElementById('editSatuan').value = barang.satuan || 'Unit';
    document.getElementById('editMinStok').value = barang.min_stok;
    updateBadgeSatuan(); 
    openEditModal();
}

function bukaModalRiwayat(barangId) {
    const barang = dataBarangAsli.find(b => b.id == barangId);
    if(!barang) return;

    currentRiwayatBarang = barang;
    document.getElementById('headerNamaBarang').innerText = barang.nama_barang;
    document.getElementById('headerSisaStok').innerText = `${barang.stok} ${barang.satuan}`;
    document.getElementById('riwayatBarangId').value = barang.id;
    document.getElementById('labelSatuanUpdate').innerText = barang.satuan;
    
    document.getElementById('jumlahUpdate').value = '';
    document.getElementById('catatanUpdate').value = '';
    const wadahSn = document.getElementById('wadahSnUpdate');
    if (wadahSn) wadahSn.innerHTML = ''; 

    fetch(`get_riwayat_stok.php?id=${barangId}`)
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('timelineRiwayat');
        if(data.length === 0) {
            container.innerHTML = `<div class="text-center py-10 text-slate-400 text-sm italic font-medium">Belum ada riwayat pergerakan stok.</div>`;
        } else {
            let html = '';
            data.forEach(hist => {
                const isMasuk = hist.jenis_update === 'masuk';
                const color = isMasuk ? 'emerald' : 'red';
                const sign = isMasuk ? '+' : '-';
                const icon = isMasuk ? 'plus' : 'minus';
                
                const dateObj = new Date(hist.tanggal);
                const tglFormat = dateObj.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                const timeFormat = dateObj.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

                html += `
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-slate-50 bg-${color}-100 text-${color}-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                            <i class="fas fa-${icon} text-xs"></i>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-2xl border border-slate-100 bg-white shadow-sm hover:border-${color}-200 transition-all">
                            <div class="flex items-center justify-between space-x-2 mb-1">
                                <div class="font-bold text-slate-800 text-sm capitalize">${hist.jenis_update}</div>
                                <time class="text-[10px] font-bold text-slate-400">${tglFormat} ${timeFormat}</time>
                            </div>
                            <div class="text-xs text-slate-500 mb-2 leading-relaxed">${hist.catatan || 'Tanpa Keterangan'}</div>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-${color}-50 text-${color}-600">
                                ${sign}${hist.jumlah} ${barang.satuan}
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        const modal = document.getElementById('modalRiwayatStok');
        const content = document.getElementById('modalRiwayatContent');
        if(modal && content) {
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
            }, 10);
        }
    });
}

// 7. Pencarian
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const kataKunci = e.target.value.toLowerCase();
        const dataTerfilter = dataBarangAsli.filter(barang => barang.nama_barang.toLowerCase().includes(kataKunci));
        renderTable(dataTerfilter);
    });
}

// 8. Submit Form (Tambah Barang Baru)
document.getElementById('formTambah').onsubmit = function(e) {
    e.preventDefault();
    
    const radioChecked = document.querySelector('input[name="jenisPelacakan"]:checked');
    const isSerialized = radioChecked ? radioChecked.value === 'Y' : false;
    
    let arraySN = [];
    let stokAwal = 0;

    if (isSerialized) {
        const inputSNs = document.querySelectorAll('input[name="sn_array[]"]');
        if (inputSNs.length === 0) { alert('Silakan Generate Kolom SN terlebih dahulu!'); return; }
        inputSNs.forEach(input => { if(input.value.trim() !== '') arraySN.push(input.value.trim()); });
        if (arraySN.length !== inputSNs.length) { alert('Pastikan semua kolom SN sudah terisi!'); return; }
    } else {
        stokAwal = document.getElementById('stok').value || 0;
    }

    const formData = {
        nama: document.getElementById('namaBarang').value,
        satuan: document.getElementById('satuan').value,
        minStok: document.getElementById('minStok').value,
        is_serialized: isSerialized ? 'Y' : 'N',
        sn_list: arraySN, 
        stok: stokAwal 
    };

    const btnSubmit = this.querySelector('button[type="submit"]');
    const originalText = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    btnSubmit.disabled = true;

    fetch('tambah_barang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) { closeModal(); this.reset(); loadBarang(); } 
        else { alert('Gagal: ' + data.message); }
    })
    .catch(err => { alert("Terjadi kesalahan sistem!"); console.error(err); })
    .finally(() => { btnSubmit.innerHTML = originalText; btnSubmit.disabled = false; });
};


document.getElementById('formEdit').onsubmit = function(e) {
    e.preventDefault();
    const formData = {
        id: document.getElementById('editId').value,
        nama: document.getElementById('editNamaBarang').value,
        satuan: document.getElementById('editSatuan').value,
        minStok: document.getElementById('editMinStok').value,
        harga: 0
    };
    fetch('edit_barang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) { closeEditModal(); loadBarang(); }
    });
};