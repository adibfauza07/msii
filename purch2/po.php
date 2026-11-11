<?php
if (session_id() == "") session_start();

require_once dirname(__DIR__) . "/config/db_plant2.php";
if ($conn === false) die("Koneksi database gagal.");

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function sql_error_text() { return print_r(sqlsrv_errors(), true); }
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }

function fmt_date($v) {
    if ($v instanceof DateTime) return $v->format("Y-m-d");
    if ($v === "" || $v === null) return date("Y-m-d");
    $t = strtotime((string)$v);
    return $t === false ? date("Y-m-d") : date("Y-m-d", $t);
}

function fmt_date_view($v) {
    if ($v instanceof DateTime) return $v->format("d-M-Y");
    if ($v === "" || $v === null) return "";
    $t = strtotime((string)$v);
    return $t === false ? "" : date("d-M-Y", $t);
}

function roman_month($m) {
    $r = array(1=>"I",2=>"II",3=>"III",4=>"IV",5=>"V",6=>"VI",7=>"VII",8=>"VIII",9=>"IX",10=>"X",11=>"XI",12=>"XII");
    $m = intval($m);
    return isset($r[$m]) ? $r[$m] : "I";
}

function get_refs($conn) {
    $ret = array("NEXT_PO"=>1, "PO_NUM"=>"%s/IMC/PO/%s/%s");
    $stmt = @sqlsrv_query($conn, "SELECT TOP 1 NEXT_PO, PO_NUM FROM dbo.REFS");
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            if (isset($r["NEXT_PO"])) $ret["NEXT_PO"] = intval($r["NEXT_PO"]);
            if (isset($r["PO_NUM"]) && trim((string)$r["PO_NUM"]) !== "") $ret["PO_NUM"] = trim((string)$r["PO_NUM"]);
        }
    }
    return $ret;
}

function generate_po_num($conn, $poDate) {
    $refs = get_refs($conn);
    $next = intval($refs["NEXT_PO"]);
    if ($next <= 0) $next = 1;

    $nextText = str_pad($next, 3, "0", STR_PAD_LEFT);
    $roman = roman_month(date("n", strtotime($poDate)));
    $year = date("Y", strtotime($poDate));
    $poNum = @sprintf(trim($refs["PO_NUM"]), $nextText, $roman, $year);

    if ($poNum == "" || strpos($poNum, "%") !== false) $poNum = $nextText . "/IMC/PO/" . $roman . "/" . $year;
    return trim($poNum);
}

function inc_next_po($conn) {
    @sqlsrv_query($conn, "UPDATE TOP (1) dbo.REFS SET NEXT_PO = ISNULL(NEXT_PO, 0) + 1");
}

function find_item_id_by_code($conn, $code) {
    $code = trim((string)$code);
    if ($code === "") return 0;
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?", array($code));
    if ($stmt === false) return 0;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $r ? intval($r["ITEM_ID"]) : 0;
}

function get_quotation_price($conn, $supId, $itemId, $cur) {
    $supId = intval($supId); $itemId = intval($itemId); $cur = trim((string)$cur);
    if ($supId <= 0 || $itemId <= 0 || $cur === "") return 0;

    $sql = "
        SELECT TOP 1 D.QUOD_PRICE
        FROM dbo.QUOT_DETAIL D
        INNER JOIN dbo.QUOTATION Q ON D.QUO_ID = Q.QUO_ID
        WHERE Q.SUP_ID = ?
          AND D.ITEM_ID = ?
          AND Q.CURR_CODE = ?
          AND ISNULL(D.QUOD_PRICE,0) > 0
        ORDER BY
          CASE WHEN ISNULL(D.QUOD_ACTIVE,0)=1 THEN 0 ELSE 1 END,
          Q.QUO_DATE DESC,
          Q.QUO_ID DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, array($supId, $itemId, $cur));
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) return floatval($r["QUOD_PRICE"]);
    }
    return 0;
}

/* AJAX rate quotation */
if (getv("action") == "rate_json") {
    header("Content-Type: application/json; charset=utf-8");
    $price = get_quotation_price($conn, intval(getv("sup_id","0")), intval(getv("item_id","0")), getv("curr_code",""));
    echo json_encode(array("ok"=>$price>0?1:0, "price"=>$price));
    exit;
}

/* RECEIVE LIST POPUP */
if (getv("action") == "receive_view") {
    $poNum = getv("po_num","");
    $itemId = intval(getv("item_id","0"));

    $sql = "
        SELECT
            R.RCV_DATE,
            R.RCV_NO,
            R.RCV_DONO,
            RD.RCVD_QTY,
            S.SUP_CODE,
            S.SUP_COMP,
            I.ITEM_CODE,
            I.ITEM_NAME
        FROM dbo.RECEIVE_DETAIL RD
        INNER JOIN dbo.RECEIVE R ON R.RCV_ID = RD.RCV_ID
        INNER JOIN dbo.PO P ON P.PO_ID = RD.PO_ID
        INNER JOIN dbo.ITEMS I ON I.ITEM_ID = RD.ITEM_ID
        INNER JOIN dbo.SUPPLIER S ON S.SUP_ID = P.SUP_ID AND S.SUP_ID = R.SUP_ID
        WHERE RD.ITEM_ID = ?
          AND P.PO_NUM = ?
        ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, array($itemId, $poNum));
    if ($stmt === false) die("<pre>Query Receive error:\n".sql_error_text()."</pre>");

    $rows = array();
    $total = 0;
    $itemCode = "";
    $itemName = "";

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($itemCode === "") {
            $itemCode = trim((string)$r["ITEM_CODE"]);
            $itemName = trim((string)$r["ITEM_NAME"]);
        }
        $total += floatval($r["RCVD_QTY"]);
        $rows[] = $r;
    }

    if ($itemCode === "") {
        $stmtItem = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE, ITEM_NAME FROM dbo.ITEMS WHERE ITEM_ID = ?", array($itemId));
        if ($stmtItem !== false) {
            $ri = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC);
            if ($ri) {
                $itemCode = trim((string)$ri["ITEM_CODE"]);
                $itemName = trim((string)$ri["ITEM_NAME"]);
            }
        }
    }
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Receive List</title>
        <!-- AdminLTE CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    </head>
    <body class="p-3 bg-light">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">RECEIVE LIST - PO: <?php echo h($poNum); ?> / ITEM: <?php echo h($itemCode." - ".$itemName); ?></h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-striped table-sm text-sm m-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th style="width:120px;">Receive Date</th>
                            <th style="width:130px;">Receive No</th>
                            <th style="width:140px;">DO No</th>
                            <th class="text-right" style="width:100px;">Qty</th>
                            <th style="width:100px;">Sup.Code</th>
                            <th>Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach($rows as $r) { ?>
                            <tr>
                                <td class="text-center"><?php echo h($no); ?></td>
                                <td><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td>
                                <td><?php echo h($r["RCV_NO"]); ?></td>
                                <td><?php echo h($r["RCV_DONO"]); ?></td>
                                <td class="text-right"><?php echo h(number_format(floatval($r["RCVD_QTY"]),2,".",",")); ?></td>
                                <td><?php echo h($r["SUP_CODE"]); ?></td>
                                <td><?php echo h($r["SUP_COMP"]); ?></td>
                            </tr>
                            <?php $no++; } ?>
                        <?php if ($no == 1) { ?>
                            <tr><td colspan="7" class="text-center text-muted">Data receive belum ada.</td></tr>
                        <?php } else { ?>
                            <tr class="table-secondary font-weight-bold">
                                <td colspan="4" class="text-right">TOTAL</td>
                                <td class="text-right"><?php echo h(number_format($total,2,".",",")); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">CLOSE</button>
            </div>
        </div>
    </body>
    </html>
<?php
    exit;
}

