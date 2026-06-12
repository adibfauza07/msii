<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Data Showa | Grid Mode</title>

    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
            padding: 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .header-section {
            border-bottom: 2px solid #eeeeee;
            margin-bottom: 25px;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h3 {
            margin: 0;
            font-weight: 700;
            color: #333333;
        }

        #example-table {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }

        .cell-edited {
            background-color: #fff3cd !important;
            box-shadow: inset 0 0 5px rgba(255,193,7,0.5);
        }

        .tabulator .tabulator-header .tabulator-col,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #000000 !important;
            font-weight: bold !important;
        }

        .btn-action-group {
            display: flex;
            gap: 10px;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
        }

        #modalEditData {
            display: none;
            opacity: 0;
            transition: opacity 0.15s linear;
            background-color: rgba(0,0,0,0.6);
        }

        #modalEditData.show {
            display: block;
            opacity: 1;
        }

        .modal-dialog {
            margin-top: 80px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <a href="dashboard_home.php" class="btn btn-secondary btn-sm btn-back shadow-sm">
        &larr; Kembali ke Menu
    </a>

    <div class="main-container">
        <div class="header-section">
            <div class="header-title">
                <h3>Master Data Showa</h3>
                <small class="text-muted">Grid Mode - Pop-up Style Editing</small>
            </div>
            <div class="btn-action-group">
                <button type="button" id="btn-add" class="btn btn-primary shadow-sm">
                    <b>+</b> Tambah Baris
                </button>
                <button type="button" id="btn-save" class="btn btn-success shadow-sm">
                    Simpan Semua
                </button>
            </div>
        </div>

        <div id="example-table"></div>
    </div>
</div>

<div class="modal" id="modalEditData" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Master Data</h5>
                <button type="button" class="btn-close" style="float:right;" onclick="tutupModal()" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEdit" onsubmit="return false;">
                    <input type="hidden" id="edit-id">

                    <div class="mb-3">
                        <label class="form-label">Part Code</label>
                        <input type="text" class="form-control" id="edit-part-code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Part No</label>
                        <input type="text" class="form-control" id="edit-part-no">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Part Name</label>
                        <input type="text" class="form-control" id="edit-part-name">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty Polibag</label>
                            <input type="number" class="form-control" id="edit-qty-polibag">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty Box</label>
                            <input type="number" class="form-control" id="edit-qty-box">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="tutupModal()">Tutup</button>
                <button type="button" class="btn btn-primary" id="btn-update-baris">Update Data</button>
            </div>
        </div>
    </div>
</div>

<script>
var table = null;
var barisYangDiedit = null;
var rowBaruCounter = 0;

function getValue(id) {
    var el = document.getElementById(id);
    if (!el) {
        return "";
    }
    return el.value;
}

function setValue(id, value) {
    var el = document.getElementById(id);
    if (el) {
        el.value = value == null ? "" : value;
    }
}

function toInt(value) {
    if (value === null || value === undefined || value === "") {
        return 0;
    }
    return parseInt(value, 10) || 0;
}

function markRowEdited(row) {
    if (!row) {
        return;
    }

    var cells = row.getCells();
    for (var i = 0; i < cells.length; i++) {
        cells[i].getElement().classList.add("cell-edited");
    }

    var data = row.getData();
    data._edited = 1;
    row.update(data);
}

function bukaModalEdit(row) {
    if (!row) {
        return;
    }

    var rowData = row.getData();

    setValue("edit-id", rowData.id || "");
    setValue("edit-part-code", rowData.part_code || "");
    setValue("edit-part-no", rowData.part_no || "");
    setValue("edit-part-name", rowData.part_name || "");
    setValue("edit-qty-polibag", rowData.qty_polibag || 0);
    setValue("edit-qty-box", rowData.qty_box || 0);

    barisYangDiedit = row;

    var modal = document.getElementById("modalEditData");
    modal.style.display = "block";
    modal.style.opacity = "1";
    modal.classList.add("show");

    setTimeout(function () {
        var input = document.getElementById("edit-part-code");
        if (input) {
            input.focus();
            input.select();
        }
    }, 100);
}

function tutupModal() {
    var modal = document.getElementById("modalEditData");
    modal.style.opacity = "0";
    modal.classList.remove("show");

    setTimeout(function () {
        modal.style.display = "none";
    }, 150);
}

function buildModalData() {
    return {
        id: getValue("edit-id"),
        part_code: getValue("edit-part-code"),
        part_no: getValue("edit-part-no"),
        part_name: getValue("edit-part-name"),
        qty_polibag: toInt(getValue("edit-qty-polibag")),
        qty_box: toInt(getValue("edit-qty-box")),
        _edited: 1
    };
}

function updateBarisDariModal() {
    if (!barisYangDiedit) {
        alert("Tidak ada baris yang dipilih.");
        return;
    }

    var updatedData = buildModalData();

    barisYangDiedit.update(updatedData).then(function () {
        markRowEdited(barisYangDiedit);
        tutupModal();
    }).catch(function (err) {
        alert("Gagal update baris di layar: " + err);
    });
}

function tambahBaris() {
    if (!table) {
        alert("Table belum siap.");
        return;
    }

    rowBaruCounter++;

    var dataBaru = {
        id: "",
        part_code: "",
        part_no: "",
        part_name: "",
        qty_polibag: 0,
        qty_box: 0,
        _new: 1,
        _row_no: rowBaruCounter
    };

    table.addRow(dataBaru, false).then(function (row) {
        row.select();
        bukaModalEdit(row);
    }).catch(function (err) {
        alert("Gagal tambah baris: " + err);
    });
}

function hapusDataBaris(row) {
    if (!row) {
        alert("Pilih baris yang mau dihapus.");
        return;
    }

    var data = row.getData();
    var id = data.id || "";

    if (id == "") {
        if (confirm("Hapus baris baru ini dari layar?")) {
            row.delete();
        }
        return;
    }

    if (!confirm("Yakin hapus data ID: " + id + " ?")) {
        return;
    }

    fetch("api_save.php?action=delete&id=" + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.status == "success") {
                row.delete();
                alert(res.message || "Data berhasil dihapus.");
            } else {
                alert(res.message || "Gagal hapus data.");
            }
        })
        .catch(function (err) {
            alert("Gagal hapus data: " + err);
        });
}

