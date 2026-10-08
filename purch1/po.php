<?php
if (session_id() == "") session_start();

require_once dirname(__DIR__) . "/config/db_plant1.php";
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
<html>
<head>
<meta charset="utf-8">
<title>Receive List</title>
<style>
html,body{margin:0;padding:10px;background:#d4d0c8;font-family:Tahoma,Arial,sans-serif;font-size:12px;color:#000}
.title{background:#000080;color:#fff;padding:6px 8px;font-weight:bold;margin-bottom:8px}
table{border-collapse:collapse;width:100%;background:#fff}
th{background:#1d2a3d;color:#fff;border:1px solid #777;padding:5px;text-align:left}
td{border:1px solid #ccc;padding:4px}
.num{text-align:right}.center{text-align:center}.right{text-align:right}
.total-row td{background:#eee;font-weight:bold}
.btn{height:24px;padding:2px 12px;border:1px solid #777;background:#eee;cursor:pointer;font-size:12px;margin-top:8px}
</style>
</head>
<body>
<div class="title">RECEIVE LIST - PO: <?php echo h($poNum); ?> / ITEM: <?php echo h($itemCode." - ".$itemName); ?></div>
<table>
<thead>
<tr>
<th style="width:35px;">No</th>
<th style="width:100px;">Receive Date</th>
<th style="width:110px;">Receive No</th>
<th style="width:120px;">DO No</th>
<th style="width:90px;">Qty</th>
<th style="width:90px;">Sup.Code</th>
<th>Supplier</th>
</tr>
</thead>
<tbody>
<?php $no=1; foreach($rows as $r) { ?>
<tr>
<td class="center"><?php echo h($no); ?></td>
<td><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td>
<td><?php echo h($r["RCV_NO"]); ?></td>
<td><?php echo h($r["RCV_DONO"]); ?></td>
<td class="num"><?php echo h(number_format(floatval($r["RCVD_QTY"]),2,".",",")); ?></td>
<td><?php echo h($r["SUP_CODE"]); ?></td>
<td><?php echo h($r["SUP_COMP"]); ?></td>
</tr>
<?php $no++; } ?>
<?php if ($no == 1) { ?>
<tr><td colspan="7" class="center">Data receive belum ada.</td></tr>
<?php } else { ?>
<tr class="total-row">
<td colspan="4" class="right">TOTAL</td>
<td class="num"><?php echo h(number_format($total,2,".",",")); ?></td>
<td colspan="2"></td>
</tr>
<?php } ?>
</tbody>
</table>
<button type="button" class="btn" onclick="window.close()">CLOSE</button>
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
                $sqlH = "
                    UPDATE dbo.PO SET
                        SUP_ID=?,
                        PO_DATE=?,
                        PO_TO=?,
                        PO_TODEF=?,
                        PO_CUR=?,
                        PO_CLOSE=?,
                        PO_TERM=?,
                        PO_TERMDEL=?,
                        PO_TERMDELSCH=?,
                        PO_REV=ISNULL(PO_REV,0)+1,
                        PO_DATEDO=?,
                        PO_NUM=?,
                        PO_REM=?
                    WHERE PO_ID=?
                ";
                $paramsH = array($supId,$poDate,$poTo,$poToDef,$poCur,$poClose,$poTerm,$poTermDel,$poTermDelSch,$poDateDo,$poNum,$poRem,$poId);
                $stmtH = sqlsrv_query($conn,$sqlH,$paramsH);
                if ($stmtH === false) { $ok=false; $error="Simpan header PO gagal:\n".sql_error_text(); }
            } else {
                $sqlH = "
                    INSERT INTO dbo.PO
                    (SUP_ID, PO_DATE, PO_TO, PO_TODEF, PO_CUR, PO_CLOSE, PO_TERM, PO_TERMDEL, PO_TERMDELSCH, PO_REV, PO_DATEDO, PO_NUM, PO_REM)
                    OUTPUT INSERTED.PO_ID
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ";
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

                    $sqlD = "
                        INSERT INTO dbo.PO_DETAIL
                        (PO_ID, REQ_ID, ITEM_ID, POD_QTY, POD_PRICE, POD_UNIT, POD_DUE)
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?)
                    ";
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
    "PO_ID"=>"",
    "PO_NUM"=>"",
    "PO_DATE"=>date("Y-m-d"),
    "PO_DATEDO"=>date("Y-m-d"),
    "SUP_ID"=>"",
    "SUP_CODE"=>"",
    "SUP_COMP"=>"",
    "PO_TO"=>"",
    "TO_SUP_CODE"=>"",
    "TO_SUP_COMP"=>"",
    "PO_TODEF"=>0,
    "PO_CUR"=>"IDR",
    "PO_CLOSE"=>0,
    "PO_TERM"=>"",
    "PO_TERMDEL"=>"",
    "PO_TERMDELSCH"=>0,
    "PO_REM"=>""
);
$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT
            P.PO_ID,
            P.PO_NUM,
            P.PO_DATE,
            P.PO_DATEDO,
            P.SUP_ID,
            P.PO_TO,
            P.PO_TODEF,
            P.PO_CUR,
            P.PO_CLOSE,
            P.PO_TERM,
            P.PO_TERMDEL,
            P.PO_TERMDELSCH,
            P.PO_REM,
            S.SUP_CODE,
            S.SUP_COMP,
            ST.SUP_CODE AS TO_SUP_CODE,
            ST.SUP_COMP AS TO_SUP_COMP
        FROM dbo.PO P
        LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID
        LEFT JOIN dbo.SUPPLIER ST ON P.PO_TO = ST.SUP_ID
        WHERE P.PO_ID = ?
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
        SELECT
            D.PO_ID,
            D.REQ_ID,
            D.ITEM_ID,
            D.POD_QTY,
            D.POD_PRICE,
            D.POD_UNIT,
            D.POD_DUE,
            (ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)) AS POD_AMOUNT,
            I.ITEM_CODE,
            I.ITEM_NAME,
            R.REQ_NO
        FROM dbo.PO_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        LEFT JOIN dbo.REQUISITION R ON D.REQ_ID = R.REQ_ID
        WHERE D.PO_ID = ?
        ORDER BY I.ITEM_CODE, R.REQ_NO
    ";
    $stmtD = sqlsrv_query($conn,$sqlD,array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
}

if (getv("msg") == "saved") $message = "PO berhasil disimpan.";
if (getv("msg") == "deleted") $message = "PO berhasil dihapus.";

/* SUPPLIER DATA */
$supplierAuto = array();
$sqlSup = "
    SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_TERM
    FROM dbo.SUPPLIER
    WHERE ISNULL(SUP_CODE,'') <> ''
    ORDER BY SUP_CODE
";
$stmtSup = sqlsrv_query($conn,$sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array(
            "SUP_ID"=>intval($s["SUP_ID"]),
            "SUP_CODE"=>trim((string)$s["SUP_CODE"]),
            "SUP_COMP"=>trim((string)$s["SUP_COMP"]),
            "CURR_CODE"=>trim((string)$s["CURR_CODE"]),
            "SUP_TERM"=>trim((string)$s["SUP_TERM"])
        );
    }
}

/* PO AUTO COMPLETE UNTUK SEARCH */
$poAuto = array();
$sqlPoAuto = "
    SELECT TOP 500
        P.PO_ID,
        P.PO_NUM,
        P.PO_DATE,
        S.SUP_CODE,
        S.SUP_COMP
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
        SELECT
            RD.ITEM_ID,
            I.ITEM_CODE,
            I.ITEM_NAME,
            I.ITEM_UNIT,
            RD.REQ_ID,
            R.REQ_DUE,
            R.REQ_DATE,
            R.REQ_NO,
            RD.REQD_QTY,
            ISNULL(IPV.POD_QTY,0) AS POD_QTY,
            RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) AS REQ_BQTY
        FROM dbo.REQ_DETAIL RD
        INNER JOIN dbo.REQUISITION R ON RD.REQ_ID = R.REQ_ID
        INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
        LEFT JOIN dbo.ITEM_REQ_PO_VIEW IPV ON RD.REQ_ID = IPV.REQ_ID AND RD.ITEM_ID = IPV.ITEM_ID
        WHERE R.REQ_CLOSE = 0
          AND RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) > 0
    ),
    QUO AS
    (
        SELECT
            Q.SUP_ID,
            Q.CURR_CODE,
            QD.ITEM_ID,
            QD.QUOD_PRICE,
            ROW_NUMBER() OVER
            (
                PARTITION BY Q.SUP_ID, Q.CURR_CODE, QD.ITEM_ID
                ORDER BY
                    CASE WHEN ISNULL(QD.QUOD_ACTIVE,0)=1 THEN 0 ELSE 1 END,
                    Q.QUO_DATE DESC,
                    Q.QUO_ID DESC
            ) AS RN
        FROM dbo.QUOTATION Q
        INNER JOIN dbo.QUOT_DETAIL QD ON Q.QUO_ID = QD.QUO_ID
        WHERE ISNULL(QD.QUOD_PRICE,0) > 0
    )
    SELECT TOP 2000
        O.ITEM_ID,
        O.ITEM_CODE,
        O.ITEM_NAME,
        O.ITEM_UNIT,
        O.REQ_ID,
        O.REQ_DUE,
        O.REQ_DATE,
        O.REQ_NO,
        O.REQD_QTY,
        O.POD_QTY,
        O.REQ_BQTY,
        Q.SUP_ID,
        Q.CURR_CODE,
        Q.QUOD_PRICE
    FROM OSREQ O
    INNER JOIN QUO Q ON O.ITEM_ID = Q.ITEM_ID AND Q.RN = 1
    ORDER BY Q.SUP_ID, Q.CURR_CODE, O.ITEM_CODE, O.REQ_DUE, O.REQ_NO
