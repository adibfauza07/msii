<?php
// material_slip/functions/helpers.php

// Ambil koneksi dari parent
require_once __DIR__ . '/../config/database.php';

function generate_doc_number() {
    $conn = getDB();
    if (!$conn) {
        return 'MS-' . date('Ym') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    // Begin transaction
    sqlsrv_begin_transaction($conn);
    
    // Cek apakah tabel GEN_DOC_NUMBER ada
    $sql_check = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'GEN_DOC_NUMBER'";
    $stmt = sqlsrv_query($conn, $sql_check);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] == 0) {
        // Buat tabel jika belum ada
        $sql_create = "CREATE TABLE GEN_DOC_NUMBER (
            DOC_TYPE VARCHAR(20) PRIMARY KEY,
            LAST_NUMBER INT DEFAULT 0
        )";
        sqlsrv_query($conn, $sql_create);
        sqlsrv_query($conn, "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 0)");
        $last_number = 1;
    } else {
        $sql = "SELECT LAST_NUMBER FROM GEN_DOC_NUMBER WHERE DOC_TYPE = 'MATSLIP'";
        $stmt = sqlsrv_query($conn, $sql);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        if ($row) {
            $last_number = $row['LAST_NUMBER'] + 1;
        } else {
            $last_number = 1;
            $sql_insert = "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 1)";
            sqlsrv_query($conn, $sql_insert);
        }
    }
    
    $sql_update = "UPDATE GEN_DOC_NUMBER SET LAST_NUMBER = $last_number WHERE DOC_TYPE = 'MATSLIP'";
    sqlsrv_query($conn, $sql_update);
    
    sqlsrv_commit($conn);
    
    return 'MS-' . date('Ym') . '-' . str_pad($last_number, 6, '0', STR_PAD_LEFT);
}

function get_header($doc_number) {
    $conn = getDB();
    if (!$conn) return null;
    
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_HEADER WHERE TRAN_DOC = ?";
    $params = array($doc_number);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return null;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function get_details($header_id) {
    $conn = getDB();
    if (!$conn) return array();
    
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ? ORDER BY ID";
    $params = array($header_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

function get_all_headers($limit = 10, $offset = 0) {
    $conn = getDB();
    if (!$conn) return array();
    
    // SQL Server 2008 menggunakan ROW_NUMBER untuk pagination
    $sql = "SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (ORDER BY ID DESC) as RowNum 
        FROM TR_MATERIAL_SLIP_HEADER
    ) as temp WHERE RowNum > ? AND RowNum <= ?";
    $params = array($offset, $offset + $limit);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

function count_all_headers() {
    $conn = getDB();
    if (!$conn) return 0;
    
    $sql = "SELECT COUNT(*) as total FROM TR_MATERIAL_SLIP_HEADER";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row ? $row['total'] : 0;
}

function get_instansi() {
    return array('nama_instansi' => 'PT. MAJU JAYA');
}

function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . $host . $dir . '/' . $path;
}

function redirect($url) {
    header('Location: ' . base_url($url));
    exit;
}

function set_flash($key, $message) {
    $_SESSION[$key] = $message;
}

function get_flash($key) {
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}
?>