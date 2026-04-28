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

        /* Styling Table */
        #example-table { 
            border-radius: 8px; 
            overflow: hidden; 
            border: 1px solid #dee2e6;
        }

        /* Penanda Sel yang diedit */
        .cell-edited {
            background-color: #fff3cd !important;
            box-shadow: inset 0 0 5px rgba(255,193,7,0.5);
        }

        .tabulator-header {
            background-color: #343a40 !important;
            color: white !important;
            font-weight: bold;
        }

        /* Action Buttons */
        .btn-action-group {
            display: flex;
            gap: 10px;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
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
                <small class="text-muted">Grid Mode – Excel Style Editing</small>
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

<script>
var table = new Tabulator("#example-table", {
    height:"550px",
    layout:"fitColumns",
    selectable:1,
    ajaxURL:"api_load.php",
    ajaxConfig:"GET",
    placeholder:"Data tidak ditemukan...",
    
    // Styling baris
    rowHeader:{formatter:"rownum", hozAlign:"center", width:40},

    columns:[
        {title:"ID", field:"id", width:60, editor:false, hozAlign:"center", headerHozAlign:"center"},
        {title:"Part Code", field:"part_code", editor:"input", headerSort:true},
        {title:"Part No", field:"part_no", editor:"input", headerSort:true},
        {title:"Part Name", field:"part_name", editor:"input", headerSort:true},
        {title:"Qty Polibag", field:"qty_polibag", editor:"number", hozAlign:"right", headerHozAlign:"right"},
        {title:"Qty Box", field:"qty_box", editor:"number", hozAlign:"right", headerHozAlign:"right"},
        {
            title:"Hapus",
            field:"delete",
            width:80,
            hozAlign:"center",
            headerSort:false,
            formatter:function(){
                return "<button class='btn btn-outline-danger btn-sm' style='padding: 0px 8px;'>&times;</button>";
            },
            cellClick:function(e, cell){
                let id = cell.getRow().getData().id;
                if(!id){
                    cell.getRow().delete();
                    return;
                }
                if(confirm("Yakin hapus data ID: "+id+" ?")){
                    fetch("api_save.php?action=delete&id="+id)
                    .then(r=>r.json())
                    .then(res=>{
                        if(res.status == "success"){
                            cell.getRow().delete();
                        } else {
                            alert("Gagal hapus!");
                        }
                    });
                }
            }
        }
    ],

    // Memberi warna kuning pada sel yang baru diedit
    cellEdited:function(cell){
        cell.getElement().classList.add("cell-edited");
    }
});

// Event Tambah Baris
document.getElementById("btn-add").addEventListener("click", function(){
    table.addRow({id:"", part_code:"", part_no:"", part_name:"", qty_polibag:0, qty_box:0}, true);
});

// Event Save All
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
        table.replaceData(); // Refresh data agar ID yang baru muncul
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