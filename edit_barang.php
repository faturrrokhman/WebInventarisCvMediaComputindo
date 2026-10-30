<?php
session_start();
// Gembok Anti-Hacker
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'AKSES DITOLAK! Anda tidak memiliki izin Superadmin.']);
    exit;
}

header('Content-Type: application/json');
include 'koneksi.php';
header('Content-Type: application/json');
include 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID Barang tidak ditemukan']);
    exit;
}

$id = (int)$data['id'];
$nama = $conn->real_escape_string($data['nama']);
$minStok = (int)$data['minStok'];

// HANYA update nama_barang dan min_stok (Satuan dilarang berubah)
$sql = "UPDATE katalog_barang SET nama_barang = '$nama', min_stok = $minStok WHERE id = $id";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $conn->error]);
}
$conn->close();
?>