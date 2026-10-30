<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function lokasi_json($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    lokasi_json(['success' => false, 'message' => 'Sesi login tidak ditemukan. Silakan login kembali.'], 401);
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/google_maps_resolver.php';
$conn->set_charset('utf8mb4');

function lokasi_valid_coords($lat, $lng) {
    return $lat !== null && $lng !== null && $lat !== '' && $lng !== '' &&
        is_numeric($lat) && is_numeric($lng) && (float)$lat >= -90 && (float)$lat <= 90 &&
        (float)$lng >= -180 && (float)$lng <= 180;
}

// Mendeteksi koordinat dari link panjang secara lokal. Link pendek bisa diproses saat pengguna menekan tombol Simpan Link & Ambil Lokasi.
function lokasi_parse_maps($input) {
    $text = trim((string)$input);
    if ($text === '') return null;
    $decoded = rawurldecode($text);
    $latPattern = '(-?\\d{1,2}(?:\\.\\d+)?)';
    $lngPattern = '(-?\\d{1,3}(?:\\.\\d+)?)';
    $patterns = [
        ['regex' => '/^\\s*' . $latPattern . '\\s*[,;]\\s*' . $lngPattern . '\\s*$/', 'order' => 'latlng'],
        ['regex' => '/@' . $latPattern . '\\s*,\\s*' . $lngPattern . '/', 'order' => 'latlng'],
        ['regex' => '/!3d' . $latPattern . '!4d' . $lngPattern . '/i', 'order' => 'latlng'],
        ['regex' => '/!2d' . $lngPattern . '!3d' . $latPattern . '/i', 'order' => 'lnglat'],
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern['regex'], $decoded, $match)) {
            if ($pattern['order'] === 'lnglat') { $lat = (float)$match[2]; $lng = (float)$match[1]; }
            else { $lat = (float)$match[1]; $lng = (float)$match[2]; }
            if (lokasi_valid_coords($lat, $lng)) return ['latitude' => $lat, 'longitude' => $lng];
        }
    }
    $parts = parse_url($text);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (['q', 'query', 'll', 'center', 'destination'] as $key) {
            if (!isset($query[$key])) continue;
            if (preg_match('/^\\s*' . $latPattern . '\\s*[,;]\\s*' . $lngPattern . '\\s*$/', (string)$query[$key], $match)) {
                $lat = (float)$match[1]; $lng = (float)$match[2];
                if (lokasi_valid_coords($lat, $lng)) return ['latitude' => $lat, 'longitude' => $lng];
            }
        }
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    $sql = "SELECT p.id, p.pelanggan_nama, p.alamat, p.link_maps, p.latitude, p.longitude,
                   p.tanggal_pasang, p.langganan,
                   o.kode_odp AS assigned_kode_odp, op.nomor_port AS assigned_nomor_port
            FROM pemasangan p
            LEFT JOIN odp_port op ON op.pemasangan_id = p.id
            LEFT JOIN odp o ON o.id = op.odp_id
            ORDER BY p.pelanggan_nama ASC, p.id ASC";
    $result = $conn->query($sql);
    if (!$result) {
        lokasi_json(['success' => false, 'message' => 'Data lokasi gagal dibaca. Pastikan kolom latitude dan longitude pada tabel pemasangan sudah ditambahkan melalui file SQL.'], 500);
    }
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $storedValid = lokasi_valid_coords($row['latitude'], $row['longitude']);
        if ($storedValid) {
            $row['koordinat_sumber'] = 'database';
        } else {
            $parsed = lokasi_parse_maps($row['link_maps']);
            if ($parsed) {
                $row['latitude'] = $parsed['latitude'];
                $row['longitude'] = $parsed['longitude'];
                $row['koordinat_sumber'] = 'link_maps';
            } else {
                $row['latitude'] = null;
                $row['longitude'] = null;
                $row['koordinat_sumber'] = 'belum_ada';
            }
        }
        $row['id'] = (int)$row['id'];
        $row['assigned_nomor_port'] = $row['assigned_nomor_port'] === null ? null : (int)$row['assigned_nomor_port'];
        $row['lokasi_tersedia'] = lokasi_valid_coords($row['latitude'], $row['longitude']);
        $data[] = $row;
    }
    lokasi_json(['success' => true, 'data' => $data]);
}