function simpanSemua() {
    if (!table) {
        alert("Table belum siap.");
        return;
    }

    var data = table.getData();
    var saveData = [];

    for (var i = 0; i < data.length; i++) {
        var row = data[i];

        if ((row.part_code || "") == "" && (row.part_no || "") == "" && (row.part_name || "") == "") {
            continue;
        }

        saveData.push({
            id: row.id || "",
            part_code: row.part_code || "",
            part_no: row.part_no || "",
            part_name: row.part_name || "",
            qty_polibag: toInt(row.qty_polibag),
            qty_box: toInt(row.qty_box)
        });
    }

    if (saveData.length == 0) {
        alert("Tidak ada data untuk disimpan.");
        return;
    }

    var btn = document.getElementById("btn-save");
    btn.disabled = true;
    btn.innerHTML = "Menyimpan...";

    fetch("api_save.php?action=save_all", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(saveData)
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.status != "success") {
            alert(res.message || "Gagal simpan data.");
            return;
        }

        alert(res.message || "Berhasil! Semua data master telah diperbarui.");
        table.replaceData();
    })
    .catch(function (err) {
        alert("Terjadi kesalahan jaringan / response bukan JSON: " + err);
    })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = "Simpan Semua";
    });
}

function buatTombolAksi(cell) {
    var container = document.createElement("div");

    var btnEdit = document.createElement("button");
    btnEdit.type = "button";
    btnEdit.className = "btn btn-outline-primary btn-sm";
    btnEdit.style.padding = "0px 6px";
    btnEdit.style.marginRight = "4px";
    btnEdit.innerHTML = "Edit";
    btnEdit.onclick = function (e) {
        if (e && e.stopPropagation) {
            e.stopPropagation();
        }
        bukaModalEdit(cell.getRow());
    };

    var btnDel = document.createElement("button");
    btnDel.type = "button";
    btnDel.className = "btn btn-outline-danger btn-sm";
    btnDel.style.padding = "0px 8px";
    btnDel.innerHTML = "X";
    btnDel.onclick = function (e) {
        if (e && e.stopPropagation) {
            e.stopPropagation();
        }
        hapusDataBaris(cell.getRow());
    };

    container.appendChild(btnEdit);
    container.appendChild(btnDel);

    return container;
}

function initTable() {
    table = new Tabulator("#example-table", {
        height: "550px",
        layout: "fitColumns",
        selectable: 1,
        ajaxURL: "api_load.php",
        ajaxConfig: "GET",
        placeholder: "Data tidak ditemukan...",

        ajaxError: function (xhr, textStatus, errorThrown) {
            alert("Gagal load api_load.php. Cek file api_load.php dan nama tabel. Error: " + errorThrown);
        },

        rowHeader: {formatter: "rownum", hozAlign: "center", width: 40},

        columns: [
            {title: "ID", field: "id", width: 60, editor: false, hozAlign: "center", headerHozAlign: "center"},
            {title: "Part Code", field: "part_code", editor: false, headerSort: true},
            {title: "Part No", field: "part_no", editor: false, headerSort: true},
            {title: "Part Name", field: "part_name", editor: false, headerSort: true},
            {title: "Qty Polibag", field: "qty_polibag", editor: false, hozAlign: "right", headerHozAlign: "right"},
            {title: "Qty Box", field: "qty_box", editor: false, hozAlign: "right", headerHozAlign: "right"},
            {title: "Aksi", width: 130, hozAlign: "center", headerSort: false, formatter: buatTombolAksi}
        ],

        rowDblClick: function (e, row) {
            bukaModalEdit(row);
        },

        rowClick: function (e, row) {
            row.select();
        }
    });
}

window.onload = function () {
    initTable();

    document.getElementById("btn-add").onclick = function () {
        tambahBaris();
    };

    document.getElementById("btn-update-baris").onclick = function () {
        updateBarisDariModal();
    };

    document.getElementById("btn-save").onclick = function () {
        simpanSemua();
    };

    document.getElementById("formEdit").onkeydown = function (e) {
        e = e || window.event;
        var key = e.keyCode || e.which;

        if (key == 13) {
            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }
            updateBarisDariModal();
            return false;
        }

        return true;
    };
};
</script>

</body>
</html>
