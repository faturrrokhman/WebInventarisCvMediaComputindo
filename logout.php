<?php
session_start();
session_unset();
session_destroy();
echo json_encode(["success" => true, "message" => "Logout berhasil"]);
// Atau jika ingin langsung diarahkan ke halaman login:
header("Location: login.html");
exit;
?>