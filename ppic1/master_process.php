<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/database_ppic.php";

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

function to_float($value) {
    if ($value === null || $value === "") {
        return 0;
    }
    return floatval(str_replace(",", "", (string)$value));
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
   AJAX: LOAD PROCESS
========================================================== */
if ($action == "load_process") {
    $sql = "
        SELECT
            PROC_ID,
            ISNULL(PROC_NAME, '') AS PROC_NAME,
            ISNULL(PROC_EFFICIENTCY, 0) AS PROC_EFFICIENTCY,
            ISNULL(PROC_MEASURE, 0) AS PROC_MEASURE,
            ISNULL(PROC_HOURS, 0) AS PROC_HOURS,
            ISNULL(PROC_MMDAY, 0) AS PROC_MMDAY,
            ISNULL(PROC_MMDAY2, 0) AS PROC_MMDAY2,
            ISNULL(PROC_MMDAY3, 0) AS PROC_MMDAY3
        FROM dbo.PROCESS
        ORDER BY PROC_NAME
    ";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "PROC_ID" => to_int($r["PROC_ID"]),
            "PROC_NAME" => trim((string)$r["PROC_NAME"]),
            "PROC_EFFICIENTCY" => (float)$r["PROC_EFFICIENTCY"],
            "PROC_MEASURE" => (float)$r["PROC_MEASURE"],
            "PROC_HOURS" => (float)$r["PROC_HOURS"],
            "PROC_MMDAY" => (float)$r["PROC_MMDAY"],
            "PROC_MMDAY2" => (float)$r["PROC_MMDAY2"],
            "PROC_MMDAY3" => (float)$r["PROC_MMDAY3"]
        );
    }

    json_out(array("success" => true, "rows" => $rows));
}

/* ==========================================================
   AJAX: LOAD STATION BY PROCESS
========================================================== */
if ($action == "load_station") {
    $proc_id = to_int(get_value("PROC_ID", "0"));

    if ($proc_id <= 0) {
        json_out(array("success" => true, "rows" => array()));
    }

    $sql = "
        SELECT
            MAG_ID,
            ISNULL(MAG_STATION, '') AS MAG_STATION,
            ISNULL(MAG_LOC, '') AS MAG_LOC,
            ISNULL(PROC_ID, 0) AS PROC_ID
        FROM dbo.MAG
        WHERE PROC_ID = ?
        ORDER BY MAG_STATION, MAG_LOC
    ";

    $stmt = sqlsrv_query($conn, $sql, array($proc_id));

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "MAG_ID" => to_int($r["MAG_ID"]),
            "MAG_STATION" => trim((string)$r["MAG_STATION"]),
            "MAG_LOC" => trim((string)$r["MAG_LOC"]),
            "PROC_ID" => to_int($r["PROC_ID"])
        );
    }

    json_out(array("success" => true, "rows" => $rows));
}

/* ==========================================================
   AJAX: SAVE PROCESS
   - PROC_ID kosong = INSERT
   - PROC_ID ada    = UPDATE
========================================================== */
if ($action == "save_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $proc_name = post_value("PROC_NAME", "");
    $proc_eff = to_float(post_value("PROC_EFFICIENTCY", "0"));
    $proc_measure = to_float(post_value("PROC_MEASURE", "0"));
    $proc_hours = to_float(post_value("PROC_HOURS", "0"));
    $proc_mmday = to_float(post_value("PROC_MMDAY", "0"));
    $proc_mmday2 = to_float(post_value("PROC_MMDAY2", "0"));
    $proc_mmday3 = to_float(post_value("PROC_MMDAY3", "0"));

    if ($proc_name == "") {
        json_out(array("success" => false, "message" => "PROSES belum diisi."));
    }

    if ($proc_id > 0) {
        $sql = "
            UPDATE dbo.PROCESS
            SET
                PROC_NAME = ?,
                PROC_EFFICIENTCY = ?,
                PROC_MEASURE = ?,
                PROC_HOURS = ?,
                PROC_MMDAY = ?,
                PROC_MMDAY2 = ?,
                PROC_MMDAY3 = ?
            WHERE PROC_ID = ?
        ";

        $params = array(
            $proc_name,
            $proc_eff,
            $proc_measure,
            $proc_hours,
            $proc_mmday,
            $proc_mmday2,
            $proc_mmday3,
            $proc_id
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }

        json_out(array("success" => true, "message" => "Process berhasil diupdate.", "PROC_ID" => $proc_id));
    }

    $sql = "
        INSERT INTO dbo.PROCESS
            (PROC_NAME, PROC_EFFICIENTCY, PROC_MEASURE, PROC_HOURS, PROC_MMDAY, PROC_MMDAY2, PROC_MMDAY3)
        OUTPUT INSERTED.PROC_ID
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
    ";

    $params = array(
        $proc_name,
        $proc_eff,
        $proc_measure,
        $proc_hours,
        $proc_mmday,
        $proc_mmday2,
        $proc_mmday3
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$r || !isset($r["PROC_ID"])) {
        json_out(array("success" => false, "message" => "Insert berhasil tapi PROC_ID tidak terbaca."));
    }

    json_out(array("success" => true, "message" => "Process berhasil ditambah.", "PROC_ID" => to_int($r["PROC_ID"])));
}

