<?php
// ============================================
// KONFIGURASI DATABASE
// ============================================
require_once __DIR__ . "/../config/database_ordering.php";

// ============================================
// AMBIL PARAMETER DARI REQUEST
// ============================================
 $currentYear  = date('Y');
 $currentMonth = date('n');

 $selectedYear   = isset($_GET['year'])      ? intval($_GET['year'])      : $currentYear;
 $selectedMonth  = isset($_GET['month'])     ? intval($_GET['month'])     : $currentMonth;
 $selectedCust   = isset($_GET['cust_code']) ? trim($_GET['cust_code']) : '';
 $isExport       = isset($_GET['export']) && $_GET['export'] === 'excel';

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = $currentMonth;
}

// ============================================
// HITUNG 3 BULAN SEBELUMNYA
// ============================================
 $lookbackMonths  = 3;
 $prevMonthsList  = array();
 $tm = $selectedMonth;
 $ty = $selectedYear;
for ($i = 0; $i < $lookbackMonths; $i++) {
    $tm--;
    if ($tm < 1) { $tm = 12; $ty--; }
    $prevMonthsList[] = array('month' => $tm, 'year' => $ty);
}

// ============================================
// PANGGIL STORED PROCEDURE (BULAN TERPILIH)
// ============================================
 $sql    = "{CALL sp_total_shoot_production_monthly(?, ?)}";
 $params = array(
    array($selectedYear,  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT),
    array($selectedMonth, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT)
);

 $stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
}

 $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

 $namaBulan = [
    1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
    4  => 'April',     5  => 'Mei',      6  => 'Juni',
    7  => 'Juli',      8  => 'Agustus',  9  => 'September',
    10 => 'Oktober',   11 => 'November', 12 => 'Desember'
];

// ============================================
// BACA DATA & GROUPING (BULAN TERPILIH)
// ============================================
 $groupedData = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custMatch = ($selectedCust === '') || (strtoupper(trim($row['CUST_CODE'])) === strtoupper($selectedCust));
    if ($custMatch) {
        $key = strtoupper(trim($row['CUST_CODE']) . '_' . trim($row['PART_CODE']));
        if (!isset($groupedData[$key])) {
            $groupedData[$key] = $row;
            $groupedData[$key]['total_production'] = floatval($row['total_production']);
            $groupedData[$key]['total_shoot']      = floatval($row['total_shoot']);
            $groupedData[$key]['PD_CAV']           = intval($row['PD_CAV']);
            for ($d = 1; $d <= 31; $d++) {
                $groupedData[$key]['d' . $d] = floatval($row['d' . $d]);
            }
        } else {
            $groupedData[$key]['total_production'] += floatval($row['total_production']);
            $groupedData[$key]['total_shoot']      += floatval($row['total_shoot']);
            $groupedData[$key]['PD_CAV']           = max($groupedData[$key]['PD_CAV'], intval($row['PD_CAV']));
            for ($d = 1; $d <= 31; $d++) {
                $groupedData[$key]['d' . $d] += floatval($row['d' . $d]);
            }
        }
    }
}
sqlsrv_free_stmt($stmt);

// ============================================
// AMBIL DATA 3 BULAN SEBELUMNYA
// Total production per part, lalu hitung shoot = prod/cavity
// Sisa = fmod(total_shoot_3bulan, 25000)
// ============================================
 $prevAggData = array(); // [key => ['total_prod' => X, 'cavity' => Y]]

foreach ($prevMonthsList as $pm) {
    $sqlP    = "{CALL sp_total_shoot_production_monthly(?, ?)}";
    $paramsP = array(
        array($pm['year'],  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT),
        array($pm['month'], SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT)
    );
    $stmtP = sqlsrv_query($conn, $sqlP, $paramsP);
    if ($stmtP) {
        while ($rp = sqlsrv_fetch_array($stmtP, SQLSRV_FETCH_ASSOC)) {
            $kp = strtoupper(trim($rp['CUST_CODE']) . '_' . trim($rp['PART_CODE']));
            if (!isset($prevAggData[$kp])) {
                $prevAggData[$kp] = array('total_prod' => 0, 'cavity' => 1);
            }
            $prevAggData[$kp]['total_prod'] += floatval($rp['total_production']);
            $prevAggData[$kp]['cavity'] = max($prevAggData[$kp]['cavity'], intval($rp['PD_CAV']));
        }
        sqlsrv_free_stmt($stmtP);
    }
}
sqlsrv_close($conn);

// Hitung sisa shoot per part dari 3 bulan sebelumnya
 $prevRemainder = array(); // [key => sisa_shoot]
 $prevTotalShoot = array(); // [key => total_shoot_3bulan] untuk info
foreach ($prevAggData as $kp => $pdata) {
    $cav = $pdata['cavity'] > 0 ? $pdata['cavity'] : 1;
    $totalShoot = $pdata['total_prod'] / $cav;
    $prevTotalShoot[$kp] = $totalShoot;
    $prevRemainder[$kp]  = fmod($totalShoot, 25000);
}

// Buat label bulan untuk tampilan
 $prevMonthsLabel = array();
foreach ($prevMonthsList as $pm) {
    $prevMonthsLabel[] = $namaBulan[$pm['month']] . ' ' . $pm['year'];
}

