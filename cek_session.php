<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(["logged_in" => false]);
    exit;
}

echo json_encode([
    "logged_in" => true,
    "role" => isset($_SESSION['role']) ? $_SESSION['role'] : 'admin_biasa',
    "nama" => isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Admin'
]);
?>