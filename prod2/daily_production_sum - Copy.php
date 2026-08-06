<?php
/**
 * MONITORING CYCLE TIME — SUM TANPA LOT
 * Split: MC + CODE + ITEM + CUST
 * Sticky: MC → CODE → ITEM
 * + STD MATERIAL VIRGIN/PEWARNA + PROFIT/LOSS MATERIAL
 * + SUMMARY LOSS CYCLE TIME + TOP 10 CHARTS
 * + CHARTS PROFIT VS LOSS MATERIAL (PER MESIN & GLOBAL)
 */

require_once __DIR__ . "/../config/global.php";

 $db = null;
if (isset($conn)) { $db = $conn; }
elseif (isset($connection)) { $db = $connection; }
elseif (isset($dbconn)) { $db = $dbconn; }
if (!$db) { die('Koneksi database tidak ditemukan.'); }
if (!function_exists('sqlsrv_query')) { die('Extension sqlsrv belum aktif.'); }

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function num($v) { return ($v === null || $v === '') ? 0.0 : (float)$v; }
function fmt0($v) { return number_format(num($v), 0, '.', ','); }
function fmt2($v) { return number_format(num($v), 2, '.', ','); }
function fmt3($v) { return number_format(num($v), 3, '.', ','); }
function fmtRpAbs($v) {
    $n = num($v);
    if ($n < 0) return '(Rp ' . number_format(abs($n), 0, ',', '.') . ')';
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function validDateYmd($v, $fb) {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $fb;
    $ts = strtotime($v . ' 00:00:00');
    return ($ts === false || date('Y-m-d', $ts) !== $v) ? $fb : $v;
}
function dateDisplay($ymd) { $ts = strtotime($ymd); return $ts ? date('d-m-Y', $ts) : $ymd; }
function sqlsrvErrorText() {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!$errors) return 'Unknown error.';
    $out = array();
    foreach ($errors as $e) $out[] = '[' . $e['SQLSTATE'] . '] ' . $e['code'] . ' - ' . $e['message'];
    return implode("\n", $out);
}
function xlsSafe($v) { return str_replace(array("\r\n","\r"), "\n", (string)$v); }
function positiveFirst($c, $n) { $c = num($c); $n = num($n); return ($c == 0 && $n != 0) ? $n : $c; }

/*
 * Kategori material:
 * - ITTY 02 dan kode berakhiran -0 = VIRGIN
 * - ITTY 02 selain -0              = CRUSHER
 * - ITTY 13                        = PEWARNA
 */
function materialCategory($ittyCode, $matCode) {
    $itty = trim((string)$ittyCode);
    $code = rtrim((string)$matCode);
    if ($itty === '13') return 'PEWARNA';
    if ($itty === '02') return substr($code, -2) === '-0' ? 'VIRGIN' : 'CRUSHER';
    return '';
}

/*
 * STD MATERIAL:
 *     WO_QTY × BOM_QTY ÷ 1000
 *
 * ACTUAL MATERIAL (Update):
 *     PD_QTY × BOM_QTY ÷ 1000 (khusus ITTY 02 dan 13)
 *
 * Profit/Loss material dihitung untuk VIRGIN dan PEWARNA:
 *     (STD_MATERIAL - ACTUAL_MATERIAL) × HARGA_ASLI
 */
function decorateMaterialMetrics($materials, $woQty, $pdQty) {
    $prepared = array();
    $stdVirgin = 0.0;
    $stdPewarna = 0.0;
    $actualVirginTotal = 0.0;
    $actualPewarnaTotal = 0.0;
    $materialPl = 0.0;

    foreach ($materials as $m) {
        $cat = materialCategory(isset($m['ITTY_CODE']) ? $m['ITTY_CODE'] : '', isset($m['MAT_CODE']) ? $m['MAT_CODE'] : '');
        $eligible = ($cat === 'VIRGIN' || $cat === 'PEWARNA');
        $bomQty = num(isset($m['BOM_QTY']) ? $m['BOM_QTY'] : 0);
        
        // --- STANDARD MATERIAL ---
        $stdQty = $eligible ? (num($woQty) * $bomQty) / 1000.0 : 0.0;
        
        // --- ACTUAL MATERIAL --- (Berdasarkan PD_QTY khusus ITTY 02 & 13)
        $isItty02or13 = ($cat !== '');
        $actualQty = $isItty02or13 ? (num($pdQty) * $bomQty) / 1000.0 : 0.0;

        if ($cat === 'VIRGIN') { 
            $stdVirgin += $stdQty; 
            $actualVirginTotal += $actualQty; 
        }
        if ($cat === 'PEWARNA') { 
            $stdPewarna += $stdQty; 
            $actualPewarnaTotal += $actualQty; 
        }

        // --- SELISIH & PROFIT/LOSS ---
        $difference = $eligible ? $stdQty - $actualQty : 0.0;
        
        if ($actualQty == 0) {
            $profitLoss = 0.0;
        } else {
            $profitLoss = $eligible ? $difference * num(isset($m['HARGA_ASLI_IDR']) ? $m['HARGA_ASLI_IDR'] : 0) : 0.0;
        }

        $m['MATERIAL_CATEGORY'] = $cat;
        $m['STD_MATERIAL'] = $stdQty;
        $m['ACTUAL_MATERIAL'] = $actualQty;
        $m['SELISIH_MATERIAL'] = $difference;
        $m['MATERIAL_PL_IDR'] = $profitLoss;
        $m['PL_APPLIES'] = $eligible ? 1 : 0;
        
        $materialPl += $profitLoss;
        $prepared[] = $m;
    }

    return array(
        'materials' => $prepared,
        'std_virgin' => $stdVirgin,
        'std_pewarna' => $stdPewarna,
        'std_total' => $stdVirgin + $stdPewarna,
        'actual_pl' => $actualVirginTotal + $actualPewarnaTotal,
        'difference' => ($stdVirgin + $stdPewarna) - ($actualVirginTotal + $actualPewarnaTotal),
        'profit_loss' => $materialPl
    );
}

 $defaultStart = date('Y-m-01');
 $defaultEnd   = date('Y-m-d');
 $startDate = validDateYmd(isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart, $defaultStart);
 $endDate   = validDateYmd(isset($_GET['end_date']) ? $_GET['end_date'] : $defaultEnd, $defaultEnd);
if (strtotime($startDate) > strtotime($endDate)) { $tmp = $startDate; $startDate = $endDate; $endDate = $tmp; }
 $code     = isset($_GET['code']) && trim($_GET['code']) !== '' ? trim($_GET['code']) : '%';
 $custCode = isset($_GET['cust_code']) && trim($_GET['cust_code']) !== '' ? trim($_GET['cust_code']) : '%';
 $startSql = $startDate . ' 00:00:00.000';
 $endSql   = $endDate . ' 23:59:59.997';

function getUsdRate($db, $dateYmd) {
    $dateSql = $dateYmd . ' 23:59:59.997'; $rate = 0.0;
    $sql = "SELECT TOP 1 CURR_VRATE FROM dbo.CURR_RAT WHERE UPPER(LTRIM(RTRIM(CURR_CODE)))='USD' AND CONVERT(DATETIME,?,121)>=CURR_SDATE AND (CURR_EDATE IS NULL OR CONVERT(DATETIME,?,121)<=CURR_EDATE) ORDER BY CURR_SDATE DESC,CURR_EDATE DESC";
    $stmt = @sqlsrv_query($db, $sql, array($dateSql, $dateSql));
    if ($stmt !== false) { $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC); if ($r && isset($r['CURR_VRATE'])) $rate = num($r['CURR_VRATE']); sqlsrv_free_stmt($stmt); }
    if ($rate <= 0) {
        $sql2 = "SELECT TOP 1 CURR_VRATE FROM dbo.CURR_RAT WHERE UPPER(LTRIM(RTRIM(CURR_CODE)))='USD' AND CURR_SDATE<=CONVERT(DATETIME,?,121) ORDER BY CURR_SDATE DESC,CURR_EDATE DESC";
        $s2 = @sqlsrv_query($db, $sql2, array($dateSql));
        if ($s2 !== false) { $r2 = sqlsrv_fetch_array($s2, SQLSRV_FETCH_ASSOC); if ($r2 && isset($r2['CURR_VRATE'])) $rate = num($r2['CURR_VRATE']); sqlsrv_free_stmt($s2); }
    }
    return $rate;
}
 $dbUsdRate = getUsdRate($db, $endDate);
 $manualUsdRate = isset($_GET['usd_rate']) ? num(str_replace(array('.',','), array('','.'), $_GET['usd_rate'])) : 0;
 $usdRate = $manualUsdRate > 0 ? $manualUsdRate : $dbUsdRate;

 $machineRateUsdMap = array(
    '201'=>8,'202'=>8,'203'=>8,'204'=>9,'205'=>8,'206'=>10,'207'=>10,'208'=>11,'209'=>12,'210'=>13,
    '211'=>13,'212'=>5,'213'=>5,'214'=>5,'215'=>5,'216'=>5,'217'=>5,'218'=>6,'219'=>6,'220'=>6,
    '221'=>6,'222'=>6,'223'=>8,'225'=>9,'226'=>8,'227'=>8,'228'=>8,'229'=>8,'230'=>10,'231'=>9,
    '232'=>9,'233'=>6,'234'=>8,'235'=>8,'236'=>8,'237'=>8,'238'=>8,'239'=>8,'240'=>9,'241'=>9,
    '242'=>12,'243'=>10,'244'=>10,'245'=>10,'246'=>10,'247'=>10,'248'=>12,'249'=>12,'250'=>12,
    '252'=>13,'253'=>13,'254'=>18,'255'=>15,'256'=>18
);
function digitsOnly($v) { return preg_replace('/[^0-9]/', '', (string)$v); }
function machineCandidateKeys($st, $mc) {
    $k=array(); $sd=digitsOnly($st); $md=digitsOnly($mc);
    if($md!==''){$k[]=ltrim($md,'0');$k[]=$md;}
    if($sd!==''&&$md!==''){$k[]=$sd.$md;$k[]=$sd.str_pad($md,2,'0',STR_PAD_LEFT);}
    $j=digitsOnly((string)$st.(string)$mc);
    if($j!==''){$k[]=$j;if(strlen($j)>=3)$k[]=substr($j,-3);}
    $c=array();foreach($k as $x){$x=trim((string)$x);if($x==='')continue;$n=ltrim($x,'0');if($n==='')$n='0';$c[$n]=true;}
    return array_keys($c);
}
function machineRateUsd($row,$rm) {
    foreach(array('RATE_USD','MC_RATE_USD','MAC_RATE_USD','MACHINE_RATE_USD','RATE_MC_USD','MC_RATE') as $k){if(isset($row[$k])&&num($row[$k])>0)return num($row[$k]);}
    $cands=machineCandidateKeys(isset($row['MAG_STATION'])?$row['MAG_STATION']:'',isset($row['MAC_CODE'])?$row['MAC_CODE']:'');
    foreach($cands as $c){if(isset($rm[$c]))return num($rm[$c]);}return 0.0;
}
function machineDisplay($row,$rm) {
    $st=isset($row['MAG_STATION'])?trim((string)$row['MAG_STATION']):'';$mc=isset($row['MAC_CODE'])?trim((string)$row['MAC_CODE']):'';
    $cands=machineCandidateKeys($st,$mc);foreach($cands as $c){if(isset($rm[$c]))return $c;}
    return $mc!==''?trim($st.' '.$mc):$st;
}

 $stmt = sqlsrv_query($db, "{CALL dbo.sp_daily_prod(?,?,?,?)}", array($startSql, $endSql, $code, $custCode));
