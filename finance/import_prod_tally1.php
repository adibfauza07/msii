<?php
// C:\xampp\htdocs\msii\finance\import_prod_tally.php
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@set_time_limit(0);

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';
if (file_exists($config1)) { require_once $config1; } 
elseif (file_exists($config2)) { require_once $config2; } 
else { die('Config not found.'); }

$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

// AKSES P1 & P2
$allow_p1 = ($login_user == 'plant1' || $active_plant == 'p1');
$allow_p2 = ($login_user == 'plant2' || $active_plant == 'p2');

if (!$allow_p1 && !$allow_p2) {
    echo "<script>alert('Akses hanya untuk Plant 1 / Plant 2.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Tentukan plant aktif untuk label dan tombol kembali.
if ($active_plant == 'p1' || $active_plant == 'p2') {
    $current_plant_code = $active_plant;
} elseif ($login_user == 'plant1') {
    $current_plant_code = 'p1';
} else {
    $current_plant_code = 'p2';
}

$current_plant_label = ($current_plant_code == 'p1') ? 'P1' : 'P2';
$back_import_url = ($current_plant_code == 'p1')
    ? 'tally_import.php'
    : 'tally_import_p2.php?tab=prod';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function x($s) { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
function qx($sql, $params = array()) { return q($sql, $params); }

// Ambil kode ITEM sebanyak 8 karakter paling kiri.
function itemCode8($value) {
    return substr(trim((string)$value), 0, 8);
}

// Normalisasi kolom ITEM
function normalizeItemRows8($rows) {
    $out = array();
    foreach ($rows as $r) {
        if (is_array($r)) {
            $itemValue = getRowValueInsensitive($r, array('ITEM', 'ITEM_CODE'), '');
            setRowValueDual($r, 'ITEM', itemCode8($itemValue));
        }
        $out[] = $r;
    }
    return $out;
}

function rate5($value) {
    return number_format(round((float)$value, 5), 5, '.', '');
}

function fetchAllRows($stmt) {
    $rows = array();
    if (!$stmt) return $rows;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

function normalizeSqlDate($value) {
    if ($value instanceof DateTime) { return $value->format('Y-m-d'); }
    $s = trim((string)$value);
    if ($s === '') { return date('Y-m-d'); }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) { return $m[1] . '-' . $m[2] . '-' . $m[3]; }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) { return $m[1]; }
    $time = strtotime(str_replace('/', '-', $s));
    if ($time !== false) { return date('Y-m-d', $time); }
    return date('Y-m-d');
}

function getRowValueInsensitive($row, $names, $default = '') {
    if (!is_array($row)) return $default;
    foreach ($names as $name) {
        if (isset($row[$name])) return $row[$name];
    }
    $lower = array_change_key_case($row, CASE_LOWER);
    foreach ($names as $name) {
        $key = strtolower($name);
        if (isset($lower[$key])) return $lower[$key];
    }
    return $default;
}

function setRowValueDual(&$row, $upperKey, $value) {
    $row[$upperKey] = $value;
    $row[strtolower($upperKey)] = $value;
}

function extractItemCodeFromText($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (preg_match('/^([A-Za-z0-9]+(?:-[A-Za-z0-9]+)+)/', $text, $m)) { return strtoupper(trim($m[1])); }
    if (preg_match('/^([A-Za-z0-9]{4,})/', $text, $m)) { return strtoupper(trim($m[1])); }
    return '';
}

function getUsdVrateByDate($dateValue) {
    static $cache = array();
    $date = normalizeSqlDate($dateValue);
    if (isset($cache[$date])) return $cache[$date];

    $dt = $date . ' 00:00:00';
    $rate = 0.0;

    $stmt = qx("SELECT TOP 1 CURR_VRATE FROM CURR_RAT WHERE CURR_CODE = 'USD' AND CONVERT(DATETIME, ?, 120) BETWEEN CURR_SDATE AND CURR_EDATE ORDER BY CURR_SDATE DESC, CURR_EDATE DESC", array($dt));
    if ($stmt) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r['CURR_VRATE'])) { $rate = (float)$r['CURR_VRATE']; }
    }

    if ($rate <= 0) {
        $stmt = qx("SELECT TOP 1 CURR_VRATE FROM CURR_RAT WHERE CURR_CODE = 'USD' AND CURR_SDATE <= CONVERT(DATETIME, ?, 120) ORDER BY CURR_SDATE DESC, CURR_EDATE DESC", array($dt));
        if ($stmt) {
            $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($r && isset($r['CURR_VRATE'])) { $rate = (float)$r['CURR_VRATE']; }
        }
    }
    $cache[$date] = $rate;
    return $rate;
}

