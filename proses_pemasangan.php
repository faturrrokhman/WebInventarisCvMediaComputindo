<?php
header('Content-Type: application/json');
include 'koneksi.php';

// Ambil data POST dari form HTML
$pelanggan = $conn->real_escape_string($_POST['pelanggan'] ?? '');
$alamat = $conn->real_escape_string($_POST['alamat'] ?? '');
$link_maps = $conn->real_escape_string($_POST['link_maps'] ?? '');
$tanggal = $conn->real_escape_string($_POST['tanggal'] ?? '');
// PERUBAHAN: Ambil data 'langganan' bukan 'teknisi'
$langganan = $conn->real_escape_string($_POST['langganan'] ?? ''); 

// Items dikirim dalam bentuk string JSON dari Javascript, jadi harus di-decode
$items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

if (empty($pelanggan) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap!']);
    exit;
}

// Hubungan ODP/port opsional untuk pemasangan baru. Keduanya harus dipilih bersama.
$odp_raw = trim($_POST['odp_id'] ?? '');
$port_raw = trim($_POST['port_id'] ?? '');
$odp_id = null;
$port_id = null;
if (($odp_raw === '') !== ($port_raw === '')) {
    echo json_encode(['success' => false, 'message' => 'Pilih ODP dan port sekaligus, atau kosongkan keduanya.']);
    exit;
}
if ($odp_raw !== '' && $port_raw !== '') {
    $odp_id = filter_var($odp_raw, FILTER_VALIDATE_INT);
    $port_id = filter_var($port_raw, FILTER_VALIDATE_INT);
    if (!$odp_id || $odp_id < 1 || !$port_id || $port_id < 1) {
        echo json_encode(['success' => false, 'message' => 'Pilihan ODP atau port tidak valid.']);
        exit;
    }
    $odp_id = (int)$odp_id;
    $port_id = (int)$port_id;
}

// Validasi koordinat rumah. Keduanya opsional, tetapi harus diisi berpasangan.
$latitudeRaw = trim($_POST['latitude'] ?? '');
$longitudeRaw = trim($_POST['longitude'] ?? '');
$latitudeSql = 'NULL';
$longitudeSql = 'NULL';
if (($latitudeRaw === '') !== ($longitudeRaw === '')) {
    echo json_encode(['success' => false, 'message' => 'Latitude dan longitude harus diisi bersamaan.']);
    exit;
}
if ($latitudeRaw !== '' && $longitudeRaw !== '') {
    if (!is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
        echo json_encode(['success' => false, 'message' => 'Koordinat rumah harus berupa angka.']);
        exit;
    }
    $latitudeValue = (float)$latitudeRaw;
    $longitudeValue = (float)$longitudeRaw;
    if ($latitudeValue < -90 || $latitudeValue > 90 || $longitudeValue < -180 || $longitudeValue > 180) {
        echo json_encode(['success' => false, 'message' => 'Koordinat rumah berada di luar rentang yang valid.']);
        exit;
    }
    $latitudeSql = number_format($latitudeValue, 7, '.', '');
    $longitudeSql = number_format($longitudeValue, 7, '.', '');
}

// Proses Upload Foto (jika ada)
$foto_nama = null;
if(isset($_FILES['foto_lokasi']) && $_FILES['foto_lokasi']['error'] == 0){
    $ext = pathinfo($_FILES['foto_lokasi']['name'], PATHINFO_EXTENSION);
    $foto_nama = time() . '_' . uniqid() . '.' . $ext;
    
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
    move_uploaded_file($_FILES['foto_lokasi']['tmp_name'], 'uploads/' . $foto_nama);
}

// Mulai Database Transaction 
$conn->begin_transaction();

