<?php
// /msii/ppic/hitung_label_vendor.php
// PHP 5.4 + SQL Server (sqlsrv)
// Report kebutuhan roll label untuk Production Vendor (Group by Vendor & Warna).

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
        I.ITEM_CODE,
        I.ITEM_NAME,
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
      $whereCari
),
HITUNG_PER_PO AS
(
    SELECT
        PO_ID, PO_NUM, ITEM_ID, ITEM_CODE, ITEM_NAME, SUP_CODE, SUP_COMP, WARNA_LABEL, PO_QTY, STD_PACK, STD_PACK_BOX,

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
        WARNA_LABEL,
        COUNT(DISTINCT PO_ID) AS JUMLAH_PO,
        COUNT(DISTINCT ITEM_ID) AS JUMLAH_ITEM,
        SUM(PO_QTY) AS TOTAL_QTY_PRODUKSI,
        SUM(LABEL_BAG) AS TOTAL_LABEL_BAG,
        SUM(LABEL_BOX) AS TOTAL_LABEL_BOX,

        SUM(CASE WHEN STD_PACK <= 1 THEN 1 ELSE 0 END) AS PO_BAG_TANPA_STD,
        SUM(CASE WHEN STD_PACK_BOX <= 1 THEN 1 ELSE 0 END) AS PO_BOX_TANPA_STD
    FROM HITUNG_PER_PO
    GROUP BY SUP_CODE, SUP_COMP, WARNA_LABEL
)
SELECT
    SUP_CODE,
    SUP_COMP,
    WARNA_LABEL,
    JUMLAH_PO,
    JUMLAH_ITEM,
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
$totalItem = 0;
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
    $totalItem += (int)$row['JUMLAH_ITEM'];
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
    <title>Report Kebutuhan Label Vendor</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; color: #25313c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .page { max-width: 1600px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .title h1 { margin: 0 0 4px; font-size: 23px; }
        .title p { margin: 0; color: #697784; }
        .button { display: inline-block; border: 1px solid #b9c2ca; border-radius: 5px; padding: 8px 12px; background: #ffffff; color: #25313c; text-decoration: none; cursor: pointer; font-size: 12px; }
        .button-primary { background: #2368a2; border-color: #2368a2; color: #ffffff; }
        .button-dark { background: #34495e; border-color: #34495e; color: #ffffff; }
        .filter-card, .report-card, .notice { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.06); }
        .filter-card { padding: 13px; margin-bottom: 14px; }
        .filter-row { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 10px; }
        .field label { display: block; margin-bottom: 5px; font-size: 11px; font-weight: bold; color: #586672; }
        .field select, .field input { height: 34px; border: 1px solid #bdc7d0; border-radius: 5px; padding: 6px 9px; background: #ffffff; color: #25313c; }
        .filter-note { margin-top: 9px; color: #73808c; font-size: 11px; }
        .summary-grid { display: grid; grid-template-columns: repeat(6, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .summary-card { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; padding: 12px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.05); }
        .summary-card .label { display: block; color: #71808d; font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 7px; }
        .summary-card .value { display: block; font-size: 21px; font-weight: bold; }
        .summary-card .unit { color: #73808c; font-size: 11px; margin-left: 3px; }
        .report-head { padding: 12px 14px; border-bottom: 1px solid #dce2e7; background: #fbfcfd; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1300px; border-collapse: collapse; }
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
        .badge-danger { background: #fbe2e2; color: #a52a2a; border: 1px solid #efbcbc; }
        .badge-ok { background: #e4f4e8; color: #27743b; border: 1px solid #bfe1c8; }
        .notice { padding: 13px; margin-bottom: 14px; }
        .notice-error { color: #8f2525; background: #fff5f5; border-color: #efcaca; }
        .notice-warning { color: #765400; background: #fff9e6; border-color: #eedb99; }
        .empty { padding: 28px !important; color: #788692; text-align: center; }
        .formula-note { padding: 11px 14px; background: #fbfcfd; border-top: 1px solid #dce2e7; color: #667582; font-size: 11px; line-height: 1.55; }
        @media print { body { background: #ffffff; padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="title">
            <h1>Report Kebutuhan Label Vendor</h1>
            <p>
                Periode <?php echo h_report(month_name_report($bulan)); ?>
                <?php echo h_report($tahun); ?> — 1 roll berisi
                <?php echo format_number_report($labelPerRoll); ?> label.
            </p>
        </div>
        <div class="no-print">
            <button type="button" class="button button-dark" onclick="window.print();">Print Report</button>
        </div>
    </div>

    <form method="get" action="hitung_label_vendor.php" class="filter-card no-print">
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
                <input type="text" name="cari" id="cari" style="width:280px;" value="<?php echo h_report($cari); ?>" placeholder="PO, vendor, item, warna">
            </div>
            <div>
                <button type="submit" class="button button-primary">Tampilkan</button>
                <a href="hitung_label_vendor.php" class="button">Reset</a>
            </div>
        </div>
        <div class="filter-note">Difilter berdasarkan Tanggal PO dan khusus Vendor (kode 'v%'). Dikelompokkan per Vendor & Warna.</div>
    </form>

    <?php if ($queryError !== null): ?>
        <div class="notice notice-error">
            <strong>Query report gagal.</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>

        <?php if ($totalPoBagTanpaStd > 0 || $totalPoBoxTanpaStd > 0): ?>
            <div class="notice notice-warning">
                Ada standard packing yang belum disetting (Nol atau 1):
                <strong><?php echo format_number_report($totalPoBagTanpaStd); ?> PO BAG</strong> dan 
                <strong><?php echo format_number_report($totalPoBoxTanpaStd); ?> PO BOX</strong>.
            </div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-card"><span class="label">Baris Data</span><span class="value"><?php echo format_number_report($totalBaris); ?></span></div>
            <div class="summary-card"><span class="label">Total PO</span><span class="value"><?php echo format_number_report($totalPo); ?></span></div>
            <div class="summary-card"><span class="label">Total Qty Vendor</span><span class="value"><?php echo format_number_report($totalProduksi); ?></span></div>
            <div class="summary-card"><span class="label">Total Label BAG</span><span class="value"><?php echo format_number_report($totalLabelBag); ?></span></div>
            <div class="summary-card"><span class="label">Roll BAG</span><span class="value"><?php echo format_number_report($totalRollBag); ?></span></div>
            <div class="summary-card"><span class="label">Roll BOX</span><span class="value"><?php echo format_number_report($totalRollBox); ?></span></div>
        </div>

        <div class="report-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">No.</th>
                            <th rowspan="2">Vendor Code</th>
                            <th rowspan="2">Vendor Name</th>
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
                            <tr><td colspan="14" class="empty">Tidak ada data.</td></tr>
                        <?php else: ?>
                            <?php $no = 0; foreach ($rows as $row): $no++; ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td class="text-left"><?php echo h_report($row['SUP_CODE']); ?></td>
                                    <td class="text-left"><strong><?php echo h_report($row['SUP_COMP']); ?></strong></td>
                                    
                                    <td class="text-left">
                                        <a href="detail_vendor.php?sup_code=<?php echo urlencode($row['SUP_CODE']); ?>&amp;warna=<?php echo urlencode($row['WARNA_LABEL']); ?>&amp;bulan=<?php echo h_report($bulan); ?>&amp;tahun=<?php echo h_report($tahun); ?>&amp;cari=<?php echo urlencode($cari); ?>" target="_blank" rel="noopener noreferrer" style="color: #2368a2; text-decoration: none; border-bottom: 1px dashed #2368a2;">
                                            <strong><?php echo h_report($row['WARNA_LABEL']); ?></strong>
                                        </a>
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
                                <td colspan="4" class="text-right">TOTAL</td>
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
            <div class="formula-note"><strong>Catatan:</strong> Data digrup per Vendor dan Warna. Apabila 1 Vendor memiliki pesanan dengan 2 warna label yang berbeda, akan muncul sebagai 2 baris terpisah agar perhitungan Roll (kebutuhan roll masing-masing warna) tetap akurat.</div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>