/* ==========================================================
   AJAX: DELETE PROCESS
========================================================== */
if ($action == "delete_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));

    if ($proc_id <= 0) {
        json_out(array("success" => false, "message" => "PROC_ID kosong."));
    }

    $cekMag = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAG WHERE PROC_ID = ?", array($proc_id));
    if ($cekMag !== false) {
        $cm = sqlsrv_fetch_array($cekMag, SQLSRV_FETCH_ASSOC);
        if ($cm && to_int($cm["CNT"]) > 0) {
            json_out(array("success" => false, "message" => "Tidak bisa hapus. Process masih punya station di MAG."));
        }
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.PROCESS WHERE PROC_ID = ?", array($proc_id));

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "Process berhasil dihapus."));
}

/* ==========================================================
   AJAX: SAVE STATION / MAG
========================================================== */
if ($action == "save_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $mag_station = post_value("MAG_STATION", "");
    $mag_loc = post_value("MAG_LOC", "");

    if ($proc_id <= 0) {
        json_out(array("success" => false, "message" => "PROC_ID kosong. Pilih / simpan process dulu."));
    }

    if ($mag_station == "") {
        json_out(array("success" => false, "message" => "STATION belum diisi."));
    }

    if ($mag_id > 0) {
        $sql = "
            UPDATE dbo.MAG
            SET
                MAG_STATION = ?,
                MAG_LOC = ?,
                PROC_ID = ?
            WHERE MAG_ID = ?
        ";

        $params = array(
            $mag_station,
            $mag_loc,
            $proc_id,
            $mag_id
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }

        json_out(array("success" => true, "message" => "Station berhasil diupdate.", "MAG_ID" => $mag_id));
    }

    $sql = "
        INSERT INTO dbo.MAG
            (MAG_STATION, MAG_LOC, PROC_ID)
        OUTPUT INSERTED.MAG_ID
        VALUES
            (?, ?, ?)
    ";

    $params = array(
        $mag_station,
        $mag_loc,
        $proc_id
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$r || !isset($r["MAG_ID"])) {
        json_out(array("success" => false, "message" => "Insert station berhasil tapi MAG_ID tidak terbaca."));
    }

    json_out(array("success" => true, "message" => "Station berhasil ditambah.", "MAG_ID" => to_int($r["MAG_ID"])));
}