$message = "";
$error = "";
$action = postv("action","");
$editId = intval(getv("edit","0"));
$q = getv("q", ""); 

/* DELETE */
if ($action == "delete") {
    $poId = intval(postv("po_id","0"));
    if ($poId <= 0) {
        $error = "Pilih PO dulu.";
    } else {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.PO_DETAIL WHERE PO_ID = ?", array($poId));
        if ($stmtCek === false) {
            $error = "Cek detail PO gagal:\n".sql_error_text();
        } else {
            $rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            if (intval($rc["CNT"]) > 0) {
                $error = "Tidak dapat menghapus, masih terdapat detailnya.";
            } else {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.PO WHERE PO_ID = ?", array($poId));
                if ($stmtDel === false) $error = "Delete PO gagal:\n".sql_error_text();
                else { header("Location: po.php?msg=deleted"); exit; }
            }
        }
    }
}

/* SAVE */
if ($action == "save") {
    $poId = intval(postv("po_id","0"));
    $poNum = strtoupper(postv("po_num",""));
    $poDate = postv("po_date",date("Y-m-d"));
    $poDateDo = postv("po_datedo",date("Y-m-d"));
    $supId = intval(postv("sup_id","0"));
    $poTo = intval(postv("po_to","0"));
    $poToDef = isset($_POST["po_todef"]) ? 1 : 0;
    $poCur = strtoupper(postv("po_cur","IDR"));
    $poClose = isset($_POST["po_close"]) ? 1 : 0;
    $poTerm = postv("po_term","");
    $poTermDel = postv("po_termdel","");
    $poTermDelSch = isset($_POST["po_termdelsch"]) ? 1 : 0;
    $poRem = postv("po_rem","");
    $allowEmpty = intval(postv("allow_empty_detail","0"));

    if ($poDate == "") $poDate = date("Y-m-d");
    if ($poDateDo == "") $poDateDo = $poDate;

    $autoNoUsed = false;
    if ($poNum == "") {
        $poNum = generate_po_num($conn, $poDate);
        $autoNoUsed = true;
    }

if ($supId <= 0) $error = "Supplier wajib dipilih.";
    // elseif ($poTo <= 0) $error = "Invoice To wajib dipilih."; // Baris ini dimatikan
    elseif ($poCur == "") $error = "Currency wajib diisi.";

    if ($error == "") {
        $reqIds = isset($_POST["req_id"]) ? $_POST["req_id"] : array();
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array();
        $podDues = isset($_POST["pod_due"]) ? $_POST["pod_due"] : array();
        $podQtys = isset($_POST["pod_qty"]) ? $_POST["pod_qty"] : array();
        $podPrices = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array();
        $podUnits = isset($_POST["pod_unit"]) ? $_POST["pod_unit"] : array();

        $hasDetail = false;
        for ($i=0; $i<count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]);
            $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
            if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
            $qty = isset($podQtys[$i]) ? to_float($podQtys[$i]) : 0;
            if ($itemId > 0 && $qty > 0) { $hasDetail = true; break; }
        }

        if (!$hasDetail && !($allowEmpty == 1 && $poId > 0)) {
            $error = "Detail PO minimal 1 baris dan qty harus lebih dari 0.";
        } else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if ($poId > 0) {
                $sqlH = "UPDATE dbo.PO SET SUP_ID=?, PO_DATE=?, PO_TO=?, PO_TODEF=?, PO_CUR=?, PO_CLOSE=?, PO_TERM=?, PO_TERMDEL=?, PO_TERMDELSCH=?, PO_REV=ISNULL(PO_REV,0)+1, PO_DATEDO=?, PO_NUM=?, PO_REM=? WHERE PO_ID=?";
                $paramsH = array($supId,$poDate,$poTo,$poToDef,$poCur,$poClose,$poTerm,$poTermDel,$poTermDelSch,$poDateDo,$poNum,$poRem,$poId);
                $stmtH = sqlsrv_query($conn,$sqlH,$paramsH);
                if ($stmtH === false) { $ok=false; $error="Simpan header PO gagal:\n".sql_error_text(); }
            } else {
                $sqlH = "INSERT INTO dbo.PO (SUP_ID, PO_DATE, PO_TO, PO_TODEF, PO_CUR, PO_CLOSE, PO_TERM, PO_TERMDEL, PO_TERMDELSCH, PO_REV, PO_DATEDO, PO_NUM, PO_REM) OUTPUT INSERTED.PO_ID VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
                $paramsH = array($supId,$poDate,$poTo,$poToDef,$poCur,$poClose,$poTerm,$poTermDel,$poTermDelSch,$poDateDo,$poNum,$poRem);
                $stmtH = sqlsrv_query($conn,$sqlH,$paramsH);
                if ($stmtH === false) {
                    $ok=false; $error="Simpan header PO gagal:\n".sql_error_text();
                } else {
                    $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC);
                    if ($new) $poId = intval($new[0]);
                    else { $ok=false; $error="PO_ID baru tidak terbaca."; }
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.PO_DETAIL WHERE PO_ID = ?", array($poId));
                if ($stmtDel === false) { $ok=false; $error="Hapus detail lama gagal:\n".sql_error_text(); }
            }

            if ($ok) {
                for ($i=0; $i<count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]);
                    $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                    if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);

                    $reqId = isset($reqIds[$i]) ? intval($reqIds[$i]) : 0;
                    $podDue = isset($podDues[$i]) ? trim((string)$podDues[$i]) : "";
                    $qty = isset($podQtys[$i]) ? to_float($podQtys[$i]) : 0;
                    $price = isset($podPrices[$i]) ? to_float($podPrices[$i]) : 0;
                    $unit = isset($podUnits[$i]) ? trim((string)$podUnits[$i]) : "";

                    if ($itemId <= 0 || $qty <= 0) continue;
                    if ($price <= 0) $price = get_quotation_price($conn, $supId, $itemId, $poCur);
                    if ($price <= 0) { $ok=false; $error="Price / Quotation tidak dapat ditemukan untuk item ".$itemCode; break; }
                    if ($podDue == "") $podDue = $poDateDo;

                    $sqlD = "INSERT INTO dbo.PO_DETAIL (PO_ID, REQ_ID, ITEM_ID, POD_QTY, POD_PRICE, POD_UNIT, POD_DUE) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $paramsD = array($poId,$reqId,$itemId,$qty,$price,$unit,$podDue);
                    $stmtD = sqlsrv_query($conn,$sqlD,$paramsD);
                    if ($stmtD === false) { $ok=false; $error="Simpan detail gagal:\n".sql_error_text(); break; }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                if ($autoNoUsed) inc_next_po($conn);
                header("Location: po.php?edit=".$poId."&msg=saved");
                exit;
            } else {
                sqlsrv_rollback($conn);
                if ($error == "") $error = "Simpan PO gagal:\n".sql_error_text();
            }
        }
    }
}

