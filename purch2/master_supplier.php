<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

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
                        $supCode, $supComp, $currCode, $supAddr1, $supAddr2,
                        $supCity, $supPhone, $supFax, $supEmail, $supConta,
                        $supTerm, $supNpwp, $supAbbr, $supPe, $supId
                    );
                } else {
                    $sql = "
                        INSERT INTO dbo.SUPPLIER
                        (
                            SUP_CODE, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
                            SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA,
                            SUP_TERM, SUP_NPWP, SUP_ABBR, SUP_PE
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";

                    $params = array(
                        $supCode, $supComp, $currCode, $supAddr1, $supAddr2,
                        $supCity, $supPhone, $supFax, $supEmail, $supConta,
                        $supTerm, $supNpwp, $supAbbr, $supPe
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
if (getv("msg", "") == "saved") $message = "Data supplier berhasil disimpan.";
if (getv("msg", "") == "deleted") $message = "Data supplier berhasil dihapus.";

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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Supplier - Purchasing</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE 3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        .pointer:hover {
            cursor: pointer;
            background-color: #f4f6f9 !important;
        }
        .row-inactive {
            color: #888;
            background-color: #fcfcfc;
        }
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            max-height: 250px;
            overflow-y: auto;
            min-width: 300px;
            display: none;
            font-size: 14px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .ac-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f4f4f4;
        }
        .ac-item:last-child {
            border-bottom: none;
        }
        .ac-item:hover, .ac-item.active {
            background: #6f42c1; /* Purple match theme */
            color: #ffffff;
        }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto); ?>;
        var acBox = null, acItems = [], acIndex = -1, acMode = "";

        function byId(id) { return document.getElementById(id); }
        function setValue(id, value) { var el = byId(id); if (el) el.value = value == null ? "" : value; }

        function initAC() { acBox = document.getElementById("acBox"); }
        function hideAC() {
            if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; }
            acItems = []; acIndex = -1; acMode = "";
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

        function showSupplierAC(input, target) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = target;
            acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < supplierData.length; i++) {
                var s = supplierData[i];
                var matchText = "";
                
                if (target === 'code') {
                    matchText = s.SUP_CODE;
                } else if (target === 'comp') {
                    matchText = s.SUP_COMP;
                } else if (target === 'search') {
                    matchText = (s.SUP_CODE || "") + " " + (s.SUP_COMP || "");
                }
                
                if ((matchText || "").toUpperCase().indexOf(key) >= 0) {
                    acItems.push(s);
                }
                if (acItems.length >= 40) break;
            }

            positionAC(input);
            renderAC(function (s) {
                return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP;
            }, pickSupplier);
        }

        function supplierKey(e, input, target) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== target || acItems.length === 0) showSupplierAC(input, target);
                acMove(1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== target || acItems.length === 0) showSupplierAC(input, target);
                acMove(-1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === target && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickSupplier); return false;
                }
                
                // Allow standard form submit if using the search box and no autocomplete item is active
                if (target === 'search') {
                    return true;
                }
                return true; 
            }
            if (e.key === "Escape") hideAC();
        }

        function pickSupplier(s) {
            if (acMode === 'search') {
                hideAC();
                goEdit(s.SUP_ID);
            } else {
                fillSupplier(s);
                hideAC();
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

        function newData() { window.location.href = "master_supplier.php"; }
        function confirmDelete() { return confirm("Yakin hapus supplier ini?"); }
        function goEdit(id) { window.location.href = "master_supplier.php?edit=" + encodeURIComponent(id); }

        function deleteCurrent() {
            var id = byId("sup_id").value;
            if (id == "" || id == "0") {
                alert("Pilih supplier dulu.");
                return;
            }
            if (!confirmDelete()) return;

            byId("delete_sup_id").value = id;
            byId("deleteForm").submit();
        }

        // Close Auto Complete if clicked outside
        document.addEventListener("click", function (e) {
            initAC();
            if (acBox && !acBox.contains(e.target)) {
                if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
            }
        });
    </script>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container-fluid">
            <a href="dashboard_purchasing.php" class="navbar-brand">
                <span class="brand-text font-weight-light"><i class="fas fa-building text-purple mr-2"></i> Purchasing System</span>
            </a>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a href="dashboard_home.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Close</a>
                </li>
            </ul>
        </div>
    </nav>
    <!-- /.navbar -->

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Master Supplier</h1>
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

                <div class="card card-purple card-outline">
                    
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <button type="button" class="btn btn-sm btn-default mr-1" onclick="newData()"><i class="fas fa-file"></i> New</button>
                            <button type="submit" form="supplierForm" class="btn btn-sm btn-success mr-1"><i class="fas fa-save"></i> Save Supplier</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteCurrent()"><i class="fas fa-trash"></i> Delete</button>
                        </h3>
                        
                        <div class="card-tools">
                            <form method="get" action="master_supplier.php" class="form-inline m-0">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <input type="text" name="q" id="search_q" class="form-control float-right" value="<?php echo h($q); ?>" placeholder="Search Code / Company" autocomplete="off" oninput="showSupplierAC(this, 'search')" onkeydown="supplierKey(event, this, 'search')">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                        <a href="master_supplier.php" class="btn btn-default" title="Reset"><i class="fas fa-sync"></i></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form id="supplierForm" method="post" action="master_supplier.php">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($edit["SUP_ID"]); ?>">

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Code</label>
                                        <input type="text" class="form-control form-control-sm text-uppercase" name="sup_code" id="sup_code" value="<?php echo h($edit["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'code')" onkeydown="supplierKey(event, this, 'code')" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Company</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_comp" id="sup_comp" value="<?php echo h($edit["SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this, 'comp')" onkeydown="supplierKey(event, this, 'comp')" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Curr</label>
                                        <select class="form-control form-control-sm" name="curr_code" id="curr_code">
                                            <?php foreach ($currList as $c) { ?>
                                                <option value="<?php echo h($c); ?>" <?php echo trim((string)$edit["CURR_CODE"]) == $c ? "selected" : ""; ?>><?php echo h($c); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Abbr.</label>
                                        <input type="text" class="form-control form-control-sm text-uppercase" name="sup_abbr" id="sup_abbr" value="<?php echo h($edit["SUP_ABBR"]); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Address 1</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_addr1" id="sup_addr1" value="<?php echo h($edit["SUP_ADDR1"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Address 2</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_addr2" id="sup_addr2" value="<?php echo h($edit["SUP_ADDR2"]); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>City</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_city" id="sup_city" value="<?php echo h($edit["SUP_CITY"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_phone" id="sup_phone" value="<?php echo h($edit["SUP_PHONE"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fax</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_fax" id="sup_fax" value="<?php echo h($edit["SUP_FAX"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Contact</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_conta" id="sup_conta" value="<?php echo h($edit["SUP_CONTA"]); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_email" id="sup_email" value="<?php echo h($edit["SUP_EMAIL"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Term</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_term" id="sup_term" list="termList" value="<?php echo h($edit["SUP_TERM"]); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>NPWP</label>
                                        <input type="text" class="form-control form-control-sm" name="sup_npwp" id="sup_npwp" value="<?php echo h($edit["SUP_NPWP"]); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input class="custom-control-input" type="checkbox" id="sup_pe" name="sup_pe" value="1" <?php echo intval($edit["SUP_PE"]) == 1 ? "checked" : ""; ?>>
                                        <label for="sup_pe" class="custom-control-label">Tidak Aktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <datalist id="termList">
                    <?php foreach ($termList as $t) { ?>
                        <option value="<?php echo h($t); ?>">
                    <?php } ?>
                </datalist>

                <form id="deleteForm" method="post" action="master_supplier.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="sup_id" id="delete_sup_id" value="">
                </form>

                <div class="card">
                    <div class="card-body p-0 table-responsive" style="max-height: 400px;">
                        <table class="table table-striped table-hover table-head-fixed text-nowrap table-sm text-sm">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th style="width:120px;">SUP_CODE</th>
                                    <th>SUP_COMP</th>
                                    <th class="text-center" style="width:120px;">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) { 
                                    $pe = isset($r["SUP_PE"]) ? intval($r["SUP_PE"]) : 0;
                                    $rowClass = $pe == 1 ? "row-inactive pointer" : "row-active pointer";
                                ?>
                                    <tr class="<?php echo h($rowClass); ?>" onclick="goEdit('<?php echo h($r["SUP_ID"]); ?>')">
                                        <td class="text-center text-purple"><i class="fas fa-caret-right"></i></td>
                                        <td class="font-weight-bold"><?php echo h($r["SUP_CODE"]); ?></td>
                                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                                        <td class="text-center">
                                            <?php if ($pe == 1) { ?>
                                                <span class="badge badge-secondary"><i class="fas fa-ban"></i> Tidak Aktif</span>
                                            <?php } else { ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Aktif</span>
                                            <?php } ?>
                                        </td>
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