/* ==========================================================
   AJAX: DELETE STATION / MAG
========================================================== */
if ($action == "delete_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));

    if ($mag_id <= 0) {
        json_out(array("success" => false, "message" => "MAG_ID kosong."));
    }

    $cekMac = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAC WHERE MAG_ID = ?", array($mag_id));
    if ($cekMac !== false) {
        $cm = sqlsrv_fetch_array($cekMac, SQLSRV_FETCH_ASSOC);
        if ($cm && to_int($cm["CNT"]) > 0) {
            json_out(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di MAC."));
        }
    }

    $cekItemProd = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.ITEM_PROD WHERE MAG_ID = ?", array($mag_id));
    if ($cekItemProd !== false) {
        $ci = sqlsrv_fetch_array($cekItemProd, SQLSRV_FETCH_ASSOC);
        if ($ci && to_int($ci["CNT"]) > 0) {
            json_out(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di ITEM_PROD."));
        }
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MAG WHERE MAG_ID = ?", array($mag_id));

    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "Station berhasil dihapus."));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Process</title>
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
            min-height: 600px;
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

        .caption {
            margin-bottom: 6px;
            font-size: 14px;
        }

        .grid-wrap {
            overflow: auto;
            border: 1px solid #777777;
            background: #ffffff;
        }

        .process-wrap {
            width: 840px; /* Increased to fit new columns */
            height: 230px;
        }

        .station-wrap {
            width: 180px;
            height: 160px;
            margin-top: 14px;
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

        table.grid input {
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
            <a class="tab" href="master_machine.php">MACHINE</a>
            <a class="tab active" href="master_process.php">PROSES</a>
        </div>

        <div class="content">
            <div class="caption">PROSES</div>

            <div class="grid-wrap process-wrap">
                <table class="grid" id="tblProcess">
                    <thead>
                        <tr>
                            <th style="width:150px;">PROSES</th>
                            <th style="width:70px;">EFF</th>
                            <th style="width:90px;">MEASURE</th>
                            <th style="width:90px;">HOURS</th>
                            <th style="width:100px;">MMDAY</th>
                            <th style="width:100px;">MMDAY2</th>
                            <th style="width:100px;">MMDAY3</th>
                        </tr>
                    </thead>
                    <tbody id="processBody"></tbody>
                </table>
            </div>

            <div class="toolbar">
                <button type="button" onclick="newProcessRow()">+</button>
                <button type="button" onclick="saveSelectedProcess()">✓</button>
                <button type="button" onclick="deleteSelectedProcess()">-</button>
                <button type="button" onclick="loadProcess()">Refresh</button>
                <button type="button" onclick="window.location.href='dashboard_home.php'">Close</button>
            </div>

            <div class="grid-wrap station-wrap">
                <table class="grid" id="tblStation">
                    <thead>
                        <tr>
                            <th style="width:100px;">STATION</th>
                            <th style="width:55px;">LOC</th>
                        </tr>
                    </thead>
                    <tbody id="stationBody"></tbody>
                </table>
            </div>

            <div class="toolbar">
                <button type="button" onclick="newStationRow()">+</button>
                <button type="button" onclick="saveSelectedStation()">✓</button>
                <button type="button" onclick="deleteSelectedStation()">-</button>
            </div>

            <div class="status" id="statusText">Ready.</div>
        </div>
    </div>
</div>

<script>
var selectedProcessRow = null;
var selectedStationRow = null;

function byId(id) { return document.getElementById(id); }
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function setStatus(msg) { byId("statusText").innerHTML = msg; }
function intval(v) { var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }
function num(v) { var n = parseFloat(String(v).replace(/,/g, "")); return isNaN(n) ? 0 : n; }

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

function makeProcessRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-proc-id", r.PROC_ID || "");

    tr.innerHTML =
        "<td><input name='PROC_NAME' value='" + html(r.PROC_NAME || "") + "'></td>" +
        "<td><input name='PROC_EFFICIENTCY' value='" + html(r.PROC_EFFICIENTCY || 0) + "'></td>" +
        "<td><input name='PROC_MEASURE' value='" + html(r.PROC_MEASURE || 0) + "'></td>" +
        "<td><input name='PROC_HOURS' value='" + html(r.PROC_HOURS || 0) + "'></td>" +
        "<td><input name='PROC_MMDAY' value='" + html(r.PROC_MMDAY || 0) + "'></td>" +
        "<td><input name='PROC_MMDAY2' value='" + html(r.PROC_MMDAY2 || 0) + "'></td>" +
        "<td><input name='PROC_MMDAY3' value='" + html(r.PROC_MMDAY3 || 0) + "'></td>";

    tr.onclick = function () {
        selectProcessRow(tr);
    };

    bindRowKeys(tr, "process");

    return tr;
}

function makeStationRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-mag-id", r.MAG_ID || "");
    tr.setAttribute("data-proc-id", r.PROC_ID || "");

    tr.innerHTML =
        "<td><input name='MAG_STATION' value='" + html(r.MAG_STATION || "") + "'></td>" +
        "<td><input name='MAG_LOC' value='" + html(r.MAG_LOC || "") + "'></td>";

    tr.onclick = function () {
        selectStationRow(tr);
    };

    bindRowKeys(tr, "station");

    return tr;
}

function bindRowKeys(tr, type) {
    var inputs = tr.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onfocus = function () {
            if (type == "process") {
                selectProcessRow(tr);
            } else {
                selectStationRow(tr);
            }
        };

        inputs[i].onkeydown = function (e) {
            e = e || window.event;
            var key = e.keyCode || e.which;

            if (key == 13) {
                e.preventDefault();
                if (type == "process") {
                    saveSelectedProcess();
                } else {
                    saveSelectedStation();
                }
                return false;
            }

            if (key == 40) {
                e.preventDefault();
                moveRow(type, 1, this);
                return false;
            }

            if (key == 38) {
                e.preventDefault();
                moveRow(type, -1, this);
                return false;
            }

            return true;
        };
    }
}

function selectProcessRow(tr) {
    var rows = byId("processBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    tr.className = "selected";
    selectedProcessRow = tr;

    var procId = tr.getAttribute("data-proc-id") || "";
    loadStation(procId);
}

function selectStationRow(tr) {
    var rows = byId("stationBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    tr.className = "selected";
    selectedStationRow = tr;
}

function loadProcess() {
    ajaxGet("?action=load_process", function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        var body = byId("processBody");
        body.innerHTML = "";

        for (var i = 0; i < res.rows.length; i++) {
            body.appendChild(makeProcessRow(res.rows[i]));
        }

        if (body.rows.length > 0) {
            selectProcessRow(body.rows[0]);
        } else {
            byId("stationBody").innerHTML = "";
        }

        setStatus("Loaded " + res.rows.length + " process.");
    });
}

function loadStation(procId) {
    selectedStationRow = null;

    if (!procId) {
        byId("stationBody").innerHTML = "";
        return;
    }

    ajaxGet("?action=load_station&PROC_ID=" + enc(procId), function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        var body = byId("stationBody");
        body.innerHTML = "";

        for (var i = 0; i < res.rows.length; i++) {
            body.appendChild(makeStationRow(res.rows[i]));
        }

        if (body.rows.length > 0) {
            selectStationRow(body.rows[0]);
        }
    });
}

function newProcessRow() {
    var body = byId("processBody");
    var tr = makeProcessRow({
        PROC_ID: "",
        PROC_NAME: "",
        PROC_EFFICIENTCY: 90,
        PROC_MEASURE: 0,
        PROC_HOURS: 1,
        PROC_MMDAY: 25,
        PROC_MMDAY2: 0,
        PROC_MMDAY3: 0
    });

    body.insertBefore(tr, body.firstChild);
    selectProcessRow(tr);

    var input = tr.querySelector("input[name='PROC_NAME']");
    if (input) {
        input.focus();
        input.select();
    }
}

function newStationRow() {
    if (!selectedProcessRow) {
        alert("Pilih process dulu.");
        return;
    }

    var procId = selectedProcessRow.getAttribute("data-proc-id") || "";

    if (procId == "") {
        alert("Process baru harus disimpan dulu sebelum tambah station.");
        return;
    }

    var body = byId("stationBody");
    var tr = makeStationRow({
        MAG_ID: "",
        PROC_ID: procId,
        MAG_STATION: "",
        MAG_LOC: "IM1"
    });

    body.insertBefore(tr, body.firstChild);
    selectStationRow(tr);

    var input = tr.querySelector("input[name='MAG_STATION']");
    if (input) {
        input.focus();
        input.select();
    }
}

function processData(tr) {
    return {
        PROC_ID: tr.getAttribute("data-proc-id") || "",
        PROC_NAME: tr.querySelector("input[name='PROC_NAME']").value,
        PROC_EFFICIENTCY: tr.querySelector("input[name='PROC_EFFICIENTCY']").value,
        PROC_MEASURE: tr.querySelector("input[name='PROC_MEASURE']").value,
        PROC_HOURS: tr.querySelector("input[name='PROC_HOURS']").value,
        PROC_MMDAY: tr.querySelector("input[name='PROC_MMDAY']").value,
        PROC_MMDAY2: tr.querySelector("input[name='PROC_MMDAY2']").value,
        PROC_MMDAY3: tr.querySelector("input[name='PROC_MMDAY3']").value
    };
}

function stationData(tr) {
    var procId = "";
    if (selectedProcessRow) {
        procId = selectedProcessRow.getAttribute("data-proc-id") || "";
    }

    return {
        MAG_ID: tr.getAttribute("data-mag-id") || "",
        PROC_ID: procId,
        MAG_STATION: tr.querySelector("input[name='MAG_STATION']").value,
        MAG_LOC: tr.querySelector("input[name='MAG_LOC']").value
    };
}

function saveSelectedProcess() {
    if (!selectedProcessRow) {
        alert("Pilih process dulu.");
        return;
    }

    var r = processData(selectedProcessRow);

    if (r.PROC_NAME == "") {
        alert("PROSES belum diisi.");
        selectedProcessRow.querySelector("input[name='PROC_NAME']").focus();
        return;
    }

    var data =
        "PROC_ID=" + enc(r.PROC_ID) +
        "&PROC_NAME=" + enc(r.PROC_NAME) +
        "&PROC_EFFICIENTCY=" + enc(r.PROC_EFFICIENTCY) +
        "&PROC_MEASURE=" + enc(r.PROC_MEASURE) +
        "&PROC_HOURS=" + enc(r.PROC_HOURS) +
        "&PROC_MMDAY=" + enc(r.PROC_MMDAY) +
        "&PROC_MMDAY2=" + enc(r.PROC_MMDAY2) +
        "&PROC_MMDAY3=" + enc(r.PROC_MMDAY3);

    ajaxPost("?action=save_process", data, function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        if (res.PROC_ID) {
            selectedProcessRow.setAttribute("data-proc-id", res.PROC_ID);
        }

        setStatus(res.message);
        alert(res.message);
        loadProcess();
    });
}