/* LOAD HEADER */
$header = array(
    "PO_ID"=>"", "PO_NUM"=>"", "PO_DATE"=>date("Y-m-d"), "PO_DATEDO"=>date("Y-m-d"),
    "SUP_ID"=>"", "SUP_CODE"=>"", "SUP_COMP"=>"", "PO_TO"=>"", "TO_SUP_CODE"=>"", "TO_SUP_COMP"=>"",
    "PO_TODEF"=>0, "PO_CUR"=>"IDR", "PO_CLOSE"=>0, "PO_TERM"=>"", "PO_TERMDEL"=>"", "PO_TERMDELSCH"=>0, "PO_REM"=>""
);
$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.SUP_ID, P.PO_TO, P.PO_TODEF, P.PO_CUR, P.PO_CLOSE, P.PO_TERM, P.PO_TERMDEL, P.PO_TERMDELSCH, P.PO_REM, S.SUP_CODE, S.SUP_COMP, ST.SUP_CODE AS TO_SUP_CODE, ST.SUP_COMP AS TO_SUP_COMP
        FROM dbo.PO P LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID LEFT JOIN dbo.SUPPLIER ST ON P.PO_TO = ST.SUP_ID WHERE P.PO_ID = ?
    ";
    $stmtH = sqlsrv_query($conn,$sqlH,array($editId));
    if ($stmtH !== false) {
        $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
        if ($rh) {
            foreach ($header as $k=>$v) if (isset($rh[$k])) $header[$k] = $rh[$k];
            $header["PO_DATE"] = fmt_date($header["PO_DATE"]);
            $header["PO_DATEDO"] = fmt_date($header["PO_DATEDO"]);
        }
    }

    $sqlD = "
        SELECT D.PO_ID, D.REQ_ID, D.ITEM_ID, D.POD_QTY, D.POD_PRICE, D.POD_UNIT, D.POD_DUE, (ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)) AS POD_AMOUNT, I.ITEM_CODE, I.ITEM_NAME, R.REQ_NO
        FROM dbo.PO_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID LEFT JOIN dbo.REQUISITION R ON D.REQ_ID = R.REQ_ID WHERE D.PO_ID = ? ORDER BY I.ITEM_CODE, R.REQ_NO
    ";
    $stmtD = sqlsrv_query($conn,$sqlD,array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
}

if (getv("msg") == "saved") $message = "PO berhasil disimpan.";
if (getv("msg") == "deleted") $message = "PO berhasil dihapus.";

/* SUPPLIER DATA */
$supplierAuto = array();
$sqlSup = "SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_TERM FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE";
$stmtSup = sqlsrv_query($conn,$sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array("SUP_ID"=>intval($s["SUP_ID"]), "SUP_CODE"=>trim((string)$s["SUP_CODE"]), "SUP_COMP"=>trim((string)$s["SUP_COMP"]), "CURR_CODE"=>trim((string)$s["CURR_CODE"]), "SUP_TERM"=>trim((string)$s["SUP_TERM"]));
    }
}

/* PO AUTO COMPLETE DATA */
$poAuto = array();
$sqlPoAuto = "
    SELECT TOP 1000 P.PO_ID, P.PO_NUM, P.PO_DATE, S.SUP_CODE, S.SUP_COMP
    FROM dbo.PO P
    LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID
    ORDER BY P.PO_DATE DESC, P.PO_NUM DESC
";
$stmtPoAuto = sqlsrv_query($conn, $sqlPoAuto);
if ($stmtPoAuto !== false) {
    while ($pa = sqlsrv_fetch_array($stmtPoAuto, SQLSRV_FETCH_ASSOC)) {
        $poAuto[] = array(
            "PO_ID" => intval($pa["PO_ID"]),
            "PO_NUM" => trim((string)$pa["PO_NUM"]),
            "PO_DATE" => fmt_date_view($pa["PO_DATE"]),
            "SUP_CODE" => trim((string)$pa["SUP_CODE"]),
            "SUP_COMP" => trim((string)$pa["SUP_COMP"])
        );
    }
}

