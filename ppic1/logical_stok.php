<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
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

function month_end_from_input($value) {
    $value = trim($value);
    if ($value == "") return date("Y-m-t");

    if (preg_match('/^\d{8}$/', $value)) {
        $value = substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }

    $ts = strtotime($value);
    if ($ts === false) return date("Y-m-t");

    return date("Y-m-t", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num_cell($value) {
    if ($value === null || $value === "") return "-";

    $n = (float)$value;
    if ($n == 0) return "-";

    if ($n < 0) {
        return "(" . number_format(abs($n), 0, ".", ",") . ")";
    }

    return number_format($n, 0, ".", ",");
}

function sum_hrow_days($hrow, $suffix) {
    $total = 0;
    for ($i = 1; $i <= 31; $i++) {
        $col = $i . "_" . $suffix;
        $total += isset($hrow[$col]) ? (float)$hrow[$col] : 0;
    }
    return $total;
}

function get_prod_row($prodMap, $custCode, $itemCode, $rowName) {
    $key = safe_trim($custCode) . "|" . safe_trim($itemCode);
    if (isset($prodMap[$key]) && isset($prodMap[$key][$rowName])) {
        return $prodMap[$key][$rowName];
    }
    return null;
}

function get_forecast_qty($forecastMap, $custCode, $itemCode, $idx) {
    $key = safe_trim($custCode) . "|" . safe_trim($itemCode);
    if (isset($forecastMap[$key]) && isset($forecastMap[$key][$idx])) {
        return $forecastMap[$key][$idx];
    }

    $key2 = safe_trim($itemCode);
    if (isset($forecastMap[$key2]) && isset($forecastMap[$key2][$idx])) {
        return $forecastMap[$key2][$idx];
    }

    return 0;
}

function print_forecast_tr($label, $qty) {
    echo '<tr class="forecast-row">';
    echo '<td class="row-label">' . h($label) . '</td>';
    echo '<td>-</td>';
    echo '<td>' . h(fmt_num_cell($qty)) . '</td>';

    for ($i = 1; $i <= 31; $i++) {
        echo '<td>-</td>';
    }

    echo '</tr>';
}

function empty_day_row() {
    $row = array("G_TOTAL" => 0);
    for ($i = 1; $i <= 31; $i++) {
        $row["D" . $i] = 0;
    }
    return $row;
}

function print_prod_tr($label, $dataRow, $cssClass) {
    echo '<tr class="' . h($cssClass) . '">';
    echo '<td class="row-label">' . h($label) . '</td>';

    echo '<td>-</td>';

    $grand = ($dataRow && isset($dataRow["G_TOTAL"])) ? $dataRow["G_TOTAL"] : 0;
    $gclass = ((float)$grand < 0) ? "negative-balance" : "";
    echo '<td class="' . h($gclass) . '">' . h(fmt_num_cell($grand)) . '</td>';

    for ($i = 1; $i <= 31; $i++) {
        $col = "D" . $i;
        $val = ($dataRow && isset($dataRow[$col])) ? $dataRow[$col] : 0;
        $class = ((float)$val < 0) ? "negative-balance" : "";
        echo '<td class="' . h($class) . '">' . h(fmt_num_cell($val)) . '</td>';
    }

    echo '</tr>';
}

function fmt_percent_cell($value) {
    if ($value === null || $value === "") return "-";

    $n = (float)$value;
    if ($n == 0) return "-";

    return number_format($n, 1, ".", ",") . "%";
}

function print_percent_tr($label, $percentRow) {
    echo '<tr class="stock-row">';
    echo '<td class="row-label">' . h($label) . '</td>';
    echo '<td>-</td>';
    echo '<td>-</td>';

    for ($i = 1; $i <= 31; $i++) {
        $col = "D" . $i;
        $val = isset($percentRow[$col]) ? $percentRow[$col] : 0;
        echo '<td>' . h(fmt_percent_cell($val)) . '</td>';
    }

    echo '</tr>';
}

function print_prod_tr_shortage($label, $dataRow, $cssClass, $shortageDay) {
    echo '<tr class="' . h($cssClass) . '">';
    echo '<td class="row-label">' . h($label) . '</td>';
    echo '<td style="
    background:#ffff00;
    color:#000000;
    font-weight:bold;
    text-align:center;
">' . h($shortageDay) . '</td>';

    $grand = ($dataRow && isset($dataRow["G_TOTAL"])) ? $dataRow["G_TOTAL"] : 0;
    $gclass = ((float)$grand < 0) ? "negative-balance" : "";
    echo '<td class="' . h($gclass) . '">' . h(fmt_num_cell($grand)) . '</td>';

    for ($i = 1; $i <= 31; $i++) {
        $col = "D" . $i;
        $val = ($dataRow && isset($dataRow[$col])) ? $dataRow[$col] : 0;
        $class = ((float)$val < 0) ? "negative-balance" : "";
        echo '<td class="' . h($class) . '">' . h(fmt_num_cell($val)) . '</td>';
    }

    echo '</tr>';
}

function print_percent_tr_shortage($label, $percentRow, $shortageDay) {
    echo '<tr class="stock-row">';
    echo '<td class="row-label">' . h($label) . '</td>';
    echo '<td style="
    background:#ffff00;
    color:#000000;
    font-weight:bold;
    text-align:center;
">' . h($shortageDay) . '</td>';
    echo '<td>-</td>';

    for ($i = 1; $i <= 31; $i++) {
        $col = "D" . $i;
        $val = isset($percentRow[$col]) ? $percentRow[$col] : 0;
        echo '<td>' . h(fmt_percent_cell($val)) . '</td>';
    }

    echo '</tr>';
}

function first_minus_day($dataRow) {
    if (!$dataRow) return "-";

    for ($i = 1; $i <= 31; $i++) {
        $col = "D" . $i;
        $val = isset($dataRow[$col]) ? (float)$dataRow[$col] : 0;

        if ($val < 0) {
            return str_pad($i, 2, "0", STR_PAD_LEFT);
        }
    }

    return "-";
}

function first_below_percent_day($stockRow, $delRow, $percent = 25) {
    if (!$stockRow) return '-';

    $delPlanTotal = sum_hrow_days($delRow, "SCH");

    if ($delPlanTotal <= 0) return '-';

    for ($i = 1; $i <= 31; $i++) {
        $stockCol = 'D' . $i;

        $stock = isset($stockRow[$stockCol]) ? (float)$stockRow[$stockCol] : 0;

        if ($stock <= 0) {
            continue;
        }

        $ratio = ($stock / $delPlanTotal) * 100;

        if ($ratio < $percent) {
            return str_pad($i, 2, '0', STR_PAD_LEFT);
        }
    }

    return '-';
}

function first_del_balance_minus_day($hrow) {
    if (!$hrow) return "-";

    for ($i = 1; $i <= 31; $i++) {
        $col = $i . "_BAL";
        $val = isset($hrow[$col]) ? (float)$hrow[$col] : 0;

        if ($val < 0) {
            return str_pad($i, 2, "0", STR_PAD_LEFT);
        }
    }

    return "-";
}

$is_filter = get_param("RUN", "") == "1";

$defaultStart = date("Y-m-01");
$defaultEnd   = date("Y-m-d");

$start_input = date_input_value(get_param("START_DATE", ""), $defaultStart);
/* END_DATE otomatis akhir bulan dari START_DATE */
$end_input   = month_end_from_input($start_input);
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = ymd_param($start_input);
$end_ymd   = ymd_param($end_input);

$rows = array();
$pages = array();
$prodMap = array();
$stockAwalMap = array();
$forecastMap = array();

$totalPages = 0;
$rowsPerPage = 2;

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.sp_PivotDeliverySchedule_ByCustomer ?, ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($start_ymd, $end_ymd, $cust_code));

    if ($stmt === false) {
        die("<pre>Query Delivery Schedule gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    $periode = substr($start_ymd, 0, 6);

    /* Forecast 3 bulan mulai dari bulan START_DATE */
    $forecastMonth1 = date("Y-m-01", strtotime($start_input));
    $forecastMonth2 = date("Y-m-01", strtotime($forecastMonth1 . " +1 month"));
    $forecastMonth3 = date("Y-m-01", strtotime($forecastMonth1 . " +2 month"));
    $forecastMonth4 = date("Y-m-01", strtotime($forecastMonth1 . " +3 month"));

    $forecastLabel1 = date("M-y", strtotime($forecastMonth1));
    $forecastLabel2 = date("M-y", strtotime($forecastMonth2));
    $forecastLabel3 = date("M-y", strtotime($forecastMonth3));


    $sqlProd = "
    SELECT
        X.CUST_CODE,
        X.ITEM_CODE,
        X.ROW_NAME,
        SUM(X.G_TOTAL) AS G_TOTAL,
        SUM(X.D1) AS D1, SUM(X.D2) AS D2, SUM(X.D3) AS D3, SUM(X.D4) AS D4,
        SUM(X.D5) AS D5, SUM(X.D6) AS D6, SUM(X.D7) AS D7, SUM(X.D8) AS D8,
        SUM(X.D9) AS D9, SUM(X.D10) AS D10, SUM(X.D11) AS D11, SUM(X.D12) AS D12,
        SUM(X.D13) AS D13, SUM(X.D14) AS D14, SUM(X.D15) AS D15, SUM(X.D16) AS D16,
        SUM(X.D17) AS D17, SUM(X.D18) AS D18, SUM(X.D19) AS D19, SUM(X.D20) AS D20,
        SUM(X.D21) AS D21, SUM(X.D22) AS D22, SUM(X.D23) AS D23, SUM(X.D24) AS D24,
        SUM(X.D25) AS D25, SUM(X.D26) AS D26, SUM(X.D27) AS D27, SUM(X.D28) AS D28,
        SUM(X.D29) AS D29, SUM(X.D30) AS D30, SUM(X.D31) AS D31
    FROM (
        SELECT
            C.CUST_CODE,
            P.ITEM_CODE,
            LTRIM(RTRIM(D.DESC_PROD)) AS ROW_NAME,
            SUM(ISNULL(D.G_TOTAL, 0)) AS G_TOTAL,
            SUM(ISNULL(D.D1, 0)) AS D1, SUM(ISNULL(D.D2, 0)) AS D2,
            SUM(ISNULL(D.D3, 0)) AS D3, SUM(ISNULL(D.D4, 0)) AS D4,
            SUM(ISNULL(D.D5, 0)) AS D5, SUM(ISNULL(D.D6, 0)) AS D6,
            SUM(ISNULL(D.D7, 0)) AS D7, SUM(ISNULL(D.D8, 0)) AS D8,
            SUM(ISNULL(D.D9, 0)) AS D9, SUM(ISNULL(D.D10, 0)) AS D10,
            SUM(ISNULL(D.D11, 0)) AS D11, SUM(ISNULL(D.D12, 0)) AS D12,
            SUM(ISNULL(D.D13, 0)) AS D13, SUM(ISNULL(D.D14, 0)) AS D14,
            SUM(ISNULL(D.D15, 0)) AS D15, SUM(ISNULL(D.D16, 0)) AS D16,
            SUM(ISNULL(D.D17, 0)) AS D17, SUM(ISNULL(D.D18, 0)) AS D18,
            SUM(ISNULL(D.D19, 0)) AS D19, SUM(ISNULL(D.D20, 0)) AS D20,
            SUM(ISNULL(D.D21, 0)) AS D21, SUM(ISNULL(D.D22, 0)) AS D22,
            SUM(ISNULL(D.D23, 0)) AS D23, SUM(ISNULL(D.D24, 0)) AS D24,
            SUM(ISNULL(D.D25, 0)) AS D25, SUM(ISNULL(D.D26, 0)) AS D26,
            SUM(ISNULL(D.D27, 0)) AS D27, SUM(ISNULL(D.D28, 0)) AS D28,
            SUM(ISNULL(D.D29, 0)) AS D29, SUM(ISNULL(D.D30, 0)) AS D30,
            SUM(ISNULL(D.D31, 0)) AS D31
        FROM dbo.RPT_PPIC P
        INNER JOIN dbo.RPT_PPIC_DTL D ON P.ID_NO = D.ID_NO
        
        /* 🔥 SOLUSI: Meng-CAST C.CUST_ID ke VARCHAR agar tidak crash saat membaca data P.CUST yang kotor 🔥 */
        INNER JOIN dbo.CUST C ON P.CUST = CAST(C.CUST_ID AS VARCHAR(50))
        
        WHERE P.PERIODE = ?
          AND (? = '%' OR C.CUST_CODE = ?)
          AND LTRIM(RTRIM(D.DESC_PROD)) IN (
              'Prod Plan R0',
              'NG Rework',
              'Est Stock Plan'
          )
        GROUP BY
            C.CUST_CODE,
            P.ITEM_CODE,
            LTRIM(RTRIM(D.DESC_PROD))

        UNION ALL

        SELECT
            C.CUST_CODE,
            I.ITEM_CODE,
            V.ROW_NAME,
            SUM(ISNULL(V.QTY, 0)) AS G_TOTAL,
            SUM(CASE WHEN DAY(V.PD_DATE) = 1  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D1,
            SUM(CASE WHEN DAY(V.PD_DATE) = 2  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D2,
            SUM(CASE WHEN DAY(V.PD_DATE) = 3  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D3,
            SUM(CASE WHEN DAY(V.PD_DATE) = 4  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D4,
            SUM(CASE WHEN DAY(V.PD_DATE) = 5  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D5,
            SUM(CASE WHEN DAY(V.PD_DATE) = 6  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D6,
            SUM(CASE WHEN DAY(V.PD_DATE) = 7  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D7,
            SUM(CASE WHEN DAY(V.PD_DATE) = 8  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D8,
            SUM(CASE WHEN DAY(V.PD_DATE) = 9  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D9,
            SUM(CASE WHEN DAY(V.PD_DATE) = 10 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D10,
            SUM(CASE WHEN DAY(V.PD_DATE) = 11 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D11,
            SUM(CASE WHEN DAY(V.PD_DATE) = 12 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D12,
            SUM(CASE WHEN DAY(V.PD_DATE) = 13 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D13,
            SUM(CASE WHEN DAY(V.PD_DATE) = 14 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D14,
            SUM(CASE WHEN DAY(V.PD_DATE) = 15 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D15,
            SUM(CASE WHEN DAY(V.PD_DATE) = 16 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D16,
            SUM(CASE WHEN DAY(V.PD_DATE) = 17 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D17,
            SUM(CASE WHEN DAY(V.PD_DATE) = 18 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D18,
            SUM(CASE WHEN DAY(V.PD_DATE) = 19 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D19,
            SUM(CASE WHEN DAY(V.PD_DATE) = 20 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D20,
            SUM(CASE WHEN DAY(V.PD_DATE) = 21 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D21,
            SUM(CASE WHEN DAY(V.PD_DATE) = 22 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D22,
            SUM(CASE WHEN DAY(V.PD_DATE) = 23 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D23,
            SUM(CASE WHEN DAY(V.PD_DATE) = 24 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D24,
            SUM(CASE WHEN DAY(V.PD_DATE) = 25 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D25,
            SUM(CASE WHEN DAY(V.PD_DATE) = 26 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D26,
            SUM(CASE WHEN DAY(V.PD_DATE) = 27 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D27,
            SUM(CASE WHEN DAY(V.PD_DATE) = 28 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D28,
            SUM(CASE WHEN DAY(V.PD_DATE) = 29 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D29,
            SUM(CASE WHEN DAY(V.PD_DATE) = 30 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D30,
            SUM(CASE WHEN DAY(V.PD_DATE) = 31 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D31
        FROM (
            SELECT W.ITEM_ID, P.PD_DATE, 'Prod OK' AS ROW_NAME, ISNULL(P.PD_OK,0) AS QTY
            FROM dbo.PRODUCTION P
            INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
            WHERE P.PD_DATE >= ?
              AND P.PD_DATE < DATEADD(DAY, 1, ?)

            UNION ALL

            SELECT W.ITEM_ID, P.PD_DATE, 'Prod HOLD' AS ROW_NAME, ISNULL(P.PD_HO,0) AS QTY
            FROM dbo.PRODUCTION P
            INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
            WHERE P.PD_DATE >= ?
              AND P.PD_DATE < DATEADD(DAY, 1, ?)

            UNION ALL

            SELECT W.ITEM_ID, P.PD_DATE, 'Prod NG' AS ROW_NAME, ISNULL(P.PD_NG,0) AS QTY
            FROM dbo.PRODUCTION P
            INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
            WHERE P.PD_DATE >= ?
              AND P.PD_DATE < DATEADD(DAY, 1, ?)
        ) V
        INNER JOIN dbo.ITEMS I ON V.ITEM_ID = I.ITEM_ID
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW IC ON I.ITEM_ID = IC.ITEM_ID
        INNER JOIN dbo.CUST C ON IC.CUST_ID = C.CUST_ID
        WHERE (? = '%' OR C.CUST_CODE = ?)
        GROUP BY C.CUST_CODE, I.ITEM_CODE, V.ROW_NAME

        UNION ALL

        SELECT
            C.CUST_CODE,
            I.ITEM_CODE,
            'Prod OK1' AS ROW_NAME,
            SUM(ISNULL(T.QTY, 0)) AS G_TOTAL,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 1  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D1,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 2  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D2,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 3  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D3,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 4  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D4,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 5  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D5,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 6  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D6,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 7  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D7,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 8  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D8,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 9  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D9,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 10 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D10,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 11 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D11,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 12 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D12,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 13 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D13,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 14 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D14,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 15 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D15,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 16 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D16,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 17 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D17,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 18 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D18,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 19 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D19,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 20 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D20,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 21 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D21,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 22 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D22,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 23 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D23,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 24 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D24,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 25 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D25,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 26 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D26,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 27 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D27,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 28 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D28,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 29 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D29,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 30 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D30,
            SUM(CASE WHEN DAY(T.TRAN_DATE) = 31 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D31
        FROM (
            SELECT
                IT.ITEM_ID,
                TR.TRAN_DATE,
                SUM(IT.IT_QTY) AS QTY
            FROM dbo.INV_TRAN IT
            INNER JOIN dbo.TRANS TR ON IT.TRAN_ID = TR.TRAN_ID
            WHERE TR.TRAN_DATE >= ?
              AND TR.TRAN_DATE < DATEADD(DAY, 1, ?)
              AND TR.TRTY_CODE = '12'
            GROUP BY IT.ITEM_ID, TR.TRAN_DATE
        ) T
        INNER JOIN dbo.ITEMS I ON T.ITEM_ID = I.ITEM_ID
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW IC ON I.ITEM_ID = IC.ITEM_ID
        INNER JOIN dbo.CUST C ON IC.CUST_ID = C.CUST_ID
        WHERE (? = '%' OR C.CUST_CODE = ?)
        GROUP BY C.CUST_CODE, I.ITEM_CODE
    ) X
    GROUP BY X.CUST_CODE, X.ITEM_CODE, X.ROW_NAME
";

    $stmtProd = sqlsrv_query($conn, $sqlProd, array(
        $periode,
        $cust_code,
        $cust_code,

        $start_ymd,
        $end_ymd,
        $start_ymd,
        $end_ymd,
        $start_ymd,
        $end_ymd,
        $cust_code,
        $cust_code,

        $start_ymd,
        $end_ymd,
        $cust_code,
        $cust_code
    ));

    if ($stmtProd === false) {
        die("<pre>Query Production / Stock gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    // [PERBAIKAN] Blok While di bawah ini mengkalkulasi ulang G_TOTAL secara dinamis dari D1 s/d D31
    while ($pr = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) {
        $key = safe_trim($pr["CUST_CODE"]) . "|" . safe_trim($pr["ITEM_CODE"]);
        $rowName = safe_trim($pr["ROW_NAME"]);

        // Hitung ulang G_TOTAL murni berdasarkan kolom harian
        if (in_array($rowName, array("Prod Plan R0", "Prod OK1", "Prod OK", "Prod HOLD", "Prod NG", "NG Rework"))) {
            $sumTotal = 0;
            for ($i = 1; $i <= 31; $i++) {
                $sumTotal += (float)$pr["D" . $i];
            }
            $pr["G_TOTAL"] = $sumTotal;
        }

        if (!isset($prodMap[$key])) {
            $prodMap[$key] = array();
        }

        $prodMap[$key][$rowName] = $pr;
    }

    /* FORECAST 3 BULAN */
    $sqlForecast = "
        SELECT
            C.CUST_CODE,
            I.ITEM_CODE,
            SUM(CASE WHEN F.FORE_MONTH >= ? AND F.FORE_MONTH < ? THEN ISNULL(F.FORE_QTY, 0) ELSE 0 END) AS F1,
            SUM(CASE WHEN F.FORE_MONTH >= ? AND F.FORE_MONTH < ? THEN ISNULL(F.FORE_QTY, 0) ELSE 0 END) AS F2,
            SUM(CASE WHEN F.FORE_MONTH >= ? AND F.FORE_MONTH < ? THEN ISNULL(F.FORE_QTY, 0) ELSE 0 END) AS F3
        FROM dbo.FORECAST F
        INNER JOIN dbo.PRICE P
            ON F.PRICE_ID = P.PRICE_ID
        INNER JOIN dbo.ITEMS I
            ON P.PART_ID = I.ITEM_ID
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW IC
            ON I.ITEM_ID = IC.ITEM_ID
        INNER JOIN dbo.CUST C
            ON IC.CUST_ID = C.CUST_ID
        WHERE F.FORE_MONTH >= ?
          AND F.FORE_MONTH < ?
          AND (? = '%' OR C.CUST_CODE = ?)
        GROUP BY
            C.CUST_CODE,
            I.ITEM_CODE
    ";

    $stmtForecast = sqlsrv_query($conn, $sqlForecast, array(
        $forecastMonth1, $forecastMonth2,
        $forecastMonth2, $forecastMonth3,
        $forecastMonth3, $forecastMonth4,
        $forecastMonth1, $forecastMonth4,
        $cust_code, $cust_code
    ));

    if ($stmtForecast === false) {
        die("<pre>Query Forecast gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($fr = sqlsrv_fetch_array($stmtForecast, SQLSRV_FETCH_ASSOC)) {
        $fkey = safe_trim($fr["CUST_CODE"]) . "|" . safe_trim($fr["ITEM_CODE"]);
        $forecastMap[$fkey] = array(
            1 => isset($fr["F1"]) ? (float)$fr["F1"] : 0,
            2 => isset($fr["F2"]) ? (float)$fr["F2"] : 0,
            3 => isset($fr["F3"]) ? (float)$fr["F3"] : 0
        );
    }

    $sqlStockAwal = "
        SELECT
            C.CUST_CODE,
            I.ITEM_CODE,
            SUM(ISNULL(TG.TAG_QTY, 0)) AS BAL
        FROM dbo.TAGS TG
        INNER JOIN dbo.SOP S ON TG.SOP_ID = S.SOP_ID
        INNER JOIN dbo.ITEMS I ON TG.ITEM_ID = I.ITEM_ID
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW IC ON I.ITEM_ID = IC.ITEM_ID
        INNER JOIN dbo.CUST C ON IC.CUST_ID = C.CUST_ID
        WHERE S.SOP_SDATE = ?
          AND (? = '%' OR C.CUST_CODE = ?)
        GROUP BY
            C.CUST_CODE,
            I.ITEM_CODE
    ";

    $stmtStockAwal = sqlsrv_query($conn, $sqlStockAwal, array(
        $start_ymd,
        $cust_code,
        $cust_code
    ));

    if ($stmtStockAwal === false) {
        die("<pre>Query Stock Awal gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($sr = sqlsrv_fetch_array($stmtStockAwal, SQLSRV_FETCH_ASSOC)) {
        $key = safe_trim($sr["CUST_CODE"]) . "|" . safe_trim($sr["ITEM_CODE"]);
        $stockAwalMap[$key] = isset($sr["BAL"]) ? (float)$sr["BAL"] : 0;
    }

    foreach ($rows as $hrow) {
        if (isset($hrow["ROW_EMPTY"])) continue;

        $key = safe_trim($hrow["CUST_CODE"]) . "|" . safe_trim($hrow["ITEM_CODE"]);

        if (!isset($prodMap[$key])) {
            $prodMap[$key] = array();
        }

        if (!isset($prodMap[$key]["Prod HOLD"])) {
            $prodMap[$key]["Prod HOLD"] = empty_day_row();
        }

        $stockPlan   = empty_day_row();
        $stockActual = empty_day_row();
        $percentStock = empty_day_row();

        $runningPlan   = isset($stockAwalMap[$key]) ? (float)$stockAwalMap[$key] : 0;
        $runningActual = isset($stockAwalMap[$key]) ? (float)$stockAwalMap[$key] : 0;

        for ($i = 1; $i <= 31; $i++) {
            $dcol = "D" . $i;

            $delPlanCol   = $i . "_SCH";
            $delActualCol = $i . "_DEL";

            $prodPlan = isset($prodMap[$key]["Prod Plan R0"][$dcol])
                ? (float)$prodMap[$key]["Prod Plan R0"][$dcol]
                : 0;

            $prodOk = isset($prodMap[$key]["Prod OK"][$dcol])
                ? (float)$prodMap[$key]["Prod OK"][$dcol]
                : 0;

            $prodHold = isset($prodMap[$key]["Prod HOLD"][$dcol])
                ? (float)$prodMap[$key]["Prod HOLD"][$dcol]
                : 0;

            $prodOk1 = isset($prodMap[$key]["Prod OK1"][$dcol])
                ? (float)$prodMap[$key]["Prod OK1"][$dcol]
                : 0;
            $delPlan = isset($hrow[$delPlanCol])
                ? (float)$hrow[$delPlanCol]
                : 0;

            $runningPlan = $runningPlan + $prodPlan - $delPlan;
            $stockPlan[$dcol] = $runningPlan;
            $delPlanTotal = sum_hrow_days($hrow, "SCH");

            if ($delPlanTotal > 0 && $runningPlan > 0) {
                $percentStock[$dcol] = ($runningPlan / $delPlanTotal) * 100;
            } else {
                $percentStock[$dcol] = 0;
            }

            $delActual = isset($hrow[$delActualCol])
                ? (float)$hrow[$delActualCol]
                : 0;

            $prodInActual = $prodOk + $prodHold;

            if ($prodInActual == 0) {
                $prodInActual = $prodOk1;
            }

            $runningActual = $runningActual + $prodInActual - $delActual;
            $stockActual[$dcol] = $runningActual;
        }

        $stockPlan["G_TOTAL"]   = 0;
        $stockActual["G_TOTAL"] = 0;

        $prodMap[$key]["Est Stock Plan"]   = $stockPlan;
        $prodMap[$key]["Est Stock Actual"] = $stockActual;
        $prodMap[$key]["Percent Stock"] = $percentStock;
    }

    if (count($rows) == 0) {
        $rows[] = array(
            "ROW_EMPTY" => 1,
            "MESSAGE" => "Data delivery schedule tidak ditemukan."
        );
    }

    $pages = array_chunk($rows, $rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Logical Stock</title>

    <style>
        @page { size: A3 landscape; margin: 5mm; }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 9px;
            color: #000000;
        }

        .filter-bar {
            width: 285mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input {
            height: 24px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-date { width: 130px; }
        .filter-cust { width: 160px; }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 24px;
            left: 0;
            width: 430px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #444444;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #ffffff;
        }

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .page {
            width: 400mm;
            min-height: 198mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 6mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child { page-break-after: auto; }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 15px;
            font-weight: normal;
        }

        .title-area {
            width: 40%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 22px;
            font-weight: normal;
            margin-top: 8px;
            line-height: 24px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 17px;
        }

        .print-date {
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .item-title {
            font-weight: bold;
            font-size: 11px;
            margin-top: 8px;
            margin-bottom: 4px;
            line-height: 15px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }

        .schedule-table th,
        .schedule-table td {
            border: 1px solid #000000;
            padding: 2px 2px;
            height: 18px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
            text-align: center;
        }

        .row-label {
            width: 72px;
            font-weight: bold;
            text-align: left !important;
        }

        .day-col { width: 3.1%; }
        .shortage-col {
            width: 55px;
            background: #ffff00;
            color: #000000;
            font-weight: bold;
        }

        .negative-balance {
            color: red !important;
            font-weight: bold;
        }

        .forecast-row td { background: #e2f0d9; font-weight:bold; }
        .prod-row td { background: #f7f7f7; }
        .stock-row td { background: #00b0f0; font-weight: bold; }
        .ng-row td { background: #ffffff; }

        .dash {
            border-top: 1px dashed #777777;
            margin: 8px 0 12px 0;
        }

        .no-data {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            margin-top: 70px;
            line-height: 24px;
        }

        @media print {
            html, body {
                width: 420mm;
                min-height: 297mm;
                background: #ffffff;
            }

            .filter-bar,
            .print-bar {
                display: none;
            }

            .page {
                width: 400mm;
                min-height: 285mm;
                margin: 0 auto;
                border: none;
                padding: 4mm;
                overflow: hidden;
            }
        }
        
        .forecast-title {
            color: #006100;
            font-weight: bold;
            font-family: Arial, sans-serif;
            font-size: 11px;
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
        <input type="date" id="END_DATE" name="END_DATE" class="filter-date" value="<?php echo h($end_input); ?>" readonly>

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
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>
                <td class="title-area">
                    <div class="report-title">LOGICAL STOCK</div>
                </td>
                <td class="right-info">Page 0 of 0</td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Isi Start, End, Customer lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> untuk semua customer.
        </div>
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
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">LOGICAL STOCK</div>
                    <div style="font-size:11px;margin-top:4px;">
                        <?php echo h($start_input); ?> s/d <?php echo h($end_input); ?>
                    </div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
                </td>
            </tr>
        </table>

        <div class="print-date">Print Date: <?php echo h(fmt_print_datetime()); ?></div>

        <?php for ($ridx = 0; $ridx < count($pageRows); $ridx++) { ?>
            <?php $hrow = $pageRows[$ridx]; ?>

            <?php if (isset($hrow["ROW_EMPTY"])) { ?>
                <div class="no-data"><?php echo h($hrow["MESSAGE"]); ?></div>
            <?php } else { ?>
                <?php
                $pcust = safe_trim($hrow["CUST_CODE"]);
                $pitem = safe_trim($hrow["ITEM_CODE"]);

                $f1 = get_forecast_qty($forecastMap, $pcust, $pitem, 1);
                $f2 = get_forecast_qty($forecastMap, $pcust, $pitem, 2);
                $f3 = get_forecast_qty($forecastMap, $pcust, $pitem, 3);

                // Mengambil nilai Beginning Stock (PHP 5.4 compatible)
                $beginningStock = isset($stockAwalMap[$pcust . "|" . $pitem]) ? $stockAwalMap[$pcust . "|" . $pitem] : 0;

                $forecastText =
                    "Forecast " . $forecastLabel1 . ": " . fmt_num_cell($f1) .
                    " | " .
                    "Forecast " . $forecastLabel2 . ": " . fmt_num_cell($f2) .
                    " | " .
                    "Forecast " . $forecastLabel3 . ": " . fmt_num_cell($f3);

                $stockAwalText = "Beginning Stock: " . fmt_num_cell($beginningStock);
                ?>

                <div class="item-title">
                    <?php echo h(safe_trim($hrow["CUST_CODE"])); ?> -
                    <?php echo h(safe_trim($hrow["CUST_COMP"])); ?><br>
                    <?php echo h(safe_trim($hrow["ITEM_CODE"])); ?> -
                    <?php echo h(safe_trim($hrow["ITEM_NAME"])); ?><br>
                    
                    <div style="margin-top:2px;">
                        <span class="forecast-title"><?php echo h($forecastText); ?></span>
                        
                        <!-- margin-left: 25px digunakan untuk memberi jarak dari teks forecast. Silakan ubah nilainya jika ingin lebih dekat/jauh -->
                        <span style="display:inline-block; margin-left:25px; color:#b22222; font-weight:bold; font-family:Arial,sans-serif; font-size:11px;">
                            | <?php echo h($stockAwalText); ?>
                        </span>
                    </div>
                </div>

                <?php
                    $stockPlanRow   = get_prod_row($prodMap, $pcust, $pitem, "Est Stock Plan");
                    $stockActualRow = get_prod_row($prodMap, $pcust, $pitem, "Est Stock Actual");
                    $percentRow     = get_prod_row($prodMap, $pcust, $pitem, "Percent Stock");

                    $minusDelBalDay = first_del_balance_minus_day($hrow);
                    $minusPlanDay   = first_minus_day($stockPlanRow);
                    $minusActualDay = first_minus_day($stockActualRow);
                    $below25Day     = first_below_percent_day($stockPlanRow, $hrow, 25);
                ?>

                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th class="row-label"></th>
                            <th class="shortage-col">Shortage</th>
                            <th class="day-col">Total</th>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <th class="day-col"><?php echo h(str_pad($i, 2, "0", STR_PAD_LEFT)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td class="row-label">Del Plan</td>
                            <td>-</td>
                            <td><?php echo h(fmt_num_cell(sum_hrow_days($hrow, "SCH"))); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <?php $val = isset($hrow[$i . "_SCH"]) ? $hrow[$i . "_SCH"] : 0; ?>
                                <td><?php echo h(fmt_num_cell($val)); ?></td>
                            <?php } ?>
                        </tr>

                        <tr>
                            <td class="row-label">Del Actual</td>
                            <td>-</td>
                            <td><?php echo h(fmt_num_cell(sum_hrow_days($hrow, "DEL"))); ?></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <?php $val = isset($hrow[$i . "_DEL"]) ? $hrow[$i . "_DEL"] : 0; ?>
                                <td><?php echo h(fmt_num_cell($val)); ?></td>
                            <?php } ?>
                        </tr>

                        <tr>
                            <td class="row-label">Del Balance</td>
                            <td style="background:#ffff00; color:#000000; font-weight:bold; text-align:center;">
                                <?php echo h($minusDelBalDay); ?>
                            </td>
                            <td></td>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <?php
                                    $val = isset($hrow[$i . "_BAL"]) ? $hrow[$i . "_BAL"] : 0;
                                    $class = ((float)$val < 0) ? "negative-balance" : "";
                                ?>
                                <td class="<?php echo h($class); ?>"><?php echo h(fmt_num_cell($val)); ?></td>
                            <?php } ?>
                        </tr>

                        <?php
                            print_prod_tr("Prod Plan",    get_prod_row($prodMap, $pcust, $pitem, "Prod Plan R0"), "prod-row");
                            print_prod_tr("Prod OK1",     get_prod_row($prodMap, $pcust, $pitem, "Prod OK1"), "prod-row");
                            print_prod_tr("Prod OK",      get_prod_row($prodMap, $pcust, $pitem, "Prod OK"), "prod-row");
                            print_prod_tr("Prod HOLD",    get_prod_row($prodMap, $pcust, $pitem, "Prod HOLD"), "prod-row");
                            print_prod_tr("Prod NG",      get_prod_row($prodMap, $pcust, $pitem, "Prod NG"), "ng-row");
                            print_prod_tr("NG Rework",    get_prod_row($prodMap, $pcust, $pitem, "NG Rework"), "ng-row");
                            print_prod_tr_shortage("Stock Plan",   $stockPlanRow, "stock-row", $minusPlanDay);
                            print_prod_tr_shortage("Stock Actual", $stockActualRow, "stock-row", $minusActualDay);
                            print_percent_tr_shortage("Stock %",   $percentRow, $below25Day);
                        ?>
                    </tbody>
                </table>
                <div class="dash"></div>
            <?php } ?>
        <?php } ?>
    </div>
<?php } ?>

<script>
var custRows = [];
var custIndex = -1;
var timer = null;

function updateEndDateToMonthEnd() {
    var startEl = document.getElementById("START_DATE");
    var endEl = document.getElementById("END_DATE");

    if (!startEl || !endEl || startEl.value == "") return;

    var parts = startEl.value.split("-");
    if (parts.length != 3) return;

    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);

    var last = new Date(y, m, 0);
    var mm = String(last.getMonth() + 1);
    var dd = String(last.getDate());

    if (mm.length < 2) mm = "0" + mm;
    if (dd.length < 2) dd = "0" + dd;

    endEl.value = y + "-" + mm + "-" + dd;
}

function enc(v) {
    return encodeURIComponent(v == null ? "" : v);
}

function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function closeReport() {
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {}

    window.open("", "_self");
    window.close();

    setTimeout(function () {
        if (!window.closed) {
            window.location.href = "dashboard_home.php";
        }
    }, 200);
}

function exportExcel() {
    var startDate = document.getElementById("START_DATE").value;
    var endDate = document.getElementById("END_DATE").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (startDate == "") {
        alert("Start Date belum diisi.");
        document.getElementById("START_DATE").focus();
        return;
    }

    if (endDate == "") {
        alert("End Date belum diisi.");
        document.getElementById("END_DATE").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "delivery_schedule_prod_export_excel.php" +
        "?START_DATE=" + enc(startDate) +
        "&END_DATE=" + enc(endDate) +
        "&CUST_CODE=" + enc(custCode);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
    document.forms[0].submit();
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

    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }

    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;

    var r = custRows[index];
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    hideSuggest();
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

            div.onmouseover = function () {
                setActiveCust(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                chooseCust(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer(q) {
    if (q == "" || q == "%") {
        hideSuggest();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var result;

            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                return;
            }

            if (result.rows) {
                renderSuggest(result.rows);
            } else {
                renderSuggest(result);
            }
        }
    };

    xhr.send("q=" + enc(q));
}

document.getElementById("START_DATE").onchange = function () {
    updateEndDateToMonthEnd();
};

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 40) {
        setActiveCust(custIndex + 1);
        return;
    }

    if (key == 38) {
        setActiveCust(custIndex - 1);
        return;
    }

    if (key == 13) {
        if (custRows.length > 0) {
            if (custIndex < 0) custIndex = 0;
            chooseCust(custIndex);
            return false;
        }

        return true;
    }

    clearTimeout(timer);
    var q = this.value;

    timer = setTimeout(function () {
        searchCustomer(q);
    }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 250);
};
</script>

</body>
</html>