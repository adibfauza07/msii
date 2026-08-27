<?php
require_once 'config.php';

function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";
$months = array(1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun', 7=>'Jul', 8=>'Agt', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des');

// ==========================================================
// PROSES SIMPAN BUDGET (TRANSACTIONAL)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_budget') {
    
    $plant_id      = isset($_POST['plant_id']) ? strtoupper(trim($_POST['plant_id'])) : '';
    $department_id = isset($_POST['department_id']) ? strtoupper(trim($_POST['department_id'])) : '';
    $period_year   = isset($_POST['period_year']) ? (int)$_POST['period_year'] : date('Y');
    $items         = isset($_POST['items']) ? $_POST['items'] : array();

    if (!empty($plant_id) && !empty($department_id) && !empty($items)) {
        
        sqlsrv_begin_transaction($conn);
        $is_success = true;

        try {
            $check_sql = "SELECT budget_id FROM Budget_Plan_Header WHERE plant_id = ? AND department_id = ? AND period_year = ?";
            $check_stmt = sqlsrv_query($conn, $check_sql, array($plant_id, $department_id, $period_year));
            
            if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
                throw new Exception("Budget untuk Departemen ini di tahun {$period_year} sudah ada.");
            }

            $insert_header = "INSERT INTO Budget_Plan_Header (plant_id, department_id, section_name, period_year, status, created_by) 
                              VALUES (?, ?, 'ALL', ?, 'DRAFT', ?);
                              SELECT SCOPE_IDENTITY() AS new_id;";
            $stmt_header = sqlsrv_query($conn, $insert_header, array($plant_id, $department_id, $period_year, $_SESSION['db_user']));
            
            if (!$stmt_header) {
                throw new Exception("Gagal menyimpan Header.");
            }

            sqlsrv_next_result($stmt_header);
            $row_id = sqlsrv_fetch_array($stmt_header, SQLSRV_FETCH_ASSOC);
            $new_budget_id = $row_id['new_id'];

            $insert_detail = "INSERT INTO Budget_Plan_Detail (budget_id, item_code, month_no, qty_plan, unit_price, amount_plan) 
                              VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_detail = sqlsrv_prepare($conn, $insert_detail, array(&$new_budget_id, &$item_code, &$month_no, &$qty_plan, &$unit_price, &$amount_plan));

            foreach ($items as $item) {
                $item_code = isset($item['item_code']) ? $item['item_code'] : '';
                if (empty($item_code)) continue;

                for ($m = 1; $m <= 12; $m++) {
                    $qty_plan   = isset($item['months'][$m]['qty']) ? (float)$item['months'][$m]['qty'] : 0;
                    $unit_price = isset($item['months'][$m]['price']) ? (float)$item['months'][$m]['price'] : 0;
                    
                    if ($qty_plan > 0) {
                        $amount_plan = $qty_plan * $unit_price;
                        $month_no    = $m;

                        if (!sqlsrv_execute($stmt_detail)) {
                            throw new Exception("Gagal menyimpan detail item " . h($item_code));
                        }
                    }
                }
            }

            sqlsrv_commit($conn);
            $message = "<div class='alert alert-success'>Budget Planning berhasil disimpan.</div>";

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Data tidak lengkap atau tidak ada barang untuk departemen ini.</div>";
    }
}

