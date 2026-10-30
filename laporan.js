let dataLaporanLengkap = [];
let dataLaporanTerfilter = []; 
let currentPage = 1;
const rowsPerPage = 10;
let userRole = 'superadmin'; 
let idLaporanAkanDihapus = null;

document.addEventListener('DOMContentLoaded', () => {
    fetch('cek_session.php')
    .then(res => {
        if (!res.ok) throw new Error("File cek_session.php bermasalah");
        return res.json();
    })
    .then(session => {
        if (!session.logged_in) {
            window.location.href = 'login.html';
            return;
        }
        userRole = session.role; 
        loadLaporan();
    })
    .catch(err => {
        console.warn("Session terganggu.", err);
        loadLaporan();
    });

    const searchInput = document.getElementById('cariPelanggan');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const kataKunci = e.target.value.toLowerCase();
            dataLaporanTerfilter = dataLaporanLengkap.filter(item => 
                item.pelanggan_nama.toLowerCase().includes(kataKunci)
            );
            currentPage = 1; 
            kalkulasiStatistik(); 
            renderTable(); 
        });
    }

    const formEdit = document.getElementById('formEditLaporan');
    if (formEdit) {
        formEdit.addEventListener('submit', function(e) {
            e.preventDefault();
            // PERUBAHAN: Mengambil field editLangganan
            const formData = {
                id: document.getElementById('editLaporanId').value,
                pelanggan: document.getElementById('editNamaPelanggan').value,
                alamat: document.getElementById('editAlamat').value,
                tanggal: document.getElementById('editTanggal').value,
                langganan: document.getElementById('editLangganan').value
            };

            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            btnSubmit.disabled = true;

            fetch('edit_laporan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) { closeEditLaporan(); loadLaporan(); } 
                else { alert('Gagal Edit: ' + data.message); }
            })
            .catch(err => alert('Terjadi kesalahan koneksi'))
            .finally(() => { btnSubmit.innerHTML = originalText; btnSubmit.disabled = false; });
        });
    }

    const btnConfirmDelete = document.getElementById('btnConfirmDeleteLaporan');
    if (btnConfirmDelete) {
        btnConfirmDelete.onclick = function() {
            if (!idLaporanAkanDihapus) return;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
            this.disabled = true;

            fetch('hapus_laporan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idLaporanAkanDihapus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) { 
                    closeDeleteLaporan(); 
                    if(document.getElementById('cariPelanggan')) document.getElementById('cariPelanggan').value = '';
                    loadLaporan(); 
                } 
                else { alert(data.message || 'Gagal menghapus data.'); closeDeleteLaporan(); }
            })
            .catch(err => alert("Kesalahan server"))
            .finally(() => { this.innerHTML = originalText; this.disabled = false; });
        };
    }
});

function loadLaporan(mulai = '', selesai = '') {
    let url = 'get_laporan.php';
    if (mulai && selesai) url += `?mulai=${mulai}&selesai=${selesai}`;

    fetch(url)
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            dataLaporanLengkap = res.data;
            dataLaporanTerfilter = [...dataLaporanLengkap]; 
            currentPage = 1;
            kalkulasiStatistik();
            renderTable();
        } else { console.error("Gagal ambil laporan"); }
    })
    .catch(err => console.error(err));
}

function kalkulasiStatistik() {
    let totalPemasangan = dataLaporanTerfilter.length;
    let totalUnit = 0; let totalMeter = 0;
    
    dataLaporanTerfilter.forEach(item => {
        totalUnit += parseFloat(item.total_unit);
        totalMeter += parseFloat(item.total_meter);
    });
    
    document.getElementById('statTotalPemasangan').innerText = totalPemasangan;
    document.getElementById('statTotalUnit').innerText = totalUnit;
    document.getElementById('statTotalMeter').innerText = totalMeter.toLocaleString('id-ID');
}

function filterLaporan() {
    const mulai = document.getElementById('tglMulai').value;
    const selesai = document.getElementById('tglSelesai').value;
    if (!mulai || !selesai) { alert("Silakan isi tanggal mulai dan selesai!"); return; }
    
    if(document.getElementById('cariPelanggan')) document.getElementById('cariPelanggan').value = '';
    
    loadLaporan(mulai, selesai);
}

