/* Dedicated loader for dashboard network-summary cards.
   Deliberately independent from dashboard.js so the network cards still update
   even if the older inventory-rendering script is cached or has unrelated errors. */
(function () {
    'use strict';

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    const numberText = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        const n = Number(value);
        return Number.isFinite(n) ? n.toLocaleString('id-ID') : '—';
    };

    function renderNetwork(data) {
        const network = data && data.jaringan;
        if (!network) {
            throw new Error('Respons JSON tidak memiliki objek "jaringan".');
        }

        const ready = network.ready === true || network.ready === 1 ||
            network.ready === '1' || network.ready === 'true';

        if (ready) {
            setText('statTotalODP', numberText(network.total_odp));
            setText('statODPDipetakan', `${numberText(network.odp_dipetakan)} dari ${numberText(network.total_odp)} ODP memiliki lokasi`);
            setText('statPelangganTerpetakan', numberText(network.pelanggan_dipetakan));
            setText('statPelangganTotal', `Dari ${numberText(network.total_pelanggan)} pelanggan terdaftar`);
            setText('statPortTerpakai', numberText(network.port_terpakai));
            setText('statPortKapasitas', `Dari ${numberText(network.port_kapasitas)} slot kapasitas`);
            setText('statPortKosong', numberText(network.port_kosong));
            setText('networkSummaryMessage', network.message || 'Ringkasan jaringan berhasil dimuat.');
        } else {
            setText('statTotalODP', numberText(network.total_odp));
            setText('statPelangganTerpetakan', numberText(network.pelanggan_dipetakan));
            setText('statPortTerpakai', numberText(network.port_terpakai));
            setText('statPortKosong', numberText(network.port_kosong));
            setText('statODPDipetakan', 'Data lokasi belum tersedia');
            setText('statPelangganTotal', 'Data pelanggan belum tersedia');
            setText('statPortKapasitas', 'Data kapasitas belum tersedia');
            setText('networkSummaryMessage', network.message || 'Ringkasan jaringan belum tersedia.');
        }
    }

    async function loadNetworkSummary() {
        try {
            const url = `get_dashboard.php?network_summary_refresh=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error(`get_dashboard.php merespons HTTP ${response.status}`);
            }
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                throw new Error('Respons get_dashboard.php bukan JSON yang valid.');
            }
            renderNetwork(data);
        } catch (error) {
            console.error('Ringkasan jaringan gagal dimuat:', error);
            setText('statTotalODP', '—');
            setText('statODPDipetakan', 'Data belum berhasil dibaca');
            setText('statPelangganTerpetakan', '—');
            setText('statPelangganTotal', 'Data belum berhasil dibaca');
            setText('statPortTerpakai', '—');
            setText('statPortKapasitas', 'Data belum berhasil dibaca');
            setText('statPortKosong', '—');
            setText('networkSummaryMessage', `Ringkasan jaringan gagal dimuat: ${error.message}`);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadNetworkSummary, { once: true });
    } else {
        loadNetworkSummary();
    }
})();