function saveSelectedStation() {
    if (!selectedProcessRow) {
        alert("Pilih process dulu.");
        return;
    }

    if (!selectedStationRow) {
        alert("Pilih station dulu.");
        return;
    }

    var r = stationData(selectedStationRow);

    if (r.PROC_ID == "") {
        alert("Process harus disimpan dulu.");
        return;
    }

    if (r.MAG_STATION == "") {
        alert("STATION belum diisi.");
        selectedStationRow.querySelector("input[name='MAG_STATION']").focus();
        return;
    }

    var data =
        "MAG_ID=" + enc(r.MAG_ID) +
        "&PROC_ID=" + enc(r.PROC_ID) +
        "&MAG_STATION=" + enc(r.MAG_STATION) +
        "&MAG_LOC=" + enc(r.MAG_LOC);

    ajaxPost("?action=save_station", data, function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        if (res.MAG_ID) {
            selectedStationRow.setAttribute("data-mag-id", res.MAG_ID);
        }

        setStatus(res.message);
        alert(res.message);
        loadStation(r.PROC_ID);
    });
}

function deleteSelectedProcess() {
    if (!selectedProcessRow) {
        alert("Pilih process dulu.");
        return;
    }

    var procId = selectedProcessRow.getAttribute("data-proc-id") || "";

    if (procId == "") {
        selectedProcessRow.parentNode.removeChild(selectedProcessRow);
        selectedProcessRow = null;
        return;
    }

    if (!confirm("Hapus process ini?")) {
        return;
    }

    ajaxPost("?action=delete_process", "PROC_ID=" + enc(procId), function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }
        setStatus(res.message);
        alert(res.message);
        loadProcess();
    });
}

