<?php
// File: work_order.php
error_reporting(0);
ob_start();

require_once __DIR__ . '/config/database.php';

// Deteksi koneksi database
$db = null;
if (isset($conn) && $conn !== false) {
    $db = $conn;
} elseif (isset($connection) && $connection !== false) {
    $db = $connection;
} elseif (isset($dbconn) && $dbconn !== false) {
    $db = $dbconn;
}

function sendJSON($data) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function wo_utf8($value) {
    $text = trim((string)$value);
    if ($text === '') return '';
    if (@preg_match('//u', $text)) return $text;
    if (function_exists('iconv')) {
        $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if ($conv !== false) return $conv;
    }
    if (function_exists('utf8_encode')) return @utf8_encode($text);
    return $text;
}

function month_key_from_date($date) {
    if ($date == '') return date('Y-m-01');
    $time = strtotime($date);
    return ($time === false) ? date('Y-m-01') : date('Y-m-01', $time);
}

function get_active_bomid($db, $item_id) {
    $sql = "SELECT TOP 1 BOM_ID FROM dbo.BOM_MASTER WHERE PART_ID = ? ORDER BY BOM_ID DESC";
    $stmt = sqlsrv_query($db, $sql, array($item_id));
    if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return intval($r['BOM_ID']);
    }
    return 0;
}

function get_or_create_wo_ref($db, $item_id, $wo_mmyy) {
    $wo_mmyy = month_key_from_date($wo_mmyy);
    $sql = "SELECT TOP 1 WO_REF FROM dbo.WO 
            WHERE WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
              AND WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)
              AND ITEM_ID = ?
            ORDER BY WO_REF ASC";
    $stmt = sqlsrv_query($db, $sql, array($wo_mmyy, $wo_mmyy, $item_id));
    if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return intval($r['WO_REF']);
    }

    $sql2 = "SELECT ISNULL(MAX(WO_REF), 0) + 1 AS WO_REF FROM dbo.WO 
             WHERE WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
               AND WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)";
    $stmt2 = sqlsrv_query($db, $sql2, array($wo_mmyy, $wo_mmyy));
    if ($stmt2 && $r2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
        return intval($r2['WO_REF']);
    }
    return 1;
}

function next_wo_no($db, $wo_ref, $wo_mmyy) {
    $wo_mmyy = month_key_from_date($wo_mmyy);
    $sql = "SELECT ISNULL(MAX(WO_NO), 0) + 1 AS WO_NO FROM dbo.WO 
            WHERE WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
              AND WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)
              AND WO_REF = ?";
    $stmt = sqlsrv_query($db, $sql, array($wo_mmyy, $wo_mmyy, $wo_ref));
    if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return intval($r['WO_NO']);
    }
    return 1;
}