if ($stmt === false) die('<pre style="white-space:pre-wrap;color:#a00">'.h(sqlsrvErrorText()).'</pre>');
 $rawRows=array(); $custKeys=array(); $itemKeys=array(); $woKeys=array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rawRows[]=$r;
    if(!empty($r['CUST_CODE']))$custKeys[trim($r['CUST_CODE'])]=true;
    if(!empty($r['ITEM_CODE']))$itemKeys[trim($r['ITEM_CODE'])]=true;
    if(!empty($r['WO_NUMBER']))$woKeys[trim($r['WO_NUMBER'])]=true;
}
sqlsrv_free_stmt($stmt);

 $custMap=array();
 $ack=array_keys($custKeys);
if(count($ack)>0){
    $in="'".implode("','",array_map(function($c){return str_replace("'","''",$c);},$ack))."'";
    $sc=@sqlsrv_query($db,"SELECT CUST_CODE,CUST_ABBR FROM dbo.CUST WHERE CUST_CODE IN($in)");
    if($sc){while($rc=sqlsrv_fetch_array($sc,SQLSRV_FETCH_ASSOC))$custMap[trim($rc['CUST_CODE'])]=trim($rc['CUST_ABBR']);sqlsrv_free_stmt($sc);}
}

 $bomMap=array();
 $aik=array_keys($itemKeys);
if(count($aik)>0){
    $in="'".implode("','",array_map(function($c){return str_replace("'","''",$c);},$aik))."'";
    $qb="WITH LatestPO AS(SELECT pd.ITEM_ID,pd.POD_PRICE,po.PO_CUR,ROW_NUMBER() OVER(PARTITION BY pd.ITEM_ID ORDER BY po.PO_DATE DESC,po.PO_ID DESC) as rn FROM dbo.PO_DETAIL pd INNER JOIN dbo.PO po ON pd.PO_ID=po.PO_ID) SELECT PART.ITEM_CODE AS PART_CODE,MAT.ITEM_CODE AS MAT_CODE,MAT.ITEM_NAME AS MAT_NAME,MAT.ITTY_CODE,CAST(BD.QTY AS DECIMAL(38,8)) AS BOM_QTY,ISNULL(p.POD_PRICE,0) AS HARGA_PO,ISNULL(p.PO_CUR,'IDR') AS PO_CUR FROM dbo.BOM_DEFAULT BD INNER JOIN dbo.ITEMS PART ON BD.PART_ID=PART.ITEM_ID INNER JOIN dbo.ITEMS MAT ON BD.ITEM_ID=MAT.ITEM_ID LEFT JOIN LatestPO p ON MAT.ITEM_ID=p.ITEM_ID AND p.rn=1 WHERE PART.ITEM_CODE IN($in) AND MAT.ITTY_CODE IN('02','03','13') AND PART.ITEM_INACTIVE=0 AND MAT.ITEM_INACTIVE=0";
    $sb=@sqlsrv_query($db,$qb);
    if($sb){while($rb=sqlsrv_fetch_array($sb,SQLSRV_FETCH_ASSOC)){
        $pc=trim($rb['PART_CODE']);if(!isset($bomMap[$pc]))$bomMap[$pc]=array('materials'=>array(),'harga_part'=>0);
        $pr=num($rb['HARGA_PO']);if(trim($rb['PO_CUR'])==='USD')$pr*=$usdRate;
        $itty=trim($rb['ITTY_CODE']);$hmp=($itty==='02'||$itty==='13')?(num($rb['BOM_QTY'])*$pr)/1000.0:num($rb['BOM_QTY'])*$pr;
        $bomMap[$pc]['materials'][]=array('MAT_CODE'=>trim($rb['MAT_CODE']),'MAT_NAME'=>trim($rb['MAT_NAME']),'BOM_QTY'=>num($rb['BOM_QTY']),'ITTY_CODE'=>trim($rb['ITTY_CODE']),'HARGA_MAT_PER_PART'=>$hmp,'HARGA_ASLI_IDR'=>$pr);
        $bomMap[$pc]['harga_part']+=$hmp;
    }sqlsrv_free_stmt($sb);}
}

 $smsMap=array();
 $awk=array_keys($woKeys);
if(count($awk)>0){
    foreach(array_chunk($awk,500) as $chunk){
        $in="'".implode("','",array_map(function($c){return str_replace("'","''",$c);},$chunk))."'";
        $qs="SELECT
                    WO.WO_NUMBER,
                    SUM(CASE
                            WHEN LTRIM(RTRIM(MAT.ITTY_CODE))='02'
                             AND RIGHT(RTRIM(MAT.ITEM_CODE),2)='-0'
                            THEN ISNULL(SD.SMSD_QTY,0)
                            ELSE 0
                        END) AS VIRGIN_QTY,
                    SUM(CASE
                            WHEN LTRIM(RTRIM(MAT.ITTY_CODE))='02'
                             AND RIGHT(RTRIM(MAT.ITEM_CODE),2)<>'-0'
                            THEN ISNULL(SD.SMSD_QTY,0)
                            ELSE 0
                        END) AS CRUSHER_QTY,
                    SUM(CASE
                            WHEN LTRIM(RTRIM(MAT.ITTY_CODE))='13'
                            THEN ISNULL(SD.SMSD_QTY,0)
                            ELSE 0
                        END) AS PEWARNA_QTY
                FROM dbo.SMS
                INNER JOIN dbo.SMS_DETAIL SD ON SMS.SMS_ID=SD.SMS_ID
                INNER JOIN dbo.WO ON SMS.WO_ID=WO.WO_ID
                INNER JOIN dbo.ITEMS MAT ON SD.ITEM_ID=MAT.ITEM_ID
                WHERE WO.WO_NUMBER IN($in)
                  AND LTRIM(RTRIM(MAT.ITTY_CODE)) IN ('02','13')
                GROUP BY WO.WO_NUMBER";
        $ss=@sqlsrv_query($db,$qs);
        if($ss){
            while($rs=sqlsrv_fetch_array($ss,SQLSRV_FETCH_ASSOC)){
                $smsMap[trim($rs['WO_NUMBER'])]=array(
                    'VIRGIN_QTY'=>num($rs['VIRGIN_QTY']),
                    'CRUSHER_QTY'=>num($rs['CRUSHER_QTY']),
                    'PEWARNA_QTY'=>num($rs['PEWARNA_QTY'])
                );
            }
            sqlsrv_free_stmt($ss);
        }
    }
}

 $sumRows=array();
foreach($rawRows as $ri=>$r){
    $cust=isset($r['CUST_CODE'])?trim((string)$r['CUST_CODE']):'';
    $comp=isset($r['CUST_COMP'])?trim((string)$r['CUST_COMP']):'';
    $station=isset($r['MAG_STATION'])?trim((string)$r['MAG_STATION']):'';
    $machine=isset($r['MAC_CODE'])?trim((string)$r['MAC_CODE']):'';
    $itemCode=isset($r['ITEM_CODE'])?trim((string)$r['ITEM_CODE']):'';
    $itemName=isset($r['ITEM_NAME'])?trim((string)$r['ITEM_NAME']):'';
    $key=$station.'|'.$machine.'|'.$itemCode.'|'.$itemName.'|'.$cust.'|'.$comp;
    if(!isset($sumRows[$key])){
        $sumRows[$key]=$r;
        $sumRows[$key]['WO_QTY']=0;$sumRows[$key]['PD_OK']=0;$sumRows[$key]['PD_HO']=0;$sumRows[$key]['NG']=0;$sumRows[$key]['PD_QTY']=0;
        $sumRows[$key]['CAV_STD']=0;$sumRows[$key]['CAV_ACT']=0;$sumRows[$key]['CT_STD']=0;$sumRows[$key]['CT_ACT']=0;
        $sumRows[$key]['_WO_PLAN']=array();$sumRows[$key]['_WOS']=array();
        $sumRows[$key]['_CWS']=0;$sumRows[$key]['_CW']=0;$sumRows[$key]['_TWS']=0;$sumRows[$key]['_TW']=0;$sumRows[$key]['_REMARKS']=array();
    }
    $ok=num(isset($r['PD_OK'])?$r['PD_OK']:0);$hold=num(isset($r['PD_HO'])?$r['PD_HO']:0);$ng=num(isset($r['NG'])?$r['NG']:0);$to=$ok+$hold+$ng;
    $sumRows[$key]['PD_OK']+=$ok;$sumRows[$key]['PD_HO']+=$hold;$sumRows[$key]['NG']+=$ng;$sumRows[$key]['PD_QTY']+=num(isset($r['PD_QTY'])?$r['PD_QTY']:$to);
    $sumRows[$key]['CAV_STD']=positiveFirst($sumRows[$key]['CAV_STD'],isset($r['CAV_STD'])?$r['CAV_STD']:0);
    $sumRows[$key]['CT_STD']=positiveFirst($sumRows[$key]['CT_STD'],isset($r['CT_STD'])?$r['CT_STD']:0);
    $ca=num(isset($r['CAV_ACT'])?$r['CAV_ACT']:0);
    if($ca>0){$cw=$to>0?$to:1;$sumRows[$key]['_CWS']+=$ca*$cw;$sumRows[$key]['_CW']+=$cw;}
    $cta=num(isset($r['CT_ACT'])?$r['CT_ACT']:0);
    if($cta>0){$sf=($ca>0&&$to>0)?($to/$ca):$to;if($sf<=0)$sf=1;$sumRows[$key]['_TWS']+=$cta*$sf;$sumRows[$key]['_TW']+=$sf;}
    $wn=isset($r['WO_NUMBER'])?trim((string)$r['WO_NUMBER']):'';$wk=$wn!==''?$wn:('__R_'.$ri);
    if(!isset($sumRows[$key]['_WO_PLAN'][$wk]))$sumRows[$key]['_WO_PLAN'][$wk]=num(isset($r['WO_QTY'])?$r['WO_QTY']:0);
    if($wn!=='')$sumRows[$key]['_WOS'][$wn]=true;
    $rm=isset($r['PD_REM'])?trim((string)$r['PD_REM']):'';if($rm!=='')$sumRows[$key]['_REMARKS'][$rm]=true;
}

 $rows=array();$missingRateMachines=array();
