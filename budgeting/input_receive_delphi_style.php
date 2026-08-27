<?php
// ==========================================================
// File: input_receive_delphi_style.php
// Deskripsi: Form Penerimaan Barang (MSDATA) - Konsep Header & Detail Delphi Style
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

require_once 'config.php';

function h($string) { return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }

function get_sql_errors() {
    $errors = sqlsrv_errors();
    $err_msg = "";
    if ($errors != null) { 
        foreach ($errors as $error) { $err_msg .= h($error['message']) . "<br>"; } 
    } else { 
        $err_msg = "Unknown Error SQL."; 
    }
    return $err_msg;
}

$message = "";

// ==========================================================
// PROSES SIMPAN TRANSAKSI (HEADER & DETAIL SEKALIGUS)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_receive') {
    
    $rcv_no   = trim($_POST['rcv_no']);
    $rcv_date = trim($_POST['rcv_date']) . ' ' . date('H:i:s');
    $rcv_dono = trim($_POST['rcv_dono']);
    $sup_id   = (int)$_POST['sup_id'];
    $rcv_pic  = trim($_POST['rcv_pic']);
    $rcv_type = (int)$_POST['rcv_type']; // 0: Raw, 1: Machine, dll (Sesuai tinyint)

    $po_ids     = isset($_POST['po_id']) ? $_POST['po_id'] : array();
    $item_ids   = isset($_POST['item_id']) ? $_POST['item_id'] : array();
    $pod_prices = isset($_POST['pod_price']) ? $_POST['pod_price'] : array();
    $qty_ins    = isset($_POST['qty_in']) ? $_POST['qty_in'] : array();

    $is_success = true;
    $error_detail = "";

    if (empty($rcv_no) || empty($sup_id) || count($po_ids) === 0) {
        $is_success = false;
        $error_detail = "Nomor ICL, Supplier, dan minimal 1 detail item barang wajib diisi.";
    } else {
        sqlsrv_begin_transaction($conn_msdata);

        // 1. Insert Header RECEIVE
        $sql_head = "INSERT INTO RECEIVE (RCV_NO, RCV_DONO, RCV_DATE, SUP_ID, RCV_PIC, RCV_TYPE) 
                     VALUES (?, ?, ?, ?, ?, ?);
                     SELECT SCOPE_IDENTITY() AS new_rcv_id;";
        
        $stmt_head = sqlsrv_query($conn_msdata, $sql_head, array($rcv_no, $rcv_dono, $rcv_date, $sup_id, $rcv_pic, $rcv_type));

        if ($stmt_head) {
            sqlsrv_next_result($stmt_head);
            $row_id = sqlsrv_fetch_array($stmt_head, SQLSRV_FETCH_ASSOC);
            $new_rcv_id = $row_id['new_rcv_id'];

            // 2. Insert Detail RECEIVE_DETAIL
            $sql_det = "INSERT INTO RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE) 
                        VALUES (?, ?, ?, ?, ?)";

            for ($i = 0; $i < count($po_ids); $i++) {
                $p_po_id   = (int)$po_ids[$i];
                $p_item_id = (int)$item_ids[$i];
                $p_qty     = (float)$qty_ins[$i];
                $p_price   = (float)$pod_prices[$i];

                if ($p_qty > 0 && $p_item_id > 0) {
                    $stmt_det = sqlsrv_query($conn_msdata, $sql_det, array($new_rcv_id, $p_item_id, $p_po_id, $p_qty, $p_price));
                    if (!$stmt_det) { 
                        $is_success = false; 
                        $error_detail = get_sql_errors(); 
                        break; 
                    }
                }
            }
        } else {
            $is_success = false;
            $error_detail = "Gagal menyimpan Header: " . get_sql_errors();
        }

        if ($is_success) {
            sqlsrv_commit($conn_msdata);
            $print_url = "report_penerimaan_msdata.php?id=" . urlencode($rcv_no);
            $message = "
            <div class='alert alert-success shadow-sm mb-4'>
                <h4 class='alert-heading'><i class='fas fa-check-circle'></i> Berhasil Disimpan!</h4>
                <p>Dokumen Penerimaan <strong>" . h($rcv_no) . "</strong> berhasil dicatat ke sistem.</p>
                <hr>
                <a href='{$print_url}' target='_blank' class='btn btn-warning btn-sm font-weight-bold'><i class='fas fa-print'></i> Cetak ICL</a>
                <a href='input_receive_delphi_style.php' class='btn btn-primary btn-sm font-weight-bold ml-2'><i class='fas fa-plus'></i> Input Baru</a>
            </div>";
        } else {
            sqlsrv_rollback($conn_msdata);
            $message = "<div class='alert alert-danger shadow-sm mb-4'><strong>Gagal!</strong><br>{$error_detail}</div>";
        }
    }
}

