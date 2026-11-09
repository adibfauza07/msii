<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{4}-\d{2}$/', $value)) return str_replace("-", "", $value) . "01";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function fmt_date($value) {
    if ($value instanceof DateTime) return $value->format("d-M-Y");
    if ($value === null || $value === "") return "";
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("d-M-Y", $ts);
}

$asper_month = isset($_GET["ASPER_MONTH"]) ? trim($_GET["ASPER_MONTH"]) : date("Y-m");
$cust_code   = isset($_GET["CUST_CODE"]) ? trim($_GET["CUST_CODE"]) : "%";

if ($cust_code == "") {
    $cust_code = "%";
}

$asper_ymd = month_to_yyyymmdd($asper_month);

if ($asper_ymd == "") {
    die("Bulan tidak valid.");
}

$sql = "
    SET NOCOUNT ON;

    DECLARE @ASPER_DT DATETIME;
    SET @ASPER_DT = CONVERT(DATETIME, ?, 112);

    SELECT TOP 100 PERCENT
        C.CUST_CODE,
        C.CUST_COMP,
        DI.DI_INVNO,
        DI.DI_DATE,
        PV.PART_CODE,
        PV.PART_NAME,
        PV.PART_NO,
        PV.PART_NUM,
        DP.DIPA_QTY,
        DP.DIPA_PQTY,
        PK.PACK_DESC, 
        PK.PACK_CODE,
        PV.PART_WEIGHT,
        PK.PACK_WEIGHT
    FROM dbo.DI_PART AS DP
    INNER JOIN dbo.DI AS DI ON DI.DI_ID = DP.DI_ID
    INNER JOIN dbo.CUST AS C ON DI.CUST_ID = C.CUST_ID
    INNER JOIN dbo.PART_VIEW AS PV ON DP.PRICE_ID = PV.PRICE_ID
    LEFT JOIN dbo.PACK AS PK ON DP.PACK_ID = PK.PACK_ID
    WHERE
        DATEDIFF(MONTH, DI.DI_DATE, @ASPER_DT) = 0
        AND C.CUST_CODE LIKE ?
    ORDER BY
        C.CUST_COMP,
        DI.DI_DATE,
        DI.DI_INVNO,
        PV.PART_NAME
";

$stmt = sqlsrv_query($conn, $sql, array($asper_ymd, $cust_code));

if ($stmt === false) {
    die("Query Monthly Packing List Export gagal.");
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $partCode = safe_trim($r["PART_CODE"]);
    $partNo   = safe_trim($r["PART_NO"]);
    $partName = safe_trim($r["PART_NAME"]);
    $partNum  = safe_trim($r["PART_NUM"]);

    $qty     = isset($r["DIPA_QTY"]) ? (float)$r["DIPA_QTY"] : 0;
    $packQty = isset($r["DIPA_PQTY"]) ? (float)$r["DIPA_PQTY"] : 0;
    
    $packCode = isset($r["PACK_CODE"]) ? safe_trim($r["PACK_CODE"]) : "";
    $packDesc = isset($r["PACK_DESC"]) ? safe_trim($r["PACK_DESC"]) : "";

    $partWeightGram = isset($r["PART_WEIGHT"]) ? (float)$r["PART_WEIGHT"] : 0;
    $partWeightKg = ($qty * $partWeightGram) / 1000;

    $packWeightGram = isset($r["PACK_WEIGHT"]) ? (float)$r["PACK_WEIGHT"] : 0;
    
    $packWeightKgTotal = ($packWeightGram * $packQty) / 1000;
    $totalLineWeightKg = $partWeightKg + $packWeightKgTotal;

    $partNoDisplay = $partNo;
    if ($partNoDisplay == "") $partNoDisplay = $partCode;

    $partNameDisplay = $partName;
    if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
        $partNameDisplay = trim($partNameDisplay . " " . $partNum);
    }

    $rows[] = array(
        "CUST_CODE"       => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"       => safe_trim($r["CUST_COMP"]),
        "DI_INVNO"        => safe_trim($r["DI_INVNO"]),
        "DI_DATE"         => $r["DI_DATE"],
        "PART_NO"         => $partNoDisplay,
        "PART_NAME"       => $partNameDisplay,
        "DIPA_QTY"        => $qty,
        "PART_WEIGHT_KG"  => $partWeightKg,
        "DIPA_PQTY"       => $packQty,
        "PACK_CODE"       => $packCode,
        "PACK_DESC"       => $packDesc,
        "PACK_WEIGHT_GR"  => $packWeightGram,
        "TOTAL_WEIGHT_KG" => $totalLineWeightKg
    );
}

