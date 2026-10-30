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

if (!isset($data['barang_id'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$barang_id = (int)$data['barang_id'];
$jenis_update = $conn->real_escape_string($data['jenis_update']);
$jumlah = (float)$data['jumlah'];
$catatan = $conn->real_escape_string($data['catatan']);
$sn_list = isset($data['sn_list']) ? $data['sn_list'] : [];

$cek = $conn->query("SELECT is_serialized FROM katalog_barang WHERE id = $barang_id");
$row = $cek->fetch_assoc();
$is_serialized = $row['is_serialized'];

$conn->begin_transaction();

try {
    if ($is_serialized == 'Y') {
        // VALIDASI UNTUK ROUTER
        if (count($sn_list) != $jumlah) {
            throw new Exception("Jumlah SN tidak sesuai dengan angka yang dimasukkan!");
        }
        
        if ($jenis_update == 'masuk') {
            // RESTOCK: Masukkan SN baru ke gudang
            foreach($sn_list as $sn) {
                $sn_clean = $conn->real_escape_string($sn);
                $sql_in = "INSERT INTO inventaris_serial (barang_id, serial_number, status, tanggal_masuk) 
                           VALUES ($barang_id, '$sn_clean', 'GUDANG', CURDATE())";
                if(!$conn->query($sql_in)) throw new Exception("Gagal simpan SN: " . $conn->error);
            }
        } else {
            // KELUAR/RUSAK: Ubah status SN jadi RUSAK (agar hilang dari stok aktif)
            foreach($sn_list as $sn) {
                $sn_clean = $conn->real_escape_string($sn);
                $sql_out = "UPDATE inventaris_serial SET status = 'RUSAK' 
                            WHERE barang_id = $barang_id AND serial_number = '$sn_clean'";
                if(!$conn->query($sql_out)) throw new Exception("Gagal update SN: " . $conn->error);
            }
        }
    } else {
        // KABEL (Bulk)
        if ($jenis_update == 'masuk') {
            $sql_update = "UPDATE inventaris_bulk SET jumlah_stok = jumlah_stok + $jumlah WHERE barang_id = $barang_id";
        } else {
            $sql_update = "UPDATE inventaris_bulk SET jumlah_stok = jumlah_stok - $jumlah WHERE barang_id = $barang_id";
        }
        if (!$conn->query($sql_update)) throw new Exception("Gagal update stok bulk: " . $conn->error);
    }

    // 3. Simpan Catatan Sejarahnya ke Tabel Log (Untuk Router maupun Kabel)
    $sql_log = "INSERT INTO log_stok_manual (barang_id, jenis_update, jumlah, catatan) 
                VALUES ($barang_id, '$jenis_update', $jumlah, '$catatan')";
                
    if (!$conn->query($sql_log)) throw new Exception("Gagal simpan log: " . $conn->error);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>