// Ambil List Supplier untuk Dropdown Header
$sql_sup = "SELECT SUP_ID, SUP_CODE, SUP_COMP FROM SUPPLIER ORDER BY SUP_COMP ASC";
$stmt_sup = sqlsrv_query($conn_msdata, $sql_sup);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Items Receive (Delphi Style) - ERP Modern</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .delphi-header-box { background-color: #006666; border-radius: 6px; padding: 15px; color: #fff; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .delphi-header-box label { font-weight: bold; color: #e0f2f1; font-size: 12px; margin-bottom: 2px; }
        .form-control-sm { height: 32px !important; font-size: 12px; }
        .table th { background-color: #e9ecef; color: #333; font-size: 12px; vertical-align: middle; }
        .table td { vertical-align: middle; font-size: 12px; }
        .select2-container .select2-selection--single { height: 32px !important; font-size: 12px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 30px !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <?php echo $message; ?>

    <form method="POST" action="" id="formReceive">
        <input type="hidden" name="action" value="save_receive">

        <!-- ========================================== -->
        <!-- BAGIAN 1: HEADER (Gaya Hijau Telur Asin Delphi) -->
        <!-- ========================================== -->
        <div class="delphi-header-box">
            <div class="row">
                <div class="col-md-3 form-group mb-2">
                    <label>I.C.L No.</label>
                    <input type="text" name="rcv_no" id="rcvNo" class="form-control form-control-sm font-weight-bold" required value="RCV-<?php echo date('ymd-Hi'); ?>">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label>Date</label>
                    <input type="date" name="rcv_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label>D.O #</label>
                    <input type="text" name="rcv_dono" class="form-control form-control-sm" placeholder="Nomor Surat Jalan Supplier">
                </div>
            </div>

            <div class="row">
                <div class="col-md-2 form-group mb-2">
                    <label>Sup.Code</label>
                    <select id="supCodeSelect" class="form-control form-control-sm select2-search">
                        <option value="">-- Pilih Kode --</option>
                        <?php 
                        if ($stmt_sup !== false) {
                            while ($s = sqlsrv_fetch_array($stmt_sup, SQLSRV_FETCH_ASSOC)) {
                                echo '<option value="'.$s['SUP_ID'].'" data-name="'.h($s['SUP_COMP']).'">'.h($s['SUP_CODE']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 form-group mb-2">
                    <label>Sup.Company</label>
                    <select name="sup_id" id="supCompanySelect" class="form-control form-control-sm select2-search" required>
                        <option value="">-- Pilih Perusahaan Supplier --</option>
                        <!-- Akan tersinkronisasi otomatis via jQuery -->
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label>PIC (Diterima Oleh)</label>
                    <input type="text" name="rcv_pic" class="form-control form-control-sm" required placeholder="Nama PIC Gudang">
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label>TYPE</label>
                    <select name="rcv_type" class="form-control form-control-sm">
                        <option value="0">Raw Material</option>
                        <option value="1">Machine / Parts</option>
                        <option value="2">Others</option>
                    </select>
                </div>
            </div>

            <!-- Panel Tombol Kontrol Gaya Delphi -->
            <div class="row mt-3 pt-2 border-top border-light">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-light btn-sm font-weight-bold px-4 text-dark shadow-sm" id="btnSimpan">
                        <i class="fas fa-save text-success"></i> Simpan
                    </button>
                    <a href="receive_report.php?source=msdata" class="btn btn-outline-light btn-sm font-weight-bold ml-2">
                        <i class="fas fa-list"></i> Refresh / Data Laporan
                    </a>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- BAGIAN 2: TABEL DETAIL (Pilih PO Open) -->
        <!-- ========================================== -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white font-weight-bold py-2">
                <i class="fas fa-shopping-cart"></i> Pilih Item dari Purchase Order (PO) yang Statusnya OPEN
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-striped mb-0" id="tableDetail">
                        <thead class="thead-light text-center" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th width="5%">Pilih</th>
                                <th width="12%">ITEM CODE</th>
                                <th width="33%">ITEM NAME</th>
                                <th width="12%">PO #</th>
                                <th width="12%">QTY PO</th>
                                <th width="14%">QTY DITERIMA (RCVD)</th>
                                <th width="12%">HARGA (POD_PRICE)</th>
                            </tr>
                        </thead>
                        <tbody id="poItemContainer">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Silakan pilih <strong>Supplier</strong> di atas untuk memuat daftar item dari PO yang masih Open.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-search').select2({ width: '100%' });

    // Sinkronisasi antara Kode Supplier dan Perusahaan Supplier
    $('#supCodeSelect').on('change', function() {
        var supId = $(this).val();
        $('#supCompanySelect').val(supId).trigger('change');
        loadOpenPOItems(supId);
    });

    $('#supCompanySelect').on('change', function() {
        var supId = $(this).val();
        $('#supCodeSelect').val(supId).trigger('change');
        loadOpenPOItems(supId);
    });

    // Fungsi AJAX untuk menarik item PO Open berdasarkan Supplier
    function loadOpenPOItems(supId) {
        if (!supId) {
            $('#poItemContainer').html('<tr><td colspan="7" class="text-center text-muted py-4">Silakan pilih Supplier terlebih dahulu.</td></tr>');
            return;
        }

        $('#poItemContainer').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-info"></i><br>Memuat data PO Open...</td></tr>');

        $.ajax({
            url: 'get_open_po_items.php',
            type: 'GET',
            data: { sup_id: supId },
            dataType: 'json',
            success: function(data) {
                if (data.length > 0) {
                    var rows = '';
                    $.each(data, function(index, row) {
                        rows += `
                            <tr>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input row-selector" id="chk_${index}" value="${index}">
                                        <label class="custom-control-label" for="chk_${index}"></label>
                                    </div>
                                    <!-- Hidden Inputs yang dikirim ke Server saat dicentang -->
                                    <input type="hidden" name="po_id[]" value="${row.po_id}" disabled class="input-data">
                                    <input type="hidden" name="item_id[]" value="${row.item_id}" disabled class="input-data">
                                    <input type="hidden" name="pod_price[]" value="${row.pod_price}" disabled class="input-data">
                                </td>
                                <td><strong>${row.item_code}</strong></td>
                                <td>${row.item_name} <small class="text-muted">(${row.uom})</small></td>
                                <td class="text-center font-weight-bold text-primary">${row.po_num}</td>
                                <td class="text-center">${row.qty_request}</td>
                                <td>
                                    <input type="number" name="qty_in[]" class="form-control form-control-sm text-right font-weight-bold text-success input-qty" min="0.01" max="${row.qty_request}" step="any" value="${row.qty_request}" disabled required>
                                </td>
                                <td class="text-right">${Number(row.pod_price).toLocaleString('id-ID')}</td>
                            </tr>
                        `;
                    });
                    $('#poItemContainer').html(rows);
                } else {
                    $('#poItemContainer').html('<tr><td colspan="7" class="text-center text-danger py-4">Tidak ada item dari PO Open untuk supplier ini.</td></tr>');
                }
            },
            error: function() {
                $('#poItemContainer').html('<tr><td colspan="7" class="text-center text-danger py-4">Gagal terhubung ke server untuk memuat item PO.</td></tr>');
            }
        });
    }

    // Interaksi Checkbox Baris Tabel (Mengaktifkan/Menonaktifkan input saat dipilih)
    $(document).on('change', '.row-selector', function() {
        var tr = $(this).closest('tr');
        var inputs = tr.find('.input-data, .input-qty');
        
        if ($(this).is(':checked')) {
            inputs.prop('disabled', false);
            tr.css('background-color', '#e8f5e9'); // Highlight hijau tipis saat dipilih
        } else {
            inputs.prop('disabled', true);
            tr.css('background-color', '');
        }
    });

    // Validasi sebelum form disubmit
    $('#formReceive').on('submit', function(e) {
        var checkedCount = $('.row-selector:checked').length;
        if (checkedCount === 0) {
            e.preventDefault();
            alert("Peringatan: Harap centang minimal 1 item barang dari tabel PO di bawah untuk diterima.");
            return false;
        }
    });
});
</script>
</body>
</html>
<?php 
if (isset($stmt_sup) && $stmt_sup !== false) sqlsrv_free_stmt($stmt_sup);
if ($conn_msdata !== false) sqlsrv_close($conn_msdata);
?>