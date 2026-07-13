<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/global.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function getv($name, $default = "") {
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function postv($name, $default = "") {
    return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function yn_pe($value) {
    return intval($value) == 1 ? "Tidak Aktif" : "Aktif";
}

$message = "";
$error = "";

$action = postv("action", "");
$editId = intval(getv("edit", "0"));

/* ======================================================
   DELETE
====================================================== */
if ($action == "delete") {
    $supId = intval(postv("sup_id", "0"));

    if ($supId <= 0) {
        $error = "ID supplier tidak valid.";
    } else {
        $sql = "DELETE FROM dbo.SUPPLIER WHERE SUP_ID = ?";
        $stmt = sqlsrv_query($conn, $sql, array($supId));

        if ($stmt === false) {
            $error = "Delete gagal:\n" . sql_error_text();
        } else {
            header("Location: master_supplier.php?msg=deleted");
            exit;
        }
    }
}

/* ======================================================
   SAVE / UPDATE
====================================================== */
if ($action == "save") {
    $supId    = intval(postv("sup_id", "0"));
    $supCode  = strtoupper(postv("sup_code", ""));
    $supComp  = postv("sup_comp", "");
    $currCode = strtoupper(postv("curr_code", "IDR"));
    $supAbbr  = strtoupper(postv("sup_abbr", ""));

    $supAddr1 = postv("sup_addr1", "");
    $supAddr2 = postv("sup_addr2", "");
    $supCity  = postv("sup_city", "");
    $supPhone = postv("sup_phone", "");
    $supFax   = postv("sup_fax", "");
    $supEmail = postv("sup_email", "");
    $supConta = postv("sup_conta", "");
    $supTerm  = postv("sup_term", "");
    $supNpwp  = postv("sup_npwp", "");
    $supPe    = isset($_POST["sup_pe"]) ? 1 : 0; // 1 = Tidak Aktif

    if ($supCode == "") {
        $error = "Supplier Code wajib diisi.";
    } elseif ($supComp == "") {
        $error = "Company wajib diisi.";
    } else {
        $sqlDup = "
            SELECT COUNT(*) AS CNT
            FROM dbo.SUPPLIER
            WHERE SUP_CODE = ?
              AND SUP_ID <> ?
        ";
        $stmtDup = sqlsrv_query($conn, $sqlDup, array($supCode, $supId));

        if ($stmtDup === false) {
            $error = "Cek duplikasi gagal:\n" . sql_error_text();
        } else {
            $dup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);

            if (intval($dup["CNT"]) > 0) {
                $error = "Supplier Code sudah ada.";
            } else {
                if ($supId > 0) {
                    $sql = "
                        UPDATE dbo.SUPPLIER SET
                            SUP_CODE  = ?,
                            SUP_COMP  = ?,
                            CURR_CODE = ?,
                            SUP_ADDR1 = ?,
                            SUP_ADDR2 = ?,
                            SUP_CITY  = ?,
                            SUP_PHONE = ?,
                            SUP_FAX   = ?,
                            SUP_EMAIL = ?,
                            SUP_CONTA = ?,
                            SUP_TERM  = ?,
                            SUP_NPWP  = ?,
                            SUP_ABBR  = ?,
                            SUP_PE    = ?
                        WHERE SUP_ID = ?
                    ";

                    $params = array(
                        $supCode,
                        $supComp,
                        $currCode,
                        $supAddr1,
                        $supAddr2,
                        $supCity,
                        $supPhone,
                        $supFax,
                        $supEmail,
                        $supConta,
                        $supTerm,
                        $supNpwp,
                        $supAbbr,
                        $supPe,
                        $supId
                    );
                } else {
                    $sql = "
                        INSERT INTO dbo.SUPPLIER
                        (
                            SUP_CODE,
                            SUP_COMP,
                            CURR_CODE,
                            SUP_ADDR1,
                            SUP_ADDR2,
                            SUP_CITY,
                            SUP_PHONE,
                            SUP_FAX,
                            SUP_EMAIL,
                            SUP_CONTA,
                            SUP_TERM,
                            SUP_NPWP,
                            SUP_ABBR,
                            SUP_PE
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";

                    $params = array(
                        $supCode,
                        $supComp,
                        $currCode,
                        $supAddr1,
                        $supAddr2,
                        $supCity,
                        $supPhone,
                        $supFax,
                        $supEmail,
                        $supConta,
                        $supTerm,
                        $supNpwp,
                        $supAbbr,
                        $supPe
                    );
                }

                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    $error = "Simpan gagal:\n" . sql_error_text();
                } else {
                    header("Location: master_supplier.php?msg=saved");
                    exit;
                }
            }
        }
    }
}

/* ======================================================
   EDIT DATA
====================================================== */
$edit = array(
    "SUP_ID"    => "",
    "SUP_CODE"  => "",
    "SUP_COMP"  => "",
    "CURR_CODE" => "IDR",
    "SUP_ADDR1" => "",
    "SUP_ADDR2" => "",
    "SUP_CITY"  => "",
    "SUP_PHONE" => "",
    "SUP_FAX"   => "",
    "SUP_EMAIL" => "",
    "SUP_CONTA" => "",
    "SUP_TERM"  => "",
    "SUP_NPWP"  => "",
    "SUP_ABBR"  => "",
    "SUP_PE"    => 0
);

if ($editId > 0) {
    $sqlEdit = "
        SELECT
            SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
            SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA, SUP_TERM,
            SUP_NPWP, SUP_ABBR, SUP_PE
        FROM dbo.SUPPLIER
        WHERE SUP_ID = ?
    ";
    $stmtEdit = sqlsrv_query($conn, $sqlEdit, array($editId));

    if ($stmtEdit !== false) {
        $rowEdit = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC);
        if ($rowEdit) {
            foreach ($edit as $k => $v) {
                if (isset($rowEdit[$k])) {
                    $edit[$k] = $rowEdit[$k];
                }
            }
        }
    }
}

