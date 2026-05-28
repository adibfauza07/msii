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

function get_value($name, $default) {
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function post_value($name, $default) {
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function to_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }
    return intval($value);
}

function json_out($arr) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr);
    exit;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

$action = get_value("action", "");

/* ==========================================================
   AJAX: LOAD MACHINE
========================================================== */
if ($action == "load") {
    $sql = "
        SELECT
            M.MAC_ID,
            ISNULL(M.MAC_CODE, '') AS MAC_CODE,
            ISNULL(M.MAC_SERIAL, '') AS MAC_SERIAL,
            ISNULL(M.MAC_TYPE, '') AS MAC_TYPE,
            ISNULL(M.MAG_ID, 0) AS MAG_ID,
            ISNULL(M.MAC_ACTIVE, 0) AS MAC_ACTIVE,
            ISNULL(M.MAC_NO, '') AS MAC_NO,
            ISNULL(G.MAG_STATION, '') AS MAG_STATION,
            ISNULL(G.MAG_LOC, '') AS MAG_LOC
        FROM dbo.MAC M
        LEFT JOIN dbo.MAG G ON M.MAG_ID = G.MAG_ID
        ORDER BY M.MAC_CODE, M.MAC_SERIAL
    ";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "MAC_ID" => to_int($r["MAC_ID"]),
            "MAC_CODE" => trim((string)$r["MAC_CODE"]),
            "MAC_SERIAL" => trim((string)$r["MAC_SERIAL"]),
            "MAC_TYPE" => trim((string)$r["MAC_TYPE"]),
            "MAG_ID" => to_int($r["MAG_ID"]),
            "MAC_ACTIVE" => to_int($r["MAC_ACTIVE"]),
            "MAC_NO" => trim((string)$r["MAC_NO"]),
            "MAG_STATION" => trim((string)$r["MAG_STATION"]),
            "MAG_LOC" => trim((string)$r["MAG_LOC"])
        );
    }

    json_out(array("success" => true, "rows" => $rows));
}

/* ==========================================================
   AJAX: SAVE MACHINE
   - MAC_ID kosong = INSERT
   - MAC_ID ada    = UPDATE
========================================================== */
if ($action == "save") {
    $mac_id = to_int(post_value("MAC_ID", "0"));
    $mac_code = post_value("MAC_CODE", "");
    $mac_serial = post_value("MAC_SERIAL", "");
    $mac_type = post_value("MAC_TYPE", "");
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $mac_active = to_int(post_value("MAC_ACTIVE", "0"));
    $mac_no = post_value("MAC_NO", "");

    if ($mac_code == "") {
        json_out(array("success" => false, "message" => "MAC_CODE belum diisi."));
    }

    if ($mag_id <= 0) {
        json_out(array("success" => false, "message" => "STATION / MAG belum dipilih."));
    }

    if ($mac_id > 0) {
        $sql = "
            UPDATE dbo.MAC
            SET
                MAC_CODE = ?,
                MAC_SERIAL = ?,
                MAC_TYPE = ?,
                MAG_ID = ?,
                MAC_ACTIVE = ?,
                MAC_NO = ?
            WHERE MAC_ID = ?
        ";

        $params = array(
            $mac_code,
            $mac_serial,
            $mac_type,
            $mag_id,
            $mac_active,
            $mac_no,
            $mac_id
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }

        json_out(array("success" => true, "message" => "Machine berhasil diupdate.", "MAC_ID" => $mac_id));
    }

    $sql = "
        INSERT INTO dbo.MAC
            (MAC_CODE, MAC_SERIAL, MAC_TYPE, MAG_ID, MAC_ACTIVE, MAC_NO)
        OUTPUT INSERTED.MAC_ID
        VALUES
            (?, ?, ?, ?, ?, ?)
    ";

    $params = array(
        $mac_code,
        $mac_serial,
        $mac_type,
        $mag_id,
        $mac_active,
        $mac_no
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$r || !isset($r["MAC_ID"])) {
        json_out(array("success" => false, "message" => "Insert berhasil tapi MAC_ID tidak terbaca."));
    }

    json_out(array("success" => true, "message" => "Machine berhasil ditambah.", "MAC_ID" => to_int($r["MAC_ID"])));
}

