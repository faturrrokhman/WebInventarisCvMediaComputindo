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

// Menangkap data JSON yang dikirim dari Javascript
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID Barang tidak ditemukan.']);
    exit;
}

$id = (int)$data['id'];

// 1. CEK RIWAYAT PEMASANGAN: Apakah barang ini sudah pernah dipasang di pelanggan?
$cek_pemasangan = "SELECT COUNT(*) as total FROM pemasangan_detail WHERE barang_id = $id";
$result_cek = $conn->query($cek_pemasangan);
$row_cek = $result_cek->fetch_assoc();

if ($row_cek['total'] > 0) {
    // JIKA SUDAH DIPAKAI -> TOLAK PENGHAPUSAN!
    echo json_encode([
        'success' => false, 
        'message' => 'DITOLAK! Barang ini tidak bisa dihapus karena sudah ada di Laporan Pemasangan pelanggan. Jika stok habis, biarkan saja sisa 0.'
    ]);
    exit; // Berhenti di sini, jangan lanjut ke proses hapus
}

// 2. JIKA AMAN (Belum pernah dipakai) -> EKSEKUSI HAPUS
// Berkat "ON DELETE CASCADE", stok sisa di gudang akan otomatis ikut terhapus
$sql = "DELETE FROM katalog_barang WHERE id = $id";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Barang berhasil dihapus.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus ke database: ' . $conn->error]);
}

$conn->close();
?>