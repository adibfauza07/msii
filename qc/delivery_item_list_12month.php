<?php
// FILE: msii/qc/delivery_item_list_12month.php
require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($conn) || $conn === false) {
    die("<div class='alert alert-danger'>Koneksi database ordering gagal.</div>");
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists('safe_trim')) {
    function safe_trim($value) {
        if ($value === null) {
            return "";
        }
        return trim((string)$value);
    }
}

if (!function_exists('get_param')) {
    function get_param($name, $default = "") {
        if (isset($_GET[$name])) {
            return trim($_GET[$name]);
        }
        if (isset($_POST[$name])) {
            return trim($_POST[$name]);
        }
        return $default;
    }
}

if (!function_exists('month_to_yyyymmdd')) {
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
}

if (!function_exists('yyyymmdd_to_month_input')) {
    function yyyymmdd_to_month_input($value) {
        $value = trim($value);
        if ($value == "") {
            return date("Y-m");
        }
        if (preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4) . "-" . substr($value, 4, 2);
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return date("Y-m");
        }
        return date("Y-m", $ts);
    }
}

if (!function_exists('fmt_print_datetime')) {
    function fmt_print_datetime() {
        return date("d-M-Y H:i:s");
    }
}

$is_filter   = get_param("RUN", "") == "1";
$auto_pdf    = get_param("EXPORT_PDF", "") == "1";
$start_month = get_param("START_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");
$plant_param = get_param("plant", isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : "p1");
$page_param  = get_param("page", "delivery_item_list_12month");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = month_to_yyyymmdd($start_month);

// --- AMBIL DAFTAR CUSTOMER UNTUK AUTOCOMPLETE ---
$allCustomers = array();
if ($start_ymd != "") {
    $sqlCust = "SET NOCOUNT ON; EXEC dbo.DeliverySum12Month_idr ?, '%'";
    $stmtCust = @sqlsrv_query($conn, $sqlCust, array($start_ymd));
    if ($stmtCust !== false) {$seenCust = array();
        while ($rc = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
            $cc = safe_trim($rc["CUST_CODE"]);
            $cn = safe_trim($rc["CUST_COMP"]);
            if ($cc != "" && !isset($seenCust[$cc])) {
                $seenCust[$cc] = true;
                $allCustomers[] = array(
                    "CUST_CODE" => $cc,
                    "CUST_COMP" => $cn
                );
            }
        }
    }
}

$rows = array();
$printRows = array();$pages = array();

$totalPages = 0;
$rowsPerPage = 55;

