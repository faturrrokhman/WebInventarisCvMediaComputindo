<?php
header('Content-Type: application/json');
include 'koneksi.php';

$response = [
    'total_barang' => 0,
    'total_pemasangan' => 0,
    'stok_menipis_count' => 0,
    'stok_menipis_list' => [],
    'pemasangan_terbaru' => [],
    'jaringan' => [
        'ready' => false,
        'total_odp' => null,
        'odp_dipetakan' => null,
        'total_pelanggan' => null,
        'pelanggan_dipetakan' => null,
        'port_kapasitas' => null,
        'port_terpakai' => null,
        'port_kosong' => null,
        'message' => 'Ringkasan jaringan belum tersedia.'
    ]
];

// 1. Hitung Total Jenis Barang
$res_barang = $conn->query("SELECT COUNT(*) as total FROM katalog_barang");
if($res_barang) $response['total_barang'] = $res_barang->fetch_assoc()['total'];

// 2. Hitung Total Pemasangan
$res_pem = $conn->query("SELECT COUNT(*) as total FROM pemasangan");
if($res_pem) $response['total_pemasangan'] = $res_pem->fetch_assoc()['total'];

// 2a. Ringkasan jaringan untuk dashboard. Jika tabel/kolom jaringan belum tersedia,
// dashboard inventaris tetap dapat digunakan dan kartu jaringan menampilkan status yang jelas.
function dashboard_valid_coords($lat, $lng) {
    return $lat !== null && $lng !== null && $lat !== '' && $lng !== '' &&
        is_numeric($lat) && is_numeric($lng) && (float)$lat >= -90 && (float)$lat <= 90 &&
        (float)$lng >= -180 && (float)$lng <= 180;
}
function dashboard_link_has_coords($link) {
    $text = trim((string)$link);
    if ($text === '') return false;
    $decoded = rawurldecode($text);
    $lat = '(-?\\d{1,2}(?:\\.\\d+)?)';
    $lng = '(-?\\d{1,3}(?:\\.\\d+)?)';
    $patterns = [
        '/@' . $lat . '\\s*,\\s*' . $lng . '/',
        '/!3d' . $lat . '!4d' . $lng . '/i',
        '/!2d' . $lng . '!3d' . $lat . '/i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $decoded, $match)) return true;
    }
    $parts = parse_url($text);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (['q', 'query', 'll', 'center', 'destination'] as $key) {
            if (isset($query[$key]) && preg_match('/^\\s*' . $lat . '\\s*[,;]\\s*' . $lng . '\\s*$/', (string)$query[$key])) return true;
        }
    }
    return false;
}

try {
    $odpResult = $conn->query("SELECT COUNT(*) AS total_odp,
        COALESCE(SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180 THEN 1 ELSE 0 END), 0) AS odp_dipetakan,
        COALESCE(SUM(kapasitas_port), 0) AS port_kapasitas
        FROM odp");
    $customerLocationsResult = $conn->query("SELECT latitude, longitude, link_maps FROM pemasangan");
    $portsUsedResult = $conn->query("SELECT COUNT(*) AS port_terpakai FROM odp_port WHERE pemasangan_id IS NOT NULL");

    if (!$odpResult || !$customerLocationsResult || !$portsUsedResult) {
        throw new RuntimeException('Query ringkasan jaringan tidak berhasil.');
    }

    $odpStats = $odpResult->fetch_assoc();
    $portStats = $portsUsedResult->fetch_assoc();
    $totalCustomers = 0;
    $mappedCustomers = 0;
    while ($customerLocation = $customerLocationsResult->fetch_assoc()) {
        $totalCustomers++;
        if (dashboard_valid_coords($customerLocation['latitude'], $customerLocation['longitude']) || dashboard_link_has_coords($customerLocation['link_maps'])) {
            $mappedCustomers++;
        }
    }
    $capacity = (int)$odpStats['port_kapasitas'];
    $used = (int)$portStats['port_terpakai'];
    $response['jaringan'] = [
        'ready' => true,
        'total_odp' => (int)$odpStats['total_odp'],
        'odp_dipetakan' => (int)$odpStats['odp_dipetakan'],
        'total_pelanggan' => $totalCustomers,
        'pelanggan_dipetakan' => $mappedCustomers,
        'port_kapasitas' => $capacity,
        'port_terpakai' => $used,
        'port_kosong' => max(0, $capacity - $used),
        'message' => 'Ringkasan berdasarkan data jaringan yang tersimpan. Port belum terhubung adalah sisa kapasitas hitungan; periksa status port sebelum digunakan.'
    ];
} catch (Throwable $e) {
    error_log('Ringkasan jaringan dashboard gagal: ' . $e->getMessage());
    $response['jaringan']['ready'] = false;
    $response['jaringan']['message'] = 'Data jaringan belum dapat dibaca. Pastikan tabel odp dan odp_port serta kolom latitude, longitude, dan link_maps pada pemasangan tersedia.';
}