/* ======================================================
   MESSAGE
====================================================== */
if (getv("msg", "") == "saved") {
    $message = "Data supplier berhasil disimpan.";
}

if (getv("msg", "") == "deleted") {
    $message = "Data supplier berhasil dihapus.";
}

/* ======================================================
   LIST DATA
====================================================== */
$q = getv("q", "");

$where = "";
$paramsList = array();

if ($q != "") {
    $where = "
        WHERE SUP_CODE LIKE ?
           OR SUP_COMP LIKE ?
           OR SUP_CITY LIKE ?
           OR SUP_PHONE LIKE ?
    ";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
}

$sqlList = "
    SELECT TOP 500
        SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
        SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA, SUP_TERM,
        SUP_NPWP, SUP_ABBR, SUP_PE
    FROM dbo.SUPPLIER
    $where
    ORDER BY SUP_CODE
";

$stmtList = sqlsrv_query($conn, $sqlList, $paramsList);

if ($stmtList === false) {
    die("<pre>Query supplier error:\n" . sql_error_text() . "</pre>");
}

/* ======================================================
   AUTO COMPLETE SUPPLIER DATA
====================================================== */
$supplierAuto = array();

$sqlAuto = "
    SELECT TOP 1000
        SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
        SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA, SUP_TERM,
        SUP_NPWP, SUP_ABBR, SUP_PE
    FROM dbo.SUPPLIER
    ORDER BY SUP_CODE
";

$stmtAuto = sqlsrv_query($conn, $sqlAuto);
if ($stmtAuto !== false) {
    while ($a = sqlsrv_fetch_array($stmtAuto, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array(
            "SUP_ID"    => isset($a["SUP_ID"]) ? $a["SUP_ID"] : "",
            "SUP_CODE"  => trim((string)$a["SUP_CODE"]),
            "SUP_COMP"  => trim((string)$a["SUP_COMP"]),
            "CURR_CODE" => trim((string)$a["CURR_CODE"]),
            "SUP_ADDR1" => trim((string)$a["SUP_ADDR1"]),
            "SUP_ADDR2" => trim((string)$a["SUP_ADDR2"]),
            "SUP_CITY"  => trim((string)$a["SUP_CITY"]),
            "SUP_PHONE" => trim((string)$a["SUP_PHONE"]),
            "SUP_FAX"   => trim((string)$a["SUP_FAX"]),
            "SUP_EMAIL" => trim((string)$a["SUP_EMAIL"]),
            "SUP_CONTA" => trim((string)$a["SUP_CONTA"]),
            "SUP_TERM"  => trim((string)$a["SUP_TERM"]),
            "SUP_NPWP"  => trim((string)$a["SUP_NPWP"]),
            "SUP_ABBR"  => trim((string)$a["SUP_ABBR"]),
            "SUP_PE"    => isset($a["SUP_PE"]) ? intval($a["SUP_PE"]) : 0
        );
    }
}

/* ======================================================
   CURRENCY LIST
====================================================== */
$currList = array("IDR", "USD", "JPY");

$sqlCurr = "
    SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE
    FROM dbo.CURR
    WHERE ISNULL(CURR_CODE, '') <> ''
    ORDER BY LTRIM(RTRIM(CURR_CODE))
";

$stmtCurr = @sqlsrv_query($conn, $sqlCurr);
if ($stmtCurr !== false) {
    $currList = array();
    while ($c = sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$c["CURR_CODE"]);
        if ($v != "") {
            $currList[] = $v;
        }
    }
}

