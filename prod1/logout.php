<?php
session_start();

// Hapus semua session
session_unset();
session_destroy();

// Kembali ke login page
header("Location: ../prod1/login.php");
exit;
