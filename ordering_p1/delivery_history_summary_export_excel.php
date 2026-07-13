<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

/* ============================================================
   Helper Functions
   ============================================================ */
function safe_trim($value) { return ($value === null) ? "" : trim((string)$value); }
function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}
function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{4}-\d{2}$/', $value)) return str_replace("-", "", $value) . "01";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    return ($ts === false) ? "" : date("Ymd", $ts);
}
function fmt_num($value, $decimal = 0) { return number_format((float)($value ?: 0), $decimal, ".", ","); }
function fmt_price($value) { return number_format((float)($value ?: 0), 4, ".", ","); }
function fmt_amount($value) { return number_format((float)($value ?: 0), 2, ".", ","); }
function get_usd_factor($row) {
    $crate = (isset($row["CRATE"]) && $row["CRATE"] != 0) ? (float)$row["CRATE"] : 1;
    $basecrate = (isset($row["BASECRATE"]) && $row["BASECRATE"] != 0) ? (float)$row["BASECRATE"] : 1;
    return $crate / $basecrate;
}
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }

/* ============================================================
   Ambil Parameter & Validasi
   ============================================================ */
 $asper_month = get_param("ASPER_MONTH", date("Y-m"));
 $cust_code   = get_param("CUST_CODE", "");
if ($cust_code == "") $cust_code = "%";

 $asper_ymd = month_to_yyyymmdd($asper_month);
if ($asper_ymd == "") die("Month tidak valid.");

/* ============================================================
   Eksekusi Stored Procedure
   ============================================================ */
 $sql = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_HISTORY_char_sum ?, ?";
 $stmt = sqlsrv_query($conn, $sql, array($asper_ymd, $cust_code));
if ($stmt === false) {
    die("<pre>Query Delivery History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

/* ============================================================
   Fetch Data & Susun Rows
   ============================================================ */
 $rows = array();
 $monthYear = "";

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($monthYear == "" && isset($r["MonthYear"])) $monthYear = safe_trim($r["MonthYear"]);

    $qty   = isset($r["QTY"])        ? (float)$r["QTY"]        : 0;
    $price = isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0;
    $amount = isset($r["AMOUNT"]) ? (float)$r["AMOUNT"] : ($qty * $price);
    $usdFactor = get_usd_factor($r);

    $rows[] = array(
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PART_NUM"   => safe_trim($r["PART_NUM"]),
        "PART_NAME"  => safe_trim($r["PART_NAME"]),
        "CURR_CODE"  => safe_trim($r["CURR_CODE"]),
        "PART_PRICE" => $price,
        "QTY"        => $qty,
        "AMOUNT"     => $amount,
        "USD_AMT"    => $amount * $usdFactor
    );
}

/* Sort by customer, then item code (PART_NUM) within each customer. */
usort($rows, function ($a, $b) {
        $cmp = strnatcasecmp($a["CUST_CODE"], $b["CUST_CODE"]);
        if ($cmp !== 0) return $cmp;

        $cmp = strnatcasecmp($a["CUST_COMP"], $b["CUST_COMP"]);
        if ($cmp !== 0) return $cmp;

        return strnatcasecmp($a["PART_NUM"], $b["PART_NUM"]);
    });

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

if ($monthYear == "") {
    $ts = strtotime(substr($asper_ymd, 0, 4) . "-" . substr($asper_ymd, 4, 2) . "-01");
    $monthYear = date("F Y", $ts);
}

/* ============================================================
   Grouping Data (Customer & Grand Total)
   ============================================================ */
 $printRows = array();

 $lastCust = ""; $lastCustCode = ""; $lastCustComp = "";
 $custQty = 0; $custAmount = 0; $custUsdAmount = 0;
 $grandQty = 0; $grandAmount = 0; $grandUsdAmount = 0;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        if ($lastCust != "") {
            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER_TOTAL",
                "CUST_CODE" => $lastCustCode,
                "CUST_COMP" => $lastCustComp,
                "QTY"       => $custQty,
                "AMOUNT"    => $custAmount,
                "USD_AMT"   => $custUsdAmount
            );
        }
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );

        $lastCust = $custKey; $lastCustCode = $r["CUST_CODE"]; $lastCustComp = $r["CUST_COMP"];
        $custQty = 0; $custAmount = 0; $custUsdAmount = 0;
    }

    $printRows[] = array(
        "ROW_TYPE"   => "DETAIL",
        "PART_NUM"   => $r["PART_NUM"],
        "PART_NAME"  => $r["PART_NAME"],
        "PART_PRICE" => $r["PART_PRICE"],
        "CURR_CODE"  => $r["CURR_CODE"],
        "QTY"        => $r["QTY"],
        "AMOUNT"     => $r["AMOUNT"],
        "USD_AMT"    => $r["USD_AMT"]
    );

    $custQty       += $r["QTY"];
    $custAmount    += $r["AMOUNT"];
    $custUsdAmount += $r["USD_AMT"];
    $grandQty       += $r["QTY"];
    $grandAmount    += $r["AMOUNT"];
    $grandUsdAmount += $r["USD_AMT"];
}

