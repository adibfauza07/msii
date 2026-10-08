<?php
require_once __DIR__ . "/../config/database_ordering.php";

$message = '';

// Variabel default form (kosong untuk mode Create)
$is_edit = false;
$form_id = '';
$form_cust_id = '';
$form_cust_label = '';
$form_pre_ref = '';
$form_pre_date = '';
$form_pre_user = '';
$form_details = array();

// ==========================================
// 1. BLOK PENANGANAN HAPUS DATA (DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = $_POST['target_id'];
    if (sqlsrv_begin_transaction($conn) === false) die(print_r(sqlsrv_errors(), true));

    try {
        $sqlDelDtl = "DELETE FROM PackReturnDtl WHERE PRE_ID = ?";
        $stmtDelDtl = sqlsrv_query($conn, $sqlDelDtl, array($del_id));
        if ($stmtDelDtl === false) throw new Exception("Gagal menghapus detail.");

        $sqlDelHdr = "DELETE FROM PackReturn WHERE PRE_ID = ?";
        $stmtDelHdr = sqlsrv_query($conn, $sqlDelHdr, array($del_id));
        if ($stmtDelHdr === false) throw new Exception("Gagal menghapus header.");

        sqlsrv_commit($conn);
        $message = "<div class='alert success'>Data Transaksi ID: $del_id berhasil dihapus!</div>";
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $message = "<div class='alert error'>Terjadi kesalahan hapus: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 2. BLOK PENANGANAN UPDATE DATA (UPDATE)
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (sqlsrv_begin_transaction($conn) === false) die(print_r(sqlsrv_errors(), true));

    try {
        $update_id = $_POST['target_id'];
        $cust_id = $_POST['cust_id'];
        $pre_ref = $_POST['pre_ref'];
        $pre_date = $_POST['pre_date'];
        $pre_user = $_POST['pre_user'];

        // Update Header
        $sqlUpdateHdr = "UPDATE PackReturn SET CUST_ID = ?, PRE_REF = ?, PRE_DATE = ?, PRE_USER = ? WHERE PRE_ID = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdateHdr, array($cust_id, $pre_ref, $pre_date, $pre_user, $update_id));
        if ($stmtUpdate === false) throw new Exception("Gagal update Header.");

        // Hapus Detail Lama
        $sqlDelDtl = "DELETE FROM PackReturnDtl WHERE PRE_ID = ?";
        sqlsrv_query($conn, $sqlDelDtl, array($update_id));

        // Insert Detail Baru
        if (isset($_POST['pack_id']) && is_array($_POST['pack_id'])) {
            $sqlDtl = "INSERT INTO PackReturnDtl (PRE_ID, PACK_ID, PRE_QTY) VALUES (?, ?, ?)";
            for ($i = 0; $i < count($_POST['pack_id']); $i++) {
                $pack_id = $_POST['pack_id'][$i];
                $pre_qty = $_POST['pre_qty'][$i];
                if (!empty($pack_id) && !empty($pre_qty)) {
                    $stmtDtl = sqlsrv_query($conn, $sqlDtl, array($update_id, $pack_id, $pre_qty));
                    if ($stmtDtl === false) throw new Exception("Gagal update Detail.");
                }
            }
        }

        sqlsrv_commit($conn);
        $message = "<div class='alert success'>Data Transaksi ID: $update_id berhasil diperbarui!</div>";
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $message = "<div class='alert error'>Terjadi kesalahan update: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 3. BLOK PENANGANAN SIMPAN BARU (CREATE)
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (sqlsrv_begin_transaction($conn) === false) die(print_r(sqlsrv_errors(), true));

    try {
        $cust_id = $_POST['cust_id'];
        $pre_ref = $_POST['pre_ref'];
        $pre_date = $_POST['pre_date'];
        $pre_user = $_POST['pre_user'];

        // Insert Header
        $sqlHeader = "INSERT INTO PackReturn (CUST_ID, PRE_REF, PRE_DATE, PRE_USER) VALUES (?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS last_id;";
        $stmtHeader = sqlsrv_query($conn, $sqlHeader, array($cust_id, $pre_ref, $pre_date, $pre_user));
        if ($stmtHeader === false) throw new Exception("Gagal insert Header.");

        sqlsrv_next_result($stmtHeader);
        sqlsrv_fetch($stmtHeader);
        $pre_id = sqlsrv_get_field($stmtHeader, 0);

        // Insert Detail
        if (isset($_POST['pack_id']) && is_array($_POST['pack_id'])) {
            $sqlDtl = "INSERT INTO PackReturnDtl (PRE_ID, PACK_ID, PRE_QTY) VALUES (?, ?, ?)";
            for ($i = 0; $i < count($_POST['pack_id']); $i++) {
                $pack_id = $_POST['pack_id'][$i];
                $pre_qty = $_POST['pre_qty'][$i];
                if (!empty($pack_id) && !empty($pre_qty)) {
                    $stmtDtl = sqlsrv_query($conn, $sqlDtl, array($pre_id, $pack_id, $pre_qty));
                    if ($stmtDtl === false) throw new Exception("Gagal insert Detail.");
                }
            }
        }

        sqlsrv_commit($conn);
        $message = "<div class='alert success'>Data berhasil disimpan! (ID Transaksi: $pre_id)</div>";
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $message = "<div class='alert error'>Terjadi kesalahan simpan: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 4. BLOK LOAD DATA UNTUK FORM EDIT (READ 1 RECORD)
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $is_edit = true;
    $form_id = $_POST['target_id'];
    
    // Ambil Data Header
    $sqlHdr = "SELECT PR.*, C.CUST_CODE, C.CUST_COMP FROM PackReturn PR LEFT JOIN CUST C ON PR.CUST_ID = C.CUST_ID WHERE PR.PRE_ID = ?";
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, array($form_id));
    if ($hdr = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) {
        $form_cust_id = $hdr['CUST_ID'];
        $form_cust_label = $hdr['CUST_CODE'] . " - " . $hdr['CUST_COMP'];
        $form_pre_ref = $hdr['PRE_REF'];
        $form_pre_user = $hdr['PRE_USER'];
        
        // Format Date untuk input html (Y-m-d)
        if (is_object($hdr['PRE_DATE'])) {
            $form_pre_date = $hdr['PRE_DATE']->format('Y-m-d');
        } else {
            $form_pre_date = date('Y-m-d', strtotime($hdr['PRE_DATE']));
        }
    }

    // Ambil Data Detail
    $sqlDtl = "SELECT PD.*, P.PACK_CODE, P.PACK_DESC FROM PackReturnDtl PD LEFT JOIN PACK P ON PD.PACK_ID = P.PACK_ID WHERE PD.PRE_ID = ?";
    $stmtDtl = sqlsrv_query($conn, $sqlDtl, array($form_id));
    while ($dtl = sqlsrv_fetch_array($stmtDtl, SQLSRV_FETCH_ASSOC)) {
        $label = trim($dtl['PACK_CODE']);
        if (!empty($dtl['PACK_DESC'])) $label .= " - " . trim($dtl['PACK_DESC']);
        $dtl['PACK_LABEL'] = $label;
        $form_details[] = $dtl;
    }
}

// ==========================================
// 5. BLOK MENGAMBIL DATA LIST (READ ALL)
// ==========================================
$sqlList = "
    SELECT TOP 10 
        PR.PRE_ID, 
        PR.PRE_REF, 
        PR.PRE_DATE, 
        PR.PRE_USER, 
        C.CUST_CODE, 
        C.CUST_COMP
    FROM PackReturn PR
    LEFT JOIN CUST C ON PR.CUST_ID = C.CUST_ID
    ORDER BY PR.PRE_ID DESC
";
$stmtList = sqlsrv_query($conn, $sqlList);
$recentReturns = array();
if ($stmtList !== false) {
    while ($row = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $recentReturns[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Pack Return</title>
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .form-group { margin-bottom: 15px; }
        label { display: inline-block; width: 150px; font-weight: bold; }
        input[type="text"], input[type="number"] {
            padding: 8px; width: 250px; border: 1px solid #ccc; border-radius: 4px;
        }
        
        .table-form { border-collapse: collapse; width: 100%; max-width: 600px; margin-top: 10px; }
        .table-form th, .table-form td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        .table-form th { background-color: #f4f4f4; }
        
        .table-list { border-collapse: collapse; width: 100%; max-width: 1000px; margin-top: 10px; font-size: 13px; }
        .table-list th, .table-list td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; vertical-align: middle; }
        .table-list th { background-color: #e9ecef; color: #333; }
        .table-list tr:hover { background-color: #f8f9fa; }

        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .info { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; font-weight: bold;}
        
        .btn { padding: 8px 15px; cursor: pointer; color: white; border: none; border-radius: 4px; text-decoration: none;}
        .btn-primary { background-color: #007bff; }
        .btn-success { background-color: #28a745; }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
        
        .btn-sm { padding: 4px 10px; font-size: 12px; cursor: pointer; text-decoration: none; border: none; border-radius: 3px; display: inline-block; margin-right: 4px;}
        .btn-warning-sm { background-color: #ffc107; color: #212529; }
        .btn-danger-sm { background-color: #dc3545; color: white; }

        .list-section { margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; }
    </style>
</head>
<body>

    <h2>Form Input Pack Return</h2>
    
    <?php echo $message; ?>
    
    <?php if ($is_edit): ?>
        <div class="alert info">Mode Edit Sedang Aktif: Mengedit Transaksi ID <?php echo htmlspecialchars($form_id); ?></div>
    <?php endif; ?>

    <!-- Form Utama (Bisa Create atau Update) -->
    <form method="POST" action="">
        <!-- Identifier Action -->
        <?php if ($is_edit): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="target_id" value="<?php echo htmlspecialchars($form_id); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <div class="form-group">
            <label>Customer:</label>
            <input type="text" id="cust_search" placeholder="Ketik nama atau kode customer..." value="<?php echo htmlspecialchars($form_cust_label); ?>" required>
            <input type="hidden" name="cust_id" id="cust_id" value="<?php echo htmlspecialchars($form_cust_id); ?>" required>
        </div>

        <div class="form-group">
            <label>Return Reference:</label>
            <input type="text" name="pre_ref" placeholder="No. Referensi" maxlength="25" value="<?php echo htmlspecialchars($form_pre_ref); ?>">
        </div>

        <div class="form-group">
            <label>Return Date:</label>
            <!-- Tipe diubah jadi text agar kompatibel di browser lama, kalender dipanggil dari jQuery -->
            <input type="text" name="pre_date" id="pre_date" placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($form_pre_date); ?>" autocomplete="off" required>
        </div>

        <div class="form-group">
            <label>User:</label>
            <input type="text" name="pre_user" placeholder="Nama User" maxlength="10" value="<?php echo htmlspecialchars($form_pre_user); ?>">
        </div>

        <hr style="max-width: 800px; margin-left: 0;">
        <h3>Detail Packing yang Dikembalikan</h3>
        
        <table class="table-form" id="detail_table">
            <thead>
                <tr>
                    <th>Pack Name/Code</th>
                    <th width="100">Qty</th>
                    <th width="80">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($is_edit && count($form_details) > 0): ?>
                    <!-- Render baris yang ada di database saat mode EDIT -->
                    <?php foreach ($form_details as $dtl): ?>
                        <tr>
                            <td>
                                <input type="text" class="pack_search" placeholder="Ketik nama/kode pack..." value="<?php echo htmlspecialchars($dtl['PACK_LABEL']); ?>" style="width: 90%;" required>
                                <input type="hidden" name="pack_id[]" class="pack_id" value="<?php echo htmlspecialchars($dtl['PACK_ID']); ?>" required>
                            </td>
                            <td>
                                <input type="number" name="pre_qty[]" min="1" value="<?php echo htmlspecialchars($dtl['PRE_QTY']); ?>" style="width: 80%;" required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger remove_row">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Render 1 baris kosong jika mode CREATE -->
                    <tr>
                        <td>
                            <input type="text" class="pack_search" placeholder="Ketik nama/kode pack..." style="width: 90%;" required>
                            <input type="hidden" name="pack_id[]" class="pack_id" required>
                        </td>
                        <td>
                            <input type="number" name="pre_qty[]" min="1" style="width: 80%;" required>
                        </td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 10px; margin-bottom: 20px;">
            <button type="button" class="btn btn-primary" id="add_row">+ Tambah Pack</button>
        </div>

        <?php if ($is_edit): ?>
            <button type="submit" class="btn btn-warning" style="color: black;">Update Data Return</button>
            <a href="pack_return.php" class="btn btn-secondary">Batal Edit</a>
        <?php else: ?>
            <button type="submit" class="btn btn-success">Simpan Data Return</button>
        <?php endif; ?>
    </form>


    <!-- ========================================== -->
    <!-- BAGIAN LIST TRANSAKSI (RAPAT & CRUD)       -->
    <!-- ========================================== -->
    <div class="list-section">
        <h3>10 Transaksi Return Terakhir</h3>
        <table class="table-list">
            <thead>
                <tr>
                    <th width="60">ID Ref</th>
                    <th width="90">Tanggal</th>
                    <th>Customer</th>
                    <th width="150">No. Referensi</th>
                    <th width="100">User</th>
                    <th width="120" style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentReturns) > 0): ?>
                    <?php foreach ($recentReturns as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['PRE_ID']); ?></td>
                            <td>
                                <?php 
                                    if (is_object($row['PRE_DATE'])) echo $row['PRE_DATE']->format('d-M-Y');
                                    else echo htmlspecialchars(date('d-M-Y', strtotime($row['PRE_DATE']))); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['CUST_CODE'] . ' - ' . $row['CUST_COMP']); ?></td>
                            <td><?php echo htmlspecialchars($row['PRE_REF']); ?></td>
                            <td><?php echo htmlspecialchars($row['PRE_USER']); ?></td>
                            <td style="text-align:center;">
                                <!-- Tombol Edit Memakai POST agar tetap di halaman yang sama -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="target_id" value="<?php echo $row['PRE_ID']; ?>">
                                    <button type="submit" class="btn-sm btn-warning-sm">Edit</button>
                                </form>
                                
                                <!-- Tombol Hapus -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data Transaksi ID: <?php echo $row['PRE_ID']; ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="target_id" value="<?php echo $row['PRE_ID']; ?>">
                                    <button type="submit" class="btn-sm btn-danger-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; color: #666; padding: 15px;">Belum ada data transaksi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    $(document).ready(function() {
        
        // Inisialisasi Datepicker untuk kompabilitas browser lawas
        $("#pre_date").datepicker({
            dateFormat: "yy-mm-dd", // Menghasilkan format YYYY-MM-DD
            changeMonth: true,
            changeYear: true
        });

        // 1. Autocomplete Customer
        $("#cust_search").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "ajax_customer_autocomplete.php",
                    type: "POST",
                    dataType: "json",
                    data: { q: request.term },
                    success: function(data) {
                        response($.map(data, function(item) {
                            return {
                                label: item.label || (item.CUST_CODE + " - " + item.CUST_COMP),
                                value: item.company || item.CUST_COMP,
                                id: item.id || item.CUST_ID
                            };
                        }));
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                $("#cust_id").val(ui.item.id);
                $(this).val(ui.item.label);
                return false;
            }
        });

        // 2. Autocomplete Pack
        $(document).on('focus', '.pack_search', function() {
            if (!$(this).data("ui-autocomplete")) {
                $(this).autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "ajax_pack_autocomplete.php", 
                            type: "POST",
                            dataType: "json",
                            data: { q: request.term },
                            success: function(data) {
                                response($.map(data, function(item) {
                                    var labelText = item.PACK_CODE;
                                    if (item.PACK_DESC && item.PACK_DESC.trim() !== '') {
                                        labelText += " - " + item.PACK_DESC;
                                    }
                                    return {
                                        label: labelText,
                                        value: labelText,
                                        id: item.PACK_ID
                                    };
                                }));
                            }
                        });
                    },
                    minLength: 1,
                    select: function(event, ui) {
                        $(this).closest('tr').find('.pack_id').val(ui.item.id);
                        $(this).val(ui.item.value);
                        return false;
                    }
                });
            }
        });

        // 3. Tambah baris baru
        $("#add_row").click(function() {
            var newRow = "<tr>" +
                "<td>" +
                    "<input type='text' class='pack_search' placeholder='Ketik nama/kode pack...' style='width: 90%;' required>" +
                    "<input type='hidden' name='pack_id[]' class='pack_id' required>" +
                "</td>" +
                "<td>" +
                    "<input type='number' name='pre_qty[]' min='1' style='width: 80%;' required>" +
                "</td>" +
                "<td>" +
                    "<button type='button' class='btn btn-danger remove_row'>Hapus</button>" +
                "</td>" +
            "</tr>";
            $("#detail_table tbody").append(newRow);
        });

        // 4. Hapus baris dinamis
        $(document).on('click', '.remove_row', function() {
            $(this).closest('tr').remove();
        });
    });
    </script>

</body>
</html>