<?php
// 1. Memanggil konfigurasi database
require_once __DIR__ . "/../config/database_ordering.php";

// Membuat URL dinamis agar tidak error 404
$query_params = $_GET;
unset($query_params['delete_id']); 
$base_query = http_build_query($query_params);
$redirect_url = '?' . $base_query; 
$delete_url_prefix = '?' . ($base_query ? $base_query . '&' : '');

// 2. PROSES DELETE DATA
if (isset($_GET['delete_id']) && $_GET['delete_id'] !== '') {
    $delete_id = $_GET['delete_id'];
    $tsql_delete = "DELETE FROM [dbo].[PACK] WHERE PACK_ID = ?";
    
    $stmt_delete = sqlsrv_query($conn, $tsql_delete, array($delete_id));
    
    if ($stmt_delete) {
        echo "<script>alert('Data berhasil dihapus!'); window.location.href='" . $redirect_url . "';</script>";
        exit;
    } else {
        $errors = sqlsrv_errors();
        $error_msg = "Gagal menghapus data.\\n";
        if ($errors != null) {
            foreach ($errors as $error) {
                $error_msg .= "Error: ".$error['message']."\\n";
            }
        }
        $error_json = json_encode($error_msg);
        echo "<script>alert(" . $error_json . "); window.location.href='" . $redirect_url . "';</script>";
        exit;
    }
}

// 3. PROSES SIMPAN DATA (CREATE / UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = $_POST['pack_id'];
    $pack_code = $_POST['pack_code'];
    $pack_name = $_POST['pack_name'];
    $pack_desc = $_POST['pack_desc'];
    $pack_weight = !empty($_POST['pack_weight']) ? $_POST['pack_weight'] : null;

    if (!empty($id_post)) {
        // Mode Update
        $tsql = "UPDATE [dbo].[PACK] 
                 SET PACK_CODE = ?, PACK_NAME = ?, PACK_DESC = ?, PACK_WEIGHT = ? 
                 WHERE PACK_ID = ?";
        $params = array($pack_code, $pack_name, $pack_desc, $pack_weight, $id_post);
    } else {
        // Mode Create
        $tsql = "INSERT INTO [dbo].[PACK] (PACK_CODE, PACK_NAME, PACK_DESC, PACK_WEIGHT) 
                 VALUES (?, ?, ?, ?)";
        $params = array($pack_code, $pack_name, $pack_desc, $pack_weight);
    }

    $stmt_save = sqlsrv_query($conn, $tsql, $params);
    if ($stmt_save) {
        echo "<script>alert('Data berhasil disimpan!'); window.location.href='" . $redirect_url . "';</script>";
        exit;
    } else {
        $error_msg = "Gagal menyimpan data. Pastikan Kode Pack tidak duplikat.";
    }
}

