<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function only(array $allowedRoles = []) {
    $role = $_SESSION['erp_role'] ;
    if (!in_array($role, $allowedRoles)) {
        die("<div style='text-align:center;margin-top:100px;'><h3>Akses Ditolak!</h3><p>Role Anda tidak diizinkan.</p><a href='dashboard.php'>Kembali</a></div>");
    }
}