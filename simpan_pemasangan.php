<?php
header('Content-Type: application/json');
include 'koneksi.php';

// Menerima data JSON dari Fetch JS
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Data tidak diterima"]);
    exit;
}

$nama = $conn->real_escape_string($data['pelanggan']);
$alamat = $conn->real_escape_string($data['alamat']);
$tanggal = $conn->real_escape_string($data['tanggal']);
$teknisi = $conn->real_escape_string($data['teknisi']);
$barang_list = $data['items'];

// Mulai transaksi database agar jika satu gagal, semua dibatalkan
$conn->begin_transaction();

try {
    foreach($barang_list as $item) {
        $id_b = $conn->real_escape_string($item['barang_id']);
        $qty = $conn->real_escape_string($item['jumlah']);
        $ket = $conn->real_escape_string($item['keterangan']);
        
        // Simpan ke tabel pemasangan
        $sql = "INSERT INTO pemasangan (nama_pelanggan, alamat, tanggal, teknisi, barang_id, jumlah, catatan) 
                VALUES ('$nama', '$alamat', '$tanggal', '$teknisi', '$id_b', '$qty', '$ket')";
        
        if (!$conn->query($sql)) throw new Exception("Gagal simpan data pemasangan");
        
        // Kurangi stok barang
        $sql_update = "UPDATE barang SET stok = stok - $qty WHERE id = $id_b";
        if (!$conn->query($sql_update)) throw new Exception("Gagal update stok barang");
    }

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>