// -----------------------------------------------------------------------------
// LOGIKA MENYAMAKAN HPP USD PLANT 1 (Dipecah & Difilter Per Tahun Produksi)
// -----------------------------------------------------------------------------
function getSalesHppRates($fromDate, $toDate) {
    $rates = array();
    
    // Tarik data tahun dari parameter
    $startYear = (int)date('Y', strtotime($fromDate));
    $endYear = (int)date('Y', strtotime($toDate));
    
    // Ambil data dari 1 TAHUN SEBELUMNYA sebagai cadangan (fallback), misal Januari 2026 belum ada sales, dia akan pakai harga 2025.
    $spFrom = sprintf('%04d-01-01 00:00:00', $startYear - 1);
    $spTo = sprintf('%04d-12-31 23:59:59', $endYear);

    $stmt = qx("{CALL dbo.sp_hpp_sales_item_idr(?, ?, ?)}", array($spFrom, $spTo, 'YEARLY'));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemCode = isset($row['ITEM_CODE']) ? trim((string)$row['ITEM_CODE']) : '';
            $year = isset($row['SALES_YEAR']) ? (int)$row['SALES_YEAR'] : 0;
            
            if ($itemCode === '' || $year === 0) continue;
            $itemKey = strtoupper($itemCode);

            // Kumpulkan data Qty & HPP USD
            $salesQty = isset($row['SALES_QTY']) ? (float)$row['SALES_QTY'] : 0;
            $spHppIdr = isset($row['TOTAL_HPP_IDR']) ? (float)$row['TOTAL_HPP_IDR'] : 0;
            $salesIdr = isset($row['TOTAL_SALES_IDR']) ? (float)$row['TOTAL_SALES_IDR'] : 0;
            $spHppUsd = isset($row['TOTAL_HPP_USD']) ? (float)$row['TOTAL_HPP_USD'] : 0;
            $salesUsd = isset($row['TOTAL_SALES_USD']) ? (float)$row['TOTAL_SALES_USD'] : 0;

            if (!isset($rates[$year])) {
                $rates[$year] = array();
            }
            if (!isset($rates[$year][$itemKey])) {
                $rates[$year][$itemKey] = array('qty' => 0, 'hpp_idr' => 0, 'hpp_usd' => 0, 'sales_idr' => 0, 'sales_usd' => 0);
            }
            
            $rates[$year][$itemKey]['qty'] += $salesQty;
            $rates[$year][$itemKey]['hpp_idr'] += $spHppIdr;
            $rates[$year][$itemKey]['hpp_usd'] += $spHppUsd;
            $rates[$year][$itemKey]['sales_idr'] += $salesIdr;
            $rates[$year][$itemKey]['sales_usd'] += $salesUsd;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $rates;
}

function get_default_usd_rate_current() {
    $stmt = qx("SELECT TOP 1 CURR_VRATE FROM CURR_RAT WHERE CURR_CODE = 'USD' AND GETDATE() BETWEEN CURR_SDATE AND CURR_EDATE ORDER BY CURR_SDATE DESC", array());
    if ($stmt) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r['CURR_VRATE'])) return (float)$r['CURR_VRATE'];
    }
    return 0.0;
}

function infer_idr_per_usd_rate($hppIdr, $hppUsd, $salesIdr, $salesUsd, $defaultRate) {
    if ($salesIdr > 0 && $salesUsd > 0) return $salesIdr / $salesUsd;
    if ($hppIdr > 0 && $hppUsd > 0) return $hppIdr / $hppUsd;
    return (float)$defaultRate;
}