/* ==========================================================
   AJAX: DELETE MACHINE
========================================================== */
if ($action == "delete") {
    $mac_id = to_int(post_value("MAC_ID", "0"));

    if ($mac_id <= 0) {
        json_out(array("success" => false, "message" => "MAC_ID kosong."));
    }

    /*
        Safety check ringan: kalau machine sudah dipakai di WO, jangan hapus.
        Kalau table WO tidak ada / beda nama, blok ini tidak menghentikan proses.
    */
    $cekWo = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.WO WHERE MAC_ID = ?", array($mac_id));
    if ($cekWo !== false) {
        $cw = sqlsrv_fetch_array($cekWo, SQLSRV_FETCH_ASSOC);
        if ($cw && to_int($cw["CNT"]) > 0) {
            json_out(array("success" => false, "message" => "Tidak bisa hapus. Machine sudah dipakai di WO."));
        }
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MAC WHERE MAC_ID = ?", array($mac_id));

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "Machine berhasil dihapus."));
}

/* ==========================================================
   LOAD MAG LIST UNTUK DROPDOWN STATION
========================================================== */
$magList = array();
$magSql = "
    SELECT
        MAG_ID,
        ISNULL(MAG_STATION, '') AS MAG_STATION,
        ISNULL(MAG_LOC, '') AS MAG_LOC
    FROM dbo.MAG
    ORDER BY MAG_STATION, MAG_LOC
";
$magStmt = sqlsrv_query($conn, $magSql);
if ($magStmt !== false) {
    while ($m = sqlsrv_fetch_array($magStmt, SQLSRV_FETCH_ASSOC)) {
        $magList[] = array(
            "MAG_ID" => to_int($m["MAG_ID"]),
            "MAG_STATION" => trim((string)$m["MAG_STATION"]),
            "MAG_LOC" => trim((string)$m["MAG_LOC"])
        );
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Machine</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .page {
            padding: 10px;
        }

        .window {
            width: 920px;
            min-height: 560px;
            background: #d4d0c8;
            border: 1px solid #555555;
            box-sizing: border-box;
        }

        .titlebar {
            height: 26px;
            line-height: 26px;
            background: #efefef;
            border-bottom: 1px solid #999999;
            padding-left: 8px;
            font-weight: bold;
            font-size: 12px;
        }

        .tabs {
            height: 34px;
            background: #d4d0c8;
            padding: 6px 6px 0 6px;
            box-sizing: border-box;
        }

        .tab {
            display: inline-block;
            padding: 5px 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            text-decoration: none;
            color: #000000;
            margin-right: 2px;
        }

        .tab.active {
            background: #ffffff;
            border-bottom: 1px solid #ffffff;
            font-weight: bold;
        }

        .content {
            padding: 8px;
            box-sizing: border-box;
        }

        .grid-panel {
            border: 2px solid red;
            padding: 12px;
            background: #d4d0c8;
            width: 860px;
            height: 390px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .grid-wrap {
            width: 810px;
            height: 330px;
            overflow: auto;
            border: 1px solid #777777;
            background: #ffffff;
        }

        table.grid {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
            font-size: 12px;
        }

        table.grid th {
            background: #e0e0e0;
            border: 1px solid #777777;
            height: 22px;
            padding: 2px 4px;
            text-align: left;
            white-space: nowrap;
        }

        table.grid td {
            background: #ffffff;
            border: 1px solid #999999;
            height: 22px;
            padding: 0;
            white-space: nowrap;
            overflow: hidden;
        }

        table.grid tr.selected td {
            background: #316ac5;
            color: #ffffff;
        }

        table.grid input,
        table.grid select {
            width: 100%;
            height: 22px;
            border: none;
            padding: 1px 3px;
            box-sizing: border-box;
            background: transparent;
            color: inherit;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        table.grid select {
            background: #ffffff;
            color: #000000;
        }

        table.grid tr.selected select {
            background: #ffffff;
            color: #000000;
        }

        .check-cell {
            text-align: center;
        }

        .check-cell input {
            width: auto !important;
            height: auto !important;
        }

        .toolbar {
            margin-top: 8px;
        }

        button {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            cursor: pointer;
            min-width: 30px;
            height: 24px;
            margin-right: 3px;
        }

        .right-button {
            float: right;
            margin-top: 14px;
            margin-right: 8px;
            width: 130px;
        }

        .status {
            margin-top: 8px;
            color: #000080;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="window">
        <div class="titlebar">Master</div>

        <div class="tabs">
            <a class="tab" href="master_item_prod.php">B.O.M</a>
            <a class="tab active" href="master_machine.php">MACHINE</a>
            <a class="tab" href="master_process.php">PROSES</a>
        </div>

        <div class="content">
            <div class="grid-panel">
                <div class="grid-wrap">
                    <table class="grid" id="tblMachine">
                        <thead>
                            <tr>
                                <th style="width:105px;">MAC_CODE</th>
                                <th style="width:135px;">MAC_SERIAL</th>
                                <th style="width:270px;">MAC_TYPE</th>
                                <th style="width:70px;">ACTIVE</th>
                                <th style="width:130px;">STATION</th>
                                <th style="width:80px;">LOC</th>
                            </tr>
                        </thead>
                        <tbody id="machineBody"></tbody>
                    </table>
                </div>

                <div class="toolbar">
                    <button type="button" onclick="newRow()">+</button>
                    <button type="button" onclick="saveSelectedRow()">✓</button>
                    <button type="button" onclick="deleteSelectedRow()">-</button>
                    <button type="button" onclick="loadMachines()">Refresh</button>
                    <button type="button" onclick="window.location.href='dashboard_home.php'">Close</button>
                </div>
            </div>

            <button type="button" class="right-button" onclick="saveSelectedRow()">SIMPAN</button>
            <div class="status" id="statusText">Ready.</div>
        </div>
    </div>
</div>

<script>
var magList = <?php echo json_encode($magList); ?>;
var selectedRow = null;

function byId(id) { return document.getElementById(id); }
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function setStatus(msg) { byId("statusText").innerHTML = msg; }
function intval(v) { var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }

function ajaxGet(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            if (xhr.status != 200) {
                alert("HTTP Error " + xhr.status + "\n" + xhr.responseText);
                return;
            }
            var res;
            try { res = JSON.parse(xhr.responseText); }
            catch(e) { alert("Response bukan JSON:\n" + xhr.responseText); return; }
            callback(res);
        }
    };
    xhr.send(null);
}

function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            if (xhr.status != 200) {
                alert("HTTP Error " + xhr.status + "\n" + xhr.responseText);
                return;
            }
            var res;
            try { res = JSON.parse(xhr.responseText); }
            catch(e) { alert("Response bukan JSON:\n" + xhr.responseText); return; }
            callback(res);
        }
    };
    xhr.send(data);
}