try {
    // 1. Insert ke tabel pemasangan utama
    // PERUBAHAN: Menggunakan kolom langganan
    $sql_pemasangan = "INSERT INTO pemasangan (pelanggan_nama, alamat, link_maps, latitude, longitude, tanggal_pasang, langganan, foto_lokasi)
                       VALUES ('$pelanggan', '$alamat', '$link_maps', $latitudeSql, $longitudeSql, '$tanggal', '$langganan', " . ($foto_nama ? "'$foto_nama'" : "NULL") . ")";
    
    if(!$conn->query($sql_pemasangan)) {
        throw new Exception("Gagal simpan data pelanggan: " . $conn->error);
    }
    
    $pemasangan_id = $conn->insert_id;

    // 1b. Jika ODP/port dipilih, pastikan port milik ODP tersebut, aktif, dan belum terisi.
    if ($odp_id !== null && $port_id !== null) {
        $checkPort = $conn->prepare("SELECT op.status, op.pemasangan_id, o.status AS odp_status
                                     FROM odp_port op
                                     INNER JOIN odp o ON o.id = op.odp_id
                                     WHERE op.id = ? AND op.odp_id = ?
                                     FOR UPDATE");
        if (!$checkPort) {
            throw new Exception("Tidak dapat memeriksa port ODP. Pastikan modul ODP sudah terpasang.");
        }
        $checkPort->bind_param('ii', $port_id, $odp_id);
        if (!$checkPort->execute()) {
            $checkPort->close();
            throw new Exception("Gagal memeriksa ketersediaan port ODP.");
        }
        $checkPort->bind_result($portStatus, $existingPemasanganId, $odpStatus);
        $portFound = $checkPort->fetch();
        $checkPort->close();
        if (!$portFound) {
            throw new Exception("Port tidak ditemukan pada ODP yang dipilih. Muat ulang formulir lalu pilih kembali.");
        }
        if ($odpStatus !== 'Aktif') {
            throw new Exception("ODP tidak berstatus Aktif sehingga tidak dapat digunakan untuk pemasangan baru.");
        }
        if ($portStatus !== 'Aktif') {
            throw new Exception("Port yang dipilih tidak berstatus Aktif.");
        }
        if ($existingPemasanganId !== null) {
            throw new Exception("Port tersebut sudah digunakan pelanggan lain. Silakan pilih port kosong.");
        }

        $assignPort = $conn->prepare("UPDATE odp_port SET pemasangan_id = ?
                                      WHERE id = ? AND odp_id = ?
                                        AND pemasangan_id IS NULL AND status = 'Aktif'");
        if (!$assignPort) {
            throw new Exception("Tidak dapat menyiapkan penyimpanan hubungan ODP/port.");
        }
        $assignPort->bind_param('iii', $pemasangan_id, $port_id, $odp_id);
        if (!$assignPort->execute() || $assignPort->affected_rows !== 1) {
            $assignPort->close();
            throw new Exception("Port baru saja digunakan atau gagal diperbarui. Silakan pilih port lain.");
        }
        $assignPort->close();
    }

    // 2. Looping (Proses satu-satu barang yang dipasang)
    foreach($items as $item) {
        $barang_id = (int)$item['barang_id'];
        $jenis = $item['jenis']; 
        $jumlah = (float)$item['jumlah'];
        $sn = $conn->real_escape_string($item['serial_number']);
        
        $keterangan = $conn->real_escape_string($item['keterangan'] ?? '');
        
        $sql_detail = "INSERT INTO pemasangan_detail (pemasangan_id, barang_id, serial_number, jumlah_terpakai, keterangan) 
                       VALUES ($pemasangan_id, $barang_id, " . ($sn ? "'$sn'" : "NULL") . ", $jumlah, '$keterangan')";
                       
        if(!$conn->query($sql_detail)) {
            throw new Exception("Gagal simpan detail barang: " . $conn->error);
        }

        // 3. KURANGI STOK GUDANG
        if ($jenis == 'Unit' && !empty($sn)) {
            $sql_update_sn = "UPDATE inventaris_serial 
                              SET status = 'TERPASANG', pemasangan_id = $pemasangan_id 
                              WHERE serial_number = '$sn' AND barang_id = $barang_id";
            if(!$conn->query($sql_update_sn)) {
                throw new Exception("Gagal update status SN: " . $conn->error);
            }
        } else {
            $sql_update_bulk = "UPDATE inventaris_bulk 
                                SET jumlah_stok = jumlah_stok - $jumlah 
                                WHERE barang_id = $barang_id";
            if(!$conn->query($sql_update_bulk)) {
                throw new Exception("Gagal mengurangi stok kabel: " . $conn->error);
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>