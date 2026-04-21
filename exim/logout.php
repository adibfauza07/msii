<?php
// FILE: exim/logout.php
session_start();

// Hapus semua data session
session_unset();
session_destroy();

// Redirect kembali ke login page yang ada di folder exim
header("Location: login.php");
exit;