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
    die("Query Sales Price History gagal.");
}

$dataRows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = array(
        "ITEM_CODE"  => safe_trim($r["ITEM_CODE"]),
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
   Susun Group Customer -> Item + Price
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
        "ITEM_NO"    => $r["ITEM_NO"],
        "ITEM_NAME"  => $r["ITEM_NAME"],
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
   Output Excel via CSV
   ============================================================ */
$customerLabel = ($cust_code === "%") ? "ALL" : $cust_code;
$fileName = "Sales_Price_History_" . safe_filename_part($customerLabel) . "_" . date("Ymd_His") . ".csv";
$exportedAt = date("d-M-Y H:i:s");

if (ob_get_length()) {
    @ob_clean();
}

// Set Header CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tambahkan BOM agar karakter terbaca UTF-8 di Excel
fputs($output, "\xEF\xBB\xBF");

// Header Laporan
fputcsv($output, array("SALES PRICE HISTORY"));
fputcsv($output, array("Company :", "P.T. IMC TEKNO INDONESIA"));
fputcsv($output, array("Department :", "PPIC Department"));
fputcsv($output, array("Customer :", $customerLabel, "", "Exported :", $exportedAt));
fputcsv($output, array()); // Baris Kosong

// Header Tabel (7 Kolom)
fputcsv($output, array("Part Code", "Part No", "Part Name", "Price", "Start Date", "End Date", "Quot."));

// Looping Data
foreach ($printRows as $r) {
    if ($r["ROW_TYPE"] === "CUSTOMER") {
        // Tampilkan Identitas Customer di baris tersendiri
        fputcsv($output, array("[" . $r["CUST_CODE"] . "] [" . $r["CUST_COMP"] . "]"));
    } 
    elseif ($r["ROW_TYPE"] === "ITEM_PRICE") {
        // Tampilkan data Part jika setShowItem true, jika false kosongkan sel agar rapi
        $iCode = !empty($r["SHOW_ITEM"]) ? $r["ITEM_CODE"] : "";
        $iNo   = !empty($r["SHOW_ITEM"]) ? $r["ITEM_NO"] : "";
        $iName = !empty($r["SHOW_ITEM"]) ? $r["ITEM_NAME"] : "";
        
        $price = format_price($r["PRDT_PRICE"]);
        $start = format_date($r["PRDT_START"]);
        $end   = format_date($r["PRDT_END"]);
        $qno   = $r["PRDT_QNO"];

        fputcsv($output, array($iCode, $iNo, $iName, $price, $start, $end, $qno));
    } 
    elseif ($r["ROW_TYPE"] === "EMPTY") {
        fputcsv($output, array("Data tidak ditemukan."));
    }
}

fclose($output);
exit;
?>