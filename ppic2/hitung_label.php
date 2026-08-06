<?php
// /msii/ppic/hitung_label.php
// PHP 5.4 + SQL Server (sqlsrv)
// Report kebutuhan roll label BAG dan BOX per warna setiap bulan.

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
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
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
               WO.WO_NUMBER LIKE ?
            OR I.ITEM_CODE LIKE ?
            OR I.ITEM_NO LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR ISNULL(SP.WARNA_LABEL, '') LIKE ?
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
WITH DATA_LABEL AS
(
    SELECT
        WO.WO_ID,
        WO.WO_NUMBER,
        WO.WO_MMYY,
        ISNULL(WO.WO_QTY, 0) AS WO_QTY,
        I.ITEM_ID,
        I.ITEM_CODE,
        I.ITEM_NO,
        I.ITEM_NAME,

        CASE
            WHEN LWS.ID IS NULL
                THEN ISNULL(SP.STD_PACK, 0)
            ELSE ISNULL(LWS.STD_PACK, 0)
        END AS STD_PACK,

        CASE
            WHEN LWS.ID IS NULL
                THEN ISNULL(SP.STD_PACK_BOX, 0)
            ELSE ISNULL(LWS.STD_PACK_BOX, 0)
        END AS STD_PACK_BOX,

        CASE
            WHEN LTRIM(RTRIM(ISNULL(SP.WARNA_LABEL, ''))) = ''
                THEN 'TANPA WARNA'
            ELSE UPPER(LTRIM(RTRIM(SP.WARNA_LABEL)))
        END AS WARNA_LABEL

    FROM dbo.WO WO

    INNER JOIN dbo.ITEMS I
        ON I.ITEM_ID = WO.ITEM_ID

    OUTER APPLY
    (
        SELECT TOP 1
            SP2.PACK_ID,
            SP2.STD_PACK,
            SP2.STD_PACK_BOX,
            SP2.WARNA_LABEL
        FROM dbo.STD_PACK SP2
        WHERE SP2.ITEM_CODE = I.ITEM_CODE
        ORDER BY SP2.PACK_ID DESC
    ) SP

    OUTER APPLY
    (
        SELECT TOP 1
            LWS2.ID,
            LWS2.STD_PACK,
            LWS2.STD_PACK_BOX
        FROM dbo.LABEL_WO_SETTING LWS2
        WHERE LWS2.WO_NUMBER = WO.WO_NUMBER
          AND LWS2.ITEM_CODE = I.ITEM_CODE
        ORDER BY LWS2.ID DESC
    ) LWS

    WHERE WO.WO_MMYY >= ?
      AND WO.WO_MMYY < ?
      $whereCari
),
HITUNG_PER_WO AS
(
    SELECT
        WO_ID,
        WO_NUMBER,
        ITEM_ID,
        ITEM_CODE,
        ITEM_NO,
        ITEM_NAME,
        WARNA_LABEL,
        WO_QTY,
        STD_PACK,
        STD_PACK_BOX,

        CASE
            WHEN STD_PACK > 1 AND WO_QTY > 0 THEN
                CAST(
                    CEILING(
                        CAST(WO_QTY AS DECIMAL(18, 4)) /
                        CAST(STD_PACK AS DECIMAL(18, 4))
                    ) AS BIGINT
                )
            ELSE 0
        END AS LABEL_BAG,

        CASE
            WHEN STD_PACK_BOX > 1 AND WO_QTY > 0 THEN
                CAST(
                    CEILING(
                        CAST(WO_QTY AS DECIMAL(18, 4)) /
                        CAST(STD_PACK_BOX AS DECIMAL(18, 4))
                    ) AS BIGINT
                )
            ELSE 0
        END AS LABEL_BOX

    FROM DATA_LABEL
),
TOTAL_PER_WARNA AS
(
    SELECT
        WARNA_LABEL,
        COUNT(DISTINCT WO_ID) AS JUMLAH_WO,
        COUNT(DISTINCT ITEM_ID) AS JUMLAH_ITEM,
        SUM(WO_QTY) AS TOTAL_QTY_PRODUKSI,
        SUM(LABEL_BAG) AS TOTAL_LABEL_BAG,
        SUM(LABEL_BOX) AS TOTAL_LABEL_BOX,

        SUM(
            CASE
                WHEN STD_PACK <= 1 THEN 1
                ELSE 0
            END
        ) AS WO_BAG_TANPA_STD,

        SUM(
            CASE
                WHEN STD_PACK_BOX <= 1 THEN 1
                ELSE 0
            END
        ) AS WO_BOX_TANPA_STD

    FROM HITUNG_PER_WO
    GROUP BY WARNA_LABEL
)
SELECT
    WARNA_LABEL,
    JUMLAH_WO,
    JUMLAH_ITEM,
    TOTAL_QTY_PRODUKSI,
    TOTAL_LABEL_BAG,

    CASE
        WHEN TOTAL_LABEL_BAG > 0 THEN
            CAST(
                CEILING(
                    CAST(TOTAL_LABEL_BAG AS DECIMAL(18, 4)) / $labelPerRoll
                ) AS BIGINT
            )
        ELSE 0
    END AS TOTAL_ROLL_BAG,

    CASE
        WHEN TOTAL_LABEL_BAG > 0 THEN
            (
                CAST(
                    CEILING(
                        CAST(TOTAL_LABEL_BAG AS DECIMAL(18, 4)) / $labelPerRoll
                    ) AS BIGINT
                ) * $labelPerRoll
            ) - TOTAL_LABEL_BAG
        ELSE 0
    END AS SISA_KAPASITAS_BAG,

    TOTAL_LABEL_BOX,

    CASE
        WHEN TOTAL_LABEL_BOX > 0 THEN
            CAST(
                CEILING(
                    CAST(TOTAL_LABEL_BOX AS DECIMAL(18, 4)) / $labelPerRoll
                ) AS BIGINT
            )
        ELSE 0
    END AS TOTAL_ROLL_BOX,

    CASE
        WHEN TOTAL_LABEL_BOX > 0 THEN
            (
                CAST(
                    CEILING(
                        CAST(TOTAL_LABEL_BOX AS DECIMAL(18, 4)) / $labelPerRoll
                    ) AS BIGINT
                ) * $labelPerRoll
            ) - TOTAL_LABEL_BOX
        ELSE 0
    END AS SISA_KAPASITAS_BOX,

    WO_BAG_TANPA_STD,
    WO_BOX_TANPA_STD

