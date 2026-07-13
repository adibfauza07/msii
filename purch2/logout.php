<?php
session_start();

// Hapus semua session
session_unset();
session_destroy();

// Kembali ke login page
header("Location: ../ordering_p2/login.php");
exit;