// ============================================
// HITUNG OH MARKERS
// 
// RUMUS: shoot = production / cavity
// Starting point = sisa shoot dari 3 bulan sebelumnya
// Setiap 25.000 → OH + RESET ke sisa
//
// Contoh: sisa dari 3 bulan lalu = 18.000, cavity = 2
//   tgl1: prod=4000  → shoot=2000  → running=20.000
//   tgl2: prod=6000  → shoot=3000  → running=23.000
//   tgl3: prod=5000  → shoot=2500  → running=25.500 → OH! reset→500
//   tgl4: prod=8000  → shoot=4000  → running=4.500
//   ...
// ============================================
 $ohMarkers       = array();
 $ohDayShoots     = array();
 $totalOHParts      = 0;
 $totalOHMilestones = 0;

foreach ($groupedData as $key => $row) {
    $cavity = intval($row['PD_CAV']);
    if ($cavity <= 0) $cavity = 1;

    // Mulai dari sisa 3 bulan sebelumnya
    $running = isset($prevRemainder[$key]) ? $prevRemainder[$key] : 0;
    $ohMarkers[$key]   = array();
    $ohDayShoots[$key] = array();

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dayProd  = floatval($row['d' . $d]);
        $dayShoot = $dayProd / $cavity;
        $ohDayShoots[$key][$d] = $dayShoot;

        $running += $dayShoot;

        $ohInDay = 0;
        while ($running >= 25000) {
            $ohInDay++;
            $running -= 25000;
        }

        if ($ohInDay > 0) {
            $ohMarkers[$key][$d] = $ohInDay;
        }
    }

    $cnt = count($ohMarkers[$key]);
    if ($cnt > 0) {
        $totalOHParts++;
        $totalOHMilestones += $cnt;
    }
}

// ============================================
// GRAND TOTAL
// ============================================
 $dataRows             = array_values($groupedData);
 $rowCount             = count($dataRows);
 $grandTotalProduction = 0;
 $grandTotalShoot      = 0;
 $dayTotals            = array_fill(1, 31, 0);

foreach ($dataRows as $row) {
    $grandTotalProduction += $row['total_production'];
    $grandTotalShoot      += $row['total_shoot'];
    for ($d = 1; $d <= 31; $d++) {
        $dayTotals[$d] += $row['d' . $d];
    }
}

// ============================================
// HELPER
// ============================================
function formatNumber($num) {
    if ($num == 0) return '-';
    return number_format($num, 0, ',', '.');
}
function formatShoot($num) {
    if ($num == 0 || $num == null) return '-';
    return number_format($num, 2, ',', '.');
}

