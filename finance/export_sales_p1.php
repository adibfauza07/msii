<?php
// C:\xampp\htdocs\msii\finance\export_sales_p1.php
// SALES P1 - UDI MAGIC A-L RAW + PROGRESS
@ini_set('max_execution_time','0');
@ini_set('memory_limit','1024M');
@set_time_limit(0);

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';
if (file_exists($config1)) require_once $config1;
elseif (file_exists($config2)) require_once $config2;
else die('File config database_aging.php tidak ditemukan.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function x($s){ return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
function qx($sql,$params=array()){ return q($sql,$params); }
function fetchAllRows($stmt){ $rows=array(); while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){ $rows[]=$r; } return $rows; }
function rawVal($v){ if($v instanceof DateTime) return $v->format('d-M-Y'); return trim((string)$v); }
function fmtTallyDateYmd($v){
    if($v instanceof DateTime) return $v->format('Ymd');
    $s=trim((string)$v); if($s==='') return '';
    $t=strtotime($s); if($t!==false) return date('Ymd',$t);
    $d=preg_replace('/[^0-9]/','',$s); if(strlen($d)==8) return $d;
    return $s;
}
function groupRows($rows){
    $groups=array();
    foreach($rows as $r){
        $key=trim((string)$r['NO_INVOICE']).'|'.trim((string)$r['NO_DS']);
        if(!isset($groups[$key])) $groups[$key]=array('header'=>$r,'rows'=>array());
        $groups[$key]['rows'][]=$r;
    }
    return $groups;
}
function sendToTally($xml,$url){
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-Type: text/xml'));
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
    curl_setopt($ch,CURLOPT_TIMEOUT,120);
    $res=curl_exec($ch);
    if($res===false) $res='CURL ERROR: '.curl_error($ch);
    curl_close($ch);
    return $res;
}
function responseOk($res){
    if(strpos($res,'<CREATED>1</CREATED>')!==false) return true;
    if(strpos($res,'<ALTERED>1</ALTERED>')!==false) return true;
    if(strpos($res,'<ERRORS>0</ERRORS>')!==false && strpos($res,'<LINEERROR>')===false) return true;
    return false;
}

function tallyLineError($res){
    if(preg_match_all('/<LINEERROR>(.*?)<\\/LINEERROR>/is', (string)$res, $m)){
        $msg=implode(' | ', $m[1]);
        $msg=html_entity_decode($msg, ENT_QUOTES, 'UTF-8');
        return trim(strip_tags($msg));
    }
    if(stripos((string)$res,'CURL ERROR')!==false) return trim((string)$res);
    return '';
}

