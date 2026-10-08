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
    elseif ($poTo <= 0) $error = "Invoice To wajib dipilih.";
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

/* PO AUTO COMPLETE DATA (Untuk Pencarian di Header) */
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

/* LIST PO */
$q = getv("q","");
$where = "";
$paramsList = array();
if ($q != "") {
    $where = "WHERE P.PO_NUM LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ?";
    $paramsList = array("%".$q."%","%".$q."%","%".$q."%");
}

$sqlList = "
    SELECT TOP 300 P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.PO_CUR, P.PO_CLOSE, S.SUP_CODE, S.SUP_COMP, COUNT(D.ITEM_ID) AS DETAIL_COUNT, ISNULL(SUM(ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)),0) AS TOTAL_AMOUNT
    FROM dbo.PO P LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID LEFT JOIN dbo.PO_DETAIL D ON P.PO_ID = D.PO_ID $where
    GROUP BY P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.PO_CUR, P.PO_CLOSE, S.SUP_CODE, S.SUP_COMP ORDER BY P.PO_DATE DESC, P.PO_NUM DESC
";
$stmtList = sqlsrv_query($conn,$sqlList,$paramsList);
if ($stmtList === false) die("<pre>Query list PO error:\n".sql_error_text()."</pre>");

if (count($details) == 0) {
    for ($i=0; $i<4; $i++) {
        $details[] = array("REQ_ID"=>"", "ITEM_ID"=>"", "ITEM_CODE"=>"", "ITEM_NAME"=>"", "REQ_NO"=>"", "POD_DUE"=>date("Y-m-d"), "POD_QTY"=>"", "POD_PRICE"=>"", "POD_UNIT"=>"", "POD_AMOUNT"=>"");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order - Purchasing</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE 3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            max-height: 250px;
            overflow-y: auto;
            min-width: 360px;
            display: none;
            font-size: 14px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .ac-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f4f4f4;
        }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover, .ac-item.active { background: #007bff; color: #ffffff; }

        /* CUSTOM COMPACT TABLE ROW */
        #detailTable th, #detailTable td {
            padding: 2px 4px !important;
            vertical-align: middle !important;
        }
        .table-input-transparent {
            border: 1px solid transparent;
            background: transparent;
            height: 22px !important;
            padding: 0px 4px !important;
            font-size: 12px !important;
            border-radius: 0;
        }
        .table-input-transparent:focus {
            background: #fff;
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        #detailTable .btn-xs {
            padding: 1px 6px !important;
            font-size: 11px !important;
            height: 22px !important;
            line-height: 1.5 !important;
        }
        tr.pointer:hover, tr.detail-selected td {
            cursor: pointer;
            background-color: #e7f1ff !important;
        }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto); ?>;
        var osReqData = <?php echo json_encode($osReqAuto); ?>;
        var poData = <?php echo json_encode($poAuto); ?>; // Data pencarian PO
        
        var acBox=null, acItems=[], acIndex=-1, acMode="", acRow=-1, supplierTarget="sup";
        var rowSeq=<?php echo count($details); ?>;
        var selectedDetailRow=null;

        function byId(id){return document.getElementById(id);}
        function setValue(id,value){var el=byId(id); if(el) el.value=(value==null?"":value);}

        function initAC(){acBox=byId("acBox");}
        function hideAC(){
            if(acBox){acBox.style.display="none";acBox.innerHTML="";}
            acItems=[];acIndex=-1;acMode="";acRow=-1;
        }
        function positionAC(input){
            initAC();var r=input.getBoundingClientRect();
            acBox.style.left=(r.left+window.scrollX)+"px";
            acBox.style.top=(r.bottom+window.scrollY)+"px";
            acBox.style.width=(r.width<360?360:r.width)+"px";
        }
        function renderAC(renderText,pickFunc){
            initAC();acBox.innerHTML="";
            for(var i=0;i<acItems.length;i++){
                var d=document.createElement("div");
                d.className="ac-item"+(i==acIndex?" active":"");
                d.innerHTML=renderText(acItems[i]);
                d.setAttribute("data-index",i);
                d.onmousedown=function(){pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]);};
                acBox.appendChild(d);
            }
            acBox.style.display=acItems.length>0?"block":"none";
        }
        function acMove(step,renderText,pickFunc){
            if(acItems.length<=0)return;
            acIndex+=step;
            if(acIndex<0)acIndex=acItems.length-1;
            if(acIndex>=acItems.length)acIndex=0;
            renderAC(renderText,pickFunc);
        }
        function acEnter(pickFunc){
            if(acItems.length<=0)return false;
            if(acIndex<0)acIndex=0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function submitSave(allowEmpty){
            if(byId("allow_empty_detail")) byId("allow_empty_detail").value=allowEmpty?"1":"0";
            var f=byId("poForm");if(f) f.submit();
        }
        function newData(){window.location.href="po.php";}
        function goEdit(id){if(!id)return;window.location.href="po.php?edit="+encodeURIComponent(id);}
        function deleteCurrent(){
            var id=byId("po_id").value;
            if(id==""||id=="0"){alert("Pilih PO dulu.");return;}
            if(!confirm("Yakin hapus PO ini?"))return;
            byId("delete_po_id").value=id;byId("deleteForm").submit();
        }
        function headerKey(e){
            if(e.key==="Enter"){
                if(acMode!==""&&acItems.length>0)return true;
                e.preventDefault();submitSave(false);return false;
            }
        }

        function selectDetailRow(tr){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++) rows[i].classList.remove("detail-selected");
            selectedDetailRow=tr;
            if(tr)tr.classList.add("detail-selected");
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
                return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small class='text-muted'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>";
            }, pickPoSearch);
        }
        function poSearchKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault(); if(acMode!=="posearch"||acItems.length==0) showPoSearchAC(input);
                acMove(1, function(p){ return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small class='text-muted'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>"; }, pickPoSearch); return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault(); if(acMode!=="posearch"||acItems.length==0) showPoSearchAC(input);
                acMove(-1, function(p){ return "<b>" + p.PO_NUM + "</b> - Date: " + p.PO_DATE + "<br><small class='text-muted'>" + p.SUP_CODE + " - " + p.SUP_COMP + "</small>"; }, pickPoSearch); return false;
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
        function fieldEnterSave(e,row){
            if(e.key==="Enter"){e.preventDefault();submitSave(false);return false;}
            if(row!==undefined)setTimeout(function(){calcAmount(row);},10);
        }

        function addRow(){
            var tbody=byId("detailBody");var row=rowSeq;rowSeq++;
            var tr=document.createElement("tr");
            tr.onclick=function(){selectDetailRow(this);};
            tr.innerHTML=
                '<td class="text-center align-middle row-no"></td>' +
                '<td>' +
                    '<input type="hidden" name="req_id[]" id="req_id_'+row+'">' +
                    '<input type="hidden" name="item_id[]" id="item_id_'+row+'">' +
                    '<input type="text" class="form-control form-control-sm table-input-transparent" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')">' +
                '</td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent" name="item_name[]" id="item_name_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')"></td>' +
                '<td><input type="date" class="form-control form-control-sm table-input-transparent" name="pod_due[]" id="pod_due_'+row+'" value="<?php echo date("Y-m-d"); ?>" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="pod_qty[]" id="pod_qty_'+row+'" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="pod_price[]" id="pod_price_'+row+'" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent" name="pod_unit[]" id="pod_unit_'+row+'" onkeydown="fieldEnterSave(event, '+row+')"></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent" name="req_no[]" id="req_no_'+row+'" readonly></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent text-right" id="pod_amount_'+row+'" readonly></td>' +
                '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();" title="Delete"><i class="fas fa-times"></i></button></td>';

            tbody.appendChild(tr);
            renumberRows();selectDetailRow(tr);byId("item_code_"+row).focus();
        }

        function deleteRow(btn){
            var tr=btn.closest("tr");
            if(!confirm("Hapus baris detail ini?"))return;
            tr.parentNode.removeChild(tr);renumberRows();
            var id=byId("po_id").value;if(id!=""&&id!="0")submitSave(true);
        }
        function renumberRows(){
            var rows=byId("detailBody").getElementsByTagName("tr");
            for(var i=0;i<rows.length;i++){
                var c=rows[i].getElementsByClassName("row-no")[0];
                if(c)c.innerHTML=i+1;
            }
        }

        function showReceive(){
            var poNum=byId("po_num").value;
            if(!poNum){alert("Pilih / simpan PO dulu.");return;}
            var tr=selectedDetailRow;
            if(!tr){var rows=byId("detailBody").getElementsByTagName("tr");if(rows.length>0)tr=rows[0];}
            if(!tr){alert("Pilih detail item dulu.");return;}
            var itemInput=tr.querySelector('input[name="item_id[]"]');
            var itemId=itemInput?itemInput.value:"";
            if(!itemId||itemId=="0"){alert("Pilih detail item dulu.");return;}
            window.open("po.php?action=receive_view&po_num="+encodeURIComponent(poNum)+"&item_id="+encodeURIComponent(itemId),"RECEIVE_LIST","width=800,height=500,scrollbars=yes,resizable=yes");
        }
        function printPO(){
            var poId=byId("po_id").value;
            if(!poId||poId=="0"){alert("Pilih / simpan PO dulu.");return;}
            window.open("print_po.php?po_id="+encodeURIComponent(poId),"PRINT_PO","width=900,height=700,scrollbars=yes,resizable=yes");
        }

        document.addEventListener("DOMContentLoaded",function(){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++){rows[i].onclick=function(){selectDetailRow(this);};}
        });
        document.addEventListener("click",function(e){
            initAC();if(acBox&&!acBox.contains(e.target)){if(!e.target||!e.target.getAttribute||e.target.getAttribute("autocomplete")!=="off")hideAC();}
        });
    </script>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container-fluid">
            <a href="dashboard_purch.php" class="navbar-brand">
                <span class="brand-text font-weight-light"><i class="fas fa-shopping-cart text-info mr-2"></i> Purchasing System</span>
            </a>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a href="dashboard_home.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Close</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Purchase Order</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <?php if ($message != "") { ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-check"></i> Success!</h5>
                        <?php echo h($message); ?>
                    </div>
                <?php } ?>

                <?php if ($error != "") { ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> Error!</h5>
                        <?php echo h($error); ?>
                    </div>
                <?php } ?>

                <div class="card card-info card-outline">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <button type="button" class="btn btn-sm btn-default mr-1" onclick="newData()"><i class="fas fa-file"></i> New</button>
                            <button type="button" class="btn btn-sm btn-info mr-1" onclick="submitSave(false)"><i class="fas fa-save"></i> Save PO</button>
                            <button type="button" class="btn btn-sm btn-danger mr-3" onclick="deleteCurrent()"><i class="fas fa-trash"></i> Delete</button>
                            
                            <a href="next_po.php" class="btn btn-sm btn-outline-secondary mr-1"><i class="fas fa-cogs"></i> Set Next PO #</a>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="printPO()"><i class="fas fa-print"></i> Print</button>
                        </h3>
                        
                        <!-- Search Box di Header -->
                        <div class="card-tools">
                            <form method="get" action="po.php" class="form-inline m-0" id="searchFormTop">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <input type="text" name="q" class="form-control float-right" value="<?php echo h($q); ?>" placeholder="Search PO No / Supplier" autocomplete="off" oninput="showPoSearchAC(this)" onkeydown="poSearchKey(event, this)">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                        <a href="po.php" class="btn btn-default" title="Reset"><i class="fas fa-sync"></i></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form id="poForm" method="post" action="po.php">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="po_id" id="po_id" value="<?php echo h($header["PO_ID"]); ?>">
                        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
                        <input type="hidden" name="po_to" id="po_to" value="<?php echo h($header["PO_TO"]); ?>">
                        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>PO No.</label>
                                        <input type="text" class="form-control form-control-sm" name="po_num" id="po_num" value="<?php echo h($header["PO_NUM"]); ?>" placeholder="Auto" onkeydown="headerKey(event)">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date</label>
                                        <input type="date" class="form-control form-control-sm" name="po_date" value="<?php echo h($header["PO_DATE"]); ?>" onkeydown="headerKey(event)">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date DO</label>
                                        <input type="date" class="form-control form-control-sm" name="po_datedo" value="<?php echo h($header["PO_DATEDO"]); ?>" onkeydown="headerKey(event)">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>Close</label>
                                        <div class="custom-control custom-checkbox mt-1">
                                            <input class="custom-control-input" type="checkbox" id="po_close" name="po_close" value="1" <?php echo intval($header["PO_CLOSE"]) == 1 ? "checked" : ""; ?>>
                                            <label for="po_close" class="custom-control-label"></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Supplier</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" style="max-width:80px;" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')">
                                            <input type="text" class="form-control" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Terms of Pmt</label>
                                        <input type="text" class="form-control form-control-sm" name="po_term" id="po_term" value="<?php echo h($header["PO_TERM"]); ?>" onkeydown="headerKey(event)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Term of Delv</label>
                                        <input type="text" class="form-control form-control-sm" name="po_termdel" id="po_termdel" value="<?php echo h($header["PO_TERMDEL"]); ?>" onkeydown="headerKey(event)">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>Cur.</label>
                                        <select class="form-control form-control-sm" name="po_cur" id="po_cur" onkeydown="headerKey(event)">
                                            <?php foreach($currList as $c){ ?>
                                                <option value="<?php echo h($c); ?>" <?php echo trim((string)$header["PO_CUR"])==$c ? "selected" : ""; ?>><?php echo h($c); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Invoice To</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" style="max-width:80px;" name="to_sup_code" id="to_sup_code" value="<?php echo h($header["TO_SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')">
                                            <input type="text" class="form-control" name="to_sup_comp" id="to_sup_comp" value="<?php echo h($header["TO_SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label>Remark</label>
                                        <textarea class="form-control form-control-sm" name="po_rem" rows="2" style="resize:none;" onkeydown="headerKey(event)"><?php echo h($header["PO_REM"]); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="icheck-primary mt-1">
                                            <div class="custom-control custom-checkbox d-inline mr-3">
                                                <input class="custom-control-input" type="checkbox" id="po_todef" name="po_todef" value="1" <?php echo intval($header["PO_TODEF"]) == 1 ? "checked" : ""; ?>>
                                                <label for="po_todef" class="custom-control-label">PT. IMC TEKNO</label>
                                            </div>
                                            <div class="custom-control custom-checkbox d-inline">
                                                <input class="custom-control-input" type="checkbox" id="po_termdelsch" name="po_termdelsch" value="1" <?php echo intval($header["PO_TERMDELSCH"]) == 1 ? "checked" : ""; ?>>
                                                <label for="po_termdelsch" class="custom-control-label">Delivery Schedule</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive p-0 mt-2 border">
                                <table class="table table-bordered table-sm text-sm m-0" id="detailTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="text-center" style="width:35px;">No</th>
                                            <th style="width:120px;">Code</th>
                                            <th>Item Name</th>
                                            <th style="width:110px;">Due On</th>
                                            <th style="width:80px;">Qty.</th>
                                            <th style="width:100px;">Rate</th>
                                            <th style="width:80px;">Unit</th>
                                            <th style="width:100px;">Req.#</th>
                                            <th style="width:120px;">Amount</th>
                                            <th class="text-center" style="width:40px;">ACT</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailBody">
                                        <?php for($i=0;$i<count($details);$i++){ 
                                            $d=$details[$i];
                                            $qty=isset($d["POD_QTY"])?floatval($d["POD_QTY"]):0;
                                            $price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0;
                                            $amount=isset($d["POD_AMOUNT"])?floatval($d["POD_AMOUNT"]):($qty*$price);
                                        ?>
                                            <tr>
                                                <td class="text-center align-middle row-no"><?php echo h($i+1); ?></td>
                                                <td>
                                                    <input type="hidden" name="req_id[]" id="req_id_<?php echo h($i); ?>" value="<?php echo h($d["REQ_ID"]); ?>">
                                                    <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                                    <input type="text" class="form-control form-control-sm table-input-transparent" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)">
                                                </td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)"></td>
                                                <td><input type="date" class="form-control form-control-sm table-input-transparent" name="pod_due[]" id="pod_due_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_DUE"])?fmt_date($d["POD_DUE"]):date("Y-m-d")); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="pod_qty[]" id="pod_qty_<?php echo h($i); ?>" value="<?php echo h($qty==0?"":$qty); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" value="<?php echo h($price==0?"":$price); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent" name="pod_unit[]" id="pod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_UNIT"])?$d["POD_UNIT"]:""); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent" name="req_no[]" id="req_no_<?php echo h($i); ?>" value="<?php echo h(isset($d["REQ_NO"])?$d["REQ_NO"]:""); ?>" readonly></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent text-right" id="pod_amount_<?php echo h($i); ?>" value="<?php echo h($amount==0?"":number_format($amount,2,".","")); ?>" readonly></td>
                                                <td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();" title="Delete"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addRow()"><i class="fas fa-plus"></i> Tambah Baris</button>
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Simpan Data</button>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm" onclick="showReceive()"><i class="fas fa-box-open"></i> Show Recv.</button>
                        </div>
                    </form>
                </div>

                <form id="deleteForm" method="post" action="po.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="po_id" id="delete_po_id" value="">
                </form>

                <div class="card">
                    <div class="card-body p-0 table-responsive" style="max-height: 400px;">
                        <table class="table table-striped table-hover table-head-fixed text-nowrap table-sm text-sm">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th>PO No</th>
                                    <th>Date</th>
                                    <th>Date DO</th>
                                    <th class="text-center">Cur</th>
                                    <th>Sup.Code</th>
                                    <th>Supplier</th>
                                    <th class="text-center">Close</th>
                                    <th class="text-right">Detail</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="poListBody">
                                <?php while($r=sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)){ ?>
                                    <?php $poIdLink=intval($r["PO_ID"]); $editUrl="po.php?edit=".$poIdLink; ?>
                                    <tr class="pointer po-row" data-poid="<?php echo h($poIdLink); ?>" onclick="goEdit('<?php echo h($poIdLink); ?>')">
                                        <td class="text-center text-info"><a href="<?php echo h($editUrl); ?>" onclick="event.stopPropagation();" class="text-info"><i class="fas fa-caret-right"></i></a></td>
                                        <td><a href="<?php echo h($editUrl); ?>" onclick="event.stopPropagation();" class="text-dark font-weight-bold"><?php echo h($r["PO_NUM"]); ?></a></td>
                                        <td><?php echo h(fmt_date_view($r["PO_DATE"])); ?></td>
                                        <td><?php echo h(fmt_date_view($r["PO_DATEDO"])); ?></td>
                                        <td class="text-center"><?php echo h($r["PO_CUR"]); ?></td>
                                        <td><?php echo h($r["SUP_CODE"]); ?></td>
                                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                                        <td class="text-center"><?php if(intval($r["PO_CLOSE"])==1) { echo '<span class="badge badge-secondary">YES</span>'; } ?></td>
                                        <td class="text-right"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0,".",",")); ?></td>
                                        <td class="text-right"><?php echo h(number_format(floatval($r["TOTAL_AMOUNT"]),2,".",",")); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="acBox" class="ac-box"></div>

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>