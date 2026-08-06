<?php
/**
 * Fungsi akses data SQL Server untuk dashboard IT Inventory.
 * Semua fungsi dibuat kompatibel dengan PHP 5.4.
 */

function db_date_string($value)
{
    if ($value instanceof DateTime) {
        return $value->format('Y-m-d');
    }

    if ($value === null) {
        return '';
    }

    return (string) $value;
}

function db_query_value($conn, $sql, $params, $defaultValue)
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return $defaultValue;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
    sqlsrv_free_stmt($stmt);

    if (!$row || !isset($row[0])) {
        return $defaultValue;
    }

    return $row[0];
}

function db_query_rows($conn, $sql, $params)
{
    $rows = array();
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        return $rows;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);
    return $rows;
}

function inventory_table_exists($conn, $tableName)
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?";
    return (int) db_query_value($conn, $sql, array($tableName), 0) > 0;
}

function inventory_get_summary($conn, $defaults)
{
    $summary = $defaults;

    if (!inventory_table_exists($conn, 'dokumen_bc')) {
        return $summary;
    }

    $summary['pemasukan_bulan'] = (int) db_query_value(
        $conn,
        "SELECT COUNT(*) FROM dbo.dokumen_bc
         WHERE arah = 'Pemasukan'
           AND YEAR(tanggal) = YEAR(GETDATE())
           AND MONTH(tanggal) = MONTH(GETDATE())",
        array(),
        $summary['pemasukan_bulan']
    );

    $summary['pengeluaran_bulan'] = (int) db_query_value(
        $conn,
        "SELECT COUNT(*) FROM dbo.dokumen_bc
         WHERE arah = 'Pengeluaran'
           AND YEAR(tanggal) = YEAR(GETDATE())
           AND MONTH(tanggal) = MONTH(GETDATE())",
        array(),
        $summary['pengeluaran_bulan']
    );

    $summary['dokumen_pending'] = (int) db_query_value(
        $conn,
        "SELECT COUNT(*) FROM dbo.dokumen_bc WHERE status = 'Pending'",
        array(),
        $summary['dokumen_pending']
    );

    if (inventory_table_exists($conn, 'aset_it')) {
        $summary['aset_it'] = (int) db_query_value(
            $conn,
            "SELECT COUNT(*) FROM dbo.aset_it",
            array(),
            $summary['aset_it']
        );
    }

    return $summary;
}

function inventory_normalize_document_rows($rows)
{
    $result = array();

    foreach ($rows as $row) {
        $result[] = array(
            'no_dokumen' => isset($row['no_dokumen']) ? $row['no_dokumen'] : '',
            'tanggal' => isset($row['tanggal']) ? db_date_string($row['tanggal']) : '',
            'jenis' => isset($row['jenis']) ? $row['jenis'] : '',
            'arah' => isset($row['arah']) ? $row['arah'] : '',
            'supplier' => isset($row['supplier']) ? $row['supplier'] : '',
            'jumlah' => isset($row['jumlah']) ? $row['jumlah'] : 0,
            'status' => isset($row['status']) ? $row['status'] : ''
        );
    }

    return $result;
}

function inventory_get_recent_documents($conn, $limit)
{
    $limit = (int) $limit;
    if ($limit < 1 || $limit > 100) {
        $limit = 5;
    }

    if (!inventory_table_exists($conn, 'dokumen_bc')) {
        return array();
    }

    $sql = "SELECT TOP " . $limit . "
                no_dokumen,
                tanggal,
                jenis,
                arah,
                partner AS supplier,
                jumlah,
                status
            FROM dbo.dokumen_bc
            ORDER BY tanggal DESC, id DESC";

    return inventory_normalize_document_rows(db_query_rows($conn, $sql, array()));
}

function inventory_get_report_rows($conn, $reportId, $startDate, $endDate, $docType)
{
    if (!inventory_table_exists($conn, 'dokumen_bc')) {
        return array();
    }

    $where = array('tanggal >= ?', 'tanggal < DATEADD(day, 1, ?)');
    $params = array($startDate, $endDate);

    if ($docType !== '') {
        $where[] = 'jenis = ?';
        $params[] = $docType;
    }

    if ((int) $reportId === 1) {
        $where[] = "arah = 'Pemasukan'";
    } elseif ((int) $reportId === 2) {
        $where[] = "arah = 'Pengeluaran'";
    } elseif ((int) $reportId >= 3) {
        $where[] = "arah = 'Mutasi'";
    }

    $sql = "SELECT
                no_dokumen,
                tanggal,
                jenis,
                arah,
                partner AS supplier,
                jumlah,
                status
            FROM dbo.dokumen_bc
            WHERE " . implode(' AND ', $where) . "
            ORDER BY tanggal DESC, id DESC";

    return inventory_normalize_document_rows(db_query_rows($conn, $sql, $params));
}