/* ======================================================
   TERM LIST
====================================================== */
$termList = array();

$sqlTerm = "
    SELECT DISTINCT LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50)))) AS SUP_TERM
    FROM dbo.SUPPLIER
    WHERE SUP_TERM IS NOT NULL
      AND LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50)))) <> ''
    ORDER BY LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50))))
";

$stmtTerm = sqlsrv_query($conn, $sqlTerm);
if ($stmtTerm !== false) {
    while ($t = sqlsrv_fetch_array($stmtTerm, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$t["SUP_TERM"]);
        if ($v != "") {
            $termList[] = $v;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Supplier - Purchasing</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #0b8b80;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #ffffff;
        }

        .wrap {
            padding: 8px 10px;
        }

        .top-buttons {
            margin-bottom: 6px;
        }

        .btn {
            height: 24px;
            padding: 2px 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            color: #000000;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            line-height: 18px;
            box-sizing: border-box;
        }

        .btn:hover {
            background: #dcdcdc;
        }

        .btn-save {
            background: #dff0d8;
        }

        .btn-del {
            background: #f2dede;
        }

        .msg {
            background: #dff0d8;
            color: #006100;
            border: 1px solid #6aa84f;
            padding: 6px;
            margin-bottom: 6px;
        }

        .err {
            background: #f2dede;
            color: #990000;
            border: 1px solid #cc0000;
            padding: 6px;
            margin-bottom: 6px;
            white-space: pre-wrap;
        }

        .form-area {
            width: 700px;
        }

        table.form-table {
            border-collapse: collapse;
            width: 700px;
        }

        table.form-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .label {
            display: block;
            color: #ffffff;
            font-weight: bold;
            margin-bottom: 2px;
        }

        input[type="text"],
        select {
            height: 23px;
            border: 1px solid #777777;
            padding: 2px 4px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
        }

        .code {
            width: 65px;
            text-transform: uppercase;
        }

        .company {
            width: 310px;
        }

        .curr {
            width: 65px;
        }

        .abbr {
            width: 45px;
            text-transform: uppercase;
        }

        .addr {
            width: 345px;
        }

        .addr2 {
            width: 338px;
        }

        .city {
            width: 160px;
        }

        .phone {
            width: 175px;
        }

        .fax {
            width: 225px;
        }

        .email {
            width: 235px;
        }

        .contact {
            width: 105px;
        }

        .term {
            width: 105px;
        }

        .npwp {
            width: 225px;
        }

        .grid-wrap {
            width: 690px;
            height: 170px;
            overflow: auto;
            background: #ffffff;
            border: 1px solid #777777;
            margin-top: 38px;
            margin-left: 16px;
        }

        table.grid {
            border-collapse: collapse;
            width: 100%;
            background: #ffffff;
            color: #000000;
        }

        table.grid th {
            background: #d9d9d9;
            color: #777777;
            border: 1px solid #888888;
            padding: 3px;
            text-align: left;
            font-weight: normal;
            height: 20px;
        }

        table.grid td {
            border: 1px solid #cccccc;
            padding: 2px 4px;
            height: 18px;
            white-space: nowrap;
        }

        table.grid tr:hover {
            background: #cce5ff;
            cursor: pointer;
        }

        .row-active {
            background: #ffffff;
        }

        .row-inactive {
            background: #f8f8f8;
            color: #777777;
        }

        .bottom-buttons {
            margin-top: 16px;
            margin-left: 16px;
        }

        .nav {
            display: inline-block;
            margin-left: 8px;
        }

        .nav button {
            min-width: 25px;
            height: 23px;
            border: 1px solid #777777;
            background: #eeeeee;
            cursor: pointer;
        }

        .search-area {
            margin-left: 16px;
            margin-top: 8px;
            color: #ffffff;
        }

        .search-area input {
            width: 260px;
        }

        .checkbox-cell {
            text-align: center;
        }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto); ?>;

        function byId(id) {
            return document.getElementById(id);
        }

        function setValue(id, value) {
            var el = byId(id);
            if (el) {
                el.value = value == null ? "" : value;
            }
        }

        function fillSupplier(s) {
            setValue("sup_id", s.SUP_ID);
            setValue("sup_code", s.SUP_CODE);
            setValue("sup_comp", s.SUP_COMP);
            setValue("curr_code", s.CURR_CODE || "IDR");
            setValue("sup_abbr", s.SUP_ABBR);
            setValue("sup_addr1", s.SUP_ADDR1);
            setValue("sup_addr2", s.SUP_ADDR2);
            setValue("sup_city", s.SUP_CITY);
            setValue("sup_phone", s.SUP_PHONE);
            setValue("sup_fax", s.SUP_FAX);
            setValue("sup_email", s.SUP_EMAIL);
            setValue("sup_conta", s.SUP_CONTA);
            setValue("sup_term", s.SUP_TERM);
            setValue("sup_npwp", s.SUP_NPWP);

            var pe = byId("sup_pe");
            if (pe) {
                pe.checked = parseInt(s.SUP_PE, 10) == 1;
            }
        }

        function findByCode(code) {
            code = (code || "").toUpperCase();

            for (var i = 0; i < supplierData.length; i++) {
                if ((supplierData[i].SUP_CODE || "").toUpperCase() == code) {
                    return supplierData[i];
                }
            }

            return null;
        }

        function findByCompany(comp) {
            comp = (comp || "").toUpperCase();

            for (var i = 0; i < supplierData.length; i++) {
                if ((supplierData[i].SUP_COMP || "").toUpperCase() == comp) {
                    return supplierData[i];
                }
            }

            return null;
        }

        function autocompleteCode() {
            var code = byId("sup_code").value;
            var s = findByCode(code);

            if (s) {
                fillSupplier(s);
            }
        }

        function autocompleteCompany() {
            var comp = byId("sup_comp").value;
            var s = findByCompany(comp);

            if (s) {
                fillSupplier(s);
            }
        }

        function newData() {
            window.location.href = "master_supplier.php";
        }

        function confirmDelete() {
            return confirm("Yakin hapus supplier ini?");
        }

        function goEdit(id) {
            window.location.href = "master_supplier.php?edit=" + encodeURIComponent(id);
        }

        function deleteCurrent() {
            var id = byId("sup_id").value;

            if (id == "" || id == "0") {
                alert("Pilih supplier dulu.");
                return;
            }

            if (!confirmDelete()) {
                return;
            }

            byId("delete_sup_id").value = id;
            byId("deleteForm").submit();
        }
    </script>
</head>
<body>

<div class="wrap">

    <?php if ($message != "") { ?>
        <div class="msg"><?php echo h($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="err"><?php echo h($error); ?></div>
    <?php } ?>

    <div class="top-buttons">
        <button type="button" class="btn" onclick="newData()">NEW</button>
        <button type="submit" form="supplierForm" class="btn btn-save">SAVE SUPPLIER</button>
        <button type="button" class="btn btn-del" onclick="deleteCurrent()">DELETE</button>
        <a href="dashboard_purchasing.php" class="btn">CLOSE</a>
    </div>

    <form id="supplierForm" method="post" action="master_supplier.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($edit["SUP_ID"]); ?>">

        <div class="form-area">
            <table class="form-table">
                <tr>
                    <td>
                        <span class="label">Code</span>
                        <input type="text"
                               name="sup_code"
                               id="sup_code"
                               class="code"
                               value="<?php echo h($edit["SUP_CODE"]); ?>"
                               list="supplierCodeList"
                               onblur="autocompleteCode()"
                               required>
                    </td>

                    <td>
                        <span class="label">Company</span>
                        <input type="text"
                               name="sup_comp"
                               id="sup_comp"
                               class="company"
                               value="<?php echo h($edit["SUP_COMP"]); ?>"
                               list="supplierCompanyList"
                               onblur="autocompleteCompany()"
                               required>
                    </td>

                    <td>
                        <span class="label">Curr</span>
                        <select name="curr_code" id="curr_code" class="curr">
                            <?php foreach ($currList as $c) { ?>
                                <option value="<?php echo h($c); ?>" <?php echo trim((string)$edit["CURR_CODE"]) == $c ? "selected" : ""; ?>>
                                    <?php echo h($c); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </td>

                    <td>
                        <span class="label">Abbr.</span>
                        <input type="text"
                               name="sup_abbr"
                               id="sup_abbr"
                               class="abbr"
                               value="<?php echo h($edit["SUP_ABBR"]); ?>">
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <span class="label">Adress</span>
                        <input type="text"
                               name="sup_addr1"
                               id="sup_addr1"
                               class="addr"
                               value="<?php echo h($edit["SUP_ADDR1"]); ?>">
                    </td>

                    <td colspan="2">
                        <span class="label">&nbsp;</span>
                        <input type="text"
                               name="sup_addr2"
                               id="sup_addr2"
                               class="addr2"
                               value="<?php echo h($edit["SUP_ADDR2"]); ?>">
                    </td>
                </tr>

                <tr>
                    <td>
                        <span class="label">City</span>
                        <input type="text"
                               name="sup_city"
                               id="sup_city"
                               class="city"
                               value="<?php echo h($edit["SUP_CITY"]); ?>">
                    </td>

                    <td>
                        <span class="label">Phone</span>
                        <input type="text"
                               name="sup_phone"
                               id="sup_phone"
                               class="phone"
                               value="<?php echo h($edit["SUP_PHONE"]); ?>">
                    </td>

                    <td colspan="2">
                        <span class="label">Fax</span>
                        <input type="text"
                               name="sup_fax"
                               id="sup_fax"
                               class="fax"
                               value="<?php echo h($edit["SUP_FAX"]); ?>">
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <span class="label">Email</span>
                        <input type="text"
                               name="sup_email"
                               id="sup_email"
                               class="email"
                               value="<?php echo h($edit["SUP_EMAIL"]); ?>">
                    </td>

                    <td>
                        <span class="label">Contact</span>
                        <input type="text"
                               name="sup_conta"
                               id="sup_conta"
                               class="contact"
                               value="<?php echo h($edit["SUP_CONTA"]); ?>">
                    </td>

                    <td>
                        <span class="label">Term</span>
                        <input type="text"
                               name="sup_term"
                               id="sup_term"
                               class="term"
                               list="termList"
                               value="<?php echo h($edit["SUP_TERM"]); ?>">
                    </td>

                    <td>
                        <span class="label">NPWP</span>
                        <input type="text"
                               name="sup_npwp"
                               id="sup_npwp"
                               class="npwp"
                               value="<?php echo h($edit["SUP_NPWP"]); ?>">
                    </td>
                </tr>

                <tr>
                    <td colspan="4">
                        <label>
                            <input type="checkbox"
                                   name="sup_pe"
                                   id="sup_pe"
                                   value="1"
                                   <?php echo intval($edit["SUP_PE"]) == 1 ? "checked" : ""; ?>>
                            Tidak Aktif
                        </label>
                    </td>
                </tr>
            </table>
        </div>
    </form>

    <datalist id="supplierCodeList">
        <?php foreach ($supplierAuto as $s) { ?>
            <option value="<?php echo h($s["SUP_CODE"]); ?>"><?php echo h($s["SUP_COMP"]); ?></option>
        <?php } ?>
    </datalist>

    <datalist id="supplierCompanyList">
        <?php foreach ($supplierAuto as $s) { ?>
            <option value="<?php echo h($s["SUP_COMP"]); ?>"><?php echo h($s["SUP_CODE"]); ?></option>
        <?php } ?>
    </datalist>

    <datalist id="termList">
        <?php foreach ($termList as $t) { ?>
            <option value="<?php echo h($t); ?>">
        <?php } ?>
    </datalist>

    <form id="deleteForm" method="post" action="master_supplier.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="sup_id" id="delete_sup_id" value="">
    </form>

    <div class="search-area">
        <form method="get" action="master_supplier.php">
            Search:
            <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Code / company / city / phone">
            <button type="submit" class="btn">SEARCH</button>
            <a href="master_supplier.php" class="btn">ALL</a>
        </form>
    </div>

    <div class="grid-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:18px;"></th>
                    <th style="width:70px;">SUP_CODE</th>
                    <th>SUP_COMP</th>
                    <th style="width:95px;">SUP_PE</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) { ?>
                    <?php
                        $pe = isset($r["SUP_PE"]) ? intval($r["SUP_PE"]) : 0;
                        $rowClass = $pe == 1 ? "row-inactive" : "row-active";
                    ?>
                    <tr class="<?php echo h($rowClass); ?>" onclick="goEdit('<?php echo h($r["SUP_ID"]); ?>')">
                        <td>▶</td>
                        <td><?php echo h($r["SUP_CODE"]); ?></td>
                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                        <td>
                            <input type="checkbox" disabled <?php echo $pe == 1 ? "checked" : ""; ?>>
                            <?php echo h(yn_pe($pe)); ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="bottom-buttons">
        <button type="button" class="btn">Terms</button>

        <span class="nav">
            <button type="button" onclick="newData()">＋</button>
            <button type="submit" form="supplierForm">✔</button>
            <button type="button" onclick="deleteCurrent()">－</button>
            <button type="button" onclick="location.reload()">↻</button>
        </span>
    </div>

</div>

</body>
</html>