function getListHppIdrForItemCode02($itemCode) {
    static $cache = array();
    $itemCode = strtoupper(trim((string)$itemCode));
    if ($itemCode === '' || substr($itemCode, 0, 2) !== '02') {
        return array('ok' => false, 'hpp_idr' => 0.0, 'item_code' => $itemCode, 'error' => 'BUKAN_02');
    }
    if (isset($cache[$itemCode])) return $cache[$itemCode];

    $sql = "
        ;WITH RootPart AS ( SELECT TOP 1 ITEM_ID, ITEM_CODE, ITEM_NAME FROM ITEMS WHERE ITEM_INACTIVE = 0 AND ITEM_CODE LIKE '02%' AND ITEM_CODE = ? ORDER BY ITEM_CODE ),
        LatestPO AS ( SELECT pd.ITEM_ID AS MAT_ID, pd.POD_PRICE, po.PO_DATE AS PRICE_DATE_RAW, pd.POD_UNIT, po.PO_CUR, ROW_NUMBER() OVER ( PARTITION BY pd.ITEM_ID ORDER BY po.PO_DATE DESC, po.PO_ID DESC ) AS rn FROM PO_DETAIL pd INNER JOIN PO po ON pd.PO_ID = po.PO_ID ),
        PriceData AS ( SELECT p.MAT_ID, p.POD_PRICE, p.PRICE_DATE_RAW, p.POD_UNIT, p.PO_CUR, c.CURR_VRATE, CAST ( CASE WHEN p.POD_PRICE IS NULL THEN NULL WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR' THEN p.POD_PRICE WHEN c.CURR_VRATE IS NULL THEN NULL ELSE p.POD_PRICE * c.CURR_VRATE END AS DECIMAL(38, 8) ) AS PRICE_IDR, CASE WHEN p.POD_PRICE IS NULL THEN 'TANPA_HARGA' WHEN ISNULL(p.PO_CUR, 'IDR') <> 'IDR' AND c.CURR_VRATE IS NULL THEN 'TANPA_KURS' ELSE 'OK' END AS PRICE_STATUS FROM LatestPO p LEFT JOIN CURR_RAT c ON p.PO_CUR = c.CURR_CODE AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND c.CURR_EDATE WHERE p.rn = 1 ),
        HppBomTree AS ( SELECT bd.PART_ID AS ROOT_PART_ID, bd.ITEM_ID AS COMPONENT_ID, m.ITTY_CODE, CAST(bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY, CAST('/' + CONVERT(VARCHAR(50), bd.PART_ID) + '/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/' AS VARCHAR(MAX)) AS BOM_PATH, 1 AS BOM_LEVEL FROM BOM_DEFAULT bd INNER JOIN ITEMS root_item ON bd.PART_ID = root_item.ITEM_ID INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID WHERE root_item.ITTY_CODE = '01' AND root_item.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0 UNION ALL SELECT bt.ROOT_PART_ID, bd.ITEM_ID AS COMPONENT_ID, m.ITTY_CODE, CAST(bt.TOTAL_QTY * bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY, CAST(bt.BOM_PATH + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/' AS VARCHAR(MAX)) AS BOM_PATH, bt.BOM_LEVEL + 1 FROM HppBomTree bt INNER JOIN ITEMS current_item ON bt.COMPONENT_ID = current_item.ITEM_ID AND current_item.ITTY_CODE = '01' INNER JOIN BOM_DEFAULT bd ON bt.COMPONENT_ID = bd.PART_ID INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID WHERE bt.BOM_LEVEL < 20 AND current_item.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0 AND bt.BOM_PATH NOT LIKE '%/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/%' ),
        Hpp01Summary AS ( SELECT bt.ROOT_PART_ID, CAST ( SUM ( CASE WHEN bt.ITTY_CODE = '02' THEN ROUND((bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0, 2) WHEN bt.ITTY_CODE IN ('03', '05') THEN ROUND(bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0), 2) ELSE 0 END ) AS DECIMAL(38, 8) ) AS HPP_IDR_PER_UNIT, SUM(CASE WHEN bt.ITTY_CODE IN ('02', '03', '05') THEN 1 ELSE 0 END) AS LEAF_COUNT, SUM ( CASE WHEN bt.ITTY_CODE IN ('02', '03', '05') AND (pd.MAT_ID IS NULL OR pd.PRICE_STATUS <> 'OK') THEN 1 ELSE 0 END ) AS MISSING_PRICE_COUNT FROM HppBomTree bt LEFT JOIN PriceData pd ON bt.COMPONENT_ID = pd.MAT_ID WHERE bt.ITTY_CODE IN ('02', '03', '05') GROUP BY bt.ROOT_PART_ID ),
        DirectBom AS ( SELECT i.ITEM_ID AS PART_ID, i.ITEM_CODE AS PART_CODE, i.ITEM_NAME AS PART_NAME, m.ITEM_ID AS MAT_ID, m.ITEM_CODE AS MAT_CODE, m.ITEM_NAME AS MAT_NAME, CAST(bd.QTY AS DECIMAL(38, 8)) AS QTY, m.ITTY_CODE FROM BOM_DEFAULT bd INNER JOIN RootPart i ON bd.PART_ID = i.ITEM_ID INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID WHERE ( m.ITTY_CODE IN ('02', '03', '05') OR ( i.ITEM_CODE LIKE '02%' AND m.ITTY_CODE = '01' ) ) AND m.ITEM_INACTIVE = 0 ),
        Calc AS ( SELECT b.PART_CODE, b.MAT_CODE, b.ITTY_CODE, CAST ( CASE WHEN b.ITTY_CODE = '01' THEN ROUND(b.QTY * ISNULL(h.HPP_IDR_PER_UNIT, 0), 2) WHEN b.ITTY_CODE = '02' THEN ROUND((b.QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0, 2) WHEN b.ITTY_CODE IN ('03', '05') THEN ROUND(b.QTY * ISNULL(pd.PRICE_IDR, 0), 2) ELSE 0 END AS DECIMAL(38, 2) ) AS TOTAL_HARGA, CASE WHEN b.ITTY_CODE = '01' AND (h.ROOT_PART_ID IS NULL OR ISNULL(h.LEAF_COUNT, 0) = 0) THEN 1 WHEN b.ITTY_CODE = '01' AND ISNULL(h.MISSING_PRICE_COUNT, 0) > 0 THEN 1 WHEN b.ITTY_CODE IN ('02', '03', '05') AND (pd.MAT_ID IS NULL OR pd.PRICE_STATUS <> 'OK') THEN 1 ELSE 0 END AS ERROR_LINE FROM DirectBom b LEFT JOIN PriceData pd ON b.MAT_ID = pd.MAT_ID LEFT JOIN Hpp01Summary h ON b.MAT_ID = h.ROOT_PART_ID )
        SELECT MAX(PART_CODE) AS ITEM_CODE, CAST(ISNULL(SUM(TOTAL_HARGA), 0) AS DECIMAL(38, 2)) AS HPP_IDR, COUNT(*) AS DETAIL_COUNT, SUM(ERROR_LINE) AS ERROR_COUNT FROM Calc OPTION (MAXRECURSION 100)
    ";

    $stmt = qx($sql, array($itemCode));
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

    if (!$row || !isset($row['ITEM_CODE']) || trim((string)$row['ITEM_CODE']) === '') {
        $cache[$itemCode] = array('ok' => false, 'hpp_idr' => 0.0, 'item_code' => $itemCode, 'error' => 'TIDAK_ADA_BOM_LIST');
        return $cache[$itemCode];
    }

    $hpp = isset($row['HPP_IDR']) ? (float)$row['HPP_IDR'] : 0.0;
    $detailCount = isset($row['DETAIL_COUNT']) ? (int)$row['DETAIL_COUNT'] : 0;
    $errorCount = isset($row['ERROR_COUNT']) ? (int)$row['ERROR_COUNT'] : 0;

    $cache[$itemCode] = array('ok' => ($hpp > 0 && $detailCount > 0), 'hpp_idr' => $hpp, 'item_code' => $itemCode, 'detail_count' => $detailCount, 'error_count' => $errorCount, 'error' => ($hpp > 0 && $detailCount > 0) ? '' : 'HPP_LIST_NOL');
    return $cache[$itemCode];
}

function applyHppUsdToRows($rows, &$stats, $fromDate, $toDate) {
    global $current_plant_code;
    
    $stats = array('total' => count($rows), 'adjusted' => 0, 'skipped' => 0, 'missing_hpp' => 0, 'missing_rate' => 0);
    $out = array();

    $isPlant1 = ($current_plant_code === 'p1');
    $salesRates = array();
    $defaultUsdRate = 0;

    if ($isPlant1) {
        $salesRates = getSalesHppRates($fromDate, $toDate);
        $defaultUsdRate = get_default_usd_rate_current();
    }

    foreach ($rows as $r) {
        $itemText = getRowValueInsensitive($r, array('ITEM_CODE', 'ITEM'), '');
        $itemCode = extractItemCodeFromText($itemText);

        if ($itemCode === '') {
            $stats['skipped']++;
            $out[] = $r;
            continue;
        }

        $prodDate = getRowValueInsensitive($r, array('PROD_DATE', 'DATE'), date('Y-m-d'));
        // Ambil tahun dari tanggal produksi untuk filter pencocokan
        $prodYear = (int)date('Y', strtotime($prodDate));
        $qty = (float)getRowValueInsensitive($r, array('DEST_QTY', 'QTY'), 0);
        
        $rateUsd = 0;

        if ($isPlant1) {
            $itemKey = strtoupper($itemCode);
            $sr = null;
            
            // 1. Prioritaskan rate persis di TAHUN PRODUKSI (misal: 2026)
            if (isset($salesRates[$prodYear][$itemKey])) {
                $sr = $salesRates[$prodYear][$itemKey];
            } 
            // 2. Jika di 2026 tidak ada, mundur ke 2025 (Fallback 1 tahun)
            elseif (isset($salesRates[$prodYear - 1][$itemKey])) {
                $sr = $salesRates[$prodYear - 1][$itemKey];
            }
            // 3. Fallback terakhir: cari di semua tahun tersedia (Diurutkan dari terbaru)
            else {
                $availableYears = array_keys($salesRates);
                rsort($availableYears);
                foreach ($availableYears as $ay) {
                    if (isset($salesRates[$ay][$itemKey])) {
                        $sr = $salesRates[$ay][$itemKey];
                        break;
                    }
                }
            }
            
            if ($sr !== null) {
                // Jika item berawalan 02%, kombinasikan dengan logika BOM List HPP (seperti script Sales)
                if (substr($itemCode, 0, 2) === '02') {
                    $hppInfo = getListHppIdrForItemCode02($itemCode);
                    if ($hppInfo['ok']) {
                        $inferred = infer_idr_per_usd_rate($sr['hpp_idr'], $sr['hpp_usd'], $sr['sales_idr'], $sr['sales_usd'], $defaultUsdRate);
                        $usdRate = $inferred > 0 ? $inferred : ($defaultUsdRate > 0 ? $defaultUsdRate : getUsdVrateByDate($prodDate));
                        $rateUsd = ((float)$hppInfo['hpp_idr']) / $usdRate;
                    } else {
                        // Jika tidak ada BOM List HPP 02, gunakan SP
                        if ($sr['qty'] != 0) {
                            $rateUsd = abs($sr['hpp_usd'] / $sr['qty']);
                        }
                    }
                } else {
                    // Item SELAIN 02% (seperti 014724-0 dll), ambil langsung dari unit HPP USD patokan Sales yang sesuai dengan Tahunnya!
                    if ($sr['qty'] != 0) {
                        $rateUsd = abs($sr['hpp_usd'] / $sr['qty']);
                    }
                }
            } else {
                // Item tidak ada di laporan Sales sama sekali, fallback ke BOM List (hanya kalau 02)
                if (substr($itemCode, 0, 2) === '02') {
                    $hppInfo = getListHppIdrForItemCode02($itemCode);
                    if ($hppInfo['ok']) {
                        $usdRate = getUsdVrateByDate($prodDate);
                        if ($usdRate > 0) {
                            $rateUsd = ((float)$hppInfo['hpp_idr']) / $usdRate;
                        }
                    }
                }
            }
        } else {
            // Plant 2 Logic: Tetap hanya melakukan adjustment untuk item 02% (BOM)
            if (substr($itemCode, 0, 2) === '02') {
                $hppInfo = getListHppIdrForItemCode02($itemCode);
                if ($hppInfo['ok']) {
                    $usdRate = getUsdVrateByDate($prodDate);
                    if ($usdRate > 0) {
                        $rateUsd = ((float)$hppInfo['hpp_idr']) / $usdRate;
                    } else {
                        $stats['missing_rate']++;
                    }
                } else {
                    $stats['missing_hpp']++;
                }
            }
        }

        // Terapkan penyesuaian jika perhitungan rate USD berhasil ditarik
        if ($rateUsd > 0) {
            $rateUsd = round($rateUsd, 5);
            $amountUsd = round($rateUsd * $qty, 2);

            setRowValueDual($r, 'DEST_RATE', rate5($rateUsd));
            setRowValueDual($r, 'DEST_AMOUNT', $amountUsd);

            $stats['adjusted']++;
        } else {
            $stats['skipped']++;
        }

        $out[] = $r;
    }
    return $out;
}

function hppAdjustMessage($stats) {
    if (!is_array($stats)) return '';
    return ' | Update HPP/Unit USD: ' . (int)$stats['adjusted'] . ' baris disesuaikan dengan patokan HPP (Dicocokkan per Tahun Produksi).';
}

function sendToTally($xml, $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    $res = curl_exec($ch);
    if ($res === false) { $res = 'CURL ERROR: ' . curl_error($ch); }
    curl_close($ch);
    return $res;
}

function tallyResponseCounts($res) {
    $tags = array('CREATED', 'ALTERED', 'DELETED', 'COMBINED', 'IGNORED', 'ERRORS', 'CANCELLED', 'CANCELED');
    $counts = array();
    foreach ($tags as $tag) {
        $counts[strtolower($tag)] = 0;
        if (preg_match('/<' . $tag . '>\s*(-?\d+)\s*<\/' . $tag . '>/i', (string)$res, $m)) {
            $counts[strtolower($tag)] = (int)$m[1];
        }
    }
    return $counts;
}

function responseOk($res) {
    if (stripos((string)$res, 'CURL ERROR') !== false) return false;
    if (stripos((string)$res, '<LINEERROR>') !== false) return false;
    $c = tallyResponseCounts($res);
    $errors = isset($c['errors']) ? (int)$c['errors'] : 0;
    $ignored = isset($c['ignored']) ? (int)$c['ignored'] : 0;
    $cancelled = 0;
    if (isset($c['cancelled'])) $cancelled += (int)$c['cancelled'];
    if (isset($c['canceled'])) $cancelled += (int)$c['canceled'];
    $created = isset($c['created']) ? (int)$c['created'] : 0;
    $altered = isset($c['altered']) ? (int)$c['altered'] : 0;
    return ($errors === 0 && $ignored === 0 && $cancelled === 0 && ($created > 0 || $altered > 0));
}

function tallyLineError($res) {
    if (preg_match_all('/<LINEERROR>(.*?)<\/LINEERROR>/is', (string)$res, $m)) {
        return trim(strip_tags(html_entity_decode(implode(' | ', $m[1]), ENT_QUOTES, 'UTF-8')));
    }
    if (stripos((string)$res, 'CURL ERROR') !== false) return trim((string)$res);
    return '';
}

function tallyFailureReason($res) {
    $lineError = tallyLineError($res);
    if ($lineError !== '') return $lineError;

    $c = tallyResponseCounts($res);
    $parts = array();
    if (!empty($c['errors'])) $parts[] = 'ERRORS=' . (int)$c['errors'];
    if (!empty($c['ignored'])) $parts[] = 'IGNORED=' . (int)$c['ignored'];
    if (!empty($c['cancelled'])) $parts[] = 'CANCELLED=' . (int)$c['cancelled'];
    if (!empty($c['canceled'])) $parts[] = 'CANCELED=' . (int)$c['canceled'];
    if (!empty($c['created'])) $parts[] = 'CREATED=' . (int)$c['created'];
    if (!empty($c['altered'])) $parts[] = 'ALTERED=' . (int)$c['altered'];

    if (!empty($parts)) return 'Gagal (' . implode(', ', $parts) . ').';
    return 'Respons Tally tidak menunjukkan voucher berhasil dibuat.';
}

function saveDebugXml($name, $xml) {
    $dir = __DIR__ . '/debug_tally_xml_prod';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$name) . '.xml';
    @file_put_contents($file, $xml);
    return $file;
}

function ensureTallyServerTable() { qx("IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL CREATE TABLE dbo.Tally_Server_Master (ID INT IDENTITY(1,1) PRIMARY KEY, ServerName VARCHAR(100) NULL, TallyIP VARCHAR(100) NOT NULL, TallyPort VARCHAR(10) NOT NULL, IsDefault BIT NOT NULL DEFAULT 0)", array()); }
function seedTallyServers() { ensureTallyServerTable(); $cek = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array()); $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC); if ($r && (int)$r['JML'] == 0) { qx("INSERT INTO dbo.Tally_Server_Master (ServerName, TallyIP, TallyPort, IsDefault) VALUES ('Localhost', '127.0.0.1', '9002', 0), ('dianero99', 'dianero99', '9002', 1)", array()); } }
function loadTallyServers() { seedTallyServers(); return fetchAllRows(qx("SELECT ID, ServerName, TallyIP, TallyPort, IsDefault FROM dbo.Tally_Server_Master ORDER BY IsDefault DESC", array())); }
function testTallyConnection($ip, $port) { $fp = @fsockopen($ip, $port, $errno, $errstr, 3); if ($fp) { fclose($fp); return 'OK: Tally port terbuka di ' . $ip . ':' . $port; } return 'ERROR: Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr; }

function buildProdXml($rows) {
    $vouchersXml = '';
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        
        $unik = isset($rl['uniqueid']) ? trim((string)$rl['uniqueid']) : '';
        $vchNo = isset($rl['vch_no']) ? trim((string)$rl['vch_no']) : '';
        $date = isset($rl['prod_date']) ? trim((string)$rl['prod_date']) : '';
        
        $itemCode = isset($rl['item']) ? trim((string)$rl['item']) : '';
        
        $qty = isset($rl['dest_qty']) ? trim((string)$rl['dest_qty']) : '0';
        $rate = isset($rl['dest_rate']) ? trim((string)$rl['dest_rate']) : '0';
        $rateFixed5 = rate5($rate);
        $amount = isset($rl['dest_amount']) ? trim((string)$rl['dest_amount']) : '0';
        $narration = isset($rl['narration']) ? trim((string)$rl['narration']) : '';
        
        $amountNeg = round((float)$amount, 2) * -1;
        $guid = 'udi-IMCPRODUKSI-' . $unik . '-' . $vchNo;

        $vouchersXml .= '
          <VOUCHER REMOTEID="'.x($guid).'" VCHTYPE="Production" ACTION="Create">
            <GUID>'.x($guid).'</GUID>
            <DATE>'.x($date).'</DATE>
            <EFFECTIVEDATE>'.x($date).'</EFFECTIVEDATE>
            <VOUCHERTYPENAME>Production</VOUCHERTYPENAME>
            <ISINVOICE>Yes</ISINVOICE>
            <VOUCHERNUMBER>'.x($vchNo).'</VOUCHERNUMBER>
            <NARRATION>'.x($narration).'</NARRATION>
            <INVENTORYENTRIESIN.LIST>
              <STOCKITEMNAME>'.x($itemCode).'</STOCKITEMNAME>
              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
              <RATE>'.x($rateFixed5).'</RATE>
              <AMOUNT>'.x($amountNeg).'</AMOUNT>
              <ACTUALQTY>'.x($qty).'</ACTUALQTY>
              <BILLEDQTY>'.x($qty).'</BILLEDQTY>
              <BATCHALLOCATIONS.LIST>
                <GODOWNNAME>Main Location</GODOWNNAME>
                <BATCHNAME>Primary Batch</BATCHNAME>
                <DESTINATIONGODOWNNAME>Main Location</DESTINATIONGODOWNNAME>
                <AMOUNT>'.x($amountNeg).'</AMOUNT>
                <ACTUALQTY>'.x($qty).'</ACTUALQTY>
                <BILLEDQTY>'.x($qty).'</BILLEDQTY>
              </BATCHALLOCATIONS.LIST>
            </INVENTORYENTRIESIN.LIST>
          </VOUCHER>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>Vouchers</REPORTNAME>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          '.$vouchersXml.'
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
}

/* REQUEST HANDLER */
$tally_servers = loadTallyServers();
$defaultSrv = isset($tally_servers[0]) ? $tally_servers[0] : array();

$tally_ip = isset($defaultSrv['TallyIP']) ? $defaultSrv['TallyIP'] : '127.0.0.1';
$tally_port = isset($defaultSrv['TallyPort']) ? $defaultSrv['TallyPort'] : '9002';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$toDate = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
$message = '';
$resultRows = array();
$summary = null;
$failedRows = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tally_ip = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : $tally_ip;
    $tally_port = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : $tally_port;
}

if ($action == 'test') {
    $message = testTallyConnection($tally_ip, $tally_port);
} elseif ($action == 'preview') {
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
    $adjStats = array();
    // Menggunakan fungsi penyesuaian yang baru
    $resultRows = applyHppUsdToRows($resultRows, $adjStats, $fromDate, $toDate);
    $resultRows = normalizeItemRows8($resultRows);
    $message = 'Preview data Production selesai. ITEM ditampilkan 8 karakter (ITEM_CODE). Total: ' . count($resultRows) . hppAdjustMessage($adjStats);
} elseif ($action == 'import_table') {
    qx("DELETE FROM dbo.Tally_Prod", array());
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $rows = fetchAllRows($stmt);
    $adjStats = array();
    // Menggunakan fungsi penyesuaian yang baru
    $rows = applyHppUsdToRows($rows, $adjStats, $fromDate, $toDate);
    $rows = normalizeItemRows8($rows);
    
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        qx("INSERT INTO dbo.Tally_Prod (UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION) VALUES (?, ?, ?, ?, ?, CAST(? AS DECIMAL(38,5)), ?, ?)", array(
            isset($rl['uniqueid']) ? $rl['uniqueid'] : '',
            isset($rl['vch_no']) ? $rl['vch_no'] : '',
            isset($rl['prod_date']) ? $rl['prod_date'] : '',
            isset($rl['item']) ? trim($rl['item']) : '',
            isset($rl['dest_qty']) ? $rl['dest_qty'] : 0,
            isset($rl['dest_rate']) ? rate5($rl['dest_rate']) : '0.00000',
            isset($rl['dest_amount']) ? $rl['dest_amount'] : 0,
            isset($rl['narration']) ? $rl['narration'] : ''
        ));
    }
    $message = 'Berhasil simpan ke tabel Tally_Prod dengan format ITEM_CODE. Total: ' . count($rows) . hppAdjustMessage($adjStats);
    $stmt = qx("SELECT UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION FROM dbo.Tally_Prod ORDER BY PROD_DATE, VCH_NO", array());
    $resultRows = fetchAllRows($stmt);
} elseif ($action == 'export') {
    qx("UPDATE dbo.Tally_Prod SET ITEM = LEFT(LTRIM(RTRIM(ITEM)), 8) WHERE ITEM IS NOT NULL", array());
    $stmt = qx("SELECT UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION FROM dbo.Tally_Prod ORDER BY PROD_DATE, VCH_NO", array());
    $resultRows = fetchAllRows($stmt);
    $adjStats = array();
    // Menggunakan fungsi penyesuaian yang baru
    $resultRows = applyHppUsdToRows($resultRows, $adjStats, $fromDate, $toDate);
    $resultRows = normalizeItemRows8($resultRows);

    if (count($resultRows) == 0) {
        $message = 'Tabel Tally_Prod kosong. Klik Import ke Tabel dulu.';
    } else {
        $url = 'http://' . $tally_ip . ':' . $tally_port;
        $successCount = 0;
        $failedRows = array();

        foreach ($resultRows as $index => $row) {
            $rl = array_change_key_case($row, CASE_LOWER);
            $unik = isset($rl['uniqueid']) ? trim((string)$rl['uniqueid']) : '';
            $vchNo = isset($rl['vch_no']) ? trim((string)$rl['vch_no']) : '';
            $prodDate = isset($rl['prod_date']) ? trim((string)$rl['prod_date']) : '';
            $itemCode = isset($rl['item']) ? trim($rl['item']) : '';
            $qty = isset($rl['dest_qty']) ? $rl['dest_qty'] : 0;

            $xml = buildProdXml(array($row));
            $res = sendToTally($xml, $url);
            $counts = tallyResponseCounts($res);

            if (responseOk($res)) {
                $successCount++;
                continue;
            }

            $debugKey = $vchNo . '_' . $unik . '_' . ($index + 1);
            $debugXml = saveDebugXml('PROD_FAILED_XML_' . $debugKey, $xml);
            $debugResponse = saveDebugXml('PROD_FAILED_RESPONSE_' . $debugKey, $res);
            $countText = 'CREATED=' . (int)$counts['created'] . ', ALTERED=' . (int)$counts['altered'] . ', IGNORED=' . (int)$counts['ignored'] . ', ERRORS=' . (int)$counts['errors'];

            $failedRows[] = array(
                'no' => $index + 1,
                'uniqueid' => $unik,
                'vch_no' => $vchNo,
                'prod_date' => $prodDate,
                'item_code' => $itemCode,
                'qty' => $qty,
                'error' => tallyFailureReason($res),
                'counts' => $countText,
                'debug_xml' => $debugXml,
                'debug_response' => $debugResponse
            );
        }

        $failedCount = count($failedRows);
        $status = ($failedCount === 0) ? 'Sukses' : (($successCount > 0) ? 'Sebagian' : 'Gagal');

        $summary = array(
            'total' => count($resultRows),
            'success' => $successCount,
            'failed' => $failedCount,
            'status' => $status,
            'debug' => $failedCount > 0 ? (__DIR__ . '/debug_tally_xml_prod') : '-',
            'error' => $failedCount > 0 ? 'Lihat tabel Detail Data Tidak Masuk di bawah.' : '',
        );

        $message = 'Kirim ke Tally selesai. Berhasil masuk: ' . $successCount . ', tidak masuk: ' . $failedCount . ', total: ' . count($resultRows) . hppAdjustMessage($adjStats);
    }
} else {
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
    $adjStats = array();
    // Menggunakan fungsi penyesuaian yang baru
    $resultRows = applyHppUsdToRows($resultRows, $adjStats, $fromDate, $toDate);
    $resultRows = normalizeItemRows8($resultRows);
}
?>
<?php include 'layout.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">EXPORT PRODUCTION TO TALLY <?php echo h($current_plant_label); ?></h3>
        <a href="<?php echo h($back_import_url); ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-info"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="alert alert-warning py-2">
        <strong>Pembaruan Sistem:</strong> Script ini sudah disederhanakan agar berjalan seperti modul Sales. Sistem mengirimkan <b>ITEM_CODE</b> (8 Karakter) secara langsung ke tag XML STOCKITEMNAME tanpa perlu mengecek katalog master di Tally terlebih dahulu. Harga disesuaikan mengikuti patokan HPP Sales <b>berdasarkan Tahun Produksi</b>.
    </div>

    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Import Production</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang memproses data...</div>
        </div>
    </div>

    <form method="post" id="frmProd">
        <input type="hidden" name="action" id="action" value="">
        
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Settings Tally Server</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pilih Server Tally</label>
                        <select id="server_combo" class="form-control" onchange="pilihServerTally()">
                            <option value="">-- pilih server tally --</option>
                            <?php foreach ($tally_servers as $srv) { ?>
                                <?php
                                $srvId = isset($srv['ID']) ? $srv['ID'] : '';
                                $srvIp = isset($srv['TallyIP']) ? $srv['TallyIP'] : '';
                                $srvPort = isset($srv['TallyPort']) ? $srv['TallyPort'] : '';
                                $srvName = isset($srv['ServerName']) ? $srv['ServerName'] : '';
                                $selectedServer = ($srvIp == $tally_ip && $srvPort == $tally_port) ? 'selected' : '';
                                ?>
                                <option value="<?php echo h($srvIp . '|' . $srvPort . '|' . $srvId); ?>" <?php echo $selectedServer; ?>>
                                    <?php echo h($srvName . ' - ' . $srvIp . ':' . $srvPort); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tally IP Address</label>
                        <input type="text" name="tally_ip" id="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Port</label>
                        <input type="text" name="tally_port" id="tally_port" class="form-control" value="<?php echo h($tally_port); ?>">
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Conn</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Filter Data Production</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Dari Tanggal</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo h($fromDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Sampai Tanggal</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo h($toDate); ?>">
                    </div>
                    <div class="col-md-8 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-success" onclick="setAction('preview')">Preview Data</button>
                        <button type="button" class="btn btn-info" onclick="setAction('import_table')">Import ke Tally_Prod</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Send to Tally</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($summary !== null) { ?>
        <?php
        $summaryColor = 'danger';
        if ($summary['status'] == 'Sukses') $summaryColor = 'success';
        elseif ($summary['status'] == 'Sebagian') $summaryColor = 'warning';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.getElementById('exportProgressBox');
            var bar = document.getElementById('exportProgressBar');
            var txt = document.getElementById('exportProgressText');

            if (box && bar) {
                box.style.display = 'block';
                bar.className = 'progress-bar bg-<?php echo h($summaryColor); ?>';
                bar.style.width = '100%';
                bar.innerHTML = '100% Selesai';
                if (txt) txt.innerHTML = 'Import selesai. Status: <?php echo h($summary['status']); ?>.';
            }
        });
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Import Production</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Voucher</th><td><?php echo h($summary['total']); ?></td></tr>
                    <tr><th>Berhasil Masuk</th><td><span class="badge bg-success"><?php echo h($summary['success']); ?></span></td></tr>
                    <tr><th>Tidak Masuk</th><td><span class="badge bg-<?php echo $summary['failed'] > 0 ? 'danger' : 'secondary'; ?>"><?php echo h($summary['failed']); ?></span></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?php echo h($summaryColor); ?>"><?php echo h($summary['status']); ?></span></td></tr>
                    <?php if ($summary['failed'] > 0) { ?>
                        <tr><th>Folder Debug</th><td><?php echo h(str_replace(__DIR__, '', $summary['debug'])); ?></td></tr>
                    <?php } ?>
                </table>

                <?php if ($summary['failed'] > 0) { ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong><?php echo h($summary['failed']); ?> voucher tidak masuk.</strong>
                        Detail VCH No., UNIQUEID, ITEM_CODE, dan pesan Tally ditampilkan di bawah.
                    </div>
                <?php } else { ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <strong>Semua voucher berhasil masuk ke Tally.</strong>
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php if (!empty($failedRows)) { ?>
            <div class="card shadow-sm mb-3 border-danger">
                <div class="card-header fw-bold text-danger">Detail Data Tidak Masuk ke Tally</div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height:500px;">
                        <table class="table table-bordered table-striped table-sm align-middle">
                            <thead class="table-danger sticky-top">
                                <tr>
                                    <th>No.</th>
                                    <th>Date</th>
                                    <th>VCH No.</th>
                                    <th>UNIQUEID</th>
                                    <th>ITEM_CODE</th>
                                    <th>Qty</th>
                                    <th>Informasi Tally</th>
                                    <th>Response Count</th>
                                    <th>Debug XML</th>
                                    <th>Debug Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failedRows as $fr) { ?>
                                    <tr>
                                        <td><?php echo h($fr['no']); ?></td>
                                        <td><?php echo h($fr['prod_date']); ?></td>
                                        <td><strong><?php echo h($fr['vch_no']); ?></strong></td>
                                        <td><?php echo h($fr['uniqueid']); ?></td>
                                        <td><b><?php echo h($fr['item_code']); ?></b></td>
                                        <td class="text-end"><?php echo h($fr['qty']); ?></td>
                                        <td style="min-width:280px;"><?php echo h($fr['error']); ?></td>
                                        <td><?php echo h($fr['counts']); ?></td>
                                        <td><small><?php echo h(str_replace(__DIR__, '', $fr['debug_xml'])); ?></small></td>
                                        <td><small><?php echo h(str_replace(__DIR__, '', $fr['debug_response'])); ?></small></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Data Production</div>
        <div class="card-body">
            <div class="table-responsive" style="max-height:500px;">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>UNIQUEID</th>
                            <th>VCH-NO</th>
                            <th>DATE</th>
                            <th>ITEM_CODE</th>
                            <th>DEST. QTY</th>
                            <th>DEST. RATE</th>
                            <th>DEST. AMOUNT</th>
                            <th>NARRATION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultRows as $r) { 
                            $rl = array_change_key_case($r, CASE_LOWER);
                        ?>
                        <tr>
                            <td><?php echo h(isset($rl['uniqueid']) ? $rl['uniqueid'] : ''); ?></td>
                            <td><b><?php echo h(isset($rl['vch_no']) ? $rl['vch_no'] : ''); ?></b></td>
                            <td><?php echo h(isset($rl['prod_date']) ? $rl['prod_date'] : ''); ?></td>
                            <td><b><?php echo h(isset($rl['item']) ? $rl['item'] : ''); ?></b></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_qty']) ? $rl['dest_qty'] : ''); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_rate']) ? rate5($rl['dest_rate']) : '0.00000'); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_amount']) ? $rl['dest_amount'] : ''); ?></td>
                            <td><?php echo h(isset($rl['narration']) ? $rl['narration'] : ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function setAction(a) {
    document.getElementById('action').value = a;
    document.getElementById('frmProd').submit();
}

function pilihServerTally() {
    var combo = document.getElementById('server_combo');
    if (!combo || combo.value == '') return;
    var p = combo.value.split('|');
    document.getElementById('tally_ip').value = p[0];
    document.getElementById('tally_port').value = p[1];
}

function showExportProgress() {
    var box = document.getElementById('exportProgressBox');
    var bar = document.getElementById('exportProgressBar');
    var txt = document.getElementById('exportProgressText');

    if (box) box.style.display = 'block';
    var pct = 5;
    if (bar) {
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        bar.style.width = pct + '%';
        bar.innerHTML = pct + '%';
    }
    if (txt) txt.innerHTML = 'Mohon tunggu, sedang memproses data Production ke Tally Server...';

    window._progressTimer = setInterval(function () {
        if (pct < 90) {
            pct += 5;
            if (bar) {
                bar.style.width = pct + '%';
                bar.innerHTML = pct + '%';
            }
            if (txt) {
                txt.innerHTML = 'Sedang mengirim data ke Tally... Mohon jangan tutup browser (' + pct + '%)';
            }
        }
    }, 800);
}

function confirmExport() {
    if (confirm('Kirim seluruh data Production ke Tally sekarang? Script XML akan langsung dikirim menggunakan format ITEM_CODE.')) {
        showExportProgress();
        setTimeout(function () {
            setAction('export');
        }, 200);
    }
}
</script>

</body>
</html>