// Array untuk menyimpan data autocomplete (akan diisi saat looping tabel)
$autocomplete_data = array();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Pack</title>
    <!-- Bootstrap 4 & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- jQuery UI CSS untuk Autocomplete -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        /* MENGECILKAN UKURAN FONT & ELEMEN (COMPACT MODE) */
        .compact-view { font-size: 13px; }
        .compact-view .card-title { font-size: 15px; font-weight: bold; }
        .compact-view .col-form-label { font-size: 13px; padding-top: 5px; }
        .compact-view .form-control { font-size: 13px; padding: 4px 8px; height: 28px; }
        .compact-view .btn { font-size: 12px; padding: 4px 10px; }
        .compact-view .table th, .compact-view .table td { padding: 6px 10px; vertical-align: middle; }
        
        /* STYLE KHUSUS AUTOCOMPLETE */
        .ui-autocomplete {
            z-index: 1050;
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            font-size: 12px;
            background-color: #fff;
            border: 1px solid #ccc;
        }
        .ui-menu-item .ui-menu-item-wrapper { padding: 5px 10px; }
        .ui-menu-item .ui-menu-item-wrapper:hover { background-color: #007bff; color: white; border:none; }
    </style>
</head>
<body class="bg-light compact-view">

<div class="container-fluid mt-3">
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger p-2" style="font-size: 13px;"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- CARD FORM INPUT -->
    <div class="card card-primary mb-3 shadow-sm">
        <div class="card-header bg-primary text-white py-2">
            <h3 class="card-title mb-0" id="form-title">Form Tambah Data Pack</h3>
        </div>
        <div class="card-body py-3">
            <form method="POST" action="" id="formPack">
                <input type="hidden" name="pack_id" id="pack_id" value="">
                
                <div class="form-group row mb-2">
                    <label class="col-sm-2 col-form-label font-weight-bold">Kode Pack:</label>
                    <div class="col-sm-3">
                        <!-- Input Kode Pack dengan fitur autocomplete -->
                        <input type="text" class="form-control" name="pack_code" id="pack_code" maxlength="2" placeholder="Ketik kode..." required>
                    </div>
                </div>
                
                <div class="form-group row mb-2">
                    <label class="col-sm-2 col-form-label font-weight-bold">Nama Pack:</label>
                    <div class="col-sm-5">
                        <input type="text" class="form-control" name="pack_name" id="pack_name" maxlength="20">
                    </div>
                </div>

                <div class="form-group row mb-2">
                    <label class="col-sm-2 col-form-label font-weight-bold">Deskripsi:</label>
                    <div class="col-sm-5">
                        <input type="text" class="form-control" name="pack_desc" id="pack_desc" maxlength="30">
                    </div>
                </div>
                
                <div class="form-group row mb-3">
                    <label class="col-sm-2 col-form-label font-weight-bold">Berat (Weight):</label>
                    <div class="col-sm-3">
                        <input type="number" class="form-control" step="any" name="pack_weight" id="pack_weight">
                    </div>
                </div>
                
                <div class="form-group row mb-0">
                    <div class="col-sm-2"></div>
                    <div class="col-sm-10">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-times"></i> Batal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD TABEL LIST DATA -->
    <div class="card card-secondary shadow-sm">
        <div class="card-header bg-secondary text-white py-2">
            <h3 class="card-title mb-0">Daftar Data Pack</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center" width="5%">No</th>
                            <th width="10%">Kode</th>
                            <th width="25%">Nama</th>
                            <th width="35%">Deskripsi</th>
                            <th width="10%">Berat</th>
                            <th class="text-center" width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tsql_list = "SELECT PACK_ID, PACK_CODE, PACK_NAME, PACK_DESC, PACK_WEIGHT 
                                      FROM [dbo].[PACK] 
                                      ORDER BY PACK_ID DESC";
                        $stmt_list = sqlsrv_query($conn, $tsql_list);

                        if ($stmt_list === false) {
                            echo "<tr><td colspan='6' class='text-center text-danger'>Gagal mengambil data dari database.</td></tr>";
                        } else {
                            $no = 1;
                            $has_data = false;
                            
                            while ($row = sqlsrv_fetch_array($stmt_list, SQLSRV_FETCH_ASSOC)) {
                                $has_data = true;
                                
                                // Menyimpan data ke array untuk dipakai Autocomplete JavaScript nanti
                                $autocomplete_data[] = array(
                                    'label'  => trim($row['PACK_CODE']) . ' - ' . trim($row['PACK_NAME']), // Yang tampil saat diketik
                                    'value'  => trim($row['PACK_CODE']), // Nilai yang masuk ke input box
                                    'id'     => $row['PACK_ID'],
                                    'name'   => trim($row['PACK_NAME']),
                                    'desc'   => trim($row['PACK_DESC']),
                                    'weight' => $row['PACK_WEIGHT']
                                );

                                echo "<tr>";
                                echo "<td class='text-center'>" . $no++ . "</td>";
                                echo "<td>" . htmlspecialchars($row['PACK_CODE']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['PACK_NAME']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['PACK_DESC']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['PACK_WEIGHT']) . "</td>";
                                
                                $delete_link = $delete_url_prefix . "delete_id=" . $row['PACK_ID'];

                                echo "<td class='text-center'>
                                        <button type='button' class='btn btn-info btn-edit' 
                                            data-id='" . $row['PACK_ID'] . "' 
                                            data-code='" . htmlspecialchars($row['PACK_CODE'], ENT_QUOTES) . "' 
                                            data-name='" . htmlspecialchars($row['PACK_NAME'], ENT_QUOTES) . "' 
                                            data-desc='" . htmlspecialchars($row['PACK_DESC'], ENT_QUOTES) . "' 
                                            data-weight='" . htmlspecialchars($row['PACK_WEIGHT'], ENT_QUOTES) . "'>
                                            <i class='fas fa-edit'></i> Edit
                                        </button>
                                        <a href='" . $delete_link . "' class='btn btn-danger' onclick='return confirm(\"Apakah Anda yakin ingin menghapus data ini?\")'>
                                            <i class='fas fa-trash'></i> Delete
                                        </a>
                                      </td>";
                                echo "</tr>";
                            }
                            
                            if (!$has_data) {
                                echo "<tr><td colspan='6' class='text-center py-3'>Belum ada data.</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery Wajib untuk Autocomplete -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- jQuery UI JS untuk fitur Autocomplete -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Data array yang dihasilkan dari PHP database di atas
    var packList = <?php echo json_encode($autocomplete_data); ?>;

    // 2. Inisialisasi jQuery UI Autocomplete pada input Kode Pack
    $(document).ready(function() {
        $("#pack_code").autocomplete({
            source: packList,
            minLength: 1, // Autocomplete aktif setelah ketik 1 huruf
            select: function(event, ui) {
                // Ketika pilihan dari dropdown autocomplete diklik, otomatis isi seluruh form
                $('#pack_id').val(ui.item.id);
                $('#pack_code').val(ui.item.value);
                $('#pack_name').val(ui.item.name);
                $('#pack_desc').val(ui.item.desc);
                $('#pack_weight').val(ui.item.weight);
                
                // Ubah judul jadi form edit
                $('#form-title').text('Form Edit Data Pack');
                return false;
            }
        });
    });

    // 3. Fungsi saat tombol Edit di tabel diklik (Tetap dipertahankan)
    $('.btn-edit').on('click', function() {
        var id = $(this).data('id');
        var code = $(this).data('code');
        var name = $(this).data('name');
        var desc = $(this).data('desc');
        var weight = $(this).data('weight');

        $('#pack_id').val(id);
        $('#pack_code').val(code);
        $('#pack_name').val(name);
        $('#pack_desc').val(desc);
        $('#pack_weight').val(weight);

        $('#form-title').text('Form Edit Data Pack');
        
        $('html, body').animate({scrollTop: 0}, 'fast');
    });

    // 4. Fungsi Reset (Batal)
    function resetForm() {
        $('#formPack')[0].reset();
        $('#pack_id').val('');
        $('#form-title').text('Form Tambah Data Pack');
    }
</script>
</body>
</html>