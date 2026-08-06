<?php
// /msii/ppic/packaging_vendor.php
// PHP 5.4 + SQL Server (sqlsrv)
// Report kebutuhan packaging (Polybag dan Box) untuk Vendor (Group by Vendor).

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
        )
    ";

    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
WITH DATA_PACKING AS
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
        ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX

    FROM dbo.PO PO
    INNER JOIN dbo.PO_DETAIL POD ON PO.PO_ID = POD.PO_ID
    INNER JOIN dbo.SUPPLIER SUP ON PO.SUP_ID = SUP.SUP_ID
    INNER JOIN dbo.ITEMS I ON POD.ITEM_ID = I.ITEM_ID

    OUTER APPLY
    (
        SELECT TOP 1 SP2.PACK_ID, SP2.STD_PACK, SP2.STD_PACK_BOX
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
        PO_ID, PO_NUM, ITEM_ID, ITEM_CODE, ITEM_NAME, SUP_CODE, SUP_COMP, PO_QTY, STD_PACK, STD_PACK_BOX,

        -- Kebutuhan Polybag = PO QTY / STD PACK
        CASE
            WHEN STD_PACK > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS KEBUTUHAN_POLYBAG,

        -- Kebutuhan Box = PO QTY / STD PACK BOX
        CASE
            WHEN STD_PACK_BOX > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK_BOX AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS KEBUTUHAN_BOX
    FROM DATA_PACKING
),
TOTAL_PER_VENDOR AS
(
    SELECT
        SUP_CODE,
        SUP_COMP,
        COUNT(DISTINCT PO_ID) AS JUMLAH_PO,
        COUNT(DISTINCT ITEM_ID) AS JUMLAH_ITEM,
        SUM(PO_QTY) AS TOTAL_QTY_PRODUKSI,
        SUM(KEBUTUHAN_POLYBAG) AS TOTAL_POLYBAG,
        SUM(KEBUTUHAN_BOX) AS TOTAL_BOX,

        SUM(CASE WHEN STD_PACK <= 1 THEN 1 ELSE 0 END) AS PO_BAG_TANPA_STD,
        SUM(CASE WHEN STD_PACK_BOX <= 1 THEN 1 ELSE 0 END) AS PO_BOX_TANPA_STD
    FROM HITUNG_PER_PO
    GROUP BY SUP_CODE, SUP_COMP
)
SELECT
    SUP_CODE,
    SUP_COMP,
    JUMLAH_PO,
    JUMLAH_ITEM,
    TOTAL_QTY_PRODUKSI,
    TOTAL_POLYBAG,
    TOTAL_BOX,
    PO_BAG_TANPA_STD,
    PO_BOX_TANPA_STD
FROM TOTAL_PER_VENDOR
ORDER BY SUP_CODE ASC
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

$totalVendor = count($rows);
$totalPo = 0;
$totalItem = 0;
$totalProduksi = 0;
$grandTotalPolybag = 0;
$grandTotalBox = 0;
$totalPoBagTanpaStd = 0;
$totalPoBoxTanpaStd = 0;