function renderTable() {
    const tbody = document.getElementById('tabelLaporan');
    tbody.innerHTML = '';

    if (dataLaporanTerfilter.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-slate-400">Pencarian tidak ditemukan atau belum ada data.</td></tr>`;
        updatePagination(0); return;
    }

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const currentData = dataLaporanTerfilter.slice(startIndex, endIndex);

    currentData.forEach(item => {
        const tglObj = new Date(item.tanggal_pasang);
        const tglFormat = tglObj.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

        let alamatHtml = `<div class="font-medium text-xs text-slate-600">${item.alamat}</div>`;
        if (item.link_maps) {
            alamatHtml += `<a href="${item.link_maps}" target="_blank" class="text-[10px] font-bold text-blue-500 hover:text-blue-700 flex items-center gap-1 mt-1"><i class="fas fa-map-marker-alt"></i> Buka di Maps</a>`;
        }

        let materialHtml = item.detail.map(d => {
            let teksKeterangan = (d.keterangan && d.keterangan.trim() !== '') 
                ? ` <span class="text-[10px] text-orange-600 font-medium bg-orange-50 px-1.5 py-0.5 rounded ml-1 border border-orange-100"><i class="fas fa-pencil-alt text-[9px] mr-1"></i>${d.keterangan}</span>` 
                : '';

            if (d.is_serialized === 'Y') {
                return `<div class="mb-2 text-xs text-slate-700 flex flex-wrap items-center gap-y-1"><b class="text-blue-600 mr-1">1 Unit</b> ${d.nama_barang}${teksKeterangan}<div class="w-full mt-0.5"><span class="text-[10px] text-slate-400 font-mono font-bold bg-slate-100 px-1 py-0.5 rounded">SN: ${d.serial_number}</span></div></div>`;
            } else {
                return `<div class="mb-2 text-xs text-slate-700 flex flex-wrap items-center"><b class="text-emerald-600 mr-1">${parseFloat(d.jumlah_terpakai)} ${d.satuan_hitung}</b> ${d.nama_barang}${teksKeterangan}</div>`;
            }
        }).join('<hr class="my-2 border-slate-100">');

        let fotoBtn = `<span class="text-[10px] text-slate-400 italic">Tanpa Foto</span>`;
        if (item.foto_lokasi) {
            fotoBtn = `
                <button onclick="lihatFoto('uploads/${item.foto_lokasi}', '${item.pelanggan_nama}')" class="flex flex-col items-center justify-center gap-1 text-blue-500 hover:text-blue-700 transition-colors mx-auto group">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                        <i class="fas fa-image text-sm"></i>
                    </div><span class="text-[9px] font-bold uppercase tracking-wider">Lihat</span>
                </button>
            `;
        }

        let btnHapusHtml = '';
        if (userRole === 'superadmin') {
            btnHapusHtml = `
                <button onclick="confirmDeleteLaporan(${item.id})" class="w-8 h-8 bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 rounded-lg transition-colors flex items-center justify-center" title="Hapus Laporan">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            `;
        }

        const tr = document.createElement('tr');
        tr.className = "hover:bg-slate-50 transition-colors";
        
        tr.innerHTML = `
            <td class="py-4 px-4 text-center">
                <div class="flex justify-center gap-1">
                    <button onclick='prepareEdit(${JSON.stringify(item)})' class="w-8 h-8 bg-slate-100 hover:bg-blue-100 text-slate-500 hover:text-blue-600 rounded-lg transition-colors flex items-center justify-center" title="Edit Laporan">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ${btnHapusHtml}
                </div>
            </td>
            <td class="py-4 px-4">
                <div class="font-bold text-slate-700 text-xs">${tglFormat}</div>
            </td>
            <td class="py-4 px-4"><div class="font-bold text-slate-800 text-sm uppercase">${item.pelanggan_nama}</div></td>
            <td class="py-4 px-4 text-center align-middle">${fotoBtn}</td>
            <td class="py-4 px-4 w-48">${alamatHtml}</td>
            <td class="py-4 px-4"><div class="bg-slate-50 p-3 rounded-xl border border-slate-100">${materialHtml || '-'}</div></td>
            <td class="py-4 px-4 text-center"><span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl font-bold text-[10px] tracking-wider uppercase inline-flex items-center gap-1.5"><i class="fas fa-wifi text-blue-400"></i> ${item.langganan}</span></td>
        `;
        tbody.appendChild(tr);
    });

    updatePagination(dataLaporanTerfilter.length);
}

