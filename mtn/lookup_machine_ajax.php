<?php
// =============================================================
// FILE: mtn/lookup_machine_ajax.php
// REVISI: 
// 1. Menampilkan SEMUA mesin (termasuk yang tidak aktif)
// 2. Sorting diperbaiki (MAC dulu, baru PLANT)
// =============================================================

require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
// Sesuaikan role jika perlu. Jika user maintenance (p2) mau lihat, pastikan masuk list.
only(['p2', 'admin']); 
require "../config/database.php";

header("Content-Type: application/json");

$q = isset($_GET['q']) ? trim($_GET['q']) : "";

// ==========================
// 1. Jika tidak ada keyword
// ==========================
if ($q === "") {
    $sql = "
        SELECT TOP 20 MAC_ID, MAC, PLANT, TONAGE, MAC_ACTIVE
        FROM MAC_MTN
        -- WHERE MAC_ACTIVE = 1  <-- SAYA KOMENTAR DULU AGAR SEMUA MUNCUL
        ORDER BY MAC ASC, PLANT ASC
    ";

    $stmt = sqlsrv_query($conn, $sql);
}

// ==========================
// 2. Jika ada pencarian
// ==========================
else {
    // Cari berdasarkan MAC
    $sql = "
        SELECT TOP 20 
            MAC_ID,
            MAC,
            PLANT,
            TONAGE,
            MAC_ACTIVE
        FROM MAC_MTN
        WHERE 
            -- MAC_ACTIVE = 1 AND  <-- SAYA KOMENTAR DULU
            MAC LIKE ? 
        ORDER BY MAC ASC, PLANT ASC
    ";

    // Parameter: "Berawalan" ($q + %)
    $paramMac = $q . '%'; 
    $params   = [$paramMac];

    $stmt = sqlsrv_query($conn, $sql, $params);
}

// ==========================
// 3. Error Handling
// ==========================
if ($stmt === false) {
    echo json_encode([
        "error" => true,
        "message" => sqlsrv_errors()
    ]);
    exit;
}

// ==========================
// 4. Output Data
// ==========================
$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    // Tambahan info jika mesin non-aktif (opsional, biar tahu visualnya)
    $macLabel = trim($row["MAC"]);
    if (isset($row['MAC_ACTIVE']) && $row['MAC_ACTIVE'] == 0) {
        $macLabel .= " (OFF)";
    }

    $data[] = [
        "MAC_ID" => $row["MAC_ID"],
        "MAC"    => $macLabel, // Nanti di tabel muncul "37 (OFF)" kalau tidak aktif
        "PLANT"  => $row["PLANT"],
        "TONAGE" => $row["TONAGE"]
    ];
}

echo json_encode($data);
exit;
?>