<?php
session_start();

// Hapus semua session
session_unset();
session_destroy();

// Kembali ke login page
header("Location: ../inventory/login.php");
exit;
