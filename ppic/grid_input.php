<?php
// SECURITY MIDDLEWARE
require_once "../middleware/Auth.php";      // wajib login
require_once "../middleware/RoleCheck.php"; // cek hak akses

// Siapa yang boleh akses grid barcode? (PPIC)
only(['p2']); // hanya plant1 & plant2

// Koneksi database sesuai user login
require_once "../config/database.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Master Data Showa</title>

    <!-- Tabulator CSS & JS -->
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

    <style>
        body { padding:20px; font-family:Arial; }
        #example-table { height: 600px; }

        .tabulator-row.tabulator-selected {
            background-color: #ffcccc !important;
        }

        .cell-edited {
            background-color: #ffaaaa !important;
        }

        #btn-add, #btn-save {
            padding:5px 12px;
            cursor:pointer;
            border:0;
            border-radius:4px;
        }
        #btn-add { background:#007bff; color:white; }
        #btn-save { background:green; color:white; margin-left:10px; }
    </style>
</head>
<body>

<a href="dashboard_ppic.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu
</a>    

<h3>Master Data Showa (Grid Mode – Excel Style)</h3>

<button id="btn-add">+ Tambah Baris Baru</button>
<button id="btn-save">💾 Save Semua Perubahan</button>

<br><br>

<div id="example-table"></div>

<script>
var table = new Tabulator("#example-table", {
    height:"600px",
    layout:"fitColumns",
    selectable:1,
    movableColumns:true,
    ajaxURL:"api_load.php",
    ajaxConfig:"GET",

    columns:[
        {title:"ID", field:"id", width:60, editor:false},
        {title:"Part Code", field:"part_code", editor:"input"},
        {title:"Part No", field:"part_no", editor:"input"},
        {title:"Part Name", field:"part_name", editor:"input"},
        {title:"Qty Polibag", field:"qty_polibag", editor:"number"},
        {title:"Qty Box", field:"qty_box", editor:"number"},
        {
            title:"Delete",
            field:"delete",
            width:70,
            hozAlign:"center",
            formatter:function(){
                return "<span style='color:red;cursor:pointer;font-size:20px;'>&#10006;</span>";
            },
            cellClick:function(e, cell){
                let id = cell.getRow().getData().id;

                if(!id){
                    alert("Data belum tersimpan, tidak bisa dihapus.");
                    return;
                }

                if(confirm("Yakin hapus data ID: "+id+" ?")){
                    fetch("api_save.php?action=delete&id="+id)
                    .then(r=>r.json())
                    .then(res=>{
                        if(res.status == "success"){
                            cell.getRow().delete();
                        } else {
                            alert("Gagal hapus: " + JSON.stringify(res.msg));
                        }
                    });
                }
            }
        }
    ],

    rowClick:function(e, row){
        table.deselectRow();
        row.select();
    },

    cellEdited:function(cell){
        cell.getElement().classList.add("cell-edited");

        let data = cell.getRow().getData();

        fetch("api_save.php?action=save", {
            method:"POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify(data)
        })
        .then(r=>r.text())
        .then(res=>console.log("Saved:", res));
    }
});

document.getElementById("btn-add").addEventListener("click", function(){
    table.addRow({
        id:"",
        part_code:"",
        part_no:"",
        part_name:"",
        qty_polibag:0,
        qty_box:0
    }, true);
});

document.getElementById("btn-save").addEventListener("click", function(){
    let data = table.getData();

    fetch("api_save.php?action=save_all", {
        method:"POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(data)
    })
    .then(r=>r.text())
    .then(res=>{
        alert("Semua perubahan berhasil disimpan!");
        console.log(res);
        table.replaceData();
    });
});
</script>

</body>
</html>
