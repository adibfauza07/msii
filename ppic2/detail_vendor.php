<?php
// /msii/ppic/detail_vendor.php
// PHP 5.4 + SQL Server (sqlsrv)
// Menampilkan rincian PO berdasarkan Vendor (SUP_CODE) dan Warna Label.

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

$bulan    = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cari     = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$sup_code = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
$warna    = isset($_GET['warna']) ? trim($_GET['warna']) : '';

if ($sup_code === '' || $warna === '') {
    die("Vendor Code atau Warna tidak valid/tidak dipilih.");
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
    for($i=0; $i<6; $i++){
        $params[] = $like;
    }
}

$params[] = $sup_code;
$params[] = $warna;

$sql = "
WITH DATA_LABEL AS
(
    SELECT
        PO.PO_ID,
        ISNULL(PO.PO_NUM, CAST(PO.PO_ID AS VARCHAR)) AS PO_NUM, 
        PO.PO_DATE,
        ISNULL(POD.POD_QTY, 0) AS PO_QTY,
        I.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME,
        SUP.SUP_CODE, SUP.SUP_COMP,

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
      $whereCari
),
HITUNG_PER_PO AS
(
    SELECT
        PO_ID, PO_NUM, PO_DATE, ITEM_CODE, ITEM_NAME, SUP_CODE, SUP_COMP, WARNA_LABEL, PO_QTY, STD_PACK, STD_PACK_BOX,
        
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
)
SELECT *
FROM HITUNG_PER_PO
WHERE SUP_CODE = ? AND WARNA_LABEL = ?
ORDER BY PO_DATE ASC, PO_NUM ASC, ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn, $sql, $params);
$queryError = null;
$rows = array();

$totalProduksi = 0;
$totalLabelBag = 0;
$totalLabelBox = 0;
$vendorName = "";

if ($stmt === false) {
    $queryError = sqlsrv_errors();
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
        $totalProduksi += (float)$row['PO_QTY'];
        $totalLabelBag += (int)$row['LABEL_BAG'];
        $totalLabelBox += (int)$row['LABEL_BOX'];
        $vendorName = $row['SUP_COMP']; // Ambil nama vendor dari baris manapun
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail PO Vendor - <?php echo h_report($sup_code); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; color: #25313c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .page { max-width: 1400px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .title h1 { margin: 0 0 4px; font-size: 23px; }
        .title p { margin: 0; color: #697784; }
        .button { display: inline-block; border: 1px solid #b9c2ca; border-radius: 5px; padding: 8px 12px; background: #ffffff; color: #25313c; text-decoration: none; cursor: pointer; font-size: 12px; }
        .button-primary { background: #2368a2; border-color: #2368a2; color: #ffffff; }
        .report-card, .notice { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.06); }
        .report-head { padding: 12px 14px; border-bottom: 1px solid #dce2e7; background: #fbfcfd; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1000px; border-collapse: collapse; }
        th, td { border-right: 1px solid #dce2e7; border-bottom: 1px solid #dce2e7; padding: 8px 7px; vertical-align: middle; }
        th:last-child, td:last-child { border-right: 0; }
        thead th { background: #34495e; color: #ffffff; text-align: center; font-size: 11px; }
        tbody tr:nth-child(even) { background: #fafbfc; }
        tbody tr:hover { background: #f1f7fb; }
        tfoot td { background: #eaf0f5; font-weight: bold; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .empty { padding: 28px !important; color: #788692; text-align: center;}
        .notice-error { color: #8f2525; background: #fff5f5; border-color: #efcaca; padding: 13px; margin-bottom: 14px; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 12px; font-size: 10px; background: #fbe2e2; color: #a52a2a; border: 1px solid #efbcbc; }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="title">
            <h1>Detail PO: <?php echo h_report($vendorName ?: $sup_code); ?></h1>
            <p>
                Warna Label: <strong><?php echo h_report($warna); ?></strong> &mdash; 
                Periode: <?php echo h_report(month_name_report($bulan)) . ' ' . h_report($tahun); ?> 
                (Total Data: <?php echo format_number_report(count($rows)); ?> PO)
            </p>
        </div>
        <div>
            <button type="button" class="button" onclick="window.close();">&laquo; Tutup Tab</button>
            <button type="button" class="button button-primary" onclick="window.print();">Print Report</button>
        </div>
    </div>

    <?php if ($queryError !== null): ?>
        <div class="notice notice-error">
            <strong>Query report gagal.</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>
        <div class="report-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal PO</th>
                            <th>PO Number</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Qty PO</th>
                            <th>STD Pack (BAG)</th>
                            <th>Label BAG</th>
                            <th>STD Pack (BOX)</th>
                            <th>Label BOX</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="10" class="empty">Tidak ada rincian data.</td></tr>
                        <?php else: ?>
                            <?php $no = 0; foreach ($rows as $row): $no++; ?>
                                <?php 
                                    $poDate = '';
                                    if ($row['PO_DATE'] instanceof DateTime) {
                                        $poDate = $row['PO_DATE']->format('d/m/Y');
                                    } else {
                                        $poDate = h_report($row['PO_DATE']);
                                    }
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td class="text-center"><?php echo $poDate; ?></td>
                                    <td class="text-left"><strong><?php echo h_report($row['PO_NUM']); ?></strong></td>
                                    <td class="text-left"><?php echo h_report($row['ITEM_CODE']); ?></td>
                                    <td class="text-left"><?php echo h_report($row['ITEM_NAME']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['PO_QTY']); ?></td>
                                    
                                    <td class="text-center">
                                        <?php echo ((int)$row['STD_PACK'] <= 1) ? '<span class="badge">Nol / Belum Disetting</span>' : format_number_report($row['STD_PACK']); ?>
                                    </td>
                                    <td class="text-right"><?php echo format_number_report($row['LABEL_BAG']); ?></td>
                                    
                                    <td class="text-center">
                                        <?php echo ((int)$row['STD_PACK_BOX'] <= 1) ? '<span class="badge">Nol / Belum Disetting</span>' : format_number_report($row['STD_PACK_BOX']); ?>
                                    </td>
                                    <td class="text-right"><?php echo format_number_report($row['LABEL_BOX']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($rows) > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right">TOTAL</td>
                                <td class="text-right"><?php echo format_number_report($totalProduksi); ?></td>
                                <td class="text-right"></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBag); ?></td>
                                <td class="text-right"></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBox); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>