// Ambil list Departemen
$sql_dept = "SELECT DISTINCT department_id, department_name, plant_id FROM Master_Department ORDER BY plant_id, department_id";
$stmt_dept = sqlsrv_query($conn, $sql_dept);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Budgeting - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 12px; padding: 15px; }
        .table-grid th, .table-grid td { vertical-align: middle; padding: 4px; white-space: nowrap; }
        .table-grid input { width: 85px; font-size: 12px; padding: 2px 5px; }
        .freeze-col { position: sticky; left: 0; background: #fff; z-index: 1; border-right: 2px solid #ccc; min-width: 250px; }
        .wrapper { overflow-x: auto; width: 100%; border: 1px solid #ccc; background: #fff; padding-bottom: 15px; }
        .readonly-amount { background-color: #e9ecef; color: #495057; font-weight: bold; border: 1px solid #ced4da; }
        #loadingMsg { display: none; margin-left: 10px; color: #0056b3; font-weight: bold; }
    </style>
</head>
<body>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white font-weight-bold">Form Planning Budget (12 Bulan)</div>
        <div class="card-body">
            <?php echo $message; ?>
            
            <form method="POST" action="" id="formBudget">
                <input type="hidden" name="action" value="save_budget">
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Lokasi Plant & Departemen</label>
                        <select name="department_id" id="department_id" class="form-control" required>
                            <option value="">-- Pilih Dept --</option>
                            <?php 
                            while ($row_dept = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
                                echo '<option value="'.h($row_dept['department_id']).'" data-plant="'.h($row_dept['plant_id']).'">'.h($row_dept['plant_id']).' - '.h($row_dept['department_name']).'</option>';
                            }
                            ?>
                        </select>
                        <input type="hidden" name="plant_id" id="plant_id" value=""> 
                    </div>
                    <div class="col-md-2">
                        <label>Tahun Periode</label>
                        <input type="number" name="period_year" class="form-control" value="<?php echo date('Y'); ?>" required>
                    </div>
                    <div class="col-md-7 text-right align-self-end">
                        <span id="loadingMsg">Memuat data barang...</span>
                        <button type="submit" class="btn btn-success btn-sm ml-3">Simpan Planning</button>
                    </div>
                </div>

                <div class="wrapper">
                    <table class="table table-bordered table-grid table-hover" id="budgetTable">
                        <thead class="bg-dark text-white text-center">
                            <tr>
                                <th rowspan="2" class="freeze-col text-center">Kode & Nama Barang</th>
                                <?php foreach ($months as $m => $m_name): ?>
                                    <th colspan="3"><?php echo $m_name; ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($months as $m => $m_name): ?>
                                    <th>Qty</th>
                                    <th>Harga (Rp)</th>
                                    <th class="text-warning">Amount (Rp)</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="37" class="text-center text-muted font-italic py-4">Silakan pilih Departemen di atas untuk menampilkan daftar barang.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>

        </div>
    </div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script>
    $(document).ready(function() {
        // Saat dropdown Departemen berubah
        $('#department_id').on('change', function() {
            var dept_id = $(this).val();
            var plant_id = $(this).find(':selected').data('plant');
            
            // Set plant_id tersembunyi
            $('#plant_id').val(plant_id);
            
            var $tbody = $('#budgetTable tbody');

            // Kosongkan tabel dan tunjukkan loading
            $tbody.empty();
            if(!dept_id) {
                $tbody.append('<tr><td colspan="37" class="text-center text-muted font-italic py-4">Silakan pilih Departemen di atas untuk menampilkan daftar barang.</td></tr>');
                return;
            }

            $('#loadingMsg').show();
            $tbody.append('<tr><td colspan="37" class="text-center text-info font-italic py-4">Sedang memuat data dari database...</td></tr>');

            // AJAX Request ke endpoint PHP
            $.ajax({
                url: 'get_items_by_dept.php',
                type: 'GET',
                data: { dept_id: dept_id },
                dataType: 'json',
                success: function(data) {
                    $tbody.empty();
                    
                    if(data.length > 0) {
                        var html = '';
                        $.each(data, function(index, item) {
                            html += '<tr>';
                            
                            // Kolom Freeze Kiri (Info Barang, BUKAN DROPDOWN LAGI)
                            html += '<td class="freeze-col">';
                            html += '<input type="hidden" name="items['+index+'][item_code]" value="'+item.item_code+'">';
                            html += '<strong class="text-primary">' + item.item_code + '</strong><br>';
                            html += '<span>' + item.item_name + '</span> <em>(' + item.uom + ')</em>';
                            html += '</td>';
                            
                            // Looping 12 Bulan via JS
                            for(var m=1; m<=12; m++) {
                                html += '<td><input type="number" name="items['+index+'][months]['+m+'][qty]" class="calc-qty" data-month="'+m+'" value="0" min="0" step="any"></td>';
                                html += '<td><input type="number" name="items['+index+'][months]['+m+'][price]" class="calc-price" data-month="'+m+'" value="0" min="0" step="any"></td>';
                                html += '<td><input type="text" class="readonly-amount" data-month="'+m+'" value="0" readonly tabindex="-1"></td>';
                            }
                            
                            html += '</tr>';
                        });
                        $tbody.html(html);
                    } else {
                        $tbody.html('<tr><td colspan="37" class="text-center text-danger font-weight-bold py-4">Master Barang untuk departemen ini belum tersedia. Silakan input di menu Master Item terlebih dahulu.</td></tr>');
                    }
                },
                error: function() {
                    $tbody.empty();
                    $tbody.html('<tr><td colspan="37" class="text-center text-danger py-4">Gagal menghubungi server. Periksa koneksi jaringan.</td></tr>');
                },
                complete: function() {
                    $('#loadingMsg').hide();
                }
            });
        });

        // Perhitungan Real-time Amount
        $('#budgetTable').on('input', '.calc-qty, .calc-price', function() {
            var $row = $(this).closest('tr'); 
            var month = $(this).data('month'); 
            
            var qty = parseFloat($row.find('.calc-qty[data-month="' + month + '"]').val()) || 0;
            var price = parseFloat($row.find('.calc-price[data-month="' + month + '"]').val()) || 0;
            
            var amount = qty * price;
            $row.find('.readonly-amount[data-month="' + month + '"]').val(amount.toLocaleString('id-ID'));
        });
    });
</script>

</body>
</html>