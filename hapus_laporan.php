<?php
session_start();
// Gembok Anti-Hacker (Hanya Superadmin yang boleh akses)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'AKSES DITOLAK! Hanya Superadmin yang boleh menghapus laporan.']);
    exit;
}

header('Content-Type: application/json');
include 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID Laporan tidak ditemukan']);
    exit;
}

$pemasangan_id = (int)$data['id'];

$conn->begin_transaction();

try {
    // 1. Ambil detail barang apa saja yang dipakai di laporan ini
    $sql_detail = "SELECT pd.*, kb.is_serialized 
                   FROM pemasangan_detail pd 
                   JOIN katalog_barang kb ON pd.barang_id = kb.id 
                   WHERE pd.pemasangan_id = $pemasangan_id";
    $res_detail = $conn->query($sql_detail);
    
    while ($row = $res_detail->fetch_assoc()) {
        $barang_id = $row['barang_id'];
        $jumlah = $row['jumlah_terpakai'];
        $sn = $row['serial_number'];
        
        // 2. KEMBALIKAN STOK KE GUDANG!
        if ($row['is_serialized'] == 'Y' && !empty($sn)) {
            // Jika router, ubah statusnya kembali menjadi GUDANG
            $sql_refund_sn = "UPDATE inventaris_serial SET status = 'GUDANG', pemasangan_id = NULL WHERE serial_number = '$sn'";
            if (!$conn->query($sql_refund_sn)) throw new Exception("Gagal mengembalikan SN");
        } else {
            // Jika kabel, tambahkan kembali meterannya ke gudang
            $sql_refund_bulk = "UPDATE inventaris_bulk SET jumlah_stok = jumlah_stok + $jumlah WHERE barang_id = $barang_id";
            if (!$conn->query($sql_refund_bulk)) throw new Exception("Gagal mengembalikan stok kabel");
        }
    }

    // 3. Hapus Laporan Utama (Detailnya otomatis terhapus karena ON DELETE CASCADE)
    $sql_delete = "DELETE FROM pemasangan WHERE id = $pemasangan_id";
    if (!$conn->query($sql_delete)) throw new Exception("Gagal menghapus laporan");

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>