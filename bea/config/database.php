<?php
/**
 * Bridge koneksi dashboard ke konfigurasi bersama Plant 1.
 * Kompatibel dengan PHP 5.4 + Microsoft SQL Server 2008.
 *
 * Struktur yang diasumsikan:
 *   /msii/config/db_plant1.php  (nama pada screenshot)
 *   /msii/it-inventory-bc/config/database.php
 *
 * Nama db_plan1.php juga didukung sebagai fallback.
 */

$dbConnected = false;
$dbError = '';
$conn = false;

$msiiRoot = dirname(dirname(__DIR__));
$configCandidates = array(
    $msiiRoot . '/config/db_plant1.php',
    $msiiRoot . '/config/db_plan1.php'
);

$sharedConfig = '';
foreach ($configCandidates as $candidate) {
    if (file_exists($candidate)) {
        $sharedConfig = $candidate;
        break;
    }
}

if ($sharedConfig === '') {
    $dbError = 'File db_plant1.php atau db_plan1.php tidak ditemukan di ' . $msiiRoot . '/config/';
    return;
}

require_once $sharedConfig;

if (isset($conn) && $conn !== false) {
    $dbConnected = true;
} else {
    $dbError = 'Koneksi SQL Server Plant 1 tidak tersedia.';
}