foreach ($rows as $row) {
    $totalPo += (int)$row['JUMLAH_PO'];
    $totalItem += (int)$row['JUMLAH_ITEM'];
    $totalProduksi += (float)$row['TOTAL_QTY_PRODUKSI'];
    $grandTotalPolybag += (int)$row['TOTAL_POLYBAG'];
    $grandTotalBox += (int)$row['TOTAL_BOX'];
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
    <title>Report Kebutuhan Packaging Vendor</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; color: #25313c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .page { max-width: 1300px; margin: 0 auto; }
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
        .summary-grid { display: grid; grid-template-columns: repeat(5, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .summary-card { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; padding: 12px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.05); }
        .summary-card .label { display: block; color: #71808d; font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 7px; }
        .summary-card .value { display: block; font-size: 21px; font-weight: bold; }
        .summary-card .unit { color: #73808c; font-size: 11px; margin-left: 3px; }
        .report-head { padding: 12px 14px; border-bottom: 1px solid #dce2e7; background: #fbfcfd; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-right: 1px solid #dce2e7; border-bottom: 1px solid #dce2e7; padding: 8px 7px; vertical-align: middle; }
        th:last-child, td:last-child { border-right: 0; }
        thead th { background: #34495e; color: #ffffff; text-align: center; font-size: 11px; line-height: 1.25; }
        tbody tr:nth-child(even) { background: #fafbfc; }
        tbody tr:hover { background: #f1f7fb; }
        tfoot td { background: #eaf0f5; font-weight: bold; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .qty-value { font-size: 14px; font-weight: bold; color: #174f7a; }
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
            <h1>Report Kebutuhan Packaging Vendor</h1>
            <p>Periode <?php echo h_report(month_name_report($bulan)); ?> <?php echo h_report($tahun); ?></p>
        </div>
        <div class="no-print">
            <button type="button" class="button button-dark" onclick="window.print();">Print Report</button>
        </div>
    </div>

    <form method="get" action="packaging_vendor.php" class="filter-card no-print">
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
                <input type="text" name="cari" id="cari" style="width:280px;" value="<?php echo h_report($cari); ?>" placeholder="PO, vendor, atau item">
            </div>
            <div>
                <button type="submit" class="button button-primary">Tampilkan</button>
                <a href="packaging_vendor.php" class="button">Reset</a>
            </div>
        </div>
        <div class="filter-note">Difilter berdasarkan Tanggal PO dan khusus Vendor (kode 'v%'). Data direkap per Vendor.</div>
    </form>

    <?php if ($queryError !== null): ?>
        <div class="notice notice-error">
            <strong>Query report gagal.</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>

        <?php if ($totalPoBagTanpaStd > 0 || $totalPoBoxTanpaStd > 0): ?>
            <div class="notice notice-warning">
                Ada standard packing yang belum disetting (Nol atau 1):
                <strong><?php echo format_number_report($totalPoBagTanpaStd); ?> PO (tanpa Std Bag)</strong> dan 
                <strong><?php echo format_number_report($totalPoBoxTanpaStd); ?> PO (tanpa Std Box)</strong>.
                Sistem tidak akan menghitung kebutuhan packaging pada baris PO tersebut.
            </div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-card"><span class="label">Jumlah Vendor</span><span class="value"><?php echo format_number_report($totalVendor); ?></span></div>
            <div class="summary-card"><span class="label">Total PO</span><span class="value"><?php echo format_number_report($totalPo); ?></span></div>
            <div class="summary-card"><span class="label">Total Qty Vendor</span><span class="value"><?php echo format_number_report($totalProduksi); ?><span class="unit">pcs</span></span></div>
            <div class="summary-card"><span class="label">Total Polybag</span><span class="value"><?php echo format_number_report($grandTotalPolybag); ?><span class="unit">pcs</span></span></div>
            <div class="summary-card"><span class="label">Total Box</span><span class="value"><?php echo format_number_report($grandTotalBox); ?><span class="unit">pcs</span></span></div>
        </div>

        <div class="report-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Vendor Code</th>
                            <th>Vendor Name</th>
                            <th>Jml Item</th>
                            <th>Jml PO</th>
                            <th>Qty PO Vendor</th>
                            <th>Kebutuhan<br>Polybag</th>
                            <th>Kebutuhan<br>Box</th>
                            <th>PO Tanpa<br>Std Bag</th>
                            <th>PO Tanpa<br>Std Box</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalVendor === 0): ?>
                            <tr><td colspan="10" class="empty">Tidak ada data.</td></tr>
                        <?php else: ?>
                            <?php $no = 0; foreach ($rows as $row): $no++; ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td class="text-left"><?php echo h_report($row['SUP_CODE']); ?></td>
                                  <td class="text-left">
    <a href="packaging_detail.php?sup_code=<?php echo urlencode($row['SUP_CODE']); ?>&amp;bulan=<?php echo h_report($bulan); ?>&amp;tahun=<?php echo h_report($tahun); ?>&amp;cari=<?php echo urlencode($cari); ?>" target="_blank" rel="noopener noreferrer" style="color: #2368a2; text-decoration: none; border-bottom: 1px dashed #2368a2;">
        <strong><?php echo h_report($row['SUP_COMP']); ?></strong>
    </a>
</td>
                                    
                                    <td class="text-right"><?php echo format_number_report($row['JUMLAH_ITEM']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['JUMLAH_PO']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_QTY_PRODUKSI']); ?></td>

                                    <td class="text-right qty-value"><?php echo format_number_report($row['TOTAL_POLYBAG']); ?></td>
                                    <td class="text-right qty-value"><?php echo format_number_report($row['TOTAL_BOX']); ?></td>
                                    
                                    <td class="text-center"><?php echo ((int)$row['PO_BAG_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BAG_TANPA_STD']).'</span>' : '<span class="badge badge-ok">0</span>'; ?></td>
                                    <td class="text-center"><?php echo ((int)$row['PO_BOX_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BOX_TANPA_STD']).'</span>' : '<span class="badge badge-ok">0</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($totalVendor > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right">GRAND TOTAL</td>
                                <td class="text-right"><?php echo format_number_report($totalItem); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalPo); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalProduksi); ?></td>
                                <td class="text-right qty-value"><?php echo format_number_report($grandTotalPolybag); ?></td>
                                <td class="text-right qty-value"><?php echo format_number_report($grandTotalBox); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalPoBagTanpaStd); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalPoBoxTanpaStd); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <div class="formula-note">
                <strong>Rumus Perhitungan:</strong><br>
                1. Kebutuhan Polybag dihitung per PO terlebih dahulu = Pembulatan Ke Atas (PO_QTY / STD_PACK), lalu dijumlahkan per Vendor.<br>
                2. Kebutuhan Box dihitung per PO terlebih dahulu = Pembulatan Ke Atas (PO_QTY / STD_PACK_BOX), lalu dijumlahkan per Vendor.<br>
                <em>*Data dikelompokkan secara keseluruhan berdasarkan Vendor.</em>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var bulan = document.getElementById('bulan');
    var tahun = document.getElementById('tahun');

    if (bulan) {
        bulan.onchange = function () {
            this.form.submit();
        };
    }

    if (tahun) {
        tahun.onkeyup = function (event) {
            event = event || window.event;
            if (event.keyCode === 13) {
                this.form.submit();
            }
        };
    }
})();
</script>
</body>
</html>