FROM TOTAL_PER_WARNA
ORDER BY
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

$totalWarna = count($rows);
$totalWo = 0;
$totalItem = 0;
$totalProduksi = 0;
$totalLabelBag = 0;
$totalRollBag = 0;
$totalSisaBag = 0;
$totalLabelBox = 0;
$totalRollBox = 0;
$totalSisaBox = 0;
$totalWoBagTanpaStd = 0;
$totalWoBoxTanpaStd = 0;

foreach ($rows as $row) {
    $totalWo += (int)$row['JUMLAH_WO'];
    $totalItem += (int)$row['JUMLAH_ITEM'];
    $totalProduksi += (float)$row['TOTAL_QTY_PRODUKSI'];
    $totalLabelBag += (int)$row['TOTAL_LABEL_BAG'];
    $totalRollBag += (int)$row['TOTAL_ROLL_BAG'];
    $totalSisaBag += (int)$row['SISA_KAPASITAS_BAG'];
    $totalLabelBox += (int)$row['TOTAL_LABEL_BOX'];
    $totalRollBox += (int)$row['TOTAL_ROLL_BOX'];
    $totalSisaBox += (int)$row['SISA_KAPASITAS_BOX'];
    $totalWoBagTanpaStd += (int)$row['WO_BAG_TANPA_STD'];
    $totalWoBoxTanpaStd += (int)$row['WO_BOX_TANPA_STD'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Kebutuhan Roll Label</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; color: #25313c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .page { max-width: 1500px; margin: 0 auto; }
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
        .field-month select { width: 150px; }
        .field-year input { width: 90px; }
        .field-search input { width: 280px; }
        .filter-note { margin-top: 9px; color: #73808c; font-size: 11px; }
        .summary-grid { display: grid; grid-template-columns: repeat(6, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .summary-card { background: #ffffff; border: 1px solid #dce2e7; border-radius: 8px; padding: 12px; box-shadow: 0 3px 12px rgba(24, 39, 51, 0.05); }
        .summary-card .label { display: block; color: #71808d; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 7px; }
        .summary-card .value { display: block; font-size: 21px; font-weight: bold; }
        .summary-card .unit { color: #73808c; font-size: 11px; margin-left: 3px; }
        .report-card { overflow: hidden; }
        .report-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #dce2e7; background: #fbfcfd; }
        .report-head strong { font-size: 14px; }
        .report-head span { color: #70808d; font-size: 11px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1160px; border-collapse: collapse; }
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
        .empty { padding: 28px !important; color: #788692; }
        .formula-note { padding: 11px 14px; background: #fbfcfd; border-top: 1px solid #dce2e7; color: #667582; font-size: 11px; line-height: 1.55; }
        @media (max-width: 1100px) { .summary-grid { grid-template-columns: repeat(3, minmax(140px, 1fr)); } }
        @media (max-width: 700px) { body { padding: 10px; } .topbar, .report-head { align-items: flex-start; flex-direction: column; } .summary-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); } .field, .field select, .field input, .field-search input { width: 100%; } .filter-row { display: block; } .field { margin-bottom: 9px; } }
        @media print { body { background: #ffffff; padding: 0; font-size: 10px; } .page { max-width: none; } .no-print { display: none !important; } .summary-grid { grid-template-columns: repeat(6, 1fr); } .filter-card, .report-card, .summary-card, .notice { box-shadow: none; } table { min-width: 0; } }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="title">
            <h1>Report Kebutuhan Roll Label</h1>
            <p>
                Periode <?php echo h_report(month_name_report($bulan)); ?>
                <?php echo h_report($tahun); ?> — 1 roll berisi
                <?php echo format_number_report($labelPerRoll); ?> label.
            </p>
        </div>

        <div class="no-print">
            <a href="label_plant2.php?bulan=<?php echo h_report($bulan); ?>&amp;tahun=<?php echo h_report($tahun); ?>"
               class="button">
                Kembali ke Label
            </a>
            <button type="button" class="button button-dark" onclick="window.print();">
                Print Report
            </button>
        </div>
    </div>

    <form method="get" action="hitung_label.php" class="filter-card no-print">
        <div class="filter-row">
            <div class="field field-month">
                <label for="bulan">Bulan</label>
                <select name="bulan" id="bulan">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($bulan === $i) ? 'selected' : ''; ?>>
                            <?php echo h_report(month_name_report($i)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="field field-year">
                <label for="tahun">Tahun</label>
                <input type="text"
                       name="tahun"
                       id="tahun"
                       maxlength="4"
                       value="<?php echo h_report($tahun); ?>">
            </div>

            <div class="field field-search">
                <label for="cari">Cari</label>
                <input type="text"
                       name="cari"
                       id="cari"
                       value="<?php echo h_report($cari); ?>"
                       placeholder="WO, item, atau warna label">
            </div>

            <div>
                <button type="submit" class="button button-primary">Tampilkan</button>
                <a href="hitung_label.php" class="button">Reset</a>
            </div>
        </div>

        <div class="filter-note">
            Kebutuhan label dihitung per WO terlebih dahulu, kemudian dijumlahkan per warna.
            Roll BAG dan BOX dihitung terpisah.
        </div>
    </form>

    <?php if ($queryError !== null): ?>
        <div class="notice notice-error">
            <strong>Query report gagal.</strong>
            <pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>

        <?php if ($totalWoBagTanpaStd > 0 || $totalWoBoxTanpaStd > 0): ?>
            <div class="notice notice-warning">
                Ada data standard packing yang belum disetting (Nol atau 1):
                <strong><?php echo format_number_report($totalWoBagTanpaStd); ?> WO BAG</strong>
                dan
                <strong><?php echo format_number_report($totalWoBoxTanpaStd); ?> WO BOX</strong>.
                Baris tersebut tidak menambah kebutuhan label pada jenis yang standard-nya <= 1.
            </div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-card">
                <span class="label">Jumlah Warna</span>
                <span class="value">
                    <?php echo format_number_report($totalWarna); ?>
                    <span class="unit">warna</span>
                </span>
            </div>

            <div class="summary-card">
                <span class="label">Jumlah WO</span>
                <span class="value">
                    <?php echo format_number_report($totalWo); ?>
                    <span class="unit">WO</span>
                </span>
            </div>

            <div class="summary-card">
                <span class="label">Total Produksi</span>
                <span class="value">
                    <?php echo format_number_report($totalProduksi); ?>
                    <span class="unit">pcs</span>
                </span>
            </div>

            <div class="summary-card">
                <span class="label">Label BAG</span>
                <span class="value">
                    <?php echo format_number_report($totalLabelBag); ?>
                    <span class="unit">label</span>
                </span>
            </div>

            <div class="summary-card">
                <span class="label">Roll BAG</span>
                <span class="value">
                    <?php echo format_number_report($totalRollBag); ?>
                    <span class="unit">roll</span>
                </span>
            </div>

            <div class="summary-card">
                <span class="label">Roll BOX</span>
                <span class="value">
                    <?php echo format_number_report($totalRollBox); ?>
                    <span class="unit">roll</span>
                </span>
            </div>
        </div>

        <div class="report-card">
            <div class="report-head">
                <strong>
                    Kebutuhan Roll per Warna —
                    <?php echo h_report(month_name_report($bulan)); ?>
                    <?php echo h_report($tahun); ?>
                </strong>
                <span>
                    Roll dihitung per warna, sehingga roll berbeda warna tidak digabung.
                </span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">No.</th>
                            <th rowspan="2">Warna Label</th>
                            <th rowspan="2">WO</th>
                            <th rowspan="2">Item</th>
                            <th rowspan="2">Qty Produksi</th>
                            <th colspan="3">BAG</th>
                            <th colspan="3">BOX</th>
                            <th colspan="2">Standard Kosong</th>
                        </tr>
                        <tr>
                            <th>Label</th>
                            <th>Roll</th>
                            <th>Sisa Kapasitas</th>
                            <th>Label</th>
                            <th>Roll</th>
                            <th>Sisa Kapasitas</th>
                            <th>BAG</th>
                            <th>BOX</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr>
                                <td colspan="13" class="text-center empty">
                                    Tidak ada data untuk periode dan pencarian tersebut.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 0; ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $no++; ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    
                                    <td class="text-left">
                                        <a href="detail_warna.php?warna=<?php echo urlencode($row['WARNA_LABEL']); ?>&amp;bulan=<?php echo h_report($bulan); ?>&amp;tahun=<?php echo h_report($tahun); ?>&amp;cari=<?php echo urlencode($cari); ?>" target="_blank" rel="noopener noreferrer" style="color: #2368a2; text-decoration: none; border-bottom: 1px dashed #2368a2;">
                                            <strong><?php echo h_report($row['WARNA_LABEL']); ?></strong>
                                        </a>
                                    </td>

                                    <td class="text-right"><?php echo format_number_report($row['JUMLAH_WO']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['JUMLAH_ITEM']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_QTY_PRODUKSI']); ?></td>

                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_LABEL_BAG']); ?></td>
                                    <td class="text-right roll-value"><?php echo format_number_report($row['TOTAL_ROLL_BAG']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['SISA_KAPASITAS_BAG']); ?></td>

                                    <td class="text-right"><?php echo format_number_report($row['TOTAL_LABEL_BOX']); ?></td>
                                    <td class="text-right roll-value"><?php echo format_number_report($row['TOTAL_ROLL_BOX']); ?></td>
                                    <td class="text-right"><?php echo format_number_report($row['SISA_KAPASITAS_BOX']); ?></td>

                                    <td class="text-center">
                                        <?php if ((int)$row['WO_BAG_TANPA_STD'] > 0): ?>
                                            <span class="badge badge-danger">
                                                <?php echo format_number_report($row['WO_BAG_TANPA_STD']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-ok">0</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ((int)$row['WO_BOX_TANPA_STD'] > 0): ?>
                                            <span class="badge badge-danger">
                                                <?php echo format_number_report($row['WO_BOX_TANPA_STD']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-ok">0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                    <?php if (count($rows) > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-right">TOTAL</td>
                                <td class="text-right"><?php echo format_number_report($totalWo); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalItem); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalProduksi); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBag); ?></td>
                                <td class="text-right roll-value"><?php echo format_number_report($totalRollBag); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalSisaBag); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalLabelBox); ?></td>
                                <td class="text-right roll-value"><?php echo format_number_report($totalRollBox); ?></td>
                                <td class="text-right"><?php echo format_number_report($totalSisaBox); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalWoBagTanpaStd); ?></td>
                                <td class="text-center"><?php echo format_number_report($totalWoBoxTanpaStd); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <div class="formula-note">
                <strong>Rumus:</strong>
                Label BAG per WO = pembulatan ke atas WO Qty / STD_PACK.
                Label BOX per WO = pembulatan ke atas WO Qty / STD_PACK_BOX.
                Roll per warna = pembulatan ke atas total label warna / <?php echo format_number_report($labelPerRoll); ?>.
                Sisa kapasitas adalah jumlah slot label yang belum terpakai pada roll terakhir.
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