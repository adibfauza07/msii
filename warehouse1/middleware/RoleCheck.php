<?php
// /msii/middleware/RoleCheck.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function only(array $allowedRoles = [])
{
    if (!isset($_SESSION['erp_role'])) {
        die("<h3 style='color:red;'>ACCESS DENIED</h3><p>Role user tidak ditemukan.</p>");
    }

    $role = $_SESSION['erp_role'];

    if (!in_array($role, $allowedRoles)) {
        die("<h3 style='color:red;'>ACCESS DENIED</h3><p>Anda tidak memiliki izin mengakses halaman ini.</p>");
    }
}
