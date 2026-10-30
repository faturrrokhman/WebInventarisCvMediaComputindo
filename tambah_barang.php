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

if (!isset($data['nama']) || empty($data['nama'])) {
    echo json_encode(['success' => false, 'message' => 'Nama barang wajib diisi!']);
    exit;
}

$nama = $conn->real_escape_string($data['nama']);
$satuan = $conn->real_escape_string($data['satuan']);
$minStok = (int)$data['minStok'];
$sn_list = isset($data['sn_list']) ? $data['sn_list'] : []; 
$stok_awal = (float)($data['stok'] ?? 0);

// PERUBAHAN DI SINI: Menerima is_serialized secara langsung dari Javascript
$is_serialized = isset($data['is_serialized']) ? $conn->real_escape_string($data['is_serialized']) : 'N';
$kategori = ($is_serialized == 'Y') ? 'Perangkat' : 'Material';

$sql_katalog = "INSERT INTO katalog_barang (nama_barang, kategori, satuan_hitung, is_serialized, min_stok)
                VALUES ('$nama', '$kategori', '$satuan', '$is_serialized', $minStok)";

if ($conn->query($sql_katalog)) {
    $barang_id = $conn->insert_id;

    if ($is_serialized == 'N') {
        // MATERIAL BEBAS (Kabel, Klem, Baut, dll)
        $conn->query("INSERT INTO inventaris_bulk (barang_id, jumlah_stok) VALUES ($barang_id, $stok_awal)");
    } else {
        // PERANGKAT BER-SN (Router, STB, dll)
        if (!empty($sn_list) && is_array($sn_list)) {
            foreach ($sn_list as $sn) {
                $sn_clean = $conn->real_escape_string($sn);
                $conn->query("INSERT INTO inventaris_serial (barang_id, serial_number, status, tanggal_masuk)
                              VALUES ($barang_id, '$sn_clean', 'GUDANG', CURDATE())");
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Barang berhasil ditambahkan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $conn->error]);
}
$conn->close();
?>