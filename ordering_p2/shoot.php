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

 $selectedYear   = isset($_GET['year'])     ? intval($_GET['year'])     : $currentYear;
 $selectedMonth  = isset($_GET['month'])    ? intval($_GET['month'])    : $currentMonth;
 $selectedCust   = isset($_GET['cust_code']) ? trim($_GET['cust_code']) : '';

// Validasi bulan
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = $currentMonth;
}

// ============================================
// PANGGIL STORED PROCEDURE
// ============================================
 $sql    = "{CALL sp_total_shoot_delivery_monthly(?, ?)}";
 $params = array(
    array($selectedYear,  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT),
    array($selectedMonth, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT)
);

 $stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
}

// ============================================
// AMBIL JUMLAH HARI DALAM BULAN TERSEBUT
// ============================================
 $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

// Nama bulan dalam Bahasa Indonesia
 $namaBulan = [
    1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
    4  => 'April',     5  => 'Mei',      6  => 'Juni',
    7  => 'Juli',      8  => 'Agustus',   9  => 'September',
    10 => 'Oktober',   11 => 'November', 12 => 'Desember'
];

// ============================================
// BACA DATA & HITUNG GRAND TOTAL
// ============================================
 $grandTotalDelivery = 0;
 $grandTotalShoot    = 0;
 $dayTotals          = array_fill(1, 31, 0);
 $rowCount           = 0;
 $dataRows           = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = $row;
    $rowCount++;

    $custMatch = ($selectedCust === '') || (strtoupper(trim($row['CUST_CODE'])) === strtoupper($selectedCust));

    if ($custMatch) {
        $grandTotalDelivery += floatval($row['total_delivery']);
        $grandTotalShoot    += floatval($row['total_shoot']);
        for ($d = 1; $d <= 31; $d++) {
            $dayTotals[$d] += floatval($row['d' . $d]);
        }
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// ============================================
// FUNGSI HELPER
// ============================================
function formatNumber($num) {
    if ($num == 0) return '-';
    return number_format($num, 0, ',', '.');
}

function formatShoot($num) {
    if ($num == 0 || $num == null) return '-';
    return number_format($num, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total Shoot Delivery Monthly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Plus Jakarta Sans', sans-serif;
            --accent: #059669;
            --accent-light: #d1fae5;
            --accent-dark: #047857;
            --bg-page: #f5f5f5;
            --bg-card: #ffffff;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --text-zero: #d1d5db;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-primary);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0f0f0; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }

        .navbar-custom {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 200ms ease;
        }
        .stat-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(5,150,105,0.1);
            transform: translateY(-2px);
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--accent-light);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .stat-card .stat-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-secondary);
        }
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .filter-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 24px;
        }
        .filter-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-select,
        .form-control {
            border: 1px solid var(--border-color);
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: border-color 150ms, box-shadow 150ms;
        }
        .form-select:focus,
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5,150,105,0.12);
        }
        .form-control::placeholder { color: var(--text-muted); }

        .btn-accent {
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 200ms ease;
        }
        .btn-accent:hover {
            background: var(--accent-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }

        .btn-outline-custom {
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--bg-card);
            transition: all 150ms ease;
        }
        .btn-outline-custom:hover {
            border-color: #999;
            color: var(--text-primary);
            background: #f9fafb;
        }

        .quick-link {
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding-bottom: 10px;
            transition: color 150ms;
        }
        .quick-link:hover { color: var(--accent); }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--accent-light);
            border: 1px solid rgba(5,150,105,0.3);
            color: var(--accent-dark);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: monospace;
        }
        .filter-badge .btn-close-badge {
            background: none;
            border: none;
            color: var(--accent-dark);
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            line-height: 1;
            opacity: 0.5;
            transition: opacity 150ms;
        }
        .filter-badge .btn-close-badge:hover { opacity: 1; }

        .table-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        .table-wrapper .table-scroll {
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
        }

        .table-shoot {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 0;
        }
        .table-shoot thead th {
            background: #f9fafb;
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 12px 10px;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }
        .table-shoot thead th.day-col {
            text-align: center;
            min-width: 55px;
            color: var(--text-muted);
        }
        .table-shoot thead th.day-col.active {
            color: var(--accent);
            background: #f0fdf4;
        }
        .table-shoot thead th.total-col {
            text-align: right;
            min-width: 100px;
            background: #f0fdf4;
            color: var(--accent-dark);
            border-left: 2px solid rgba(5,150,105,0.15);
        }

        .table-shoot tbody tr { transition: background 100ms ease; }
        .table-shoot tbody tr:hover { background: #f9fafb; }
        .table-shoot tbody tr.row-hidden { display: none; }
        .table-shoot tbody td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
            color: var(--text-primary);
            white-space: nowrap;
        }
        .table-shoot tbody td.text-center { text-align: center; }
        .table-shoot tbody td.num-zero { color: var(--text-zero); }
        .table-shoot tbody td.num-val {
            color: var(--text-primary);
            font-variant-numeric: tabular-nums;
        }
        .table-shoot tbody td.num-highlight {
            color: var(--accent-dark);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .table-shoot tbody td.total-col {
            text-align: right;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            border-left: 2px solid rgba(5,150,105,0.08);
            background: rgba(240,253,244,0.3);
        }
        .table-shoot tbody td.total-col.highlight {
            color: var(--accent-dark);
            font-weight: 700;
        }

        .table-shoot tfoot td {
            padding: 12px 10px;
            border-top: 2px solid rgba(5,150,105,0.25);
            background: #f0fdf4;
            font-size: 12px;
            font-weight: 700;
            color: var(--accent-dark);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .table-shoot tfoot td.text-center { text-align: center; }
        .table-shoot tfoot td.total-col {
            text-align: right;
            border-left: 2px solid rgba(5,150,105,0.15);
        }

        .cust-code-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            background: #f3f4f6;
            color: var(--text-secondary);
            font-family: monospace;
            font-size: 11px;
        }

        /* Autocomplete */
        .autocomplete-wrap { position: relative; }
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 4px;
            max-height: 260px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 9px 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 100ms ease;
            border-bottom: 1px solid #f3f4f6;
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover,
        .autocomplete-item.active { background: var(--accent-light); }
        .autocomplete-item .code {
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
            color: var(--accent-dark);
            min-width: 100px;
        }
        .autocomplete-item .name {
            font-size: 12px;
            color: var(--text-primary);
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .autocomplete-item .abbr {
            font-size: 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .autocomplete-empty {
            padding: 20px 14px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }
        .autocomplete-loading {
            padding: 16px 14px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }
        .autocomplete-loading::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #e5e7eb;
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--text-primary);
            color: #fff;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 13px;
            z-index: 200;
            transform: translateY(100px);
            opacity: 0;
            transition: all 400ms ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }

        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
            color: var(--text-muted);
        }

        .footer-info { font-size: 11px; color: var(--text-muted); }

        .page-subtitle {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--accent);
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }
        @media (min-width: 768px) {
            .page-title { font-size: 2.75rem; }
        }

        .search-wrap { position: relative; }
        .search-wrap .bi {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
            pointer-events: none;
        }
        .search-wrap .form-control {
            padding-left: 34px;
            width: 220px;
            font-size: 12px;
        }

        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .navbar-custom { display: none !important; }
            .filter-section { display: none !important; }
            .table-wrapper { border: 1px solid #ddd; }
            .table-shoot thead th { background: #f3f3f3 !important; color: #333 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot thead th.total-col { background: #e8f5e9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot tbody td { color: #333 !important; border-color: #ddd !important; }
            .table-shoot tbody td.total-col { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-shoot tfoot td { background: #f0fdf4 !important; color: #047857 !important; border-color: #bbf7d0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .stat-card { border: 1px solid #ddd; background: #fff !important; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-custom fixed-top no-print" style="z-index: 1040;">
        <div class="container-fluid" style="max-width: 1800px; padding: 0 24px;">
            <div class="d-flex align-items-center justify-content-between w-100 py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-2" style="width:32px;height:32px;background:var(--accent);color:#fff;font-size:14px;">
                        <i class="bi bi-crosshair"></i>
                    </div>
                    <span class="fw-bold" style="font-size:13px;letter-spacing:-0.01em;">SHOOT DELIVERY</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button onclick="exportCSV()" class="btn btn-outline-custom d-flex align-items-center gap-1">
                        <i class="bi bi-download" style="font-size:13px;"></i> Export CSV
                    </button>
                    <button onclick="window.print()" class="btn btn-outline-custom d-flex align-items-center gap-1">
                        <i class="bi bi-printer" style="font-size:13px;"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="container-fluid" style="max-width:1800px; padding: 0 24px; padding-top: 80px; padding-bottom: 48px;">

        <!-- HEADER -->
        <div class="mb-4">
            <p class="page-subtitle mb-2">Monthly Report</p>
            <h1 class="page-title mb-2">Total Shoot Delivery</h1>
            <p style="color:var(--text-secondary); font-size:14px; max-width:500px;">
                Rekapitulasi harian shoot & delivery per item berdasarkan bulan dan tahun yang dipilih.
            </p>
        </div>

        <!-- FILTER -->
        <form method="GET" action="" id="filterForm" class="no-print mb-4">
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="filter-label d-block">Tahun</label>
                        <select name="year" id="yearSelect" class="form-select" style="min-width:130px;">
                            <?php
                            for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) {
                                $sel = ($y == $selectedYear) ? 'selected' : '';
                                echo "<option value=\"$y\" $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="filter-label d-block">Bulan</label>
                        <select name="month" id="monthSelect" class="form-select" style="min-width:170px;">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $sel = ($m == $selectedMonth) ? 'selected' : '';
                                echo "<option value=\"$m\" $sel>" . $namaBulan[$m] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col" style="min-width: 280px;">
                        <label class="filter-label d-block">Customer</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="custInput" placeholder="Ketik kode / nama customer..." autocomplete="off" value="<?= htmlspecialchars($selectedCust) ?>" class="form-control">
                            <input type="hidden" name="cust_code" id="custCodeHidden" value="<?= htmlspecialchars($selectedCust) ?>">
                            <div class="autocomplete-list" id="custDropdown"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <label class="filter-label d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-accent d-flex align-items-center gap-2">
                            <i class="bi bi-funnel" style="font-size:14px;"></i> Tampilkan
                        </button>
                    </div>
                    <div class="col-auto d-flex align-items-end gap-3 pb-1">
                        <a href="?year=<?= $currentYear ?>&month=<?= $currentMonth ?>" class="quick-link">
                            <i class="bi bi-calendar-check" style="font-size:13px;"></i> Bulan Ini
                        </a>
                        <a href="?year=<?= ($selectedMonth == 1 ? $selectedYear - 1 : $selectedYear) ?>&month=<?= $selectedMonth == 1 ? 12 : $selectedMonth - 1 ?>" class="quick-link">
                            <i class="bi bi-chevron-left" style="font-size:13px;"></i> Bulan Lalu
                        </a>
                    </div>
                </div>
                <?php if ($selectedCust !== ''): ?>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <span class="filter-label mb-0">Filter aktif:</span>
                    <span class="filter-badge">
                        <i class="bi bi-person" style="font-size:11px;"></i>
                        <?= htmlspecialchars($selectedCust) ?>
                        <button type="button" class="btn-close-badge" onclick="clearCustFilter()" title="Hapus filter customer">
                            <i class="bi bi-x" style="font-size:12px;"></i>
                        </button>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                        <span class="stat-label">Total Delivery</span>
                    </div>
                    <p class="stat-value"><?= formatNumber($grandTotalDelivery) ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="stat-icon"><i class="bi bi-crosshair"></i></div>
                        <span class="stat-label">Total Shoot</span>
                    </div>
                    <p class="stat-value"><?= formatShoot($grandTotalShoot) ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="stat-icon"><i class="bi bi-layers"></i></div>
                        <span class="stat-label">Jumlah Item</span>
                    </div>
                    <p class="stat-value" id="visibleItemCount"><?= number_format($rowCount, 0, ',', '.') ?></p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
                        <span class="stat-label">Periode</span>
                    </div>
                    <p class="stat-value"><?= $namaBulan[$selectedMonth] ?></p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;"><?= $selectedYear ?> · <?= $daysInMonth ?> hari</p>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 style="font-size:14px;font-weight:600;color:var(--text-primary);margin:0;">
                    Detail Harian
                    <span style="color:var(--text-muted);font-weight:400;margin-left:8px;"><?= $namaBulan[$selectedMonth] ?> <?= $selectedYear ?></span>
                </h2>
                <div class="no-print search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari item..." class="form-control">
                </div>
            </div>

            <div class="table-wrapper">
                <div class="table-scroll">
                    <?php if ($rowCount > 0): ?>
                    <table class="table-shoot" id="mainTable">
                        <thead>
                            <tr>
                                <th class="text-start" style="min-width:45px;">No</th>
                                <th class="text-start" style="min-width:100px;">Cust Code</th>
                                <th class="text-start" style="min-width:120px;">Item Code</th>
                                <th class="text-start" style="min-width:220px;">Item Name</th>
                                <th class="text-center" style="min-width:65px;">Cavities</th>
                                <!-- ★ TOTAL DIKIRI ★ -->
                                <th class="total-col">Total Delivery</th>
                                <th class="total-col">Total Shoot</th>
                                <!-- ★ HARI ★ -->
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <?php if ($d <= $daysInMonth): ?>
                                        <th class="day-col active"><?= $d ?></th>
                                    <?php else: ?>
                                        <th class="day-col" style="opacity:0.25;"><?= $d ?></th>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php
                            $no = 0;
                            foreach ($dataRows as $row):
                                $no++;
                                $custCode = strtoupper(trim($row['CUST_CODE']));
                                $hiddenClass = ($selectedCust !== '' && $custCode !== strtoupper($selectedCust)) ? ' row-hidden' : '';
                            ?>
                            <tr data-search="<?= strtolower($row['CUST_CODE'] . ' ' . $row['ITEM_CODE'] . ' ' . $row['ITEM_NAME']) ?>" data-cust="<?= $custCode ?>" class="<?= $hiddenClass ?>">
                                <td style="color:var(--text-muted);font-size:11px;"><?= $no ?></td>
                                <td><span class="cust-code-badge"><?= htmlspecialchars($row['CUST_CODE']) ?></span></td>
                                <td style="font-family:monospace;font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($row['ITEM_CODE']) ?></td>
                                <td style="font-weight:500;font-size:12px;"><?= htmlspecialchars($row['ITEM_NAME']) ?></td>
                                <td class="text-center" style="color:var(--text-secondary);"><?= intval($row['ITEM_CAVT']) ?></td>
                                <!-- ★ TOTAL DIKIRI ★ -->
                                <td class="total-col num-val"><?= formatNumber($row['total_delivery']) ?></td>
                                <td class="total-col highlight num-highlight"><?= formatShoot($row['total_shoot']) ?></td>
                                <!-- ★ HARI ★ -->
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <?php
                                        $val = floatval($row['d' . $d]);
                                        $cssClass = 'text-center ';
                                        if ($d > $daysInMonth) {
                                            $cssClass .= 'num-zero';
                                            $display = '-';
                                        } elseif ($val == 0) {
                                            $cssClass .= 'num-zero';
                                            $display = '-';
                                        } else {
                                            $cssClass .= 'num-val';
                                            $display = formatNumber($val);
                                        }
                                    ?>
                                    <td class="<?= $cssClass ?>" <?= $d > $daysInMonth ? 'style="opacity:0.25;"' : '' ?>><?= $display ?></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot id="tableFoot">
                            <tr>
                                <td colspan="5" class="text-start" style="font-weight:800;">GRAND TOTAL</td>
                                <!-- ★ TOTAL DIKIRI ★ -->
                                <td class="total-col"><?= formatNumber($grandTotalDelivery) ?></td>
                                <td class="total-col"><?= formatShoot($grandTotalShoot) ?></td>
                                <!-- ★ HARI ★ -->
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <?php
                                        $val = $dayTotals[$d];
                                        if ($d > $daysInMonth) {
                                            $display = '-';
                                        } elseif ($val == 0) {
                                            $display = '-';
                                        } else {
                                            $display = formatNumber($val);
                                        }
                                    ?>
                                    <td class="text-center" <?= $d > $daysInMonth ? 'style="opacity:0.25;"' : '' ?>><?= $display ?></td>
                                <?php endfor; ?>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                            <p style="color:var(--text-secondary);font-size:14px;margin:0;">Tidak ada data untuk periode ini.</p>
                            <p style="color:var(--text-muted);font-size:12px;margin:4px 0 0;">Coba pilih bulan atau tahun yang lain.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FOOTER INFO -->
        <div class="footer-info no-print d-flex flex-wrap align-items-center justify-content-between mt-3">
            <p class="mb-0">Dicetak: <?= date('d M Y H:i:s') ?> · <span id="footerRowCount"><?= $rowCount ?></span> baris data</p>
            <p class="mb-0">sp_total_shoot_delivery_monthly · @year=<?= $selectedYear ?> · @month=<?= $selectedMonth ?><?= $selectedCust ? ' · @cust=' . htmlspecialchars($selectedCust) : '' ?></p>
        </div>

    </main>

    <!-- TOAST -->
    <div id="toast" class="toast-custom no-print">
        <span id="toastMsg"></span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const custInput      = document.getElementById('custInput');
        const custHidden     = document.getElementById('custCodeHidden');
        const custDropdown   = document.getElementById('custDropdown');
        let   acTimeout      = null;
        let   acActiveIndex  = -1;

        custInput.addEventListener('input', function() {
            const q = this.value.trim();
            custHidden.value = '';
            acActiveIndex = -1;
            if (q.length < 1) { hideDropdown(); return; }
            clearTimeout(acTimeout);
            acTimeout = setTimeout(() => fetchCustomers(q), 300);
        });

        custInput.addEventListener('focus', function() {
            const q = this.value.trim();
            if (q.length >= 1) fetchCustomers(q);
        });

        custInput.addEventListener('keydown', function(e) {
            const items = custDropdown.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                acActiveIndex = Math.min(acActiveIndex + 1, items.length - 1);
                highlightItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                acActiveIndex = Math.max(acActiveIndex - 1, 0);
                highlightItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (acActiveIndex >= 0 && items[acActiveIndex]) {
                    selectCustomer(items[acActiveIndex]);
                } else {
                    custHidden.value = custInput.value.trim();
                    document.getElementById('filterForm').submit();
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
                custInput.blur();
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-wrap')) hideDropdown();
        });

        function fetchCustomers(q) {
            custDropdown.innerHTML = '<div class="autocomplete-loading">Mencari...</div>';
            custDropdown.classList.add('show');
            const formData = new FormData();
            formData.append('q', q);
            fetch('search_cust.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (!data || data.length === 0) {
                    custDropdown.innerHTML = '<div class="autocomplete-empty">Customer tidak ditemukan</div>';
                    return;
                }
                let html = '';
                data.forEach(item => {
                    html += `<div class="autocomplete-item" data-code="${escapeHtml(item.CUST_CODE)}" onclick="selectCustomer(this)">
                        <span class="code">${escapeHtml(item.CUST_CODE)}</span>
                        <span class="name">${escapeHtml(item.CUST_COMP)}</span>
                        ${item.CUST_ABBR ? '<span class="abbr">' + escapeHtml(item.CUST_ABBR) + '</span>' : ''}
                    </div>`;
                });
                custDropdown.innerHTML = html;
            })
            .catch(() => {
                custDropdown.innerHTML = '<div class="autocomplete-empty">Gagal memuat data</div>';
            });
        }

        function selectCustomer(el) {
            const code = el.getAttribute('data-code');
            const name = el.querySelector('.name') ? el.querySelector('.name').textContent : '';
            custInput.value  = code + ' - ' + name;
            custHidden.value = code;
            hideDropdown();
            document.getElementById('filterForm').submit();
        }

        function highlightItem(items) {
            items.forEach((it, i) => it.classList.toggle('active', i === acActiveIndex));
            if (items[acActiveIndex]) items[acActiveIndex].scrollIntoView({ block: 'nearest' });
        }

        function hideDropdown() {
            custDropdown.classList.remove('show');
            custDropdown.innerHTML = '';
            acActiveIndex = -1;
        }

        function clearCustFilter() {
            custInput.value  = '';
            custHidden.value = '';
            document.getElementById('filterForm').submit();
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const rows  = document.querySelectorAll('#tableBody tr');
                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search') || '';
                    row.style.display = (query === '' || searchData.includes(query)) ? '' : 'none';
                });
                recalcVisible();
            });
        }

        function recalcVisible() {
            const rows = document.querySelectorAll('#tableBody tr:not(.row-hidden)');
            let count = 0;
            rows.forEach(r => { if (r.style.display !== 'none') count++; });
            document.getElementById('visibleItemCount').textContent = count.toLocaleString('id-ID');
            document.getElementById('footerRowCount').textContent  = count.toLocaleString('id-ID');
        }

        function showToast(msg, duration = 3000) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toastMsg');
            toastMsg.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), duration);
        }

        function exportCSV() {
            const table = document.getElementById('mainTable');
            if (!table) { showToast('Tidak ada data untuk di-export.'); return; }
            let csv = [];
            const allRows = table.querySelectorAll('tr');
            allRows.forEach(row => {
                if (row.closest('tbody') && (row.classList.contains('row-hidden') || row.style.display === 'none')) return;
                const cols = row.querySelectorAll('th, td');
                const rowData = [];
                cols.forEach(col => {
                    let text = col.innerText.replace(/"/g, '""').trim();
                    rowData.push('"' + text + '"');
                });
                csv.push(rowData.join(','));
            });
            if (csv.length <= 1) { showToast('Tidak ada data visible untuk di-export.'); return; }
            const csvContent = '\uFEFF' + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const period = <?= $selectedYear ?> + '-' + String(<?= $selectedMonth ?>).padStart(2, '0');
            link.href = url;
            link.download = 'shoot_delivery_' + period + (<?= json_encode($selectedCust) ?> ? '_' + <?= json_encode($selectedCust) ?> : '') + '.csv';
            link.click();
            URL.revokeObjectURL(url);
            showToast('CSV berhasil di-download!');
        }

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') document.getElementById('filterForm').submit();
            if (e.ctrlKey && e.key === 'f') { e.preventDefault(); if (searchInput) searchInput.focus(); }
        });
    </script>

</body>
</html>