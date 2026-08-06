<?php
/**
 * LAPORAN MUTASI BARANG JADI
 * PHP 5.4 + SQLSRV + SQL Server 2008
 *
 * Lokasi:
 *   /msii/bea/mutasi_barang.php
 *
 * Stored procedure:
 *   dbo.sp_laporan_mutasi_barang1
 */

/* =========================================================
 * SESSION DAN LOGIN PROTECTION
 * ========================================================= */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$loginUrl = "/msii/bea/login.php";

if (
    !isset($_SESSION['db_user']) ||
    trim($_SESSION['db_user']) == "" ||
    !isset($_SESSION['active_module']) ||
    $_SESSION['active_module'] != "it_inventory"
) {
    header("Location: " . $loginUrl);
    exit();
}

/* =========================================================
 * KONEKSI DATABASE
 * ========================================================= */
$moduleDatabase = __DIR__ . "/config/database.php";
$sharedPlant1   = __DIR__ . "/../config/db_plant1.php";
$sharedPlan1    = __DIR__ . "/../config/db_plan1.php";

if (file_exists($moduleDatabase)) {
    require_once $moduleDatabase;
} elseif (file_exists($sharedPlant1)) {
    require_once $sharedPlant1;
} elseif (file_exists($sharedPlan1)) {
    require_once $sharedPlan1;
} else {
    die("File koneksi database Plant 1 tidak ditemukan.");
}

if (!isset($conn) || $conn === false) {
    header("Location: " . $loginUrl . "?error=session_expired");
    exit();
}

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function mbj_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function mbj_valid_date($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $parts = explode("-", $value);

    if (count($parts) != 3) {
        return false;
    }

    return checkdate(
        (int) $parts[1],
        (int) $parts[2],
        (int) $parts[0]
    );
}