function deleteSelectedStation() {
    if (!selectedStationRow) {
        alert("Pilih station dulu.");
        return;
    }

    var magId = selectedStationRow.getAttribute("data-mag-id") || "";
    var procId = "";

    if (selectedProcessRow) {
        procId = selectedProcessRow.getAttribute("data-proc-id") || "";
    }

    if (magId == "") {
        selectedStationRow.parentNode.removeChild(selectedStationRow);
        selectedStationRow = null;
        return;
    }

    if (!confirm("Hapus station ini?")) {
        return;
    }

    ajaxPost("?action=delete_station", "MAG_ID=" + enc(magId), function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }
        setStatus(res.message);
        alert(res.message);
        loadStation(procId);
    });
}

function moveRow(type, direction, input) {
    var tr = input.parentNode.parentNode;
    var body = type == "process" ? byId("processBody") : byId("stationBody");
    var rows = body.getElementsByTagName("tr");
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
        if (type == "process") {
            newProcessRow();
        } else {
            newStationRow();
        }
        return;
    }

    if (type == "process") {
        selectProcessRow(rows[nextIndex]);
    } else {
        selectStationRow(rows[nextIndex]);
    }

    var nextCell = rows[nextIndex].cells[cellIndex];
    if (nextCell) {
        var nextInput = nextCell.getElementsByTagName("input")[0];
        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    }
}

loadProcess();
</script>
</body>
</html>