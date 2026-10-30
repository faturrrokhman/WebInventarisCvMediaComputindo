<?php
header('Content-Type: application/json');
include 'koneksi.php';

$tglMulai = isset($_GET['mulai']) ? $conn->real_escape_string($_GET['mulai']) : '';
$tglSelesai = isset($_GET['selesai']) ? $conn->real_escape_string($_GET['selesai']) : '';

$whereClause = "";
if (!empty($tglMulai) && !empty($tglSelesai)) {
    $whereClause = " WHERE p.tanggal_pasang BETWEEN '$tglMulai' AND '$tglSelesai' ";
}

$sql = "SELECT p.*, 
        (SELECT COALESCE(SUM(pd.jumlah_terpakai), 0) FROM pemasangan_detail pd JOIN katalog_barang kb ON pd.barang_id = kb.id WHERE pd.pemasangan_id = p.id AND LOWER(kb.satuan_hitung) != 'meter') as total_unit,
        (SELECT COALESCE(SUM(pd.jumlah_terpakai), 0) FROM pemasangan_detail pd JOIN katalog_barang kb ON pd.barang_id = kb.id WHERE pd.pemasangan_id = p.id AND LOWER(kb.satuan_hitung) = 'meter') as total_meter
        FROM pemasangan p
        $whereClause
        ORDER BY p.tanggal_pasang DESC, p.id DESC";
        
$result = $conn->query($sql);
$laporan = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pemasangan_id = $row['id'];
        
        $sql_detail = "SELECT pd.id, pd.barang_id, pd.jumlah_terpakai, pd.serial_number, pd.keterangan, 
                              kb.nama_barang, kb.satuan_hitung, kb.is_serialized 
                       FROM pemasangan_detail pd 
                       JOIN katalog_barang kb ON pd.barang_id = kb.id 
                       WHERE pd.pemasangan_id = $pemasangan_id";
        $res_detail = $conn->query($sql_detail);
        
        $detail = [];
        while($d = $res_detail->fetch_assoc()){
            $detail[] = $d;
        }
        $row['detail'] = $detail; 
        $laporan[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $laporan]);
$conn->close();
?>