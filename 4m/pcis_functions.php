<?php

function generateControlNumberFormat() {
    $romanMonth = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
    $m = date('n');
    $y = date('Y');
    return "4M-IMC-P2-" . $romanMonth[$m] . "-" . $y . "-";
}

/**
 * Menghasilkan Nomor Kontrol Baru (Implementasi Qry_No_Baru)
 */
function getNewControlNumber() {
    global $conn; // Mengambil koneksi dari database.php

    // 1. Ambil format awalan (Contoh: 4M-IMC-P2-III-2026-)
    // Fungsi generateControlNumberFormat() harus sudah ada sebelumnya
    $prefix = generateControlNumberFormat(); 
    $searchPattern = $prefix . '%'; // Pola untuk LIKE

    // 2. Query sesuai SQL yang kamu berikan (Qry_No_Baru)
    // Kita gunakan TOP 1 dan ORDER BY DESC untuk ambil yang terbesar/terakhir
    $sql = "SELECT TOP 1 RIGHT(RTRIM(CONTROL_NO), 4) as Last_No
            FROM PROSES_CHANGE
            WHERE CONTROL_NO LIKE ?
            ORDER BY RIGHT(RTRIM(CONTROL_NO), 4) DESC";

    $params = array($searchPattern);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    // 3. Logika Penentuan Nomor Urut
    if ($row && $row['Last_No'] !== null) {
        // Jika ditemukan, angka terakhir ditambah 1
        $nextNumber = (int)$row['Last_No'] + 1;
    } else {
        // Jika belum ada nomor untuk bulan/tahun ini, mulai dari 1
        $nextNumber = 1;
    }

    // 4. Gabungkan prefix dengan nomor urut yang diformat 4 digit (0001)
    return $prefix . str_pad($nextNumber, 4, "0", STR_PAD_LEFT);
}
?>