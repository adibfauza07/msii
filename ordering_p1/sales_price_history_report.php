<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }
    return trim((string)$value);
}

function fmt_print_date() {
    return date("d-M-Y H:i:s");
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }

    return date("d-M-Y", $ts);
}

function fmt_price($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, 4, ".", "");
}

// 1. Tangkap parameter filter customer
$filterCustCode = isset($_GET['cust_code']) ? trim($_GET['cust_code']) : '';
$filterCustLabel = isset($_GET['cust_label']) ? trim($_GET['cust_label']) : '';

$whereSql = "";
$params = array();

// 2. Tambahkan kondisi WHERE jika filter diisi
if ($filterCustCode !== "") {
    $whereSql = " WHERE CUST_CODE = ? ";
    $params[] = $filterCustCode;
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        ISNULL(PARTNUM, '') AS PARTNUM,
        ISNULL(ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(PRICE_CODE, '') AS PRICE_CODE,
        ISNULL(ITEM_NO, '') AS ITEM_NO,
        ISNULL(ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_COMP, '') AS CUST_COMP,
        ISNULL(PRDT_PRICE, 0) AS PRDT_PRICE,
        PRDT_START,
        PRDT_END,
        ISNULL(PRDT_QNO, '') AS PRDT_QNO
    FROM dbo.RPT_SALES_PRICE_HISTORY_VIEW
    $whereSql
    ORDER BY
        CUST_CODE,
        ITEM_CODE,
        ITEM_NO,
        ITEM_NAME,
        PRDT_START,
        PRDT_END
";

// 3. Masukkan parameter ke query
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query Sales Price History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$dataRows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = array(
        "PARTNUM"    => safe_trim($r["PARTNUM"]),
        "ITEM_CODE"  => safe_trim($r["ITEM_CODE"]),
        "PRICE_CODE" => safe_trim($r["PRICE_CODE"]),
        "ITEM_NO"    => safe_trim($r["ITEM_NO"]),
        "ITEM_NAME"  => safe_trim($r["ITEM_NAME"]),
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PRDT_PRICE" => $r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => safe_trim($r["PRDT_QNO"])
    );
}

$printRows = array();
$lastCust = "";
$lastItem = "";

for ($i = 0; $i < count($dataRows); $i++) {
    $r = $dataRows[$i];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );
        $lastCust = $custKey;
        $lastItem = "";
    }

    $itemKey = $r["ITEM_CODE"] . "|" . $r["ITEM_NO"] . "|" . $r["ITEM_NAME"];

    if ($itemKey != $lastItem) {
        $printRows[] = array(
            "ROW_TYPE"   => "ITEM",
            "ITEM_CODE"  => $r["ITEM_CODE"],
            "PRICE_CODE" => $r["PRICE_CODE"],
            "ITEM_NO"    => $r["ITEM_NO"],
            "ITEM_NAME"  => $r["ITEM_NAME"]
        );
        $lastItem = $itemKey;
    }

    $printRows[] = array(
        "ROW_TYPE"   => "PRICE",
        "PRDT_PRICE" => $r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => $r["PRDT_QNO"]
    );
}

if (count($printRows) == 0) {
    $printRows[] = array(
        "ROW_TYPE" => "EMPTY"
    );
}

$rowsPerPage = 38;
$pages = array_chunk($printRows, $rowsPerPage);
$totalPages = count($pages);