function groupItemCodes($group){
    $items=array();
    foreach($group['rows'] as $r){
        $code=isset($r['ITEM_CODE']) ? trim((string)$r['ITEM_CODE']) : '';
        if($code!='') $items[]=$code;
    }
    return implode(', ', $items);
}
function saveDebugXml($voucherNo,$dsNo,$xml){
    $dir=__DIR__.'/debug_tally_xml_sales_p1';
    if(!is_dir($dir)) @mkdir($dir,0777,true);
    $v=preg_replace('/[^A-Za-z0-9_\-]/','_', (string)$voucherNo);
    $d=preg_replace('/[^A-Za-z0-9_\-]/','_', (string)$dsNo);
    $file=$dir.'/'.$v.'_'.$d.'.xml';
    @file_put_contents($file,$xml);
    return $file;
}
function ensureSettingTable(){
    qx("IF OBJECT_ID('dbo.Tally_Export_Setting','U') IS NULL
        BEGIN
            CREATE TABLE dbo.Tally_Export_Setting
            (
                SettingName varchar(50) NOT NULL PRIMARY KEY,
                TallyIP varchar(50) NULL,
                TallyPort varchar(10) NULL,
                SqlQuery text NULL,
                XmlTemplate text NULL,
                UpdatedAt datetime NULL
            )
        END", array());
}
function defaultSqlQuery(){
    return "SELECT
    NO_INVOICE,
    NO_DS,
    Tanggal,
    CUST_CODE,
    ITEM_CODE,
    ITEM_NAME,
    QTY,
    ITEM_PRICE,
    TOTAL_HARGA,
    TOTAL_HARGA2,
    GROUP_TOTAL,
    BC,
    ITEM_NO,
    PO
FROM dbo.Tally_SALES
ORDER BY NO_INVOICE, NO_DS, ITEM_CODE";
}
function getSetting(){
    ensureSettingTable();
    $stmt=qx("SELECT TOP 1 * FROM dbo.Tally_Export_Setting WHERE SettingName='SALES_P1_UDI_AL_RAW_PROGRESS'", array());
    $r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC);
    if(!$r){
        qx("INSERT INTO dbo.Tally_Export_Setting
            (SettingName,TallyIP,TallyPort,SqlQuery,XmlTemplate,UpdatedAt)
            VALUES ('SALES_P1_UDI_AL_RAW_PROGRESS','serplan1','9002',?,'',GETDATE())", array(defaultSqlQuery()));
        $stmt=qx("SELECT TOP 1 * FROM dbo.Tally_Export_Setting WHERE SettingName='SALES_P1_UDI_AL_RAW_PROGRESS'", array());
        $r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC);
    }
    return $r;
}
function saveSetting($ip,$port,$sql){
    ensureSettingTable();
    qx("UPDATE dbo.Tally_Export_Setting SET TallyIP=?, TallyPort=?, SqlQuery=?, UpdatedAt=GETDATE()
        WHERE SettingName='SALES_P1_UDI_AL_RAW_PROGRESS'", array($ip,$port,$sql));
}
function testTallyConnection($ip,$port){
    $fp=@fsockopen($ip,$port,$errno,$errstr,3);
    if($fp){ fclose($fp); return 'OK: Tally port terbuka di '.$ip.':'.$port; }
    return 'ERROR: Tidak bisa konek ke '.$ip.':'.$port.' - '.$errstr;
}
function buildSalesXml($group){
    $h=$group['header'];
    $a=rawVal($h['NO_INVOICE']); // A
    $b=rawVal($h['NO_DS']);      // B
    $c=fmtTallyDateYmd($h['Tanggal']); // C
    $d=rawVal($h['CUST_CODE']);  // D
    // Sales balance:
    // Inventory/Sales allocation = Credit positive amount.
    // Customer ledger = Debit, so Tally XML needs ISDEEMEDPOSITIVE=Yes and AMOUNT negative.
    $k = 0;
    foreach ($group['rows'] as $rr) {
        $k += (float)rawVal($rr['TOTAL_HARGA']);
    }
    $k = '-' . abs(round($k, 4));
    $l=isset($h['BC']) ? rawVal($h['BC']) : ''; // L
    $guid='udi-Sales-'.$a.'-'.$b.'-'.$c;
    $itemsXml='';
    foreach($group['rows'] as $r){
        $e=rawVal($r['ITEM_CODE']);    // E
        $g=rawVal($r['QTY']);          // G
        $hprice=rawVal($r['ITEM_PRICE']); // H
        $i=rawVal($r['TOTAL_HARGA']);  // I
        $itemsXml.='
        <ALLINVENTORYENTRIES.LIST SCROLL="YES">
          <STOCKITEMNAME>'.x($e).'</STOCKITEMNAME>
          <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
          <RATE>'.x($hprice).'</RATE>
          <AMOUNT>'.x($i).'</AMOUNT>
          <ACTUALQTY>'.x($g).'</ACTUALQTY>
          <BILLEDQTY>'.x($g).'</BILLEDQTY>
          <ACCOUNTINGALLOCATIONS.LIST>
            <LEDGERNAME>43110 Sales Local Electronic Component To KB/EPTE</LEDGERNAME>
            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
            <AMOUNT>'.x($i).'</AMOUNT>
          </ACCOUNTINGALLOCATIONS.LIST>
          <BATCHALLOCATIONS.LIST>
            <GODOWNNAME>Main Location</GODOWNNAME>
            <BATCHNAME>Primary Batch</BATCHNAME>
            <DESTINATIONGODOWNNAME>Main Location</DESTINATIONGODOWNNAME>
            <AMOUNT>'.x($i).'</AMOUNT>
            <ACTUALQTY>'.x($g).'</ACTUALQTY>
            <BILLEDQTY>'.x($g).'</BILLEDQTY>
          </BATCHALLOCATIONS.LIST>
        </ALLINVENTORYENTRIES.LIST>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME></REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <VOUCHER REMOTEID="'.x($guid).'" VCHTYPE="Sales" ACTION="Create">
            <GUID>'.x($guid).'</GUID>
            <DATE>'.x($c).'</DATE>
            <VOUCHERNUMBER>'.x($a).'</VOUCHERNUMBER>
            <EFFECTIVEDATE>'.x($c).'</EFFECTIVEDATE>
            <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>
            <REFERENCE>'.x($b).'</REFERENCE>
            <NARRATION>'.x($l).'</NARRATION>
            <ISINVOICE>Yes</ISINVOICE>
<LEDGERENTRIES.LIST>
  <LEDGERNAME>'.x($d).'</LEDGERNAME>
  <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
  <AMOUNT>'.x($k).'</AMOUNT>
</LEDGERENTRIES.LIST>
'.$itemsXml.'
          </VOUCHER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
}

$setting=getSetting();
$tally_ip=isset($setting['TallyIP'])?$setting['TallyIP']:'serplan1';
$tally_port=isset($setting['TallyPort'])?$setting['TallyPort']:'9002';
$sql_query=isset($setting['SqlQuery'])?$setting['SqlQuery']:defaultSqlQuery();

$action=isset($_POST['action'])?$_POST['action']:'';
$message='';
$resultRows=array();
$exportDetails=array();
$summary=null;
$batch_limit=isset($_POST['batch_limit'])?(int)$_POST['batch_limit']:50;
if($batch_limit<=0) $batch_limit=50;

if($_SERVER['REQUEST_METHOD']=='POST'){
    $tally_ip=isset($_POST['tally_ip'])?trim($_POST['tally_ip']):$tally_ip;
    $tally_port=isset($_POST['tally_port'])?trim($_POST['tally_port']):$tally_port;
    $sql_query=isset($_POST['sql_query'])?$_POST['sql_query']:$sql_query;
}

if($action=='reset_sql'){
    $sql_query=defaultSqlQuery();
    saveSetting($tally_ip,$tally_port,$sql_query);
    $message='SQL default Sales P1 berhasil di-reset.';
}elseif($action=='save'){
    saveSetting($tally_ip,$tally_port,$sql_query);
    $message='Setting berhasil disimpan.';
}elseif($action=='test'){
    $message=testTallyConnection($tally_ip,$tally_port);
}elseif($action=='preview' || $action=='export'){
    $stmt=qx($sql_query,array());
    $resultRows=fetchAllRows($stmt);
    $groups=groupRows($resultRows);
    if($action=='preview'){
        $message='Preview data selesai. Total row: '.count($resultRows).', voucher: '.count($groups);
    }else{
        saveSetting($tally_ip,$tally_port,$sql_query);
        $url='http://'.$tally_ip.':'.$tally_port;
        $success=0; $failed=0; $processed=0;
        $totalVoucher=count($groups);
        $totalToProcess=($batch_limit < $totalVoucher) ? $batch_limit : $totalVoucher;

        foreach($groups as $key=>$group){
            if($processed >= $batch_limit) break;

            $counterNo=$processed + 1;
            $remainingBefore=$totalToProcess - $processed;
            $xml=buildSalesXml($group);
            $voucherNoDebug=isset($group['header']['NO_INVOICE'])?$group['header']['NO_INVOICE']:'';
            $dsNoDebug=isset($group['header']['NO_DS'])?$group['header']['NO_DS']:'';
            $debugFile=saveDebugXml($voucherNoDebug,$dsNoDebug,$xml);
            $res=sendToTally($xml,$url);
            $ok=responseOk($res);
            if($ok) $success++; else $failed++;
            $exportDetails[]=array(
                'no'=>$counterNo,
                'remaining_before'=>$remainingBefore,
                'remaining_after'=>$remainingBefore - 1,
                'counter_text'=>$ok ? ($counterNo . ' sukses') : ($counterNo . ' gagal'),
                'voucher'=>$voucherNoDebug,
                'ds'=>$dsNoDebug,
                'items'=>count($group['rows']),
                'item_codes'=>groupItemCodes($group),
                'ok'=>$ok,
                'error_msg'=>tallyLineError($res),
                'debug_file'=>$debugFile,
                'response'=>$res
            );
            $processed++;
        }
        $summary=array('rows'=>count($resultRows),'voucher'=>count($groups),'target'=>$totalToProcess,'processed'=>$processed,'remaining'=>count($groups)-$processed,'success'=>$success,'failed'=>$failed);
        $message='Export Sales selesai. Diproses: '.$processed.', sukses: '.$success.', gagal: '.$failed;
    }
}
if($action==''){
    $stmt=qx($sql_query,array());
    $resultRows=fetchAllRows($stmt);
}
?>
<?php include 'layout.php'; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">EXPORT SALES P1 TO TALLY - UDI A-L RAW</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
    <?php if($message!=''){ ?><div class="alert alert-info"><?php echo h($message); ?></div><?php } ?>
    <div class="alert alert-warning">
        Sales P1 sesuai template UDI Magic:
        A=NO_INVOICE, B=NO_DS, C=Tanggal, D=CUST_CODE, E=ITEM_CODE, G=QTY, H=ITEM_PRICE, I=TOTAL_HARGA, K=GROUP_TOTAL, L=BC.
        Setelah pasang file ini wajib klik <b>Reset SQL Default</b>.
        Debug XML: <b>/msii/finance/debug_tally_xml_sales_P1/</b>.
        Counter menampilkan hitungan mundur, contoh 900 → 899, dan status 1 sukses, 2 sukses, dst.
        FIX Sales balance: Customer ledger dikirim <b>ISDEEMEDPOSITIVE=Yes</b> dan <b>AMOUNT negatif</b> supaya Dr/Cr match.
    </div>
    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Export</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang export ke Tally...</div>
        </div>
    </div>
    <form method="post" id="frmMagic">
        <input type="hidden" name="action" id="action" value="">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Settings</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label fw-bold">Tally IP Address</label><input type="text" name="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold">Tally Port</label><input type="text" name="tally_port" class="form-control" value="<?php echo h($tally_port); ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold">Batch Limit</label><input type="number" name="batch_limit" class="form-control" value="<?php echo h($batch_limit); ?>"></div>
                    <div class="col-md-5 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Connection</button>
                        <button type="button" class="btn btn-success" onclick="setAction('save')">Save</button>
                        <button type="button" class="btn btn-warning" onclick="setAction('reset_sql')">Reset SQL Default</button>
                        <button type="button" class="btn btn-primary" onclick="setAction('preview')">Preview Query</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Export Sales to Tally</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">SQL Query</div>
            <div class="card-body"><textarea name="sql_query" class="form-control" rows="11" style="font-family:Consolas,monospace;"><?php echo h($sql_query); ?></textarea></div>
        </div>
    </form>

    <?php if($summary!==null){ ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var box=document.getElementById('exportProgressBox'), bar=document.getElementById('exportProgressBar'), txt=document.getElementById('exportProgressText');
            if(box&&bar){ box.style.display='block'; bar.className='progress-bar'; bar.style.width='100%'; bar.innerHTML='100%'; if(txt) txt.innerHTML='Export selesai: <?php echo h($summary['processed']); ?> dari <?php echo h($summary['target']); ?> voucher. Sukses: <?php echo h($summary['success']); ?>, Gagal: <?php echo h($summary['failed']); ?>.'; }
        });
        </script>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Export</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Row</th><td><?php echo h($summary['rows']); ?></td></tr>
                    <tr><th>Total Voucher</th><td><?php echo h($summary['voucher']); ?></td></tr>
                    <tr><th>Target Proses</th><td><?php echo h($summary['target']); ?></td></tr>
                    <tr><th>Diproses</th><td><?php echo h($summary['processed']); ?></td></tr>
                    <tr><th>Sisa</th><td><?php echo h($summary['remaining']); ?></td></tr>
                    <tr><th>Sukses</th><td><?php echo h($summary['success']); ?></td></tr>
                    <tr><th>Gagal</th><td><?php echo h($summary['failed']); ?></td></tr>
                </table>
                <div class="table-responsive" style="max-height:350px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top"><tr><th>No</th><th>Sisa Sebelum</th><th>Sisa Sesudah</th><th>Counter</th><th>Voucher</th><th>NO DS</th><th>Items</th><th>ITEM_CODE</th><th>Status</th><th>Error Tally</th><th>Debug XML</th><th>Response Tally</th></tr></thead>
                        <tbody>
                        <?php foreach($exportDetails as $d){ ?>
                            <tr>
                                <td class="text-end"><?php echo h($d['no']); ?></td>
                                <td class="text-end"><?php echo h($d['remaining_before']); ?></td>
                                <td class="text-end"><?php echo h($d['remaining_after']); ?></td>
                                <td><?php echo h($d['counter_text']); ?></td>
                                <td><?php echo h($d['voucher']); ?></td>
                                <td><?php echo h($d['ds']); ?></td>
                                <td class="text-end"><?php echo h($d['items']); ?></td>
                                <td style="min-width:220px;"><?php echo h($d['item_codes']); ?></td>
                                <td><?php echo $d['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">GAGAL</span>'; ?></td>
                                <td style="min-width:260px;"><?php echo h($d['error_msg']); ?></td>
                                <td><?php echo h(str_replace(__DIR__,'',$d['debug_file'])); ?></td>
                                <td><pre style="white-space:pre-wrap;max-width:700px;"><?php echo h($d['response']); ?></pre></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Preview Data Query</div>
        <div class="card-body">
            <?php if(count($resultRows)==0){ ?>
                <div class="alert alert-secondary">Data tidak ada.</div>
            <?php } else { ?>
                <div class="table-responsive" style="max-height:600px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top"><tr><?php foreach(array_keys($resultRows[0]) as $col){ ?><th><?php echo h($col); ?></th><?php } ?></tr></thead>
                        <tbody>
                        <?php foreach($resultRows as $r){ ?><tr><?php foreach($r as $v){ ?><td><?php echo h($v instanceof DateTime ? $v->format('Y-m-d') : $v); ?></td><?php } ?></tr><?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
function setAction(a){ document.getElementById('action').value=a; document.getElementById('frmMagic').submit(); }
function showExportProgress(){
    var box=document.getElementById('exportProgressBox'), bar=document.getElementById('exportProgressBar'), txt=document.getElementById('exportProgressText');
    if(box) box.style.display='block';
    var pct=5;
    if(bar){ bar.style.width=pct+'%'; bar.innerHTML=pct+'%'; }
    if(txt) txt.innerHTML='Export sedang berjalan: mulai dari counter 1 sampai selesai. Jangan tutup browser.';
    var liveCounter = 1;
    window._progressTimer=setInterval(function(){
        if(pct<90){
            pct+=5;
            liveCounter++;
            if(bar){ bar.style.width=pct+'%'; bar.innerHTML=pct+'%'; }
            if(txt){ txt.innerHTML='Export sedang berjalan... counter sekitar ' + liveCounter + ' diproses. Jangan tutup browser.'; }
        }
    },700);
}
function confirmExport(){
    if(confirm('Export Sales data query ke Tally sekarang?')){
        showExportProgress();
        setTimeout(function(){ setAction('export'); },200);
    }
}
</script>
</body>
</html>