// ============================================
// EXPORT HEADER
// ============================================
if ($isExport) {
    $filename = "Total_Shoot_Production_" . $selectedYear . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>

<?php if (!$isExport): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total Shoot Production Monthly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Plus Jakarta Sans', sans-serif;
            --accent: #2563eb;
            --accent-light: #dbeafe;
            --accent-dark: #1d4ed8;
            --bg-page: #f5f5f5;
            --bg-card: #ffffff;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --text-zero: #d1d5db;
            --danger: #dc2626;
            --danger-light: #fee2e2;
            --danger-border: #fca5a5;
            --danger-bg: #fef2f2;
            --danger-dot: #ef4444;
            --amber: #d97706;
            --amber-light: #fef3c7;
            --amber-border: #fcd34d;
            --amber-bg: #fffbeb;
        }
        body { background-color: var(--bg-page); color: var(--text-primary); font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0f0f0; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }

        .navbar-custom { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.04); }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; transition: all 200ms ease; }
        .stat-card:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(37,99,235,0.1); transform: translateY(-2px); }
        .stat-card .stat-icon { width: 36px; height: 36px; border-radius: 8px; background: var(--accent-light); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .stat-card .stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-secondary); }
        .stat-card .stat-value { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .stat-card .stat-sub { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; }

        .stat-card-oh { background: var(--danger-bg); border-color: var(--danger-border); }
        .stat-card-oh:hover { border-color: var(--danger); box-shadow: 0 4px 12px rgba(220,38,38,0.15); }
        .stat-card-oh .stat-icon { background: var(--danger-light); color: var(--danger); }
        .stat-card-oh .stat-label { color: var(--danger); }
        .stat-card-oh .stat-value { color: var(--danger); }
        .stat-card-oh .stat-sub { color: var(--danger-dot); }

        .stat-card-base { background: var(--amber-bg); border-color: var(--amber-border); }
        .stat-card-base:hover { border-color: var(--amber); box-shadow: 0 4px 12px rgba(217,119,6,0.15); }
        .stat-card-base .stat-icon { background: var(--amber-light); color: var(--amber); }
        .stat-card-base .stat-label { color: var(--amber); }
        .stat-card-base .stat-sub { color: var(--amber); }

        .filter-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px 24px; }
        .filter-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-secondary); margin-bottom: 6px; }
        .form-select, .form-control { border: 1px solid var(--border-color); font-size: 13px; padding: 8px 12px; border-radius: 8px; transition: border-color 150ms, box-shadow 150ms; }
        .form-select:focus, .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .form-control::placeholder { color: var(--text-muted); }
        .btn-accent { background: var(--accent); color: #fff; font-weight: 600; font-size: 13px; border: none; padding: 8px 20px; border-radius: 8px; transition: all 200ms ease; }
        .btn-accent:hover { background: var(--accent-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-secondary); font-size: 12px; font-weight: 500; padding: 6px 12px; border-radius: 8px; background: var(--bg-card); transition: all 150ms ease; }
        .btn-outline-custom:hover { border-color: #999; color: var(--text-primary); background: #f9fafb; }
        .quick-link { font-size: 12px; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding-bottom: 10px; transition: color 150ms; }
        .quick-link:hover { color: var(--accent); }
        .filter-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-light); border: 1px solid rgba(37,99,235,0.3); color: var(--accent-dark); font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-family: monospace; }
        .filter-badge .btn-close-badge { background: none; border: none; color: var(--accent-dark); cursor: pointer; padding: 0; font-size: 14px; line-height: 1; opacity: 0.5; transition: opacity 150ms; }
        .filter-badge .btn-close-badge:hover { opacity: 1; }

        .table-wrapper { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; }
        .table-wrapper .table-scroll { overflow-x: auto; max-height: 70vh; overflow-y: auto; }

        .table-shoot { width: 100%; border-collapse: collapse; font-size: 12px; margin: 0; }
        .table-shoot thead th { background: #f9fafb; color: var(--text-secondary); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; padding: 12px 10px; border-bottom: 2px solid var(--border-color); position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .table-shoot thead th.day-col { text-align: center; min-width: 55px; color: var(--text-muted); }
        .table-shoot thead th.day-col.active { color: var(--accent); background: var(--accent-light); }
        .table-shoot thead th.total-col { text-align: right; min-width: 100px; background: var(--accent-light); color: var(--accent-dark); border-left: 2px solid rgba(37,99,235,0.15); }

        .table-shoot tbody tr { transition: background 100ms ease; }
        .table-shoot tbody tr:hover { background: #f9fafb; }
        .table-shoot tbody tr.row-hidden { display: none; }
        .table-shoot tbody td { padding: 10px; border-bottom: 1px solid #f3f4f6; color: var(--text-primary); white-space: nowrap; }
        .table-shoot tbody td.text-center { text-align: center; }
        .table-shoot tbody td.num-zero { color: var(--text-zero); }
        .table-shoot tbody td.num-val { color: var(--text-primary); font-variant-numeric: tabular-nums; }
        .table-shoot tbody td.num-highlight { color: var(--accent-dark); font-weight: 700; font-variant-numeric: tabular-nums; }
        .table-shoot tbody td.total-col { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; border-left: 2px solid rgba(37,99,235,0.08); background: rgba(219,234,254,0.3); }
        .table-shoot tbody td.total-col.highlight { color: var(--accent-dark); font-weight: 700; }

        /* ===== SEL OH ===== */
        .table-shoot tbody td.oh-marked {
            background: var(--danger-bg) !important;
            color: var(--danger) !important;
            font-weight: 800 !important;
            font-variant-numeric: tabular-nums;
            position: relative;
            border-left: 3px solid var(--danger-dot) !important;
            border-bottom: 1px solid var(--danger-border) !important;
        }
        .oh-cell-badge {
            position: absolute;
            top: 1px;
            right: 2px;
            background: var(--danger);
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 3px;
            line-height: 1.4;
            letter-spacing: 0.02em;
            box-shadow: 0 1px 4px rgba(220,38,38,0.4);
            z-index: 2;
        }

        .table-shoot tbody tr.row-oh-active > td:first-child {
            box-shadow: inset 3px 0 0 var(--danger-dot);
        }

        .table-shoot tfoot td { padding: 12px 10px; border-top: 2px solid rgba(37,99,235,0.25); background: var(--accent-light); font-size: 12px; font-weight: 700; color: var(--accent-dark); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .table-shoot tfoot td.text-center { text-align: center; }
        .table-shoot tfoot td.total-col { text-align: right; border-left: 2px solid rgba(37,99,235,0.15); }

        .cust-code-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; background: #f3f4f6; color: var(--text-secondary); font-family: monospace; font-size: 11px; }

        /* Tooltip */
        .oh-cell-wrap { position: relative; }
        .oh-cell-wrap .oh-cell-tip {
            display: none;
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #f9fafb;
            font-size: 10px;
            font-weight: 500;
            padding: 7px 11px;
            border-radius: 6px;
            white-space: nowrap;
            z-index: 60;
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
            pointer-events: none;
            line-height: 1.6;
        }
        .oh-cell-wrap .oh-cell-tip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1f2937;
        }
        .oh-cell-wrap:hover .oh-cell-tip { display: block; }

        /* Autocomplete */
        .autocomplete-wrap { position: relative; }
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 4px; max-height: 260px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05); }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 9px 14px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: background 100ms ease; border-bottom: 1px solid #f3f4f6; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active { background: var(--accent-light); }
        .autocomplete-item .code { font-family: monospace; font-size: 12px; font-weight: 600; color: var(--accent-dark); min-width: 100px; }
        .autocomplete-item .name { font-size: 12px; color: var(--text-primary); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .autocomplete-item .abbr { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .autocomplete-empty { padding: 20px 14px; text-align: center; color: var(--text-muted); font-size: 12px; }
        .autocomplete-loading { padding: 16px 14px; text-align: center; color: var(--text-muted); font-size: 12px; }
        .autocomplete-loading::after { content: ''; display: inline-block; width: 12px; height: 12px; border: 2px solid #e5e7eb; border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; margin-left: 8px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .toast-custom { position: fixed; bottom: 24px; right: 24px; background: var(--text-primary); color: #fff; border-radius: 10px; padding: 12px 20px; font-size: 13px; z-index: 200; transform: translateY(100px); opacity: 0; transition: all 400ms ease; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .toast-custom.show { transform: translateY(0); opacity: 1; }

        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state .empty-icon { width: 56px; height: 56px; border-radius: 14px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px; color: var(--text-muted); }

        .footer-info { font-size: 11px; color: var(--text-muted); }
        .page-subtitle { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: var(--accent); }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.02em; }
        @media (min-width: 768px) { .page-title { font-size: 2.75rem; } }

        .search-wrap { position: relative; }
        .search-wrap .bi { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; pointer-events: none; }
        .search-wrap .form-control { padding-left: 34px; width: 220px; font-size: 12px; }

        .oh-info-banner { display: flex; align-items: flex-start; gap: 8px; padding: 10px 16px; background: var(--danger-bg); border: 1px solid var(--danger-border); border-radius: 8px; font-size: 12px; color: var(--danger); margin-bottom: 12px; line-height: 1.6; }
        .oh-info-banner i { font-size: 14px; flex-shrink: 0; margin-top: 2px; }
        .oh-info-banner strong { font-weight: 700; }
        .oh-formula { display: inline-block; background: rgba(220,38,38,0.08); border: 1px solid var(--danger-border); border-radius: 4px; padding: 1px 6px; font-family: monospace; font-size: 11px; }

        .base-info-banner { display: flex; align-items: flex-start; gap: 8px; padding: 10px 16px; background: var(--amber-bg); border: 1px solid var(--amber-border); border-radius: 8px; font-size: 12px; color: var(--amber); margin-bottom: 12px; line-height: 1.6; }
        .base-info-banner i { font-size: 14px; flex-shrink: 0; margin-top: 2px; }
        .base-info-banner strong { font-weight: 700; }
        .base-months { display: inline-flex; gap: 4px; flex-wrap: wrap; }
        .base-month-tag { display: inline-block; background: rgba(217,119,6,0.1); border: 1px solid var(--amber-border); border-radius: 4px; padding: 1px 7px; font-size: 10px; font-weight: 700; font-family: monospace; }

        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .navbar-custom { display: none !important; }
            .filter-section { display: none !important; }
            .oh-info-banner, .base-info-banner { display: none !important; }
            .table-wrapper { border: 1px solid #ddd; }
            .table-shoot thead th { background: #f3f3f3 !important; color: #333 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot thead th.total-col { background: #e0e7ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot tbody td { color: #333 !important; border-color: #ddd !important; }
            .table-shoot tbody td.total-col { background: #eff6ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot tbody td.oh-marked { background: #fee2e2 !important; color: #dc2626 !important; border-left: 3px solid #ef4444 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .oh-cell-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot tfoot td { background: #eff6ff !important; color: #1d4ed8 !important; border-color: #bfdbfe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .stat-card { border: 1px solid #ddd; background: #fff !important; }
            .stat-card-oh { background: #fef2f2 !important; border-color: #fca5a5 !important; }
            .stat-card-base { background: #fffbeb !important; border-color: #fcd34d !important; }
            .table-shoot tbody tr.row-oh-active > td:first-child { box-shadow: inset 3px 0 0 #ef4444; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom fixed-top no-print" style="z-index:1040;">
        <div class="container-fluid" style="max-width:1800px;padding:0 24px;">
            <div class="d-flex align-items-center justify-content-between w-100 py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-2" style="width:32px;height:32px;background:var(--accent);color:#fff;font-size:14px;"><i class="bi bi-hammer"></i></div>
                    <span class="fw-bold" style="font-size:13px;letter-spacing:-0.01em;">SHOOT PRODUCTION</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="?year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>&cust_code=<?= urlencode($selectedCust) ?>&export=excel" class="btn btn-success btn-sm d-flex align-items-center gap-1 text-white"><i class="bi bi-file-earmark-excel" style="font-size:13px;"></i> Export Excel</a>
                    <button onclick="window.print()" class="btn btn-outline-custom d-flex align-items-center gap-1"><i class="bi bi-printer" style="font-size:13px;"></i> Print</button>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid" style="max-width:1800px;padding:0 24px;padding-top:80px;padding-bottom:48px;">

        <div class="mb-4">
            <p class="page-subtitle mb-2">Monthly Report</p>
            <h1 class="page-title mb-2">Total Shoot Production</h1>
            <p style="color:var(--text-secondary);font-size:14px;max-width:500px;">Rekapitulasi harian shoot & production per item berdasarkan bulan dan tahun yang dipilih.</p>
        </div>

        <form method="GET" action="" id="filterForm" class="no-print mb-4">
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="filter-label d-block">Tahun</label>
                        <select name="year" id="yearSelect" class="form-select" style="min-width:130px;">
                            <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) { $sel = ($y == $selectedYear) ? 'selected' : ''; echo "<option value=\"$y\" $sel>$y</option>"; } ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="filter-label d-block">Bulan</label>
                        <select name="month" id="monthSelect" class="form-select" style="min-width:170px;">
                            <?php for ($m = 1; $m <= 12; $m++) { $sel = ($m == $selectedMonth) ? 'selected' : ''; echo "<option value=\"$m\" $sel>" . $namaBulan[$m] . "</option>"; } ?>
                        </select>
                    </div>
                    <div class="col" style="min-width:280px;">
                        <label class="filter-label d-block">Customer</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="custInput" placeholder="Ketik kode / nama customer..." autocomplete="off" value="<?= htmlspecialchars($selectedCust) ?>" class="form-control">
                            <input type="hidden" name="cust_code" id="custCodeHidden" value="<?= htmlspecialchars($selectedCust) ?>">
                            <div class="autocomplete-list" id="custDropdown"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <label class="filter-label d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-accent d-flex align-items-center gap-2"><i class="bi bi-funnel" style="font-size:14px;"></i> Tampilkan</button>
                    </div>
                    <div class="col-auto d-flex align-items-end gap-3 pb-1">
                        <a href="?year=<?= $currentYear ?>&month=<?= $currentMonth ?>" class="quick-link"><i class="bi bi-calendar-check" style="font-size:13px;"></i> Bulan Ini</a>
                        <a href="?year=<?= ($selectedMonth == 1 ? $selectedYear - 1 : $selectedYear) ?>&month=<?= $selectedMonth == 1 ? 12 : $selectedMonth - 1 ?>" class="quick-link"><i class="bi bi-chevron-left" style="font-size:13px;"></i> Bulan Lalu</a>
                    </div>
                </div>
                <?php if ($selectedCust !== ''): ?>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <span class="filter-label mb-0">Filter aktif:</span>
                    <span class="filter-badge"><i class="bi bi-person" style="font-size:11px;"></i> <?= htmlspecialchars($selectedCust) ?> <button type="button" class="btn-close-badge" onclick="clearCustFilter()" title="Hapus filter customer"><i class="bi bi-x" style="font-size:12px;"></i></button></span>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-box-seam"></i></div><span class="stat-label">Total Production</span></div>
                    <p class="stat-value"><?= formatNumber($grandTotalProduction) ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-crosshair"></i></div><span class="stat-label">Total Shoot</span></div>
                    <p class="stat-value"><?= formatShoot($grandTotalShoot) ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-layers"></i></div><span class="stat-label">Jumlah Part</span></div>
                    <p class="stat-value" id="visibleItemCount"><?= number_format($rowCount, 0, ',', '.') ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-card-oh">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><span class="stat-label">Over Hours</span></div>
                    <p class="stat-value"><?= number_format($totalOHMilestones, 0, ',', '.') ?></p>
                    <p class="stat-sub"><?= number_format($totalOHParts, 0, ',', '.') ?> part &middot; reset tiap 25rb</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-calendar3"></i></div><span class="stat-label">Periode</span></div>
                    <p class="stat-value"><?= $namaBulan[$selectedMonth] ?></p>
                    <p class="stat-sub"><?= $selectedYear ?> &middot; <?= $daysInMonth ?> hari</p>
                </div>
            </div>
            <div class="col-6 col-lg-9">
                <div class="stat-card stat-card-base">
                    <div class="d-flex align-items-center gap-2 mb-2"><div class="stat-icon"><i class="bi bi-clock-history"></i></div><span class="stat-label">Base Akumulasi (3 Bulan Sebelumnya)</span></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php foreach ($prevMonthsList as $idx => $pm): ?>
                            <span class="base-month-tag"><?= $namaBulan[$pm['month']] . ' ' . $pm['year'] ?></span>
                            <?php if ($idx < count($prevMonthsList) - 1): ?>
                                <span style="color:var(--amber);font-size:12px;">+</span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="stat-sub" style="margin-top:6px;">Shoot = Production &divide; Cavity &middot; Sisa yang belum 25.000 dibawa ke bulan ini</p>
                </div>
            </div>
        </div>

        <?php if ($totalOHMilestones > 0): ?>
        <div class="oh-info-banner no-print">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                <strong><?= number_format($totalOHMilestones, 0, ',', '.') ?> tanda OH</strong> ditemukan — 
                Rumus: <span class="oh-formula">shoot = production &divide; cavity</span>, 
                akumulasi dimulai dari <strong>sisa shoot 3 bulan sebelumnya</strong>, 
                setiap <strong>25.000 shoot → tanda merah, reset ke sisa</strong>, hitung ulang.
            </span>
        </div>
        <?php endif; ?>

        <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 style="font-size:14px;font-weight:600;color:var(--text-primary);margin:0;">
                    Detail Harian
                    <span style="color:var(--text-muted);font-weight:400;margin-left:8px;"><?= $namaBulan[$selectedMonth] ?> <?= $selectedYear ?></span>
                </h2>
                <div class="no-print search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari part..." class="form-control">
                </div>
            </div>
<?php endif; ?>

            <?php if ($isExport): ?>
                <table border="0">
                    <tr><th colspan="37" style="font-size:16px;text-align:left;">TOTAL SHOOT PRODUCTION - <?= strtoupper($namaBulan[$selectedMonth]) ?> <?= $selectedYear ?></th></tr>
                    <?php if ($selectedCust): ?>
                    <tr><th colspan="37" style="text-align:left;">CUSTOMER: <?= htmlspecialchars($selectedCust) ?></th></tr>
                    <?php endif; ?>
                    <tr><th colspan="37" style="text-align:left;font-size:11px;color:#666;">OH = shoot (prod/cavity), akumulasi dari sisa <?= implode(' + ', $prevMonthsLabel) ?>, setiap 25.000 → reset</th></tr>
                    <tr><th colspan="37"></th></tr>
                </table>
            <?php endif; ?>

            <div class="table-wrapper">
                <div class="table-scroll">
                    <?php if ($rowCount > 0): ?>
                    <table class="table-shoot" id="mainTable" <?= $isExport ? 'border="1"' : '' ?>>
                        <thead>
                            <tr>
                                <th class="text-start" style="min-width:45px;<?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">No</th>
                                <th class="text-start" style="min-width:100px;<?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">Cust Code</th>
                                <th class="text-start" style="min-width:120px;<?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">Part Code</th>
                                <th class="text-start" style="min-width:220px;<?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">Part Name</th>
                                <th class="text-center" style="min-width:50px;<?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">Cav</th>
                                <th class="total-col" <?= $isExport ? 'style="background-color:#dbeafe;"' : '' ?>>Total Prod.</th>
                                <th class="total-col" <?= $isExport ? 'style="background-color:#dbeafe;"' : '' ?>>Total Shoot</th>
                                <th class="text-center" style="min-width:70px;background:var(--amber-bg);color:var(--amber);border-left:2px solid var(--amber-border);<?= $isExport ? 'background-color:#fffbeb;color:#d97706;border-left:2px solid #fcd34d;' : '' ?>">
                                    <?php if (!$isExport): ?>
                                    <i class="bi bi-clock-history" style="font-size:10px;"></i>
                                    <?php endif; ?>
                                    Sisa
                                </th>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <?php if ($d <= $daysInMonth): ?>
                                        <th class="day-col active" <?= $isExport ? 'style="background-color:#e2e8f0;"' : '' ?>><?= $d ?></th>
                                    <?php else: ?>
                                        <th class="day-col" style="<?= $isExport ? 'background-color:#e2e8f0;' : 'opacity:0.25;' ?>"><?= $d ?></th>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php
                            $no = 0;
                            foreach ($dataRows as $row):
                                $no++;
                                $custCode    = strtoupper(trim($row['CUST_CODE']));
                                $ohKey       = strtoupper(trim($row['CUST_CODE']) . '_' . trim($row['PART_CODE']));
                                $rowMarkers  = isset($ohMarkers[$ohKey]) ? $ohMarkers[$ohKey] : array();
                                $rowShoots   = isset($ohDayShoots[$ohKey]) ? $ohDayShoots[$ohKey] : array();
                                $rowHasOH    = count($rowMarkers) > 0;
                                $rowOhClass  = $rowHasOH ? ' row-oh-active' : '';
                                $cavity      = intval($row['PD_CAV']);
                                if ($cavity <= 0) $cavity = 1;
                                $sisaAwal    = isset($prevRemainder[$ohKey]) ? $prevRemainder[$ohKey] : 0;
                                $totalShootPrev = isset($prevTotalShoot[$ohKey]) ? $prevTotalShoot[$ohKey] : 0;
                            ?>
                            <tr data-search="<?= strtolower($row['CUST_CODE'] . ' ' . $row['PART_CODE'] . ' ' . $row['PART_NAME']) ?>" data-cust="<?= $custCode ?>" class="<?= $rowOhClass ?>">
                                <td style="color:var(--text-muted);font-size:11px;"><?= $no ?></td>
                                <td><span class="cust-code-badge"><?= htmlspecialchars($row['CUST_CODE']) ?></span></td>
                                <td style="font-family:monospace;font-size:11px;color:var(--text-secondary);<?= $isExport ? "mso-number-format:'\\@';" : '' ?>"><?= htmlspecialchars($row['PART_CODE']) ?></td>
                                <td style="font-weight:500;font-size:12px;"><?= htmlspecialchars($row['PART_NAME']) ?></td>
                                <td class="text-center" style="color:var(--text-secondary);"><?= $cavity ?></td>

                                <td class="total-col num-val" <?= $isExport ? 'style="background-color:#eff6ff;mso-number-format:\'\\#\\,\\#\\#0\';"' : '' ?>>
                                    <?= $isExport ? (floatval($row['total_production']) ?: '-') : formatNumber($row['total_production']) ?>
                                </td>
                                <td class="total-col highlight num-highlight" <?= $isExport ? 'style="background-color:#eff6ff;mso-number-format:\'\\#\\,\\#\\#0\\.00\';"' : '' ?>>
                                    <?= $isExport ? (floatval($row['total_shoot']) ?: '-') : formatShoot($row['total_shoot']) ?>
                                </td>

                                <!-- KOLOM SISA -->
                                <td class="text-center" style="border-left:2px solid rgba(217,119,6,0.15);background:rgba(255,251,235,0.5);font-variant-numeric:tabular-nums;<?php
                                    if ($sisaAwal > 0) {
                                        echo 'color:var(--amber);font-weight:700;';
                                    } else {
                                        echo 'color:var(--text-zero);';
                                    }
                                    if ($isExport) {
                                        echo 'background-color:#fffbeb;border-left:2px solid #fcd34d;';
                                        if ($sisaAwal > 0) {
                                            echo 'color:#d97706;font-weight:bold;';
                                        } else {
                                            echo 'color:#d1d5db;';
                                        }
                                        echo "mso-number-format:'\\#\\,\\#\\#0\\.00';";
                                    }
                                ?>">
                                    <?= $isExport ? ($sisaAwal > 0 ? number_format($sisaAwal, 2, ',', '.') : '-') : ($sisaAwal > 0 ? number_format($sisaAwal, 0, ',', '.') : '-') ?>
                                </td>

                                <?php
                                $tipRunning = $sisaAwal;

                                for ($d = 1; $d <= 31; $d++):
                                    $val      = floatval($row['d' . $d]);
                                    $ohMarker = isset($rowMarkers[$d]) ? $rowMarkers[$d] : 0;
                                    $dayShoot = isset($rowShoots[$d]) ? $rowShoots[$d] : 0;

                                    $tipBeforeDay = $tipRunning;
                                    $tipRunning  += $dayShoot;
                                    $tipAfterReset = $tipRunning;
                                    if ($ohMarker > 0) {
                                        $tipAfterReset = $tipRunning - ($ohMarker * 25000);
                                    }

                                    $cssClass    = 'text-center ';
                                    $inlineStyle = '';

                                    if ($d > $daysInMonth) {
                                        $cssClass .= 'num-zero';
                                        $display = '-';
                                        if (!$isExport) $inlineStyle = 'opacity:0.25;';
                                    } elseif ($val == 0 && $ohMarker == 0) {
                                        $cssClass .= 'num-zero';
                                        $display = '-';
                                    } elseif ($ohMarker > 0) {
                                        $cssClass .= 'oh-marked';
                                        $display = $isExport ? $val : formatNumber($val);
                                        if ($isExport) {
                                            $inlineStyle = "background-color:#fee2e2;color:#dc2626;font-weight:bold;border-left:3px solid #ef4444;mso-number-format:'\\#\\,\\#\\#0';";
                                        }
                                    } else {
                                        $cssClass .= 'num-val';
                                        $display = $isExport ? $val : formatNumber($val);
                                        if ($isExport) {
                                            $inlineStyle = "mso-number-format:'\\#\\,\\#\\#0';";
                                        }
                                    }
                                ?>
                                <td class="<?= $cssClass ?>" <?= $inlineStyle ? 'style="'.$inlineStyle.'"' : '' ?>>
                                    <?php if (!$isExport && $ohMarker > 0): ?>
                                        <div class="oh-cell-wrap">
                                            <span class="oh-cell-badge">+<?= $ohMarker ?></span>
                                            <div class="oh-cell-tip">
                                                <strong>Over Hours +<?= $ohMarker ?></strong><br>
                                                Sisa dari 3 bln: <strong><?= number_format($sisaAwal, 0, ',', '.') ?></strong> shoot<br>
                                                Prod tgl <?= $d ?>: <strong><?= number_format($val, 0, ',', '.') ?></strong> &divide; <?= $cavity ?> = <strong><?= number_format($dayShoot, 2, ',', '.') ?></strong><br>
                                                Akumulasi: <strong><?= number_format($tipBeforeDay, 2, ',', '.') ?></strong> + <?= number_format($dayShoot, 2, ',', '.') ?> = <strong><?= number_format($tipBeforeDay + $dayShoot, 2, ',', '.') ?></strong><br>
                                                Sisa setelah reset: <strong><?= number_format($tipAfterReset, 2, ',', '.') ?></strong>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?= $display ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot id="tableFoot">
                            <tr>
                                <td colspan="6" class="text-start" style="font-weight:800;<?= $isExport ? 'background-color:#dbeafe;' : '' ?>">GRAND TOTAL</td>
                                <td class="total-col" <?= $isExport ? 'style="background-color:#dbeafe;mso-number-format:\'\\#\\,\\#\\#0\\.00\';"' : '' ?>>
                                    <?= $isExport ? ($grandTotalShoot ?: '-') : formatShoot($grandTotalShoot) ?>
                                </td>
                                <td class="text-center" style="border-left:2px solid var(--amber-border);<?= $isExport ? 'background-color:#fffbeb;' : 'background:var(--amber-bg);' ?>color:var(--text-muted);">-</td>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <?php
                                        $val = $dayTotals[$d];
                                        $inlineStyle = $isExport ? 'background-color:#dbeafe;' : '';
                                        if ($d > $daysInMonth) {
                                            $display = '-';
                                            if (!$isExport) $inlineStyle .= ' opacity:0.25;';
                                        } elseif ($val == 0) {
                                            $display = '-';
                                        } else {
                                            $display = $isExport ? $val : formatNumber($val);
                                            if ($isExport) $inlineStyle .= " mso-number-format:'\\#\\,\\#\\#0';";
                                        }
                                    ?>
                                    <td class="text-center" style="<?= $inlineStyle ?>"><?= $display ?></td>
                                <?php endfor; ?>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                        <?php if (!$isExport): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                            <p style="color:var(--text-secondary);font-size:14px;margin:0;">Tidak ada data untuk periode ini.</p>
                            <p style="color:var(--text-muted);font-size:12px;margin:4px 0 0;">Coba pilih bulan atau tahun yang lain.</p>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

<?php if (!$isExport): ?>
        </div>

        <div class="footer-info no-print d-flex flex-wrap align-items-center justify-content-between mt-3">
            <p class="mb-0">Dicetak: <?= date('d M Y H:i:s') ?> &middot; <span id="footerRowCount"><?= $rowCount ?></span> baris &middot; <span style="color:var(--danger);"><?= number_format($totalOHMilestones, 0, ',', '.') ?></span> tanda OH &middot; Base: <?= implode(' + ', $prevMonthsLabel) ?></p>
            <p class="mb-0">sp_total_shoot_production_monthly &middot; @year=<?= $selectedYear ?> &middot; @month=<?= $selectedMonth ?><?= $selectedCust ? ' &middot; @cust=' . htmlspecialchars($selectedCust) : '' ?></p>
        </div>

    </main>

    <div id="toast" class="toast-custom no-print"><span id="toastMsg"></span></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const custInput = document.getElementById('custInput');
        const custHidden = document.getElementById('custCodeHidden');
        const custDropdown = document.getElementById('custDropdown');
        let acTimeout = null, acActiveIndex = -1;

        custInput.addEventListener('input', function() {
            const q = this.value.trim(); custHidden.value = ''; acActiveIndex = -1;
            if (q.length < 1) { hideDropdown(); return; }
            clearTimeout(acTimeout); acTimeout = setTimeout(() => fetchCustomers(q), 300);
        });
        custInput.addEventListener('focus', function() { const q = this.value.trim(); if (q.length >= 1) fetchCustomers(q); });
        custInput.addEventListener('keydown', function(e) {
            const items = custDropdown.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); acActiveIndex = Math.min(acActiveIndex + 1, items.length - 1); highlightItem(items); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); acActiveIndex = Math.max(acActiveIndex - 1, 0); highlightItem(items); }
            else if (e.key === 'Enter') { e.preventDefault(); if (acActiveIndex >= 0 && items[acActiveIndex]) selectCustomer(items[acActiveIndex]); else { custHidden.value = custInput.value.trim(); document.getElementById('filterForm').submit(); } }
            else if (e.key === 'Escape') { hideDropdown(); custInput.blur(); }
        });
        document.addEventListener('click', function(e) { if (!e.target.closest('.autocomplete-wrap')) hideDropdown(); });

        function fetchCustomers(q) {
            custDropdown.innerHTML = '<div class="autocomplete-loading">Mencari...</div>'; custDropdown.classList.add('show');
            const fd = new FormData(); fd.append('q', q);
            fetch('search_cust.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (!data || !data.length) { custDropdown.innerHTML = '<div class="autocomplete-empty">Customer tidak ditemukan</div>'; return; }
                let h = ''; data.forEach(i => { h += `<div class="autocomplete-item" data-code="${esc(i.CUST_CODE)}" onclick="selectCustomer(this)"><span class="code">${esc(i.CUST_CODE)}</span><span class="name">${esc(i.CUST_COMP)}</span>${i.CUST_ABBR ? '<span class="abbr">' + esc(i.CUST_ABBR) + '</span>' : ''}</div>`; });
                custDropdown.innerHTML = h;
            }).catch(() => { custDropdown.innerHTML = '<div class="autocomplete-empty">Gagal memuat data</div>'; });
        }
        function selectCustomer(el) { custInput.value = el.getAttribute('data-code') + ' - ' + (el.querySelector('.name') ? el.querySelector('.name').textContent : ''); custHidden.value = el.getAttribute('data-code'); hideDropdown(); document.getElementById('filterForm').submit(); }
        function highlightItem(items) { items.forEach((it, i) => it.classList.toggle('active', i === acActiveIndex)); if (items[acActiveIndex]) items[acActiveIndex].scrollIntoView({ block: 'nearest' }); }
        function hideDropdown() { custDropdown.classList.remove('show'); custDropdown.innerHTML = ''; acActiveIndex = -1; }
        function clearCustFilter() { custInput.value = ''; custHidden.value = ''; document.getElementById('filterForm').submit(); }
        function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const q = this.value.toLowerCase().trim();
                document.querySelectorAll('#tableBody tr').forEach(r => { r.style.display = (q === '' || (r.getAttribute('data-search') || '').includes(q)) ? '' : 'none'; });
                recalcVisible();
            });
        }
        function recalcVisible() {
            let c = 0; document.querySelectorAll('#tableBody tr:not(.row-hidden)').forEach(r => { if (r.style.display !== 'none') c++; });
            document.getElementById('visibleItemCount').textContent = c.toLocaleString('id-ID');
            document.getElementById('footerRowCount').textContent = c.toLocaleString('id-ID');
        }
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') document.getElementById('filterForm').submit();
            if (e.ctrlKey && e.key === 'f') { e.preventDefault(); if (searchInput) searchInput.focus(); }
        });
    </script>
</body>
</html>
<?php endif; ?>