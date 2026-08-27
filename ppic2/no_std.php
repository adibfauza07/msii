<?php
// /msii/ppic/hitung_label_vendor.php
// PHP 5.4 + SQL Server (sqlsrv)
// Report exception untuk Master Data Packing & Label Vendor yang belum lengkap.

require_once "../config/database_ppic.php";

function h_report($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_number_report($value)
{
    return number_format((float)$value, 0, ',', '.');
}

function month_name_report($month)
{
    $months = array(
        1  => 'Januari', 2  => 'Februari', 3  => 'Maret', 4  => 'April',
        5  => 'Mei', 6  => 'Juni', 7  => 'Juli', 8  => 'Agustus',
        9  => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );
    return isset($months[$month]) ? $months[$month] : '';
}

$labelPerRoll = 400;

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cari  = isset($_GET['cari']) ? trim($_GET['cari']) : '';

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('n');
}

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

$tanggalAwal = sprintf('%04d-%02d-01', $tahun, $bulan);
$tanggalAkhir = date('Y-m-d', strtotime($tanggalAwal . ' +1 month'));

$params = array($tanggalAwal, $tanggalAkhir);
$whereCari = '';

if ($cari !== '') {
    $whereCari = "
        AND (
               PO.PO_NUM LIKE ?
            OR I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR SUP.SUP_CODE LIKE ?
            OR SUP.SUP_COMP LIKE ?
            OR ISNULL(SP.WARNA_LABEL, '') LIKE ?
        )
    ";

    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
WITH DATA_LABEL AS
(
    SELECT
        PO.PO_ID,
        ISNULL(PO.PO_NUM, CAST(PO.PO_ID AS VARCHAR)) AS PO_NUM, 
        PO.PO_DATE,
        ISNULL(POD.POD_QTY, 0) AS PO_QTY,
        I.ITEM_ID,
        I.ITEM_CODE AS MAT_CODE,
        I.ITEM_NAME AS MAT_NAME,
        SUP.SUP_CODE,
        SUP.SUP_COMP,

        ISNULL(SP.STD_PACK, 0) AS STD_PACK,
        ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX,

        CASE
            WHEN LTRIM(RTRIM(ISNULL(SP.WARNA_LABEL, ''))) = '' THEN 'TANPA WARNA'
            ELSE UPPER(LTRIM(RTRIM(SP.WARNA_LABEL)))
        END AS WARNA_LABEL

    FROM dbo.PO PO
    INNER JOIN dbo.PO_DETAIL POD ON PO.PO_ID = POD.PO_ID
    INNER JOIN dbo.SUPPLIER SUP ON PO.SUP_ID = SUP.SUP_ID
    INNER JOIN dbo.ITEMS I ON POD.ITEM_ID = I.ITEM_ID

    OUTER APPLY
    (
        SELECT TOP 1 SP2.PACK_ID, SP2.STD_PACK, SP2.STD_PACK_BOX, SP2.WARNA_LABEL
        FROM dbo.STD_PACK SP2
        WHERE SP2.ITEM_ID = I.ITEM_ID
        ORDER BY SP2.PACK_ID DESC
    ) SP

    WHERE SUP.SUP_CODE LIKE 'v%'
      AND PO.PO_DATE >= ?
      AND PO.PO_DATE < ?
      /* FILTER: Tampilkan jika (STD_PACK & BOX <= 1) ATAU (WARNA LABEL KOSONG) */
      AND (
          (ISNULL(SP.STD_PACK, 0) <= 1 AND ISNULL(SP.STD_PACK_BOX, 0) <= 1)
          OR 
          (LTRIM(RTRIM(ISNULL(SP.WARNA_LABEL, ''))) = '')
      )
      $whereCari
),
HITUNG_PER_PO AS
(
    SELECT
        PO_ID, PO_NUM, ITEM_ID, MAT_CODE, MAT_NAME, SUP_CODE, SUP_COMP, WARNA_LABEL, PO_QTY, STD_PACK, STD_PACK_BOX,

        CASE
            WHEN STD_PACK > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS LABEL_BAG,

        CASE
            WHEN STD_PACK_BOX > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK_BOX AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS LABEL_BOX
    FROM DATA_LABEL
),
TOTAL_PER_VENDOR AS
(
    SELECT
        SUP_CODE,
        SUP_COMP,
        MAT_CODE,
        MAT_NAME,
        WARNA_LABEL,
        COUNT(DISTINCT PO_ID) AS JUMLAH_PO,
        SUM(PO_QTY) AS TOTAL_QTY_PRODUKSI,
        SUM(LABEL_BAG) AS TOTAL_LABEL_BAG,
        SUM(LABEL_BOX) AS TOTAL_LABEL_BOX,

        SUM(CASE WHEN STD_PACK <= 1 THEN 1 ELSE 0 END) AS PO_BAG_TANPA_STD,
        SUM(CASE WHEN STD_PACK_BOX <= 1 THEN 1 ELSE 0 END) AS PO_BOX_TANPA_STD
    FROM HITUNG_PER_PO
    GROUP BY SUP_CODE, SUP_COMP, MAT_CODE, MAT_NAME, WARNA_LABEL
)
SELECT
    SUP_CODE,
    SUP_COMP,
    MAT_CODE,
    MAT_NAME,
    WARNA_LABEL,
    JUMLAH_PO,
    TOTAL_QTY_PRODUKSI,
    
    TOTAL_LABEL_BAG,
    CASE WHEN TOTAL_LABEL_BAG > 0 THEN CAST(CEILING(CAST(TOTAL_LABEL_BAG AS DECIMAL(18, 4)) / $labelPerRoll) AS BIGINT) ELSE 0 END AS TOTAL_ROLL_BAG,
    CASE WHEN TOTAL_LABEL_BAG > 0 THEN (CAST(CEILING(CAST(TOTAL_LABEL_BAG AS DECIMAL(18, 4)) / $labelPerRoll) AS BIGINT) * $labelPerRoll) - TOTAL_LABEL_BAG ELSE 0 END AS SISA_KAPASITAS_BAG,

    TOTAL_LABEL_BOX,
    CASE WHEN TOTAL_LABEL_BOX > 0 THEN CAST(CEILING(CAST(TOTAL_LABEL_BOX AS DECIMAL(18, 4)) / $labelPerRoll) AS BIGINT) ELSE 0 END AS TOTAL_ROLL_BOX,
    CASE WHEN TOTAL_LABEL_BOX > 0 THEN (CAST(CEILING(CAST(TOTAL_LABEL_BOX AS DECIMAL(18, 4)) / $labelPerRoll) AS BIGINT) * $labelPerRoll) - TOTAL_LABEL_BOX ELSE 0 END AS SISA_KAPASITAS_BOX,

    PO_BAG_TANPA_STD,
    PO_BOX_TANPA_STD

FROM TOTAL_PER_VENDOR
ORDER BY
    SUP_CODE ASC,
    MAT_CODE ASC,
    CASE WHEN WARNA_LABEL = 'TANPA WARNA' THEN 1 ELSE 0 END,
    WARNA_LABEL
";

$stmt = sqlsrv_query($conn, $sql, $params);
$queryError = null;
$rows = array();

if ($stmt === false) {
    $queryError = sqlsrv_errors();
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
}

$totalBaris = count($rows);
$totalPo = 0;
$totalProduksi = 0;
$totalLabelBag = 0;
$totalRollBag = 0;
$totalSisaBag = 0;
$totalLabelBox = 0;
$totalRollBox = 0;
$totalSisaBox = 0;
$totalPoBagTanpaStd = 0;
$totalPoBoxTanpaStd = 0;

foreach ($rows as $row) {
    $totalPo += (int)$row['JUMLAH_PO'];
    $totalProduksi += (float)$row['TOTAL_QTY_PRODUKSI'];
    $totalLabelBag += (int)$row['TOTAL_LABEL_BAG'];
    $totalRollBag += (int)$row['TOTAL_ROLL_BAG'];
    $totalSisaBag += (int)$row['SISA_KAPASITAS_BAG'];
    $totalLabelBox += (int)$row['TOTAL_LABEL_BOX'];
    $totalRollBox += (int)$row['TOTAL_ROLL_BOX'];
    $totalSisaBox += (int)$row['SISA_KAPASITAS_BOX'];
    $totalPoBagTanpaStd += (int)$row['PO_BAG_TANPA_STD'];
    $totalPoBoxTanpaStd += (int)$row['PO_BOX_TANPA_STD'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Data Packing / Label Belum Lengkap</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; color: #25313c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .page { max-width: 1700px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .title h1 { margin: 0 0 4px; font-size: 23px; }
        .title p { margin: 0; color: #697784; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid #b9c2ca; border-radius: 5px; padding: 8px 14px; background: #ffffff; color: #25313c; text-decoration: none; cursor: pointer; font-size: 12px; font-weight: bold; }
        .button-primary { background: #2368a2; border-color: #2368a2; color: #ffffff; }
        .button-dark { background: #34495e; border-color: #34495e; color: #ffffff; }
        .button-dark:hover { background: #2c3e50; }
        .filter-card, .report-card, .notice { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.06); }
        .filter-card { padding: 13px; margin-bottom: 14px; }
        .filter-row { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 10px; }
        .field label { display: block; margin-bottom: 5px; font-size: 11px; font-weight: bold; color: #586672; }
        .field select, .field input { height: 34px; border: 1px solid #bdc7d0; border-radius: 5px; padding: 6px 9px; background: #ffffff; color: #25313c; }
        .filter-note { margin-top: 9px; color: #73808c; font-size: 11px; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(5, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .summary-card { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; padding: 12px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.05); }
        .summary-card .label { display: block; color: #71808d; font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 7px; }
        .summary-card .value { display: block; font-size: 21px; font-weight: bold; }
        
        .report-head { padding: 12px 14px; border-bottom: 1px solid #dce2e7; background: #fbfcfd; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1400px; border-collapse: collapse; }
        th, td { border-right: 1px solid #dce2e7; border-bottom: 1px solid #dce2e7; padding: 8px 7px; vertical-align: middle; }
        th:last-child, td:last-child { border-right: 0; }
        thead th { background: #34495e; color: #ffffff; text-align: center; font-size: 11px; line-height: 1.25; }
        thead tr:nth-child(2) th { background: #405a72; }
        tbody tr:nth-child(even) { background: #fafbfc; }
        tbody tr:hover { background: #f1f7fb; }
        tfoot td { background: #eaf0f5; font-weight: bold; }
        
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .roll-value { font-size: 15px; font-weight: bold; color: #174f7a; }
        
        .badge { display: inline-block; min-width: 24px; padding: 3px 7px; border-radius: 12px; font-size: 10px; text-align: center; }
        .badge-danger { background: #fbe2e2; color: #a52a2a; border: 1px solid #efbcbc; font-weight: bold; }
        .badge-ok { background: #e4f4e8; color: #27743b; border: 1px solid #bfe1c8; }
        
        .notice { padding: 13px; margin-bottom: 14px; }
        .notice-error { color: #8f2525; background: #fff5f5; border-color: #efcaca; }
        .empty { padding: 28px !important; color: #788692; text-align: center; }
        .formula-note { padding: 11px 14px; background: #fbfcfd; border-top: 1px solid #dce2e7; color: #667582; font-size: 11px; line-height: 1.55; }

        /* ==================================================
           CSS KHUSUS UNTUK PRINT (Dioptimalkan)
           ================================================== */
        @media print { 
            @page { 
                size: landscape; /* Memaksa orientasi kertas landscape */
                margin: 10mm; 
            }
            body { 
                background: #ffffff; 
                padding: 0; 
                margin: 0;
                font-size: 11px; /* Huruf sedikit dikecilkan agar muat */
                color: #000;
            }
            .page { max-width: 100%; margin: 0; }
            
            /* Sembunyikan elemen yang tidak perlu di print */
            .no-print { display: none !important; } 
            
            /* Hilangkan box-shadow dan border luar yang memakan tempat */
            .filter-card, .report-card, .summary-card { 
                border: none; 
                box-shadow: none; 
            }
            
            /* Sesuaikan grid summary agar jadi inline/baris */
            .summary-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 15px;
            }
            .summary-card {
                border: 1px solid #ccc;
                padding: 8px;
                flex: 1;
            }
            .summary-card .value { font-size: 16px; }

            /* Optimasi Tabel Print */
            .table-wrap { overflow: visible; }
            table { min-width: 100% !important; border-top: 1px solid #000; border-left: 1px solid #000; }
            th, td { 
                border-right: 1px solid #000 !important; 
                border-bottom: 1px solid #000 !important; 
                padding: 4px !important; /* Kurangi padding tabel agar lebih padat */
                font-size: 10px;
            }
            
            /* Paksa browser mencetak warna background tabel (Chrome/Edge/Firefox) */
            thead th, tfoot td, .badge {
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
            }
            
            thead th { background-color: #e2e8ed !important; color: #000 !important; font-weight: bold; }
            tfoot td { background-color: #f3f5f7 !important; }
            
            .badge-danger { background-color: #fbe2e2 !important; color: #a52a2a !important; border: 1px solid #a52a2a !important;}
            .badge-ok { background-color: #e4f4e8 !important; color: #27743b !important; border: 1px solid #27743b !important;}
            
            a { color: #000 !important; text-decoration: none !important; border-bottom: none !important; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="title">
            <h1>Report Anomali Data Packing & Warna Label</h1>
            <p>
                Periode <?php echo h_report(month_name_report($bulan)); ?> <?php echo h_report($tahun); ?> 
                — Menampilkan Material yang STD_PACK <= 1 <strong>ATAU</strong> Warna Label belum diisi.
            </p>
        </div>
        <div class="no-print">
            <!-- TOMBOL PRINT -->
            <button type="button" class="button button-dark" onclick="window.print();">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                  <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>
                Print Laporan
            </button>
        </div>
    </div>

    <!-- FILTER AKAN DIHILANGKAN SAAT DI PRINT (class="no-print") -->
    <form method="get" action="" class="filter-card no-print">
        <div class="filter-row">
            <div class="field">
                <label for="bulan">Bulan PO</label>
                <select name="bulan" id="bulan">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($bulan === $i) ? 'selected' : ''; ?>>
                            <?php echo h_report(month_name_report($i)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label for="tahun">Tahun PO</label>
                <input type="text" name="tahun" id="tahun" maxlength="4" style="width:90px;" value="<?php echo h_report($tahun); ?>">
            </div>
            <div class="field">
                <label for="cari">Cari</label>
                <input type="text" name="cari" id="cari" style="width:280px;" value="<?php echo h_report($cari); ?>" placeholder="PO, vendor, material, warna">
            </div>
            <div>
                <button type="submit" class="button button-primary">Tampilkan</button>
                <a href="hitung_label_vendor.php" class="button">Reset</a>
            </div>
        </div>
        <div class="filter-note">Difilter berdasarkan Tanggal PO dan khusus Vendor (kode 'v%'). Data digrup per <strong>Material</strong> & Vendor.</div>
    </form>

    <?php if ($queryError !== null): ?>
        <div class="notice notice-error no-print">
            <strong>Query report gagal.</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>

        <div class="summary-grid">
            <div class="summary-card"><span class="label">Total Material Bermasalah</span><span class="value"><?php echo format_number_report($totalBaris); ?></span></div>
            <div class="summary-card"><span class="label">Total PO Terdampak</span><span class="value"><?php echo format_number_report($totalPo); ?></span></div>
            <div class="summary-card"><span class="label">Total Qty Produksi</span><span class="value"><?php echo format_number_report($totalProduksi); ?></span></div>
            <div class="summary-card"><span class="label">PO BAG Tanpa STD</span><span class="value" style="color: #a52a2a;"><?php echo format_number_report($totalPoBagTanpaStd); ?></span></div>
            <div class="summary-card"><span class="label">PO BOX Tanpa STD</span><span class="value" style="color: #a52a2a;"><?php echo format_number_report($totalPoBoxTanpaStd); ?></span></div>
        </div>

        <div class="report-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">No.</th>
                            <th rowspan="2">Vendor Code</th>
                            <th rowspan="2">Vendor Name</th>
                            <th rowspan="2">Mat Code</th>
                            <th rowspan="2" style="width: 250px;">Mat Name</th>
                            <th rowspan="2">Warna Label</th>
                            <th rowspan="2">Jml PO</th>
                            <th rowspan="2">Qty PO Vendor</th>
                            <th colspan="3">BAG</th>
                            <th colspan="3">BOX</th>
                            <th colspan="2">Standard Kosong</th>
                        </tr>
                        <tr>
                            <th>Label</th>
                            <th>Roll</th>
                            <th>Sisa Kap.</th>
                            <th>Label</th>
                            <th>Roll</th>
                            <th>Sisa Kap.</th>
                            <th>BAG</th>
                            <th>BOX</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalBaris === 0): ?>
                            <tr><td colspan="16" class="empty">Tidak ada anomali. Semua data STD_PACK dan Warna Label sudah lengkap.</td></tr>
                        <?php else: ?>
                            <?php $no = 0; foreach ($rows as $row): $no++; ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td class="text-left"><?php echo h_report($row['SUP_CODE']); ?></td>
                                    <td class="text-left"><strong><?php echo h_report($row['SUP_COMP']); ?></strong></td>
                                    
                                    <td class="text-left"><?php echo h_report($row['MAT_CODE']); ?></td>
                                    <td class="text-left"><?php echo h_report($row['MAT_NAME']); ?></td>

                                    <td class="text-center">
                                        <?php if ($row['WARNA_LABEL'] === 'TANPA WARNA'): ?>
                                            <span class="badge badge-danger">KOSONG</span>
                                        <?php else: ?>
                                            <a href="detail_vendor.php?sup_code=<?php echo urlencode($row['SUP_CODE']); ?>&amp;warna=<?php echo urlencode($row['WARNA_LABEL']); ?>&amp;bulan=<?php echo h_report($bulan); ?>&amp;tahun=<?php echo h_report($tahun); ?>&amp;cari=<?php echo urlencode($cari); ?>" target="_blank" rel="noopener noreferrer" style="color: #2368a2; text-decoration: none; border-bottom: 1px dashed #2368a2;">
                                                <strong><?php echo h_report($row['WARNA_LABEL']); ?></strong>
                                            </a>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-right"><?php echo format_number_report($row['JUMLAH_PO']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_QTY_PRODUKSI']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_LABEL_BAG']); ?></td>
                                    <td class="text-right roll-value"><?php echo format_number_report($row['TOTAL_ROLL_BAG']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['SISA_KAPASITAS_BAG']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_LABEL_BOX']); ?></td>
                                    <td class="text-right roll-value"><?php echo format_number_report($row['TOTAL_ROLL_BOX']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['SISA_KAPASITAS_BOX']); ?></td>
                                    <td class="text-center"><?php echo ((int)$row['PO_BAG_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BAG_TANPA_STD']).'</span>' : '<span class="badge badge-ok">0</span>'; ?></td>
                                    <td class="text-center"><?php echo ((int)$row['PO_BOX_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BOX_TANPA_STD']).'</span>' : '<span class="badge badge-ok">0</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($totalBaris > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right">TOTAL</td>
                                <td class="text-right"><?php echo format_number_report($totalPo); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalProduksi); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBag); ?></td>
                                <td class="text-right roll-value"><?php echo format_number_report($totalRollBag); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalSisaBag); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBox); ?></td>
                                <td class="text-right roll-value"><?php echo format_number_report($totalRollBox); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalSisaBox); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalPoBagTanpaStd); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalPoBoxTanpaStd); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <div class="formula-note no-print"><strong>Catatan:</strong> Tabel ini sekarang dikelompokkan secara spesifik per-<strong>Material</strong>. Data akan tampil jika kapasitas <code>STD_PACK / BOX</code> <= 1, <strong>ATAU</strong> jika konfigurasi <code>WARNA_LABEL</code> dibiarkan kosong. (Ditandai dengan badge merah muda "KOSONG").</div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>