if ($totalPages <= 0) {
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Price History</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- jQuery & jQuery UI untuk Autocomplete -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <style>
        @page {
            size: A4 portrait;
            margin: 7mm;
        }

        /* Styling spesifik print area agar tetap presisi */
        .page {
            width: 198mm;
            min-height: 285mm;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #ced4da;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); /* Shadow ala Bootstrap */
            padding: 9mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
            font-family: "Courier New", monospace;
            font-size: 11px;
            color: #000000;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Penyesuaian UI Autocomplete agar senada dengan Bootstrap */
        .ui-autocomplete {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 0.875rem;
            z-index: 1050;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .ui-menu-item-wrapper { padding: 8px 12px !important; }

        /* Report Header & Table bawaan lama */
        .header { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .header td { border: none; vertical-align: top; }
        .company { width: 32%; font-family: Arial, sans-serif; font-size: 12px; line-height: 14px; }
        .company-title { font-size: 16px; font-weight: normal; }
        .title-area { width: 38%; text-align: center; font-family: Arial, sans-serif; }
        .report-title { font-size: 18px; font-weight: bold; margin-top: 4px; }
        .right-info { width: 30%; text-align: right; font-family: Arial, sans-serif; font-size: 12px; line-height: 18px; }
        .print-date { margin-top: 18px; }
        
        .price-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .price-table th {
            border-top: 1px solid #000000; border-bottom: 1px solid #000000;
            padding: 4px 3px; height: 22px; font-weight: normal; text-align: left;
        }
        .price-table td { padding: 2px 3px; height: 17px; line-height: 13px; vertical-align: top; white-space: nowrap; overflow: hidden; }

        .col-part { width: 62%; }
        .col-price { width: 9%; text-align: right; }
        .col-start { width: 11%; }
        .col-end { width: 11%; }
        .col-quot { width: 7%; }

        .customer-row td { height: 22px; font-weight: bold; font-size: 12px; padding-top: 6px; letter-spacing: 1px; }
        .part-label { letter-spacing: 8px; font-weight: normal; }
        .item-row td { padding-top: 4px; height: 19px; }
        .dash-row td { border-bottom: 1px dashed #000000; height: 6px; padding: 0; }
        .empty-row td { height: 18px; }
        .num { text-align: right; }
        .center { text-align: center; }

        /* Konfigurasi saat mode PRINT aktif */
        @media print {
            body { background: #ffffff !important; }
            .page {
                margin: 0 auto;
                border: none;
                box-shadow: none;
                padding: 7mm;
            }
            .ui-autocomplete { display: none !important; }
        }
    </style>
</head>

<!-- Menggunakan background abu-abu terang bawaan bootstrap -->
<body class="bg-light">

<!-- Navbar Control Panel (Akan disembunyikan otomatis saat di print berkat class d-print-none) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top d-print-none mb-4 py-3">
    <div class="container-fluid" style="max-width: 1000px;">
        <span class="navbar-brand mb-0 h1">
            <i class="bi bi-file-earmark-text text-primary"></i> Report Filter
        </span>
        
        <div class="collapse navbar-collapse">
            <form method="GET" action="" class="d-flex w-100 align-items-center ms-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="cust_autocomplete" name="cust_label" placeholder="Ketik Kode atau Nama Customer..." value="<?php echo h($filterCustLabel); ?>">
                    <input type="hidden" name="cust_code" id="cust_code" value="<?php echo h($filterCustCode); ?>">
                    
                    <button class="btn btn-primary px-4" type="submit">Cari Data</button>
                    <a href="?" class="btn btn-outline-secondary px-3" title="Reset Filter">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="d-flex ms-4">
            <button type="button" class="btn btn-success me-2 text-nowrap" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="btn btn-danger px-3" onclick="window.close()">
                <i class="bi bi-x-circle"></i> Close
            </button>
        </div>
    </div>
</nav>

<!-- Area Kertas Laporan -->
<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>

    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">PT. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">SALES PRICE HISTORY</div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>

                    <div class="print-date">
                        Print Date : <?php echo h(fmt_print_date()); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="price-table">
            <thead>
                <tr>
                    <th class="col-part">
                        <span class="part-label">P&nbsp; A&nbsp; R&nbsp; T</span>
                    </th>
                    <th class="col-price">Price</th>
                    <th class="col-start">Start Date</th>
                    <th class="col-end">End Date</th>
                    <th class="col-quot">Quot.</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="5">
                                [<?php echo h($r["CUST_CODE"]); ?>]
                                [<?php echo h($r["CUST_COMP"]); ?>]
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td class="col-part">
                                [<?php echo h($r["ITEM_CODE"]); ?>]
                                [<?php echo h($r["ITEM_NO"]); ?>]
                                [<?php echo h($r["ITEM_NAME"]); ?>]
                            </td>
                            <td class="col-price"></td>
                            <td class="col-start"></td>
                            <td class="col-end"></td>
                            <td class="col-quot"></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "PRICE") { ?>
                        <tr class="price-row">
                            <td class="col-part"></td>
                            <td class="col-price num">
                                <?php echo h(fmt_price($r["PRDT_PRICE"])); ?>
                            </td>
                            <td class="col-start">
                                <?php echo h(fmt_date($r["PRDT_START"])); ?>
                            </td>
                            <td class="col-end">
                                <?php echo h(fmt_date($r["PRDT_END"])); ?>
                            </td>
                            <td class="col-quot">
                                <?php echo h($r["PRDT_QNO"]); ?>
                            </td>
                        </tr>

                        <?php
                            $next = isset($pageRows[$i + 1]) ? $pageRows[$i + 1] : null;
                            if ($next === null || $next["ROW_TYPE"] == "ITEM" || $next["ROW_TYPE"] == "CUSTOMER") {
                        ?>
                            <tr class="dash-row">
                                <td colspan="5"></td>
                            </tr>
                        <?php } ?>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="5" class="center">
                                <div class="alert alert-warning py-1 mt-2 mb-0 d-inline-block border-0" style="font-family:system-ui; font-size:12px;">
                                    <i class="bi bi-info-circle me-1"></i> Data tidak ditemukan untuk filter tersebut.
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>

                <?php
                    $fillCount = $rowsPerPage - count($pageRows);
                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>

                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr class="empty-row">
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script inisialisasi Autocomplete -->
<script>
$(document).ready(function() {
    $("#cust_autocomplete").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ajax_customer_autocomplete.php",
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#cust_autocomplete").val(ui.item.label);
            $("#cust_code").val(ui.item.code);
            return false;
        }
    });

    // Kosongkan nilai hidden input (cust_code) apabila teks diubah manual
    $("#cust_autocomplete").on("input", function() {
        $("#cust_code").val("");
    });
});
</script>

</body>
</html>