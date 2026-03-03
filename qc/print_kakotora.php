<?php
// FILE: msii/qc/print_kakotora.php
// DESKRIPSI: Halaman Cetak Laporan Kakotora (A4 Layout)

// 1. CEK SESSION & PLANT
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { die("Akses ditolak. Silakan login."); }

$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

// 2. KONEKSI DATABASE (Copy Logic dari API)
if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    if(!isset($_SESSION['server_sql'])) $_SESSION['server_sql'] = "192.168.0.xxx"; // Pastikan IP ini benar
    
    $db_path = __DIR__ . '/../config/database.php';
    if(file_exists($db_path)) { require_once $db_path; } else { die("Config P2 Error"); }
} else {
    require_once __DIR__ . '/../config/database_p1.php'; 
}

// 3. AMBIL DATA
$id = isset($_GET['id']) ? $_GET['id'] : 0;
if($id == 0) die("ID Data tidak valid.");

// Query Gabungan Header & Detail
$sql = "SELECT 
            a.*, 
            c.CUST_COMP, v.PART_NAME, v.PART_CODE,
            d.cause, d.counter, d.pic, d.eff_date, d.status as detail_status,
            (SELECT TOP 1 loc_problem FROM car_loc WHERE car_id = a.car_id) as loc_problem,
            (SELECT TOP 1 EFEK FROM car_efek WHERE car_id = a.car_id) as efek,
            (SELECT TOP 1 klasifikasi FROM car_klasifikasi WHERE car_id = a.car_id) as klasifikasi
        FROM car_claim a
        LEFT JOIN CUST c ON a.cust_id = c.CUST_ID
        LEFT JOIN ITEM_CUSTINFO_VIEW v ON a.item_id = v.ITEM_ID
        LEFT JOIN car_claim_detail d ON a.car_id = d.car_id
        WHERE a.car_id = ?";

$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if(!$data) die("Data tidak ditemukan.");