if ($is_filter) {
    if ($start_ymd == "") {
        die("Starting Month tidak valid.");
    }

    $search_cust = trim($cust_code);
    if ($search_cust == "") {
        $search_cust = "%";
    }

    // Ambil semua data terlebih dahulu dengan parameter '%' agar kita bisa memfilter
    // baik berdasarkan CUST_CODE maupun potongan nama CUST_COMP (misal ketik "ya" atau "plati")
    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.DeliverySum12Month_idr ?, '%'
    ";

    $stmt = sqlsrv_query($conn, $sql, array($start_ymd));

    if ($stmt === false) {
        die("<pre>Query Delivery Summary 12 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    $uniqueCheck = array();
    $keyword = strtolower(str_replace("%", "", $search_cust));

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {$totalQty = 0;
        for ($m = 1; $m <= 12; $m++) {$qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;
            $totalQty +=$qty;
        }

        // Hanya tampilkan item yang ter-delivery selama 1 tahun tersebut (Total Qty > 0)
        if ($totalQty <= 0) {
            continue;
        }

        $custCodeVal = safe_trim($r["CUST_CODE"]);
        $custCompVal = safe_trim($r["CUST_COMP"]);
        $partCodeVal = isset($r["PART_NUM"]) ? safe_trim($r["PART_NUM"]) : "";
        $partNameVal = safe_trim($r["PART_NAME"]);

        // Filter berdasarkan CUST_CODE atau CUST_COMP jika bukan '%'
        if ($keyword !== "") {
            $matchCode = (strpos(strtolower($custCodeVal), $keyword) !== false);$matchComp = (strpos(strtolower($custCompVal),$keyword) !== false);
            if (!$matchCode && !$matchComp) {
                continue;
            }
        }

        $uniqueKey =$custCodeVal . "|" . $partCodeVal . "|" . $partNameVal;
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

    for ($i = 0; $i < count($rows);$i++) {
        $r =$rows[$i];$printRows[] = array(
            "ROW_TYPE"  => "DETAIL",
            "NO"        => $i + 1,
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"],
            "PART_CODE" => $r["PART_CODE"],
            "PART_NAME" => $r["PART_NAME"]
        );
    }

    if (count($printRows) == 0) {$printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data item delivery untuk customer '" . $cust_code . "' selama 12 bulan tidak ditemukan."
        );
    }

    $pages = array_chunk($printRows,$rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {$totalPages = 1;
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>

<style>
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    .report-wrapper {
        font-family: "Courier New", monospace;
        font-size: 10px;
        color: #000;
    }

    /* FILTER */
    .filter-bar {
        width: 210mm;
        margin: 5px auto;
        background: #d4d0c8;
        border: 1px solid #666;
        padding: 6px;
        box-sizing: border-box;
        font-family: Tahoma, Arial, sans-serif;
        font-size: 11px;
    }

    .filter-bar input {
        height: 24px;
        border: 1px solid #777;
        font-size: 11px;
        padding: 2px 4px;
        background: #fff;
    }

    .filter-month {
        width: 130px;
    }

    .filter-cust {
        width: 200px;
    }

    .filter-bar button {
        height: 24px;
        font-size: 11px;
        cursor: pointer;
        padding: 0 8px;
    }

    /* AUTOCOMPLETE */
    .autocomplete-wrap {
        position: relative;
        display: inline-block;
    }

    .autocomplete-list {
        position: absolute;
        top: 24px;
        left: 0;
        width: 420px;
        max-height: 250px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #444;
        z-index: 9999;
        display: none;
        text-align: left;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .autocomplete-item {
        padding: 5px 8px;
        border-bottom: 1px solid #ddd;
        cursor: pointer;
        font-size: 11px;
        color: #000;
    }

    .autocomplete-item:hover,
    .autocomplete-item.active {
        background: #2f70c9;
        color: #fff;
    }

    /* BUTTON */
    .print-bar {
        width: 210mm;
        margin: 5px auto;
        text-align: right;
        font-family: Tahoma, Arial, sans-serif;
    }

    .print-bar button {
        padding: 4px 10px;
        font-size: 11px;
        cursor: pointer;
    }

    /* PAGE */
    .page {
        width: 210mm;
        min-height: 297mm;
        margin: 5px auto;
        background: #fff;
        border: 1px solid #000;
        padding: 6mm;
        box-sizing: border-box;
        page-break-after: always;
    }

    .page:last-child {
        page-break-after: auto;
    }

    /* HEADER */
    .header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }

    .header td {
        border: none;
        vertical-align: top;
    }

    .company {
        width: 30%;
        font-family: Arial, sans-serif;
        font-size: 10px;
        line-height: 12px;
    }

    .company-title {
        font-size: 13px;
        font-weight: bold;
    }

    .title-area {
        width: 40%;
        text-align: center;
    }

    .report-title {
        font-family: Arial, sans-serif;
        font-size: 15px;
        font-weight: bold;
    }

    .start-month {
        font-family: Arial, sans-serif;
        font-size: 10px;
        margin-top: 2px;
    }

    .right-info {
        width: 30%;
        text-align: right;
        font-family: Arial, sans-serif;
        font-size: 10px;
    }

    /* TABLE */
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .summary-table th,
    .summary-table td {
        padding: 3px 4px;
        height: 14px;
        line-height: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 9px;
        border-bottom: 1px dotted #ccc;
    }

    .summary-table thead th {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        text-align: left;
        font-weight: bold;
        font-size: 9px;
        background: #f0f0f0;
    }

    /* COLUMNS */
    .col-no {
        width: 6%;
        text-align: center !important;
        border-right: 1px dashed #000;
    }

    .col-cust {
        width: 36%;
        text-align: left !important;
        border-right: 1px dashed #000;
    }

    .col-code {
        width: 22%;
        text-align: left !important;
        border-right: 1px dashed #000;
    }

    .col-name {
        width: 36%;
        text-align: left !important;
    }

    .center {
        text-align: center !important;
    }

    .no-data {
        text-align: center;
        margin-top: 60px;
        font-family: Arial, sans-serif;
        font-size: 12px;
    }

    /* PRINT */
    @media print {
        html, body {
            width: 210mm;
            min-height: 297mm;
            background: #fff !important;
        }

        .filter-bar,
        .print-bar,
        .navbar-custom {
            display: none !important;
        }

        .page {
            width: 100%;
            min-height: auto;
            border: none;
            margin: 0;
            padding: 0;
        }
    }
</style>

<div class="report-wrapper">
    <div class="filter-bar">
        <form id="filterForm" method="get" action="<?php echo h($selfFile); ?>" autocomplete="off" style="margin:0;">
            <input type="hidden" name="page" value="<?php echo h($page_param); ?>">
            <input type="hidden" name="plant" value="<?php echo h($plant_param); ?>">
            <input type="hidden" name="RUN" value="1">

            Starting Month:
            <input type="month" id="START_MONTH" name="START_MONTH" class="filter-month" value="<?php echo h(yyyymmdd_to_month_input($start_ymd)); ?>">

            Customer:
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_CODE" name="CUST_CODE" class="filter-cust" value="<?php echo h($cust_code); ?>" placeholder="Ketik kode / nama customer / %">
                <div id="custSuggest" class="autocomplete-list"></div>
            </div>

            <button type="submit">FILTER</button>
            <button type="button" onclick="setAllCustomer()">ALL</button>
            <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
            <button type="button" onclick="exportPdf()">EXPORT PDF</button>
        </form>
    </div>

    <div class="print-bar">
        <button type="button" onclick="window.print()">PRINT</button>
        <button type="button" onclick="closeReport()">CLOSE</button>
    </div>

    <?php if (!$is_filter) { ?>
        <div class="page">
            <table class="header">
                <tr>
                    <td class="company"><div class="company-title">PT. IMC TEKNO INDONESIA</div>PPIC Department</td>
                    <td class="title-area"><div class="report-title"> ITEM LIST (12 MONTH)</div><div class="start-month">Starting Month: -</div></td>
                    <td class="right-info">Page 0 of 0<br>Print Date: <?php echo h(fmt_print_datetime()); ?></td>
                </tr>
            </table>

            <div class="no-data">
                Data belum ditampilkan.<br><br>
                Pilih Starting Month dan Customer lalu klik <b>FILTER</b>.<br>
                Klik <b>ALL</b> untuk semua customer.
            </div>
        </div>
    <?php } ?>

    <?php for ($p = 0; $p < count($pages);$p++) { ?>
        <?php $pageRows = $pages[$p]; $pageNo =$p + 1; ?>

        <div class="page">
            <table class="header">
                <tr>
                    <td class="company"><div class="company-title">PT. IMC TEKNO INDONESIA</div>PPIC Department</td>
                    <td class="title-area"><div class="report-title"> ITEM LIST (12 MONTH)</div><div class="start-month">Starting Month: <?php echo h($start_ymd); ?></div></td>
                    <td class="right-info">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>Print Date: <?php echo h(fmt_print_datetime()); ?></td>
                </tr>
            </table>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-cust">Cust Name</th>
                        <th class="col-code">Part Code (Item Code)</th>
                        <th class="col-name">Part Name (Item Name)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < count($pageRows);$i++) { ?>
                        <?php $r = $pageRows[$i]; ?>

                        <?php if ($r["ROW_TYPE"] == "DETAIL") { ?>
                            <tr>
                                <td class="col-no"><?php echo h($r["NO"]); ?></td>
                                <td class="col-cust"><?php echo h($r["CUST_COMP"]); ?></td>
                                <td class="col-code"><?php echo h($r["PART_CODE"]); ?></td>
                                <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            </tr>
                        <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                            <tr>
                                <td colspan="4" class="center"><?php echo h($r["MESSAGE"]); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<script>
// Daftar customer yang dimuat langsung dari database
var masterCustomers = <?php echo json_encode($allCustomers); ?>;
var custRows = [];
var custIndex = -1;
var timer = null;

function enc(v) { return encodeURIComponent(v == null ? "" : v); }

function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;");
}

function closeReport() {
    window.location.href = "dashboard_qc.php?page=home&plant=<?php echo h($plant_param); ?>";
}

function validateInput() {
    var startMonth = document.getElementById("START_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (startMonth == "") {
        alert("Starting Month belum dipilih.");
        document.getElementById("START_MONTH").focus();
        return false;
    }

    if (custCode == "") {
        document.getElementById("CUST_CODE").value = "%";
    }
    return true;
}

function exportExcel() {
    if (!validateInput()) return;
    var startMonth = document.getElementById("START_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;

    window.location = "delivery_item_list_12month_export_excel.php?START_MONTH=" + enc(startMonth) + "&CUST_CODE=" + enc(custCode);
}

function exportPdf() {
    if (!validateInput()) return;
    var startMonth = document.getElementById("START_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;

    window.location = "<?php echo h($selfFile); ?>?page=<?php echo h($page_param); ?>&plant=<?php echo h($plant_param); ?>&RUN=1&EXPORT_PDF=1&START_MONTH=" + enc(startMonth) + "&CUST_CODE=" + enc(custCode);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
    document.getElementById("filterForm").submit();
}

function hideSuggest() {
    var box = document.getElementById("custSuggest");
    box.style.display = "none";
    box.innerHTML = "";
    custRows = [];
    custIndex = -1;
}

function setActiveCust(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        custIndex = -1;
        return;
    }

    if (index < 0) {
        index = items.length - 1;
    }

    if (index >= items.length) {
        index = 0;
    }

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }

    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) {
        return;
    }
    var r = custRows[index];
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    hideSuggest();
    document.getElementById("filterForm").submit();
}

function renderSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";
    custRows = rows || [];
    custIndex = -1;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function () { setActiveCust(idx); };
            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }
                chooseCust(idx);
            };
            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer(q) {
    q = String(q == null ? "" : q).trim();
    if (q == "" || q == "%") {
        hideSuggest();
        return;
    }

    var qLower = q.toLowerCase();
    var filtered = [];

    for (var i = 0; i < masterCustomers.length; i++) {
        var c = masterCustomers[i];
        var codeMatch = c.CUST_CODE && c.CUST_CODE.toLowerCase().indexOf(qLower) !== -1;
        var compMatch = c.CUST_COMP && c.CUST_COMP.toLowerCase().indexOf(qLower) !== -1;
        if (codeMatch || compMatch) {
            filtered.push(c);
        }
    }

    renderSuggest(filtered);
}

var custInput = document.getElementById("CUST_CODE");

custInput.onkeydown = function (e) {
    e = e || window.event;
    var key = e.keyCode || e.which;

    if (key == 40) { // Arrow Down
        setActiveCust(custIndex + 1);
        e.preventDefault();
        return false;
    }
    if (key == 38) { // Arrow Up
        setActiveCust(custIndex - 1);
        e.preventDefault();
        return false;
    }
    if (key == 13) { // Enter
        if (custRows.length > 0 && custIndex >= 0) {
            chooseCust(custIndex);
            e.preventDefault();
            return false;
        }
        return true;
    }
};

custInput.onkeyup = function (e) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    if (key == 40 || key == 38 || key == 13) {
        return;
    }

    clearTimeout(timer);
    var q = this.value;
    timer = setTimeout(function () {
        searchCustomer(q);
    }, 100);
};

custInput.onfocus = function () {
    if (this.value != "" && this.value != "%") {
        searchCustomer(this.value);
    }
};

custInput.onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 250);
};

<?php if ($auto_pdf &&$is_filter) { ?>
window.addEventListener("load", function() {
    document.title = "Delivered_Item_List_<?php echo h($start_ymd); ?>";
    setTimeout(function() {
        window.print();
    }, 300);
});
<?php } ?>
</script>