foreach($sumRows as $r){
    foreach($r['_WO_PLAN'] as $qp)$r['WO_QTY']+=num($qp);
    $r['CAV_ACT']=num($r['_CW'])>0?num($r['_CWS'])/num($r['_CW']):0;
    $r['CT_ACT']=num($r['_TW'])>0?num($r['_TWS'])/num($r['_TW']):0;
    $r['OUTPUT_TOTAL']=num($r['PD_OK'])+num($r['PD_HO'])+num($r['NG']);
    $r['CT_DIFF']=num($r['CT_STD'])-num($r['CT_ACT']);
    $r['TOTAL_SHOOT']=num($r['CAV_ACT'])>0?num($r['OUTPUT_TOTAL'])/num($r['CAV_ACT']):0;
    $r['RUNNING_STD_HOUR']=(num($r['TOTAL_SHOOT'])*num($r['CT_STD']))/3600.0;
    $r['RUNNING_ACT_HOUR']=(num($r['TOTAL_SHOOT'])*num($r['CT_ACT']))/3600.0;
    $r['RUNNING_DIFF_HOUR']=num($r['RUNNING_STD_HOUR'])-num($r['RUNNING_ACT_HOUR']);
    $r['TOTAL_REDUCE_HOUR']=(num($r['TOTAL_SHOOT'])*num($r['CT_DIFF']))/3600.0;
    $r['RATE_MC_USD']=machineRateUsd($r,$machineRateUsdMap);$r['KURS_USD']=$usdRate;
    $r['RATE_MC_IDR']=num($r['RATE_MC_USD'])*num($r['KURS_USD']);
    $r['REDUCE_IDR']=num($r['TOTAL_REDUCE_HOUR'])*num($r['RATE_MC_IDR']);
    $r['MC_DISPLAY']=machineDisplay($r,$machineRateUsdMap);
    $r['PD_REM']=implode(' | ',array_keys($r['_REMARKS']));
    $ic=isset($r['ITEM_CODE'])?trim($r['ITEM_CODE']):'';$ccr=isset($r['CUST_CODE'])?trim($r['CUST_CODE']):'';
    $r['CUST_ABBR']=isset($custMap[$ccr])?$custMap[$ccr]:$ccr;
    if(isset($bomMap[$ic])){$r['BOM_MATERIALS']=$bomMap[$ic]['materials'];$r['HARGA_PART']=$bomMap[$ic]['harga_part'];}else{$r['BOM_MATERIALS']=array();$r['HARGA_PART']=0;}
    $r['ACTUAL_VIRGIN']=0;$r['ACTUAL_CRUSHER']=0;$r['ACTUAL_PEWARNA']=0;
    foreach($r['_WOS'] as $wo=>$v){
        if(isset($smsMap[$wo])){
            $r['ACTUAL_VIRGIN']+=$smsMap[$wo]['VIRGIN_QTY'];
            $r['ACTUAL_CRUSHER']+=$smsMap[$wo]['CRUSHER_QTY'];
            $r['ACTUAL_PEWARNA']+=$smsMap[$wo]['PEWARNA_QTY'];
        }
    }

    // --- APPLY NEW MATERIAL ACTUAL FORMULA ---
    $materialMetrics=decorateMaterialMetrics(
        $r['BOM_MATERIALS'],
        $r['WO_QTY'],
        $r['PD_QTY'] 
    );
    $r['BOM_MATERIALS']=$materialMetrics['materials'];
    $r['STD_MATERIAL_VIRGIN']=$materialMetrics['std_virgin'];
    $r['STD_MATERIAL_PEWARNA']=$materialMetrics['std_pewarna'];
    $r['STD_MATERIAL_TOTAL']=$materialMetrics['std_total'];
    $r['ACTUAL_MATERIAL_PL']=$materialMetrics['actual_pl'];
    $r['SELISIH_MATERIAL_PL']=$materialMetrics['difference'];
    $r['MATERIAL_PL_IDR']=$materialMetrics['profit_loss'];

    if(num($r['RATE_MC_USD'])<=0)$missingRateMachines[$r['MC_DISPLAY']]=true;
    unset($r['_WO_PLAN'],$r['_WOS'],$r['_CWS'],$r['_CW'],$r['_TWS'],$r['_TW'],$r['_REMARKS']);
    $rows[]=$r;
}
usort($rows,function($a,$b){
    $c=strnatcasecmp(isset($a['MC_DISPLAY'])?$a['MC_DISPLAY']:'',isset($b['MC_DISPLAY'])?$b['MC_DISPLAY']:'');if($c!==0)return $c;
    $c=strnatcasecmp(isset($a['ITEM_CODE'])?$a['ITEM_CODE']:'',isset($b['ITEM_CODE'])?$b['ITEM_CODE']:'');if($c!==0)return $c;
    return strnatcasecmp(isset($a['CUST_CODE'])?$a['CUST_CODE']:'',isset($b['CUST_CODE'])?$b['CUST_CODE']:'');
});

 $machineGroups=array();
 $grand=array('WO_QTY'=>0,'CAV_STD'=>0,'CAV_ACT'=>0,'CT_STD'=>0,'CT_ACT'=>0,'CT_DIFF'=>0,'PD_OK'=>0,'PD_HO'=>0,'NG'=>0,'OUTPUT_TOTAL'=>0,'TOTAL_SHOOT'=>0,'RUNNING_STD_HOUR'=>0,'RUNNING_ACT_HOUR'=>0,'RUNNING_DIFF_HOUR'=>0,'TOTAL_REDUCE_HOUR'=>0,'RATE_MC_USD'=>0,'REDUCE_IDR'=>0,'RATE_MC_IDR'=>0,'STD_MATERIAL_VIRGIN'=>0,'STD_MATERIAL_PEWARNA'=>0,'STD_MATERIAL_TOTAL'=>0,'ACTUAL_MATERIAL_PL'=>0,'SELISIH_MATERIAL_PL'=>0,'MATERIAL_PL_IDR'=>0,'ACTUAL_VIRGIN'=>0,'ACTUAL_CRUSHER'=>0,'ACTUAL_PEWARNA'=>0);
 $smk=array_keys($grand);
foreach($rows as $r){
    $mk=isset($r['MC_DISPLAY'])?$r['MC_DISPLAY']:'';
    if(!isset($machineGroups[$mk]))$machineGroups[$mk]=array('machine'=>$mk,'rows'=>array(),'total'=>$grand);
    $machineGroups[$mk]['rows'][]=$r;
    foreach($smk as $k){$machineGroups[$mk]['total'][$k]+=num(isset($r[$k])?$r[$k]:0);$grand[$k]+=num(isset($r[$k])?$r[$k]:0);}
}

/* ===== PREPARE SUMMARY + CHART DATA (TOP 10) ===== */
 $summaryAll = array();
 $totalSavings = 0; $totalLoss = 0;
 $lossPerMachine = array(); $savingsPerMachine = array();
 $lossCount = 0; $savingsCount = 0;

 /* --- Material Profit/Loss Metrics --- */
 $matTotalProfit = 0; $matTotalLoss = 0;
 $matPlPerMachine = array();

foreach ($rows as $r) {
    /* Summary Cycle Time */
    $reduce = num($r['REDUCE_IDR']);
    $mc = isset($r['MC_DISPLAY']) ? $r['MC_DISPLAY'] : '';
    $summaryAll[] = array(
        'mc' => $mc,
        'code' => isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : '',
        'item' => isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : '',
        'cust' => $r['CUST_ABBR'],
        'ct_std' => num($r['CT_STD']),
        'ct_act' => num($r['CT_ACT']),
        'ct_diff' => num($r['CT_DIFF']),
        'reduce' => $reduce
    );
    if ($reduce >= 0) {
        $totalSavings += $reduce; $savingsCount++;
        if (!isset($savingsPerMachine[$mc])) $savingsPerMachine[$mc] = 0;
        $savingsPerMachine[$mc] += $reduce;
    } else {
        $totalLoss += abs($reduce); $lossCount++;
        if (!isset($lossPerMachine[$mc])) $lossPerMachine[$mc] = 0;
        $lossPerMachine[$mc] += abs($reduce);
    }

    /* Summary Material Profit / Loss */
    $matPl = num($r['MATERIAL_PL_IDR']);
    if (!isset($matPlPerMachine[$mc])) {
        $matPlPerMachine[$mc] = array('profit' => 0, 'loss' => 0);
    }
    if ($matPl >= 0) {
        $matTotalProfit += $matPl;
        $matPlPerMachine[$mc]['profit'] += $matPl;
    } else {
        $matTotalLoss += abs($matPl);
        $matPlPerMachine[$mc]['loss'] += abs($matPl);
    }
}
 $netImpact = $totalSavings - $totalLoss;

 $summarySorted = $summaryAll;
usort($summarySorted, function($a, $b) { return $a['reduce'] - $b['reduce']; });

/* Top 10 losses (pakai ABSOLUT untuk chart) */
 $topLosses = array();
 $tmp = $summaryAll;
usort($tmp, function($a, $b) { return $a['reduce'] - $b['reduce']; });
 $topLosses = array_slice($tmp, 0, 10);
 $chartLossData = array();
foreach ($topLosses as $tl) {
    $chartLossData[] = array(
        'label' => mb_strlen($tl['item']) > 20 ? mb_substr($tl['item'], 0, 20) . '...' : $tl['item'],
        'value' => abs($tl['reduce'])
    );
}

/* Top 10 savings */
 $topSavings = array();
 $tmp2 = $summaryAll;
usort($tmp2, function($a, $b) { return $b['reduce'] - $a['reduce']; });
 $topSavings = array_slice($tmp2, 0, 10);
 $chartSavingsData = array();
foreach ($topSavings as $ts) {
    $chartSavingsData[] = array(
        'label' => mb_strlen($ts['item']) > 20 ? mb_substr($ts['item'], 0, 20) . '...' : $ts['item'],
        'value' => $ts['reduce']
    );
}

/* Loss per machine sorted desc, top 10 */
arsort($lossPerMachine);
 $chartMachineData = array();
 $i = 0;
foreach ($lossPerMachine as $mc => $val) {
    if ($i >= 10) break;
    $chartMachineData[] = array('label' => 'MC ' . $mc, 'value' => $val);
    $i++;
}

/* Prepare Material Machine Chart Data */
 $chartMatMachineLabels = array();
 $chartMatMachineProfit = array();
 $chartMatMachineLoss = array();
 $mcKeys = array_keys($matPlPerMachine);
 sort($mcKeys); // Sort by machine display alphabetical
 foreach ($mcKeys as $mc) {
    $chartMatMachineLabels[] = 'MC ' . $mc;
    $chartMatMachineProfit[] = $matPlPerMachine[$mc]['profit'];
    $chartMatMachineLoss[]   = $matPlPerMachine[$mc]['loss'];
 }

