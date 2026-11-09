<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function get_value($row, $keys, $default = 0) {
    for ($i = 0; $i < count($keys); $i++) {
        $key = $keys[$i];
        if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== "") {
            return $row[$key];
        }
    }
    return $default;
}

$di_id = isset($_GET["DI_ID"]) ? intval($_GET["DI_ID"]) : 0;

if ($di_id <= 0) {
    die("DI_ID tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_PACKINGLIST ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("Query SP_PACKINGLIST gagal.");
}

$rows = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data Packing List tidak ditemukan untuk DI_ID: " . htmlspecialchars($di_id));
}

$head = $rows[0];
$invNo = isset($head["DI_INVNO"]) ? trim((string)$head["DI_INVNO"]) : "Unknown";

$totalPartQty      = 0;
$totalPartWeightKg = 0;
$totalPackQty      = 0;
$totalWeightKg     = 0;

$detailRows = array();

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $partCode = isset($r["PART_CODE"]) ? trim((string)$r["PART_CODE"]) : "";
    $partNo   = isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "";
    $partName = isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "";
    $partNum  = isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "";

    $qty     = isset($r["DIPA_QTY"]) ? (float)$r["DIPA_QTY"] : 0;
    $packQty = isset($r["DIPA_PQTY"]) ? (float)$r["DIPA_PQTY"] : 0;

    $partWeightGram = (float)get_value($r, array("PART_WEIGHT"), 0);

    if (isset($r["PART_WEIGHT_KG"])) {
        $partWeightKg = (float)$r["PART_WEIGHT_KG"];
    } else {
        $partWeightKg = ($qty * $partWeightGram) / 1000;
    }

    $packWeightGram = (float)get_value($r, array("PACK_WEIGHT_GR", "PACK_WEIGHT"), 0);

    if (isset($r["TOTAL_WEIGHT_KG"])) {
        $totalLineWeightKg = (float)$r["TOTAL_WEIGHT_KG"];
    } elseif (isset($r["TWEIGHT"])) {
        $totalLineWeightKg = (float)$r["TWEIGHT"];
    } else {
        $packWeightKgTotal = ($packWeightGram * $packQty) / 1000;
        $totalLineWeightKg = $partWeightKg + $packWeightKgTotal;
    }

    $partNoDisplay = $partNo;
    if ($partNoDisplay == "") {
        $partNoDisplay = $partCode;
    }

    $partNameDisplay = $partName;
    if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
        $partNameDisplay = trim($partNameDisplay . " " . $partNum);
    }

    $detailRows[] = array(
        "PART_NO"          => $partNoDisplay,
        "PART_NAME"        => $partNameDisplay,
        "DIPA_QTY"         => $qty,
        "PART_WEIGHT_KG"   => $partWeightKg,
        "DIPA_PQTY"        => $packQty,
        "PACK_WEIGHT_GR"   => $packWeightGram,
        "TOTAL_WEIGHT_KG"  => $totalLineWeightKg
    );

    $totalPartQty      += $qty;
    $totalPartWeightKg += $partWeightKg;
    $totalPackQty      += $packQty;
    $totalWeightKg     += $totalLineWeightKg;
}

// FORMAT HEADER AGAR JADI EXCEL
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"PackingList_" . $invNo . ".xls\"");
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
                <th rowspan="2">#</th>
                <th rowspan="2">PART NUMBER</th>
                <th rowspan="2">PART NAME</th>
                <th colspan="2">PART</th>
                <th colspan="2">PACKING</th>
                <th rowspan="2">TOTAL Weight (kg)</th>
            </tr>
            <tr>
                <th>Qty (pcs)</th>
                <th>Weight (kg)</th>
                <th>Qty (box)</th>
                <th>Weight (gr)</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < count($detailRows); $i++) { 
                $r = $detailRows[$i]; 
            ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($r["PART_NO"]); ?></td>
                <td><?php echo htmlspecialchars($r["PART_NAME"]); ?></td>
                
                <!-- Penting: Cetak angka murni tanpa number_format -->
                <td><?php echo $r["DIPA_QTY"]; ?></td>
                <td><?php echo $r["PART_WEIGHT_KG"]; ?></td>
                <td><?php echo $r["DIPA_PQTY"]; ?></td>
                <td><?php echo $r["PACK_WEIGHT_GR"]; ?></td>
                <td><?php echo $r["TOTAL_WEIGHT_KG"]; ?></td>
            </tr>
            <?php } ?>
            <tr>
                <td colspan="3" style="text-align: center; font-weight: bold;">TOTAL</td>
                <!-- Cetak total murni tanpa number_format -->
                <td style="font-weight: bold;"><?php echo $totalPartQty; ?></td>
                <td style="font-weight: bold;"><?php echo $totalPartWeightKg; ?></td>
                <td style="font-weight: bold;"><?php echo $totalPackQty; ?></td>
                <td></td>
                <td style="font-weight: bold;"><?php echo $totalWeightKg; ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>