function html(v) {
    return String(v == null ? "" : v)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function magOptions(selectedMagId) {
    var s = "<select name='MAG_ID' onchange='syncLoc(this)'>";
    s += "<option value=''>-- PILIH --</option>";

    for (var i = 0; i < magList.length; i++) {
        var m = magList[i];
        var selected = String(m.MAG_ID) == String(selectedMagId) ? " selected" : "";
        var text = html(m.MAG_STATION);
        s += "<option value='" + html(m.MAG_ID) + "' data-loc='" + html(m.MAG_LOC) + "'" + selected + ">" + text + "</option>";
    }

    s += "</select>";
    return s;
}

function syncLoc(sel) {
    var tr = sel.parentNode.parentNode;
    var opt = sel.options[sel.selectedIndex];
    var loc = opt ? opt.getAttribute("data-loc") || "" : "";
    tr.querySelector("input[name='MAG_LOC']").value = loc;
}

function makeRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-mac-id", r.MAC_ID || "");

    var activeChecked = intval(r.MAC_ACTIVE) == 1 ? " checked" : "";

    tr.innerHTML =
        "<td><input name='MAC_CODE' value='" + html(r.MAC_CODE || "") + "'></td>" +
        "<td><input name='MAC_SERIAL' value='" + html(r.MAC_SERIAL || "") + "'></td>" +
        "<td><input name='MAC_TYPE' value='" + html(r.MAC_TYPE || "") + "'></td>" +
        "<td class='check-cell'><input type='checkbox' name='MAC_ACTIVE'" + activeChecked + "></td>" +
        "<td>" + magOptions(r.MAG_ID || "") + "</td>" +
        "<td><input name='MAG_LOC' value='" + html(r.MAG_LOC || "") + "' readonly></td>";

    tr.onclick = function () {
        selectRow(tr);
    };

    var inputs = tr.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onfocus = function () {
            selectRow(tr);
        };
        inputs[i].onkeydown = function (e) {
            e = e || window.event;
            var key = e.keyCode || e.which;
            if (key == 13) {
                e.preventDefault();
                saveSelectedRow();
                return false;
            }
            if (key == 40) {
                e.preventDefault();
                moveRow(1, this);
                return false;
            }
            if (key == 38) {
                e.preventDefault();
                moveRow(-1, this);
                return false;
            }
            return true;
        };
    }

    return tr;
}

