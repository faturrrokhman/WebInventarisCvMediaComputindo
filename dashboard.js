document.addEventListener('DOMContentLoaded', () => {
    fetch('get_dashboard.php?dashboard_refresh=' + Date.now(), { cache: 'no-store' })
    .then(res => {
        if (!res.ok) throw new Error('get_dashboard.php merespons HTTP ' + res.status);
        return res.json();
    })
    .then(data => {
        
        // 1. Update Kotak Statistik Angka
        document.getElementById('statTotalBarang').innerText = data.total_barang;
        document.getElementById('statTotalPemasangan').innerText = data.total_pemasangan;
        document.getElementById('statStokMenipis').innerText = data.stok_menipis_count;


        // 1a. Ringkasan jaringan yang diambil dari database.
        const jaringan = data.jaringan || {};
        const setText = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        };
        const numberText = value => value === null || value === undefined || !Number.isFinite(Number(value))
            ? '—'
            : Number(value).toLocaleString('id-ID');
        if (jaringan.ready) {
            setText('statTotalODP', numberText(jaringan.total_odp));
            setText('statODPDipetakan', `${numberText(jaringan.odp_dipetakan)} dari ${numberText(jaringan.total_odp)} ODP memiliki lokasi`);
            setText('statPelangganTerpetakan', numberText(jaringan.pelanggan_dipetakan));
            setText('statPelangganTotal', `Dari ${numberText(jaringan.total_pelanggan)} pelanggan terdaftar`);
            setText('statPortTerpakai', numberText(jaringan.port_terpakai));
            setText('statPortKapasitas', `Dari ${numberText(jaringan.port_kapasitas)} slot kapasitas`);
            setText('statPortKosong', numberText(jaringan.port_kosong));
            setText('networkSummaryMessage', jaringan.message || 'Ringkasan jaringan berhasil dimuat.');
        } else {
            ['statTotalODP', 'statPelangganTerpetakan', 'statPortTerpakai', 'statPortKosong'].forEach(id => setText(id, '—'));
            setText('statODPDipetakan', 'Data lokasi belum tersedia');
            setText('statPelangganTotal', 'Data pelanggan belum tersedia');
            setText('statPortKapasitas', 'Data kapasitas belum tersedia');
            setText('networkSummaryMessage', jaringan.message || 'Ringkasan jaringan belum dapat dimuat.');
        }

        // 2. Render Tabel Pemasangan Terbaru
        const tbodyPem = document.getElementById('tabelPemasangan');
        tbodyPem.innerHTML = '';
        
        if (data.pemasangan_terbaru.length === 0) {
            tbodyPem.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-slate-400 text-sm">Belum ada aktivitas pemasangan.</td></tr>';
        } else {
            data.pemasangan_terbaru.forEach(p => {
                const tglObj = new Date(p.tanggal_pasang);
                const tglFormat = tglObj.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                
                tbodyPem.innerHTML += `
                    <tr class="hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-0">
                        <td class="py-4 px-2">
                            <div class="font-bold text-slate-800 text-xs md:text-sm uppercase">${p.pelanggan_nama}</div>
                            <div class="text-[10px] text-slate-400 mt-1 truncate max-w-[120px] md:max-w-[200px] italic">${p.alamat}</div>
                        </td>
                        <td class="py-4 px-2">
                            <div class="text-[10px] md:text-xs text-slate-600 leading-relaxed bg-slate-50 p-2 rounded-lg border border-slate-100">
                                ${p.material_html || '-'}
                            </div>
                        </td>
                        <td class="py-4 px-2 text-center">
                            <span class="bg-blue-50 text-blue-600 px-2.5 py-1.5 rounded-lg font-bold text-[9px] uppercase tracking-wider whitespace-nowrap inline-flex items-center gap-1.5">
                                <i class="fas fa-wifi text-blue-400"></i> ${p.langganan}
                            </span>
                        </td>
                        <td class="py-4 px-2 text-right">
                            <div class="font-bold text-slate-600 text-[10px] md:text-xs">${tglFormat}</div>
                        </td>
                    </tr>
                `;
            });
        }

        // 3. Render Daftar List Stok Menipis
        const listStok = document.getElementById('listStokMenipis');
        listStok.innerHTML = '';
        
        if (data.stok_menipis_list.length === 0) {
            listStok.innerHTML = `
                <div class="text-center py-8 text-slate-400 flex flex-col items-center">
                    <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center mb-3 text-xl"><i class="fas fa-check"></i></div>
                    <span class="text-sm font-medium">Semua stok barang aman</span>
                </div>`;
        } else {
            data.stok_menipis_list.forEach(s => {
                const icon = s.is_serialized === 'Y' ? 'fa-router text-blue-500' : 'fa-bolt text-amber-500';
                
                listStok.innerHTML += `
                    <div class="flex items-center justify-between p-3 md:p-4 bg-red-50/50 border border-red-100 rounded-2xl hover:border-red-200 transition-colors">
                        <div class="flex items-center gap-3 md:gap-4">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                <i class="fas ${icon} text-sm md:text-base"></i>
                            </div>
                            <div>
                                <h5 class="font-bold text-slate-800 text-[11px] md:text-xs uppercase">${s.nama_barang}</h5>
                                <p class="text-[9px] text-red-500 font-bold mt-0.5 uppercase tracking-wider">Segera Restock!</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg md:text-xl font-black text-red-600 leading-none">${s.sisa_stok}</div>
                            <div class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">${s.satuan}</div>
                        </div>
                    </div>
                `;
            });
        }
    })
    .catch(err => {
        console.error("Error loading dashboard data:", err);
        // Jangan biarkan kartu Ringkasan Jaringan terus menampilkan status "Memuat...".
        const setText = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        };
        ['statTotalODP', 'statPelangganTerpetakan', 'statPortTerpakai', 'statPortKosong'].forEach(id => setText(id, '—'));
        setText('statODPDipetakan', 'Data belum berhasil dibaca');
        setText('statPelangganTotal', 'Data belum berhasil dibaca');
        setText('statPortKapasitas', 'Data belum berhasil dibaca');
        setText('networkSummaryMessage', 'Ringkasan jaringan gagal dimuat. Periksa get_dashboard.php dan struktur database, lalu muat ulang halaman.');
        // Tidak memakai alert berulang; pesan error tampil langsung di dashboard.
    });
});