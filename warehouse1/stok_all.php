<?php
/**
 * LAPORAN KETERANGAN POSISI INVENTORI/PERSEDIAAN AKHIR TRIWULAN (BLOK V BPS)
 * MULTI-SERVER: Gabungan Plant 1 & Plant 2
 * Logika Harga : ITTY 01 (PRICE_DETAIL), ITTY 02,03,05 (PO_DETAIL)
 * Kurs USD     : Otomatis Konversi via CURR_RAT_TALLY
 * Prioritas    : Harga Plant 2, jika 0 maka ambil Plant 1
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
 * PARAMETER TANGGAL PENCACAHAN (OPNAME)
 * ========================================================= */
$tahun = isset($_GET['tahun']) && preg_match('/^\d{4}$/', $_GET['tahun']) 
    ? trim($_GET['tahun']) 
    : date('Y');

$dateQ1 = $tahun . '-04-01';             // Opname stok akhir Maret
$dateQ2 = $tahun . '-07-01';             // Opname stok akhir Juni
$dateQ3 = $tahun . '-10-01';             // Opname stok akhir September
$dateQ4 = ($tahun + 1) . '-01-01';       // Opname stok akhir Desember

$queryError = [];
$reportData = [];

/* =========================================================
 * KODE SQL UTAMA (MENGAMBIL VOLUME, HARGA PRICE & PO, + KURS)
 * ========================================================= */
$sql = "
    SELECT 
        I.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, 
        TY.ITTY_CODE, TY.ITTY_DESC,
        CONVERT(varchar(10), CAST(S.SOP_SDATE AS DATE), 120) AS SOP_DATE,
        SUM(T.TAG_QTY) AS STOK_AKHIR,
        
        -- MENGAMBIL HARGA ITTY 01 DARI TABEL PRICE_DETAIL (JIKA USD KONVERSI RP)
        MAX(CASE 
            WHEN TY.ITTY_CODE = '01' AND UPPER(LTRIM(RTRIM(P.CURR_CODE))) = 'USD' THEN 
                ISNULL(PD.PRDT_PRICE, 0) * (ISNULL(CR_PRICE.CURR_RP, 0) / ISNULL(NULLIF(CR_PRICE.CURR_USD, 0), 1))
            WHEN TY.ITTY_CODE = '01' THEN 
                ISNULL(PD.PRDT_PRICE, 0) 
            ELSE 0 
        END) AS HARGA_01,
                 
        -- MENGAMBIL HARGA ITTY 02, 03, 05 DARI TABEL PO (JIKA USD KONVERSI RP)
        MAX(CASE 
            WHEN TY.ITTY_CODE IN ('02','03','05') AND UPPER(LTRIM(RTRIM(LatestPO.PO_CUR))) = 'USD' THEN 
                ISNULL(LatestPO.POD_PRICE, 0) * (ISNULL(CR_PO.CURR_RP, 0) / ISNULL(NULLIF(CR_PO.CURR_USD, 0), 1))
            WHEN TY.ITTY_CODE IN ('02','03','05') THEN 
                ISNULL(LatestPO.POD_PRICE, 0)
            ELSE 0 
        END) AS HARGA_PO

    FROM dbo.SOP S
    INNER JOIN dbo.TAGS T ON S.SOP_ID = T.SOP_ID
    INNER JOIN dbo.ITEMS I ON T.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.ITTY TY ON I.ITTY_CODE = TY.ITTY_CODE
    
    -- [1] JOIN UNTUK HARGA BARANG JADI (01)
    LEFT JOIN dbo.PRICE P ON I.ITEM_ID = P.PART_ID AND TY.ITTY_CODE = '01'
    LEFT JOIN dbo.PRICE_DETAIL PD ON P.PRICE_ID = PD.PRICE_ID
        AND CAST(S.SOP_SDATE AS DATE) >= CAST(PD.PRDT_START AS DATE) 
        AND CAST(S.SOP_SDATE AS DATE) <= CAST(PD.PRDT_END AS DATE)
    LEFT JOIN dbo.CURR_RAT_TALLY CR_PRICE ON P.CURR_CODE = CR_PRICE.CURR_CODE
        AND CAST(S.SOP_SDATE AS DATE) >= CAST(CR_PRICE.CURR_SDATE AS DATE)
        AND CAST(S.SOP_SDATE AS DATE) <= CAST(CR_PRICE.CURR_EDATE AS DATE)

    -- [2] OUTER APPLY UNTUK MENCARI PO TERBARU SEBELUM TANGGAL PENCACAHAN (ITTY 02,03,05)
    OUTER APPLY (
        SELECT TOP 1 PO2.PO_CUR, PD2.POD_PRICE
        FROM dbo.PO PO2
        INNER JOIN dbo.PO_DETAIL PD2 ON PO2.PO_ID = PD2.PO_ID
        WHERE PD2.ITEM_ID = I.ITEM_ID AND TY.ITTY_CODE IN ('02','03','05')
          AND CAST(PO2.PO_DATE AS DATE) <= CAST(S.SOP_SDATE AS DATE)
        ORDER BY PO2.PO_DATE DESC
    ) AS LatestPO
    LEFT JOIN dbo.CURR_RAT_TALLY CR_PO ON LatestPO.PO_CUR = CR_PO.CURR_CODE
        AND CAST(S.SOP_SDATE AS DATE) >= CAST(CR_PO.CURR_SDATE AS DATE)
        AND CAST(S.SOP_SDATE AS DATE) <= CAST(CR_PO.CURR_EDATE AS DATE)

    WHERE TY.ITTY_CODE IN ('01', '02', '03', '05')
      AND CAST(S.SOP_SDATE AS DATE) IN (?, ?, ?, ?)
    GROUP BY I.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, TY.ITTY_CODE, TY.ITTY_DESC, CAST(S.SOP_SDATE AS DATE)