function mbj_display_date($value)
{
    if ($value instanceof DateTime) {
        return $value->format("d-m-Y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $time = strtotime((string) $value);

    if ($time === false) {
        return (string) $value;
    }

    return date("d-m-Y", $time);
}

function mbj_number($value, $decimal)
{
    if ($value === null || $value === "") {
        return number_format(0, $decimal, ".", ",");
    }

    return number_format(
        (float) $value,
        $decimal,
        ".",
        ","
    );
}

function mbj_contains($haystack, $needle)
{
    $haystack = (string) $haystack;
    $needle   = (string) $needle;

    if ($needle === "") {
        return true;
    }

    if (function_exists("mb_stripos")) {
        return mb_stripos(
            $haystack,
            $needle,
            0,
            "UTF-8"
        ) !== false;
    }

    return stripos($haystack, $needle) !== false;
}

function mbj_month_period($dateFrom, $dateTo)
{
    $fromTime = strtotime($dateFrom);
    $toTime   = strtotime($dateTo);

    if ($fromTime === false || $toTime === false) {
        return 1;
    }

    $fromYear  = (int) date("Y", $fromTime);
    $fromMonth = (int) date("n", $fromTime);
    $toYear    = (int) date("Y", $toTime);
    $toMonth   = (int) date("n", $toTime);

    $period = (($toYear - $fromYear) * 12)
        + ($toMonth - $fromMonth)
        + 1;

    return ($period < 1) ? 1 : $period;
}

function mbj_status_rank($row)
{
    $status = isset($row["KETERANGAN"])
        ? strtoupper(trim($row["KETERANGAN"]))
        : "";

    /*
     * Hanya status TIDAK SESUAI yang dipindahkan ke bagian bawah.
     * Status lain tetap mengikuti urutan asli stored procedure.
     */
    return ($status == "TIDAK SESUAI") ? 1 : 0;
}

function mbj_sort_not_sesuai_last($a, $b)
{
    $rankA = mbj_status_rank($a);
    $rankB = mbj_status_rank($b);

    if ($rankA != $rankB) {
        return ($rankA < $rankB) ? -1 : 1;
    }

    $orderA = isset($a["__ORIGINAL_ORDER"])
        ? (int) $a["__ORIGINAL_ORDER"]
        : 0;

    $orderB = isset($b["__ORIGINAL_ORDER"])
        ? (int) $b["__ORIGINAL_ORDER"]
        : 0;

    if ($orderA == $orderB) {
        return 0;
    }

    return ($orderA < $orderB) ? -1 : 1;
}

function mbj_sqlsrv_error()
{
    $errors = sqlsrv_errors();

    if (!is_array($errors)) {
        return "Kesalahan SQL Server tidak diketahui.";
    }

    $messages = array();

    foreach ($errors as $error) {
        $messages[] =
            "[" . $error["SQLSTATE"] . "] "
            . $error["code"]
            . " - "
            . $error["message"];
    }

    return implode("\n", $messages);
}

/* =========================================================
 * PARAMETER FILTER
 * ========================================================= */
$today       = date("Y-m-d");
$defaultFrom = date("Y-m-01");

$tglAwal = isset($_GET["tglawal"])
    ? trim($_GET["tglawal"])
    : $defaultFrom;

$tglAkhir = isset($_GET["tglakhir"])
    ? trim($_GET["tglakhir"])
    : $today;

$cari = isset($_GET["cari"])
    ? trim($_GET["cari"])
    : "";

/*
|--------------------------------------------------------------------------
| HALAMAN TELUSUR STOCK ANALYSIS
|--------------------------------------------------------------------------
| File ini dipakai bersama laporan bahan baku dan barang jadi.
*/
$stockAnalysisUrl = "/msii/bea/print_stock_analysis.php";

$format = isset($_GET["format"])
    ? strtolower(trim($_GET["format"]))
    : "html";

$autoPrint = isset($_GET["print"]) &&
             $_GET["print"] == "1";

$errors = array();

if (!mbj_valid_date($tglAwal)) {
    $errors[] = "Tanggal awal tidak valid.";
}

if (!mbj_valid_date($tglAkhir)) {
    $errors[] = "Tanggal akhir tidak valid.";
}

if (
    mbj_valid_date($tglAwal) &&
    mbj_valid_date($tglAkhir) &&
    strtotime($tglAwal) >= strtotime($tglAkhir)
) {
    $errors[] = "Tanggal stok akhir harus lebih besar dari tanggal stok awal.";
}

if (strlen($cari) > 150) {
    $errors[] = "Kata pencarian terlalu panjang.";
}

/*
 * Contoh:
 * Stok awal  : 01-Jun-2026
 * Transaksi  : 01-Jun-2026 s/d 30-Jun-2026
 * Stok akhir : 01-Jul-2026
 */
$transactionEndDate = mbj_valid_date($tglAkhir)
    ? date("Y-m-d", strtotime($tglAkhir . " -1 day"))
    : $tglAkhir;

$stockAnalysisPeriod = mbj_month_period(
    $tglAwal,
    $tglAkhir
);

/* =========================================================
 * PANGGIL STORED PROCEDURE
 * ========================================================= */
$rows = array();
$queryError = "";

if (count($errors) == 0) {
    $sql = "{CALL dbo.sp_laporan_mutasi_barang1(?, ?)}";

    $params = array(
        $tglAwal,
        $tglAkhir
    );

    /*
     * Gunakan cursor default forward-only.
     * Jangan memakai SQLSRV_CURSOR_KEYSET.
     */
    $stmt = sqlsrv_query(
        $conn,
        $sql,
        $params
    );

    if ($stmt === false) {
        $queryError = mbj_sqlsrv_error();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }

        sqlsrv_free_stmt($stmt);
    }
}

/* =========================================================
 * AUTOCOMPLETE, PENCARIAN, DAN PENGURUTAN STATUS
 * ========================================================= */
$allRows = $rows;
$autocompleteNames = array();

/*
 * Buat daftar nama barang unik untuk HTML datalist.
 */
foreach ($allRows as $autocompleteRow) {
    $itemName = isset($autocompleteRow["NAMA_BARANG"])
        ? trim($autocompleteRow["NAMA_BARANG"])
        : "";

    $itemCode = isset($autocompleteRow["KODE_BARANG"])
        ? trim($autocompleteRow["KODE_BARANG"])
        : "";

    if ($itemName != "") {
        $autocompleteKey = strtolower($itemName);

        if (!isset($autocompleteNames[$autocompleteKey])) {
            $autocompleteNames[$autocompleteKey] = array(
                "name" => $itemName,
                "code" => $itemCode
            );
        }
    }
}

/*
 * Urutkan pilihan autocomplete berdasarkan nama barang.
 */
uasort(
    $autocompleteNames,
    function ($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    }
);

/*
 * Pencarian dapat memakai nama barang maupun kode barang.
 */
if ($cari != "") {
    $filteredRows = array();

    foreach ($rows as $searchRow) {
        $itemCode = isset($searchRow["KODE_BARANG"])
            ? $searchRow["KODE_BARANG"]
            : "";

        $itemName = isset($searchRow["NAMA_BARANG"])
            ? $searchRow["NAMA_BARANG"]
            : "";

        if (
            mbj_contains($itemCode, $cari) ||
            mbj_contains($itemName, $cari) ||
            mbj_contains($itemCode . " - " . $itemName, $cari)
        ) {
            $filteredRows[] = $searchRow;
        }
    }

    $rows = $filteredRows;
}

/*
 * Pertahankan urutan asli stored procedure untuk semua status,
 * lalu pindahkan TIDAK SESUAI ke bagian paling bawah.
 */
foreach ($rows as $rowIndex => $sortRow) {
    $rows[$rowIndex]["__ORIGINAL_ORDER"] = $rowIndex;
}

usort($rows, "mbj_sort_not_sesuai_last");

foreach ($rows as $rowIndex => $sortRow) {
    unset($rows[$rowIndex]["__ORIGINAL_ORDER"]);
}

/* =========================================================
 * EXPORT EXCEL
 * ========================================================= */
if (
    $format == "excel" &&
    count($errors) == 0 &&
    $queryError == ""
) {
    $filename =
        "mutasi_barang_jadi_"
        . $tglAwal
        . "_sd_"
        . $tglAkhir
        . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">

    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #000000;
            padding: 3px;
        }

        .title {
            border: 0;
            font-size: 12px;
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .number {
            text-align: right;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td class="title" colspan="12">
            LAPORAN PERTANGGUNGJAWABAN BARANG JADI
        </td>
    </tr>

    <tr>
        <td class="title" colspan="12">
            KAWASAN BERIKAT PT. IMC TEKNO INDONESIA
        </td>
    </tr>

    <tr>
        <td class="title" colspan="12">
            Periode
            <?php echo mbj_h(mbj_display_date($tglAwal)); ?>
            s/d
            <?php echo mbj_h(mbj_display_date($tglAkhir)); ?>
        </td>
    </tr>

    <tr>
        <td colspan="12"></td>
    </tr>

    <tr>
        <th>NO</th>
        <th>KODE BARANG</th>
        <th>NAMA BARANG</th>
        <th>SATUAN</th>
        <th>
            SALDO AWAL<br>
            <?php echo mbj_h(mbj_display_date($tglAwal)); ?>
        </th>
        <th>PEMASUKAN</th>
        <th>PENGELUARAN</th>
        <th>PENYESUAIAN</th>
        <th>
            SALDO AKHIR<br>
            <?php echo mbj_h(mbj_display_date($tglAkhir)); ?>
        </th>
        <th>STOK OPNAME</th>
        <th>SELISIH</th>
        <th>KETERANGAN</th>
    </tr>

    <?php
    $no = 1;

    foreach ($rows as $row) {
    ?>
        <tr>
            <td class="center"><?php echo $no; ?></td>

            <td>
                <?php echo mbj_h(
                    isset($row["KODE_BARANG"])
                        ? $row["KODE_BARANG"]
                        : ""
                ); ?>
            </td>

            <td>
                <?php echo mbj_h(
                    isset($row["NAMA_BARANG"])
                        ? $row["NAMA_BARANG"]
                        : ""
                ); ?>
            </td>

            <td class="center">
                <?php echo mbj_h(
                    isset($row["SATUAN"])
                        ? $row["SATUAN"]
                        : ""
                ); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["SALDO_AWAL"])
                        ? $row["SALDO_AWAL"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["MASUK"])
                        ? $row["MASUK"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["KELUAR"])
                        ? $row["KELUAR"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["PENYESUAIAN"])
                        ? $row["PENYESUAIAN"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["SALDO_AKHIR"])
                        ? $row["SALDO_AKHIR"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["STOK_OPNAME"])
                        ? $row["STOK_OPNAME"]
                        : 0,
                    2
                )); ?>
            </td>

            <td class="number">
                <?php echo mbj_h(mbj_number(
                    isset($row["SELISIH"])
                        ? $row["SELISIH"]
                        : 0,
                    2
                )); ?>
            </td>

            <td>
                <?php echo mbj_h(
                    isset($row["KETERANGAN"])
                        ? $row["KETERANGAN"]
                        : ""
                ); ?>
            </td>
        </tr>
    <?php
        $no++;
    }
    ?>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="12" class="center">
                Data tidak ditemukan.
            </td>
        </tr>
    <?php } ?>
