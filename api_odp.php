<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function odp_json($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Endpoint ini hanya dapat digunakan setelah pengguna login ke aplikasi.
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    odp_json(['success' => false, 'message' => 'Sesi login tidak ditemukan. Silakan login kembali.'], 401);
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/google_maps_resolver.php';
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    $sql = "SELECT o.id, o.kode_odp, o.nama_odp, o.alamat, o.link_maps, o.latitude, o.longitude,
                   o.kapasitas_port, o.status, o.keterangan, o.created_at,
                   COUNT(DISTINCT p.id) AS jumlah_port_tercatat,
                   COUNT(DISTINCT CASE WHEN p.pemasangan_id IS NOT NULL THEN p.id END) AS port_terpakai
            FROM odp o
            LEFT JOIN odp_port p ON p.odp_id = o.id
            GROUP BY o.id
            ORDER BY o.kode_odp ASC";
    $result = $conn->query($sql);
    if (!$result) {
        odp_json(['success' => false, 'message' => 'Gagal mengambil data ODP. Pastikan tabel odp dan odp_port sudah dibuat.'], 500);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['kapasitas_port'] = (int)$row['kapasitas_port'];
        $row['jumlah_port_tercatat'] = (int)$row['jumlah_port_tercatat'];
        $row['port_terpakai'] = (int)$row['port_terpakai'];
        $row['port_tersedia'] = max(0, $row['kapasitas_port'] - $row['port_terpakai']);
        $data[] = $row;
    }
    odp_json(['success' => true, 'data' => $data]);
}

if ($method === 'POST' && $action === 'save') {
    $id = filter_var($_POST['id'] ?? '0', FILTER_VALIDATE_INT);
    $id = $id ? (int)$id : 0;
    $kode = trim($_POST['kode_odp'] ?? '');
    $nama = trim($_POST['nama_odp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $linkMaps = trim($_POST['link_maps'] ?? '');
    $latRaw = trim($_POST['latitude'] ?? '');
    $lngRaw = trim($_POST['longitude'] ?? '');
    $kapasitas = filter_var($_POST['kapasitas_port'] ?? '8', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? 'Aktif');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($kode === '' || $nama === '') {
        odp_json(['success' => false, 'message' => 'Kode ODP dan nama ODP wajib diisi.'], 422);
    }
    if (mb_strlen($kode) > 50 || mb_strlen($nama) > 100) {
        odp_json(['success' => false, 'message' => 'Kode ODP maksimal 50 karakter dan nama ODP maksimal 100 karakter.'], 422);
    }
    if ($kapasitas === false || $kapasitas < 1 || $kapasitas > 256) {
        odp_json(['success' => false, 'message' => 'Kapasitas port harus berupa angka antara 1 dan 256.'], 422);
    }
    if (!in_array($status, ['Aktif', 'Nonaktif', 'Maintenance'], true)) {
        odp_json(['success' => false, 'message' => 'Status ODP tidak valid.'], 422);
    }

    if (mb_strlen($linkMaps) > 1000) {
        odp_json(['success' => false, 'message' => 'Link Google Maps terlalu panjang (maksimal 1000 karakter).'], 422);
    }

    $latitude = null;
    $longitude = null;
    if (($latRaw === '') !== ($lngRaw === '')) {
        odp_json(['success' => false, 'message' => 'Latitude dan longitude harus diisi bersama-sama.'], 422);
    }
    if ($latRaw !== '' && $lngRaw !== '') {
        if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
            odp_json(['success' => false, 'message' => 'Latitude dan longitude harus berupa angka.'], 422);
        }
        $latitude = (float)$latRaw;
        $longitude = (float)$lngRaw;
        if (!gmaps_valid_coords($latitude, $longitude)) {
            odp_json(['success' => false, 'message' => 'Koordinat di luar rentang yang valid.'], 422);
        }
    }

    // Jika pengguna menempel link Maps, server mencoba membaca koordinatnya otomatis.
    if ($linkMaps !== '') {
        $resolved = gmaps_resolve_link($linkMaps);
        if (!$resolved['success']) {
            odp_json(['success' => false, 'message' => $resolved['message']], 422);
        }
        $latitude = (float)$resolved['coordinates']['latitude'];
        $longitude = (float)$resolved['coordinates']['longitude'];
    } elseif ($id > 0) {
        // Saat mengedit data lain, jangan hilangkan koordinat/link lama hanya karena kolom link kosong.
        $existing = $conn->prepare('SELECT link_maps, latitude, longitude FROM odp WHERE id = ?');
        if (!$existing) odp_json(['success' => false, 'message' => 'Tidak dapat membaca lokasi ODP lama.'], 500);
        $existing->bind_param('i', $id);
        $existing->execute();
        $old = $existing->get_result()->fetch_assoc();
        $existing->close();
        if ($old) {
            if ($latRaw === '' && $lngRaw === '') {
                $latitude = $old['latitude'] !== null ? (float)$old['latitude'] : null;
                $longitude = $old['longitude'] !== null ? (float)$old['longitude'] : null;
            }
            if ($linkMaps === '') $linkMaps = (string)($old['link_maps'] ?? '');
        }
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE odp SET kode_odp=?, nama_odp=?, alamat=?, link_maps=?, latitude=?, longitude=?, kapasitas_port=?, status=?, keterangan=? WHERE id=?");
        if (!$stmt) odp_json(['success' => false, 'message' => 'Tidak dapat menyiapkan pembaruan data.'], 500);
        $stmt->bind_param('ssssssissi', $kode, $nama, $alamat, $linkMaps, $latitude, $longitude, $kapasitas, $status, $keterangan, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO odp (kode_odp, nama_odp, alamat, link_maps, latitude, longitude, kapasitas_port, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) odp_json(['success' => false, 'message' => 'Tidak dapat menyiapkan penyimpanan data.'], 500);
        $stmt->bind_param('ssssssiss', $kode, $nama, $alamat, $linkMaps, $latitude, $longitude, $kapasitas, $status, $keterangan);
    }

    if (!$stmt->execute()) {
        if ((int)$stmt->errno === 1062) {
            odp_json(['success' => false, 'message' => 'Kode ODP tersebut sudah digunakan. Gunakan kode yang berbeda.'], 409);
        }
        odp_json(['success' => false, 'message' => 'Data gagal disimpan. Periksa kembali struktur tabel database.'], 500);
    }
    $stmt->close();
    odp_json(['success' => true, 'message' => $id > 0 ? 'Data ODP berhasil diperbarui.' : 'Data ODP berhasil ditambahkan.']);
}

if ($method === 'POST' && $action === 'delete') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) odp_json(['success' => false, 'message' => 'ID ODP tidak valid.'], 422);

    $check = $conn->prepare('SELECT COUNT(*) FROM odp_port WHERE odp_id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    $check->bind_result($portCount);
    $check->fetch();
    $check->close();

    if ((int)$portCount > 0) {
        odp_json(['success' => false, 'message' => 'ODP belum bisa dihapus karena sudah memiliki catatan port. Hapus atau pindahkan data port terlebih dahulu.'], 409);
    }

    $stmt = $conn->prepare('DELETE FROM odp WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        odp_json(['success' => false, 'message' => 'Data ODP gagal dihapus.'], 500);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected < 1) odp_json(['success' => false, 'message' => 'Data ODP tidak ditemukan.'], 404);
    odp_json(['success' => true, 'message' => 'Data ODP berhasil dihapus.']);
}

odp_json(['success' => false, 'message' => 'Permintaan tidak dikenali.'], 400);