// =================================================================
// 1. HANDLER CETAK WORK ORDER (INJECTION VS NON-INJECTION / WOAS)
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'cetak_wo') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $wo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Ambil Data Header WO Lengkap
    $sqlHdr = "SELECT TOP 1
                W.WO_ID, W.WO_NUMBER, W.WO_MMYY, W.WO_REF, W.WO_NO, W.MODIFY_DATE, W.PRCD_CODE,
                P.PROC_NAME, C.CUST_COMP, M.MAC_CODE, W.WO_CAP, W.WO_START, W.WO_END, W.WO_QTY, W.WO_REM,
                IP.ITEM_CAVT, IP.ITEM_CYTM, IP.CAPD, I.ITEM_CODE, I.ITEM_NO, I.ITEM_NAME,
                ISNULL(SP.WARNA_LABEL, 'WHITE') AS WARNA_LABEL,
                ISNULL(SP.STD_PACK, 0) AS STD_PACK,
                ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX,
                W.BOM_ID
              FROM dbo.WO W
              INNER JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
              LEFT JOIN dbo.PROCESS P ON W.PROC_ID = P.PROC_ID
              LEFT JOIN dbo.ITEM_CUSTINFO_VIEW C ON W.ITEM_ID = C.ITEM_ID
              LEFT JOIN dbo.ITEM_CAPD_VIEW IP ON W.ITEM_ID = IP.ITEM_ID
              LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID
              LEFT JOIN (
                  SELECT ITEM_CODE, MAX(WARNA_LABEL) AS WARNA_LABEL, MAX(STD_PACK) AS STD_PACK, MAX(STD_PACK_BOX) AS STD_PACK_BOX
                  FROM dbo.STD_PACK GROUP BY ITEM_CODE
              ) SP ON SP.ITEM_CODE = I.ITEM_CODE
              WHERE W.WO_ID = ?";
    
    $stmtHdr = sqlsrv_query($db, $sqlHdr, array($wo_id));
    $header = $stmtHdr ? sqlsrv_fetch_object($stmtHdr) : null;
    
    // Coba Ambil Detail Material via Stored Procedure RPT_WO_new, jika tidak ada gunakan query BOM fallback
    $materials = array();
    $sqlProc = "EXEC RPT_WO_new ?";
    $stmtMat = sqlsrv_query($db, $sqlProc, array($wo_id));
    if ($stmtMat && sqlsrv_has_rows($stmtMat)) {
        while ($rm = sqlsrv_fetch_object($stmtMat)) {
            $materials[] = $rm;
        }
    } else {
        $sqlMat = "SELECT I.ITEM_CODE AS CITEM_CODE, I.ITEM_NAME AS CITEM_NAME, 
                          ISNULL(B.QTY, 1) AS QTY, ISNULL(I.ITEM_UNIT, 'Pcs') AS UNIT, 0 AS FINAL_PCT_QTY
                   FROM dbo.BOM B
                   LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
                   WHERE B.BOM_ID = ?
                   ORDER BY I.ITEM_CODE ASC";
        $stmtMat2 = sqlsrv_query($db, $sqlMat, array($header ? $header->BOM_ID : 0));
        if ($stmtMat2) {
            while ($rm = sqlsrv_fetch_object($stmtMat2)) {
                $materials[] = $rm;
            }
        }
    }
    $sqlCount = count($materials);

    // Deteksi Proses: Jika mengandung 'INJECTION' gunakan wo.php, selain itu gunakan woas.php
    $procNameUpper = $header ? strtoupper(trim($header->PROC_NAME)) : '';
    $isInjection = (strpos($procNameUpper, 'INJECTION') !== false);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Cetak Work Order</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
        <style>
            table[border="1"] { border-collapse: collapse !important; border-spacing: 0 !important; border: 1.5px solid #000 !important; }
            table[border="1"] > tbody > tr > th, table[border="1"] > tbody > tr > td,
            table[border="1"] > tr > th, table[border="1"] > tr > td { border: 1.5px solid #000 !important; border-color: #000 !important; padding: 2px !important; }
            .kecil { font-size: 11px; padding-right: 2px; }
            .besar { font-size: 14px; font-weight: bold; color: #D15B47; padding-right: 2px; }
            @media print {
                .no-print { display: none !important; }
                .pagebreak { page-break-after: always; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color: #000 !important; }
                table[border="1"] { border-collapse: collapse !important; border: 1.5px solid #000 !important; }
                table[border="1"] th, table[border="1"] td { border: 1.5px solid #000 !important; }
            }
        </style>
    </head>
    <body style="background:#fff;">
        <section class="invoice" style="margin-top:2px; padding:10px; text-align:center;">
            <div class="col-lg-12">
                <center>
                    <table width="750px" border="0">
                        <tr>
                            <th class="kecil">PT. IMC TEKNO INDONESIA</th>
                            <th rowspan="4" style="text-align:right; width:70px; padding:0px;">
                                <div style="border:1px solid #000; width:65px; height:65px; line-height:65px; text-align:center; font-size:9px; font-weight:bold;">QR CODE</div>
                            </th>
                        </tr>
                        <tr><th class="kecil">PPIC DEPARTEMENT</th></tr>
                        <tr><th class="kecil">PRODUCTION PLANNING CONTROL</th></tr>
                        <tr><th class="kecil">&nbsp;</th></tr>
                    </table>

                    <table width="750px" border="1">
                        <tr>
                            <th class="kecil" style="width:14%;">WO NO</th>
                            <td class="kecil" style="width:22%; font-weight:bold;"><?= $header ? $header->WO_NUMBER : '' ?></td>
                            <th class="kecil" colspan="2" rowspan="3" style="text-align:center; vertical-align:middle; font-size:22px; font-weight:bold;">WORK ORDER</th>
                            <th class="kecil" style="width:12%;">Part Code</th>
                            <td class="kecil" style="width:20%; font-weight:bold;"><?= $header ? $header->ITEM_CODE : '' ?></td>
                            <th class="kecil" style="width:10%;">Cav</th>
                            <td class="kecil" style="width:8%;"><?= $header ? $header->ITEM_CAVT : '' ?></td>
                        </tr>
                        <tr>
                            <th class="kecil">Issue Date</th>
                            <td class="kecil"><?= ($header && $header->MODIFY_DATE) ? date('d-M-Y', strtotime($header->MODIFY_DATE)) : '' ?></td>
                            <th class="kecil">Part No</th>
                            <td class="kecil"><?= $header ? $header->ITEM_NO : '' ?></td>
                            <th class="kecil">C.Time</th>
                            <td class="kecil"><?= $header ? number_format($header->ITEM_CYTM, 2) : '0.00' ?></td>
                        </tr>
                        <tr>
                            <th class="kecil">Section</th>
                            <td class="kecil"><?= $header ? $header->PROC_NAME : '' ?></td>
                            <th class="kecil">Part Name</th>
                            <td class="kecil" colspan="3"><?= $header ? $header->ITEM_NAME : '' ?></td>
                        </tr>
                        <tr>
                            <th class="kecil">Machine No</th>
                            <td class="kecil"><?= $header ? $header->MAC_CODE : '' ?></td>
                            <th class="kecil" style="width:13%;">Plan Start Date</th>
                            <td class="kecil"><?= ($header && $header->WO_START) ? date('d-M-Y', strtotime($header->WO_START)) : '' ?></td>
                            <th class="kecil">Customer</th>
                            <td class="kecil" colspan="3"><?= $header ? $header->CUST_COMP : '' ?></td>
                        </tr>

                        <?php if ($isInjection): ?>
                        <!-- ========================= BARIS KHUSUS WO INJECTION ========================= -->
                        <tr>
                            <th class="kecil">WO Qty</th>
                            <td class="kecil"><?= $header ? number_format($header->WO_QTY, 0) : 0 ?> Pcs</td>
                            <th class="kecil">Plan Finish Date</th>
                            <td class="kecil"><?= ($header && $header->WO_END) ? date('d-M-Y', strtotime($header->WO_END)) : '' ?></td>
                            <th class="kecil">Cap/Day</th>
                            <td class="kecil"><?= $header ? number_format($header->CAPD, 0) : 0 ?> Pcs</td>
                            <th class="kecil">Mc Day Std</th>
                            <td class="kecil">
                                <?php 
                                    $mcd_std = ($header && isset($header->CAPD) && $header->CAPD > 0) ? ($header->WO_QTY / $header->CAPD) : 0;
                                    echo number_format($mcd_std, 2); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="kecil" style="vertical-align:middle;">Item Code</th>
                            <th class="kecil" colspan="3" style="text-align:center; height:22px; vertical-align:middle;">Material Name</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">BOM</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">Used Plan</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">Unit</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">%</th>
                        </tr>
                        <?php if ($sqlCount > 0): foreach ($materials as $key): 
                            $persen = (isset($key->FINAL_PCT_QTY) && number_format($key->FINAL_PCT_QTY, 0) != 0) ? number_format($key->FINAL_PCT_QTY, 0) : '-';
                        ?>
                            <tr>
                                <td class="kecil"><?= $key->CITEM_CODE ?></td>
                                <td colspan="3" class="kecil"><?= $key->CITEM_NAME ?></td>
                                <td class="kecil" style="text-align:center;"><?= number_format($key->QTY, 2) ?></td>
                                <td class="kecil" style="text-align:center;"><?= number_format($key->QTY * ($header ? $header->WO_QTY : 0), 2) ?></td>
                                <td class="kecil" style="text-align:center;"><?= $key->UNIT ?></td>
                                <td class="kecil" style="text-align:center;"><?= $persen ?></td>
                            </tr>
                        <?php endforeach; endif; ?>

                        <?php else: ?>
                        <!-- ========================= BARIS KHUSUS WOAS (SELAIN INJECTION) ========================= -->
                        <tr>
                            <th class="kecil">WO Qty</th>
                            <td class="kecil"><?= $header ? number_format($header->WO_QTY, 0) : 0 ?> Pcs</td>
                            <th class="kecil">Plan Finish Date</th>
                            <td class="kecil"><?= ($header && $header->WO_END) ? date('d-M-Y', strtotime($header->WO_END)) : '' ?></td>
                            <th class="kecil">Cap/Hours</th>
                            <td class="kecil" colspan="3"><?= $header ? number_format($header->CAPD, 0) : 0 ?> Pcs</td>
                        </tr>
                        <tr>
                            <th class="kecil" style="vertical-align:middle;">Item Code</th>
                            <th class="kecil" colspan="3" style="text-align:center; height:22px; vertical-align:middle;">Material Name</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">BOM</th>
                            <th class="kecil" style="text-align:center; vertical-align:middle;">Used Plan</th>
                            <th colspan="2" class="kecil" style="text-align:center; vertical-align:middle;">Unit</th>
                        </tr>
                        <?php if ($sqlCount > 0): foreach ($materials as $key): ?>
                            <tr>
                                <td class="kecil"><?= $key->CITEM_CODE ?></td>
                                <td colspan="3" class="kecil"><?= $key->CITEM_NAME ?></td>
                                <td class="kecil" style="text-align:right;"><?= number_format($key->QTY, 2) ?></td>
                                <td class="kecil" style="text-align:right;"><?= number_format($key->QTY * ($header ? $header->WO_QTY : 0), 2) ?></td>
                                <td colspan="2" class="kecil" style="text-align:center;"><?= $key->UNIT ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <?php endif; ?>

                        <!-- Bagian Keterangan & Checklist Prod Type -->
                        <tr>
                            <td colspan="8">
                                <table cellpadding="0" style="width: 100%; border: none;">
                                    <tr>
                                        <td style="width: 140px;">
                                            <?php 
                                                $tipe_prod = ($header && isset($header->PRCD_CODE)) ? strtoupper(trim($header->PRCD_CODE)) : '';
                                                $cek_normal = ($tipe_prod == 'MP') ? '&#10004;' : '&nbsp;';
                                                $cek_trial  = ($tipe_prod == 'PP') ? '&#10004;' : '&nbsp;';
                                            ?>
                                            <table cellpadding="0" style="border: none;">
                                                <tr><td style="border:1.5px solid #000; width:35px; height:18px; text-align:center;">&nbsp;</td><td class="kecil" style="padding-left:5px;">URGENT</td></tr>
                                                <tr><td style="border:1.5px solid #000; width:35px; height:18px; text-align:center; font-weight:bold; font-size:13px;"><?= $cek_normal; ?></td><td class="kecil" style="padding-left:5px;">NORMAL</td></tr>
                                                <tr><td style="border:1.5px solid #000; width:35px; height:18px; text-align:center; font-weight:bold; font-size:13px;"><?= $cek_trial; ?></td><td class="kecil" style="padding-left:5px;">TRIAL</td></tr>
                                                <tr><td style="border:1.5px solid #000; width:35px; height:18px; text-align:center;">&nbsp;</td><td class="kecil" style="padding-left:5px;">ETC</td></tr>
                                            </table>
                                        </td>

                                        <td class="kecil" style="vertical-align:top; padding:4px;" colspan="3">
                                            <table cellpadding="0" cellspacing="0" style="border:none; table-layout:fixed; width:100%;">
                                                <tr>
                                                    <td style="border:none; width:130px; font-size:10px;">REMARKS (By PPC)</td>
                                                    <td style="border:none; width:10px; text-align:center;">:</td>
                                                    <td style="border:none; font-size:11px;"><?= ($header && isset($header->WO_REM)) ? $header->WO_REM : ''; ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="border:none; width:130px; font-size:10px;"><strong>Label Colour</strong></td>
                                                    <td style="border:none; width:10px; text-align:center;"><strong>:</strong></td>
                                                    <td style="border:none; font-size:11px;"><strong><?= ($header && isset($header->WARNA_LABEL)) ? $header->WARNA_LABEL : 'WHITE'; ?></strong></td>
                                                </tr>
                                                <?php 
                                                    $wo_qty       = ($header && isset($header->WO_QTY)) ? $header->WO_QTY : 0;
                                                    $std_pack     = ($header && isset($header->STD_PACK) && $header->STD_PACK > 1) ? $header->STD_PACK : 0;
                                                    $std_pack_box = ($header && isset($header->STD_PACK_BOX) && $header->STD_PACK_BOX > 1) ? $header->STD_PACK_BOX : 0;
                                                    $jml_polybag  = ($std_pack > 1) ? ceil($wo_qty / $std_pack) : 0;
                                                    $jml_box      = ($std_pack_box > 1) ? ceil($wo_qty / $std_pack_box) : 0;
                                                ?>
                                                <tr>
                                                    <td style="border:none; width:130px; font-size:10px;"><strong>Polybag label QTY</strong></td>
                                                    <td style="border:none; width:10px; text-align:center;"><strong>:</strong></td>
                                                    <td style="border:none; font-size:11px;"><strong><?= number_format($jml_polybag, 0) ?> Label</strong></td>
                                                </tr>
                                                <tr>
                                                    <td style="border:none; width:130px; font-size:10px;"><strong>Box Label QTY</strong></td>
                                                    <td style="border:none; width:10px; text-align:center;"><strong>:</strong></td>
                                                    <td style="border:none; font-size:11px;"><strong><?= number_format($jml_box, 0) ?> Label</strong></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Production Achievement & Signatures -->
                        <tr><th colspan="8" class="kecil" style="text-align:center;">PRODUCTION ACHIEVMENT</th></tr>
                        <tr>
                            <th class="kecil" colspan="2" style="text-align:center;">Actual</th>
                            <th class="kecil">QTY OK</th>
                            <td class="kecil" style="text-align:right">Pcs</td>
                            <th class="kecil" colspan="4" rowspan="3">Remarks (By Production)</th>
                        </tr>
                        <tr>
                            <th class="kecil">Start Date</th>
                            <th></th>
                            <th class="kecil">QTY HOLD</th>
                            <td class="kecil" style="text-align:right">Pcs</td>
                        </tr>
                        <tr>
                            <th class="kecil">Finish Date</th>
                            <th></th>
                            <th class="kecil">QTY NG</th>
                            <td class="kecil" style="text-align:right">Pcs</td>
                        </tr>

                        <tr style="border:0px;">
                            <th class="kecil" colspan="4" style="vertical-align:bottom;">FM.CO.00.10(REV.8:28 Nov 23)</th>
                            <td colspan="4" style="padding:0px;">
                                <table width="100%" border="1" style="border-collapse: collapse;">
                                    <tr>
                                        <th colspan="2" class="kecil" style="text-align:center; width:50%;">Production</th>
                                        <th colspan="2" class="kecil" style="text-align:center; width:50%;">PPC</th>
                                    </tr>
                                    <tr>
                                        <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:50px;">Checked By</td>
                                        <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:50px;">Prepared By</td>
                                        <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:50px;">Checked By</td>
                                        <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:50px;">Prepared By</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </center>
                
                <div class="row no-print" style="margin-top:15px; max-width:750px; margin-left:auto; margin-right:auto;">
                    <div class="col-sm-12 text-right">
                        <button type="button" class="btn btn-default btn-sm" onclick="window.print();"><i class="glyphicon glyphicon-print"></i> Print</button>
                        <button type="button" class="btn btn-default btn-sm" onclick="window.close();"><i class="glyphicon glyphicon-remove"></i> Close</button>
                    </div>
                </div>
            </div>
        </section>
    </body>
    </html>
    <?php
    exit;
}

// =================================================================
// 2. HANDLER AJAX ENDPOINTS
// =================================================================
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

if ($action !== '') {
    if (!$db) sendJSON(array('success' => false, 'message' => 'Koneksi database tidak tersedia.'));

    // A. AUTOCOMPLETE ITEM CODE
    if ($action === 'search_item_code') {
        $term = isset($_POST['term']) ? trim($_POST['term']) : (isset($_GET['term']) ? trim($_GET['term']) : '');
        if ($term === '') sendJSON(array('success' => true, 'data' => array()));

        $like = '%' . $term . '%';
        $sql = "SELECT TOP 30 I.ITEM_ID, LTRIM(RTRIM(I.ITEM_CODE)) AS ITEM_CODE, 
                       ISNULL(LTRIM(RTRIM(I.ITEM_NO)), '') AS ITEM_NO, 
                       ISNULL(LTRIM(RTRIM(I.ITEM_NAME)), '') AS ITEM_NAME
                FROM dbo.ITEMS I
                WHERE (I.ITEM_CODE LIKE ? OR I.ITEM_NO LIKE ? OR I.ITEM_NAME LIKE ?)
                  AND (I.ITEM_INACTIVE IS NULL OR I.ITEM_INACTIVE = 0)
                ORDER BY I.ITEM_CODE ASC";

        $stmt = sqlsrv_query($db, $sql, array($like, $like, $like));
        $res = array();
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $res[] = array(
                    'ITEM_ID'   => intval($r['ITEM_ID']),
                    'ITEM_CODE' => wo_utf8($r['ITEM_CODE']),
                    'ITEM_NO'   => wo_utf8($r['ITEM_NO']),
                    'ITEM_NAME' => wo_utf8($r['ITEM_NAME'])
                );
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('success' => true, 'data' => $res));
    }

    // B. FIND ITEM DETAIL
    if ($action === 'find_item') {
        $item_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
        $sql = "SELECT TOP 1 I.ITEM_ID, I.ITEM_CODE, I.ITEM_NO, I.ITEM_NAME,
                       ISNULL(IP.CAPD, 0) AS CAPD, IP.PROC_ID, P.PROC_NAME, M.MAC_ID, M.MAC_CODE
                FROM dbo.ITEMS I
                LEFT JOIN dbo.ITEM_CAPD_VIEW IP ON I.ITEM_ID = IP.ITEM_ID
                LEFT JOIN dbo.PROCESS P ON IP.PROC_ID = P.PROC_ID
                LEFT JOIN dbo.MAC M ON M.MAG_ID = IP.MAG_ID
                WHERE LTRIM(RTRIM(I.ITEM_CODE)) = ?
                ORDER BY I.ITEM_ID ASC";

        $stmt = sqlsrv_query($db, $sql, array($item_code));
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['ITEM_ID']   = intval($row['ITEM_ID']);
            $row['ITEM_CODE'] = wo_utf8($row['ITEM_CODE']);
            $row['ITEM_NO']   = wo_utf8($row['ITEM_NO']);
            $row['ITEM_NAME'] = wo_utf8($row['ITEM_NAME']);
            $row['MAC_CODE']  = wo_utf8($row['MAC_CODE']);
            $row['PROC_NAME'] = wo_utf8($row['PROC_NAME']);
            sqlsrv_free_stmt($stmt);
            sendJSON(array('success' => true, 'data' => $row));
        } else {
            sendJSON(array('success' => false, 'message' => 'ITEM_CODE tidak ditemukan.'));
        }
    }

    // C. LOAD WO PER BULAN
    if ($action === 'load_wo_input') {
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $wo_mmyy = isset($_POST['wo_mmyy']) ? month_key_from_date($_POST['wo_mmyy']) : date('Y-m-01');

        $sql = "SELECT W.WO_ID, W.WO_NO, W.WO_NUMBER, M.MAC_CODE, W.PRCD_CODE, W.WO_QTY, P.PROC_NAME,
                       W.WO_REF, W.PROC_ID, W.MAC_ID, W.BOM_ID, W.ITEM_ID,
                       CONVERT(varchar(10), W.WO_START, 120) AS WO_START,
                       CONVERT(varchar(10), W.WO_END, 120) AS WO_END,
                       W.WO_REM,
                       CONVERT(varchar(10), W.WO_MMYY, 120) AS WO_MMYY,
                       CONVERT(varchar(19), W.MODIFY_DATE, 120) AS MODIFY_DATE
                FROM dbo.WO W
                LEFT JOIN dbo.PROCESS P ON W.PROC_ID = P.PROC_ID
                LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID
                WHERE W.WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
                  AND W.WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)
                  AND W.ITEM_ID = ?
                ORDER BY W.WO_START ASC, M.MAC_CODE ASC, W.WO_NO ASC";

        $stmt = sqlsrv_query($db, $sql, array($wo_mmyy, $wo_mmyy, $item_id));
        $data = array();
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $r['WO_NUMBER'] = wo_utf8($r['WO_NUMBER']);
                $r['MAC_CODE']  = wo_utf8($r['MAC_CODE']);
                $r['PROC_NAME'] = wo_utf8($r['PROC_NAME']);
                $r['WO_REM']    = wo_utf8($r['WO_REM']);
                $data[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('success' => true, 'data' => $data));
    }

    // D. LOAD BOM DETAIL
    if ($action === 'load_bom_input') {
        $bom_id = isset($_POST['bom_id']) ? intval($_POST['bom_id']) : 0;
        if ($bom_id <= 0) sendJSON(array('success' => true, 'data' => array()));

        $sql = "SELECT B.BOM_ID, B.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, B.QTY, B.UNIT
                FROM dbo.BOM B
                LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
                WHERE B.BOM_ID = ?
                ORDER BY I.ITEM_CODE ASC";

        $stmt = sqlsrv_query($db, $sql, array($bom_id));
        $rows = array();
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $r['ITEM_CODE'] = wo_utf8($r['ITEM_CODE']);
                $r['ITEM_NAME'] = wo_utf8($r['ITEM_NAME']);
                $r['UNIT']      = wo_utf8($r['UNIT']);
                $rows[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('success' => true, 'data' => $rows));
    }

    // E. SIMPAN WORK ORDER
    if ($action === 'save_input') {
        $item_id    = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $qty        = isset($_POST['qty']) ? intval($_POST['qty']) : 0;
        $mac_id     = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;
        $proc_id    = isset($_POST['proc_id']) ? intval($_POST['proc_id']) : 0;
        $prcd_code  = isset($_POST['prcd_code']) ? trim($_POST['prcd_code']) : 'MP';
        $remark     = isset($_POST['remark']) ? trim($_POST['remark']) : '';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date   = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

        if ($item_id <= 0) sendJSON(array('success' => false, 'message' => 'Item belum dipilih. Klik Find dulu.'));
        if ($qty <= 0) sendJSON(array('success' => false, 'message' => 'Qty harus lebih dari 0.'));
        if ($start_date == '' || $end_date == '') sendJSON(array('success' => false, 'message' => 'Tanggal Start dan End harus diisi.'));

        $wo_mmyy = month_key_from_date($start_date);
        $bom_id  = get_active_bomid($db, $item_id);

        if ($bom_id == 0) sendJSON(array('success' => false, 'message' => 'BOM tidak ditemukan untuk item ini.'));

        $wo_ref = get_or_create_wo_ref($db, $item_id, $wo_mmyy);
        $wo_no  = next_wo_no($db, $wo_ref, $wo_mmyy);

        $sql = "INSERT INTO dbo.WO 
                (WO_MMYY, WO_REF, WO_NO, WO_REM, WO_QTY, PRCD_CODE, MAC_ID, WO_START, WO_END, ITEM_ID, PROC_ID, BOM_ID, WO_CLOSED, STATUS, MODIFY_DATE)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'OPEN', GETDATE())";

        $params = array($wo_mmyy, $wo_ref, $wo_no, $remark, $qty, $prcd_code, $mac_id, $start_date, $end_date, $item_id, $proc_id, $bom_id);
        $stmt = sqlsrv_query($db, $sql, $params);

        if ($stmt) {
            sqlsrv_query($db, "
                UPDATE W
                SET W.WO_CAP = ISNULL(C.CAPD, 0)
                FROM dbo.WO W
                OUTER APPLY (
                    SELECT TOP 1 IP.CAPD
                    FROM dbo.ITEM_CAPD_VIEW IP
                    WHERE IP.ITEM_ID = W.ITEM_ID
                    ORDER BY CASE WHEN IP.PROC_ID = W.PROC_ID THEN 0 ELSE 1 END, IP.CAPD DESC
                ) C
                WHERE W.WO_CAP IS NULL
            ");
            sendJSON(array('success' => true, 'message' => 'Work Order berhasil disimpan!'));
        } else {
            sendJSON(array('success' => false, 'message' => 'Gagal simpan: ' . print_r(sqlsrv_errors(), true)));
        }
    }

    // F. UPDATE WORK ORDER
    if ($action === 'update_input') {
        $item_id     = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $qty         = isset($_POST['qty']) ? intval($_POST['qty']) : 0;
        $mac_id      = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;
        $proc_id     = isset($_POST['proc_id']) ? intval($_POST['proc_id']) : 0;
        $prcd_code   = isset($_POST['prcd_code']) ? trim($_POST['prcd_code']) : 'MP';
        $remark      = isset($_POST['remark']) ? trim($_POST['remark']) : '';
        $start_date  = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date    = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
        $old_mmyy_k  = isset($_POST['wo_mmyy_key']) ? $_POST['wo_mmyy_key'] : '';
        $old_mmyy    = month_key_from_date($old_mmyy_k);
        $new_mmyy    = month_key_from_date($start_date);
        $old_wo_ref  = isset($_POST['wo_ref']) ? intval($_POST['wo_ref']) : 0;
        $old_wo_no   = isset($_POST['wo_no']) ? intval($_POST['wo_no']) : 0;

        if ($new_mmyy != $old_mmyy) {
            $new_wo_ref = get_or_create_wo_ref($db, $item_id, $new_mmyy);
            $new_wo_no  = next_wo_no($db, $new_wo_ref, $new_mmyy);
        } else {
            $new_wo_ref = $old_wo_ref;
            $new_wo_no  = $old_wo_no;
        }

        $bom_id = get_active_bomid($db, $item_id);

        $sql = "UPDATE dbo.WO SET 
                    WO_MMYY = ?, WO_REF = ?, WO_NO = ?, WO_QTY = ?, WO_START = ?, WO_END = ?, 
                    WO_REM = ?, PRCD_CODE = ?, PROC_ID = ?, MAC_ID = ?, BOM_ID = ?, MODIFY_DATE = GETDATE()
                WHERE WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
                  AND WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)
                  AND WO_REF = ? AND WO_NO = ?";

        $params = array($new_mmyy, $new_wo_ref, $new_wo_no, $qty, $start_date, $end_date, $remark, $prcd_code, $proc_id, $mac_id, $bom_id, $old_mmyy, $old_mmyy, $old_wo_ref, $old_wo_no);
        $stmt = sqlsrv_query($db, $sql, $params);

        if ($stmt) {
            sendJSON(array('success' => true, 'message' => 'Work Order berhasil diupdate!'));
        } else {
            sendJSON(array('success' => false, 'message' => 'Gagal update WO: ' . print_r(sqlsrv_errors(), true)));
        }
    }

    // G. DELETE WORK ORDER
    if ($action === 'delete_input') {
        $old_mmyy_k = isset($_POST['wo_mmyy_key']) ? $_POST['wo_mmyy_key'] : '';
        $old_mmyy   = month_key_from_date($old_mmyy_k);
        $wo_ref     = isset($_POST['wo_ref']) ? intval($_POST['wo_ref']) : 0;
        $wo_no      = isset($_POST['wo_no']) ? intval($_POST['wo_no']) : 0;

        $sql = "DELETE FROM dbo.WO 
                WHERE WO_MMYY >= DATEADD(MONTH, DATEDIFF(MONTH, 0, ?), 0)
                  AND WO_MMYY < DATEADD(MONTH, DATEDIFF(MONTH, 0, ?) + 1, 0)
                  AND WO_REF = ? AND WO_NO = ?";

        $stmt = sqlsrv_query($db, $sql, array($old_mmyy, $old_mmyy, $wo_ref, $wo_no));
        if ($stmt) {
            sendJSON(array('success' => true, 'message' => 'Work Order berhasil dihapus!'));
        } else {
            sendJSON(array('success' => false, 'message' => 'Gagal hapus: ' . print_r(sqlsrv_errors(), true)));
        }
    }

    // H. LIST SEMUA WO
    if ($action === 'list_wo') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        
        $searching = isset($_POST['searching']) ? trim($_POST['searching']) : '';
        $proses    = isset($_POST['proses']) ? intval($_POST['proses']) : 0;
        $bulan     = isset($_POST['bulan']) ? trim($_POST['bulan']) : '';
        $tahun     = isset($_POST['tahun']) ? trim($_POST['tahun']) : '';

        $where = array();
        $params = array();

        if ($searching !== '') {
            $where[] = "(W.WO_NUMBER LIKE ? OR I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ? OR C.CUST_COMP LIKE ?)";
            $params[] = '%' . $searching . '%';
            $params[] = '%' . $searching . '%';
            $params[] = '%' . $searching . '%';
            $params[] = '%' . $searching . '%';
        }
        if ($proses > 0) {
            $where[] = "W.PROC_ID = ?";
            $params[] = $proses;
        }
        if ($bulan !== '' && $bulan !== 'ALL') {
            $where[] = "MONTH(W.WO_MMYY) = ?";
            $params[] = intval($bulan);
        }
        if ($tahun !== '' && $tahun !== 'ALL') {
            $where[] = "YEAR(W.WO_MMYY) = ?";
            $params[] = intval($tahun);
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        $sqlList = "SELECT TOP 100 
                        W.WO_ID, W.WO_NUMBER, CONVERT(varchar(10), W.WO_MMYY, 120) AS WO_DATE,
                        P.PROC_NAME, ISNULL(C.CUST_COMP, '') AS CUST_COMP, I.ITEM_CODE, I.ITEM_NO, I.ITEM_NAME, 
                        W.WO_QTY
                    FROM dbo.WO W
                    INNER JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
                    LEFT JOIN dbo.PROCESS P ON W.PROC_ID = P.PROC_ID
                    LEFT JOIN dbo.ITEM_CUSTINFO_VIEW C ON W.ITEM_ID = C.ITEM_ID
                    $whereClause
                    ORDER BY W.WO_ID DESC";

        $stmtList = sqlsrv_query($db, $sqlList, $params);
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-striped table-hover" style="font-size:11px; margin-bottom:0; background:#fff;">
                <thead>
                    <tr style="background:#3c8dbc; color:#fff;">
                        <th style="width:115px;">WO NUMBER</th>
                        <th style="width:80px;">WO DATE</th>
                        <th>PROCESS</th>
                        <th>CUSTOMER</th>
                        <th>ITEM CODE</th>
                        <th>ITEM NO</th>
                        <th>ITEM NAME</th>
                        <th style="width:75px;" class="text-right">WO QTY</th>
                        <th style="width:65px;" class="text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>';
        if ($stmtList && sqlsrv_has_rows($stmtList)) {
            while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
                echo '<tr>
                        <td><b>' . htmlspecialchars($r['WO_NUMBER']) . '</b></td>
                        <td>' . htmlspecialchars($r['WO_DATE']) . '</td>
                        <td>' . htmlspecialchars($r['PROC_NAME']) . '</td>
                        <td>' . htmlspecialchars($r['CUST_COMP']) . '</td>
                        <td><a href="javascript:void(0);" onclick="selectGlobalWo(\'' . htmlspecialchars($r['ITEM_CODE']) . '\')"><b>' . htmlspecialchars($r['ITEM_CODE']) . '</b></a></td>
                        <td>' . htmlspecialchars($r['ITEM_NO']) . '</td>
                        <td>' . htmlspecialchars($r['ITEM_NAME']) . '</td>
                        <td class="text-right font-weight-bold">' . number_format($r['WO_QTY'], 0) . '</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-primary font-weight-bold" onclick="cetakWO(' . intval($r['WO_ID']) . ')" title="Cetak Dokumen WO">
                                <i class="fa fa-print"></i> Cetak
                            </button>
                        </td>
                      </tr>';
            }
            sqlsrv_free_stmt($stmtList);
        } else {
            echo '<tr><td colspan="9" class="text-center text-muted" style="padding:20px;">Tidak ada data Work Order ditemukan.</td></tr>';
        }
        echo '</tbody></table></div>';
        exit;
    }

    sendJSON(array('success' => false, 'message' => 'Action tidak dikenali.'));
}

