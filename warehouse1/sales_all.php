<?php
/**
 * LAPORAN BLOK VI - NILAI PRODUKSI / PENJUALAN TRIWULANAN
 * MULTI-SERVER: Gabungan Plant 1 & Plant 2
 * Sumber Data: DI, DIPA_PAR, PRICE, CURR_RAT
 * PHP 5.4 + SQLSRV + SQL Server 2008
 */

set_time_limit(120);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/global.php';

/* =========================================================
 * KONFIGURASI MULTI-SERVER
 * ========================================================= */
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "sa";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$dbName = "msData";

$servers_config = [
    'p1' => ['ip' => '192.168.0.4', 'label' => 'Plant 1'],
    'p2' => ['ip' => '192.168.0.9', 'label' => 'Plant 2'],
];

$conn_opts_base = [
    "Database" => $dbName, "Uid" => $uid, "PWD" => $pwd,
    "CharacterSet" => "UTF-8", "LoginTimeout" => 3, "Encrypt" => false
];

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function sap_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sap_number($value) {
    if ($value == 0) return '-'; 
    return number_format((float) $value, 2, ',', '.');
}

function sap_sql_error() {
    $errors = sqlsrv_errors();
    if (!is_array($errors)) return 'Kesalahan SQL Server tidak diketahui.';
    $messages = array();
    foreach ($errors as $error) {
        $messages[] = '[' . $error['SQLSTATE'] . '] ' . $error['code'] . ' - ' . $error['message'];
    }
    return implode("\n", $messages);
}

/* =========================================================
 * PARAMETER TAHUN
 * ========================================================= */
$tahun = isset($_GET['tahun']) && preg_match('/^\d{4}$/', $_GET['tahun']) 
    ? trim($_GET['tahun']) 
    : date('Y');

$queryError = [];
$reportData = [];

/* =========================================================
 * KODE SQL UTAMA (MENGAMBIL DATA SALES / PENJUALAN)
 * ========================================================= */
$sql = "
    SELECT 
        I.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, 
        ISNULL(TY.ITTY_CODE, '99') AS ITTY_CODE, 
        ISNULL(TY.ITTY_DESC, 'Lain-lain') AS ITTY_DESC,
        DATEPART(quarter, DI.DI_DATE) AS QUARTER_NO,
        
        -- TOTAL VOLUME PENJUALAN
        SUM(ISNULL(DIPA_PAR.QTY, 0)) AS VOL_JUAL,
        
        -- TOTAL NILAI PENJUALAN (DENGAN KONVERSI KURS JIKA BUKAN RUPIAH)
        SUM(
            ISNULL(DIPA_PAR.QTY, 0) * ISNULL(DIPA_PAR.PART_PRICE, 0) * 
            CASE 
                WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 
                ELSE ISNULL(RV.CURR_VRATE, 1) 
            END
        ) AS NILAI_JUAL

    FROM dbo.DI
    INNER JOIN dbo.DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
    INNER JOIN dbo.PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN dbo.ITEMS I ON PRICE.PART_ID = I.ITEM_ID
    LEFT JOIN dbo.ITTY TY ON I.ITTY_CODE = TY.ITTY_CODE
    LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
    LEFT JOIN dbo.CURR_RAT AS RV ON APV.CURR_CODE = RV.CURR_CODE
        AND DI.DI_DATE >= RV.CURR_SDATE
        AND (DI.DI_DATE <= RV.CURR_EDATE OR RV.CURR_EDATE IS NULL)
        
    WHERE YEAR(DI.DI_DATE) = ?
    GROUP BY 
        I.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, 
        TY.ITTY_CODE, TY.ITTY_DESC, 
        DATEPART(quarter, DI.DI_DATE)
";

/* =========================================================
 * EKSEKUSI MULTI-SERVER CONNECTION (LOOPING P1 & P2)
 * ========================================================= */