/* OS REQ DATA FILTER BY QUOTATION */
$osReqAuto = array();
$sqlOS = "
    ;WITH OSREQ AS
    (
        SELECT RD.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, RD.REQ_ID, R.REQ_DUE, R.REQ_DATE, R.REQ_NO, RD.REQD_QTY, ISNULL(IPV.POD_QTY,0) AS POD_QTY, RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) AS REQ_BQTY
        FROM dbo.REQ_DETAIL RD INNER JOIN dbo.REQUISITION R ON RD.REQ_ID = R.REQ_ID INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID LEFT JOIN dbo.ITEM_REQ_PO_VIEW IPV ON RD.REQ_ID = IPV.REQ_ID AND RD.ITEM_ID = IPV.ITEM_ID
        WHERE R.REQ_CLOSE = 0 AND RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) > 0
    ),
    QUO AS
    (
        SELECT Q.SUP_ID, Q.CURR_CODE, QD.ITEM_ID, QD.QUOD_PRICE, ROW_NUMBER() OVER (PARTITION BY Q.SUP_ID, Q.CURR_CODE, QD.ITEM_ID ORDER BY CASE WHEN ISNULL(QD.QUOD_ACTIVE,0)=1 THEN 0 ELSE 1 END, Q.QUO_DATE DESC, Q.QUO_ID DESC) AS RN
        FROM dbo.QUOTATION Q INNER JOIN dbo.QUOT_DETAIL QD ON Q.QUO_ID = QD.QUO_ID WHERE ISNULL(QD.QUOD_PRICE,0) > 0
    )
    SELECT TOP 2000 O.ITEM_ID, O.ITEM_CODE, O.ITEM_NAME, O.ITEM_UNIT, O.REQ_ID, O.REQ_DUE, O.REQ_DATE, O.REQ_NO, O.REQD_QTY, O.POD_QTY, O.REQ_BQTY, Q.SUP_ID, Q.CURR_CODE, Q.QUOD_PRICE
    FROM OSREQ O INNER JOIN QUO Q ON O.ITEM_ID = Q.ITEM_ID AND Q.RN = 1 ORDER BY Q.SUP_ID, Q.CURR_CODE, O.ITEM_CODE, O.REQ_DUE, O.REQ_NO
";
$stmtOS = sqlsrv_query($conn,$sqlOS);
if ($stmtOS !== false) {
    while ($o = sqlsrv_fetch_array($stmtOS, SQLSRV_FETCH_ASSOC)) {
        $osReqAuto[] = array("ITEM_ID"=>intval($o["ITEM_ID"]), "ITEM_CODE"=>trim((string)$o["ITEM_CODE"]), "ITEM_NAME"=>trim((string)$o["ITEM_NAME"]), "ITEM_UNIT"=>trim((string)$o["ITEM_UNIT"]), "REQ_ID"=>intval($o["REQ_ID"]), "REQ_DUE"=>fmt_date($o["REQ_DUE"]), "REQ_DUE_VIEW"=>fmt_date_view($o["REQ_DUE"]), "REQ_DATE"=>fmt_date($o["REQ_DATE"]), "REQ_DATE_VIEW"=>fmt_date_view($o["REQ_DATE"]), "REQ_NO"=>trim((string)$o["REQ_NO"]), "REQD_QTY"=>floatval($o["REQD_QTY"]), "POD_QTY"=>floatval($o["POD_QTY"]), "REQ_BQTY"=>floatval($o["REQ_BQTY"]), "SUP_ID"=>intval($o["SUP_ID"]), "CURR_CODE"=>trim((string)$o["CURR_CODE"]), "QUOD_PRICE"=>floatval($o["QUOD_PRICE"]));
    }
}

/* CURR LIST */
$currList = array("IDR","USD","JPY","YEN");
$stmtCurr = @sqlsrv_query($conn, "SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE FROM dbo.CURR WHERE ISNULL(CURR_CODE,'') <> '' ORDER BY LTRIM(RTRIM(CURR_CODE))");
if ($stmtCurr !== false) {
    $currList = array();
    while ($c = sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$c["CURR_CODE"]);
        if ($v != "") $currList[] = $v;
    }
}