if ($lastCust != "") {
    $printRows[] = array(
        "ROW_TYPE"  => "CUSTOMER_TOTAL",
        "CUST_CODE" => $lastCustCode,
        "CUST_COMP" => $lastCustComp,
        "QTY"       => $custQty,
        "AMOUNT"    => $custAmount,
        "USD_AMT"   => $custUsdAmount
    );
}
if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE" => "GRAND_TOTAL",
        "QTY"      => $grandQty,
        "AMOUNT"   => $grandAmount,
        "USD_AMT"  => $grandUsdAmount
    );
}
if (count($printRows) == 0) {
    $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data delivery history tidak ditemukan.");
}

/* ============================================================
   Generate Excel File Output
   ============================================================ */
 $fileDate = date("Ymd_His");
 $fileName = "Delivery_History_Sum_" . str_replace(array(" ", "/", "\\"), "_", $monthYear) . "_" . $fileDate . ".xls";

// Header untuk download Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<!--[if gte mso 9]>
<xml>
  <x:ExcelWorkbook>
    <x:ExcelWorksheets>
      <x:ExcelWorksheet>
        <x:Name>Delivery History</x:Name>
        <x:WorksheetOptions>
          <x:DisplayGridlines/>
          <x:FreezePanes/>
          <x:FrozenNoSplit/>
          <x:SplitHorizontal>4</x:SplitHorizontal>
          <x:TopRowBottomPane>4</x:TopRowBottomPane>
          <x:ActivePane>2</x:ActivePane>
        </x:WorksheetOptions>
      </x:ExcelWorksheet>
    </x:ExcelWorksheets>
  </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; }
    table { border-collapse: collapse; mso-number-format: "\@"; }
    th, td { border: 1px solid #000; padding: 4px 6px; }
    .title { font-size: 16px; font-weight: bold; }
    .subtitle { font-size: 11px; }
    .num { text-align: right; mso-number-format: "#,##0"; }
    .num2 { text-align: right; mso-number-format: "#,##0.00"; }
    .num4 { text-align: right; mso-number-format: "#,##0.0000"; }
    .center { text-align: center; }
    .header { background-color: #d9e1f2; font-weight: bold; }
    .customer { background-color: #e9ecef; font-weight: bold; }
    .cust-total { background-color: #f8f9fa; font-weight: bold; }
    .grand { background-color: #d1ecf1; font-weight: bold; }
</style>
</head>
<body>

<table border="1" cellspacing="0" cellpadding="4">
    <tr>
        <td colspan="7" class="title">DELIVERY HISTORY (SUMMARY)</td>
    </tr>
    <tr>
        <td colspan="7" class="subtitle"><b>Month :</b> <?= h($monthYear) ?></td>
    </tr>
    <tr>
        <td colspan="7" class="subtitle"><b>Customer :</b> <?= h(($cust_code == "%" ? "ALL" : $cust_code)) ?></td>
    </tr>
    <tr>
        <td colspan="7" class="subtitle"><b>Printed :</b> <?= h(date("d-M-Y h:i:sA")) ?></td>
    </tr>
    <tr class="header">
        <th style="width: 15%;">CODE</th>
        <th style="width: 35%;">NAME</th>
        <th class="num" style="width: 10%;">PRICE</th>
        <th class="center" style="width: 10%;">CURR</th>
        <th class="num" style="width: 10%;">QTY</th>
        <th class="num" style="width: 10%;">AMOUNT</th>
        <th class="num" style="width: 10%;">USD.AMT</th>
    </tr>
    <?php foreach ($printRows as $r): ?>
        <?php if ($r["ROW_TYPE"] == "CUSTOMER"): ?>
            <tr class="customer">
                <td colspan="7"><?= h($r["CUST_CODE"]) ?>   <?= h($r["CUST_COMP"]) ?></td>
            </tr>
        <?php elseif ($r["ROW_TYPE"] == "DETAIL"): ?>
            <tr>
                <td><?= h($r["PART_NUM"]) ?></td>
                <td><?= h($r["PART_NAME"]) ?></td>
                <td class="num4"><?= h(fmt_price($r["PART_PRICE"])) ?></td>
                <td class="center"><?= h($r["CURR_CODE"]) ?></td>
                <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                <td class="num2"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                <td class="num2"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
            </tr>
        <?php elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL"): ?>
            <tr class="cust-total">
                <td colspan="4" class="num" style="text-align:right;">Subtotal Customer <?= h($r["CUST_CODE"]) ?> :</td>
                <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                <td class="num2"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                <td class="num2"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
            </tr>
        <?php elseif ($r["ROW_TYPE"] == "GRAND_TOTAL"): ?>
            <tr class="grand">
                <td colspan="4" class="num" style="text-align:right;">GRAND TOTAL :</td>
                <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                <td class="num2"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                <td class="num2"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
            </tr>
        <?php elseif ($r["ROW_TYPE"] == "EMPTY"): ?>
            <tr>
                <td colspan="7" class="center"><?= h($r["MESSAGE"]) ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
</table>

</body>
</html>