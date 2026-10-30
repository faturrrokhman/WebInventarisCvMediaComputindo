<?php
session_start(); 
header('Content-Type: application/json');
include 'koneksi.php';

$user = $conn->real_escape_string($_POST['username']);
$pass = $conn->real_escape_string($_POST['password']);

$sql = "SELECT * FROM users WHERE username='$user' AND password='$pass'";
$query = $conn->query($sql);

if ($query->num_rows > 0) {
    $data_user = $query->fetch_assoc();
    
    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id'] = $data_user['id'];
    $_SESSION['username'] = $data_user['username'];
    
    // ANTI-ERROR: Jika kolom nama_lengkap tidak ada di DB, pakai username saja
    $_SESSION['nama_lengkap'] = isset($data_user['nama_lengkap']) ? $data_user['nama_lengkap'] : $data_user['username'];
    
    // ANTI-ERROR: Pastikan role ada, jika tidak anggap admin biasa
    $_SESSION['role'] = isset($data_user['role']) ? $data_user['role'] : 'admin_biasa'; 

    echo json_encode([
        "success" => true,
        "role" => $_SESSION['role'],
        "nama" => $_SESSION['nama_lengkap']
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Username atau Password salah!"]);
}

$conn->close();
?>