// 3. Cek Stok Menipis
$sql_stok = "SELECT k.id, k.nama_barang, k.min_stok, k.satuan_hitung, k.is_serialized,
             COALESCE(b.jumlah_stok, 0) AS stok_bulk,
             (SELECT COUNT(*) FROM inventaris_serial s WHERE s.barang_id = k.id AND s.status = 'GUDANG') AS stok_serial
             FROM katalog_barang k
             LEFT JOIN inventaris_bulk b ON k.id = b.barang_id";
$res_stok = $conn->query($sql_stok);

if($res_stok) {
    while($row = $res_stok->fetch_assoc()) {
        $stok_aktual = ($row['is_serialized'] == 'Y') ? $row['stok_serial'] : $row['stok_bulk'];
        if($stok_aktual <= $row['min_stok']) {
            $response['stok_menipis_count']++;
            $response['stok_menipis_list'][] = [
                'nama_barang' => $row['nama_barang'],
                'sisa_stok' => floatval($stok_aktual),
                'satuan' => $row['satuan_hitung'],
                'is_serialized' => $row['is_serialized']
            ];
        }
    }
}

// 4. Ambil 5 Pemasangan Terbaru beserta Materialnya
// PERUBAHAN DI SINI: Mengambil kolom 'langganan' dari tabel
$sql_terbaru = "SELECT id, pelanggan_nama, alamat, langganan, tanggal_pasang 
                FROM pemasangan ORDER BY tanggal_pasang DESC, id DESC LIMIT 5";
$res_terbaru = $conn->query($sql_terbaru);

if($res_terbaru) {
    while($row = $res_terbaru->fetch_assoc()) {
        $pem_id = $row['id'];
        
        // Tarik detail barang dari tabel pemasangan_detail
        $sql_det = "SELECT pd.jumlah_terpakai, pd.serial_number, kb.nama_barang, kb.satuan_hitung, kb.is_serialized 
                    FROM pemasangan_detail pd 
                    JOIN katalog_barang kb ON pd.barang_id = kb.id 
                    WHERE pd.pemasangan_id = $pem_id";
        $res_det = $conn->query($sql_det);
        
        $material = [];
        while($d = $res_det->fetch_assoc()){
            if($d['is_serialized'] == 'Y') {
                $material[] = "<span class='text-blue-600 font-bold'>1 Unit</span> " . $d['nama_barang'] . " <br><span class='text-[9px] text-slate-400 font-mono'>SN: " . $d['serial_number'] . "</span>";
            } else {
                $material[] = "<span class='text-emerald-600 font-bold'>" . floatval($d['jumlah_terpakai']) . " " . $d['satuan_hitung'] . "</span> " . $d['nama_barang'];
            }
        }
        $row['material_html'] = implode("<hr class='my-1 border-slate-100'>", $material);
        $response['pemasangan_terbaru'][] = $row;
    }
}

echo json_encode($response);
$conn->close();
?>