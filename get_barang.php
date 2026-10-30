<?php
header('Content-Type: application/json');
include 'koneksi.php';

// Mengambil data dari tabel katalog, digabung dengan tabel bulk, dan dihitung otomatis dari tabel serial
$sql = "SELECT k.id, k.nama_barang, k.kategori, k.satuan_hitung AS satuan, k.is_serialized, k.min_stok,
        COALESCE(b.jumlah_stok, 0) AS stok_bulk,
        (SELECT COUNT(*) FROM inventaris_serial s WHERE s.barang_id = k.id AND s.status = 'GUDANG') AS stok_serial
        FROM katalog_barang k
        LEFT JOIN inventaris_bulk b ON k.id = b.barang_id";

$result = $conn->query($sql);
$data = [];

if($result) {
    while($row = $result->fetch_assoc()) {
        // Jika barang wajib SN (Router), stoknya dihitung dari tabel inventaris_serial.
        // Jika bukan (Kabel), stoknya diambil dari tabel inventaris_bulk.
        $stok_aktual = ($row['is_serialized'] == 'Y') ? $row['stok_serial'] : $row['stok_bulk'];
        
        // Memformat data agar cocok dibaca oleh JavaScript kamu
        $row['stok'] = floatval($stok_aktual);
        $data[] = $row;
    }
} else {
    echo json_encode(["error" => $conn->error]);
    exit;
}

echo json_encode($data);
$conn->close();
?>