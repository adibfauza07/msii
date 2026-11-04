<?php
// FILE: msii/qc/delivery_item_list_12month_export_excel.php
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

function get_param($name, $default = "") {
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    return $default;
}

function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") {
        return "";
    }
    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return str_replace("-", "", $value) . "01";
    }
    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }
    return date("Ymd", $ts);
}

$start_month = get_param("START_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "%");

$search_cust = trim($cust_code);
if ($search_cust == "") {
    $search_cust = "%";
}

$start_ymd = month_to_yyyymmdd($start_month);
if ($start_ymd == "") {
    die("Starting Month tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.DeliverySum12Month_idr ?, '%'
";

$stmt = sqlsrv_query($conn, $sql, array($start_ymd));

if ($stmt === false) {
    die("<pre>Query gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
$uniqueCheck = array();
$keyword = strtolower(str_replace("%", "", $search_cust));

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalQty = 0;
    for ($m = 1; $m <= 12; $m++) {
        $qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;
        $totalQty += $qty;
    }

    if ($totalQty <= 0) {
        continue;
    }

    $custCodeVal = safe_trim($r["CUST_CODE"]);
    $custCompVal = safe_trim($r["CUST_COMP"]);
    $partCodeVal = isset($r["PART_NUM"]) ? safe_trim($r["PART_NUM"]) : "";
    $partNameVal = safe_trim($r["PART_NAME"]);

    if ($keyword !== "") {
        $matchCode = (strpos(strtolower($custCodeVal), $keyword) !== false);
        $matchComp = (strpos(strtolower($custCompVal), $keyword) !== false);
        if (!$matchCode && !$matchComp) {
            continue;
        }
    }

    $uniqueKey = $custCodeVal . "|" . $partCodeVal . "|" . $partNameVal;
    if (isset($uniqueCheck[$uniqueKey])) {
        continue;
    }
    $uniqueCheck[$uniqueKey] = true;

    $rows[] = array(
        "CUST_CODE" => $custCodeVal,
        "CUST_COMP" => $custCompVal,
        "PART_CODE" => $partCodeVal,
        "PART_NAME" => $partNameVal
    );
}

$filename = "Delivered_Item_List_12Month_" . $start_ymd . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        table {
            border-collapse: collapse;
        }
        th {
            background-color: #d9d9d9;
            border: 1px solid #000000;
            padding: 6px;
            font-weight: bold;
            text-align: center;
        }
        td {
            border: 1px solid #000000;
            padding: 5px;
            vertical-align: middle;
        }
        .text-mode {
            mso-number-format: "\@";
        }
        .center {
            text-align: center;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            border: none;
        }
        .subtitle {
            font-size: 11px;
            border: none;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="4" class="title">PT. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>
    <tr>
        <td colspan="4" class="title">ITEM LIST (12 MONTH)</td>
    </tr>
    <tr>
        <td colspan="4" class="subtitle">Starting Month: <?php echo h($start_ymd); ?> | Customer Filter: <?php echo h($search_cust); ?></td>
    </tr>
    <tr>
        <td colspan="4" class="subtitle">Print Date: <?php echo h(date("d-M-Y H:i:s")); ?></td>
    </tr>
    <tr>
        <td colspan="4" style="border:none;"></td>
    </tr>
    <thead>
        <tr>
            <th style="width: 50px;">No</th>
            <th style="width: 280px;">Cust Name</th>
            <th style="width: 180px;">Part Code (Item Code)</th>
            <th style="width: 320px;">Part Name (Item Name)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0) { ?>
            <tr>
                <td colspan="4" class="center">Data item selama 12 bulan tidak ditemukan.</td>
            </tr>
        <?php } else { ?>
            <?php for ($i = 0; $i < count($rows); $i++) { ?>
                <?php $r = $rows[$i]; ?>
                <tr>
                    <td class="center"><?php echo ($i + 1); ?></td>
                    <td class="text-mode"><?php echo h($r["CUST_COMP"]); ?></td>
                    <td class="text-mode"><?php echo h($r["PART_CODE"]); ?></td>
                    <td class="text-mode"><?php echo h($r["PART_NAME"]); ?></td>
                </tr>
            <?php } ?>
        <?php } ?>
    </tbody>
</table>

</body>
</html>