$printRows = array();
$lastCust = "";
$lastInv = "";

$custPartQty = 0; $custPartKg = 0; $custPackQty = 0; $custTotalKg = 0;
$invPartQty = 0;  $invPartKg = 0;  $invPackQty = 0;  $invTotalKg = 0;
$grandPartQty = 0; $grandPartKg = 0; $grandPackQty = 0; $grandTotalKg = 0;
$invNo = "";

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $custKey = $r["CUST_COMP"];
    $invKey  = $r["CUST_COMP"] . "|" . $r["DI_INVNO"];

    if ($custKey != $lastCust) {
        if ($lastInv != "") {
            $printRows[] = array(
                "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
                "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
            );
        }
        if ($lastCust != "") {
            $printRows[] = array(
                "ROW_TYPE" => "CUSTOMER_TOTAL",
                "QTY" => $custPartQty, "PART_KG" => $custPartKg, "PQTY" => $custPackQty, "TOTAL_KG" => $custTotalKg
            );
        }
        $printRows[] = array("ROW_TYPE" => "CUSTOMER", "CUST_COMP" => $r["CUST_COMP"]);
        
        $lastCust = $custKey; $lastInv = "";
        $custPartQty = 0; $custPartKg = 0; $custPackQty = 0; $custTotalKg = 0;
        $invPartQty = 0;  $invPartKg = 0;  $invPackQty = 0;  $invTotalKg = 0;
        $invNo = "";
    }

    $showInv = 0;
    if ($invKey != $lastInv) {
        if ($lastInv != "") {
            $printRows[] = array(
                "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
                "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
            );
        }
        $showInv = 1; $lastInv = $invKey; $invNo = $r["DI_INVNO"];
        $invPartQty = 0; $invPartKg = 0; $invPackQty = 0; $invTotalKg = 0;
    }

    $printRows[] = array(
        "ROW_TYPE"        => "DETAIL",
        "SHOW_INV"        => $showInv,
        "DI_INVNO"        => $r["DI_INVNO"],
        "DI_DATE"         => $r["DI_DATE"],
        "PART_NO"         => $r["PART_NO"],
        "PART_NAME"       => $r["PART_NAME"],
        "DIPA_QTY"        => $r["DIPA_QTY"],
        "PART_WEIGHT_KG"  => $r["PART_WEIGHT_KG"],
        "DIPA_PQTY"       => $r["DIPA_PQTY"],
        "PACK_CODE"       => $r["PACK_CODE"],
        "PACK_DESC"       => $r["PACK_DESC"],
        "PACK_WEIGHT_GR"  => $r["PACK_WEIGHT_GR"],
        "TOTAL_WEIGHT_KG" => $r["TOTAL_WEIGHT_KG"]
    );

    $invPartQty += $r["DIPA_QTY"]; $invPartKg += $r["PART_WEIGHT_KG"]; $invPackQty += $r["DIPA_PQTY"]; $invTotalKg += $r["TOTAL_WEIGHT_KG"];
    $custPartQty += $r["DIPA_QTY"]; $custPartKg += $r["PART_WEIGHT_KG"]; $custPackQty += $r["DIPA_PQTY"]; $custTotalKg += $r["TOTAL_WEIGHT_KG"];
    $grandPartQty += $r["DIPA_QTY"]; $grandPartKg += $r["PART_WEIGHT_KG"]; $grandPackQty += $r["DIPA_PQTY"]; $grandTotalKg += $r["TOTAL_WEIGHT_KG"];
}

if ($lastInv != "") {
    $printRows[] = array(
        "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
        "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
    );
}
if ($lastCust != "") {
    $printRows[] = array(
        "ROW_TYPE" => "CUSTOMER_TOTAL",
        "QTY" => $custPartQty, "PART_KG" => $custPartKg, "PQTY" => $custPackQty, "TOTAL_KG" => $custTotalKg
    );
}
if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE" => "GRAND_TOTAL",
        "QTY" => $grandPartQty, "PART_KG" => $grandPartKg, "PQTY" => $grandPackQty, "TOTAL_KG" => $grandTotalKg
    );
}

