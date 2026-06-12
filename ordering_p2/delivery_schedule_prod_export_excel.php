<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) die("Koneksi database gagal.");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function safe_trim($v){ return $v === null ? "" : trim((string)$v); }

function get_param($name,$default=""){
    if(isset($_GET[$name])) return trim($_GET[$name]);
    if(isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function ymd_param($value){
    $value = trim($value);
    if($value=="") return "";
    if(preg_match('/^\d{8}$/',$value)) return $value;
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) return str_replace("-","",$value);
    $ts = strtotime($value);
    if($ts===false) return "";
    return date("Ymd",$ts);
}

function fmt_print_datetime(){ return date("d-M-Y H:i:s"); }

function excel_num($value){
    if($value === null || $value === "") return "";
    $n = (float)$value;
    if($n == 0) return "";
    if($n < 0) return "(" . number_format(abs($n),0,".","") . ")";
    return number_format($n,0,".","");
}

function excel_percent($value){
    if($value === null || $value === "") return "";
    $n = (float)$value;
    if($n == 0) return "";
    return number_format($n,1,".","") . "%";
}

function sum_hrow_days($hrow,$suffix){
    $total = 0;
    for($i=1;$i<=31;$i++){
        $col = $i . "_" . $suffix;
        $total += isset($hrow[$col]) ? (float)$hrow[$col] : 0;
    }
    return $total;
}

function empty_day_row(){
    $row = array("G_TOTAL"=>0);
    for($i=1;$i<=31;$i++) $row["D".$i] = 0;
    return $row;
}

function get_prod_row($prodMap,$custCode,$itemCode,$rowName){
    $key = safe_trim($custCode) . "|" . safe_trim($itemCode);
    if(isset($prodMap[$key]) && isset($prodMap[$key][$rowName])) return $prodMap[$key][$rowName];
    return null;
}

function first_minus_day($dataRow){
    if(!$dataRow) return "-";
    for($i=1;$i<=31;$i++){
        $col = "D".$i;
        if(isset($dataRow[$col]) && (float)$dataRow[$col] < 0) return str_pad($i,2,"0",STR_PAD_LEFT);
    }
    return "-";
}

function first_del_balance_minus_day($hrow){
    if(!$hrow) return "-";
    for($i=1;$i<=31;$i++){
        $col = $i . "_BAL";
        if(isset($hrow[$col]) && (float)$hrow[$col] < 0) return str_pad($i,2,"0",STR_PAD_LEFT);
    }
    return "-";
}

function first_below_percent_day($stockRow,$delRow,$percent=25){
    if(!$stockRow) return "-";

    $delPlanTotal = sum_hrow_days($delRow,"SCH");
    if($delPlanTotal <= 0) return "-";

    for($i=1;$i<=31;$i++){
        $col = "D".$i;
        $stock = isset($stockRow[$col]) ? (float)$stockRow[$col] : 0;
        if($stock <= 0) continue;
        $ratio = ($stock / $delPlanTotal) * 100;
        if($ratio < $percent) return str_pad($i,2,"0",STR_PAD_LEFT);
    }
    return "-";
}

function print_prod_tr_excel($label,$dataRow,$cssClass,$shortageDay="-"){
    echo '<tr class="'.h($cssClass).'">';
    echo '<td class="text">'.h($label).'</td>';
    echo '<td class="shortage">'.h($shortageDay).'</td>';

    $grand = ($dataRow && isset($dataRow["G_TOTAL"])) ? $dataRow["G_TOTAL"] : 0;
    $gclass = ((float)$grand < 0) ? "num negative" : "num";
    echo '<td class="'.h($gclass).'">'.h(excel_num($grand)).'</td>';

    for($i=1;$i<=31;$i++){
        $col = "D".$i;
        $val = ($dataRow && isset($dataRow[$col])) ? $dataRow[$col] : 0;
        $class = ((float)$val < 0) ? "num negative" : "num";
        echo '<td class="'.h($class).'">'.h(excel_num($val)).'</td>';
    }

    echo '</tr>';
}

function print_percent_tr_excel($label,$dataRow,$shortageDay="-"){
    echo '<tr class="stock-row">';
    echo '<td class="text">'.h($label).'</td>';
    echo '<td class="shortage">'.h($shortageDay).'</td>';
    echo '<td class="num"></td>';

    for($i=1;$i<=31;$i++){
        $col = "D".$i;
        $val = ($dataRow && isset($dataRow[$col])) ? $dataRow[$col] : 0;
        $class = ((float)$val < 25 && (float)$val > 0) ? "num warning" : "num";
        echo '<td class="'.h($class).'">'.h(excel_percent($val)).'</td>';
    }

    echo '</tr>';
}

$start_date = get_param("START_DATE","");
$end_date   = get_param("END_DATE","");
$cust_code  = get_param("CUST_CODE","%");

$start_ymd = ymd_param($start_date);
$end_ymd   = ymd_param($end_date);

if($start_ymd=="" || $end_ymd=="") die("Tanggal tidak valid.");
if($cust_code=="") $cust_code = "%";

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.sp_PivotDeliverySchedule_ByCustomer ?, ?, ?
";

$stmt = sqlsrv_query($conn,$sql,array($start_ymd,$end_ymd,$cust_code));
if($stmt === false){ die("<pre>Query Delivery Schedule gagal:\n".print_r(sqlsrv_errors(),true)."</pre>"); }

$rows = array();
while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){ $rows[] = $r; }

