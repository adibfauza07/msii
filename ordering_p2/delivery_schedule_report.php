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

function get_param($name, $default = "") {
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    return $default;
}

function ymd_param($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{8}$/', $value)) return $value;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function date_input_value($value, $default) {
    $value = trim($value);
    if ($value == "") return $default;
    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }
    $ts = strtotime($value);
    if ($ts === false) return $default;
    return date("Y-m-d", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num_cell($value) {
    if ($value === null || $value === "") return ""; // Tanda "-" dihilangkan
    $n = (float)$value;
    if ($n == 0) return ""; // Tanda "-" dihilangkan jika nilainya 0
    return number_format($n, 0, ".", ",");
}

$is_filter = get_param("RUN", "") == "1";

$defaultStart = date("Y-m-01");
$defaultEnd   = date("Y-m-t");

$start_input = date_input_value(get_param("START_DATE", ""), $defaultStart);
$end_input   = date("Y-m-t", strtotime($start_input)); 
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = ymd_param($start_input);
$end_ymd   = ymd_param($end_input);

$rows = array();
$pages = array();
$totalPages = 0;
$rowsPerPage = 3; 

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }
    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.sp_PivotDeliverySchedule_ByCustomer_box ?, ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($start_ymd, $end_ymd, $cust_code));

    if ($stmt === false) {
        die("<pre>Query Delivery Schedule gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    // ------------------------------------------------------------------------
    // [NEW] LOGIKA MENGHITUNG TOTAL CUSTOMER & GRAND TOTAL
    // ------------------------------------------------------------------------
    $rawRows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rawRows[] = $r;
    }

    $custBoxTotals  = array_fill(1, 31, 0);
    $custBoxTotals['TOTAL'] = 0;
    
    $grandBoxTotals = array_fill(1, 31, 0);
    $grandBoxTotals['TOTAL'] = 0;

    $currentCust = null;
    $currentCustComp = null;

    foreach ($rawRows as $r) {
        // Jika berganti customer, masukkan baris TOTAL CUSTOMER sebelumnya
        if ($currentCust !== null && $currentCust !== $r['CUST_CODE']) {
            $rows[] = array(
                'IS_CUST_TOTAL' => true,
                'CUST_CODE'     => $currentCust,
                'CUST_COMP'     => $currentCustComp,
                'TOTAL_BOX'     => $custBoxTotals['TOTAL'],
                'DAILY'         => $custBoxTotals
            );
            // Reset kalkulasi untuk customer baru
            $custBoxTotals = array_fill(1, 31, 0);
            $custBoxTotals['TOTAL'] = 0;
        }

        $currentCust = $r['CUST_CODE'];
        $currentCustComp = $r['CUST_COMP'];

        // Akumulasi Data Box - PERBAIKAN PHP 5.4
        $custBoxTotals['TOTAL']  += (float)(isset($r['TOTAL_BOX']) ? $r['TOTAL_BOX'] : 0);
        $grandBoxTotals['TOTAL'] += (float)(isset($r['TOTAL_BOX']) ? $r['TOTAL_BOX'] : 0);

        for ($i = 1; $i <= 31; $i++) {
            $val = (float)(isset($r[$i . '_BOX']) ? $r[$i . '_BOX'] : 0);
            $custBoxTotals[$i]  += $val;
            $grandBoxTotals[$i] += $val;
        }

        // Masukkan data normal (Item Plan)
        $rows[] = $r;
    }

    // Setelah loop selesai, masukkan TOTAL CUSTOMER terakhir & GRAND TOTAL
    if ($currentCust !== null) {
        $rows[] = array(
            'IS_CUST_TOTAL' => true,
            'CUST_CODE'     => $currentCust,
            'CUST_COMP'     => $currentCustComp,
            'TOTAL_BOX'     => $custBoxTotals['TOTAL'],
            'DAILY'         => $custBoxTotals
        );
        $rows[] = array(
            'IS_GRAND_TOTAL' => true,
            'TOTAL_BOX'      => $grandBoxTotals['TOTAL'],
            'DAILY'          => $grandBoxTotals
        );
    }
    // ------------------------------------------------------------------------

    if (count($rows) == 0) {
        $rows[] = array(
            "ROW_EMPTY" => 1,
            "MESSAGE"   => "Data delivery schedule tidak ditemukan."
        );
    }

    $pages = array_chunk($rows, $rowsPerPage);
    $totalPages = count($pages);
    if ($totalPages <= 0) $totalPages = 1;
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Schedule</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Calibri", sans-serif;
            font-weight: bold;
            font-size: 10px;
            color: #000000;
        }

        .filter-bar {
            width: 98%; margin: 8px auto; background: #d4d0c8;
            border: 1px solid #666666; padding: 6px; box-sizing: border-box;
            font-family: "Calibri", sans-serif; font-size: 12px;
        }
        .filter-bar input { height: 24px; border: 1px solid #777777; font-size: 12px; padding: 2px 4px; box-sizing: border-box; font-weight: bold; font-family: "Calibri", sans-serif; }
        .filter-date { width: 130px; }
        .filter-cust { width: 160px; }
        .filter-bar button { height: 26px; font-size: 12px; cursor: pointer; margin-left: 4px; font-weight: bold; font-family: "Calibri", sans-serif; }

        .autocomplete-wrap { position: relative; display: inline-block; }
        .autocomplete-list { position: absolute; top: 24px; left: 0; width: 430px; max-height: 230px; overflow-y: auto; background: #ffffff; border: 1px solid #444444; z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25); }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #dddddd; cursor: pointer; line-height: 16px; font-family: "Calibri", sans-serif; font-size: 12px; font-weight: bold; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #ffffff; }

        .print-bar { width: 98%; margin: 8px auto; text-align: right; }
        .print-bar button { padding: 6px 14px; font-size: 11px; cursor: pointer; font-family: "Calibri", sans-serif; font-weight: bold; }

        .page {
            width: 98%; min-height: 198mm; margin: 10px auto;
            background: #ffffff; border: 2px solid #000000; padding: 6mm;
            box-sizing: border-box; page-break-after: always; overflow: hidden;
        }
        .page:last-child { page-break-after: auto; }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header td { border: none; vertical-align: top; }
        .company { width: 30%; font-family: "Calibri", sans-serif; font-size: 12px; line-height: 14px; font-weight: bold; }
        .company-title { font-size: 16px; font-weight: bold; }
        .title-area { width: 40%; text-align: center; font-family: "Calibri", sans-serif; font-weight: bold; }
        .report-title { font-size: 24px; font-weight: bold; margin-top: 8px; line-height: 24px; }
        .right-info { width: 30%; text-align: right; font-family: "Calibri", sans-serif; font-size: 12px; line-height: 17px; font-weight: bold; }
        .print-date { text-align: right; font-family: "Calibri", sans-serif; font-size: 12px; margin-bottom: 6px; font-weight: bold; }

        .item-title { font-weight: bold; font-size: 12px; margin-top: 8px; margin-bottom: 4px; line-height: 16px; }

        .schedule-table {
            width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 12px;
        }
        .schedule-table th, .schedule-table td {
            border: 1px solid #000000; padding: 2px 2px; height: 20px; line-height: 14px;
            box-sizing: border-box; vertical-align: middle; white-space: nowrap;
            overflow: hidden; font-size: 10px; text-align: center; font-weight: bold;
        }

        .row-label { width: 26px; font-weight: bold; text-align: left !important; }
        .total-col { width: 3.5%; font-weight: bold; background-color: #f0f0f0; }
        .day-col { width: 2.9%; }
        
        .dash { border-top: 1px dashed #777777; margin: 8px 0 12px 0; }
        .no-data { font-family: "Calibri", sans-serif; font-weight: bold; font-size: 15px; text-align: center; margin-top: 70px; line-height: 24px; }
        
        .negative-balance { color: red; font-weight: bold; }
        .col-total-val { font-weight: bold; background-color: #fcfcfc; }
        
        .summary-table td { font-weight: bold; }
        .summary-title { margin-top: 10px; font-size: 12px; font-weight: bold; }

        @media print {
            html, body { width: 297mm; min-height: 210mm; background: #ffffff; }
            .filter-bar, .print-bar { display: none; }
            .page { width: 285mm; min-height: 198mm; margin: 0 auto; border: none; padding: 4mm; overflow: hidden; }
            .schedule-table th, .schedule-table td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">
        Start:
        <input type="date" id="START_DATE" name="START_DATE" class="filter-date" value="<?php echo h($start_input); ?>">
        End:
        <input type="date" id="END_DATE" name="END_DATE" class="filter-date" value="<?php echo h($end_input); ?>" readonly style="background:#e9ecef; cursor:not-allowed;">
        Customer:
        <div class="autocomplete-wrap">
            <input type="text" id="CUST_CODE" name="CUST_CODE" class="filter-cust" value="<?php echo h($cust_code); ?>" placeholder="Ketik customer / %">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>
        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
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
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area"><div class="report-title">DELIVERY SCHEDULE</div></td>
                <td class="right-info">Page 0 of 0</td>
            </tr>
        </table>
        <div class="no-data">Data belum ditampilkan.<br><br>Isi Start, End, Customer lalu klik <b>FILTER</b>.</div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area">
                    <div class="report-title">DELIVERY SCHEDULE</div>
                    <div style="font-size:12px;margin-top:4px;">
                        <?php echo h($start_input); ?> s/d <?php echo h($end_input); ?>
                    </div>
                </td>
                <td class="right-info">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?></td>
            </tr>
        </table>

        <div class="print-date">Print Date: <?php echo h(fmt_print_datetime()); ?></div>

        <?php for ($ridx = 0; $ridx < count($pageRows); $ridx++) { ?>
            <?php $hrow = $pageRows[$ridx]; ?>

            <?php if (isset($hrow["ROW_EMPTY"])) { ?>
                <div class="no-data"><?php echo h($hrow["MESSAGE"]); ?></div>
                
            <?php } elseif (isset($hrow["IS_CUST_TOTAL"])) { ?>
                <!-- VIEW KHUSUS: TOTAL PER CUSTOMER -->
                <div class="summary-title" style="color: #2f70c9;">
                    TOTAL KEBUTUHAN BOX - <?php echo h($hrow["CUST_CODE"] . " (" . $hrow["CUST_COMP"] . ")"); ?>
                </div>
                <table class="schedule-table summary-table" style="border: 2px solid #555;">
                    <thead>
                        <tr>
                            <th class="row-label"></th>
                            <th class="total-col">Total</th>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <th class="day-col"><?php echo h(str_pad($i, 2, "0", STR_PAD_LEFT)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background-color: #f2f7ff;">
                            <td class="row-label">T.Box</td>
                            <td class="col-total-val"><?php echo h(fmt_num_cell($hrow["TOTAL_BOX"])); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <td><?php echo h(fmt_num_cell($hrow["DAILY"][$i])); ?></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                </table>
                <div class="dash"></div>

            <?php } elseif (isset($hrow["IS_GRAND_TOTAL"])) { ?>
                <!-- VIEW KHUSUS: GRAND TOTAL SEMUA CUSTOMER -->
                <div class="summary-title" style="color: #d11f1f; font-size: 13px;">
                    GRAND TOTAL KEBUTUHAN BOX (KESELURUHAN)
                </div>
                <table class="schedule-table summary-table" style="border: 2px solid #000;">
                    <thead>
                        <tr>
                            <th class="row-label"></th>
                            <th class="total-col">Total</th>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <th class="day-col"><?php echo h(str_pad($i, 2, "0", STR_PAD_LEFT)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background-color: #ffdada;">
                            <td class="row-label">G.Tot</td>
                            <td class="col-total-val"><?php echo h(fmt_num_cell($hrow["TOTAL_BOX"])); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <td><?php echo h(fmt_num_cell($hrow["DAILY"][$i])); ?></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                </table>
                <div class="dash"></div>

            <?php } else { ?>
                <!-- VIEW NORMAL: ITEM SCHEDULE -->
                <div class="item-title">
                    <?php echo h(safe_trim($hrow["CUST_CODE"])); ?> - <?php echo h(safe_trim($hrow["CUST_COMP"])); ?><br>
                    <?php echo h(safe_trim($hrow["ITEM_CODE"])); ?> - <?php echo h(safe_trim($hrow["ITEM_NAME"])); ?>
                </div>

                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th class="row-label"></th>
                            <th class="total-col">Total</th>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <th class="day-col"><?php echo h(str_pad($i, 2, "0", STR_PAD_LEFT)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="row-label">Pla</td>
                            <td class="col-total-val"><?php echo h(fmt_num_cell(isset($hrow["TOTAL_SCH"]) ? $hrow["TOTAL_SCH"] : 0)); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <td><?php echo h(fmt_num_cell(isset($hrow[$i . "_SCH"]) ? $hrow[$i . "_SCH"] : 0)); ?></td>
                            <?php } ?>
                        </tr>
                        <tr>
                            <td class="row-label">Act</td>
                            <td class="col-total-val"><?php echo h(fmt_num_cell(isset($hrow["TOTAL_DEL"]) ? $hrow["TOTAL_DEL"] : 0)); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <td><?php echo h(fmt_num_cell(isset($hrow[$i . "_DEL"]) ? $hrow[$i . "_DEL"] : 0)); ?></td>
                            <?php } ?>
                        </tr>
                        <tr>
                            <td class="row-label">Bal</td>
                            <?php
                                $totBal = isset($hrow["TOTAL_BAL"]) ? $hrow["TOTAL_BAL"] : 0;
                                $totBalClass = ((float)$totBal < 0) ? "negative-balance col-total-val" : "col-total-val";
                            ?>
                            <td class="<?php echo $totBalClass; ?>"><?php echo h(fmt_num_cell($totBal)); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <?php
                                    $val = isset($hrow[$i . "_BAL"]) ? $hrow[$i . "_BAL"] : 0;
                                    $class = ((float)$val < 0) ? "negative-balance" : "";
                                ?>
                                <td class="<?php echo $class; ?>"><?php echo h(fmt_num_cell($val)); ?></td>
                            <?php } ?>
                        </tr>
                        <tr>
                            <td class="row-label">Box</td>
                            <td class="col-total-val"><?php echo h(fmt_num_cell(isset($hrow["TOTAL_BOX"]) ? $hrow["TOTAL_BOX"] : 0)); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <td><?php echo h(fmt_num_cell(isset($hrow[$i . "_BOX"]) ? $hrow[$i . "_BOX"] : 0)); ?></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                </table>
                <div class="dash"></div>
            <?php } ?>
        <?php } ?>
    </div>
<?php } ?>

<script>
var custRows = []; var custIndex = -1; var timer = null;
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function htmlEncode(value) { return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
function closeReport() {
    try { if (window.parent && window.parent !== window) { window.location.href = "dashboard_home.php"; return; } } catch (e) {}
    window.open("", "_self"); window.close();
    setTimeout(function () { if (!window.closed) { window.location.href = "dashboard_home.php"; } }, 200);
}
function exportExcel() {
    var startDate = document.getElementById("START_DATE").value, endDate = document.getElementById("END_DATE").value, custCode = document.getElementById("CUST_CODE").value;
    if (startDate == "") { alert("Start Date belum diisi."); document.getElementById("START_DATE").focus(); return; }
    if (endDate == "") { alert("End Date belum diisi."); document.getElementById("END_DATE").focus(); return; }
    if (custCode == "") { alert("Customer belum diisi."); document.getElementById("CUST_CODE").focus(); return; }
    window.location = "delivery_schedule_export_excel.php?START_DATE=" + enc(startDate) + "&END_DATE=" + enc(endDate) + "&CUST_CODE=" + enc(custCode);
}
function setAllCustomer() { document.getElementById("CUST_CODE").value = "%"; document.forms[0].submit(); }
function hideSuggest() { var box = document.getElementById("custSuggest"); box.style.display = "none"; box.innerHTML = ""; custRows = []; custIndex = -1; }
function setActiveCust(index) {
    var box = document.getElementById("custSuggest"), items = box.getElementsByClassName("autocomplete-item");
    if (!items || items.length == 0) { custIndex = -1; return; }
    if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
    for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
    items[index].className = "autocomplete-item active"; custIndex = index;
}
function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;
    document.getElementById("CUST_CODE").value = custRows[index].CUST_CODE; hideSuggest();
}
function renderSuggest(rows) {
    var box = document.getElementById("custSuggest"); box.innerHTML = ""; custRows = rows || []; custIndex = -1;
    if (!rows || rows.length == 0) { box.style.display = "none"; return; }
    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div"); div.className = "autocomplete-item";
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function () { setActiveCust(idx); };
            div.onmousedown = function (e) { if (e && e.preventDefault) e.preventDefault(); chooseCust(idx); };
            box.appendChild(div);
        })(rows[i], i);
    }
    box.style.display = "block"; setActiveCust(0);
}
function searchCustomer(q) {
    if (q == "" || q == "%") { hideSuggest(); return; }
    var xhr = new XMLHttpRequest(); xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            try { var result = JSON.parse(xhr.responseText); renderSuggest(result.rows ? result.rows : result); } catch (e) {}
        }
    };
    xhr.send("q=" + enc(q));
}
document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event; var key = e.keyCode || e.which;
    if (key == 40) { setActiveCust(custIndex + 1); return; }
    if (key == 38) { setActiveCust(custIndex - 1); return; }
    if (key == 13) { if (custRows.length > 0) { if (custIndex < 0) custIndex = 0; chooseCust(custIndex); return false; } return true; }
    clearTimeout(timer); var q = this.value; timer = setTimeout(function () { searchCustomer(q); }, 250);
};
document.getElementById("CUST_CODE").onblur = function () { setTimeout(function () { hideSuggest(); }, 250); };
document.getElementById("START_DATE").addEventListener("change", function() {
    var startDate = this.value;
    if (startDate) {
        var dateObj = new Date(startDate);
        var lastDay = new Date(dateObj.getFullYear(), dateObj.getMonth() + 1, 0);
        var y = lastDay.getFullYear(), m = String(lastDay.getMonth() + 1).padStart(2, '0'), d = String(lastDay.getDate()).padStart(2, '0');
        document.getElementById("END_DATE").value = y + '-' + m + '-' + d;
    }
});
</script>

</body>
</html>