/* EXPORT XLS */
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $filename = 'Monitoring_Cycle_Time_' . $startDate . '_to_' . $endDate . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td,th{mso-number-format:\@;vertical-align:middle;font-size:9px;font-family:Arial,sans-serif;border:1px solid #999}th{background:#d9e1f2;font-weight:bold;text-align:center}.n{mso-number-format:\#\,\#\#0;text-align:right}.n2{mso-number-format:\#\,\#\#0\.00;text-align:right}.n3{mso-number-format:\#\,\#\#0\.000;text-align:right}.mc-total{background:#fff2cc;font-weight:bold}.grand{background:#1f4e78;color:#fff;font-weight:bold}.pos{color:#17823b;font-weight:bold}.neg{color:#c0392b;font-weight:bold}</style></head><body>';
    echo '<table cellspacing="0" cellpadding="3">';
    echo '<tr><td colspan="36" style="font-size:15px;font-weight:bold;text-align:center">MONITORING CYCLE TIME + MATERIAL</td></tr>';
    echo '<tr><td colspan="36" style="text-align:center">Periode '.xlsSafe(dateDisplay($startDate)).' s/d '.xlsSafe(dateDisplay($endDate)).' | KURS USD '.num($usdRate).'</td></tr>';
    echo '<tr><th rowspan="2">MC</th><th rowspan="2">CODE</th><th rowspan="2">ITEM</th><th rowspan="2">CUST ABBR</th><th rowspan="2">QTY WO</th><th colspan="2">CAV</th><th colspan="3">C/T (sec)</th><th colspan="4">PROD AKTUAL</th><th rowspan="2">TOTAL SHOOT</th><th colspan="3">MC RUNNING (HOUR)</th><th rowspan="2">TOTAL REDUCE (HOUR)</th><th rowspan="2">RATE M/C ($/HOUR)</th><th rowspan="2">REDUCE (Rp)</th><th rowspan="2">RATE M/C (Rp/HOUR)</th><th rowspan="2">KURS USD</th><th rowspan="2">HARGA PART (Rp)</th><th colspan="9">MATERIAL BOM</th><th colspan="3">ACTUAL (Kg)</th></tr>';
    echo '<tr><th>STD</th><th>ACT</th><th>STD</th><th>ACT</th><th>SELISIH</th><th>OK</th><th>HOLD</th><th>NG</th><th>TOTAL</th><th>STD</th><th>ACTUAL</th><th>SELISIH</th><th>MAT CODE</th><th>MAT NAME</th><th>BOM QTY</th><th>STD MATERIAL</th><th>ACTUAL MATERIAL</th><th>SELISIH</th><th>HARGA BOM (Rp)</th><th>HARGA ASLI (Rp)</th><th>PROFIT/LOSS (Rp)</th><th>VIRGIN</th><th>CRUSHER</th><th>PEWARNA</th></tr>';

    if (count($machineGroups) === 0) {
        echo '<tr><td colspan="36" style="text-align:center">Data tidak ditemukan.</td></tr>';
    } else {
        foreach ($machineGroups as $g) {
            foreach ($g['rows'] as $r) {
                $mats=$r['BOM_MATERIALS'];
                $rs=max(1,count($mats));
                echo '<tr>';
                echo '<td rowspan="'.$rs.'">'.xlsSafe($r['MC_DISPLAY']).'</td>';
                echo '<td rowspan="'.$rs.'">'.xlsSafe($r['ITEM_CODE']).'</td>';
                echo '<td rowspan="'.$rs.'">'.xlsSafe($r['ITEM_NAME']).'</td>';
                echo '<td rowspan="'.$rs.'">'.xlsSafe($r['CUST_ABBR']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['WO_QTY']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['CAV_STD']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['CAV_ACT']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['CT_STD']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['CT_ACT']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['CT_DIFF']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['PD_OK']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['PD_HO']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['NG']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['OUTPUT_TOTAL']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['TOTAL_SHOOT']).'</td>';
                echo '<td class="n3" rowspan="'.$rs.'">'.num($r['RUNNING_STD_HOUR']).'</td>';
                echo '<td class="n3" rowspan="'.$rs.'">'.num($r['RUNNING_ACT_HOUR']).'</td>';
                echo '<td class="n3" rowspan="'.$rs.'">'.num($r['RUNNING_DIFF_HOUR']).'</td>';
                echo '<td class="n3" rowspan="'.$rs.'">'.num($r['TOTAL_REDUCE_HOUR']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['RATE_MC_USD']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['REDUCE_IDR']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['RATE_MC_IDR']).'</td>';
                echo '<td class="n" rowspan="'.$rs.'">'.num($r['KURS_USD']).'</td>';
                echo '<td class="n2" rowspan="'.$rs.'">'.num($r['HARGA_PART']).'</td>';

                if (count($mats)>0) {
                    $first=true;
                    foreach ($mats as $m) {
                        if (!$first) echo '<tr>';
                        $plClass=num($m['MATERIAL_PL_IDR'])>=0?'pos':'neg';
                        echo '<td>'.xlsSafe($m['MAT_CODE']).'</td>';
                        echo '<td>'.xlsSafe($m['MAT_NAME']).'</td>';
                        echo '<td class="n3">'.num($m['BOM_QTY']).'</td>';
                        echo '<td class="n3">'.($m['PL_APPLIES']?num($m['STD_MATERIAL']):0).'</td>';
                        echo '<td class="n3">'.num($m['ACTUAL_MATERIAL']).'</td>';
                        echo '<td class="n3">'.($m['PL_APPLIES']?num($m['SELISIH_MATERIAL']):0).'</td>';
                        echo '<td class="n2">'.num($m['HARGA_MAT_PER_PART']).'</td>';
                        echo '<td class="n2">'.num($m['HARGA_ASLI_IDR']).'</td>';
                        echo '<td class="n '.$plClass.'">'.($m['PL_APPLIES']?num($m['MATERIAL_PL_IDR']):0).'</td>';
                        if ($first) {
                            echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_VIRGIN']).'</td>';
                            echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_CRUSHER']).'</td>';
                            echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_PEWARNA']).'</td>';
                        }
                        echo '</tr>';
                        $first=false;
                    }
                } else {
                    echo '<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>';
                    echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_VIRGIN']).'</td>';
                    echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_CRUSHER']).'</td>';
                    echo '<td class="n2" rowspan="'.$rs.'">'.num($r['ACTUAL_PEWARNA']).'</td></tr>';
                }
            }

            $t=$g['total'];
            echo '<tr class="mc-total"><td colspan="4">TOTAL MC '.xlsSafe($g['machine']).'</td>';
            echo '<td class="n">'.num($t['WO_QTY']).'</td><td class="n2">'.num($t['CAV_STD']).'</td><td class="n2">'.num($t['CAV_ACT']).'</td><td class="n2">'.num($t['CT_STD']).'</td><td class="n2">'.num($t['CT_ACT']).'</td><td class="n2">'.num($t['CT_DIFF']).'</td><td class="n">'.num($t['PD_OK']).'</td><td class="n">'.num($t['PD_HO']).'</td><td class="n">'.num($t['NG']).'</td><td class="n">'.num($t['OUTPUT_TOTAL']).'</td><td class="n2">'.num($t['TOTAL_SHOOT']).'</td><td class="n3">'.num($t['RUNNING_STD_HOUR']).'</td><td class="n3">'.num($t['RUNNING_ACT_HOUR']).'</td><td class="n3">'.num($t['RUNNING_DIFF_HOUR']).'</td><td class="n3">'.num($t['TOTAL_REDUCE_HOUR']).'</td><td class="n2">'.num($t['RATE_MC_USD']).'</td><td class="n">'.num($t['REDUCE_IDR']).'</td><td class="n">'.num($t['RATE_MC_IDR']).'</td><td></td><td></td>';
            echo '<td colspan="3"></td><td class="n3">'.num($t['STD_MATERIAL_TOTAL']).'</td><td class="n3">'.num($t['ACTUAL_MATERIAL_PL']).'</td><td class="n3">'.num($t['SELISIH_MATERIAL_PL']).'</td><td colspan="2"></td><td class="n">'.num($t['MATERIAL_PL_IDR']).'</td>';
            echo '<td class="n2">'.num($t['ACTUAL_VIRGIN']).'</td><td class="n2">'.num($t['ACTUAL_CRUSHER']).'</td><td class="n2">'.num($t['ACTUAL_PEWARNA']).'</td></tr>';
        }

        echo '<tr class="grand"><td colspan="4">GRAND TOTAL</td>';
        echo '<td class="n">'.num($grand['WO_QTY']).'</td><td class="n2">'.num($grand['CAV_STD']).'</td><td class="n2">'.num($grand['CAV_ACT']).'</td><td class="n2">'.num($grand['CT_STD']).'</td><td class="n2">'.num($grand['CT_ACT']).'</td><td class="n2">'.num($grand['CT_DIFF']).'</td><td class="n">'.num($grand['PD_OK']).'</td><td class="n">'.num($grand['PD_HO']).'</td><td class="n">'.num($grand['NG']).'</td><td class="n">'.num($grand['OUTPUT_TOTAL']).'</td><td class="n2">'.num($grand['TOTAL_SHOOT']).'</td><td class="n3">'.num($grand['RUNNING_STD_HOUR']).'</td><td class="n3">'.num($grand['RUNNING_ACT_HOUR']).'</td><td class="n3">'.num($grand['RUNNING_DIFF_HOUR']).'</td><td class="n3">'.num($grand['TOTAL_REDUCE_HOUR']).'</td><td class="n2">'.num($grand['RATE_MC_USD']).'</td><td class="n">'.num($grand['REDUCE_IDR']).'</td><td class="n">'.num($grand['RATE_MC_IDR']).'</td><td></td><td></td>';
        echo '<td colspan="3"></td><td class="n3">'.num($grand['STD_MATERIAL_TOTAL']).'</td><td class="n3">'.num($grand['ACTUAL_MATERIAL_PL']).'</td><td class="n3">'.num($grand['SELISIH_MATERIAL_PL']).'</td><td colspan="2"></td><td class="n">'.num($grand['MATERIAL_PL_IDR']).'</td>';
        echo '<td class="n2">'.num($grand['ACTUAL_VIRGIN']).'</td><td class="n2">'.num($grand['ACTUAL_CRUSHER']).'</td><td class="n2">'.num($grand['ACTUAL_PEWARNA']).'</td></tr>';
    }

    echo '</table></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoring Cycle Time + Material BOM</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body{background:#eef1f5;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;font-size:12px;padding-bottom:30px}
        .panel-filter .panel-heading{padding:8px 15px;background-color:#2c3e50;border-color:#2c3e50}
        .panel-filter .panel-title{font-size:14px;font-weight:600;color:#fff;margin:0}
        .panel-filter .panel-body{padding:12px 15px;background:#fff}
        .filter-group{margin-right:6px;margin-bottom:5px}
        .filter-group label{display:block;font-size:10px;font-weight:600;color:#555;margin-bottom:2px}
        .filter-group .form-control{height:32px;font-size:11px;padding:4px 8px;border-radius:3px}
        .btn-action{height:32px;font-size:11px;padding:4px 12px;border-radius:3px;margin-right:4px}
        .info-bar{background:#fff;border:1px solid #dce1e8;border-radius:4px;padding:10px 15px;margin-bottom:10px;font-size:12px;line-height:1.8}
        .info-bar strong{color:#2c3e50}
        .warning-box{background:#fdf6ec;border:1px solid #f0c36d;color:#8a6d3b;padding:8px 12px;margin-bottom:10px;border-radius:4px;font-size:11px}
        .table-container{background:#fff;border:1px solid #c8ced6;border-radius:4px;overflow:hidden;margin-bottom:15px}
        .table-scroll{overflow-x:auto;overflow-y:visible}
        .report-table{width:100%;min-width:2950px;border-collapse:separate;border-spacing:0;margin:0;font-size:10px;line-height:1.4}
        .report-table thead th{background:#3a5ba0;color:#fff;border:1px solid #2c4a87;border-top:none;padding:5px 4px;text-align:center;vertical-align:middle;white-space:nowrap;font-weight:600;font-size:9px}
        .report-table thead tr:nth-child(2) th{background:#4a6db5;border-top:1px solid #5a7dc5;font-size:8px}
        .report-table tbody td{border:1px solid #dce1e8;border-top:none;padding:4px 5px;vertical-align:middle;background:#fff}
        .report-table tbody tr.data-row td{border-top:1px solid #e8ecf1}
        .report-table tbody tr.data-row:nth-child(even) td{background:#f6f8fb}
        .report-table tbody tr.data-row:hover td{background:#e3edf7!important}
        .report-table tbody tr.mc-total td{background:#fff8e1!important;font-weight:700;border-top:2px solid #e6c84b;border-bottom:2px solid #e6c84b;color:#5d4e00}
        .report-table tbody tr.grand-total td{background:#1a3a6b!important;color:#fff!important;font-weight:700;border-top:3px solid #0f2548;border-bottom:3px solid #0f2548}
        .t-r{text-align:right;white-space:nowrap}.t-c{text-align:center;white-space:nowrap}.t-l{text-align:left}
        .c-pos{color:#1b8c3e;font-weight:700}.c-neg{color:#c0392b;font-weight:700}.c-blue{color:#2471a3;font-weight:600}
        .col-mc{width:55px;min-width:55px;max-width:55px}.col-code{width:95px;min-width:95px;max-width:95px}.col-item{width:200px;min-width:200px;max-width:200px}
        .col-cust{width:70px;min-width:70px;max-width:70px}.col-sm{width:52px;min-width:52px;max-width:52px}.col-hr{width:62px;min-width:62px;max-width:62px}
        .col-mny{width:85px;min-width:85px;max-width:85px}.col-mat{width:105px;min-width:105px;max-width:105px}
        .col-item,.col-code,.col-mat{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .sk1{position:sticky;left:0;z-index:5}.sk2{position:sticky;left:55px;z-index:5}.sk3{position:sticky;left:150px;z-index:5}
        .report-table thead th.sk1,.report-table thead th.sk2,.report-table thead th.sk3{z-index:10}
        .report-table thead th{z-index:3}
        .sk1,.sk2,.sk3{-webkit-transform:translateZ(0);transform:translateZ(0);will-change:left;-webkit-backface-visibility:hidden;backface-visibility:hidden}
        .report-table tbody td.sk1{background:#fff!important}.report-table tbody td.sk2{background:#fff!important}.report-table tbody td.sk3{background:#fff!important}
        .report-table tbody tr.data-row:nth-child(even) td.sk1{background:#f6f8fb!important}.report-table tbody tr.data-row:nth-child(even) td.sk2{background:#f6f8fb!important}.report-table tbody tr.data-row:nth-child(even) td.sk3{background:#f6f8fb!important}
        .report-table tbody tr.data-row:hover td.sk1{background:#e3edf7!important}.report-table tbody tr.data-row:hover td.sk2{background:#e3edf7!important}.report-table tbody tr.data-row:hover td.sk3{background:#e3edf7!important}
        .report-table tbody tr.mc-total td.sk1{background:#fff8e1!important}.report-table tbody tr.mc-total td.sk2{background:#fff8e1!important}.report-table tbody tr.mc-total td.sk3{background:#fff8e1!important}
        .report-table tbody tr.grand-total td.sk1{background:#1a3a6b!important;color:#fff!important}.report-table tbody tr.grand-total td.sk2{background:#1a3a6b!important;color:#fff!important}.report-table tbody tr.grand-total td.sk3{background:#1a3a6b!important;color:#fff!important}
        .report-table thead th.sk1{background:#3a5ba0!important}.report-table thead th.sk2{background:#3a5ba0!important}.report-table thead th.sk3{background:#3a5ba0!important}
        .report-table thead tr:nth-child(2) th.sk1{background:#4a6db5!important}.report-table thead tr:nth-child(2) th.sk2{background:#4a6db5!important}.report-table thead tr:nth-child(2) th.sk3{background:#4a6db5!important}
        .sk3::after{content:'';position:absolute;top:0;right:-4px;bottom:0;width:4px;background:#3a5ba0;z-index:11;pointer-events:none}
        .report-table thead tr:nth-child(2) th.sk3::after{background:#4a6db5}.report-table tbody tr.mc-total td.sk3::after{background:#e6c84b}.report-table tbody tr.grand-total td.sk3::after{background:#0f2548}
        .ac-wrap{position:relative}.ac-wrap .form-control{padding-right:28px}
        .ac-clear-btn{position:absolute;right:6px;top:22px;width:20px;height:20px;line-height:20px;text-align:center;cursor:pointer;color:#aaa;font-size:14px;font-weight:700;z-index:2;border-radius:50%;display:none;background:#eee;transition:all .15s}
        .ac-clear-btn:hover{background:#c0392b;color:#fff}.ac-clear-btn.visible{display:block}
        .ac-dropdown{display:none;position:absolute;top:100%;left:0;min-width:380px;max-width:520px;max-height:300px;overflow-y:auto;overflow-x:hidden;background:#fff;border:1px solid #b0bec5;border-top:2px solid #3a5ba0;border-radius:0 0 5px 5px;box-shadow:0 6px 18px rgba(0,0,0,.18);z-index:9999;margin-top:-1px}
        .ac-dropdown.ac-loading{padding:12px 15px;color:#888;font-size:11px;text-align:center}
        .ac-dropdown.ac-empty{padding:12px 15px;color:#999;font-size:11px;text-align:center}
        .ac-item{display:flex;align-items:center;padding:7px 10px;cursor:pointer;border-bottom:1px solid #f0f2f5;transition:background .1s}
        .ac-item:last-child{border-bottom:none}.ac-item:hover,.ac-item-active{background:#e8f0fe!important}
        .ac-item-code{font-weight:700;color:#1a3a6b;min-width:75px;max-width:100px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:11px;flex-shrink:0}
        .ac-item-sep{color:#ccc;margin:0 6px;flex-shrink:0;font-size:10px}
        .ac-item-comp{flex:1;color:#444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:11px;min-width:0}
        .ac-item-abbr{background:#e0e7ef;color:#3a5ba0;padding:2px 7px;border-radius:3px;font-size:9px;font-weight:700;white-space:nowrap;flex-shrink:0;margin-left:6px}
        .ac-item-active .ac-item-abbr{background:#3a5ba0;color:#fff}.ac-item-active .ac-item-code{color:#0f2548}
        .ac-hl{background:#ffd54f;color:#333;border-radius:2px;padding:0 1px}

        /* ===== SUMMARY ===== */
        .summary-panel .panel-heading{padding:10px 15px;background:#8e44ad;border-color:#8e44ad}
        .summary-panel .panel-title{font-size:15px;font-weight:700;color:#fff;margin:0}
        .kpi-card{border-radius:6px;padding:15px 20px;text-align:center;margin-bottom:15px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .kpi-card .kpi-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
        .kpi-card .kpi-value{font-size:20px;font-weight:700}
        .kpi-card .kpi-sub{font-size:10px;margin-top:4px;opacity:.8}
        .kpi-savings{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff}
        .kpi-loss{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff}
        .kpi-net{background:linear-gradient(135deg,#2c3e50,#34495e);color:#fff}
        .kpi-items{background:linear-gradient(135deg,#2980b9,#3498db);color:#fff}
        .summary-table{width:100%;border-collapse:collapse;font-size:10px}
        .summary-table thead th{background:#9b59b6;color:#fff;padding:6px 5px;text-align:center;white-space:nowrap;font-size:9px;font-weight:600;border:1px solid #7d3c98}
        .summary-table tbody td{padding:4px 5px;border:1px solid #e0e0e0;vertical-align:middle}
        .summary-table tbody tr:nth-child(even) td{background:#f9f5fc}
        .summary-table tbody tr.row-loss td{background:#fdecea}
        .summary-table tbody tr.row-loss:nth-child(even) td{background:#f9e0de}
        .summary-table tbody tr.row-save td{background:#eafaf1}
        .summary-table tbody tr.row-save:nth-child(even) td{background:#d5f5e3}
        .summary-table tbody tr.summary-total td{background:#8e44ad!important;color:#fff!important;font-weight:700;font-size:11px}
        .summary-scroll{overflow-x:auto;max-height:500px;overflow-y:auto}
        .chart-box{background:#fff;border:1px solid #c8ced6;border-radius:6px;padding:15px;margin-bottom:15px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .chart-box h5{margin:0 0 12px;font-size:12px;font-weight:700;color:#2c3e50;padding-bottom:8px;border-bottom:2px solid #eee}
        .chart-box canvas{max-height:320px}
        .formula-note{margin-top:10px;color:#777;font-size:10px;line-height:1.6}
        @page{size:A3 landscape;margin:5mm}
        @media print{
            body{background:#fff!important;padding:0!important;font-size:8px}
            .panel-filter,.warning-box,.formula-note,.btn-action,.summary-panel{display:none!important}
            .table-container{border:none!important;border-radius:0!important}
            .table-scroll{overflow:visible!important}
            .report-table{min-width:0!important;font-size:6.5px}
            .sk1,.sk2,.sk3{position:static!important;transform:none!important;will-change:auto!important;backface-visibility:visible!important}
            .sk3::after{display:none!important}.info-bar{border:none;padding:3px 0;margin-bottom:5px}
        }
    </style>
</head>
<body>
<div class="container-fluid" style="min-width:1200px;padding-top:10px;">

    <div class="panel panel-default panel-filter">
        <div class="panel-heading"><h4 class="panel-title"><span class="glyphicon glyphicon-filter"></span> Filter Monitoring Cycle Time</h4></div>
        <div class="panel-body">
            <form class="form-inline" method="get" action="" id="filterForm">
                <div class="form-group filter-group"><label>Dari Tanggal</label><input type="date" class="form-control" name="start_date" value="<?php echo h($startDate); ?>"></div>
                <div class="form-group filter-group"><label>Ke Tanggal</label><input type="date" class="form-control" name="end_date" value="<?php echo h($endDate); ?>"></div>
                <div class="form-group filter-group"><label>Item Code</label><input type="text" class="form-control" name="code" value="<?php echo h($code === '%' ? '' : $code); ?>" placeholder="Ketik item..."></div>
                <div class="form-group filter-group ac-wrap" id="acCustWrap"><label>Customer</label><input type="text" class="form-control" id="fCust" name="cust_code" value="<?php echo h($custCode === '%' ? '' : $custCode); ?>" placeholder="Ketik customer..." autocomplete="off" spellcheck="false"><span class="ac-clear-btn" id="acCustClear">&times;</span><div class="ac-dropdown" id="acCustDrop"></div></div>
                <div class="form-group filter-group" style="width:95px"><label>Kurs USD</label><input type="text" class="form-control" name="usd_rate" value="<?php echo h($usdRate > 0 ? number_format($usdRate, 0, '.', '') : ''); ?>"></div>
                <div class="form-group filter-group"><label>&nbsp;</label><div>
                    <button type="submit" class="btn btn-primary btn-action"><span class="glyphicon glyphicon-search"></span> Tampilkan</button>
                    <button type="button" class="btn btn-default btn-action" onclick="window.print();"><span class="glyphicon glyphicon-print"></span> Print</button>
                    <a class="btn btn-success btn-action" href="?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&code=<?php echo urlencode($code); ?>&cust_code=<?php echo urlencode($custCode); ?>&usd_rate=<?php echo $usdRate; ?>&export=xls"><span class="glyphicon glyphicon-download"></span> XLS</a>
                </div></div>
            </form>
        </div>
    </div>

    <div class="info-bar"><div class="row"><div class="col-md-12">
        <strong>MONITORING CYCLE TIME + MATERIAL BOM</strong>
        <span class="label label-info" style="margin-left:8px">SUM TANPA LOT</span>
        <span class="pull-right">Periode: <strong><?php echo h(dateDisplay($startDate)); ?></strong> s/d <strong><?php echo h(dateDisplay($endDate)); ?></strong> &nbsp;&bull;&nbsp; Kurs USD: <strong><?php echo fmt0($usdRate); ?></strong> &nbsp;&bull;&nbsp; Data: <strong><?php echo count($rows); ?></strong> baris &nbsp;&bull;&nbsp; Mesin: <strong><?php echo count($machineGroups); ?></strong></span>
    </div></div></div>

    <?php if ($usdRate <= 0): ?><div class="alert alert-warning warning-box"><span class="glyphicon glyphicon-exclamation-sign"></span> <strong>Perhatian:</strong> Kurs USD tidak ditemukan.</div><?php endif; ?>
    <?php if (count($missingRateMachines) > 0): ?><div class="alert alert-warning warning-box"><span class="glyphicon glyphicon-exclamation-sign"></span> <strong>Rate USD/jam belum tersedia</strong> untuk mesin: <strong><?php echo h(implode(', ', array_keys($missingRateMachines))); ?></strong></div><?php endif; ?>

    <div class="table-container"><div class="table-scroll">
        <table class="report-table">
            <thead><tr>
                <th class="col-mc sk1" rowspan="2">MC</th><th class="col-code sk2" rowspan="2">CODE</th><th class="col-item sk3" rowspan="2">ITEM</th>
                <th class="col-cust" rowspan="2">CUST<br>ABBR</th><th class="col-sm" rowspan="2">QTY<br>WO</th>
                <th class="col-sm" colspan="2">CAV</th><th class="col-sm" colspan="3">C/T (sec)</th><th class="col-sm" colspan="4">PROD AKTUAL</th><th class="col-sm" rowspan="2">TOTAL<br>SHOOT</th>
                <th class="col-hr" colspan="3">MC RUNNING (HOUR)</th><th class="col-hr" rowspan="2">TOTAL<br>REDUCE</th><th class="col-sm" rowspan="2">RATE<br>($/H)</th>
                <th class="col-mny" rowspan="2">REDUCE (Rp)</th><th class="col-mny" rowspan="2">RATE<br>(Rp/H)</th><th class="col-mny" rowspan="2">KURS<br>USD</th><th class="col-mny" rowspan="2">HARGA<br>PART (Rp)</th>
                <th class="col-mat" colspan="9">MATERIAL BOM</th><th class="col-sm" colspan="3">ACTUAL (Kg)</th>
            </tr><tr>
                <th>STD</th><th>ACT</th><th>STD</th><th>ACT</th><th>SELISIH</th><th>OK</th><th>HOLD</th><th>NG</th><th>TOTAL</th><th>STD</th><th>ACTUAL</th><th>SELISIH</th>
                <th>MAT CODE</th><th>MAT NAME</th><th>BOM QTY</th><th>STD<br>MATERIAL</th><th>ACTUAL<br>MATERIAL</th><th>SELISIH</th><th>HARGA BOM (Rp)</th><th>HARGA ASLI (Rp)</th><th>PROFIT/LOSS (Rp)</th><th>VIRGIN</th><th>CRUSHER</th><th>PEWARNA</th>
            </tr></thead>
            <tbody>
            <?php if (count($machineGroups) === 0): ?>
                <tr><td colspan="36" class="t-c" style="padding:30px;color:#999;font-size:13px"><span class="glyphicon glyphicon-info-sign"></span> Data tidak ditemukan.</td></tr>
            <?php else: ?>
                <?php foreach ($machineGroups as $g): ?>
                    <?php foreach ($g['rows'] as $r): ?>
                        <?php
                            $diffCls=num($r['TOTAL_REDUCE_HOUR'])>=0?'c-pos':'c-neg';
                            $ctCls=num($r['CT_DIFF'])>=0?'c-pos':'c-neg';
                            $mats=$r['BOM_MATERIALS'];
                            $rowspan=max(1,count($mats));
                            $itemTitle=isset($r['ITEM_NAME'])?h($r['ITEM_NAME']):'';
                            $codeTitle=h(isset($r['ITEM_CODE'])?$r['ITEM_CODE']:'');
                        ?>
                        <tr class="data-row">
                            <td class="t-c sk1" rowspan="<?php echo $rowspan; ?>"><?php echo h($r['MC_DISPLAY']); ?></td>
                            <td class="t-l sk2 col-code" rowspan="<?php echo $rowspan; ?>" title="<?php echo $codeTitle; ?>"><?php echo $codeTitle; ?></td>
                            <td class="t-l sk3 col-item" rowspan="<?php echo $rowspan; ?>" title="<?php echo $itemTitle; ?>"><?php echo $itemTitle; ?></td>
                            <td class="t-c" rowspan="<?php echo $rowspan; ?>"><?php echo h($r['CUST_ABBR']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['WO_QTY']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['CAV_STD']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['CAV_ACT']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['CT_STD']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['CT_ACT']); ?></td>
                            <td class="t-r <?php echo $ctCls; ?>" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['CT_DIFF']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['PD_OK']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['PD_HO']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['NG']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['OUTPUT_TOTAL']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['TOTAL_SHOOT']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt3($r['RUNNING_STD_HOUR']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt3($r['RUNNING_ACT_HOUR']); ?></td>
                            <td class="t-r <?php echo $diffCls; ?>" rowspan="<?php echo $rowspan; ?>"><?php echo fmt3($r['RUNNING_DIFF_HOUR']); ?></td>
                            <td class="t-r <?php echo $diffCls; ?>" rowspan="<?php echo $rowspan; ?>"><?php echo fmt3($r['TOTAL_REDUCE_HOUR']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['RATE_MC_USD']); ?></td>
                            <td class="t-r <?php echo $diffCls; ?>" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['REDUCE_IDR']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['RATE_MC_IDR']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt0($r['KURS_USD']); ?></td>
                            <td class="t-r c-blue" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['HARGA_PART']); ?></td>

                            <?php if (count($mats)>0): $m=$mats[0]; $matPlCls=num($m['MATERIAL_PL_IDR'])>=0?'c-pos':'c-neg'; ?>
                                <td class="t-l col-mat" title="<?php echo h($m['MATERIAL_CATEGORY']); ?>"><?php echo h($m['MAT_CODE']);?></td>
                                <td class="t-l col-mat" title="<?php echo h($m['MAT_NAME']);?>"><?php echo h($m['MAT_NAME']);?></td>
                                <td class="t-r"><?php echo fmt3($m['BOM_QTY']);?></td>
                                <td class="t-r"><?php echo $m['PL_APPLIES']?fmt3($m['STD_MATERIAL']):'-';?></td>
                                <td class="t-r"><?php echo fmt3($m['ACTUAL_MATERIAL']);?></td>
                                <td class="t-r <?php echo $m['PL_APPLIES']?(num($m['SELISIH_MATERIAL'])>=0?'c-pos':'c-neg'):''; ?>"><?php echo $m['PL_APPLIES']?fmt3($m['SELISIH_MATERIAL']):'-';?></td>
                                <td class="t-r c-blue"><?php echo fmt2($m['HARGA_MAT_PER_PART']);?></td>
                                <td class="t-r"><?php echo fmt2($m['HARGA_ASLI_IDR']);?></td>
                                <td class="t-r <?php echo $matPlCls; ?>"><?php echo $m['PL_APPLIES']?fmt0($m['MATERIAL_PL_IDR']):'-';?></td>
                            <?php else: ?>
                                <td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td><td class="t-c">-</td>
                            <?php endif; ?>

                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['ACTUAL_VIRGIN']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['ACTUAL_CRUSHER']); ?></td>
                            <td class="t-r" rowspan="<?php echo $rowspan; ?>"><?php echo fmt2($r['ACTUAL_PEWARNA']); ?></td>
                        </tr>

                        <?php for($i=1;$i<count($mats);$i++): $m=$mats[$i]; $matPlCls=num($m['MATERIAL_PL_IDR'])>=0?'c-pos':'c-neg'; ?>
                            <tr class="data-row">
                                <td class="t-l col-mat" title="<?php echo h($m['MATERIAL_CATEGORY']); ?>"><?php echo h($m['MAT_CODE']);?></td>
                                <td class="t-l col-mat" title="<?php echo h($m['MAT_NAME']);?>"><?php echo h($m['MAT_NAME']);?></td>
                                <td class="t-r"><?php echo fmt3($m['BOM_QTY']);?></td>
                                <td class="t-r"><?php echo $m['PL_APPLIES']?fmt3($m['STD_MATERIAL']):'-';?></td>
                                <td class="t-r"><?php echo fmt3($m['ACTUAL_MATERIAL']);?></td>
                                <td class="t-r <?php echo $m['PL_APPLIES']?(num($m['SELISIH_MATERIAL'])>=0?'c-pos':'c-neg'):''; ?>"><?php echo $m['PL_APPLIES']?fmt3($m['SELISIH_MATERIAL']):'-';?></td>
                                <td class="t-r c-blue"><?php echo fmt2($m['HARGA_MAT_PER_PART']);?></td>
                                <td class="t-r"><?php echo fmt2($m['HARGA_ASLI_IDR']);?></td>
                                <td class="t-r <?php echo $matPlCls; ?>"><?php echo $m['PL_APPLIES']?fmt0($m['MATERIAL_PL_IDR']):'-';?></td>
                            </tr>
                        <?php endfor; ?>
                    <?php endforeach; ?>

                    <?php $t=$g['total']; $matTotalCls=num($t['MATERIAL_PL_IDR'])>=0?'c-pos':'c-neg'; ?>
                    <tr class="mc-total">
                        <td class="t-l sk1">TOTAL MC <?php echo h($g['machine']);?></td><td class="sk2"></td><td class="sk3"></td><td></td>
                        <td class="t-r"><?php echo fmt0($t['WO_QTY']);?></td><td class="t-r"><?php echo fmt2($t['CAV_STD']);?></td><td class="t-r"><?php echo fmt2($t['CAV_ACT']);?></td><td class="t-r"><?php echo fmt2($t['CT_STD']);?></td><td class="t-r"><?php echo fmt2($t['CT_ACT']);?></td><td class="t-r"><?php echo fmt2($t['CT_DIFF']);?></td><td class="t-r"><?php echo fmt0($t['PD_OK']);?></td><td class="t-r"><?php echo fmt0($t['PD_HO']);?></td><td class="t-r"><?php echo fmt0($t['NG']);?></td><td class="t-r"><?php echo fmt0($t['OUTPUT_TOTAL']);?></td><td class="t-r"><?php echo fmt2($t['TOTAL_SHOOT']);?></td><td class="t-r"><?php echo fmt3($t['RUNNING_STD_HOUR']);?></td><td class="t-r"><?php echo fmt3($t['RUNNING_ACT_HOUR']);?></td><td class="t-r"><?php echo fmt3($t['RUNNING_DIFF_HOUR']);?></td><td class="t-r"><?php echo fmt3($t['TOTAL_REDUCE_HOUR']);?></td><td class="t-r"><?php echo fmt2($t['RATE_MC_USD']);?></td><td class="t-r"><?php echo fmt0($t['REDUCE_IDR']);?></td><td class="t-r"><?php echo fmt0($t['RATE_MC_IDR']);?></td><td></td><td></td>
                        <td colspan="3"></td><td class="t-r"><?php echo fmt3($t['STD_MATERIAL_TOTAL']);?></td><td class="t-r"><?php echo fmt3($t['ACTUAL_MATERIAL_PL']);?></td><td class="t-r"><?php echo fmt3($t['SELISIH_MATERIAL_PL']);?></td><td colspan="2"></td><td class="t-r <?php echo $matTotalCls; ?>"><?php echo fmt0($t['MATERIAL_PL_IDR']);?></td>
                        <td class="t-r"><?php echo fmt2($t['ACTUAL_VIRGIN']);?></td><td class="t-r"><?php echo fmt2($t['ACTUAL_CRUSHER']);?></td><td class="t-r"><?php echo fmt2($t['ACTUAL_PEWARNA']);?></td>
                    </tr>
                <?php endforeach; ?>

                <?php $grandMatCls=num($grand['MATERIAL_PL_IDR'])>=0?'c-pos':'c-neg'; ?>
                <tr class="grand-total">
                    <td class="t-l sk1">GRAND TOTAL</td><td class="sk2"></td><td class="sk3"></td><td></td>
                    <td class="t-r"><?php echo fmt0($grand['WO_QTY']);?></td><td class="t-r"><?php echo fmt2($grand['CAV_STD']);?></td><td class="t-r"><?php echo fmt2($grand['CAV_ACT']);?></td><td class="t-r"><?php echo fmt2($grand['CT_STD']);?></td><td class="t-r"><?php echo fmt2($grand['CT_ACT']);?></td><td class="t-r"><?php echo fmt2($grand['CT_DIFF']);?></td><td class="t-r"><?php echo fmt0($grand['PD_OK']);?></td><td class="t-r"><?php echo fmt0($grand['PD_HO']);?></td><td class="t-r"><?php echo fmt0($grand['NG']);?></td><td class="t-r"><?php echo fmt0($grand['OUTPUT_TOTAL']);?></td><td class="t-r"><?php echo fmt2($grand['TOTAL_SHOOT']);?></td><td class="t-r"><?php echo fmt3($grand['RUNNING_STD_HOUR']);?></td><td class="t-r"><?php echo fmt3($grand['RUNNING_ACT_HOUR']);?></td><td class="t-r"><?php echo fmt3($grand['RUNNING_DIFF_HOUR']);?></td><td class="t-r"><?php echo fmt3($grand['TOTAL_REDUCE_HOUR']);?></td><td class="t-r"><?php echo fmt2($grand['RATE_MC_USD']);?></td><td class="t-r"><?php echo fmt0($grand['REDUCE_IDR']);?></td><td class="t-r"><?php echo fmt0($grand['RATE_MC_IDR']);?></td><td></td><td></td>
                    <td colspan="3"></td><td class="t-r"><?php echo fmt3($grand['STD_MATERIAL_TOTAL']);?></td><td class="t-r"><?php echo fmt3($grand['ACTUAL_MATERIAL_PL']);?></td><td class="t-r"><?php echo fmt3($grand['SELISIH_MATERIAL_PL']);?></td><td colspan="2"></td><td class="t-r <?php echo $grandMatCls; ?>"><?php echo fmt0($grand['MATERIAL_PL_IDR']);?></td>
                    <td class="t-r"><?php echo fmt2($grand['ACTUAL_VIRGIN']);?></td><td class="t-r"><?php echo fmt2($grand['ACTUAL_CRUSHER']);?></td><td class="t-r"><?php echo fmt2($grand['ACTUAL_PEWARNA']);?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>

    <!-- ===== SUMMARY LOSS + CHARTS ===== -->
    <div class="panel panel-default summary-panel" style="margin-top:20px">
        <div class="panel-heading"><h4 class="panel-title"><span class="glyphicon glyphicon-stats"></span> SUMMARY CYCLE TIME & MATERIAL BOM</h4></div>
        <div class="panel-body">
            <div class="row" style="margin-bottom:20px">
                <div class="col-md-3"><div class="kpi-card kpi-savings"><div class="kpi-label">Total CT Savings</div><div class="kpi-value"><?php echo 'Rp '.number_format($totalSavings,0,',','.');?></div><div class="kpi-sub"><?php echo $savingsCount;?> item efisien</div></div></div>
                <div class="col-md-3"><div class="kpi-card kpi-loss"><div class="kpi-label">Total CT Loss</div><div class="kpi-value">(<?php echo number_format($totalLoss,0,',','.');?>)</div><div class="kpi-sub"><?php echo $lossCount;?> item loss</div></div></div>
                <div class="col-md-3"><div class="kpi-card kpi-net"><div class="kpi-label">CT Net Impact</div><div class="kpi-value"><?php echo $netImpact>=0?'Rp '.number_format($netImpact,0,',','.'):'('.number_format(abs($netImpact),0,',','.').')';?></div><div class="kpi-sub"><?php echo $netImpact>=0?'POSITIF':'NEGATIF';?></div></div></div>
                <div class="col-md-3"><div class="kpi-card kpi-items"><div class="kpi-label">Total Items</div><div class="kpi-value"><?php echo count($rows);?></div><div class="kpi-sub"><?php echo count($machineGroups);?> mesin</div></div></div>
            </div>
            <div class="summary-scroll" style="margin-bottom:20px">
                <table class="summary-table"><thead><tr>
                    <th style="width:30px">No</th><th style="width:50px">MC</th><th style="width:90px">CODE</th><th style="width:180px">ITEM</th><th style="width:60px">CUST</th><th style="width:65px">C/T STD</th><th style="width:65px">C/T ACT</th><th style="width:65px">SELISIH</th><th style="width:120px">REDUCE (Rp)</th>
                </tr></thead><tbody>
                <?php if(count($summarySorted)===0):?><tr><td colspan="9" class="t-c" style="padding:20px;color:#999">Tidak ada data.</td></tr>
                <?php else:$no=1;$sumReduce=0;foreach($summarySorted as $s):$isLoss=$s['reduce']<0;$sumReduce+=$s['reduce'];?>
                    <tr class="<?php echo $isLoss?'row-loss':'row-save';?>">
                        <td class="t-c"><?php echo $no++;?></td>
                        <td class="t-c"><?php echo h($s['mc']);?></td>
                        <td class="t-l"><?php echo h($s['code']);?></td>
                        <td class="t-l" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px" title="<?php echo h($s['item']);?>"><?php echo h($s['item']);?></td>
                        <td class="t-c"><?php echo h($s['cust']);?></td>
                        <td class="t-r"><?php echo fmt2($s['ct_std']);?></td>
                        <td class="t-r"><?php echo fmt2($s['ct_act']);?></td>
                        <td class="t-r <?php echo $s['ct_diff']>=0?'c-pos':'c-neg';?>"><?php echo fmt2($s['ct_diff']);?></td>
                        <td class="t-r <?php echo $isLoss?'c-neg':'c-pos';?>"><?php echo fmtRpAbs($s['reduce']);?></td>
                    </tr>
                <?php endforeach;?>
                <tr class="summary-total"><td colspan="8" class="t-r">GRAND TOTAL</td><td class="t-r"><?php echo fmtRpAbs($sumReduce);?></td></tr>
                <?php endif;?>
                </tbody></table>
            </div>

            <!-- Charts Cycle Time -->
            <div class="row">
                <div class="col-md-6"><div class="chart-box"><h5><span class="glyphicon glyphicon-arrow-down" style="color:#e74c3c"></span> Top 10 Loss Cycle Time</h5><canvas id="chartLoss"></canvas></div></div>
                <div class="col-md-6"><div class="chart-box"><h5><span class="glyphicon glyphicon-arrow-up" style="color:#27ae60"></span> Top 10 Savings Cycle Time</h5><canvas id="chartSavings"></canvas></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="chart-box"><h5><span class="glyphicon glyphicon-pie-chart" style="color:#8e44ad"></span> Loss vs Savings Cycle Time</h5><canvas id="chartPie"></canvas></div></div>
                <div class="col-md-8"><div class="chart-box"><h5><span class="glyphicon glyphicon-briefcase" style="color:#e67e22"></span> Loss per Mesin Cycle Time (Top 10)</h5><canvas id="chartMachine"></canvas></div></div>
            </div>

            <!-- NEW: Charts Material Profit/Loss -->
            <div class="row">
                <div class="col-md-8"><div class="chart-box"><h5><span class="glyphicon glyphicon-tasks" style="color:#2980b9"></span> Material Profit vs Loss per Mesin</h5><canvas id="chartMatMachine"></canvas></div></div>
                <div class="col-md-4"><div class="chart-box"><h5><span class="glyphicon glyphicon-pie-chart" style="color:#8e44ad"></span> Global Material Profit vs Loss</h5><canvas id="chartMatPie"></canvas></div></div>
            </div>

        </div>
    </div>

    <div class="formula-note"><div class="row"><div class="col-md-12">
        <strong>Formula:</strong> Total Shoot = Output Total / Cavity Actual &bull; Running STD = (Total Shoot &times; CT STD) / 3600 &bull; Reduce (Rp) = Total Reduce Hour &times; Rate MC (Rp/Hour)<br><strong>Material:</strong> STD Material = (QTY WO &times; BOM QTY) / 1000 &bull; ACTUAL Material = (PD_QTY &times; BOM QTY) / 1000 &bull; Profit/Loss = (STD Material - Actual Material) &times; Harga Asli. Perhitungan P/L diterapkan untuk Virgin dan Pewarna.
        <br><strong>Summary:</strong> Loss = C/T Actual > C/T STD (Reduce negatif). Savings = C/T Actual < C/T STD (Reduce positif). Net Impact = Savings &minus; Loss.
    </div></div></div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
/* ===== AUTOCOMPLETE ===== */
(function($){
    'use strict';
    var AC={$input:null,$dropdown:null,$clear:null,timer:null,activeIdx:-1,url:'ac_customer.php',minLen:1,delay:250};
    function init(){AC.$input=$('#fCust');AC.$dropdown=$('#acCustDrop');AC.$clear=$('#acCustClear');if(!AC.$input.length||!AC.$dropdown.length)return;AC.$input.on('input.ac',onInput).on('keydown.ac',onKeydown).on('focus.ac',function(){if($.trim(AC.$input.val()).length>=AC.minLen)onInput();updateClear();}).on('blur.ac',function(){setTimeout(hide,150);});AC.$clear.on('mousedown.ac',function(e){e.preventDefault();AC.$input.val('').focus();updateClear();hide();});$(document).on('mousedown.ac',function(e){if(!$(e.target).closest('#acCustWrap').length)hide();});updateClear();}
    function onInput(){clearTimeout(AC.timer);var q=$.trim(AC.$input.val());updateClear();if(q.length<AC.minLen){hide();return;}AC.timer=setTimeout(function(){fetch(q);},AC.delay);}
    function fetch(q){showLoading();$.ajax({url:AC.url,data:{q:q},dataType:'json',cache:false,success:function(r){if(!r||!Array.isArray(r)||r.length===0){showEmpty();return;}AC.activeIdx=-1;render(r,q);},error:function(){hide();}});}
    function render(items,query){AC.$dropdown.empty().removeClass('ac-loading ac-empty');var qL=query.toLowerCase();$.each(items,function(i,it){var $i=$('<div class="ac-item"></div>').attr('data-index',i).attr('data-code',it.CUST_CODE||'');$i.html('<span class="ac-item-code">'+hl(it.CUST_CODE||'',qL)+'</span><span class="ac-item-sep">|</span><span class="ac-item-comp">'+hl(it.CUST_COMP||'',qL)+'</span><span class="ac-item-abbr">'+esc(it.CUST_ABBR||'')+'</span>');$i.on('mousedown',function(e){e.preventDefault();sel(it.CUST_CODE);}).on('mouseenter',function(){setA(i);});AC.$dropdown.append($i);});AC.$dropdown.show();}
    function hl(t,q){if(!q)return esc(t);var l=t.toLowerCase(),i=l.indexOf(q);if(i===-1)return esc(t);return esc(t.substring(0,i))+'<span class="ac-hl">'+esc(t.substring(i,i+q.length))+'</span>'+esc(t.substring(i+q.length));}
    function sel(c){AC.$input.val(c);hide();AC.$input.focus();updateClear();}
    function setA(i){AC.activeIdx=i;AC.$dropdown.find('.ac-item-active').removeClass('ac-item-active');var $a=AC.$dropdown.find('.ac-item').eq(i);if($a.length){$a.addClass('ac-item-active');var c=AC.$dropdown[0];if(c){var t=$a.position().top,b=t+$a.outerHeight(),s=c.scrollTop,v=c.clientHeight;if(t<0)c.scrollTop=s+t-2;else if(b>v)c.scrollTop=s+b-v+2;}}}
    function onKeydown(e){var $i=AC.$dropdown.find('.ac-item'),v=AC.$dropdown.is(':visible')&&$i.length>0;if(!v)return;switch(e.keyCode){case 40:e.preventDefault();AC.activeIdx=Math.min(AC.activeIdx+1,$i.length-1);setA(AC.activeIdx);break;case 38:e.preventDefault();AC.activeIdx=Math.max(AC.activeIdx-1,0);setA(AC.activeIdx);break;case 13:e.preventDefault();if(AC.activeIdx>=0&&AC.activeIdx<$i.length)sel($i.eq(AC.activeIdx).data('code'));else{hide();$('#filterForm').submit();}break;case 27:e.preventDefault();hide();AC.$input.focus();break;case 9:if(AC.activeIdx>=0&&AC.activeIdx<$i.length){e.preventDefault();sel($i.eq(AC.activeIdx).data('code'));}else hide();break;}}
    function showLoading(){AC.$dropdown.html('<div class="ac-loading"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Mencari customer...</div>').removeClass('ac-empty').addClass('ac-loading').show();}
    function showEmpty(){AC.$dropdown.html('<div class="ac-empty">Customer tidak ditemukan</div>').removeClass('ac-loading').addClass('ac-empty').show();}
    function hide(){AC.$dropdown.hide().empty().removeClass('ac-loading ac-empty');AC.activeIdx=-1;}
    function updateClear(){if($.trim(AC.$input.val()).length>0)AC.$clear.addClass('visible');else AC.$clear.removeClass('visible');}
    function esc(s){if(!s)return '';var m={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'};return String(s).replace(/[&<>"']/g,function(c){return m[c];});}
    var ss=document.createElement('style');ss.textContent='@-webkit-keyframes acspin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}@keyframes acspin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.glyphicon-spin{animation:acspin 1s linear infinite;display:inline-block}';document.head.appendChild(ss);
    $(init);
})(jQuery);

/* ===== CHARTS ===== */
(function(){
    'use strict';

    /* Data dari PHP */
    var chartLossData         = <?php echo json_encode($chartLossData, JSON_UNESCAPED_UNICODE); ?>;
    var chartSavingsData      = <?php echo json_encode($chartSavingsData, JSON_UNESCAPED_UNICODE); ?>;
    var chartMachineData      = <?php echo json_encode($chartMachineData, JSON_UNESCAPED_UNICODE); ?>;
    var totalSavings          = <?php echo json_encode($totalSavings); ?>;
    var totalLoss             = <?php echo json_encode($totalLoss); ?>;

    /* Data Material P/L dari PHP */
    var chartMatMachineLabels = <?php echo json_encode($chartMatMachineLabels, JSON_UNESCAPED_UNICODE); ?>;
    var chartMatMachineProfit = <?php echo json_encode($chartMatMachineProfit, JSON_UNESCAPED_UNICODE); ?>;
    var chartMatMachineLoss   = <?php echo json_encode($chartMatMachineLoss, JSON_UNESCAPED_UNICODE); ?>;
    var matTotalProfit        = <?php echo json_encode($matTotalProfit); ?>;
    var matTotalLoss          = <?php echo json_encode($matTotalLoss); ?>;

    /* Helper: format Rupiah */
    function rp(v){return 'Rp ' + Math.round(v).toLocaleString('id-ID');}

    /* Helper: extract labels and values from array of objects */
    function labels(arr){var r=[];for(var i=0;i<arr.length;i++)r.push(arr[i].label);return r;}
    function values(arr){var r=[];for(var i=0;i<arr.length;i++)r.push(arr[i].value);return r;}

    /* ========== CHART 1: Top 10 Loss Cycle Time ========== */
    if(chartLossData.length > 0){
        new Chart(document.getElementById('chartLoss'),{
            type:'bar',
            data:{
                labels: labels(chartLossData),
                datasets:[{
                    label:'Loss (Rp)',
                    data: values(chartLossData),
                    backgroundColor:'rgba(231,76,60,0.75)',
                    borderColor:'rgba(192,57,43,1)',
                    borderWidth:1,
                    barPercentage:0.7
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{display:false},
                scales:{
                    yAxes:[{
                        ticks:{callback:function(v){return 'Rp '+Math.round(v).toLocaleString('id-ID');},fontColor:'#666',fontSize:9},
                        gridLines:{color:'#f0f0f0'}
                    }],
                    xAxes:[{
                        ticks:{fontStyle:'bold',fontSize:8,maxRotation:45,minRotation:25,fontColor:'#444'},
                        gridLines:{display:false}
                    }]
                },
                tooltips:{
                    callbacks:{
                        label:function(ctx){return 'Loss: -' + rp(ctx.parsed ? ctx.parsed.y : ctx.yLabel);}
                    }
                }
            }
        });
    }

    /* ========== CHART 2: Top 10 Savings Cycle Time ========== */
    if(chartSavingsData.length > 0){
        new Chart(document.getElementById('chartSavings'),{
            type:'bar',
            data:{
                labels: labels(chartSavingsData),
                datasets:[{
                    label:'Savings (Rp)',
                    data: values(chartSavingsData),
                    backgroundColor:'rgba(39,174,96,0.75)',
                    borderColor:'rgba(30,132,73,1)',
                    borderWidth:1,
                    barPercentage:0.7
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{display:false},
                scales:{
                    yAxes:[{
                        ticks:{callback:function(v){return 'Rp '+Math.round(v).toLocaleString('id-ID');},fontColor:'#666',fontSize:9},
                        gridLines:{color:'#f0f0f0'}
                    }],
                    xAxes:[{
                        ticks:{fontStyle:'bold',fontSize:8,maxRotation:45,minRotation:25,fontColor:'#444'},
                        gridLines:{display:false}
                    }]
                },
                tooltips:{
                    callbacks:{
                        label:function(ctx){return 'Savings: +' + rp(ctx.parsed ? ctx.parsed.y : ctx.yLabel);}
                    }
                }
            }
        });
    }

    /* ========== CHART 3: Doughnut Loss vs Savings Cycle Time ========== */
    if(totalLoss > 0 || totalSavings > 0){
        new Chart(document.getElementById('chartPie'),{
            type:'doughnut',
            data:{
                labels:['Loss','Savings'],
                datasets:[{
                    data:[Math.max(0,totalLoss), Math.max(0,totalSavings)],
                    backgroundColor:['rgba(231,76,60,0.8)','rgba(39,174,96,0.8)'],
                    borderColor:['#c0392b','#1e8449'],
                    borderWidth:2,
                    hoverOffset:6
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{position:'bottom',labels:{padding:15,fontStyle:'bold',fontSize:12,usePointStyle:true}},
                cutoutPercentage:'55%',
                tooltips:{
                    callbacks:{
                        label:function(tooltipItem, data){
                            var val = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                            var total = data.datasets[tooltipItem.datasetIndex].data[0] + data.datasets[tooltipItem.datasetIndex].data[1];
                            var pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
                            var label = data.labels[tooltipItem.index];
                            return label + ': ' + rp(val) + ' (' + pct + '%)';
                        }
                    }
                }
            }
        });
    }

    /* ========== CHART 4: Horizontal Bar — Loss per Mesin Top 10 Cycle Time ========== */
    if(chartMachineData.length > 0){
        new Chart(document.getElementById('chartMachine'),{
            type:'horizontalBar',
            data:{
                labels: labels(chartMachineData),
                datasets:[{
                    label:'Loss (Rp)',
                    data: values(chartMachineData),
                    backgroundColor:'rgba(230,126,34,0.75)',
                    borderColor:'rgba(211,84,0,1)',
                    borderWidth:1,
                    barPercentage:0.65
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{display:false},
                scales:{
                    xAxes:[{
                        ticks:{callback:function(v){return 'Rp '+Math.round(v).toLocaleString('id-ID');},fontColor:'#666',fontSize:9},
                        gridLines:{color:'#f0f0f0'}
                    }],
                    yAxes:[{
                        ticks:{fontStyle:'bold',fontSize:10,fontColor:'#444'},
                        gridLines:{display:false}
                    }]
                },
                tooltips:{
                    callbacks:{
                        label:function(ctx){return 'Loss: -' + rp(ctx.parsed ? ctx.parsed.x : ctx.xLabel);}
                    }
                }
            }
        });
    }

    /* ========== CHART 5: Material Profit vs Loss per Mesin (Grouped Bar) ========== */
    if(chartMatMachineLabels.length > 0){
        new Chart(document.getElementById('chartMatMachine'),{
            type:'bar',
            data:{
                labels: chartMatMachineLabels,
                datasets:[{
                    label:'Profit (Rp)',
                    data: chartMatMachineProfit,
                    backgroundColor:'rgba(39,174,96,0.75)',
                    borderColor:'rgba(30,132,73,1)',
                    borderWidth:1
                },{
                    label:'Loss (Rp)',
                    data: chartMatMachineLoss,
                    backgroundColor:'rgba(231,76,60,0.75)',
                    borderColor:'rgba(192,57,43,1)',
                    borderWidth:1
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{position:'bottom', labels: {fontStyle: 'bold'}},
                scales:{
                    yAxes:[{
                        ticks:{callback:function(v){return 'Rp '+Math.round(v).toLocaleString('id-ID');},fontColor:'#666',fontSize:9},
                        gridLines:{color:'#f0f0f0'}
                    }],
                    xAxes:[{
                        ticks:{fontStyle:'bold',fontSize:10,fontColor:'#444',maxRotation:45},
                        gridLines:{display:false}
                    }]
                },
                tooltips:{
                    callbacks:{
                        label:function(tooltipItem, data){
                            var label = data.datasets[tooltipItem.datasetIndex].label || '';
                            var val = tooltipItem.yLabel || data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                            return label + ': ' + rp(val);
                        }
                    }
                }
            }
        });
    }

    /* ========== CHART 6: Global Material Profit vs Loss (Pie Chart) ========== */
    if(matTotalProfit > 0 || matTotalLoss > 0){
        new Chart(document.getElementById('chartMatPie'),{
            type:'pie',
            data:{
                labels:['Loss Material','Profit Material'],
                datasets:[{
                    data:[Math.max(0,matTotalLoss), Math.max(0,matTotalProfit)],
                    backgroundColor:['rgba(231,76,60,0.8)','rgba(39,174,96,0.8)'],
                    borderColor:['#c0392b','#1e8449'],
                    borderWidth:2
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                legend:{position:'bottom',labels:{padding:15,fontStyle:'bold',fontSize:12,usePointStyle:true}},
                tooltips:{
                    callbacks:{
                        label:function(tooltipItem, data){
                            var val = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                            var total = data.datasets[tooltipItem.datasetIndex].data[0] + data.datasets[tooltipItem.datasetIndex].data[1];
                            var pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
                            var label = data.labels[tooltipItem.index];
                            return label + ': ' + rp(val) + ' (' + pct + '%)';
                        }
                    }
                }
            }
        });
    }

})();
</script>
</body>
</html>