$periode = substr($start_ymd,0,6);
$prodMap = array();
$stockAwalMap = array();

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
        SUM(ISNULL(D.G_TOTAL,0)) AS G_TOTAL,
        SUM(ISNULL(D.D1,0)) AS D1, SUM(ISNULL(D.D2,0)) AS D2,
        SUM(ISNULL(D.D3,0)) AS D3, SUM(ISNULL(D.D4,0)) AS D4,
        SUM(ISNULL(D.D5,0)) AS D5, SUM(ISNULL(D.D6,0)) AS D6,
        SUM(ISNULL(D.D7,0)) AS D7, SUM(ISNULL(D.D8,0)) AS D8,
        SUM(ISNULL(D.D9,0)) AS D9, SUM(ISNULL(D.D10,0)) AS D10,
        SUM(ISNULL(D.D11,0)) AS D11, SUM(ISNULL(D.D12,0)) AS D12,
        SUM(ISNULL(D.D13,0)) AS D13, SUM(ISNULL(D.D14,0)) AS D14,
        SUM(ISNULL(D.D15,0)) AS D15, SUM(ISNULL(D.D16,0)) AS D16,
        SUM(ISNULL(D.D17,0)) AS D17, SUM(ISNULL(D.D18,0)) AS D18,
        SUM(ISNULL(D.D19,0)) AS D19, SUM(ISNULL(D.D20,0)) AS D20,
        SUM(ISNULL(D.D21,0)) AS D21, SUM(ISNULL(D.D22,0)) AS D22,
        SUM(ISNULL(D.D23,0)) AS D23, SUM(ISNULL(D.D24,0)) AS D24,
        SUM(ISNULL(D.D25,0)) AS D25, SUM(ISNULL(D.D26,0)) AS D26,
        SUM(ISNULL(D.D27,0)) AS D27, SUM(ISNULL(D.D28,0)) AS D28,
        SUM(ISNULL(D.D29,0)) AS D29, SUM(ISNULL(D.D30,0)) AS D30,
        SUM(ISNULL(D.D31,0)) AS D31
    FROM dbo.RPT_PPIC P
    INNER JOIN dbo.RPT_PPIC_DTL D ON P.ID_NO = D.ID_NO
    INNER JOIN dbo.CUST C ON P.CUST = C.CUST_ID
    WHERE P.PERIODE = ?
      AND (? = '%' OR C.CUST_CODE = ?)
      AND LTRIM(RTRIM(D.DESC_PROD)) IN ('Prod Plan R0','NG Rework','Est Stock Plan')
    GROUP BY C.CUST_CODE, P.ITEM_CODE, LTRIM(RTRIM(D.DESC_PROD))

    UNION ALL

    SELECT
        C.CUST_CODE,
        I.ITEM_CODE,
        V.ROW_NAME,
        SUM(ISNULL(V.QTY,0)) AS G_TOTAL,
        SUM(CASE WHEN DAY(V.PD_DATE)=1  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D1,
        SUM(CASE WHEN DAY(V.PD_DATE)=2  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D2,
        SUM(CASE WHEN DAY(V.PD_DATE)=3  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D3,
        SUM(CASE WHEN DAY(V.PD_DATE)=4  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D4,
        SUM(CASE WHEN DAY(V.PD_DATE)=5  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D5,
        SUM(CASE WHEN DAY(V.PD_DATE)=6  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D6,
        SUM(CASE WHEN DAY(V.PD_DATE)=7  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D7,
        SUM(CASE WHEN DAY(V.PD_DATE)=8  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D8,
        SUM(CASE WHEN DAY(V.PD_DATE)=9  THEN ISNULL(V.QTY,0) ELSE 0 END) AS D9,
        SUM(CASE WHEN DAY(V.PD_DATE)=10 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D10,
        SUM(CASE WHEN DAY(V.PD_DATE)=11 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D11,
        SUM(CASE WHEN DAY(V.PD_DATE)=12 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D12,
        SUM(CASE WHEN DAY(V.PD_DATE)=13 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D13,
        SUM(CASE WHEN DAY(V.PD_DATE)=14 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D14,
        SUM(CASE WHEN DAY(V.PD_DATE)=15 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D15,
        SUM(CASE WHEN DAY(V.PD_DATE)=16 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D16,
        SUM(CASE WHEN DAY(V.PD_DATE)=17 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D17,
        SUM(CASE WHEN DAY(V.PD_DATE)=18 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D18,
        SUM(CASE WHEN DAY(V.PD_DATE)=19 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D19,
        SUM(CASE WHEN DAY(V.PD_DATE)=20 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D20,
        SUM(CASE WHEN DAY(V.PD_DATE)=21 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D21,
        SUM(CASE WHEN DAY(V.PD_DATE)=22 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D22,
        SUM(CASE WHEN DAY(V.PD_DATE)=23 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D23,
        SUM(CASE WHEN DAY(V.PD_DATE)=24 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D24,
        SUM(CASE WHEN DAY(V.PD_DATE)=25 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D25,
        SUM(CASE WHEN DAY(V.PD_DATE)=26 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D26,
        SUM(CASE WHEN DAY(V.PD_DATE)=27 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D27,
        SUM(CASE WHEN DAY(V.PD_DATE)=28 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D28,
        SUM(CASE WHEN DAY(V.PD_DATE)=29 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D29,
        SUM(CASE WHEN DAY(V.PD_DATE)=30 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D30,
        SUM(CASE WHEN DAY(V.PD_DATE)=31 THEN ISNULL(V.QTY,0) ELSE 0 END) AS D31
    FROM (
        SELECT W.ITEM_ID, P.PD_DATE, 'Prod OK' AS ROW_NAME, ISNULL(P.PD_OK,0) AS QTY
        FROM dbo.PRODUCTION P
        INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
        WHERE P.PD_DATE >= ? AND P.PD_DATE < DATEADD(DAY,1,?)

        UNION ALL

        SELECT W.ITEM_ID, P.PD_DATE, 'Prod HOLD' AS ROW_NAME, ISNULL(P.PD_HO,0) AS QTY
        FROM dbo.PRODUCTION P
        INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
        WHERE P.PD_DATE >= ? AND P.PD_DATE < DATEADD(DAY,1,?)

        UNION ALL

        SELECT W.ITEM_ID, P.PD_DATE, 'Prod NG' AS ROW_NAME, ISNULL(P.PD_NG,0) AS QTY
        FROM dbo.PRODUCTION P
        INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
        WHERE P.PD_DATE >= ? AND P.PD_DATE < DATEADD(DAY,1,?)
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
        SUM(ISNULL(T.QTY,0)) AS G_TOTAL,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=1  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D1,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=2  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D2,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=3  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D3,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=4  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D4,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=5  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D5,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=6  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D6,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=7  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D7,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=8  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D8,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=9  THEN ISNULL(T.QTY,0) ELSE 0 END) AS D9,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=10 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D10,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=11 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D11,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=12 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D12,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=13 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D13,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=14 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D14,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=15 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D15,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=16 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D16,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=17 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D17,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=18 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D18,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=19 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D19,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=20 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D20,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=21 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D21,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=22 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D22,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=23 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D23,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=24 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D24,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=25 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D25,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=26 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D26,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=27 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D27,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=28 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D28,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=29 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D29,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=30 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D30,
        SUM(CASE WHEN DAY(T.TRAN_DATE)=31 THEN ISNULL(T.QTY,0) ELSE 0 END) AS D31
    FROM (
        SELECT IT.ITEM_ID, TR.TRAN_DATE, SUM(IT.IT_QTY) AS QTY
        FROM dbo.INV_TRAN IT
        INNER JOIN dbo.TRANS TR ON IT.TRAN_ID = TR.TRAN_ID
        WHERE TR.TRAN_DATE >= ?
          AND TR.TRAN_DATE < DATEADD(DAY,1,?)
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

$stmtProd = sqlsrv_query($conn,$sqlProd,array(
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

if($stmtProd === false){ die("<pre>Query Production / Stock gagal:\n".print_r(sqlsrv_errors(),true)."</pre>"); }

while($pr = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)){
    $key = safe_trim($pr["CUST_CODE"]) . "|" . safe_trim($pr["ITEM_CODE"]);
    $rowName = safe_trim($pr["ROW_NAME"]);
    if(!isset($prodMap[$key])) $prodMap[$key] = array();
    $prodMap[$key][$rowName] = $pr;
}

$sqlStockAwal = "
SELECT
    C.CUST_CODE,
    I.ITEM_CODE,
    SUM(ISNULL(TG.TAG_QTY,0)) AS BAL
FROM dbo.TAGS TG
INNER JOIN dbo.SOP S ON TG.SOP_ID = S.SOP_ID
INNER JOIN dbo.ITEMS I ON TG.ITEM_ID = I.ITEM_ID
INNER JOIN dbo.ITEM_CUSTINFO_VIEW IC ON I.ITEM_ID = IC.ITEM_ID
INNER JOIN dbo.CUST C ON IC.CUST_ID = C.CUST_ID
WHERE S.SOP_SDATE = ?
  AND (? = '%' OR C.CUST_CODE = ?)
GROUP BY C.CUST_CODE, I.ITEM_CODE
";

$stmtStockAwal = sqlsrv_query($conn,$sqlStockAwal,array($start_ymd,$cust_code,$cust_code));
if($stmtStockAwal === false){ die("<pre>Query Stock Awal gagal:\n".print_r(sqlsrv_errors(),true)."</pre>"); }

while($sr = sqlsrv_fetch_array($stmtStockAwal, SQLSRV_FETCH_ASSOC)){
    $key = safe_trim($sr["CUST_CODE"]) . "|" . safe_trim($sr["ITEM_CODE"]);
    $stockAwalMap[$key] = isset($sr["BAL"]) ? (float)$sr["BAL"] : 0;
}

foreach($rows as $hrow){
    $key = safe_trim($hrow["CUST_CODE"]) . "|" . safe_trim($hrow["ITEM_CODE"]);

    if(!isset($prodMap[$key])) $prodMap[$key] = array();
    if(!isset($prodMap[$key]["Prod HOLD"])) $prodMap[$key]["Prod HOLD"] = empty_day_row();

    $stockPlan    = empty_day_row();
    $stockActual  = empty_day_row();
    $percentStock = empty_day_row();

    $runningPlan   = isset($stockAwalMap[$key]) ? (float)$stockAwalMap[$key] : 0;
    $runningActual = isset($stockAwalMap[$key]) ? (float)$stockAwalMap[$key] : 0;
    $delPlanTotal  = sum_hrow_days($hrow,"SCH");

    for($i=1;$i<=31;$i++){
        $dcol = "D".$i;

        $delPlanCol   = $i . "_SCH";
        $delActualCol = $i . "_DEL";

        $prodPlan = isset($prodMap[$key]["Prod Plan R0"][$dcol]) ? (float)$prodMap[$key]["Prod Plan R0"][$dcol] : 0;
        $prodOk = isset($prodMap[$key]["Prod OK"][$dcol]) ? (float)$prodMap[$key]["Prod OK"][$dcol] : 0;
        $prodHold = isset($prodMap[$key]["Prod HOLD"][$dcol]) ? (float)$prodMap[$key]["Prod HOLD"][$dcol] : 0;
        $prodOk1 = isset($prodMap[$key]["Prod OK1"][$dcol]) ? (float)$prodMap[$key]["Prod OK1"][$dcol] : 0;

        $delPlan = isset($hrow[$delPlanCol]) ? (float)$hrow[$delPlanCol] : 0;
        $delActual = isset($hrow[$delActualCol]) ? (float)$hrow[$delActualCol] : 0;

        $runningPlan = $runningPlan + $prodPlan - $delPlan;
        $stockPlan[$dcol] = $runningPlan;

        if($delPlanTotal > 0 && $runningPlan > 0){
            $percentStock[$dcol] = ($runningPlan / $delPlanTotal) * 100;
        } else {
            $percentStock[$dcol] = 0;
        }

        $prodInActual = $prodOk + $prodHold;
        if($prodInActual == 0) $prodInActual = $prodOk1;

        $runningActual = $runningActual + $prodInActual - $delActual;
        $stockActual[$dcol] = $runningActual;
    }

    $prodMap[$key]["Est Stock Plan"] = $stockPlan;
    $prodMap[$key]["Est Stock Actual"] = $stockActual;
    $prodMap[$key]["Percent Stock"] = $percentStock;
}

$fileCust = $cust_code=="%" ? "ALL" : $cust_code;
$fileName = "logical_stock_" . $fileCust . "_" . $start_ymd . "_" . $end_ymd . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"".$fileName."\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Logical Stock Export</title>
<style>
@page { size: A3 landscape; margin: 5mm; }
table { border-collapse:collapse; font-family:Arial,sans-serif; font-size:9pt; }
th { background:#d9eaf7; font-weight:bold; border:1px solid #000; text-align:center; }
td { border:1px solid #000; padding:3px; vertical-align:middle; }
.title { font-size:16pt; font-weight:bold; text-align:center; }
.text { mso-number-format:"\@"; }
.num { mso-number-format:"#,##0"; text-align:right; }
.negative { color:red; font-weight:bold; }
.warning { color:#ff6600; font-weight:bold; }
.shortage { mso-number-format:"\@"; background:#ffff00; color:#000000; font-weight:bold; text-align:center; }
.customer-row { background:#eeeeee; font-weight:bold; }
.item-row { background:#ffff99; font-weight:bold; }
.balance-row { background:#ffff99; }
.prod-row { background:#f7f7f7; }
.stock-row { background:#00b0f0; font-weight:bold; }
</style>
</head>
<body>

<table>
<tr><td colspan="34" class="title">LOGICAL STOCK</td></tr>
<tr><td colspan="34">P.T. IMC TEKNO INDONESIA - PPIC Department</td></tr>
<tr><td colspan="34">Period: <?php echo h($start_date); ?> s/d <?php echo h($end_date); ?></td></tr>
<tr><td colspan="34">Customer: <?php echo h($cust_code=="%" ? "ALL CUSTOMER" : $cust_code); ?></td></tr>
<tr><td colspan="34">Export Date: <?php echo h(fmt_print_datetime()); ?></td></tr>
<tr><td colspan="34">&nbsp;</td></tr>

<?php if(count($rows)==0){ ?>
<tr><td colspan="34" style="text-align:center;">Data logical stock tidak ditemukan.</td></tr>
<?php } ?>

<?php foreach($rows as $hrow){ ?>
    <?php
        $pcust = safe_trim($hrow["CUST_CODE"]);
        $pitem = safe_trim($hrow["ITEM_CODE"]);

        $stockPlanRow   = get_prod_row($prodMap,$pcust,$pitem,"Est Stock Plan");
        $stockActualRow = get_prod_row($prodMap,$pcust,$pitem,"Est Stock Actual");
        $percentRow     = get_prod_row($prodMap,$pcust,$pitem,"Percent Stock");

        $minusDelBalDay = first_del_balance_minus_day($hrow);
        $minusPlanDay   = first_minus_day($stockPlanRow);
        $minusActualDay = first_minus_day($stockActualRow);
        $below25Day     = first_below_percent_day($stockPlanRow,$hrow,25);
    ?>

    <tr class="customer-row">
        <td colspan="34"><?php echo h($pcust); ?> - <?php echo h(safe_trim($hrow["CUST_COMP"])); ?></td>
    </tr>

    <tr class="item-row">
        <td colspan="34"><?php echo h($pitem); ?> - <?php echo h(safe_trim($hrow["ITEM_NAME"])); ?></td>
    </tr>

    <tr>
        <th>Row</th>
        <th class="shortage">Shortage</th>
        <th>Total</th>
        <?php for($i=1;$i<=31;$i++){ ?>
            <th><?php echo h(str_pad($i,2,"0",STR_PAD_LEFT)); ?></th>
        <?php } ?>
    </tr>

    <tr>
        <td class="text">Del Plan</td>
        <td class="shortage">-</td>
        <td class="num"><?php echo h(excel_num(sum_hrow_days($hrow,"SCH"))); ?></td>
        <?php for($i=1;$i<=31;$i++){ $val = isset($hrow[$i."_SCH"]) ? $hrow[$i."_SCH"] : 0; ?>
            <td class="num"><?php echo h(excel_num($val)); ?></td>
        <?php } ?>
    </tr>

    <tr>
        <td class="text">Del Actual</td>
        <td class="shortage">-</td>
        <td class="num"><?php echo h(excel_num(sum_hrow_days($hrow,"DEL"))); ?></td>
        <?php for($i=1;$i<=31;$i++){ $val = isset($hrow[$i."_DEL"]) ? $hrow[$i."_DEL"] : 0; ?>
            <td class="num"><?php echo h(excel_num($val)); ?></td>
        <?php } ?>
    </tr>

    <tr class="balance-row">
        <td class="text">Del Balance</td>
        <td class="shortage"><?php echo h($minusDelBalDay); ?></td>
        <td></td>
        <?php for($i=1;$i<=31;$i++){
            $val = isset($hrow[$i."_BAL"]) ? $hrow[$i."_BAL"] : 0;
            $class = ((float)$val < 0) ? "num negative" : "num";
        ?>
            <td class="<?php echo h($class); ?>"><?php echo h(excel_num($val)); ?></td>
        <?php } ?>
    </tr>

    <?php
        print_prod_tr_excel("Prod Plan", get_prod_row($prodMap,$pcust,$pitem,"Prod Plan R0"), "prod-row", "-");
        print_prod_tr_excel("Prod OK1",  get_prod_row($prodMap,$pcust,$pitem,"Prod OK1"), "prod-row", "-");
        print_prod_tr_excel("Prod OK",   get_prod_row($prodMap,$pcust,$pitem,"Prod OK"), "prod-row", "-");
        print_prod_tr_excel("Prod HOLD", get_prod_row($prodMap,$pcust,$pitem,"Prod HOLD"), "prod-row", "-");
        print_prod_tr_excel("Prod NG",   get_prod_row($prodMap,$pcust,$pitem,"Prod NG"), "prod-row", "-");
        print_prod_tr_excel("NG Rework", get_prod_row($prodMap,$pcust,$pitem,"NG Rework"), "prod-row", "-");
        print_prod_tr_excel("Stock Plan", $stockPlanRow, "stock-row", $minusPlanDay);
        print_prod_tr_excel("Stock Actual", $stockActualRow, "stock-row", $minusActualDay);
        print_percent_tr_excel("Stock %", $percentRow, $below25Day);
    ?>

    <tr><td colspan="34">&nbsp;</td></tr>
<?php } ?>

</table>
</body>
</html>
