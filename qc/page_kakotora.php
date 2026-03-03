<!-- FILE: msii/qc/page_kakotora.php -->
<!-- UPDATE: Penambahan Tombol Cetak (Print) di Tabel -->

<style>
    .cursor-pointer { cursor: pointer; }
    .table-hover tbody tr:hover { background-color: #f1f8ff; }
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
    
    /* Efek Hover pada Thumbnail Gambar */
    .img-thumbnail-qc {
        transition: transform 0.2s;
        cursor: zoom-in;
    }
    .img-thumbnail-qc:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary m-0" id="pageTitle"><i class="bi bi-list-task"></i> DATA KAKOTORA (CLAIM)</h4>
    <div>
        <button class="btn btn-sm btn-success" onclick="window.location.href='api_export_excel.php'"><i class="bi bi-file-excel"></i> Excel</button>
        <button class="btn btn-sm btn-primary" onclick="addMaster()"><i class="bi bi-plus-lg"></i> Buat Claim Baru</button>
    </div>
</div>

<!-- TABEL GRID DATA -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="row">
            <div class="col-md-6 d-flex align-items-center">
                <span class="badge bg-primary me-2">Data Claim</span>
                <small class="text-muted" id="pageInfo">Loading...</small>
            </div>
            <div class="col-md-6 text-end">
                <div class="input-group input-group-sm justify-content-end">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" style="max-width: 250px;" placeholder="Cari No CAR, Problem..." onkeyup="searchTable()">
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="min-height: 300px;">
            <table class="table table-bordered mb-0" style="font-size: 13px;" id="mainTable">
                <thead class="table-light sticky-top">
                    <tr>
                        <th>No. CAR</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Part Name</th>
                        <th>Problem</th>
                        <th class="text-center">Tipe / Kategori</th>
                        <th class="text-center" width="130px">Aksi</th> <!-- Lebar kolom ditambah -->
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="7" class="text-center p-4">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white pagination-container">
        <button class="btn btn-sm btn-outline-secondary" id="btnPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i> Prev</button>
        <span class="fw-bold small text-muted" id="paginationText">Page 1 of 1</span>
        <button class="btn btn-sm btn-outline-secondary" id="btnNext" onclick="changePage(1)">Next <i class="bi bi-chevron-right"></i></button>
    </div>
</div>

<!-- DETAIL AREA (READ ONLY) -->
<div id="detailSection" class="row g-3" style="display:none;">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-light fw-bold small">DETAIL ANALISIS</div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0" style="font-size:13px;">
                    <tbody id="detailContent"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white fw-bold small text-center">EVIDENCE / FOTO</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center bg-secondary bg-opacity-10 p-2" style="min-height: 250px;">
                <div id="imageContainer" style="width:100%; height:100%; display:flex; justify-content:center; align-items:center; overflow:hidden;">
                    <span class="text-muted">Pilih data untuk melihat gambar</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ZOOM GAMBAR -->
<div class="modal fade" id="modalZoomImage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 p-2 bg-dark rounded-circle" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.8;"></button>
                <img id="imgZoomTarget" src="" class="img-fluid rounded shadow-lg" style="max-height: 90vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<!-- MODAL INPUT / EDIT -->
<div class="modal fade" id="modalForm" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="modalTitle">Form Input Data Claim</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formMaster" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction">
                    <input type="hidden" name="car_id" id="formId">

                    <!-- TABS NAV -->
                    <ul class="nav nav-tabs nav-fill mb-3" id="myTab" role="tablist">
                        <li class="nav-item"><button class="nav-link active py-1" id="tab1-btn" data-bs-toggle="tab" data-bs-target="#tab1" type="button">Header (Master)</button></li>
                        <li class="nav-item"><button class="nav-link py-1" id="tab2-btn" data-bs-toggle="tab" data-bs-target="#tab2" type="button">Analisis & Detail</button></li>
                        <li class="nav-item"><button class="nav-link py-1" id="tab3-btn" data-bs-toggle="tab" data-bs-target="#tab3" type="button">Gambar</button></li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <!-- TAB 1: HEADER -->
                        <div class="tab-pane fade show active" id="tab1">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">No CAR</label>
                                    <input type="text" name="car_no" id="inp_car_no" class="form-control form-control-sm" required placeholder="Contoh: CAR-2023-A01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Tanggal</label>
                                    <input type="date" name="claim_date" id="inp_claim_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Qty</label>
                                    <input type="number" name="qty" id="inp_qty" class="form-control form-control-sm" value="1">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Customer</label>
                                    <select name="cust_id" id="inp_cust_id" class="form-select form-select-sm" onchange="loadItems(this.value)"><option value="">-- Pilih Customer --</option></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Item / Part</label>
                                    <select name="item_id" id="inp_item_id" class="form-select form-select-sm" disabled><option value="">-- Pilih Customer Dulu --</option></select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Problem</label>
                                <textarea name="problem" id="inp_problem" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-primary">Tipe Data (Master Status)</label>
                                <select name="event_status" id="inp_event_status" class="form-select form-select-sm fw-bold">
                                    <option value="New Project">New Project</option>
                                    <option value="Claim Problem">Claim Problem</option>
                                </select>
                            </div>
                        </div>

                        <!-- TAB 2: DETAIL -->
                        <div class="tab-pane fade" id="tab2">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-success">Klasifikasi</label>
                                    <select name="klasifikasi" id="inp_klasifikasi" class="form-select form-select-sm">
                                        <option value="">-- Pilih --</option>
                                        <option value="Man">Man</option>
                                        <option value="Machine">Machine</option>
                                        <option value="Material">Material</option>
                                        <option value="Method">Method</option>
                                        <option value="Environment">Environment</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-success">Loc Problem</label>
                                    <select name="loc_problem" id="inp_loc" class="form-select form-select-sm">
                                        <option value="">-- Pilih --</option>
                                        <option value="IQC">IQC</option>
                                        <option value="Production Line">Production Line</option>
                                        <option value="Marketing Line">Marketing Line</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Efek (Multi)</label>
                                    <input type="text" name="efek" id="inp_efek" class="form-control form-control-sm" placeholder="Contoh: Line Stop...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-danger">Progress Status (Detail)</label>
                                    <select name="detail_status" id="inp_detail_status" class="form-select form-select-sm">
                                        <option value="OPEN">OPEN</option>
                                        <option value="ON PROGRESS">ON PROGRESS</option>
                                        <option value="WAITING PART">WAITING PART</option>
                                        <option value="CLOSED">CLOSED</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">PIC</label>
                                    <select name="pic" id="inp_pic" class="form-select form-select-sm">
                                        <option value="">- Pilih -</option>
                                        <option value="QC">QC</option>
                                        <option value="Moldshop">Moldshop</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Production">Production</option>
                                        <option value="PE">PE</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Tgl Efektif</label>
                                    <input type="date" name="eff_date" id="inp_eff_date" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Root Cause</label>
                                <textarea name="cause" id="inp_cause" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Countermeasure</label>
                                <textarea name="counter" id="inp_counter" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- TAB 3: GAMBAR -->
                        <div class="tab-pane fade text-center p-3" id="tab3">
                            <div class="border border-dashed p-4 bg-light rounded">
                                <input type="file" name="gambar" id="inp_gambar" class="form-control mb-3" accept="image/*">
                                <div class="text-muted small">Maks 2MB, JPG/PNG</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary px-4" onclick="saveData()">Simpan Data</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1, totalPages = 1, searchTimer = null;
$(document).ready(function(){ loadTableData(1); });

function searchTable() { clearTimeout(searchTimer); searchTimer = setTimeout(function() { currentPage = 1; loadTableData(1); }, 500); }
function changePage(d) { let n = currentPage + d; if (n > 0 && n <= totalPages) { currentPage = n; loadTableData(n); } }

function loadTableData(page) {
    let sv = $('#searchInput').val();
    $('#tableBody').html('<tr><td colspan="7" class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary"></div> Memuat data...</td></tr>');
    $.ajax({
        url: 'api_kakotora_master.php', type: 'GET', data: { action: 'get_table_data', page: page, search: sv }, dataType: 'json',
        success: function(res) {
            if(res.status == 'ok') {
                let rows = res.data, html = '';
                if(rows.length === 0) html = '<tr><td colspan="7" class="text-center text-muted p-4">Data tidak ditemukan</td></tr>';
                else {
                    rows.forEach(r => {
                        let st = r.event_status, badge = 'bg-secondary';
                        if (st == 'Claim Problem') badge = 'bg-danger'; else if (st == 'New Project') badge = 'bg-primary';
                        let cust = r.CUST_COMP || '-', part = r.PART_NAME || '-';
                        html += `
                        <tr class='cursor-pointer' onclick='loadDetail(${r.car_id})'>
                            <td class='fw-bold text-primary'>${r.car_no}</td>
                            <td>${r.tgl_formatted}</td><td>${cust}</td><td>${part}</td><td>${r.problem}</td>
                            <td class='text-center'><span class='badge ${badge}'>${st}</span></td>
                            <td class='text-center'>
                                <!-- TOMBOL CETAK -->
                                <button class='btn btn-sm btn-outline-secondary py-0 me-1' onclick='event.stopPropagation(); printSingle(${r.car_id})' title="Cetak PDF"><i class='bi bi-printer'></i></button>
                                <button class='btn btn-sm btn-outline-primary py-0 me-1' onclick='event.stopPropagation(); editMaster(${r.car_id})' title="Edit"><i class='bi bi-pencil'></i></button>
                                <button class='btn btn-sm btn-outline-danger py-0' onclick='event.stopPropagation(); deleteMaster(${r.car_id})' title="Hapus"><i class='bi bi-trash'></i></button>
                            </td>
                        </tr>`;
                    });
                }
                $('#tableBody').html(html);
                totalPages = res.pagination.total_pages;
                $('#paginationText').text(`Page ${page} of ${totalPages} (Total: ${res.pagination.total_records})`);
                $('#pageInfo').text(`Total ${res.pagination.total_records} Data`);
                $('#btnPrev').prop('disabled', page <= 1); $('#btnNext').prop('disabled', page >= totalPages);
            } else $('#tableBody').html('<tr><td colspan="7" class="text-center text-danger">'+res.msg+'</td></tr>');
        },
        error: function(xhr, s, e) { $('#tableBody').html('<tr><td colspan="7" class="text-center text-danger">Error: '+e+'</td></tr>'); }
    });
}

// --- FUNGSI CETAK ---
function printSingle(id) {
    // Membuka halaman print di tab baru
    window.open('print_kakotora.php?id=' + id, '_blank');
}

function loadCustomers(selId) { $.ajax({url:'api_kakotora_master.php', type:'GET', data:{action:'get_customers'}, dataType:'json', success:function(res){ var h='<option value="">-- Pilih --</option>'; if(res.status=='ok') res.data.forEach(function(i){ var s=(selId==i.id)?'selected':''; h+=`<option value="${i.id}" ${s}>${i.text}</option>`; }); $('#inp_cust_id').html(h); }}); }
function loadItems(cId, selId) { if(!cId) { $('#inp_item_id').html('<option value="">-- Pilih Cust --</option>').prop('disabled',true); return; } $('#inp_item_id').html('<option>Loading...</option>').prop('disabled',true); $.ajax({url:'api_kakotora_master.php', type:'GET', data:{action:'get_items_by_cust', cust_id:cId}, dataType:'json', success:function(res){ var h='<option value="">-- Pilih --</option>'; if(res.status=='ok'&&res.data.length>0){ res.data.forEach(function(i){ var s=(selId==i.id)?'selected':''; h+=`<option value="${i.id}" ${s}>${i.text}</option>`; }); $('#inp_item_id').html(h).prop('disabled',false); } else $('#inp_item_id').html('<option>Tidak ada item</option>'); }}); }

function addMaster() { $('#formMaster')[0].reset(); $('#formAction').val('insert_master'); $('#formId').val(''); $('#modalTitle').text('Buat Claim Baru'); $('#inp_car_no').prop('readonly',false); loadCustomers(); $('#inp_item_id').html('<option>-- Pilih Cust --</option>').prop('disabled',true); $('#inp_event_status').val('New Project'); $('#inp_detail_status').val('OPEN'); (new bootstrap.Tab(document.querySelector('#tab1-btn'))).show(); (new bootstrap.Modal(document.getElementById('modalForm'))).show(); }

function editMaster(id) {
    (new bootstrap.Tab(document.querySelector('#tab1-btn'))).show();
    $.ajax({ url:'api_kakotora_master.php', type:'GET', data:{action:'get_complete_data', id:id}, dataType:'json', success:function(res){
        if(res.status=='ok'){
            var d=res.data; $('#formId').val(d.car_id); $('#formAction').val('update_master'); $('#modalTitle').text('Edit: '+d.car_no);
            $('#inp_car_no').val(d.car_no).prop('readonly',true); 
            $('#inp_claim_date').val(new Date(d.claim_date.date).toISOString().split('T')[0]);
            $('#inp_qty').val(d.qty); $('#inp_problem').val(d.problem); $('#inp_event_status').val(d.event_status); 
            loadCustomers(d.cust_id); loadItems(d.cust_id, d.item_id);
            $('#inp_loc').val(d.loc_problem); $('#inp_efek').val(d.efek); $('#inp_klasifikasi').val(d.klasifikasi); $('#inp_pic').val(d.pic);
            $('#inp_cause').val(d.cause); $('#inp_counter').val(d.counter); $('#inp_detail_status').val(d.status); 
            if(d.eff_date) $('#inp_eff_date').val(new Date(d.eff_date.date).toISOString().split('T')[0]);
            (new bootstrap.Modal(document.getElementById('modalForm'))).show();
        }
    }});
}

function saveData() { var f=document.getElementById('inp_gambar'); if(f.files.length>0 && f.files[0].size>2*1024*1024){ alert("Gambar maks 2MB"); return; } $.ajax({ url:'api_kakotora_master.php', type:'POST', data:new FormData($('#formMaster')[0]), contentType:false, processData:false, dataType:'json', success:function(res){ if(res.status=='ok'){ alert('Disimpan!'); loadTableData(currentPage); $('#modalForm').modal('hide'); } else alert(res.msg); } }); }
function deleteMaster(id) { if(confirm('Hapus data?')){ $.ajax({url:'api_kakotora_master.php', type:'POST', data:{action:'delete_master', id:id}, dataType:'json', success:function(res){ if(res.status=='ok') loadTableData(currentPage); else alert(res.msg); }}); } }

function loadDetail(id) {
    $('#detailSection').fadeIn(); $('#detailContent').html('<tr><td colspan="2" class="text-center">Loading...</td></tr>');
    $.ajax({ url:'api_kakotora_detail.php', type:'GET', data:{id:id}, dataType:'json', success:function(res){
        if(res.status=='ok'){
            var d=res.data;
            $('#detailContent').html(`
                <tr><th width="30%">Tipe (Master)</th><td><span class="badge bg-primary">${d.event_status}</span></td></tr>
                <tr><th>Status Progress</th><td><span class="fw-bold text-dark">${d.status}</span></td></tr>
                <tr><th>Lokasi</th><td>${d.loc_problem}</td></tr><tr><th>Efek</th><td>${d.efek_car}</td></tr>
                <tr><th>Klasifikasi</th><td>${d.klasifikasi}</td></tr><tr><th>Root Cause</th><td>${d.cause}</td></tr>
                <tr><th>Solusi</th><td>${d.counter}</td></tr><tr><th>PIC</th><td>${d.pic}</td></tr>
            `);
            if(res.has_image) {
                var r = Math.random(); var imgUrl = `view_image.php?id=${id}&r=${r}`;
                $('#imageContainer').html(`<div class="position-relative img-thumbnail-qc" style="cursor:pointer;" onclick="showZoom('${imgUrl}')"><img src="${imgUrl}" class="img-fluid" style="max-height: 250px; width: auto; border:1px solid #ddd; padding:2px;"><div class="position-absolute top-0 end-0 p-1"><button class="btn btn-sm btn-light opacity-75 shadow-sm rounded-circle"><i class="bi bi-arrows-fullscreen"></i></button></div></div><div class="text-center mt-2"><small class="text-muted fst-italic"><i class="bi bi-info-circle"></i> Klik gambar untuk memperbesar</small></div>`);
            } else { $('#imageContainer').html('<span class="text-muted small">No Image</span>'); }
        }
    }});
}

function showZoom(url) { $('#imgZoomTarget').attr('src', url); (new bootstrap.Modal(document.getElementById('modalZoomImage'))).show(); }
function exportExcel() { window.open('api_export_excel.php', '_blank'); }
</script>