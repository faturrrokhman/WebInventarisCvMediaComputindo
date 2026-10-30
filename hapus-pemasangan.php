<?php
header('Content-Type: application/json');
include 'koneksi.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['id'])) {
    $id = $conn->real_escape_string($data['id']);

    // 1. Cari dulu data yang mau dihapus untuk dapat Nama & Tanggal
    $res = $conn->query("SELECT nama_pelanggan, tanggal FROM pemasangan WHERE id = '$id'");
    $row = $res->fetch_assoc();

    if ($row) {
        $nama = $row['nama_pelanggan'];
        $tgl = $row['tanggal'];

        // 2. Hapus SEMUA baris dalam grup pemasangan tersebut
        $sql = "DELETE FROM pemasangan WHERE nama_pelanggan = '$nama' AND tanggal = '$tgl'";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
}

$conn->close();
?>