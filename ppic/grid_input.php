<?php
// SECURITY MIDDLEWARE
require_once "../middleware/Auth.php";      
require_once "../middleware/RoleCheck.php"; 

only(['p2']); 

require_once "../config/database.php";
?>
<!DOCTYPE html>
<html>
<head>
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
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .header-section {
            border-bottom: 2px solid #eee;
            margin-bottom: 25px;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h3 {
            margin: 0;
            font-weight: 700;
            color: #333;
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
        
        /* Modal Style Manual supaya tidak bentrok dengan Bootstrap lama */
        #modalEditData {
            transition: opacity 0.15s linear;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <a href="dashboard_ppic.php" class="btn btn-secondary btn-sm btn-back shadow-sm">
        ← Kembali ke Menu
    </a>

    <div class="main-container">
        <div class="header-section">
            <div class="header-title">
                <h3>Master Data Showa</h3>
                <small class="text-muted">Grid Mode – Pop-up Style Editing</small>
            </div>
            <div class="btn-action-group">
                <button id="btn-add" class="btn btn-primary shadow-sm">
                    <b>+</b> Tambah Baris
                </button>
                <button id="btn-save" class="btn btn-success shadow-sm">
                    💾 Simpan Semua
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
        <button type="button" class="btn-close" style="float: right;" onclick="tutupModal()" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formEdit">
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
var barisYangDiedit = null; 

// ==========================================
// FUNGSI BUKA/TUTUP MODAL (SUPER AMAN)
// ==========================================
function bukaModalEdit(row) {
    let rowData = row.getData();
    
    // Tampilkan isi data tabel ke dalam pop-up form (User bisa mengubah/menghapusnya)
    document.getElementById('edit-id').value = rowData.id || "";
    document.getElementById('edit-part-code').value = rowData.part_code || "";
    document.getElementById('edit-part-no').value = rowData.part_no || "";
    document.getElementById('edit-part-name').value = rowData.part_name || "";
    document.getElementById('edit-qty-polibag').value = rowData.qty_polibag || "";
    document.getElementById('edit-qty-box').value = rowData.qty_box || "";
    
    // Ingat baris yang sedang diedit
    barisYangDiedit = row;
    
    // Munculkan Pop-up ke layar (dengan background redup)
    let modal = document.getElementById('modalEditData');
    modal.style.display = 'block';
    modal.style.backgroundColor = 'rgba(0,0,0,0.6)'; 
    setTimeout(() => { 
        modal.classList.add('show'); 
        modal.style.opacity = "1"; 
    }, 10);
}

function tutupModal() {
    let modal = document.getElementById('modalEditData');
    modal.style.opacity = "0";
    modal.classList.remove('show');
    setTimeout(() => { 
        modal.style.display = 'none'; 
    }, 150); 
}

function hapusDataBaris(row) {
    let id = row.getData().id;
    if(!id){
        row.delete();
        return;
    }
    if(confirm("Yakin hapus data ID: "+id+" ?")){
        fetch("api_save.php?action=delete&id="+id)
        .then(r=>r.json())
        .then(res=>{
            if(res.status == "success"){
                row.delete();
            } else {
                alert("Gagal hapus!");
            }
        });
    }
}
// ==========================================

var table = new Tabulator("#example-table", {
    height:"550px",
    layout:"fitColumns",
    selectable:1,
    ajaxURL:"api_load.php",
    ajaxConfig:"GET",
    placeholder:"Data tidak ditemukan...",
    
    rowHeader:{formatter:"rownum", hozAlign:"center", width:40},

    columns:[
        {title:"ID", field:"id", width:60, editor:false, hozAlign:"center", headerHozAlign:"center"},
        {title:"Part Code", field:"part_code", editor:false, headerSort:true},
        {title:"Part No", field:"part_no", editor:false, headerSort:true},
        {title:"Part Name", field:"part_name", editor:false, headerSort:true},
        {title:"Qty Polibag", field:"qty_polibag", editor:false, hozAlign:"right", headerHozAlign:"right"},
        {title:"Qty Box", field:"qty_box", editor:false, hozAlign:"right", headerHozAlign:"right"},
        {
            title:"Aksi",
            width:100,
            hozAlign:"center",
            headerSort:false,
            // STRATEGI BARU: Menggunakan DOM Element agar tombol PASTI BISA diklik
            formatter:function(cell){
                var container = document.createElement("div");
                
                // TOMBOL EDIT
                var btnEdit = document.createElement("button");
                btnEdit.type = "button";
                btnEdit.className = "btn btn-outline-primary btn-sm";
                btnEdit.style.padding = "0px 6px";
                btnEdit.style.marginRight = "4px";
                btnEdit.innerHTML = "✏️";
                btnEdit.onclick = function() {
                    bukaModalEdit(cell.getRow());
                };
                
                // TOMBOL HAPUS
                var btnDel = document.createElement("button");
                btnDel.type = "button";
                btnDel.className = "btn btn-outline-danger btn-sm";
                btnDel.style.padding = "0px 8px";
                btnDel.innerHTML = "&times;";
                btnDel.onclick = function() {
                    hapusDataBaris(cell.getRow());
                };
                
                container.appendChild(btnEdit);
                container.appendChild(btnDel);
                
                return container;
            }
        }
    ],

    cellEdited:function(cell){
        cell.getElement().classList.add("cell-edited");
    }
});

// Event Update Data dari Modal kembali ke Tabel
document.getElementById('btn-update-baris').addEventListener('click', function() {
    if(barisYangDiedit) {
        // Ambil data yang sudah dirubah/dihapus oleh user di dalam form
        let updatedData = {
            part_code: document.getElementById('edit-part-code').value,
            part_no: document.getElementById('edit-part-no').value,
            part_name: document.getElementById('edit-part-name').value,
            qty_polibag: document.getElementById('edit-qty-polibag').value,
            qty_box: document.getElementById('edit-qty-box').value
        };
        
        // Update baris pada tabel di layar
        barisYangDiedit.update(updatedData);
        
        // Beri warna background penanda (kuning) di layar
        let cells = barisYangDiedit.getCells();
        cells.forEach(c => {
            c.getElement().classList.add("cell-edited");
        });
    }

    tutupModal();
});

document.getElementById("btn-add").addEventListener("click", function(){
    table.addRow({id:"", part_code:"", part_no:"", part_name:"", qty_polibag:0, qty_box:0}, true);
});

document.getElementById("btn-save").addEventListener("click", function(){
    let data = table.getData();
    let btn = this;
    
    btn.disabled = true;
    btn.innerHTML = "Menyimpan...";

    fetch("api_save.php?action=save_all", {
        method:"POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(data)
    })
    .then(r=>r.text())
    .then(res=>{
        alert("Berhasil! Semua data master telah diperbarui.");
        table.replaceData(); 
        btn.disabled = false;
        btn.innerHTML = "💾 Simpan Semua";
    })
    .catch(err => {
        alert("Terjadi kesalahan jaringan.");
        btn.disabled = false;
        btn.innerHTML = "💾 Simpan Semua";
    });
});
</script>

</body>
</html>