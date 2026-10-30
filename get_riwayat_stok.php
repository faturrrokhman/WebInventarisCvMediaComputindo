<?php
header('Content-Type: application/json');
include 'koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$history = [];

if ($id <= 0) {
    echo json_encode([]);
    exit;
}

// 1. AMBIL RIWAYAT KELUAR (Dari Laporan Pemasangan Teknisi)
$sql_keluar = "SELECT 'keluar' AS jenis_update, pd.jumlah_terpakai AS jumlah, 
               CONCAT('Dipasang di: ', p.pelanggan_nama, IF(pd.serial_number IS NOT NULL, CONCAT(' (SN: ', pd.serial_number, ')'), '')) AS catatan, 
               p.waktu_input AS tanggal 
               FROM pemasangan_detail pd 
               JOIN pemasangan p ON pd.pemasangan_id = p.id 
               WHERE pd.barang_id = $id";
               
$res_keluar = $conn->query($sql_keluar);
if ($res_keluar) {
    while ($row = $res_keluar->fetch_assoc()) {
        $history[] = $row;
    }
}

// 2. AMBIL RIWAYAT MASUK ALAT (Dari Input SN Admin Gudang)
$sql_masuk_serial = "SELECT 'masuk' AS jenis_update, 1 AS jumlah, 
                     CONCAT('Input ke Gudang. (SN: ', serial_number, ')') AS catatan, 
                     tanggal_masuk AS tanggal 
                     FROM inventaris_serial 
                     WHERE barang_id = $id";
                     
$res_masuk = $conn->query($sql_masuk_serial);
if ($res_masuk) {
    while ($row = $res_masuk->fetch_assoc()) {
        $history[] = $row;
    }
}

// 3. AMBIL RIWAYAT UPDATE MANUAL KABEL (Dari Tabel Baru)
$sql_manual = "SELECT jenis_update, jumlah, catatan, tanggal 
               FROM log_stok_manual 
               WHERE barang_id = $id";
               
$res_manual = $conn->query($sql_manual);
if ($res_manual) {
    while ($row = $res_manual->fetch_assoc()) {
        // Tambahkan label agar tahu ini input manual
        $row['catatan'] = "[Update Manual] " . $row['catatan'];
        $history[] = $row;
    }
}

// Urutkan Riwayat dari yang Terbaru (Descending) berdasarkan waktu/tanggal
usort($history, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

echo json_encode($history);
$conn->close();
?>