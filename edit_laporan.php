<?php
header('Content-Type: application/json');
include 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = (int)$data['id'];
$pelanggan = $conn->real_escape_string($data['pelanggan']);
$alamat = $conn->real_escape_string($data['alamat']);
$tanggal = $conn->real_escape_string($data['tanggal']);
// PERUBAHAN: Tangkap input langganan
$langganan = $conn->real_escape_string($data['langganan']);

// PERUBAHAN: Update kolom langganan
$sql = "UPDATE pemasangan 
        SET pelanggan_nama='$pelanggan', alamat='$alamat', tanggal_pasang='$tanggal', langganan='$langganan' 
        WHERE id=$id";

if($conn->query($sql)){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Gagal menyimpan data: ' . $conn->error]);
}

$conn->close();
?>