<?php
header('Content-Type: application/json');
include 'koneksi.php';

$barang_id = isset($_GET['barang_id']) ? (int)$_GET['barang_id'] : 0;

if ($barang_id <= 0) {
    echo json_encode([]);
    exit;
}

// Ambil HANYA serial number yang statusnya masih 'GUDANG' (Belum Terpasang)
$sql = "SELECT serial_number FROM inventaris_serial WHERE barang_id = $barang_id AND status = 'GUDANG'";
$result = $conn->query($sql);

$data = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>