$filename = "Monthly_Packing_List_" . str_replace("-", "", $asper_month) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <th style="background-color: #d4d0c8;">INV#</th>
                <th style="background-color: #d4d0c8;">DATE</th>
                <th style="background-color: #d4d0c8;">PART NUMBER</th>
                <th style="background-color: #d4d0c8;">PART NAME</th>
                <th style="background-color: #d4d0c8;">Qty(pcs)</th>
                <th style="background-color: #d4d0c8;">Wgt(kg)</th>
                <th style="background-color: #d4d0c8;">Qty(box)</th>
                <th style="background-color: #d4d0c8;">Jenis Box</th>
                <th style="background-color: #d4d0c8;">Pack Desc</th>
                <th style="background-color: #d4d0c8;">Wgt(gr)</th>
                <th style="background-color: #d4d0c8;">Total Wgt(kg)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($printRows as $r) { ?>
                <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                    <tr>
                        <td colspan="11" style="font-weight: bold; background-color: #e9e9e9; font-size: 14px;">
                            <?php echo htmlspecialchars($r["CUST_COMP"]); ?>
                        </td>
                    </tr>
                <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                    <tr>
                        <td style="font-weight: bold;">
                            <?php echo $r["SHOW_INV"] == 1 ? htmlspecialchars($r["DI_INVNO"]) : ""; ?>
                        </td>
                        <td><?php echo htmlspecialchars(fmt_date($r["DI_DATE"])); ?></td>
                        <td><?php echo htmlspecialchars($r["PART_NO"]); ?></td>
                        <td><?php echo htmlspecialchars($r["PART_NAME"]); ?></td>
                        <td><?php echo $r["DIPA_QTY"]; ?></td>
                        <td><?php echo $r["PART_WEIGHT_KG"]; ?></td>
                        <td><?php echo $r["DIPA_PQTY"]; ?></td>
                        <td><?php echo htmlspecialchars($r["PACK_CODE"]); ?></td>
                        <td><?php echo htmlspecialchars($r["PACK_DESC"]); ?></td>
                        <td><?php echo $r["PACK_WEIGHT_GR"]; ?></td>
                        <td><?php echo $r["TOTAL_WEIGHT_KG"]; ?></td>
                    </tr>
                <?php } elseif ($r["ROW_TYPE"] == "INVOICE_TOTAL") { ?>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: bold; font-style: italic;">TOTAL INVOICE <?php echo htmlspecialchars($r["DI_INVNO"]); ?></td>
                        <td style="font-weight: bold; font-style: italic;"><?php echo $r["QTY"]; ?></td>
                        <td style="font-weight: bold; font-style: italic;"><?php echo $r["PART_KG"]; ?></td>
                        <td style="font-weight: bold; font-style: italic;"><?php echo $r["PQTY"]; ?></td>
                        <td colspan="3"></td>
                        <td style="font-weight: bold; font-style: italic;"><?php echo $r["TOTAL_KG"]; ?></td>
                    </tr>
                <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: bold; background-color: #f2f2f2;">TOTAL CUSTOMER</td>
                        <td style="font-weight: bold; background-color: #f2f2f2;"><?php echo $r["QTY"]; ?></td>
                        <td style="font-weight: bold; background-color: #f2f2f2;"><?php echo $r["PART_KG"]; ?></td>
                        <td style="font-weight: bold; background-color: #f2f2f2;"><?php echo $r["PQTY"]; ?></td>
                        <td colspan="3" style="background-color: #f2f2f2;"></td>
                        <td style="font-weight: bold; background-color: #f2f2f2;"><?php echo $r["TOTAL_KG"]; ?></td>
                    </tr>
                <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: bold; background-color: #d4d0c8; font-size: 14px;">GRAND TOTAL</td>
                        <td style="font-weight: bold; background-color: #d4d0c8; font-size: 14px;"><?php echo $r["QTY"]; ?></td>
                        <td style="font-weight: bold; background-color: #d4d0c8; font-size: 14px;"><?php echo $r["PART_KG"]; ?></td>
                        <td style="font-weight: bold; background-color: #d4d0c8; font-size: 14px;"><?php echo $r["PQTY"]; ?></td>
                        <td colspan="3" style="background-color: #d4d0c8;"></td>
                        <td style="font-weight: bold; background-color: #d4d0c8; font-size: 14px;"><?php echo $r["TOTAL_KG"]; ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>