";
$stmtOS = sqlsrv_query($conn,$sqlOS);
if ($stmtOS !== false) {
    while ($o = sqlsrv_fetch_array($stmtOS, SQLSRV_FETCH_ASSOC)) {
        $osReqAuto[] = array(
            "ITEM_ID"=>intval($o["ITEM_ID"]),
            "ITEM_CODE"=>trim((string)$o["ITEM_CODE"]),
            "ITEM_NAME"=>trim((string)$o["ITEM_NAME"]),
            "ITEM_UNIT"=>trim((string)$o["ITEM_UNIT"]),
            "REQ_ID"=>intval($o["REQ_ID"]),
            "REQ_DUE"=>fmt_date($o["REQ_DUE"]),
            "REQ_DUE_VIEW"=>fmt_date_view($o["REQ_DUE"]),
            "REQ_DATE"=>fmt_date($o["REQ_DATE"]),
            "REQ_DATE_VIEW"=>fmt_date_view($o["REQ_DATE"]),
            "REQ_NO"=>trim((string)$o["REQ_NO"]),
            "REQD_QTY"=>floatval($o["REQD_QTY"]),
            "POD_QTY"=>floatval($o["POD_QTY"]),
            "REQ_BQTY"=>floatval($o["REQ_BQTY"]),
            "SUP_ID"=>intval($o["SUP_ID"]),
            "CURR_CODE"=>trim((string)$o["CURR_CODE"]),
            "QUOD_PRICE"=>floatval($o["QUOD_PRICE"])
        );
    }
}

