<?php
// FILE: exim/proses_kirim_ceisa.php
require_once __DIR__ . '/../config/database_p1.php';

$di_id = isset($_GET['di_id']) ? $_GET['di_id'] : '';
if (empty($di_id)) { die("Error: DI_ID tidak ditemukan."); }

$sql = "{call SP_BC27(?)}";
$params = array(array($di_id, SQLSRV_PARAM_IN));
$stmt = sqlsrv_query($conn, $sql, $params);

$items = [];
$h = null; 
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if (!$h) $h = $row;
    $items[] = $row;
}
if (!$h) { die("Error: Data tidak ditemukan."); }

$total_nilai = (float)array_sum(array_column($items, 'NILAI'));

$payload = [
    "asalData" => "S", 
    "asuransi" => 0.0,
    "bruto" => (float)($h['DIPA_PQTY'] * $h['PART_WEIGHT']),
    "cif" => $total_nilai,
    "disclaimer" => "1",
    "kodeJenisTpb" => "1", 
    "freight" => 0.0,
    "hargaPenyerahan" => $total_nilai,
    "jabatanTtd" => "GENERAL MANAGER",
    "jumlahKontainer" => 0,
    "kodeDokumen" => "27",
    "kodeKantor" => trim($h['KPBC_CODE']),
    "kodeKantorTujuan" => trim($h['KPBC_CODE']), 
    "kodeTps" => "",
    "kodeTujuanPengiriman" => "1", 
    "kodeTujuanTpb" => "1",
    "kodeValuta" => trim($h['CURR_CODE']),
    "kotaTtd" => trim($h['CUST_CITY']),
    "namaTtd" => trim($h['CUST_ALIAS']), 
    "ndpbm" => 0.0,
    "netto" => (float)($h['DIPA_PQTY'] * $h['PART_WEIGHT']),
    "nik" => "0",
    "nilaiBarang" => $total_nilai,
    "nomorAju" => trim($h['KPBC_CODE']) . "27" . "123456" . date('Ymd') . "000001", // Harus 26 digit
    "seri" => 0,
    "tanggalAju" => date('Y-m-d'),
    "tanggalTtd" => date('Y-m-d'),
    "biayaTambahan" => 0.0,
    "biayaPengurang" => 0.0,
    "vd" => 0.0,
    "uangMuka" => 0.0,
    "nilaiJasa" => 0.0,
    
    // SINKRONISASI ENTITAS
    "entitas" => [
        [
            "alamatEntitas" => trim($h['CUST_ADDR1']),
            "kodeEntitas" => "3",
            "kodeJenisIdentitas" => "5",
            "namaEntitas" => "PT PERUSAHAAN ANDA", // Ganti sesuai perusahaan
            "nomorIdentitas" => "012345678901234",
            "nomorIjinEntitas" => "1234/KM.1/2022",
            "seriEntitas" => 1,
            "tanggalIjinEntitas" => "2022-01-01"
        ],
        [
            "alamatEntitas" => trim($h['CUST_ADDR1']),
            "kodeEntitas" => "7",
            "kodeJenisIdentitas" => "5",
            "namaEntitas" => trim($h['CUST_COMP']),
            "nomorIdentitas" => str_replace(['.', '-'], '', $h['CUST_NPWP']),
            "seriEntitas" => 2
        ]
    ],

    // DOKUMEN PELENGKAP (Wajib ada)
    "dokumen" => [
        [
            "kodeDokumen" => "380", // Invoice
            "nomorDokumen" => trim($h['DI_INVNO']),
            "seriDokumen" => 1,
            "tanggalDokumen" => $h['DI_DATE']->format('Y-m-d')
        ]
    ],

    // PENGANGKUT
    "pengangkut" => [
        [
            "namaPengangkut" => "TRUK",
            "nomorPengangkut" => trim($h['DI_DSNO']), // Asumsi nomor truk di sini
            "seriPengangkut" => "1"
        ]
    ],

    // KEMASAN
    "kemasan" => [
        [
            "jumlahKemasan" => (int)$h['DIPA_PQTY'],
            "kodeJenisKemasan" => "RK",
            "merkKemasan" => "-",
            "seriKemasan" => 1
        ]
    ],
    
    "pungutan" => [], 
    "barang" => []
];

// MAPPING DETAIL BARANG
foreach ($items as $idx => $item) {
    $payload['barang'][] = [
        "cif" => (float)$item['NILAI'],
        "cifRupiah" => 0.0,
        "hargaEkspor" => 0.0,
        "hargaPenyerahan" => (float)$item['NILAI'],
        "isiPerKemasan" => 0.0,
        "jumlahSatuan" => (float)$item['QTY'],
        "kodeBarang" => trim($item['PART_NO']),
        "kodeDokumen" => "27",
        "kodeSatuanBarang" => trim($item['PART_UNIT']),
        "merk" => "",
        "netto" => (float)($item['PART_WEIGHT'] * $item['QTY']),
        "nilaiBarang" => (float)$item['NILAI'],
        "posTarif" => trim($item['CUST_HSNO']),
        "seriBarang" => (string)($idx + 1),
        "spesifikasiLain" => "PRODUKSI",
        "tipe" => "",
        "ukuran" => "",
        "uraian" => trim($item['PART_NAME']),
        "hargaPerolehan" => 0.0,
        "kodeAsalBahanBaku" => "0",
        "ndpbm" => 0.0,
        "uangMuka" => 0.0,
        "nilaiJasa" => 0.0,
        "bahanBaku" => [] 
    ];
}

header('Content-Type: application/json');
echo json_encode($payload, JSON_PRETTY_PRINT);
// ... (Lanjutan dari kode penyusunan $payload sebelumnya)

// 1. Konversi Payload ke JSON string
$json_payload = json_encode($payload);

// 2. Konfigurasi API CEISA (Sesuaikan dengan data dari Bea Cukai)
$url_api = "https://api-ceisa40.beacukai.go.id/v1/dokumen-pabean"; // Contoh URL
$token   = "MASUKKAN_TOKEN_BEARER_ANDA_DI_SINI"; 

// 3. Inisialisasi cURL
$ch = curl_init($url_api);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // Method POST sesuai schema
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload); // Kirim body JSON
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
    'Content-Length: ' . strlen($json_payload)
]);

// 4. Eksekusi dan Ambil Respon
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// 5. Handling Respon dari CEISA
if ($curlError) {
    echo "Gagal menghubungkan ke CEISA: " . $curlError;
} else {
    $resArray = json_decode($response, true);
    
    if ($httpCode == 200 || $httpCode == 201) {
        // Jika sukses, kamu bisa update status di tabel BC_TRANS ERP kamu
        sqlsrv_query($conn, "UPDATE BC_TRANS SET NOMOR_BC = ?, BC_DATE = GETDATE() WHERE NO_TRANS = ?", 
                     array($resArray['nomorDaftar'], $di_id));
        
        echo "<script>alert('Sukses! Data terkirim ke CEISA. No Daftar: " . $resArray['nomorDaftar'] . "'); 
              window.location.href='dashboard_exim.php?page=status';</script>";
    } else {
        // Jika error validasi dari CEISA
        echo "<h3>Error CEISA (Code: $httpCode)</h3>";
        echo "<pre>" . print_r($resArray, true) . "</pre>";
        echo "<a href='dashboard_exim.php?page=bc27&di_id=$di_id'>Kembali ke Draft</a>";
    }
}