// Format Tanggal
$tgl_claim = ($data['claim_date']) ? $data['claim_date']->format('d-M-Y') : '-';
$tgl_eff   = ($data['eff_date']) ? $data['eff_date']->format('d-M-Y') : '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Kakotora - <?php echo $data['car_no']; ?></title>
    <!-- Bootstrap (Hanya untuk Grid System) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; -webkit-print-color-adjust: exact; }
        
        /* Layout Kertas A4 */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 10mm auto;
            border: 1px solid #D3D3D3;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        
        /* Table Styling */
        .table-report { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table-report th, .table-report td { border: 1px solid black; padding: 6px; vertical-align: top; }
        .bg-header { background-color: #f0f0f0 !important; font-weight: bold; text-transform: uppercase; }
        .no-border { border: none !important; }
        
        /* Header Title */
        .report-title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 5px; text-decoration: underline; }
        .report-subtitle { text-align: center; font-size: 14px; margin-bottom: 20px; font-weight: bold; }

        /* Signature Box */
        .signature-box { height: 80px; }
        
        /* Print Media Queries */
        @media print {
            body { margin: 0; background: none; }
            .page { margin: 0; border: initial; width: 100%; min-height: initial; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>

    <!-- Tombol Aksi (Hilang saat diprint) -->
    <div class="text-center my-3 no-print">
        <button onclick="window.print()" class="btn btn-primary fw-bold">🖨️ CETAK DOKUMEN (PRINT)</button>
        <button onclick="window.close()" class="btn btn-secondary">TUTUP</button>
    </div>

    <div class="page">
        <!-- JUDUL -->
        <div class="report-title">KAKOTORA REPORT (PAST TROUBLE)</div>
        <div class="report-subtitle">PT. MULTISTRADA ARAH SARANA (<?php echo strtoupper(($active_plant == 'p2') ? 'PLANT 2' : 'PLANT 1'); ?>)</div>

        <!-- 1. INFORMASI UMUM -->
        <table class="table-report">
            <tr>
                <td width="15%" class="bg-header">No. CAR</td>
                <td width="35%" class="fw-bold fs-6"><?php echo $data['car_no']; ?></td>
                <td width="15%" class="bg-header">Tanggal Claim</td>
                <td width="35%"><?php echo $tgl_claim; ?></td>
            </tr>
            <tr>
                <td class="bg-header">Customer</td>
                <td><?php echo $data['CUST_COMP']; ?></td>
                <td class="bg-header">Status Data</td>
                <td><?php echo $data['event_status']; ?></td>
            </tr>
            <tr>
                <td class="bg-header">Nama Part</td>
                <td><?php echo $data['PART_NAME']; ?> <br><small class="text-muted">(<?php echo $data['PART_CODE']; ?>)</small></td>
                <td class="bg-header">Qty Defect</td>
                <td><?php echo $data['qty']; ?> Pcs</td>
            </tr>
        </table>

        <!-- 2. DETAIL MASALAH -->
        <table class="table-report">
            <tr class="bg-header text-center">
                <td colspan="2">DETAIL PERMASALAHAN (PROBLEM DESCRIPTION)</td>
            </tr>
            <tr>
                <td width="20%" class="bg-header">Problem</td>
                <td><?php echo nl2br($data['problem']); ?></td>
            </tr>
            <tr>
                <td class="bg-header">Lokasi & Efek</td>
                <td>
                    <b>Lokasi:</b> <?php echo $data['loc_problem']; ?><br>
                    <b>Efek:</b> <?php echo $data['efek']; ?>
                </td>
            </tr>
            <tr>
                <td class="bg-header">Klasifikasi</td>
                <td><?php echo $data['klasifikasi']; ?></td>
            </tr>
        </table>

        <!-- 3. ANALISIS & SOLUSI -->
        <table class="table-report">
            <tr class="bg-header text-center">
                <td colspan="2">ANALISIS & PENANGGULANGAN</td>
            </tr>
            <tr>
                <td width="50%" class="bg-header">Penyebab Utama (Root Cause)</td>
                <td width="50%" class="bg-header">Tindakan Perbaikan (Countermeasure)</td>
            </tr>
            <tr>
                <td style="height: 100px;"><?php echo nl2br($data['cause']); ?></td>
                <td style="height: 100px;"><?php echo nl2br($data['counter']); ?></td>
            </tr>
        </table>

        <!-- 4. GAMBAR BUKTI -->
        <table class="table-report">
            <tr class="bg-header text-center">
                <td>EVIDENCE / BUKTI GAMBAR</td>
            </tr>
            <tr>
                <td class="text-center p-3">
                    <?php 
                        // Trik agar gambar reload saat print
                        $imgUrl = "view_image.php?id=" . $data['car_id'] . "&t=" . time(); 
                    ?>
                    <img src="<?php echo $imgUrl; ?>" style="max-height: 350px; max-width: 90%; border: 1px solid #ddd; padding: 5px;">
                </td>
            </tr>
        </table>

        <!-- 5. STATUS & TANDA TANGAN -->
        <table class="table-report">
            <tr>
                <td width="20%" class="bg-header">PIC</td>
                <td width="30%"><?php echo $data['pic']; ?></td>
                <td width="20%" class="bg-header">Status Progress</td>
                <td width="30%"><?php echo $data['detail_status']; ?></td>
            </tr>
            <tr>
                <td class="bg-header">Tanggal Efektif</td>
                <td colspan="3"><?php echo $tgl_eff; ?></td>
            </tr>
        </table>

        <br>

        <!-- TANDA TANGAN (KOTAK 3 KOLOM) -->
        <table class="table-report text-center">
            <tr>
                <td width="33%" class="bg-header">Dibuat Oleh</td>
                <td width="33%" class="bg-header">Diperiksa Oleh</td>
                <td width="33%" class="bg-header">Disetujui Oleh</td>
            </tr>
            <tr>
                <td class="signature-box">
                    <br><br><br>
                    ( <?php echo $data['pic']; ?> )
                </td>
                <td class="signature-box">
                    <br><br><br>
                    ( SPV / Manager )
                </td>
                <td class="signature-box">
                    <br><br><br>
                    ( Dept. Head / QC )
                </td>
            </tr>
        </table>
        
        <div class="text-end small text-muted">
            <i>Dicetak dari QC System pada: <?php echo date('d-m-Y H:i'); ?></i>
        </div>

    </div>

</body>
</html>