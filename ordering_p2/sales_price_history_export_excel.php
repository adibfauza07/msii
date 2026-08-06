<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

/* ============================================================
   Helper Functions
   ============================================================ */
function safe_trim($value) {
    return ($value === null) ? "" : trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim((string)$_GET[$name]);
    if (isset($_POST[$name])) return trim((string)$_POST[$name]);
    return $default;
}

function html_clean($value) {
    $value = (string)$value;
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_date($value) {
    if ($value === null || $value === "") return "";
    if ($value instanceof DateTimeInterface) {
        return $value->format("d-M-Y");
    }
    $ts = strtotime((string)$value);
    if ($ts === false) return "";
    return date("d-M-Y", $ts);
}

function format_price($value) {
    return number_format((float)$value, 4, '.', '');
}

function safe_filename_part($value) {
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$value);
    $value = trim((string)$value, '_');
    return ($value === "") ? "ALL" : $value;
}

/* ============================================================
   Parameter
   ============================================================ */
$cust_code = get_param("CUST_CODE", "%");
if ($cust_code === "") $cust_code = "%";

/* ============================================================
   Query Data
   ============================================================ */
$sql = "
    SET NOCOUNT ON;

    SELECT DISTINCT
        PART.PART_NUM AS PARTNUM,
        PART.PART_CODE AS ITEM_CODE,
        PART.PRICE_CODE AS PRICE_CODE,
        PART.PART_NO AS ITEM_NO,
        PART.PART_NAME AS ITEM_NAME,
        CUST.CUST_CODE AS CUST_CODE,
        CUST.CUST_COMP AS CUST_COMP,
        ISNULL(PRICE_DETAIL.PRDT_PRICE, 0) AS PRDT_PRICE,
        PRICE_DETAIL.PRDT_START,
        PRICE_DETAIL.PRDT_END,
        ISNULL(PRICE_DETAIL.PRDT_QNO, '') AS PRDT_QNO
    FROM dbo.PART_VIEW AS PART
    INNER JOIN dbo.CUST AS CUST
        ON PART.CUST_ID = CUST.CUST_ID
    LEFT OUTER JOIN dbo.PRICE_DETAIL AS PRICE_DETAIL
        ON PART.PRICE_ID = PRICE_DETAIL.PRICE_ID
    WHERE
        DATEDIFF(DAY, GETDATE(), PRICE_DETAIL.PRDT_START) <= 0
        AND DATEDIFF(DAY, GETDATE(), PRICE_DETAIL.PRDT_END) >= 0
        AND (
            ? = '%'
            OR CUST.CUST_CODE = ?
        )
    ORDER BY
        CUST.CUST_CODE,
        PART.PART_NUM,
        PART.PART_CODE,
        PART.PART_NO,
        PART.PART_NAME,
        PRICE_DETAIL.PRDT_START,
        PRICE_DETAIL.PRDT_END
";

$stmt = sqlsrv_query($conn, $sql, array($cust_code, $cust_code));

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
        "PRDT_PRICE" => (float)$r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => safe_trim($r["PRDT_QNO"])
    );
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

/* ============================================================
   Susun Group Customer -> Item + Price dalam satu baris
   ============================================================ */
$printRows = array();
$lastCust = "";
$lastItem = "";

foreach ($dataRows as $r) {
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey !== $lastCust) {
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );
        $lastCust = $custKey;
        $lastItem = "";
    }

    $itemKey = $r["ITEM_CODE"] . "|" . $r["ITEM_NO"] . "|" . $r["ITEM_NAME"];
    $showItem = ($itemKey !== $lastItem);

    $printRows[] = array(
        "ROW_TYPE"   => "ITEM_PRICE",
        "SHOW_ITEM"  => $showItem ? 1 : 0,
        "ITEM_CODE"  => $r["ITEM_CODE"],
        "PRICE_CODE" => $r["PRICE_CODE"],
        "ITEM_NO"    => $r["ITEM_NO"],
        "ITEM_NAME"  => $r["ITEM_NAME"],
        "PARTNUM"    => $r["PARTNUM"],
        "PRDT_PRICE" => $r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => $r["PRDT_QNO"]
    );

    $lastItem = $itemKey;
}

if (count($printRows) === 0) {
    $printRows[] = array("ROW_TYPE" => "EMPTY");
}

/* ============================================================
   Output Excel (.xls) via HTML Table
   ============================================================ */
$customerLabel = ($cust_code === "%") ? "ALL" : $cust_code;
$fileName = "Sales_Price_History_" . safe_filename_part($customerLabel) . "_" . date("Ymd_His") . ".xls";