if ($method === 'POST' && $action === 'save_link') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) lokasi_json(['success' => false, 'message' => 'ID pelanggan tidak valid.'], 422);
    $linkMaps = trim($_POST['link_maps'] ?? '');
    if ($linkMaps === '') lokasi_json(['success' => false, 'message' => 'Tempel link Bagikan dari Google Maps terlebih dahulu.'], 422);
    if (mb_strlen($linkMaps) > 1000) lokasi_json(['success' => false, 'message' => 'Link Google Maps terlalu panjang (maksimal 1000 karakter).'], 422);

    $resolved = gmaps_resolve_link($linkMaps);
    if (!$resolved['success']) lokasi_json(['success' => false, 'message' => $resolved['message']], 422);
    $lat = (float)$resolved['coordinates']['latitude'];
    $lng = (float)$resolved['coordinates']['longitude'];

    $stmt = $conn->prepare('UPDATE pemasangan SET link_maps = ?, latitude = ?, longitude = ? WHERE id = ?');
    if (!$stmt) lokasi_json(['success' => false, 'message' => 'Tidak dapat menyiapkan penyimpanan link dan lokasi pelanggan.'], 500);
    $stmt->bind_param('sddi', $linkMaps, $lat, $lng, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        lokasi_json(['success' => false, 'message' => 'Link dan koordinat gagal disimpan. Pastikan kolom link_maps, latitude, dan longitude tersedia pada tabel pemasangan.'], 500);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected === 0) {
        $check = $conn->prepare('SELECT id FROM pemasangan WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute(); $check->store_result();
        $exists = $check->num_rows > 0; $check->close();
        if (!$exists) lokasi_json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
    }
    lokasi_json(['success' => true, 'message' => 'Link Google Maps berhasil disimpan dan koordinat rumah diperbarui.', 'latitude' => $lat, 'longitude' => $lng]);
}

if ($method === 'POST' && $action === 'save') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) lokasi_json(['success' => false, 'message' => 'ID pelanggan tidak valid.'], 422);
    $latRaw = trim($_POST['latitude'] ?? '');
    $lngRaw = trim($_POST['longitude'] ?? '');
    if ($latRaw === '' || $lngRaw === '') lokasi_json(['success' => false, 'message' => 'Masukkan latitude dan longitude sekaligus.'], 422);
    if (!is_numeric($latRaw) || !is_numeric($lngRaw)) lokasi_json(['success' => false, 'message' => 'Koordinat harus berupa angka.'], 422);
    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if (!lokasi_valid_coords($lat, $lng)) lokasi_json(['success' => false, 'message' => 'Koordinat berada di luar rentang yang valid.'], 422);

    $stmt = $conn->prepare('UPDATE pemasangan SET latitude = ?, longitude = ? WHERE id = ?');
    if (!$stmt) lokasi_json(['success' => false, 'message' => 'Tidak dapat menyiapkan penyimpanan koordinat.'], 500);
    $stmt->bind_param('ddi', $lat, $lng, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        lokasi_json(['success' => false, 'message' => 'Koordinat gagal disimpan.'], 500);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected === 0) {
        $check = $conn->prepare('SELECT id FROM pemasangan WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute(); $check->store_result();
        $exists = $check->num_rows > 0; $check->close();
        if (!$exists) lokasi_json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
    }
    lokasi_json(['success' => true, 'message' => 'Koordinat lokasi pelanggan berhasil disimpan.']);
}

lokasi_json(['success' => false, 'message' => 'Permintaan tidak dikenali.'], 400);
?>