// =================================================================
// 3. QUERY MASTER DATA DROPDOWN FORM
// =================================================================
$macs = array();
$stmt1 = sqlsrv_query($db, "SELECT MAC_ID, MAC_CODE FROM dbo.MAC ORDER BY MAC_CODE ASC");
if ($stmt1) {
    while ($row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC)) $macs[] = $row;
    sqlsrv_free_stmt($stmt1);
}

$prods = array();
$stmt2 = sqlsrv_query($db, "SELECT PRCD_CODE, PRCD_DESC FROM dbo.PROD_CODE ORDER BY PRCD_CODE ASC");
if ($stmt2) {
    while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) $prods[] = $row;
    sqlsrv_free_stmt($stmt2);
}

$proses = array();
$stmt3 = sqlsrv_query($db, "SELECT PROC_ID, PROC_NAME FROM dbo.PROCESS ORDER BY PROC_ID ASC");
if ($stmt3) {
    while ($row = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC)) $proses[] = $row;
    sqlsrv_free_stmt($stmt3);
}
?>

<style>
    /* COMPACT FIT LAYOUT */
    .compact-box { margin-bottom: 6px !important; }
    .compact-box .box-header { padding: 5px 10px; }
    .compact-box .box-body { padding: 6px 10px; }
    .compact-form .form-group { margin-bottom: 4px; }
    .compact-form label { margin-bottom: 1px; font-size: 11px; font-weight: 600; color: #333; }
    .compact-form .form-control { height: 26px; padding: 2px 6px; font-size: 11px; }

    .item-code-wrap { position: relative; }
    
    /* Dropdown Autocomplete Melayang (Lebar & Terlihat Jelas) */
    #item_suggest_box {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 999999 !important;
        min-width: 440px;
        width: max-content;
        max-width: 580px;
        background: #ffffff;
        border: 1px solid #3c8dbc;
        border-radius: 4px;
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        max-height: 240px;
        overflow-y: auto;
        display: none;
    }
    #item_suggest_box .list-group-item {
        padding: 5px 8px; font-size: 11px; cursor: pointer; border-radius: 0; border: none; border-bottom: 1px solid #eee;
    }
    #item_suggest_box .list-group-item:hover, #item_suggest_box .list-group-item.active {
        background-color: #3c8dbc !important; color: #ffffff !important;
    }
    
    /* Tabel Tengah Terpadu & Fit */
    .table-mid-box { height: 135px; overflow-y: auto; border: 1px solid #d2d6de; background: #fff; }
    #tblWO th, #tblBOM th { background-color: #eaeaea; font-size: 11px; text-align: center; position: sticky; top: 0; z-index: 2; padding: 4px 6px !important; }
    #tblWO td, #tblBOM td { font-size: 11px; vertical-align: middle; padding: 3px 6px !important; }
    #tblWO tr:hover { background-color: #d9edf7 !important; cursor: pointer; }
    .tr-active { background-color: #337ab7 !important; color: #fff !important; font-weight: bold; }
    .tr-active td { color: #fff !important; }
    
    .list-wo-section { border-top: 1px solid #d2d6de; padding-top: 6px; margin-top: 6px; }
</style>

<!-- ======================= FORM INPUT WORK ORDER (COMPACT & FIT) ======================= -->
<div class="box box-primary compact-box">
    <div class="box-header with-border">
        <h3 class="box-title" style="font-size: 13px; font-weight: bold;">
            <i class="fa fa-edit text-primary"></i> <b>INPUT WORK ORDER (WO)</b>
        </h3>
    </div>
    <div class="box-body compact-form" style="background:#f9f9f9;">
        
        <!-- Baris 1: Informasi Item (5 Kolom Simetris) -->
        <div class="row">
            <div class="col-md-2 form-group item-code-wrap">
                <label>Item Code</label>
                <div class="input-group">
                    <input type="text" id="item_code" class="form-control font-weight-bold" list="item_code_datalist" placeholder="Ketik kode...">
                    <span class="input-group-btn">
                        <button type="button" id="btn_find_item" class="btn btn-info btn-flat font-weight-bold" style="height:26px; padding:2px 8px; font-size:11px;" title="Cari Item">
                            <i class="fa fa-search"></i>
                        </button>
                    </span>
                </div>
                <div id="item_suggest_box" class="list-group"></div>
                <datalist id="item_code_datalist"></datalist>
            </div>
            <div class="col-md-2 form-group">
                <label>Item No</label>
                <input type="text" id="item_no" class="form-control" readonly style="background:#eee;">
            </div>
            <div class="col-md-4 form-group">
                <label>Item Name</label>
                <input type="text" id="item_name" class="form-control" readonly style="background:#eee;">
            </div>
            <div class="col-md-2 form-group">
                <label>&nbsp;</label><br>
                <button type="button" id="btn_find_item_full" class="btn btn-info btn-block font-weight-bold" style="height:26px; padding:2px; font-size:11px;">
                    <i class="fa fa-search"></i> Find
                </button>
            </div>
            <div class="col-md-2 form-group">
                <label>WO REF</label>
                <input type="text" id="wo_ref" class="form-control font-weight-bold text-center" readonly style="background:#eee; color:#0056b3;">
            </div>
        </div>

        <!-- Baris 2: Detail WO -->
        <div class="row">
            <div class="col-md-2 form-group">
                <label>WO MmYy</label>
                <input type="date" id="wo_mmyy" class="form-control" readonly style="background:#eee;">
            </div>
            <div class="col-md-2 form-group">
                <label>Qty Target</label>
                <input type="number" id="qty" class="form-control text-right font-weight-bold" value="0">
            </div>
            <div class="col-md-2 form-group">
                <label>Start Date</label>
                <input type="date" id="start_date" class="form-control">
            </div>
            <div class="col-md-2 form-group">
                <label>End Date</label>
                <input type="date" id="end_date" class="form-control">
            </div>
            <div class="col-md-2 form-group">
                <label>Modify Date</label>
                <input type="datetime-local" id="modify_date" class="form-control" readonly style="background:#eee;">
            </div>
            <div class="col-md-2 form-group">
                <label>Remark</label>
                <input type="text" id="remark" class="form-control" placeholder="Catatan...">
            </div>
        </div>

        <!-- Baris 3: Mesin, Proses & Aksi Tombol -->
        <div class="row">
            <div class="col-md-2 form-group">
                <label>M/C (Mesin)</label>
                <select id="mac_id" class="form-control">
                    <option value="">-- pilih --</option>
                    <?php foreach ($macs as $m): ?>
                        <option value="<?php echo htmlspecialchars($m['MAC_ID']); ?>">
                            <?php echo htmlspecialchars($m['MAC_CODE']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label>Prod Type</label>
                <select id="prcd_code" class="form-control">
                    <?php foreach ($prods as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['PRCD_CODE']); ?>">
                            <?php echo htmlspecialchars($p['PRCD_CODE']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label>Proses</label>
                <select id="proc_id" class="form-control">
                    <option value="">-- pilih --</option>
                    <?php foreach ($proses as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['PROC_ID']); ?>">
                            <?php echo htmlspecialchars($p['PROC_ID'] . ' - ' . $p['PROC_NAME']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 form-group" style="padding-top: 18px;">
                <div class="btn-group btn-group-justified">
                    <a href="javascript:void(0);" class="btn btn-default btn-sm font-weight-bold" onclick="newWO()"><i class="fa fa-file-o"></i> New</a>
                    <a href="javascript:void(0);" class="btn btn-success btn-sm font-weight-bold" onclick="saveWO()"><i class="fa fa-save"></i> Save</a>
                    <a href="javascript:void(0);" class="btn btn-warning btn-sm font-weight-bold" onclick="updateWO()"><i class="fa fa-pencil"></i> Update</a>
                    <a href="javascript:void(0);" class="btn btn-danger btn-sm font-weight-bold" onclick="deleteWO()"><i class="fa fa-trash"></i> Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================= BAGIAN TABEL TENGAH (COMPACT) ======================= -->
<div class="row">
    <!-- TABEL DAFTAR WO ITEM INI -->
    <div class="col-md-7">
        <div class="box box-solid box-default compact-box">
            <div class="box-header with-border" style="background:#f4f4f4;">
                <h3 class="box-title" style="font-size:12px; font-weight:bold;"><i class="fa fa-list"></i> Daftar WO Item Ini (Bulan Berjalan)</h3>
            </div>
            <div class="box-body no-padding">
                <div class="table-mid-box">
                    <table class="table table-bordered table-striped table-hover" id="tblWO">
                        <thead>
                            <tr>
                                <th style="width:55px;">WO NO</th>
                                <th>WO NUMBER</th>
                                <th style="width:65px;">M/C</th>
                                <th style="width:75px;" class="text-right">QTY</th>
                                <th>PROCESS</th>
                                <th style="width:85px;">START</th>
                                <th style="width:85px;">END</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="7" class="text-center text-muted" style="padding:15px;">Belum ada data. Silakan cari Item terlebih dahulu.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr style="background:#f5f5f5; font-weight:bold;">
                                <td colspan="3" style="text-align:right;">TOTAL QTY:</td>
                                <td id="total_wo_qty" style="text-align:right;">0</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL BILL OF MATERIALS (BOM) -->
    <div class="col-md-5">
        <div class="box box-solid box-default compact-box">
            <div class="box-header with-border" style="background:#f4f4f4;">
                <h3 class="box-title" style="font-size:12px; font-weight:bold;"><i class="fa fa-cubes"></i> Bill of Materials (BOM)</h3>
            </div>
            <div class="box-body no-padding">
                <div class="table-mid-box">
                    <table class="table table-bordered table-striped" id="tblBOM">
                        <thead>
                            <tr>
                                <th>BOM_ID</th>
                                <th>ITEM CODE</th>
                                <th>NAME</th>
                                <th class="text-center" style="width:55px;">QTY</th>
                                <th class="text-center" style="width:45px;">UNIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted" style="padding:15px;">Belum ada data BOM.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================= SEMUA WORK ORDER (GLOBAL LIST) ======================= -->
<div class="list-wo-section">
    <div class="box box-solid box-primary compact-box">
        <div class="box-header with-border" style="padding: 5px 10px;">
            <div class="row">
                <div class="col-md-4">
                    <h3 class="box-title" style="font-size: 13px; font-weight: bold; line-height: 26px;">
                        <i class="fa fa-tasks"></i> Semua Data Work Order
                    </h3>
                </div>
                <div class="col-md-8 text-right form-inline">
                    <select id="proses_filter" class="form-control input-sm" style="height:24px; padding:1px 6px; font-size:11px; width:130px;">
                        <option value="0">-- ALL PROSES --</option>
                        <?php foreach ($proses as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['PROC_ID']); ?>"><?php echo htmlspecialchars($p['PROC_NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="bulan_filter" class="form-control input-sm" style="height:24px; padding:1px 6px; font-size:11px; width:100px;">
                        <option value="ALL">-- BULAN --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                        <?php endfor; ?>
                    </select>

                    <select id="tahun_filter" class="form-control input-sm" style="height:24px; padding:1px 6px; font-size:11px; width:80px;">
                        <?php 
                        $curY = intval(date('Y'));
                        for ($y = $curY - 2; $y <= $curY + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($curY == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <input type="text" id="search_filter" class="form-control input-sm" style="height:24px; padding:1px 6px; font-size:11px; width:150px;" placeholder="Search WO / Part...">
                    <button class="btn btn-sm btn-primary" onclick="pagination()" style="height:24px; padding:1px 8px; font-size:11px;"><i class="fa fa-search"></i> Cari</button>
                </div>
            </div>
        </div>
        
        <div id="datatables" class="box-body no-padding" style="background:#fff; min-height: 100px;">
            <p class="text-center text-muted" style="padding:15px;">Memuat data Work Order...</p>
        </div>
    </div>
</div>

<script>
var baseUrlWO = '<?php echo basename(__FILE__); ?>?action=';
var currentItemID = "";
var selectedWO = null;
var itemSearchTimer = null;
var itemSuggestIndex = -1;

function pad2(n){ return (n < 10 ? '0' : '') + n; }
function today(){ var d = new Date(); return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
function nowDateTimeLocal(){
    var d = new Date();
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
}
function sqlDateTimeToInput(v){
    if(!v) return nowDateTimeLocal();
    v = String(v).replace(' ', 'T');
    return v.length >= 16 ? v.substring(0, 16) : nowDateTimeLocal();
}
function inputDateTimeToSql(v){
    if(!v) return '';
    v = String(v).replace('T', ' ');
    return v.length == 16 ? v + ':00' : v;
}
function defaultDateFromFilter(){ return today(); }
function syncWoMmYyFromFilter(){ $('#wo_mmyy').val(today().substring(0, 7) + '-01'); } 
function syncEndDate(){
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    if(startDate == ''){ startDate = defaultDateFromFilter(); $('#start_date').val(startDate); }
    if(endDate == '' || endDate < startDate){ $('#end_date').val(startDate); }
}

function hideItemSuggest(){ itemSuggestIndex = -1; $('#item_suggest_box').hide().empty(); }
function setActiveSuggest(index){
    var items = $('#item_suggest_box .list-group-item');
    if(items.length <= 0){ itemSuggestIndex = -1; return; }
    if(index < 0) index = items.length - 1;
    if(index >= items.length) index = 0;
    itemSuggestIndex = index;
    items.removeClass('active');
    items.eq(itemSuggestIndex).addClass('active');
}
function chooseActiveSuggest(){
    var items = $('#item_suggest_box .list-group-item');
    if(items.length <= 0) return false;
    if(itemSuggestIndex < 0) itemSuggestIndex = 0;
    items.eq(itemSuggestIndex).trigger('click');
    return true;
}
function isSuggestVisible(){ return $('#item_suggest_box').is(':visible') && $('#item_suggest_box .list-group-item').length > 0; }

// ==============================================================
// AUTOCOMPLETE ITEM CODE (DUAL ENGINE: SUGGEST BOX & DATALIST)
// ==============================================================
function loadItemAutocomplete(term){
    term = $.trim(term);
    if(term.length < 1){ hideItemSuggest(); return; }
    
    $.ajax({
        url: baseUrlWO + 'search_item_code',
        type: 'POST', 
        dataType: 'text',
        data: { term: term },
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start){
                raw = raw.substring(start, end + 1);
            }
            
            var res;
            try {
                res = JSON.parse(raw);
            } catch(e) {
                hideItemSuggest();
                return;
            }

            var box = $('#item_suggest_box'); 
            box.empty();
            var dlist = '';

            if(!res || !res.success || !res.data || res.data.length <= 0){ 
                hideItemSuggest(); 
                return; 
            }

            $.each(res.data, function(i, r){
                var label = '<b>' + (r.ITEM_CODE || '') + '</b> | ' + (r.ITEM_NO || '') + ' | ' + (r.ITEM_NAME || '');
                var a = $('<a href="javascript:void(0);" class="list-group-item"></a>');
                a.html(label).attr('data-index', i);
                a.on('mouseenter', function(){ setActiveSuggest(parseInt($(this).attr('data-index'), 10)); });
                a.click(function(){
                    $('#item_code').val(r.ITEM_CODE);
                    $('#item_no').val(r.ITEM_NO);
                    $('#item_name').val(r.ITEM_NAME);
                    currentItemID = r.ITEM_ID;
                    hideItemSuggest(); 
                    findItem();
                });
                box.append(a);
                dlist += '<option value="' + r.ITEM_CODE + '">' + (r.ITEM_NO ? r.ITEM_NO + ' - ' : '') + r.ITEM_NAME + '</option>';
            });

            $('#item_code_datalist').html(dlist);
            box.show(); 
            setActiveSuggest(0);
        },
        error: function(){ hideItemSuggest(); }
    });
}

function newWO(){
    selectedWO = null;
    currentItemID = "";
    $('#item_code').val(''); $('#item_no').val(''); $('#item_name').val('');
    $('#wo_ref').val(''); $('#qty').val('0'); $('#remark').val('');
    $('#start_date').val(defaultDateFromFilter());
    $('#end_date').val(defaultDateFromFilter());
    syncWoMmYyFromFilter();
    $('#modify_date').val(nowDateTimeLocal());
    $('#mac_id').val(''); $('#prcd_code').val('MP'); $('#proc_id').val('');
    $('#tblWO tbody').html('<tr><td colspan="7" class="text-center text-muted" style="padding:15px;">Belum ada data. Silakan cari Item terlebih dahulu.</td></tr>');
    $('#tblBOM tbody').html('<tr><td colspan="5" class="text-center text-muted" style="padding:15px;">Belum ada data BOM.</td></tr>');
    $('#total_wo_qty').text('0');
    $('#item_code').focus();
}

function findItem(){
    var itemCode = $.trim($('#item_code').val());
    if(itemCode == ''){ alert('Isi ITEM_CODE terlebih dahulu.'); $('#item_code').focus(); return; }
    syncWoMmYyFromFilter(); syncEndDate();
    $('#btn_find_item, #btn_find_item_full').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.ajax({
        url: baseUrlWO + 'find_item',
        type: 'POST', dataType: 'text', data: { item_code: itemCode },
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            if(!res || !res.success){ alert(res && res.message ? res.message : 'ITEM_CODE tidak ditemukan.'); return; }
            var d = res.data;
            currentItemID = d.ITEM_ID;
            $('#item_code').val(d.ITEM_CODE); $('#item_no').val(d.ITEM_NO); $('#item_name').val(d.ITEM_NAME);
            if(d.MAC_ID) $('#mac_id').val(d.MAC_ID);
            if(d.PROC_ID) $('#proc_id').val(d.PROC_ID);
            loadWOInput();
        },
        error: function(xhr){ alert('Error saat Find Item: ' + xhr.status); },
        complete: function(){ 
            $('#btn_find_item').prop('disabled', false).html('<i class="fa fa-search"></i>');
            $('#btn_find_item_full').prop('disabled', false).html('<i class="fa fa-search"></i> Find');
        }
    });
}

function loadWOInput(){
    if(currentItemID == '') return;
    syncWoMmYyFromFilter();

    $.ajax({
        url: baseUrlWO + 'load_wo_input',
        type: 'POST', dataType: 'text',
        data: { item_id: currentItemID, wo_mmyy: $('#wo_mmyy').val() },
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            var tb = $('#tblWO tbody'); tb.empty();
            selectedWO = null;
            var totalQty = 0;

            if(!res || !res.success){ alert('Gagal load WO.'); return; }
            if(!res.data || res.data.length <= 0){ 
                tb.append('<tr><td colspan="7" class="text-center text-muted" style="padding:15px;">Tidak ada WO di bulan ini.</td></tr>'); 
                $('#total_wo_qty').text('0');
                return; 
            }
            $.each(res.data, function(i, r){
                var rowQty = parseFloat(r.WO_QTY) || 0;
                totalQty += rowQty;

                var tr = $('<tr></tr>');
                tr.append('<td class="text-center"><b>'+(r.WO_NO || '')+'</b></td>');
                tr.append('<td><b>'+(r.WO_NUMBER || '')+'</b></td>');
                tr.append('<td class="text-center">'+(r.MAC_CODE || '')+'</td>');
                tr.append('<td class="text-right font-weight-bold">'+(rowQty.toLocaleString('id-ID'))+'</td>');
                tr.append('<td>'+(r.PROC_NAME || '')+'</td>');
                tr.append('<td>'+(r.WO_START || '')+'</td>');
                tr.append('<td>'+(r.WO_END || '')+'</td>');
                tr.click(function(){ selectWOInput(r, tr, true); });
                tb.append(tr);
            });
            $('#total_wo_qty').text(totalQty.toLocaleString('id-ID'));
            selectWOInput(res.data[0], $('#tblWO tbody tr:first'), false);
        }
    });
}

function selectWOInput(r, tr, loadBom){
    selectedWO = r;
    $('#tblWO tbody tr').removeClass('tr-active');
    tr.addClass('tr-active');

    $('#wo_ref').val(r.WO_REF || '');
    $('#qty').val(r.WO_QTY || 0);
    $('#remark').val(r.WO_REM || '');
    $('#start_date').val(r.WO_START || '');
    $('#end_date').val(r.WO_END || '');
    $('#wo_mmyy').val(r.WO_MMYY || $('#wo_mmyy').val());
    $('#modify_date').val(sqlDateTimeToInput(r.MODIFY_DATE));
    $('#mac_id').val(r.MAC_ID || '');
    $('#prcd_code').val(r.PRCD_CODE || 'MP');
    $('#proc_id').val(r.PROC_ID || '');

    if(loadBom === true) loadBOMInput(r.BOM_ID);
}

function loadBOMInput(bom_id){
    if(!bom_id){
        $('#tblBOM tbody').html('<tr><td colspan="5" class="text-center text-muted" style="padding:15px;">BOM ID tidak ditemukan.</td></tr>');
        return;
    }
    $.ajax({
        url: baseUrlWO + 'load_bom_input',
        type: 'POST', dataType: 'text', data: { bom_id: bom_id },
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            var tb = $('#tblBOM tbody'); tb.empty();
            if(!res || !res.success || !res.data || res.data.length <= 0) { 
                tb.html('<tr><td colspan="5" class="text-center text-muted" style="padding:15px;">Detail BOM tidak tersedia.</td></tr>'); 
                return; 
            }
            $.each(res.data, function(i, r){
                var bQty = parseFloat(r.QTY) || 0;
                tb.append(
                    '<tr><td class="text-center">'+(r.BOM_ID || '')+'</td><td><b>'+(r.ITEM_CODE || '')+'</b></td><td>'+(r.ITEM_NAME || '')+'</td><td class="text-right font-weight-bold">'+(bQty.toLocaleString('id-ID'))+'</td><td class="text-center">'+(r.UNIT || '')+'</td></tr>'
                );
            });
        }
    });
}

function formDataWO(){
    syncEndDate();
    return {
        item_id: currentItemID, wo_mmyy: $('#wo_mmyy').val(), qty: $('#qty').val(),
        mac_id: $('#mac_id').val(), prcd_code: $('#prcd_code').val(), proc_id: $('#proc_id').val(),
        start_date: $('#start_date').val(), end_date: $('#end_date').val(),
        remark: $('#remark').val()
    };
}

function validateWOForm(){
    if(currentItemID == ''){ alert('Pilih / Find Item dulu.'); return false; }
    var qty = parseInt($('#qty').val() || '0', 10);
    if(isNaN(qty) || qty <= 0){ alert('Qty harus lebih dari 0.'); $('#qty').focus(); return false; }
    if($('#start_date').val() == ''){ alert('Tanggal Start harus diisi.'); $('#start_date').focus(); return false; }
    if($('#end_date').val() == ''){ alert('Tanggal End harus diisi.'); $('#end_date').focus(); return false; }
    if($('#end_date').val() < $('#start_date').val()){ alert('Tanggal End tidak boleh lebih kecil dari Start.'); $('#end_date').focus(); return false; }
    if($('#mac_id').val() == ''){ alert('Pilih M/C dulu.'); $('#mac_id').focus(); return false; }
    if($('#proc_id').val() == ''){ alert('Pilih Proses dulu.'); $('#proc_id').focus(); return false; }
    return true;
}

function saveWO(){
    if(!validateWOForm()) return;
    if(!confirm('Apakah Anda yakin ingin menyimpan Work Order ini?')) return;
    $.ajax({
        url: baseUrlWO + 'save_input', type: 'POST', dataType: 'text', data: formDataWO(),
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            alert(res.message);
            if(res.success){ syncWoMmYyFromFilter(); loadWOInput(); pagination(); }
        }
    });
}

function updateWO(){
    if(!selectedWO){ alert('Pilih data WO pada tabel terlebih dahulu.'); return; }
    if(!validateWOForm()) return;
    if(!confirm('Apakah Anda yakin ingin mengupdate Work Order ini?')) return;
    var d = formDataWO(); d.wo_mmyy_key = selectedWO.WO_MMYY; d.wo_ref = selectedWO.WO_REF; d.wo_no = selectedWO.WO_NO;
    $.ajax({
        url: baseUrlWO + 'update_input', type: 'POST', dataType: 'text', data: d,
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            alert(res.message);
            if(res.success){ syncWoMmYyFromFilter(); loadWOInput(); pagination(); }
        }
    });
}

function deleteWO(){
    if(!selectedWO){ alert('Pilih data WO pada tabel terlebih dahulu.'); return; }
    if(!confirm('Apakah Anda yakin ingin MENGHAPUS Work Order ini?')) return;
    $.ajax({
        url: baseUrlWO + 'delete_input', type: 'POST', dataType: 'text',
        data: { wo_mmyy_key: selectedWO.WO_MMYY, wo_ref: selectedWO.WO_REF, wo_no: selectedWO.WO_NO },
        success: function(raw){
            var start = raw.indexOf('{');
            var end = raw.lastIndexOf('}');
            if(start !== -1 && end !== -1 && end > start) raw = raw.substring(start, end + 1);
            var res = JSON.parse(raw);

            alert(res.message);
            if(res.success){ loadWOInput(); pagination(); }
        }
    });
}

function pagination(){
    var searching = $('#search_filter').val();
    var proses    = $('#proses_filter').val();
    var bulan     = $('#bulan_filter').val();
    var tahun     = $('#tahun_filter').val();

    $('#datatables').html('<p class="text-center text-primary" style="padding:20px;"><i class="fa fa-spinner fa-spin"></i> Memuat data...</p>');

    $.ajax({
        type: 'POST',
        url: baseUrlWO + 'list_wo',
        data: { searching: searching, proses: proses, bulan: bulan, tahun: tahun },
        success: function(html){
            $('#datatables').html(html);
        },
        error: function(xhr){
            $('#datatables').html('<p class="text-center text-danger" style="padding:15px;">Gagal memuat list WO: ' + xhr.status + '</p>');
        }
    });        
}

function selectGlobalWo(itemCode){
    $('#item_code').val(itemCode);
    findItem();
    $('html, body').animate({ scrollTop: 0 }, 'fast');
}

function cetakWO(woId){
    window.open(baseUrlWO + 'cetak_wo&id=' + woId, '_blank', 'width=840,height=900,scrollbars=yes');
}

$(document).ready(function(){
    $('#start_date').val(defaultDateFromFilter());
    $('#end_date').val(defaultDateFromFilter());
    $('#modify_date').val(nowDateTimeLocal());
    syncWoMmYyFromFilter(); syncEndDate();
    
    pagination();

    $('#btn_find_item, #btn_find_item_full').click(function(e){ e.preventDefault(); findItem(); });
    
    // Navigasi Keyboard Autocomplete
    $('#item_code').on('keydown', function(e){
        if(e.which == 40){ if(isSuggestVisible()){ e.preventDefault(); setActiveSuggest(itemSuggestIndex + 1); } return; }
        if(e.which == 38){ if(isSuggestVisible()){ e.preventDefault(); setActiveSuggest(itemSuggestIndex - 1); } return; }
        if(e.which == 13){ e.preventDefault(); if(isSuggestVisible()){ chooseActiveSuggest(); }else{ hideItemSuggest(); findItem(); } return; }
        if(e.which == 27){ hideItemSuggest(); return; }
    });
    
    // Pencarian Input Real-Time
    $('#item_code').on('input keyup', function(e){
        if(e.which == 38 || e.which == 40 || e.which == 13 || e.which == 27) return;
        var term = $(this).val();
        clearTimeout(itemSearchTimer);
        itemSearchTimer = setTimeout(function(){ loadItemAutocomplete(term); }, 150);
    });

    $('#item_code').on('change', function() {
        if(!isSuggestVisible()) findItem();
    });
    
    $(document).click(function(e){ if(!$(e.target).closest('#item_code, #item_suggest_box').length) hideItemSuggest(); });
    $('#start_date').change(function(){ syncEndDate(); syncWoMmYyFromFilter(); });
    $('#end_date').change(function(){ syncEndDate(); });
    $('#search_filter').keyup(function(e){ if(e.which == 13) pagination(); });
    $('#proses_filter, #bulan_filter, #tahun_filter').change(function(){ pagination(); });
});
</script>