$exportedAt = date("d-M-Y H:i:s");

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

if (ob_get_length()) {
    @ob_clean();
}

// BOM untuk UTF-8 supaya Excel baca karakter khusus dengan benar
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11pt;
            padding: 10px;
        }

        /* Title */
        .title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            padding: 10px 0;
        }

        /* Meta Info */
        .meta {
            font-size: 10pt;
            padding: 2px 0;
        }

        /* Table */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #D9DEE5;
            padding: 4px 6px;
            vertical-align: middle;
        }

        /* Header Row */
        th {
            background-color: #D9E1F2;
            font-weight: bold;
            text-align: center;
            font-size: 10pt;
        }

        /* Customer Row */
        tr.customer td {
            background-color: #E9ECEF;
            font-weight: bold;
            font-size: 10pt;
        }

        /* Item dan harga berada dalam satu baris */
        tr.item-price td {
            font-size: 10pt;
        }

        tr.item-price.first-item td {
            border-top: 1px solid #ADB5BD;
        }

        tr.separator td {
            height: 3px;
            padding: 0;
            border-top: 0;
            border-left: 1px solid #D9DEE5;
            border-right: 1px solid #D9DEE5;
            border-bottom: 1px dashed #ADB5BD;
        }

        /* Alignment */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Column Widths */
        .col-part { width: 300px; }
        .col-price { width: 100px; mso-number-format:"0.0000"; }
        .col-date { width: 100px; }
        .col-qno { width: 100px; }

        /* Empty Row */
        tr.empty td {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }

        /* Print Settings */
        @page {
            size: landscape;
            margin: 1cm;
        }

        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <!-- Title -->
    <div class="title">SALES PRICE HISTORY</div>
    
    <!-- Meta Information -->
    <div class="meta">Company : P.T. IMC TEKNO INDONESIA</div>
    <div class="meta">Department : PPIC Department</div>
    <div class="meta">Customer : <?php echo html_clean($customerLabel); ?>&emsp;&emsp;Exported : <?php echo html_clean($exportedAt); ?></div>

    <!-- Data Table -->
    <table>
        <thead>
            <tr>
                <th class="col-part">PART</th>
                <th class="col-price">Price</th>
                <th class="col-date">Start Date</th>
                <th class="col-date">End Date</th>
                <th class="col-qno">Quot.</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < count($printRows); $i++): ?>
                <?php $r = $printRows[$i]; ?>

                <?php if ($r["ROW_TYPE"] === "CUSTOMER"): ?>
                    <tr class="customer">
                        <td colspan="5">
                            [<?php echo html_clean($r["CUST_CODE"]); ?>]
                            [<?php echo html_clean($r["CUST_COMP"]); ?>]
                        </td>
                    </tr>

                <?php elseif ($r["ROW_TYPE"] === "ITEM_PRICE"): ?>
                    <tr class="item-price<?php echo !empty($r["SHOW_ITEM"]) ? " first-item" : ""; ?>">
                        <td class="text-left">
                            <?php if (!empty($r["SHOW_ITEM"])): ?>
                                [<?php echo html_clean($r["ITEM_CODE"]); ?>]
                                [<?php echo html_clean($r["ITEM_NO"]); ?>]
                                [<?php echo html_clean($r["ITEM_NAME"]); ?>]
                            <?php endif; ?>
                        </td>
                        <td class="text-right col-price">
                            <?php echo format_price($r["PRDT_PRICE"]); ?>
                        </td>
                        <td class="text-center">
                            <?php echo format_date($r["PRDT_START"]); ?>
                        </td>
                        <td class="text-center">
                            <?php echo format_date($r["PRDT_END"]); ?>
                        </td>
                        <td class="text-center">
                            <?php echo html_clean($r["PRDT_QNO"]); ?>
                        </td>
                    </tr>

                    <?php
                        $next = isset($printRows[$i + 1]) ? $printRows[$i + 1] : null;
                        $nextStartsItem = (
                            $next !== null
                            && $next["ROW_TYPE"] === "ITEM_PRICE"
                            && !empty($next["SHOW_ITEM"])
                        );
                    ?>
                    <?php if ($next === null || $next["ROW_TYPE"] === "CUSTOMER" || $nextStartsItem): ?>
                        <tr class="separator"><td colspan="5"></td></tr>
                    <?php endif; ?>

                <?php elseif ($r["ROW_TYPE"] === "EMPTY"): ?>
                    <tr class="empty">
                        <td colspan="5">Data tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Excel Freeze Pane Instruction (via XML processing instruction) -->
    <!-- Note: For true freeze panes, consider using PhpSpreadsheet library -->

</body>
</html>
<?php
exit;
?>