if (count($details) == 0) {
    for ($i=0; $i<10; $i++) {
        $details[] = array("REQ_ID"=>"", "ITEM_ID"=>"", "ITEM_CODE"=>"", "ITEM_NAME"=>"", "REQ_NO"=>"", "POD_DUE"=>date("Y-m-d"), "POD_QTY"=>"", "POD_PRICE"=>"", "POD_UNIT"=>"", "POD_AMOUNT"=>"");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* CSS KHUSUS TAMPILAN WINDOWS CLASSIC / DELPHI */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        
        .main-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #ece9d8;
        }

        .title-bar {
            background-color: #ffffff;
            text-align: center;
            font-size: 24px;
            font-family: Arial, sans-serif;
            padding: 5px 0;
            color: #000;
        }

        /* HEADER LIGHT BLUE */
        .lightblue-header {
            background-color: #a0c4e4; /* Light Blue Sesuai Gambar */
            padding: 10px;
            color: #000000;
        }

        .lbl-black {
            color: #000000;
            font-size: 11px;
            margin-bottom: 2px;
            display: block;
        }

        .input-classic {
            width: 100%;
            height: 20px;
            font-size: 12px;
            padding: 0 4px;
            border: 1px solid #7f9db9;
            box-sizing: border-box;
            background: #fff;
        }

        .input-classic:read-only { background-color: #e0e0e0; color: #000; }
        
        select.input-classic { padding: 0; }
        input[type="checkbox"].classic-cb { margin: 2px 0 0 0; }
        textarea.input-classic { height: 40px; padding: 4px; resize: none; }

        .btn-classic {
            background: #f0f0f0;
            border: 1px solid #888;
            border-bottom-color: #444;
            border-right-color: #444;
            padding: 2px 10px;
            font-size: 12px;
            color: #000;
            cursor: pointer;
            box-shadow: inset 1px 1px 0px #fff;
        }
        .btn-classic:active {
            box-shadow: inset 1px 1px 2px #888;
            border: 1px solid #444;
            border-bottom-color: #888;
            border-right-color: #888;
        }
        .btn-action-wide { width: 75px; margin-right: 2px; }

        .fieldset-control {
            border: 1px solid #fff;
            padding: 6px;
            margin-top: 5px;
            display: inline-block;
        }

        /* GRID TABLE CLASSIC */
        .datagrid-wrapper {
            flex-grow: 1;
            overflow: auto;
            background: #fff;
            border-top: 2px solid #888;
            border-bottom: 2px solid #fff;
        }

        table.datagrid {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        table.datagrid th {
            background-color: #ece9d8;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-weight: normal;
            font-size: 11px;
            padding: 2px 4px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.datagrid td {
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            font-size: 11px;
        }

        table.datagrid input[type="text"], table.datagrid input[type="date"] {
            width: 100%;
            height: 18px;
            border: none;
            padding: 0 4px;
            font-size: 11px;
            background: transparent;
            box-sizing: border-box;
        }
        table.datagrid input[type="text"]:focus, table.datagrid input[type="date"]:focus {
            background: #e7f1ff;
            outline: 1px solid #000;
        }
        table.datagrid tr.detail-selected td {
            background-color: #d0d0d0;
        }

        .footer-bar {
            background: #ece9d8;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #c0c0c0;
        }

        /* AUTOCOMPLETE STYLING */
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 2px;
            max-height: 200px;
            overflow-y: auto;
            min-width: 300px;
            display: none;
            font-size: 12px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        }
        .ac-item { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item:hover, .ac-item.active { background: #a0c4e4; color: #000; }

        /* Bootstrap grid system utilitas (minimalis) */
        .row { display: flex; flex-wrap: wrap; margin-right: -5px; margin-left: -5px; }
        .col-1 { flex: 0 0 8.333%; max-width: 8.333%; padding: 0 5px; }
        .col-1-5 { flex: 0 0 12.5%; max-width: 12.5%; padding: 0 5px; }
        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 0 5px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 0 5px; }
        .col-3-5 { flex: 0 0 29.166%; max-width: 29.166%; padding: 0 5px; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 5px; }
        .col-5 { flex: 0 0 41.666%; max-width: 41.666%; padding: 0 5px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 5px; }
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 5px; }
        .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 5px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto); ?>;
        var osReqData = <?php echo json_encode($osReqAuto); ?>;
        var poData = <?php echo json_encode($poAuto); ?>;
        
        var acBox = null, acItems = [], acIndex = -1, acMode = "", acRow = -1, supplierTarget = "sup";
        var rowSeq = <?php echo count($details); ?>;
        var selectedDetailRow = null;

        function byId(id) { return document.getElementById(id); }
        function setValue(id, value) { var el = byId(id); if (el) el.value = value == null ? "" : value; }

        function initAC() { acBox = document.getElementById("acBox"); }
        function hideAC() {
            if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; }
            acItems = []; acIndex = -1; acMode = ""; acRow = -1;
        }

        function positionAC(input) {
            initAC();
            var rect = input.getBoundingClientRect();
            acBox.style.left = (rect.left + window.scrollX) + "px";
            acBox.style.top = (rect.bottom + window.scrollY) + "px";
            acBox.style.width = rect.width < 300 ? "300px" : rect.width + "px";
        }

        function renderAC(renderText, pickFunc) {
            initAC();
            acBox.innerHTML = "";
            for (var i = 0; i < acItems.length; i++) {
                var div = document.createElement("div");
                div.className = "ac-item" + (i == acIndex ? " active" : "");
                div.innerHTML = renderText(acItems[i]);
                div.setAttribute("data-index", i);
                div.onmousedown = function () {
                    var idx = parseInt(this.getAttribute("data-index"), 10);
                    pickFunc(acItems[idx]);
                };
                acBox.appendChild(div);
            }
            acBox.style.display = acItems.length > 0 ? "block" : "none";
        }

        function acMove(step, renderText, pickFunc) {
            if (acItems.length <= 0) return;
            acIndex += step;
            if (acIndex < 0) acIndex = acItems.length - 1;
            if (acIndex >= acItems.length) acIndex = 0;
            renderAC(renderText, pickFunc);
        }

        function acEnter(pickFunc) {
            if (acItems.length <= 0) return false;
            if (acIndex < 0) acIndex = 0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function submitSave(allowEmpty) {
            if (byId("allow_empty_detail")) {
                byId("allow_empty_detail").value = allowEmpty ? "1" : "0";
            }
            var form = byId("poForm");
            if (form) form.submit();
        }

        function newData() { window.location.href = "po.php"; }
        function goEdit(id) { if(!id) return; window.location.href = "po.php?edit=" + encodeURIComponent(id); }
        function confirmDelete() { return confirm("Yakin hapus PO ini?"); }

        function deleteCurrent() {
            var id = byId("po_id").value;
            if (id == "" || id == "0") { alert("Pilih PO dulu."); return; }
            if (!confirmDelete()) return;
            byId("delete_po_id").value = id;
            byId("deleteForm").submit();
        }

        function selectDetailRow(tr){
            var rows = document.querySelectorAll("#detailBody tr");
            for(var i = 0; i < rows.length; i++) rows[i].classList.remove("detail-selected");
            selectedDetailRow = tr;
            if(tr) tr.classList.add("detail-selected");
        }

        // PO Search AutoComplete (Header)
        function showPoSearchAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="posearch"; acItems=[]; acIndex=-1;
            if(key.length<1){hideAC();return;}
            
            for(var i=0; i<poData.length; i++){
                var p = poData[i];
                var t = (p.PO_NUM||"")+" "+(p.SUP_CODE||"")+" "+(p.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key) >= 0) acItems.push(p);
                if(acItems.length >= 40) break;
            }
            positionAC(input);
            renderAC(function(p){
                return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small style='color:#555;'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>";
            }, pickPoSearch);
        }
        function poSearchKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault(); if(acMode!=="posearch"||acItems.length==0) showPoSearchAC(input);
                acMove(1, function(p){ return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small style='color:#555;'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>"; }, pickPoSearch); return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault(); if(acMode!=="posearch"||acItems.length==0) showPoSearchAC(input);
                acMove(-1, function(p){ return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small style='color:#555;'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>"; }, pickPoSearch); return false;
            }
            if(e.key==="Enter"){
                if(acMode==="posearch"&&acItems.length>0){e.preventDefault(); acEnter(pickPoSearch); return false;}
                return true; 
            }
            if(e.key==="Escape") hideAC();
        }
        function pickPoSearch(p){
            hideAC();
            goEdit(p.PO_ID);
        }

        // Supplier Autocomplete
        function showSupplierAC(input,target){
            initAC();supplierTarget=target;
            var key=(input.value||"").toUpperCase();
            acMode="supplier";acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<supplierData.length;i++){
                var s=supplierData[i];var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key)>=0)acItems.push(s);
                if(acItems.length>=40)break;
            }
            positionAC(input);
            renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);
        }
        function supplierKey(e,input,target){
            supplierTarget=target;
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input,target);
                acMove(1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input,target);
                acMove(-1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="supplier"&&acItems.length>0){e.preventDefault();acEnter(pickSupplier);return false;}
                e.preventDefault();submitSave(false);return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickSupplier(s){
            if(supplierTarget=="to"){
                setValue("po_to",s.SUP_ID);setValue("to_sup_code",s.SUP_CODE);setValue("to_sup_comp",s.SUP_COMP);
            }else{
                setValue("sup_id",s.SUP_ID);setValue("sup_code",s.SUP_CODE);setValue("sup_comp",s.SUP_COMP);
                if(s.CURR_CODE)setValue("po_cur",s.CURR_CODE);
                if(s.SUP_TERM)setValue("po_term",s.SUP_TERM);
                if(!byId("po_to").value||byId("po_to").value=="0"){
                    setValue("po_to",s.SUP_ID);setValue("to_sup_code",s.SUP_CODE);setValue("to_sup_comp",s.SUP_COMP);
                }
            }
            hideAC();
        }

        // OS Req Autocomplete
        function showOSAC(input,row){
            initAC();var supId=byId("sup_id").value;var curr=byId("po_cur").value;
            if(!supId||supId=="0"){alert("Pilih supplier dulu.");input.value="";return;}
            if(!curr){alert("Pilih currency dulu.");input.value="";return;}
            var key=(input.value||"").toUpperCase();
            acMode="osreq";acRow=row;acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<osReqData.length;i++){
                var o=osReqData[i];
                if(parseInt(o.SUP_ID,10)!=parseInt(supId,10))continue;
                if((o.CURR_CODE||"").toUpperCase()!=(curr||"").toUpperCase())continue;
                var t=(o.ITEM_CODE||"")+" "+(o.ITEM_NAME||"")+" "+(o.REQ_NO||"")+" "+(o.REQ_DUE_VIEW||"");
                if(t.toUpperCase().indexOf(key)>=0)acItems.push(o);
                if(acItems.length>=60)break;
            }
            positionAC(input);
            renderAC(function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);
        }
        function osKey(e,input,row){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="osreq"||acItems.length==0)showOSAC(input,row);
                acMove(1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="osreq"||acItems.length==0)showOSAC(input,row);
                acMove(-1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="osreq"&&acItems.length>0){e.preventDefault();acEnter(pickOS);return false;}
                e.preventDefault();return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickOS(o){
            var row=acRow;
            setValue("req_id_"+row,o.REQ_ID);setValue("item_id_"+row,o.ITEM_ID);
            setValue("item_code_"+row,o.ITEM_CODE);setValue("item_name_"+row,o.ITEM_NAME);
            setValue("pod_due_"+row,o.REQ_DUE);setValue("pod_qty_"+row,o.REQ_BQTY);
            setValue("pod_unit_"+row,o.ITEM_UNIT);setValue("req_no_"+row,o.REQ_NO);
            setValue("pod_price_"+row,o.QUOD_PRICE);
            hideAC();calcAmount(row);
            var q=byId("pod_qty_"+row);if(q){q.focus();q.select();}
        }

        function calcAmount(row){
            var q=byId("pod_qty_"+row),p=byId("pod_price_"+row);
            if(!q||!p)return;
            var qty=parseFloat((q.value||"0").replace(/,/g,""));
            var price=parseFloat((p.value||"0").replace(/,/g,""));
            if(isNaN(qty))qty=0;if(isNaN(price))price=0;
            setValue("pod_amount_"+row,(qty*price).toFixed(2));
        }
        
        function fieldEnterSave(e, row) {
    if (row !== undefined) {
        setTimeout(function(){ calcAmount(row); }, 10);
    }
    
    // Jika tombol Enter ditekan
    if (e.key === "Enter") {
        e.preventDefault(); // Cegah reload/submit form default
        
        // Panggil fungsi tambah baris bawaan dari sistem Anda
        addRow(); 
        return false;
    }
}

        function headerKey(e) {
            if (e.key === "Enter") {
                if (acMode !== "" && acItems.length > 0) return true;
                e.preventDefault(); submitSave(false); return false;
            }
        }

        function addRow(){
            var tbody = byId("detailBody");
            var row = rowSeq;
            rowSeq++;
            var tr = document.createElement("tr");
            tr.onclick = function(){ selectDetailRow(this); };

            tr.innerHTML =
                '<td class="text-center align-middle row-no" style="width:25px;"></td>' +
                '<td style="width:120px;">' +
                    '<input type="hidden" name="req_id[]" id="req_id_'+row+'">' +
                    '<input type="hidden" name="item_id[]" id="item_id_'+row+'">' +
                    '<input type="text" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')">' +
                '</td>' +
                '<td><input type="text" name="item_name[]" id="item_name_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')"></td>' +
                '<td style="width:90px;"><input type="date" name="pod_due[]" id="pod_due_'+row+'" value="<?php echo date("Y-m-d"); ?>" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td style="width:70px;"><input type="text" class="text-right" name="pod_qty[]" id="pod_qty_'+row+'" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td style="width:80px;"><input type="text" class="text-right" name="pod_price[]" id="pod_price_'+row+'" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td style="width:60px;"><input type="text" name="pod_unit[]" id="pod_unit_'+row+'" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td style="width:100px;"><input type="text" name="req_no[]" id="req_no_'+row+'" readonly></td>' +
                '<td style="width:100px;"><input type="text" class="text-right" id="pod_amount_'+row+'" readonly></td>' +
                '<td class="text-center" style="width:40px;"><button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this); event.stopPropagation();" title="Delete">X</button></td>';

            tbody.appendChild(tr);
            renumberRows();
            selectDetailRow(tr);
            byId("item_code_"+row).focus();
        }

        function deleteRow(btn){
            var tr = btn.closest("tr");
            if(!confirm("Hapus baris detail ini?"))return;
            tr.parentNode.removeChild(tr);
            renumberRows();
            var id = byId("po_id").value;
            if(id != "" && id != "0") submitSave(true);
        }
        
        function renumberRows(){
            var rows = byId("detailBody").getElementsByTagName("tr");
            for(var i=0; i<rows.length; i++){
                var c = rows[i].getElementsByClassName("row-no")[0];
                if(c) c.innerHTML = (i==0) ? "<i class='fas fa-caret-right'></i>" : "";
            }
        }

        function showReceive(){
            var poNum = byId("po_num").value;
            if(!poNum){alert("Pilih / simpan PO dulu.");return;}
            var tr = selectedDetailRow;
            if(!tr){
                var rows = byId("detailBody").getElementsByTagName("tr");
                if(rows.length > 0) tr = rows[0];
            }
            if(!tr){alert("Pilih detail item dulu.");return;}
            var itemInput = tr.querySelector('input[name="item_id[]"]');
            var itemId = itemInput ? itemInput.value : "";
            if(!itemId || itemId=="0"){alert("Pilih detail item dulu.");return;}
            window.open("po.php?action=receive_view&po_num="+encodeURIComponent(poNum)+"&item_id="+encodeURIComponent(itemId),"RECEIVE_LIST","width=800,height=500,scrollbars=yes,resizable=yes");
        }
        
        function printPO(){
            var poId = byId("po_id").value;
            if(!poId || poId=="0"){alert("Pilih / simpan PO dulu.");return;}
            window.open("print_po.php?po_id="+encodeURIComponent(poId),"PRINT_PO","width=900,height=700,scrollbars=yes,resizable=yes");
        }
        
        function setNextPO() {
            window.location.href='next_po.php';
        }

        document.addEventListener("DOMContentLoaded",function(){
            var rows = document.querySelectorAll("#detailBody tr");
            for(var i=0; i<rows.length; i++){
                rows[i].onclick = function(){ selectDetailRow(this); };
            }
            renumberRows();
        });
        
        document.addEventListener("click",function(e){
            initAC();if(acBox && !acBox.contains(e.target)){if(!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();}
        });
    </script>
</head>
<body>

<div class="main-container">
    <!-- Title Bar -->
    <div class="title-bar">
        Purchase Order
    </div>

    <?php if ($message != "") { echo "<div style='background:#d4edda; color:#155724; padding:5px; text-align:center; font-size:12px;'>$message</div>"; } ?>
    <?php if ($error != "") { echo "<div style='background:#f8d7da; color:#721c24; padding:5px; text-align:center; font-size:12px;'>$error</div>"; } ?>

    <form id="poForm" method="post" action="po.php" style="display: flex; flex-direction: column; flex-grow: 1;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="po_id" id="po_id" value="<?php echo h($header["PO_ID"]); ?>">
        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
        <input type="hidden" name="po_to" id="po_to" value="<?php echo h($header["PO_TO"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <!-- HEADER LIGHT BLUE AREA -->
        <div class="lightblue-header">
            <div class="row">
                <div class="col-8">
                    <!-- Baris Atas -->
                    <div class="row">
                        <div class="col-2">
                            <label class="lbl-black">PO No.</label>
                            <input type="text" class="input-classic" name="po_num" id="po_num" value="<?php echo h($header["PO_NUM"]); ?>" placeholder="Auto" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-2">
                            <label class="lbl-black">Date</label>
                            <input type="date" class="input-classic" name="po_date" value="<?php echo h($header["PO_DATE"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-2">
                            <label class="lbl-black">Date DO</label>
                            <input type="date" class="input-classic" name="po_datedo" value="<?php echo h($header["PO_DATEDO"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-1 text-center">
                            <label class="lbl-black">Close</label>
                            <input type="checkbox" class="classic-cb" id="po_close" name="po_close" value="1" <?php echo intval($header["PO_CLOSE"]) == 1 ? "checked" : ""; ?>>
                        </div>
                        <div class="col-1-5">
                            <label class="lbl-black">Supplier</label>
                            <input type="text" class="input-classic" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')">
                        </div>
                        <div class="col-3-5">
                            <label class="lbl-black">&nbsp;</label>
                            <input type="text" class="input-classic" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')">
                        </div>
                    </div>
                    
                    <!-- Baris Tengah -->
                    <div class="row mt-1">
                        <div class="col-2">
                            <label class="lbl-black">Terms.of Pmt</label>
                            <input type="text" class="input-classic" name="po_term" id="po_term" value="<?php echo h($header["PO_TERM"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-4">
                            <label class="lbl-black">Term.of Delv</label>
                            <input type="text" class="input-classic" name="po_termdel" id="po_termdel" value="<?php echo h($header["PO_TERMDEL"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-1">
                            <label class="lbl-black">Cur.</label>
                            <select class="input-classic" name="po_cur" id="po_cur" onkeydown="headerKey(event)">
                                <?php foreach($currList as $c){ ?>
                                    <option value="<?php echo h($c); ?>" <?php echo trim((string)$header["PO_CUR"])==$c ? "selected" : ""; ?>><?php echo h($c); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-1-5">
                            <label class="lbl-black">Invoice To</label>
                            <input type="text" class="input-classic" name="to_sup_code" id="to_sup_code" value="<?php echo h($header["TO_SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')">
                        </div>
                        <div class="col-3-5">
                            <label class="lbl-black">&nbsp;</label>
                            <input type="text" class="input-classic" name="to_sup_comp" id="to_sup_comp" value="<?php echo h($header["TO_SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')">
                        </div>
                    </div>

                    <!-- Baris Bawah -->
                    <div class="row mt-1">
                        <div class="col-6">
                            <label class="lbl-black">Remark</label>
                            <textarea class="input-classic" name="po_rem" onkeydown="headerKey(event)"><?php echo h($header["PO_REM"]); ?></textarea>
                        </div>
                        <div class="col-6" style="padding-top: 15px;">
                            <label style="font-size:11px; margin-right:15px; color:#000;">
                                <input type="checkbox" class="classic-cb" id="po_todef" name="po_todef" value="1" <?php echo intval($header["PO_TODEF"]) == 1 ? "checked" : ""; ?>> PT. IMC TEKNO
                            </label>
                            <label style="font-size:11px; color:#000;">
                                <input type="checkbox" class="classic-cb" id="po_termdelsch" name="po_termdelsch" value="1" <?php echo intval($header["PO_TERMDELSCH"]) == 1 ? "checked" : ""; ?>> Delivery Schedule
                            </label>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <fieldset class="fieldset-control" style="border:none; padding: 4px 10px;">
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="goEdit('<?php echo h($editId); ?>')">Rubah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="submitSave(false)">Simpan</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Batal</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='po.php'">Refresh</button>
                                &nbsp;
                                <button type="button" class="btn-classic" onclick="setNextPO()" style="width:100px;">Set Next PO #</button>
                                <button type="button" class="btn-classic" onclick="printPO()"><i class="fas fa-print"></i></button>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Search Data -->
                <div class="col-4">
                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                        <div style="margin-bottom: 20px; width: 100%;">
                            <label class="lbl-black text-right" style="font-weight:bold;">SEARCH PO</label>
                            <input type="text" name="q" class="input-classic" style="width:100%;" value="<?php echo h($q); ?>" placeholder="Search PO No / Supplier" autocomplete="off" oninput="showPoSearchAC(this)" onkeydown="poSearchKey(event, this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID DATA AREA -->
        <div class="datagrid-wrapper">
            <table class="datagrid" id="detailTable">
                <thead>
                    <tr>
                        <th style="width:25px;" class="text-center"></th>
                        <th style="width:100px;">Code</th>
                        <th>Item Name</th>
                        <th style="width:90px;">Due On</th>
                        <th style="width:70px;" class="text-right">Qty.</th>
                        <th style="width:80px;" class="text-right">Rate</th>
                        <th style="width:60px;">Unit</th>
                        <th style="width:100px;">Req.#</th>
                        <th style="width:100px;" class="text-right">Amount</th>
                        <th style="width:40px;" class="text-center">ACT</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <?php for ($i = 0; $i < count($details); $i++) { 
                        $d = $details[$i];
                        $qty = isset($d["POD_QTY"]) ? floatval($d["POD_QTY"]) : 0;
                        $price = isset($d["POD_PRICE"]) ? floatval($d["POD_PRICE"]) : 0;
                        $amount = isset($d["POD_AMOUNT"]) ? floatval($d["POD_AMOUNT"]) : ($qty * $price);
                    ?>
                        <tr>
                            <td class="text-center align-middle row-no" style="width:25px;"></td>
                            <td style="width:100px;">
                                <input type="hidden" name="req_id[]" id="req_id_<?php echo h($i); ?>" value="<?php echo h($d["REQ_ID"]); ?>">
                                <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                <input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"]) ? $d["ITEM_CODE"] : ""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td>
                                <input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"]) ? $d["ITEM_NAME"] : ""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:90px;">
                                <input type="date" name="pod_due[]" id="pod_due_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_DUE"]) ? fmt_date($d["POD_DUE"]) : date("Y-m-d")); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:70px;">
                                <input type="text" class="text-right" name="pod_qty[]" id="pod_qty_<?php echo h($i); ?>" value="<?php echo h($qty == 0 ? "" : $qty); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:80px;">
                                <input type="text" class="text-right" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" value="<?php echo h($price == 0 ? "" : $price); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:60px;">
                                <input type="text" name="pod_unit[]" id="pod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_UNIT"]) ? $d["POD_UNIT"] : ""); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:100px;">
                                <input type="text" name="req_no[]" id="req_no_<?php echo h($i); ?>" value="<?php echo h(isset($d["REQ_NO"]) ? $d["REQ_NO"] : ""); ?>" readonly>
                            </td>
                            <td style="width:100px;">
                                <input type="text" class="text-right" id="pod_amount_<?php echo h($i); ?>" value="<?php echo h($amount == 0 ? "" : number_format($amount, 2, ".", "")); ?>" readonly>
                            </td>
                            <td class="text-center" style="width:40px;">
                                <button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER BAR -->
        <div class="footer-bar">
            <div>
                <button type="button" class="btn-classic" onclick="addRow()"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn-classic" onclick="showReceive()" style="width: 80px; height:24px;">Show Recv.</button>
            </div>
        </div>
    </form>

    <form id="deleteForm" method="post" action="po.php" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="po_id" id="delete_po_id" value="">
    </form>
