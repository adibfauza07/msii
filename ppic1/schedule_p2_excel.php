<?php
require_once "../config/database_ppic.php";

$start = $_GET["start"];
$end   = $_GET["end"];
$cust  = $_GET["cust"];

$start8 = str_replace("-", "", $start);
$end8   = str_replace("-", "", $end);

// ambil data dari SP
$sql = "{CALL sp_PivotDeliverySchedule_ByCustomer(?,?,?)}";
$params = array($start8, $end8, $cust);
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $key = $r["CUST_CODE"]."|".$r["ITEM_CODE"];
    $data[$key] = $r;
}

// header excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=DeliverySchedule_P1.xls");

echo "<table border='1'>";
echo "<tr><th colspan='35'>Delivery Schedule P1</th></tr>";
echo "<tr><th colspan='35'>Customer: $cust | Periode $start s/d $end</th></tr>";

foreach($data as $h){

    echo "<tr><td colspan='35'><b>".
         $h['CUST_CODE']." - ".$h['CUST_COMP'].
         "</b></td></tr>";

    echo "<tr><td colspan='35'><b>".
         $h['ITEM_CODE']." - ".$h['ITEM_NAME'].
         "</b></td></tr>";

    echo "<tr><th></th>";
    for ($i=1; $i<=31; $i++) echo "<th>".str_pad($i,2,'0',STR_PAD_LEFT)."</th>";
    echo "</tr>";

    // PLA
    echo "<tr><td>Pla</td>";
    for ($i=1; $i<=31; $i++){
        $col = $i."_SCH";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    echo "</tr>";

    // ACT
    echo "<tr><td>Act</td>";
    for ($i=1; $i<=31; $i++){
        $col = $i."_DEL";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    echo "</tr>";

    // BAL
    echo "<tr><td>Bal</td>";
    for ($i=1; $i<=31; $i++){
        $col = $i."_BAL";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    echo "</tr>";

    echo "<tr><td colspan='35'></td></tr>";
}

echo "</table>";