</table>

</body>
</html>
<?php
    exit();
}

/* =========================================================
 * URL BANTU
 * ========================================================= */
$queryParams = array(
    "tglawal"  => $tglAwal,
    "tglakhir" => $tglAkhir,
    "cari"     => $cari
);

$excelUrl = "mutasi_barang.php?" . http_build_query(
    array_merge(
        $queryParams,
        array("format" => "excel")
    )
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Laporan Mutasi Barang Jadi</title>

    <link
        rel="stylesheet"
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    >

    <style>
        html,
        body {
            background: #ffffff;
            color: #111111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .screen-toolbar {
            background: #eef3f7;
            border-bottom: 1px solid #bac7d1;
            padding: 10px 12px;
        }

        .screen-toolbar .form-inline {
            margin-bottom: 8px;
        }

        .screen-toolbar label {
            margin-right: 4px;
        }

        .screen-toolbar .form-control {
            height: 30px;
            padding: 4px 7px;
            font-size: 12px;
        }

        .screen-toolbar .btn {
            height: 30px;
            padding: 5px 10px;
            font-size: 12px;
        }

        .report-wrapper {
            min-width: 1280px;
            padding: 8px 10px 25px;
        }

        .report-title {
            margin-bottom: 12px;
            line-height: 1.35;
        }

        .report-title h1 {
            margin: 0;
            font-size: 12px;
            font-weight: bold;
        }

        .report-title h2 {
            margin: 0;
            font-size: 11px;
            font-weight: bold;
        }

        .report-title p {
            margin: 2px 0 0;
            font-size: 10px;
        }

        .report-info {
            float: right;
            margin-top: -35px;
            font-size: 10px;
            text-align: right;
        }

        .report-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #222222 !important;
            padding: 2px 3px !important;
            font-size: 9px;
            line-height: 1.15;
            vertical-align: top !important;
            word-wrap: break-word;
        }

        .report-table thead th {
            background: #f4f4f4 !important;
            text-align: center;
            vertical-align: middle !important;
            font-weight: bold;
        }

        .report-table .number {
            text-align: right;
            white-space: nowrap;
        }

        .report-table .center {
            text-align: center;
        }

        .col-no {
            width: 30px;
        }

        .col-kode {
            width: 95px;
        }

        .col-nama {
            width: 270px;
        }

        .col-satuan {
            width: 55px;
        }

        .col-number {
            width: 95px;
        }

        .col-keterangan {
            width: 120px;
        }

        .alert-report {
            margin: 10px;
            white-space: pre-wrap;
        }

        .report-footer {
            margin-top: 8px;
            font-size: 9px;
        }

        .no-data {
            padding: 25px !important;
            text-align: center;
            font-size: 11px !important;
        }

        .status-sesuai {
            color: #218838;
            font-weight: bold;
        }

        .status-tidak {
            color: #c82333;
            font-weight: bold;
        }

        .status-belum {
            color: #856404;
            font-weight: bold;
        }

        .trace-link,
        .item-trace-link {
            font-weight: bold;
            text-decoration: underline;
        }

        .trace-link {
            color: inherit;
        }

        .item-trace-link {
            color: #0056b3;
        }

        .trace-link:hover,
        .trace-link:focus,
        .item-trace-link:hover,
        .item-trace-link:focus {
            text-decoration: none;
        }

        @media print {
            @page {
                size: A3 landscape;
                margin: 7mm;
            }

            html,
            body {
                width: auto;
                height: auto;
                font-size: 9px;
            }

            .no-print {
                display: none !important;
            }

            .report-wrapper {
                min-width: 0;
                padding: 0;
            }

            .report-table th,
            .report-table td {
                padding: 1px 2px !important;
                font-size: 7px;
            }

            .report-info {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="screen-toolbar no-print">

    <form
        method="get"
        action="mutasi_barang.php"
        class="form-inline"
    >
        <div class="form-group">
            <label for="tglawal">Stok Awal</label>

            <input
                type="date"
                name="tglawal"
                id="tglawal"
                class="form-control"
                value="<?php echo mbj_h($tglAwal); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="tglakhir">Stok Akhir</label>

            <input
                type="date"
                name="tglakhir"
                id="tglakhir"
                class="form-control"
                value="<?php echo mbj_h($tglAkhir); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="cari">Nama Barang</label>

            <input
                type="text"
                name="cari"
                id="cari"
                class="form-control"
                value="<?php echo mbj_h($cari); ?>"
                placeholder="Ketik nama atau kode barang"
                list="nama-barang-list"
                autocomplete="off"
                style="width: 280px;"
            >

            <datalist id="nama-barang-list">
                <?php foreach ($autocompleteNames as $autocompleteItem) { ?>
                    <option
                        value="<?php echo mbj_h($autocompleteItem["name"]); ?>"
                        label="<?php echo mbj_h($autocompleteItem["code"]); ?>"
                    ></option>
                <?php } ?>
            </datalist>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-search"></i>
            Cari
        </button>

        <a
            href="mutasi_barang.php?tglawal=<?php echo urlencode($tglAwal); ?>&amp;tglakhir=<?php echo urlencode($tglAkhir); ?>"
            class="btn btn-warning"
        >
            <i class="fa fa-refresh"></i>
            Reset Pencarian
        </a>

        <button
            type="button"
            class="btn btn-default"
            onclick="window.print();"
        >
            <i class="fa fa-print"></i>
            Cetak / PDF
        </button>

        <a
            href="<?php echo mbj_h($excelUrl); ?>"
            class="btn btn-success"
        >
            <i class="fa fa-file-excel-o"></i>
            Excel
        </a>

        <a
            href="laporan.php"
            class="btn btn-default"
        >
            <i class="fa fa-arrow-left"></i>
            Kembali
        </a>
    </form>

    <div>
        Login:
        <strong><?php echo mbj_h($_SESSION["db_user"]); ?></strong>

        &nbsp;|&nbsp;

        <a href="logout.php">
            Logout
        </a>
    </div>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="alert alert-danger alert-report">
        <strong>Filter tidak valid:</strong>
        <?php echo mbj_h(implode("\n", $errors)); ?>
    </div>
<?php } ?>

<?php if ($queryError != "") { ?>
    <div class="alert alert-danger alert-report">
        <strong>Stored procedure gagal dijalankan:</strong>
        <?php echo mbj_h($queryError); ?>
    </div>
<?php } ?>

<?php if (count($errors) == 0 && $queryError == "") { ?>

<div class="report-wrapper">

    <div class="report-title">
        <h1>
            LAPORAN PERTANGGUNGJAWABAN BARANG JADI
        </h1>

        <h2>
            KAWASAN BERIKAT PT. IMC TEKNO INDONESIA
        </h2>

        <p>
            Stok awal:
            <strong><?php echo mbj_h(mbj_display_date($tglAwal)); ?></strong>

            &nbsp;|&nbsp;

            Transaksi:
            <strong>
                <?php echo mbj_h(mbj_display_date($tglAwal)); ?>
                s/d
                <?php echo mbj_h(mbj_display_date($transactionEndDate)); ?>
            </strong>

            &nbsp;|&nbsp;

            Stok akhir:
            <strong><?php echo mbj_h(mbj_display_date($tglAkhir)); ?></strong>

            <?php if ($cari != "") { ?>
                &nbsp;|&nbsp;
                Pencarian:
                <strong><?php echo mbj_h($cari); ?></strong>
            <?php } ?>
        </p>
    </div>

    <div class="report-info no-print">
        Jumlah baris:
        <strong><?php echo count($rows); ?></strong>

        <?php if ($cari != "") { ?>
            dari
            <strong><?php echo count($allRows); ?></strong>
        <?php } ?>
    </div>

    <table class="table report-table">

        <colgroup>
            <col class="col-no">
            <col class="col-kode">
            <col class="col-nama">
            <col class="col-satuan">
            <col class="col-number">
            <col class="col-number">
            <col class="col-number">
            <col class="col-number">
            <col class="col-number">
            <col class="col-number">
            <col class="col-number">
            <col class="col-keterangan">
        </colgroup>

        <thead>
            <tr>
                <th>NO</th>
                <th>KODE BARANG</th>
                <th>NAMA BARANG</th>
                <th>SATUAN</th>

                <th>
                    SALDO AWAL
                    <br>
                    <?php echo mbj_h(mbj_display_date($tglAwal)); ?>
                </th>

                <th>PEMASUKAN</th>
                <th>PENGELUARAN</th>
                <th>PENYESUAIAN</th>

                <th>
                    SALDO AKHIR
                    <br>
                    <?php echo mbj_h(mbj_display_date($tglAkhir)); ?>
                </th>

                <th>STOK OPNAME</th>
                <th>SELISIH</th>
                <th>KETERANGAN</th>
            </tr>
        </thead>

        <tbody>

        <?php
        $no = 1;

        foreach ($rows as $row) {
            $status = isset($row["KETERANGAN"])
                ? strtoupper(trim($row["KETERANGAN"]))
                : "";

            $statusClass = "";

            if ($status == "SESUAI") {
                $statusClass = "status-sesuai";
            } elseif ($status == "TIDAK SESUAI") {
                $statusClass = "status-tidak";
            } elseif ($status == "BELUM OPNAME") {
                $statusClass = "status-belum";
            }

            $itemCode = isset($row["KODE_BARANG"])
                ? trim($row["KODE_BARANG"])
                : "";

            /*
             * Semua status dapat ditelusuri:
             * SESUAI, TIDAK SESUAI, dan BELUM OPNAME.
             */
            $traceable = ($itemCode != "");
            $traceUrl = "";

            if ($traceable) {
                $traceUrl = $stockAnalysisUrl
                    . "?"
                    . http_build_query(
                        array(
                            "report_type"  => "barang",
                            "item_id"      => $itemCode,
                            "start_date"   => $tglAwal,
                            "end_date"     => $tglAkhir,
                            "period"       => $stockAnalysisPeriod,
                            "saldo_awal"   => isset($row["SALDO_AWAL"]) ? $row["SALDO_AWAL"] : 0,
                            "masuk"        => isset($row["MASUK"]) ? $row["MASUK"] : 0,
                            "keluar"       => isset($row["KELUAR"]) ? $row["KELUAR"] : 0,
                            "saldo_akhir"  => isset($row["SALDO_AKHIR"]) ? $row["SALDO_AKHIR"] : 0,
                            "stok_opname"  => isset($row["STOK_OPNAME"]) ? $row["STOK_OPNAME"] : 0,
                            "selisih"      => isset($row["SELISIH"]) ? $row["SELISIH"] : 0
                        )
                    );
            }
        ?>
            <tr>
                <td class="center">
                    <?php echo $no; ?>
                </td>

                <td>
                    <?php if ($traceable) { ?>
                        <a
                            href="<?php echo mbj_h($traceUrl); ?>"
                            class="item-trace-link"
                            target="_blank"
                            title="Buka Stock Analysis barang jadi"
                        >
                            <i class="fa fa-search"></i>
                            <?php echo mbj_h($itemCode); ?>
                        </a>
                    <?php } else { ?>
                        <?php echo mbj_h($itemCode); ?>
                    <?php } ?>
                </td>

                <td>
                    <?php echo mbj_h(
                        isset($row["NAMA_BARANG"])
                            ? $row["NAMA_BARANG"]
                            : ""
                    ); ?>
                </td>

                <td class="center">
                    <?php echo mbj_h(
                        isset($row["SATUAN"])
                            ? $row["SATUAN"]
                            : ""
                    ); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["SALDO_AWAL"])
                            ? $row["SALDO_AWAL"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["MASUK"])
                            ? $row["MASUK"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["KELUAR"])
                            ? $row["KELUAR"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["PENYESUAIAN"])
                            ? $row["PENYESUAIAN"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["SALDO_AKHIR"])
                            ? $row["SALDO_AKHIR"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["STOK_OPNAME"])
                            ? $row["STOK_OPNAME"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="number">
                    <?php echo mbj_h(mbj_number(
                        isset($row["SELISIH"])
                            ? $row["SELISIH"]
                            : 0,
                        2
                    )); ?>
                </td>

                <td class="<?php echo mbj_h($statusClass); ?>">
                    <?php if ($traceable) { ?>
                        <a
                            href="<?php echo mbj_h($traceUrl); ?>"
                            class="trace-link"
                            target="_blank"
                            title="Telusuri transaksi WHS dan lokasi produksi"
                        >
                            <?php echo mbj_h($status); ?>
                            <i class="fa fa-external-link"></i>
                        </a>
                    <?php } else { ?>
                        <?php echo mbj_h($status); ?>
                    <?php } ?>
                </td>
            </tr>
        <?php
            $no++;
        }
        ?>

        <?php if (count($rows) == 0) { ?>
            <tr>
                <td colspan="12" class="no-data">
                    Data mutasi barang jadi
                    tidak ditemukan untuk periode yang dipilih.
                </td>
            </tr>
        <?php } ?>

        </tbody>
    </table>

    <div class="report-footer">
        Dicetak:
        <?php echo date("d-m-Y H:i:s"); ?>

        &nbsp;|&nbsp;

        Pengguna:
        <?php echo mbj_h($_SESSION["db_user"]); ?>
    </div>
</div>

<?php } ?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<?php if ($autoPrint && count($errors) == 0 && $queryError == "") { ?>
<script>
window.onload = function () {
    window.print();
};
</script>
<?php } ?>

</body>
</html>