/* CURR LIST */
$currList = array("IDR","USD","JPY","YEN");
$stmtCurr = @sqlsrv_query($conn, "
    SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE
    FROM dbo.CURR
    WHERE ISNULL(CURR_CODE,'') <> ''
    ORDER BY LTRIM(RTRIM(CURR_CODE))
");
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
    SELECT TOP 300
        P.PO_ID,
        P.PO_NUM,
        P.PO_DATE,
        P.PO_DATEDO,
        P.PO_CUR,
        P.PO_CLOSE,
        S.SUP_CODE,
        S.SUP_COMP,
        COUNT(D.ITEM_ID) AS DETAIL_COUNT,
        ISNULL(SUM(ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)),0) AS TOTAL_AMOUNT
    FROM dbo.PO P
    LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID
    LEFT JOIN dbo.PO_DETAIL D ON P.PO_ID = D.PO_ID
    $where
    GROUP BY
        P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.PO_CUR, P.PO_CLOSE, S.SUP_CODE, S.SUP_COMP
    ORDER BY P.PO_DATE DESC, P.PO_NUM DESC
";
$stmtList = sqlsrv_query($conn,$sqlList,$paramsList);
if ($stmtList === false) die("<pre>Query list PO error:\n".sql_error_text()."</pre>");

if (count($details) == 0) {
    for ($i=0; $i<4; $i++) {
        $details[] = array(
            "REQ_ID"=>"",
            "ITEM_ID"=>"",
            "ITEM_CODE"=>"",
            "ITEM_NAME"=>"",
            "REQ_NO"=>"",
            "POD_DUE"=>date("Y-m-d"),
            "POD_QTY"=>"",
            "POD_PRICE"=>"",
            "POD_UNIT"=>"",
            "POD_AMOUNT"=>""
        );
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Purchase Order</title>
<style>
        /* ==== CSS UNIVERSAL - COMPACT, MINIMALIS, FIT TO FONT ==== */
        html, body {
            margin: 0; padding: 0;
            background-color: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px; color: #333;
        }

        .wrap {
            background: #fff;
            padding: 15px 20px;
            margin: 15px auto;
            max-width: 98%;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .page-title {
            font-size: 15px; font-weight: bold; color: #333;
            border-bottom: 2px solid #28a745;
            padding-bottom: 8px; margin: -5px -5px 15px -5px;
            text-transform: uppercase;
        }
        .page-title::before { content: "🛒 Manajemen "; } 

        /* === GABUNGAN ACTION BUTTON & SEARCH BOX === */
        .top-action-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;
            padding: 8px 12px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;
        }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        
        .search-box form { display: flex; align-items: center; gap: 5px; margin: 0; white-space: nowrap; }
        .search-box input { width: 220px !important; margin: 0; }

        .bottom-buttons { margin-top: 15px; justify-content: space-between; border-top: 1px solid #ddd; padding-top: 10px; display: flex; gap: 5px; }
        .header-toolbar { display: flex; gap: 5px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; }

        .btn {
            height: 24px; padding: 0 10px; border: 1px solid #ccc; border-radius: 3px;
            background: #fff; color: #333; font-family: inherit; font-size: 11px; font-weight: bold;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; text-transform: uppercase; transition: background 0.1s;
        }
        .btn:hover { background: #e2e6ea; }

        .btn-save { background: #28a745; color: #fff; border-color: #28a745; }
        .btn-save:hover { background: #218838; }

        .btn-del { background: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-del:hover { background: #c82333; }

        .btn-x { background: #dc3545; color: #fff; width: 22px; padding: 0; border: none; border-radius: 3px; }
        .btn-x:hover { background: #c82333; }

        .btn-po, .btn-os { background: #17a2b8; color: #fff; border-color: #17a2b8; }
        .btn-po:hover, .btn-os:hover { background: #138496; }

        /* Notifikasi */
        .msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 8px 12px; border-radius: 3px; margin-bottom: 15px; }
        .err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 8px 12px; border-radius: 3px; margin-bottom: 15px; white-space: pre-wrap; }

        /* Area Form Header */
        .label { display: block; margin-bottom: 3px; font-weight: normal; color: #555; font-size: 10px; text-transform: capitalize; }

        table.form-table, table.bc-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.form-table td, table.bc-table td { padding: 0 15px 10px 0; vertical-align: top; }

        /* Input Field Styling */
        input[type=text], input[type=date], select, textarea {
            width: 100% !important;
            height: 24px; border: 1px solid #ccc; border-radius: 3px;
            padding: 2px 6px; font-family: inherit; font-size: 11px; box-sizing: border-box;
            background: #fff; color: #333;
        }
        input[type=text]:focus, input[type=date]:focus, select:focus, textarea:focus {
            outline: none; border-color: #80bdff; box-shadow: 0 0 0 0.1rem rgba(0,123,255,.25);
        }
        textarea { height: 35px; resize: vertical; }

        input[readonly], input[style*="background:#f9f9f9"], input[style*="background:#ddd;"] {
            background: #e9ecef !important; color: #495057; cursor: not-allowed;
        }
        input[type=checkbox] { width: 12px; height: 12px; vertical-align: middle; cursor: pointer; margin: 0 4px 0 0; }
        label { cursor: pointer; display: inline-flex; align-items: center; font-weight: normal; color: #333; margin-right: 10px; font-size: 11px; }

        /* Batasi lebar maksimal input header */
        .po-no, .rcv-no, .reqno { max-width: 150px; }
        .date { max-width: 120px; }
        .supplier-code, .sup-code { max-width: 100px; }
        .cur, .curr { max-width: 80px; }
        .term { max-width: 250px; }

        /* Area Tabel Data */
        table.detail, table.grid {
            width: 100%; min-width: 800px;
            border-collapse: collapse; background: #fff;
            border: 1px solid #dee2e6; margin-bottom: 10px;
        }
        table.detail th, table.grid th {
            background: #e9ecef; color: #495057; border: 1px solid #dee2e6;
            padding: 4px 6px; text-align: left; font-weight: bold; font-size: 11px;
        }
        table.detail td, table.grid td {
            border: 1px solid #dee2e6; padding: 2px 4px; vertical-align: middle; font-size: 11px;
        }
        table.detail input {
            width: 100%; height: 20px; border: 1px solid transparent;
            padding: 0 4px; background: transparent; border-radius: 2px;
        }
        table.detail input:focus { border-color: #80bdff; background: #fff; }
        table.detail input:hover:not([readonly]) { border-color: #ccc; }

        tr.detail-selected td { background: #f8f9fa; }
        table.grid tr:hover td { background: #f4f6f9; cursor: pointer; }

        .num { text-align: right; }
        .center { text-align: center; }
        .action-cell { display: flex; justify-content: center; align-items: center; gap: 4px; }

        .grid-wrap {
            width: 100%; max-height: 250px; overflow: auto;
            border: 1px solid #dee2e6; margin-top: 10px; background: #fff;
        }
        .go-btn { color: #007bff; font-weight: bold; font-size: 12px; }

        /* Autocomplete */
        .ac-box {
            position: absolute; z-index: 9999; background: #fff; border: 1px solid #ccc;
            border-radius: 3px; max-height: 200px; overflow-y: auto; min-width: 250px;
            display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 0;
        }
        .ac-item { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; font-size: 11px; }
        .ac-item:hover, .ac-item.active { background: #007bff; color: #fff; }

        /* Mode Print */
        @media print {
            body { background: #fff; padding: 0; }
            .no-print, .top-action-bar, .bottom-buttons, .header-toolbar { display: none !important; }
            .wrap { border: none; padding: 0; margin: 0; box-shadow: none; }
        }
</style>
<script>
var supplierData = <?php echo json_encode($supplierAuto); ?>;
var osReqData = <?php echo json_encode($osReqAuto); ?>;
var poData = <?php echo json_encode($poAuto); ?>;

var acBox=null, acItems=[], acIndex=-1, acMode="", acRow=-1, supplierTarget="sup";
var rowSeq=<?php echo count($details); ?>;
var selectedDetailRow=null;

function byId(id){return document.getElementById(id);}
function setValue(id,value){var el=byId(id); if(el) el.value=(value==null?"":value);}
function initAC(){acBox=byId("acBox");}
function hideAC(){if(acBox){acBox.style.display="none";acBox.innerHTML="";} acItems=[];acIndex=-1;acMode="";acRow=-1;}
function positionAC(input){initAC();var r=input.getBoundingClientRect();acBox.style.left=(r.left+window.scrollX)+"px";acBox.style.top=(r.bottom+window.scrollY)+"px";acBox.style.width=(r.width<360?360:r.width)+"px";}
function renderAC(renderText,pickFunc){initAC();acBox.innerHTML="";for(var i=0;i<acItems.length;i++){var d=document.createElement("div");d.className="ac-item"+(i==acIndex?" active":"");d.innerHTML=renderText(acItems[i]);d.setAttribute("data-index",i);d.onmousedown=function(){pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]);};acBox.appendChild(d);}acBox.style.display=acItems.length>0?"block":"none";}
function acMove(step,renderText,pickFunc){if(acItems.length<=0)return;acIndex+=step;if(acIndex<0)acIndex=acItems.length-1;if(acIndex>=acItems.length)acIndex=0;renderAC(renderText,pickFunc);}
function acEnter(pickFunc){if(acItems.length<=0)return false;if(acIndex<0)acIndex=0;pickFunc(acItems[acIndex]);return true;}

function submitSave(allowEmpty){if(byId("allow_empty_detail")) byId("allow_empty_detail").value=allowEmpty?"1":"0";var f=byId("poForm");if(f) f.submit();}
function newData(){window.location.href="po.php";}
function goEdit(id){if(!id)return;window.location.href="po.php?edit="+encodeURIComponent(id);}
function deleteCurrent(){var id=byId("po_id").value;if(id==""||id=="0"){alert("Pilih PO dulu.");return;}if(!confirm("Yakin hapus PO ini?"))return;byId("delete_po_id").value=id;byId("deleteForm").submit();}
function headerKey(e){if(e.key==="Enter"){if(acMode!==""&&acItems.length>0)return true;e.preventDefault();submitSave(false);return false;}}

function selectDetailRow(tr){var rows=document.querySelectorAll("#detailBody tr");for(var i=0;i<rows.length;i++) rows[i].classList.remove("detail-selected");selectedDetailRow=tr;if(tr)tr.classList.add("detail-selected");}

function showSupplierAC(input,target){initAC();supplierTarget=target;var key=(input.value||"").toUpperCase();acMode="supplier";acItems=[];acIndex=-1;if(key.length<1){hideAC();return;}for(var i=0;i<supplierData.length;i++){var s=supplierData[i];var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||"");if(t.toUpperCase().indexOf(key)>=0)acItems.push(s);if(acItems.length>=40)break;}positionAC(input);renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);}
function supplierKey(e,input,target){supplierTarget=target;if(e.key==="ArrowDown"){e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input,target);acMove(1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;}if(e.key==="ArrowUp"){e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input,target);acMove(-1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;}if(e.key==="Enter"){if(acMode==="supplier"&&acItems.length>0){e.preventDefault();acEnter(pickSupplier);return false;}e.preventDefault();submitSave(false);return false;}if(e.key==="Escape")hideAC();}
function pickSupplier(s){if(supplierTarget=="to"){setValue("po_to",s.SUP_ID);setValue("to_sup_code",s.SUP_CODE);setValue("to_sup_comp",s.SUP_COMP);}else{setValue("sup_id",s.SUP_ID);setValue("sup_code",s.SUP_CODE);setValue("sup_comp",s.SUP_COMP);if(s.CURR_CODE)setValue("po_cur",s.CURR_CODE);if(s.SUP_TERM)setValue("po_term",s.SUP_TERM);if(!byId("po_to").value||byId("po_to").value=="0"){setValue("po_to",s.SUP_ID);setValue("to_sup_code",s.SUP_CODE);setValue("to_sup_comp",s.SUP_COMP);}}hideAC();}

function showOSAC(input,row){initAC();var supId=byId("sup_id").value;var curr=byId("po_cur").value;if(!supId||supId=="0"){alert("Pilih supplier dulu.");input.value="";return;}if(!curr){alert("Pilih currency dulu.");input.value="";return;}var key=(input.value||"").toUpperCase();acMode="osreq";acRow=row;acItems=[];acIndex=-1;if(key.length<1){hideAC();return;}for(var i=0;i<osReqData.length;i++){var o=osReqData[i];if(parseInt(o.SUP_ID,10)!=parseInt(supId,10))continue;if((o.CURR_CODE||"").toUpperCase()!=(curr||"").toUpperCase())continue;var t=(o.ITEM_CODE||"")+" "+(o.ITEM_NAME||"")+" "+(o.REQ_NO||"")+" "+(o.REQ_DUE_VIEW||"");if(t.toUpperCase().indexOf(key)>=0)acItems.push(o);if(acItems.length>=60)break;}positionAC(input);renderAC(function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);}
function osKey(e,input,row){if(e.key==="ArrowDown"){e.preventDefault();if(acMode!=="osreq"||acItems.length==0)showOSAC(input,row);acMove(1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);return false;}if(e.key==="ArrowUp"){e.preventDefault();if(acMode!=="osreq"||acItems.length==0)showOSAC(input,row);acMove(-1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;},pickOS);return false;}if(e.key==="Enter"){if(acMode==="osreq"&&acItems.length>0){e.preventDefault();acEnter(pickOS);return false;}e.preventDefault();return false;}if(e.key==="Escape")hideAC();}
function pickOS(o){var row=acRow;setValue("req_id_"+row,o.REQ_ID);setValue("item_id_"+row,o.ITEM_ID);setValue("item_code_"+row,o.ITEM_CODE);setValue("item_name_"+row,o.ITEM_NAME);setValue("pod_due_"+row,o.REQ_DUE);setValue("pod_qty_"+row,o.REQ_BQTY);setValue("pod_unit_"+row,o.ITEM_UNIT);setValue("req_no_"+row,o.REQ_NO);setValue("pod_price_"+row,o.QUOD_PRICE);hideAC();calcAmount(row);var q=byId("pod_qty_"+row);if(q){q.focus();q.select();}}

function calcAmount(row){var q=byId("pod_qty_"+row),p=byId("pod_price_"+row);if(!q||!p)return;var qty=parseFloat((q.value||"0").replace(/,/g,""));var price=parseFloat((p.value||"0").replace(/,/g,""));if(isNaN(qty))qty=0;if(isNaN(price))price=0;setValue("pod_amount_"+row,(qty*price).toFixed(2));}
function fieldEnterSave(e,row){if(e.key==="Enter"){e.preventDefault();submitSave(false);return false;}if(row!==undefined)setTimeout(function(){calcAmount(row);},10);}

function addRow(){var tbody=byId("detailBody");var row=rowSeq;rowSeq++;var tr=document.createElement("tr");tr.onclick=function(){selectDetailRow(this);};tr.innerHTML='<td class="center row-no"></td><td><input type="hidden" name="req_id[]" id="req_id_'+row+'"><input type="hidden" name="item_id[]" id="item_id_'+row+'"><input type="text" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')"></td><td><input type="text" name="item_name[]" id="item_name_'+row+'" autocomplete="off" oninput="showOSAC(this, '+row+')" onkeydown="osKey(event, this, '+row+')"></td><td><input type="date" name="pod_due[]" id="pod_due_'+row+'" value="<?php echo date("Y-m-d"); ?>"></td><td><input type="text" name="pod_qty[]" id="pod_qty_'+row+'" class="num" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td><td><input type="text" name="pod_price[]" id="pod_price_'+row+'" class="num" onkeyup="calcAmount('+row+')" onkeydown="fieldEnterSave(event, '+row+')"></td><td><input type="text" name="pod_unit[]" id="pod_unit_'+row+'"></td><td><input type="text" name="req_no[]" id="req_no_'+row+'" readonly></td><td><input type="text" id="pod_amount_'+row+'" class="num" readonly></td><td><div class="action-cell"><button type="button" class="btn btn-x" onclick="deleteRow(this); event.stopPropagation();">X</button></div></td>';tbody.appendChild(tr);renumberRows();selectDetailRow(tr);byId("item_code_"+row).focus();}
function deleteRow(btn){var tr=btn.parentNode.parentNode.parentNode;if(!confirm("Hapus baris detail ini?"))return;tr.parentNode.removeChild(tr);renumberRows();var id=byId("po_id").value;if(id!=""&&id!="0")submitSave(true);}
function renumberRows(){var rows=byId("detailBody").getElementsByTagName("tr");for(var i=0;i<rows.length;i++){var c=rows[i].getElementsByClassName("row-no")[0];if(c)c.innerHTML=i+1;else rows[i].cells[0].innerHTML=i+1;}}

function showReceive(){var poNum=byId("po_num").value;if(!poNum){alert("Pilih / simpan PO dulu.");return;}var tr=selectedDetailRow;if(!tr){var rows=byId("detailBody").getElementsByTagName("tr");if(rows.length>0)tr=rows[0];}if(!tr){alert("Pilih detail item dulu.");return;}var itemInput=tr.querySelector('input[name="item_id[]"]');var itemId=itemInput?itemInput.value:"";if(!itemId||itemId=="0"){alert("Pilih detail item dulu.");return;}window.open("po.php?action=receive_view&po_num="+encodeURIComponent(poNum)+"&item_id="+encodeURIComponent(itemId),"RECEIVE_LIST","width=760,height=420,scrollbars=yes,resizable=yes");}
function printPO(){var poId=byId("po_id").value;if(!poId||poId=="0"){alert("Pilih / simpan PO dulu.");return;}window.open("print_po.php?po_id="+encodeURIComponent(poId),"PRINT_PO","width=900,height=700,scrollbars=yes,resizable=yes");}

/* Fungsi Autocomplete untuk PO Search */
function showPOAC(input) {
    initAC();
    var key = (input.value || "").toUpperCase();
    acMode = "po"; acItems = []; acIndex = -1;
    if(key.length < 1){ hideAC(); return; }

    for(var i=0; i<poData.length; i++) {
        var p = poData[i];
        var text = (p.PO_NUM || "") + " " + (p.PO_DATE || "") + " " + (p.SUP_CODE || "") + " " + (p.SUP_COMP || "");
        if(text.toUpperCase().indexOf(key) >= 0) { acItems.push(p); }
        if(acItems.length >= 30) break;
    }
    
    positionAC(input);
    renderAC(function(p){ return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP; }, pickPO);
}
function poSearchKey(e, input) {
    if(e.key === "ArrowDown") { e.preventDefault(); if(acMode !== "po" || acItems.length == 0) showPOAC(input); acMove(1, function(p){ return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP; }, pickPO); return false; }
    if(e.key === "ArrowUp") { e.preventDefault(); if(acMode !== "po" || acItems.length == 0) showPOAC(input); acMove(-1, function(p){ return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP; }, pickPO); return false; }
    if(e.key === "Enter") { if(acMode === "po" && acItems.length > 0) { e.preventDefault(); acEnter(pickPO); return false; } return true; }
    if(e.key === "Escape") { hideAC(); }
}
function pickPO(p) { hideAC(); window.location.href = "po.php?edit=" + encodeURIComponent(p.PO_ID); }

document.addEventListener("DOMContentLoaded",function(){var rows=document.querySelectorAll("#detailBody tr");for(var i=0;i<rows.length;i++){rows[i].onclick=function(){selectDetailRow(this);};}});
document.addEventListener("click",function(e){initAC();if(acBox&&!acBox.contains(e.target)){if(!e.target||!e.target.getAttribute||e.target.getAttribute("autocomplete")!=="off")hideAC();}});
</script>
</head>
<body>
<div class="page-title">Purchase Order</div>
<div class="wrap">
<?php if ($message != "") { ?><div class="msg"><?php echo h($message); ?></div><?php } ?>
<?php if ($error != "") { ?><div class="err"><?php echo h($error); ?></div><?php } ?>

<div class="top-action-bar no-print">
    <div class="action-buttons">
        <button type="button" class="btn" onclick="newData()">NEW</button>
        <button type="submit" form="poForm" class="btn btn-save">SAVE PO</button>
        <button type="button" class="btn btn-del" onclick="deleteCurrent()">DELETE</button>
        <a href="dashboard_purch.php" class="btn">CLOSE</a>
    </div>
    <div class="search-box">
        <form method="get" action="po.php" id="searchForm">
            <span style="font-weight:bold; margin-right:5px; color:#555;">Search:</span>
            <!-- FIX: Tambahkan autocomplete="off" oninput="showPOAC(this)" onkeydown="poSearchKey(event, this)" id="po_search" -->
            <input type="text" name="q" id="po_search" value="<?php echo h($q); ?>" placeholder="PO No / Supplier" autocomplete="off" oninput="showPOAC(this)" onkeydown="poSearchKey(event, this)">
            <button type="submit" class="btn">SEARCH</button>
            <a href="po.php" class="btn">ALL</a>
        </form>
    </div>
</div>

<form id="poForm" method="post" action="po.php">
<input type="hidden" name="action" value="save">
<input type="hidden" name="po_id" id="po_id" value="<?php echo h($header["PO_ID"]); ?>">
<input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
<input type="hidden" name="po_to" id="po_to" value="<?php echo h($header["PO_TO"]); ?>">
<input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

<table class="form-table">
<tr>
<td><span class="label">PO No.</span><input type="text" name="po_num" id="po_num" class="po-no" value="<?php echo h($header["PO_NUM"]); ?>" placeholder="Auto" onkeydown="headerKey(event)"></td>
<td><span class="label">Date</span><input type="date" name="po_date" class="date" value="<?php echo h($header["PO_DATE"]); ?>" onkeydown="headerKey(event)"></td>
<td><span class="label">Date DO</span><input type="date" name="po_datedo" class="date" value="<?php echo h($header["PO_DATEDO"]); ?>" onkeydown="headerKey(event)"></td>
<td><span class="label">Close</span><input type="checkbox" name="po_close" value="1" <?php echo intval($header["PO_CLOSE"]) == 1 ? "checked" : ""; ?>></td>
<td><span class="label">Supplier</span><input type="text" name="sup_code" id="sup_code" class="supplier-code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')"></td>
<td><span class="label">&nbsp;</span><input type="text" name="sup_comp" id="sup_comp" class="supplier-name" value="<?php echo h($header["SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'sup')" onkeydown="supplierKey(event, this, 'sup')"></td>
</tr>
<tr>
<td><span class="label">Terms.of Pmt</span><input type="text" name="po_term" id="po_term" class="po-no" value="<?php echo h($header["PO_TERM"]); ?>" onkeydown="headerKey(event)"></td>
<td colspan="2"><span class="label">Term.of Delv</span><input type="text" name="po_termdel" id="po_termdel" class="term" value="<?php echo h($header["PO_TERMDEL"]); ?>" onkeydown="headerKey(event)"></td>
<td><span class="label">Cur.</span><select name="po_cur" id="po_cur" class="cur" onkeydown="headerKey(event)"><?php foreach($currList as $c){ ?><option value="<?php echo h($c); ?>" <?php echo trim((string)$header["PO_CUR"])==$c ? "selected" : ""; ?>><?php echo h($c); ?></option><?php } ?></select></td>
<td><span class="label">Invoice To</span><input type="text" name="to_sup_code" id="to_sup_code" class="supplier-code" value="<?php echo h($header["TO_SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')"></td>
<td><span class="label">&nbsp;</span><input type="text" name="to_sup_comp" id="to_sup_comp" class="supplier-name" value="<?php echo h($header["TO_SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'to')" onkeydown="supplierKey(event, this, 'to')"></td>
</tr>
<tr>
<td colspan="3"><span class="label">Remark</span><textarea name="po_rem" class="remark" onkeydown="headerKey(event)"><?php echo h($header["PO_REM"]); ?></textarea></td>
<td colspan="3">
<span class="label">&nbsp;</span>
<label><input type="checkbox" name="po_todef" value="1" <?php echo intval($header["PO_TODEF"]) == 1 ? "checked" : ""; ?>> PT. IMC TEKNO</label>
&nbsp;&nbsp;
<label><input type="checkbox" name="po_termdelsch" value="1" <?php echo intval($header["PO_TERMDELSCH"]) == 1 ? "checked" : ""; ?>> Delivery Schedule</label>
</td>
</tr>
</table>

<div class="header-toolbar">
<a href="next_po.php" class="btn">Set Next PO #</a>
<button type="button" class="btn" onclick="printPO()">Print</button>
</div>

<table class="detail">
<thead>
<tr>
<th style="width:25px;"></th>
<th style="width:80px;">Code</th>
<th style="width:160px;">Item Name</th>
<th style="width:95px;">Due On</th>
<th style="width:55px;">Qty.</th>
<th style="width:55px;">Rate</th>
<th style="width:50px;">Unit</th>
<th style="width:50px;">Req.#</th>
<th style="width:70px;">Amount</th>
<th style="width:28px;">X</th>
</tr>
</thead>
<tbody id="detailBody">
<?php for($i=0;$i<count($details);$i++){ ?>
<?php
$d=$details[$i];
$qty=isset($d["POD_QTY"])?floatval($d["POD_QTY"]):0;
$price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0;
$amount=isset($d["POD_AMOUNT"])?floatval($d["POD_AMOUNT"]):($qty*$price);
?>
<tr>
<td class="center row-no"><?php echo h($i+1); ?></td>
<td>
<input type="hidden" name="req_id[]" id="req_id_<?php echo h($i); ?>" value="<?php echo h($d["REQ_ID"]); ?>">
<input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
<input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)">
</td>
<td><input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" autocomplete="off" oninput="showOSAC(this, <?php echo h($i); ?>)" onkeydown="osKey(event, this, <?php echo h($i); ?>)"></td>
<td><input type="date" name="pod_due[]" id="pod_due_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_DUE"])?fmt_date($d["POD_DUE"]):date("Y-m-d")); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
<td><input type="text" name="pod_qty[]" id="pod_qty_<?php echo h($i); ?>" class="num" value="<?php echo h($qty==0?"":$qty); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
<td><input type="text" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" class="num" value="<?php echo h($price==0?"":$price); ?>" onkeyup="calcAmount(<?php echo h($i); ?>)" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
<td><input type="text" name="pod_unit[]" id="pod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_UNIT"])?$d["POD_UNIT"]:""); ?>" onkeydown="fieldEnterSave(event, <?php echo h($i); ?>)"></td>
<td><input type="text" name="req_no[]" id="req_no_<?php echo h($i); ?>" value="<?php echo h(isset($d["REQ_NO"])?$d["REQ_NO"]:""); ?>" readonly></td>
<td><input type="text" id="pod_amount_<?php echo h($i); ?>" class="num" value="<?php echo h($amount==0?"":number_format($amount,2,".","")); ?>" readonly></td>
<td><div class="action-cell"><button type="button" class="btn btn-x" onclick="deleteRow(this); event.stopPropagation();">X</button></div></td>
</tr>
<?php } ?>
</tbody>
</table>

<div class="bottom-buttons">
<div>
<button type="button" class="btn" onclick="addRow()">+ Tambah Baris</button>
<button type="submit" class="btn btn-save">✔ Simpan</button>
</div>
<div>
<button type="button" class="btn" onclick="showReceive()">Show Recv.</button>
</div>
</div>
</form>

<form id="deleteForm" method="post" action="po.php">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="po_id" id="delete_po_id" value="">
</form>

<div class="grid-wrap">
<table class="grid">
<thead>
<tr>
<th style="width:22px;"></th>
<th style="width:135px;">PO No</th>
<th style="width:80px;">Date</th>
<th style="width:80px;">Date DO</th>
<th style="width:45px;">Cur</th>
<th style="width:70px;">Sup.Code</th>
<th style="width:210px;">Supplier</th>
<th style="width:45px;">Close</th>
<th style="width:50px;">Detail</th>
<th style="width:90px;">Amount</th>
</tr>
</thead>
<tbody id="poListBody">
<?php while($r=sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)){ ?>
<?php $poIdLink=intval($r["PO_ID"]); $editUrl="po.php?edit=".$poIdLink; ?>
<tr class="po-row" data-poid="<?php echo h($poIdLink); ?>" onclick="goEdit('<?php echo h($poIdLink); ?>')" style="cursor:pointer;">
<td class="center go-btn"><a href="<?php echo h($editUrl); ?>" onclick="event.stopPropagation();" style="text-decoration:none;color:#000080;font-weight:bold;">▶</a></td>
<td><a href="<?php echo h($editUrl); ?>" onclick="event.stopPropagation();" style="text-decoration:none;color:#000;"><?php echo h($r["PO_NUM"]); ?></a></td>
<td><?php echo h(fmt_date_view($r["PO_DATE"])); ?></td>
<td><?php echo h(fmt_date_view($r["PO_DATEDO"])); ?></td>
<td class="center"><?php echo h($r["PO_CUR"]); ?></td>
<td><?php echo h($r["SUP_CODE"]); ?></td>
<td><?php echo h($r["SUP_COMP"]); ?></td>
<td class="center"><?php echo intval($r["PO_CLOSE"])==1 ? "YES" : ""; ?></td>
<td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0,".",",")); ?></td>
<td class="num"><?php echo h(number_format(floatval($r["TOTAL_AMOUNT"]),2,".",",")); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

</div>
<div id="acBox" class="ac-box"></div>
</body>
</html>