</div>

<div id="acBox" class="ac-box"></div>

<script>    
    $(document).ready(function() {
    // Ganti '.input-terakhir' dengan class atau ID dari kolom input terakhir di baris Anda (misal: class input untuk Qty atau Price)
    $(document).on('keypress', '.input-terakhir', function(e) {
        // Mengecek apakah tombol yang ditekan adalah Enter (Keycode 13)
        if (e.which == 13) {
            e.preventDefault(); // Mencegah form submit default bawaan browser
            
            var currentRow = $(this).closest('tr');
            
            // 1. Eksekusi fungsi SIMPAN baris saat ini
            // Jika ada tombol simpan di baris tersebut:
            currentRow.find('.btn-save-row').click(); 
            // ATAU jika memanggil fungsi AJAX langsung:
            // simpanBaris(currentRow);
            
            // 2. Eksekusi fungsi TAMBAH BARIS BARU (Trigger tombol '+' atau fungsi tambah)
            $('#btn-add-row').click(); // Sesuaikan dengan ID tombol tambah baris Anda
            
            // 3. Pindahkan kursor (Fokus) ke input pertama di baris yang baru saja ditambahkan
            // Menggunakan setTimeout untuk memberi jeda sedikit sampai DOM baris baru selesai di-render
            setTimeout(function() {
                // Mencari baris terakhir di tabel dan memfokuskan kursor ke input pertama (misal: Item Code)
                $('#tabel-transaksi tbody tr:last').find('.input-pertama').focus();
            }, 200); // Jeda 200ms, bisa disesuaikan
        }
    });
});
</script>
</body>
</html>