function prepareEdit(item) {
    document.getElementById('editLaporanId').value = item.id;
    document.getElementById('editNamaPelanggan').value = item.pelanggan_nama;
    document.getElementById('editAlamat').value = item.alamat;
    document.getElementById('editTanggal').value = item.tanggal_pasang;
    // PERUBAHAN: Set value untuk form edit langganan
    document.getElementById('editLangganan').value = item.langganan;
    document.getElementById('modalEditLaporan').style.display = 'flex';
}

function confirmDeleteLaporan(id) {
    idLaporanAkanDihapus = id;
    document.getElementById('modalHapusLaporan').style.display = 'flex';
}

function closeDeleteLaporan() {
    idLaporanAkanDihapus = null;
    document.getElementById('modalHapusLaporan').style.display = 'none';
}

function closeEditLaporan() { document.getElementById('modalEditLaporan').style.display = 'none'; }
function tutupModalFoto() { document.getElementById('modalLihatFoto').style.display = 'none'; }
function lihatFoto(urlFoto, namaPelanggan) {
    document.getElementById('gambarPreviewModal').src = urlFoto;
    document.getElementById('teksLokasiFoto').innerText = `Lokasi: ${namaPelanggan}`;
    document.getElementById('modalLihatFoto').style.display = 'flex';
}

function updatePagination(totalRows) {
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    document.getElementById('infoHalaman').innerText = `Menampilkan data ${(currentPage - 1) * rowsPerPage + (totalRows > 0 ? 1 : 0)} - ${Math.min(currentPage * rowsPerPage, totalRows)} dari ${totalRows}`;
    document.getElementById('btnPrev').disabled = currentPage === 1;
    document.getElementById('btnNext').disabled = currentPage === totalPages || totalPages === 0;
    const divHalaman = document.getElementById('halamanList');
    divHalaman.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.className = `w-8 h-8 rounded-lg text-xs font-bold transition-all ${currentPage === i ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}`;
        btn.onclick = () => { currentPage = i; renderTable(); };
        divHalaman.appendChild(btn);
    }
}

function prevPage() { if (currentPage > 1) { currentPage--; renderTable(); } }
function nextPage() { const totalPages = Math.ceil(dataLaporanTerfilter.length / rowsPerPage); if (currentPage < totalPages) { currentPage++; renderTable(); } }

function exportToExcel() {
    if (dataLaporanTerfilter.length === 0) { alert("Tidak ada data untuk di-export"); return; }
    let dataExport = [];
    
    dataLaporanTerfilter.forEach(item => {
        let listMaterial = item.detail.map(d => {
            let detailKet = (d.keterangan && d.keterangan.trim() !== '') ? ` [Catatan: ${d.keterangan}]` : '';
            
            if (d.is_serialized === 'Y') return `1 Unit ${d.nama_barang} (SN: ${d.serial_number})${detailKet}`;
            return `${parseFloat(d.jumlah_terpakai)} ${d.satuan_hitung} ${d.nama_barang}${detailKet}`;
        }).join(" \n ");
        
        // PERUBAHAN: Export Langganan ke file Excel
        dataExport.push({ "Tanggal": item.tanggal_pasang, "Pelanggan": item.pelanggan_nama, "Alamat": item.alamat, "Langganan": item.langganan, "Rincian Material": listMaterial });
    });
    
    const worksheet = XLSX.utils.json_to_sheet(dataExport);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Laporan Pemasangan");
    XLSX.writeFile(workbook, "Laporan_Pemasangan.xlsx");
}