function selectRow(tr) {
    var rows = byId("machineBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }
    tr.className = "selected";
    selectedRow = tr;
}

function loadMachines() {
    ajaxGet("?action=load", function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        var body = byId("machineBody");
        body.innerHTML = "";

        for (var i = 0; i < res.rows.length; i++) {
            body.appendChild(makeRow(res.rows[i]));
        }

        if (body.rows.length > 0) {
            selectRow(body.rows[0]);
        }

        setStatus("Loaded " + res.rows.length + " machine.");
    });
}

function newRow() {
    var body = byId("machineBody");
    var tr = makeRow({
        MAC_ID: "",
        MAC_CODE: "",
        MAC_SERIAL: "",
        MAC_TYPE: "",
        MAC_ACTIVE: 0,
        MAG_ID: "",
        MAG_LOC: ""
    });

    body.insertBefore(tr, body.firstChild);
    selectRow(tr);

    var input = tr.querySelector("input[name='MAC_CODE']");
    if (input) {
        input.focus();
        input.select();
    }
}

function rowData(tr) {
    return {
        MAC_ID: tr.getAttribute("data-mac-id") || "",
        MAC_CODE: tr.querySelector("input[name='MAC_CODE']").value,
        MAC_SERIAL: tr.querySelector("input[name='MAC_SERIAL']").value,
        MAC_TYPE: tr.querySelector("input[name='MAC_TYPE']").value,
        MAC_ACTIVE: tr.querySelector("input[name='MAC_ACTIVE']").checked ? 1 : 0,
        MAG_ID: tr.querySelector("select[name='MAG_ID']").value,
        MAC_NO: ""
    };
}

function saveSelectedRow() {
    if (!selectedRow) {
        alert("Pilih baris machine dulu.");
        return;
    }

    var r = rowData(selectedRow);

    if (r.MAC_CODE == "") {
        alert("MAC_CODE belum diisi.");
        selectedRow.querySelector("input[name='MAC_CODE']").focus();
        return;
    }

    if (r.MAG_ID == "") {
        alert("STATION belum dipilih.");
        selectedRow.querySelector("select[name='MAG_ID']").focus();
        return;
    }

    var data =
        "MAC_ID=" + enc(r.MAC_ID) +
        "&MAC_CODE=" + enc(r.MAC_CODE) +
        "&MAC_SERIAL=" + enc(r.MAC_SERIAL) +
        "&MAC_TYPE=" + enc(r.MAC_TYPE) +
        "&MAG_ID=" + enc(r.MAG_ID) +
        "&MAC_ACTIVE=" + enc(r.MAC_ACTIVE) +
        "&MAC_NO=" + enc(r.MAC_NO);

    ajaxPost("?action=save", data, function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        if (res.MAC_ID) {
            selectedRow.setAttribute("data-mac-id", res.MAC_ID);
        }

        setStatus(res.message);
        alert(res.message);
        loadMachines();
    });
}

function deleteSelectedRow() {
    if (!selectedRow) {
        alert("Pilih baris machine dulu.");
        return;
    }

    var macId = selectedRow.getAttribute("data-mac-id") || "";

    if (macId == "") {
        selectedRow.parentNode.removeChild(selectedRow);
        selectedRow = null;
        return;
    }

    if (!confirm("Hapus machine ini?")) {
        return;
    }

    ajaxPost("?action=delete", "MAC_ID=" + enc(macId), function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }
        setStatus(res.message);
        alert(res.message);
        loadMachines();
    });
}

function moveRow(direction, input) {
    var tr = input.parentNode.parentNode;
    var rows = byId("machineBody").getElementsByTagName("tr");
    var rowIndex = -1;
    var cellIndex = input.parentNode.cellIndex;

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] == tr) {
            rowIndex = i;
            break;
        }
    }

    if (rowIndex < 0) {
        return;
    }

    var nextIndex = rowIndex + direction;

    if (nextIndex < 0) {
        nextIndex = 0;
    }

    if (nextIndex >= rows.length) {
        newRow();
        return;
    }

    selectRow(rows[nextIndex]);

    var nextCell = rows[nextIndex].cells[cellIndex];
    if (nextCell) {
        var nextInput = nextCell.getElementsByTagName("input")[0];
        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    }
}

loadMachines();
</script>
</body>
</html>
