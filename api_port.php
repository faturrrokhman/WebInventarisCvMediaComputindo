<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function port_json($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    port_json(['success' => false, 'message' => 'Sesi login tidak ditemukan. Silakan login kembali.'], 401);
}

require_once __DIR__ . '/koneksi.php';
$conn->set_charset('utf8mb4');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'odps') {
    $result = $conn->query("SELECT id, kode_odp, nama_odp, kapasitas_port, status FROM odp ORDER BY kode_odp ASC");
    if (!$result) port_json(['success' => false, 'message' => 'Gagal membaca data ODP. Periksa tabel odp.'], 500);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['kapasitas_port'] = (int)$row['kapasitas_port'];
        $data[] = $row;
    }
    port_json(['success' => true, 'data' => $data]);
}

// Buat catatan port 1 sampai kapasitas ODP. INSERT IGNORE menjaga port yang sudah ada.
if ($method === 'POST' && $action === 'ensure_ports') {
    $odpId = filter_var($_POST['odp_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$odpId || $odpId < 1) port_json(['success' => false, 'message' => 'ODP tidak valid.'], 422);

    $stmt = $conn->prepare('SELECT kapasitas_port FROM odp WHERE id = ?');
    $stmt->bind_param('i', $odpId);
    $stmt->execute();
    $stmt->bind_result($capacity);
    if (!$stmt->fetch()) {
        $stmt->close();
        port_json(['success' => false, 'message' => 'Data ODP tidak ditemukan.'], 404);
    }
    $stmt->close();

    $capacity = (int)$capacity;
    $insert = $conn->prepare("INSERT IGNORE INTO odp_port (odp_id, nomor_port, status) VALUES (?, ?, 'Aktif')");
    if (!$insert) port_json(['success' => false, 'message' => 'Tidak dapat menyiapkan data port.'], 500);
    for ($number = 1; $number <= $capacity; $number++) {
        $insert->bind_param('ii', $odpId, $number);
        if (!$insert->execute()) {
            $insert->close();
            port_json(['success' => false, 'message' => 'Gagal menyiapkan daftar port ODP.'], 500);
        }
    }
    $insert->close();
    port_json(['success' => true, 'message' => 'Daftar port berhasil disiapkan.']);
}

if ($method === 'GET' && $action === 'ports') {
    $odpId = filter_var($_GET['odp_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$odpId || $odpId < 1) port_json(['success' => false, 'message' => 'ODP tidak valid.'], 422);

    $stmt = $conn->prepare("SELECT op.id, op.odp_id, op.nomor_port, op.pemasangan_id, op.status, op.catatan,
                                   p.pelanggan_nama, p.alamat, p.tanggal_pasang, p.langganan,
                                   COALESCE(sn.serial_numbers, '') AS serial_numbers
                            FROM odp_port op
                            LEFT JOIN pemasangan p ON p.id = op.pemasangan_id
                            LEFT JOIN (
                                SELECT pemasangan_id, GROUP_CONCAT(serial_number ORDER BY serial_number SEPARATOR ', ') AS serial_numbers
                                FROM inventaris_serial
                                WHERE pemasangan_id IS NOT NULL
                                GROUP BY pemasangan_id
                            ) sn ON sn.pemasangan_id = op.pemasangan_id
                            WHERE op.odp_id = ?
                            ORDER BY op.nomor_port ASC");
    if (!$stmt) port_json(['success' => false, 'message' => 'Gagal menyiapkan daftar port.'], 500);
    $stmt->bind_param('i', $odpId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['odp_id'] = (int)$row['odp_id'];
        $row['nomor_port'] = (int)$row['nomor_port'];
        $row['pemasangan_id'] = $row['pemasangan_id'] === null ? null : (int)$row['pemasangan_id'];
        $data[] = $row;
    }
    $stmt->close();
    port_json(['success' => true, 'data' => $data]);
}

if ($method === 'GET' && $action === 'pemasangan') {
    $sql = "SELECT p.id, p.pelanggan_nama, p.alamat, p.tanggal_pasang, p.langganan,
                   COALESCE(sn.serial_numbers, '') AS serial_numbers,
                   op.id AS assigned_port_id, op.nomor_port AS assigned_nomor_port,
                   o.kode_odp AS assigned_kode_odp
            FROM pemasangan p
            LEFT JOIN odp_port op ON op.pemasangan_id = p.id
            LEFT JOIN odp o ON o.id = op.odp_id
            LEFT JOIN (
                SELECT pemasangan_id, GROUP_CONCAT(serial_number ORDER BY serial_number SEPARATOR ', ') AS serial_numbers
                FROM inventaris_serial
                WHERE pemasangan_id IS NOT NULL
                GROUP BY pemasangan_id
            ) sn ON sn.pemasangan_id = p.id
            ORDER BY p.tanggal_pasang DESC, p.pelanggan_nama ASC";
    $result = $conn->query($sql);
    if (!$result) port_json(['success' => false, 'message' => 'Gagal membaca data pemasangan pelanggan. Periksa struktur database.'], 500);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['assigned_port_id'] = $row['assigned_port_id'] === null ? null : (int)$row['assigned_port_id'];
        $row['assigned_nomor_port'] = $row['assigned_nomor_port'] === null ? null : (int)$row['assigned_nomor_port'];
        $data[] = $row;
    }
    port_json(['success' => true, 'data' => $data]);
}

if ($method === 'POST' && $action === 'save_port') {
    $portId = filter_var($_POST['port_id'] ?? '', FILTER_VALIDATE_INT);
    $installationRaw = $_POST['pemasangan_id'] ?? '';
    $installationId = ($installationRaw === '' || $installationRaw === '0') ? null : filter_var($installationRaw, FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? 'Aktif');
    $note = trim($_POST['catatan'] ?? '');

    if (!$portId || $portId < 1) port_json(['success' => false, 'message' => 'ID port tidak valid.'], 422);
    if ($installationId === false || ($installationId !== null && $installationId < 1)) {
        port_json(['success' => false, 'message' => 'Pilihan pelanggan tidak valid.'], 422);
    }
    if (!in_array($status, ['Aktif', 'Rusak', 'Nonaktif'], true)) {
        port_json(['success' => false, 'message' => 'Status port tidak valid.'], 422);
    }
    if (mb_strlen($note) > 255) port_json(['success' => false, 'message' => 'Catatan maksimal 255 karakter.'], 422);

    $check = $conn->prepare('SELECT pemasangan_id FROM odp_port WHERE id = ?');
    $check->bind_param('i', $portId);
    $check->execute();
    $check->bind_result($currentInstallation);
    if (!$check->fetch()) {
        $check->close();
        port_json(['success' => false, 'message' => 'Port tidak ditemukan.'], 404);
    }
    $currentInstallation = $currentInstallation === null ? null : (int)$currentInstallation;
    $check->close();

    if ($installationId !== null) {
        $customerCheck = $conn->prepare('SELECT id FROM pemasangan WHERE id = ?');
        $customerCheck->bind_param('i', $installationId);
        $customerCheck->execute();
        $customerCheck->store_result();
        $exists = $customerCheck->num_rows > 0;
        $customerCheck->close();
        if (!$exists) port_json(['success' => false, 'message' => 'Data pemasangan pelanggan tidak ditemukan.'], 404);

        if ($status !== 'Aktif' && $installationId !== $currentInstallation) {
            port_json(['success' => false, 'message' => 'Pilih status port Aktif sebelum menghubungkan pelanggan baru.'], 422);
        }

        $duplicate = $conn->prepare('SELECT id FROM odp_port WHERE pemasangan_id = ? AND id <> ? LIMIT 1');
        $duplicate->bind_param('ii', $installationId, $portId);
        $duplicate->execute();
        $duplicate->store_result();
        $alreadyAssigned = $duplicate->num_rows > 0;
        $duplicate->close();
        if ($alreadyAssigned) {
            port_json(['success' => false, 'message' => 'Pelanggan tersebut sudah terhubung ke port ODP lain. Lepaskan hubungan yang lama terlebih dahulu.'], 409);
        }
    }

    $stmt = $conn->prepare('UPDATE odp_port SET pemasangan_id = ?, status = ?, catatan = ? WHERE id = ?');
    if (!$stmt) port_json(['success' => false, 'message' => 'Tidak dapat menyiapkan penyimpanan port.'], 500);
    $stmt->bind_param('issi', $installationId, $status, $note, $portId);
    if (!$stmt->execute()) {
        $stmt->close();
        port_json(['success' => false, 'message' => 'Perubahan port gagal disimpan. Periksa kembali database.'], 500);
    }
    $stmt->close();
    port_json(['success' => true, 'message' => 'Port berhasil diperbarui.']);
}

port_json(['success' => false, 'message' => 'Permintaan tidak dikenali.'], 400);