foreach ($servers_config as $plantKey => $srv) {
    $connMulti = @sqlsrv_connect($srv['ip'], $conn_opts_base);
    if (!$connMulti) {
        $queryError[] = "Gagal terhubung ke {$srv['label']} ({$srv['ip']})";
        continue;
    }

    $stmt = sqlsrv_query($connMulti, $sql, [$tahun]);
    if ($stmt === false) {
        $queryError[] = "Query gagal di {$srv['label']}: " . sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemId   = (int)$row['ITEM_ID'];
            $ittyCode = trim($row['ITTY_CODE']);
            $qtr      = (int)$row['QUARTER_NO'];
            
            $vol      = (float)$row['VOL_JUAL'];
            $nilai    = (float)$row['NILAI_JUAL'];

            if (!isset($reportData[$ittyCode])) {
                $reportData[$ittyCode] = ['desc' => trim($row['ITTY_DESC']), 'items' => []];
            }
            if (!isset($reportData[$ittyCode]['items'][$itemId])) {
                $reportData[$ittyCode]['items'][$itemId] = [
                    'code' => trim($row['ITEM_CODE']), 'name' => trim($row['ITEM_NAME']), 'unit' => trim($row['ITEM_UNIT']),
                    'q1_vol' => 0, 'q1_val' => 0,
                    'q2_vol' => 0, 'q2_val' => 0,
                    'q3_vol' => 0, 'q3_val' => 0,
                    'q4_vol' => 0, 'q4_val' => 0,
                ];
            }

            // Tambahkan nilai dari server ke data kumulatif
            if ($qtr >= 1 && $qtr <= 4) {
                $reportData[$ittyCode]['items'][$itemId]["q{$qtr}_vol"] += $vol;
                $reportData[$ittyCode]['items'][$itemId]["q{$qtr}_val"] += $nilai;
            }
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($connMulti);
}

ksort($reportData);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BLOK VI - Keterangan Nilai Produksi/Penjualan (Multi-Server)</title>
    <style>
        body { background: #e3e3e3; font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 0; }
        .toolbar { background: #ffffff; border-bottom: 1px solid #cccccc; padding: 12px; text-align: center; }
        .btn { background: #f8f8f8; border: 1px solid #aaaaaa; color: #111111; cursor: pointer; display: inline-block; margin: 0 4px; padding: 7px 14px; text-decoration: none; }
        .btn-primary { background: #007bff; color: #fff; border-color: #0056b3; }
        .page { background: #ffffff; box-shadow: 0 0 8px rgba(0,0,0,0.12); box-sizing: border-box; margin: 18px auto; min-height: 210mm; padding: 20px; width: 100%; max-width: 380mm; }
        .title-bar { background-color: #e6e6fa; border: 1px solid #888; padding: 5px; font-weight: bold; text-align: center; margin-bottom: 0; border-bottom: none; color: #5a5a8c; font-size: 12px; text-transform: uppercase; }
        .detail-table { border-collapse: collapse; width: 100%; border: 1px solid #888; }
        .detail-table th, .detail-table td { border: 1px solid #888; padding: 4px; }
        .detail-table th { background: #f2f2f2; font-size: 10px; text-align: center; color: #5a5a8c; font-weight: bold; vertical-align: middle; }
        .detail-table td { font-size: 10px; vertical-align: middle; height: 20px; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .col-num { background: #f2f2f2; text-align: center; font-size: 9px; color: #888; font-style: italic; }
        .section-header { background-color: #e0d8b0; font-weight: bold; }
        .danger { background: #fff0f0; border-left: 4px solid #d9534f; margin-bottom: 9px; padding: 8px 10px; color: #c00000; }
        .server-warn { font-size: 11px; background: #d1ecf1; color: #0c5460; padding: 6px; text-align: center; margin-bottom: 10px; border-radius: 4px; border: 1px solid #bee5eb; }
        
        @media print {
            @page { margin: 10mm; size: legal landscape; }
            body { background: #ffffff; }
            .toolbar, .server-warn { display: none; }
            .page { box-shadow: none; margin: 0; padding: 0; width: 100%; max-width: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <form method="GET" action="" style="margin: 0; display: inline-block;">
        <label for="tahun" style="margin-left: 10px;"><strong>Pilih Tahun:</strong></label>
        <select id="tahun" name="tahun" style="padding: 5px; margin-left: 5px;">
            <?php 
            $startYear = date('Y') - 5;
            $endYear = date('Y') + 1;
            for ($y = $startYear; $y <= $endYear; $y++) {
                $selected = ($y == $tahun) ? 'selected' : '';
                echo "<option value=\"$y\" $selected>$y</option>";
            }
            ?>
        </select>
        <button type="submit" class="btn btn-primary" style="margin-left: 8px;">Tampilkan</button>
    </form>
    <button class="btn" onclick="window.print();" style="float: right;">Print Format BPS</button>
</div>

<?php if (count($queryError) > 0) { ?>
    <div class="danger" style="margin:20px;">
        <strong>Informasi Koneksi Server:</strong>
        <pre><?php echo sap_h(implode("\n", $queryError)); ?></pre>
    </div>
<?php } ?>

<div class="page">
    <div class="title-bar">
        BLOK VI. KETERANGAN NILAI PRODUKSI/PENJUALAN (PRODUK BARANG DAN KEKAYAAN INTELEKTUAL) TRIWULANAN, <?php echo sap_h($tahun); ?>
    </div>
    
    <div class="server-warn">
        <strong>Info Data:</strong> Penjualan (DI) adalah gabungan transaksi dari Plant 1 & Plant 2. Harga Rata-rata dihitung otomatis dari (Nilai Penjualan ÷ Volume Penjualan).
    </div>

    <table class="detail-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">No.</th>
                <th rowspan="2" style="width: 150px;">Nama<br>Komoditas</th>
                <th rowspan="2" style="width: 70px;">KBKI 2015<br>(5 Digit)</th>
                <th rowspan="2" style="width: 50px;">Kode<br>Satuan<br>Volume</th>
                
                <th colspan="3">Triwulan I - <?php echo sap_h($tahun); ?></th>
                <th colspan="3">Triwulan II - <?php echo sap_h($tahun); ?></th>
                <th colspan="3">Triwulan III - <?php echo sap_h($tahun); ?></th>
                <th colspan="3">Triwulan IV - <?php echo sap_h($tahun); ?></th>
            </tr>
            <tr>
                <!-- Triwulan 1 -->
                <th style="width: 60px;">Volume<br>Produksi/<br>Penjualan</th>
                <th style="width: 80px;">Nilai<br>Produksi/<br>Penjualan (Rp)</th>
                <th style="width: 80px;">Harga Rata-rata<br>per Satuan<br>Volume (Rp)</th>
                
                <!-- Triwulan 2 -->
                <th style="width: 60px;">Volume<br>Produksi/<br>Penjualan</th>
                <th style="width: 80px;">Nilai<br>Produksi/<br>Penjualan (Rp)</th>
                <th style="width: 80px;">Harga Rata-rata<br>per Satuan<br>Volume (Rp)</th>

                <!-- Triwulan 3 -->
                <th style="width: 60px;">Volume<br>Produksi/<br>Penjualan</th>
                <th style="width: 80px;">Nilai<br>Produksi/<br>Penjualan (Rp)</th>
                <th style="width: 80px;">Harga Rata-rata<br>per Satuan<br>Volume (Rp)</th>

                <!-- Triwulan 4 -->
                <th style="width: 60px;">Volume<br>Produksi/<br>Penjualan</th>
                <th style="width: 80px;">Nilai<br>Produksi/<br>Penjualan (Rp)</th>
                <th style="width: 80px;">Harga Rata-rata<br>per Satuan<br>Volume (Rp)</th>
            </tr>
            <!-- Penomoran Kolom (Format BPS) -->
            <tr>
                <td class="col-num">(1)</td><td class="col-num">(2)</td><td class="col-num">(3)</td><td class="col-num">(4)</td>
                <td class="col-num">(5)</td><td class="col-num">(6)</td><td class="col-num">(7)</td>
                <td class="col-num">(8)</td><td class="col-num">(9)</td><td class="col-num">(10)</td>
                <td class="col-num">(11)</td><td class="col-num">(12)</td><td class="col-num">(13)</td>
                <td class="col-num">(14)</td><td class="col-num">(15)</td><td class="col-num">(16)</td>
            </tr>
        </thead>
        <tbody>
            <?php 
            $groupIndex = 0; 
            $grandTotalQ1 = 0; $grandTotalQ2 = 0; $grandTotalQ3 = 0; $grandTotalQ4 = 0;

            foreach ($reportData as $ittyCode => $ittyGroup) { 
                $abjadGrup = chr(65 + $groupIndex); 
                $groupIndex++;

                $subTotalQ1 = 0; $subTotalQ2 = 0; $subTotalQ3 = 0; $subTotalQ4 = 0;
            ?>
                <!-- Header Kategori / Grup ITTY -->
                <tr class="section-header">
                    <td class="center"><?php echo $abjadGrup; ?>.</td>
                    <td colspan="15"><?php echo sap_h($ittyGroup['desc']); ?> (Kategori: <?php echo sap_h($ittyCode); ?>)</td>
                </tr>

                <?php 
                $no = 1;
                foreach ($ittyGroup['items'] as $itemId => $data) { 
                    
                    // Kalkulasi Harga Rata-Rata = Nilai / Volume
                    $hargaQ1 = ($data['q1_vol'] > 0) ? ($data['q1_val'] / $data['q1_vol']) : 0;
                    $hargaQ2 = ($data['q2_vol'] > 0) ? ($data['q2_val'] / $data['q2_vol']) : 0;
                    $hargaQ3 = ($data['q3_vol'] > 0) ? ($data['q3_val'] / $data['q3_vol']) : 0;
                    $hargaQ4 = ($data['q4_vol'] > 0) ? ($data['q4_val'] / $data['q4_vol']) : 0;

                    $subTotalQ1 += $data['q1_val'];
                    $subTotalQ2 += $data['q2_val'];
                    $subTotalQ3 += $data['q3_val'];
                    $subTotalQ4 += $data['q4_val'];
                ?>
                <tr>
                    <td class="center"><?php echo $no++; ?>.</td>
                    <td><?php echo sap_h($data['name']); ?></td>
                    <td class="center"><?php echo sap_h($data['code']); ?></td>
                    <td class="center"><?php echo sap_h($data['unit']); ?></td>
                    
                    <!-- Q1 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q1_vol'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($data['q1_val'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($hargaQ1)); ?></td>

                    <!-- Q2 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q2_vol'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($data['q2_val'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($hargaQ2)); ?></td>

                    <!-- Q3 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q3_vol'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($data['q3_val'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($hargaQ3)); ?></td>

                    <!-- Q4 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q4_vol'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($data['q4_val'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($hargaQ4)); ?></td>
                </tr>
                <?php } ?>

                <!-- Baris Total Nilai Sales per Kategori -->
                <tr class="section-header">
                    <td colspan="4" class="center">Total Nilai Penjualan <?php echo $abjadGrup; ?></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($subTotalQ1)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($subTotalQ2)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($subTotalQ3)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($subTotalQ4)); ?></td>
                    <td style="background-color: #888;"></td>
                </tr>

                <?php 
                $grandTotalQ1 += $subTotalQ1;
                $grandTotalQ2 += $subTotalQ2;
                $grandTotalQ3 += $subTotalQ3;
                $grandTotalQ4 += $subTotalQ4;
            } 
            ?>

            <?php if (count($reportData) == 0) { ?>
                <tr>
                    <td colspan="16" class="center">Tidak ada transaksi penjualan (Sales) yang ditemukan dari kedua server pada tahun <?php echo sap_h($tahun); ?>.</td>
                </tr>
            <?php } else { ?>
                <!-- Baris Grand Total (Keseluruhan) -->
                <tr class="section-header" style="background-color: #a3c4f3; color: #1a365d;">
                    <td colspan="4" class="center">TOTAL KESELURUHAN NILAI PENJUALAN TAHUN <?php echo sap_h($tahun); ?></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($grandTotalQ1)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($grandTotalQ2)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($grandTotalQ3)); ?></td>
                    <td style="background-color: #888;"></td>
                    <td style="background-color: #888;"></td>
                    <td class="number"><?php echo sap_h(sap_number($grandTotalQ4)); ?></td>
                    <td style="background-color: #888;"></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

</body>
</html>