";
$params = [$dateQ1, $dateQ2, $dateQ3, $dateQ4];

/* =========================================================
 * EKSEKUSI MULTI-SERVER CONNECTION (LOOPING P1 & P2)
 * ========================================================= */
foreach ($servers_config as $plantKey => $srv) {
    $connMulti = @sqlsrv_connect($srv['ip'], $conn_opts_base);
    if (!$connMulti) {
        $queryError[] = "Gagal terhubung ke {$srv['label']} ({$srv['ip']})";
        continue;
    }

    $stmt = sqlsrv_query($connMulti, $sql, $params);
    if ($stmt === false) {
        $queryError[] = "Query gagal di {$srv['label']}: " . sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemId   = (int)$row['ITEM_ID'];
            $ittyCode = trim($row['ITTY_CODE']);
            $sDate    = trim($row['SOP_DATE']);
            $qty      = (float)$row['STOK_AKHIR'];
            
            // Harga didapat dari PO atau PRICE
            $price = ($ittyCode === '01') ? (float)$row['HARGA_01'] : (float)$row['HARGA_PO'];

            // Inisialisasi kerangka grup jika belum ada
            if (!isset($reportData[$ittyCode])) {
                $reportData[$ittyCode] = ['desc' => trim($row['ITTY_DESC']), 'items' => []];
            }
            if (!isset($reportData[$ittyCode]['items'][$itemId])) {
                $reportData[$ittyCode]['items'][$itemId] = [
                    'code' => trim($row['ITEM_CODE']), 'name' => trim($row['ITEM_NAME']), 'unit' => trim($row['ITEM_UNIT']),
                    'q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0,
                    'price_q1_p1' => 0, 'price_q1_p2' => 0,
                    'price_q2_p1' => 0, 'price_q2_p2' => 0,
                    'price_q3_p1' => 0, 'price_q3_p2' => 0,
                    'price_q4_p1' => 0, 'price_q4_p2' => 0,
                ];
            }

            // Gabungkan Volume dan pisahkan Harga berdasarkan Plant
            $qKey = '';
            if ($sDate === $dateQ1) $qKey = 'q1';
            elseif ($sDate === $dateQ2) $qKey = 'q2';
            elseif ($sDate === $dateQ3) $qKey = 'q3';
            elseif ($sDate === $dateQ4) $qKey = 'q4';

            if ($qKey !== '') {
                $reportData[$ittyCode]['items'][$itemId][$qKey] += $qty;
                $reportData[$ittyCode]['items'][$itemId]["price_{$qKey}_{$plantKey}"] = $price;
            }
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($connMulti);
}

// Urutkan grup berdasarkan ITTY 01, 02, 03, 05
ksort($reportData);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BLOK V - Hasil Pencacahan Inventori (Multi-Server)</title>
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
        .server-warn { font-size: 11px; background: #fff3cd; color: #856404; padding: 6px; text-align: center; margin-bottom: 10px; border-radius: 4px; border: 1px solid #ffeeba; }
        
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
        BLOK V. KETERANGAN POSISI INVENTORI/PERSEDIAAN AKHIR TRIWULAN (MULTI-SERVER), <?php echo sap_h($tahun); ?>
    </div>
    
    <div class="server-warn">
        <strong>Info Data:</strong> Volume adalah gabungan dari Plant 1 & Plant 2. Harga memprioritaskan Plant 2, jika tidak ada/kosong menggunakan harga Plant 1. Konversi USD ke IDR aktif otomatis.
    </div>

    <table class="detail-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">No.</th>
                <th rowspan="2" style="width: 150px;">Nama<br>Komoditas</th>
                <th rowspan="2" style="width: 70px;">Kode Item /<br>KBKI 2015</th>
                <th rowspan="2" style="width: 50px;">Kode<br>Satuan<br>Volume</th>
                
                <th colspan="3">31 Maret <?php echo sap_h($tahun); ?></th>
                <th colspan="3">30 Juni <?php echo sap_h($tahun); ?></th>
                <th colspan="3">30 September <?php echo sap_h($tahun); ?></th>
                <th colspan="3">31 Desember <?php echo sap_h($tahun); ?></th>
            </tr>
            <tr>
                <!-- Triwulan 1 -->
                <th style="width: 60px;">Volume<br>Inventori</th>
                <th style="width: 80px;">Nilai<br>Inventori<br>(Rupiah)</th>
                <th style="width: 80px;">Harga<br>Rata-rata<br>per Satuan<br>(Rupiah)</th>
                
                <!-- Triwulan 2 -->
                <th style="width: 60px;">Volume<br>Inventori</th>
                <th style="width: 80px;">Nilai<br>Inventori<br>(Rupiah)</th>
                <th style="width: 80px;">Harga<br>Rata-rata<br>per Satuan<br>(Rupiah)</th>

                <!-- Triwulan 3 -->
                <th style="width: 60px;">Volume<br>Inventori</th>
                <th style="width: 80px;">Nilai<br>Inventori<br>(Rupiah)</th>
                <th style="width: 80px;">Harga<br>Rata-rata<br>per Satuan<br>(Rupiah)</th>

                <!-- Triwulan 4 -->
                <th style="width: 60px;">Volume<br>Inventori</th>
                <th style="width: 80px;">Nilai<br>Inventori<br>(Rupiah)</th>
                <th style="width: 80px;">Harga<br>Rata-rata<br>per Satuan<br>(Rupiah)</th>
            </tr>
            <!-- Penomoran Kolom -->
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
                    <td colspan="15"><?php echo sap_h($ittyGroup['desc']); ?> (<?php echo sap_h($ittyCode); ?>)</td>
                </tr>

                <?php 
                $no = 1;
                foreach ($ittyGroup['items'] as $itemId => $data) { 
                    
                    // PRIORITAS HARGA: Jika harga di Plant 2 (P2) bernilai lebih dari 0, ambil P2. Jika tidak, ambil P1.
                    $fixPriceQ1 = ($data['price_q1_p2'] > 0) ? $data['price_q1_p2'] : $data['price_q1_p1'];
                    $fixPriceQ2 = ($data['price_q2_p2'] > 0) ? $data['price_q2_p2'] : $data['price_q2_p1'];
                    $fixPriceQ3 = ($data['price_q3_p2'] > 0) ? $data['price_q3_p2'] : $data['price_q3_p1'];
                    $fixPriceQ4 = ($data['price_q4_p2'] > 0) ? $data['price_q4_p2'] : $data['price_q4_p1'];

                    // Hitung Total Nilai Inventori (Volume Total x Harga Fix)
                    $nilai_1 = $data['q1'] * $fixPriceQ1;
                    $nilai_2 = $data['q2'] * $fixPriceQ2;
                    $nilai_3 = $data['q3'] * $fixPriceQ3;
                    $nilai_4 = $data['q4'] * $fixPriceQ4;

                    $subTotalQ1 += $nilai_1;
                    $subTotalQ2 += $nilai_2;
                    $subTotalQ3 += $nilai_3;
                    $subTotalQ4 += $nilai_4;
                ?>
                <tr>
                    <td class="center"><?php echo $no++; ?>.</td>
                    <td><?php echo sap_h($data['name']); ?></td>
                    <td class="center"><?php echo sap_h($data['code']); ?></td>
                    <td class="center"><?php echo sap_h($data['unit']); ?></td>
                    
                    <!-- Q1 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q1'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($nilai_1)); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($fixPriceQ1)); ?></td>

                    <!-- Q2 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q2'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($nilai_2)); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($fixPriceQ2)); ?></td>

                    <!-- Q3 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q3'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($nilai_3)); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($fixPriceQ3)); ?></td>

                    <!-- Q4 -->
                    <td class="number"><?php echo sap_h(sap_number($data['q4'])); ?></td>
                    <td class="number" style="background-color: #fcfcfc;"><?php echo sap_h(sap_number($nilai_4)); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($fixPriceQ4)); ?></td>
                </tr>
                <?php } ?>

                <!-- Baris Total Nilai Inventori per Kategori -->
                <tr class="section-header">
                    <td colspan="4" class="center">Total Nilai <?php echo $abjadGrup; ?></td>
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
                    <td colspan="16" class="center">Tidak ada data pencacahan fisik yang ditemukan dari kedua server.</td>
                </tr>
            <?php } else { ?>
                <!-- Baris Grand Total (Keseluruhan) -->
                <tr class="section-header" style="background-color: #d1c89f;">
                    <td colspan="4" class="center">TOTAL KESELURUHAN NILAI INVENTORI</td>
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