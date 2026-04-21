<?php
// FILE: exim/proses_kirim_ceisa.php
require_once __DIR__ . '/../config/database_p1.php';

$di_id = isset($_GET['di_id']) ? $_GET['di_id'] : '';

if (empty($di_id)) {
    die("Error: DI_ID tidak ditemukan.");
}

// 1. TARIK DATA DARI SQL SERVER
$sql = "{call SP_BC27(?)}";
$params = array(array($di_id, SQLSRV_PARAM_IN));
$stmt = sqlsrv_query($conn, $sql, $params);

$items = [];
$h = null; // Variable untuk header

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if (!$h) $h = $row;
    $items[] = $row;
}

if (!$h) {
    die("Error: Data tidak ditemukan di database.");
}

// 2. MAPPING KE FORMAT JSON CEISA 4.0
// Kita gunakan type casting (float/int) agar sesuai skema tpbbc27.json
$payload = [
    "asalData" => "S", //
    "asuransi" => 0.0,
    "bruto" => (float)($h['DIPA_PQTY'] * $h['PART_WEIGHT']), // Contoh kalkulasi bruto
    "cif" => 0.0,
    "disclaimer" => "1", // Setuju
    "kodeJenisTpb" => "1", // Contoh: Kawasan Berikat
    "freight" => 0.0,
    "hargaPenyerahan" => (float)array_sum(array_column($items, 'NILAI')),
    "jabatanTtd" => "EXIM MANAGER",
    "jumlahKontainer" => 0,
    "kodeDokumen" => "27",
    "kodeKantor" => trim($h['KPBC_CODE']),
    "kodeKantorTujuan" => trim($h['KPBC_CODE']), 
    "kodeTps" => "",
    "kodeTujuanPengiriman" => "1", // Penyerahan BKP
    "kodeTujuanTpb" => "1",
    "kodeValuta" => trim($h['CURR_CODE']),
    "kotaTtd" => trim($h['CUST_CITY']),
    "namaTtd" => trim($_SESSION['db_user']), 
    "ndpbm" => 0.0,
    "netto" => (float)($h['DIPA_PQTY'] * $h['PART_WEIGHT']),
    "nik" => "0",
    "nilaiBarang" => (float)array_sum(array_column($items, 'NILAI')),
    "nomorAju" => trim($h['KPBC_CODE']) . "27" . date('YmdHis'), // Generate Nomor Aju manual
    "seri" => 0,
    "tanggalAju" => date('Y-m-d'),
    "tanggalTtd" => date('Y-m-d'),
    "biayaTambahan" => 0.0,
    "biayaPengurang" => 0.0,
    "vd" => 0.0,
    "uangMuka" => 0.0,
    "nilaiJasa" => 0.0,
    "entitas" => [
        [
            "alamatEntitas" => trim($h['CUST_ADDR1']),
            "kodeEntitas" => "3", // Pengusaha
            "kodeJenisIdentitas" => "5", // NPWP 15 digit
            "namaEntitas" => trim($h['CUST_COMP']),
            "nomorIdentitas" => str_replace(['.', '-'], '', $h['CUST_NPWP']),
            "nomorIjinEntitas" => trim($h['CUST_TPB']),
            "seriEntitas" => 1,
            "tanggalIjinEntitas" => "2022-01-01"
        ]
    ],
    "barang" => []
];

// 3. MAPPING DETAIL BARANG
foreach ($items as $idx => $item) {
    $payload['barang'][] = [
        "cif" => 0.0,
        "hargaPenyerahan" => (float)$item['NILAI'],
        "jumlahSatuan" => (float)$item['QTY'],
        "kodeBarang" => trim($item['PART_NO']),
        "kodeSatuanBarang" => trim($item['PART_UNIT']),
        "netto" => (float)($item['PART_WEIGHT'] * $item['QTY']),
        "posTarif" => trim($item['CUST_HSNO']),
        "seriBarang" => (string)($idx + 1),
        "uraian" => trim($item['PART_NAME']),
        "bahanBaku" => [] // Sesuai requirement CEISA
    ];
}

// Simpan JSON ke file temporary atau langsung kirim (untuk testing tampilkan dulu)
header('Content-Type: application/json');
echo json_encode($payload, JSON_PRETTY_PRINT);

// NOTE: Di sini nantinya kamu tambahkan curl_exec() untuk kirim ke API CEISA
?>