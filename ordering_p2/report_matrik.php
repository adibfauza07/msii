<?php
require_once __DIR__ . "/../config/database_ordering.php"; 

// Inisialisasi parameter filter (Tahun)
$year       = isset($_POST['year']) ? $_POST['year'] : date('Y'); 
$cust_code  = isset($_POST['cust_code']) ? $_POST['cust_code'] : ''; 
$cust_name  = isset($_POST['cust_name']) ? $_POST['cust_name'] : ''; 

// Start date cukup diset ke 1 Januari tahun yang dipilih, SP akan otomatis membaca tahunnya
$start_date = $year . '-01-01';

$months_name = array(
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
);

// =========================================================================
// FUNGSI MENGAMBIL DATA DARI SP PIVOT
// =========================================================================
function getMatrixData($conn, $start_date, $cust_code) {
    $matrix = array();
    
    // Inisialisasi array Grand Total
    $grand_total = array('TOTAL_OUT' => 0, 'TOTAL_IN' => 0, 'TOTAL_BAL' => 0);
    $m_keys = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
    foreach ($m_keys as $m) {
        $grand_total[$m.'_OUT'] = 0;
        $grand_total[$m.'_IN']  = 0;
        $grand_total[$m.'_BAL'] = 0;
    }

    // Panggil SP PIVOT yang baru
    $sql = "EXEC SP_PACK_IN_OUT_PIVOT_YEAR @START_DATE = ?, @CUST_CODE = ?";
    $param_cust_code = ($cust_code === '') ? null : $cust_code;
    $params = array($start_date, $param_cust_code);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $matrix[] = $row;
            
            // Kalkulasi Grand Total per kolom
            foreach ($m_keys as $m) {
                $grand_total[$m.'_OUT'] += isset($row[$m.'_OUT']) ? (float)$row[$m.'_OUT'] : 0;
                $grand_total[$m.'_IN']  += isset($row[$m.'_IN'])  ? (float)$row[$m.'_IN']  : 0;
                $grand_total[$m.'_BAL'] += isset($row[$m.'_BAL']) ? (float)$row[$m.'_BAL'] : 0;
            }
            $grand_total['TOTAL_OUT'] += isset($row['TOTAL_OUT']) ? (float)$row['TOTAL_OUT'] : 0;
            $grand_total['TOTAL_IN']  += isset($row['TOTAL_IN'])  ? (float)$row['TOTAL_IN']  : 0;
            $grand_total['TOTAL_BAL'] += isset($row['TOTAL_BAL']) ? (float)$row['TOTAL_BAL'] : 0;
        }
    }
    
    return array('data' => $matrix, 'grand_total' => $grand_total, 'error' => ($stmt === false));
}

// Fungsi kecil untuk format angka nol menjadi strip (-)
function formatVal($val) {
    $val = (float)$val;
    return ($val == 0) ? '-' : $val;
}

// =========================================================================
// HANDLER EXPORT EXCEL
// =========================================================================
if (isset($_POST['btn_export'])) {
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Matrik_Pack_InOut_" . $year . "_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    if ($conn) {
        $result = getMatrixData($conn, $start_date, $cust_code);
        $data_matrix = $result['data'];
        $grand_total = $result['grand_total'];

        echo '<table border="1"><thead><tr>
                <th rowspan="2" style="background-color: #d9edf7;">No</th>
                <th rowspan="2" style="background-color: #d9edf7;">Customer</th>
                <th rowspan="2" style="background-color: #d9edf7;">Kode Pack</th>
                <th rowspan="2" style="background-color: #d9edf7;">Pack Desk</th>';
        for ($i = 1; $i <= 12; $i++) {
            echo '<th colspan="3" style="background-color: #d9edf7; text-align:center;">' . $months_name[$i] . '</th>';
        }
        echo '<th colspan="3" style="background-color: #fcf8e3; text-align:center;">TOTAL ' . $year . '</th></tr><tr>';
        for ($i = 1; $i <= 13; $i++) {
            $bg = ($i == 13) ? '#fcf8e3' : '#f2f2f2';
            echo '<th style="background-color: '.$bg.';">Out</th><th style="background-color: '.$bg.';">In</th><th style="background-color: '.$bg.';">Bal</th>';
        }
        echo '</tr></thead><tbody>';

        if (!empty($data_matrix)) {
            $no = 1;
            foreach ($data_matrix as $row) {
                echo '<tr>';
                echo '<td>' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars((isset($row['CUST_CODE'])?$row['CUST_CODE']:'') . ' - ' . (isset($row['CUST_COMP'])?$row['CUST_COMP']:'')) . '</td>';
                echo '<td>' . htmlspecialchars(isset($row['PACK_CODE'])?$row['PACK_CODE']:'-') . '</td>';
                // Mengambil dari kolom PACK_DESC di SQL
                echo '<td>' . htmlspecialchars(isset($row['PACK_DESC'])?$row['PACK_DESC']:'-') . '</td>';
                
                for ($i = 1; $i <= 12; $i++) {
                    $m = $months_name[$i];
                    echo '<td>' . (isset($row[$m.'_OUT']) ? $row[$m.'_OUT'] : 0) . '</td>';
                    echo '<td>' . (isset($row[$m.'_IN'])  ? $row[$m.'_IN']  : 0) . '</td>';
                    echo '<td>' . (isset($row[$m.'_BAL']) ? $row[$m.'_BAL'] : 0) . '</td>';
                }
                echo '<td style="font-weight:bold;">' . (isset($row['TOTAL_OUT']) ? $row['TOTAL_OUT'] : 0) . '</td>';
                echo '<td style="font-weight:bold;">' . (isset($row['TOTAL_IN'])  ? $row['TOTAL_IN']  : 0) . '</td>';
                echo '<td style="font-weight:bold;">' . (isset($row['TOTAL_BAL']) ? $row['TOTAL_BAL'] : 0) . '</td>';
                echo '</tr>';
            }

            // GRAND TOTAL EXCEL
            echo '<tr><td colspan="4" style="text-align:right; font-weight:bold; background-color:#d9edf7;">GRAND TOTAL</td>';
            for ($i = 1; $i <= 12; $i++) {
                $m = $months_name[$i];
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $grand_total[$m.'_OUT'] . '</td>';
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $grand_total[$m.'_IN'] . '</td>';
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $grand_total[$m.'_BAL'] . '</td>';
            }
            echo '<td style="font-weight:bold; background-color:#fcf8e3;">' . $grand_total['TOTAL_OUT'] . '</td>';
            echo '<td style="font-weight:bold; background-color:#fcf8e3;">' . $grand_total['TOTAL_IN'] . '</td>';
            echo '<td style="font-weight:bold; background-color:#fcf8e3;">' . $grand_total['TOTAL_BAL'] . '</td></tr>';
        } else {
            echo '<tr><td colspan="43" style="text-align:center;">Tidak ada data ditemukan.</td></tr>';
        }
        echo '</tbody></table>';
    }
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Matrik Tahunan Pack In/Out</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css">

    <style>
        body { background-color: #f4f6f9; font-size: 12px; } 
        .table-responsive { max-height: 600px; overflow-x: auto; }
        .table th { position: sticky; background-color: #f4f6f9; z-index: 10; box-shadow: inset 0 -1px 0 #dee2e6; text-align: center; vertical-align: middle !important; }
        .table thead tr:nth-child(1) th { top: 0; }
        .table thead tr:nth-child(2) th { top: 35px; } 
        .table tfoot th { position: sticky; bottom: 0; background-color: #e9ecef; z-index: 10; box-shadow: inset 0 1px 0 #dee2e6; }
        
        .col-out { color: #dc3545; }
        .col-in { color: #28a745; }
        .col-bal { font-weight: bold; }
        .bg-total { background-color: #fff3cd !important; }
        
        .min-w-cust { min-width: 250px; }
        .min-w-pack { min-width: 150px; }
        .min-w-num { min-width: 50px; }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container-fluid">
            <span class="navbar-brand"><span class="brand-text font-weight-bold">PPIC System</span></span>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0 text-dark">Matrik Tahunan Pack In/Out</h1></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <!-- PANEL FILTER -->
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header py-2">
                        <h3 class="card-title text-sm"><i class="fas fa-filter mr-1"></i> Filter Data</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group mb-2">
                                        <label class="text-sm">Tahun</label>
                                        <select name="year" class="form-control form-control-sm">
                                            <?php 
                                            $current_year = date('Y');
                                            for ($y = $current_year - 3; $y <= $current_year + 1; $y++) {
                                                $selected = ($y == $year) ? 'selected' : '';
                                                echo "<option value='$y' $selected>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group mb-2">
                                        <label class="text-sm">Customer</label>
                                        <select name="cust_code" class="form-control form-control-sm select2-customer">
                                            <?php if ($cust_code != ''): ?>
                                                <option value="<?php echo htmlspecialchars($cust_code); ?>" selected>
                                                    <?php echo htmlspecialchars($cust_name); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                        <input type="hidden" name="cust_name" id="cust_name_hidden" value="<?php echo htmlspecialchars($cust_name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-5 d-flex align-items-end">
                                    <div class="form-group mb-2 w-100">
                                        <button type="submit" name="btn_search" class="btn btn-sm btn-primary mr-1">
                                            <i class="fas fa-search"></i> Generate Matrik
                                        </button>
                                        <button type="submit" name="btn_export" class="btn btn-sm btn-success" formtarget="_blank">
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- PANEL RESULT -->
                <?php if (isset($_POST['btn_search'])): ?>

                <?php
                $db_error = false;
                $db_error_msg = "";
                $data_matrix = array();
                $grand_total = array();

                if ($conn) {
                    $result = getMatrixData($conn, $start_date, $cust_code);
                    $data_matrix = $result['data'];
                    $grand_total = $result['grand_total'];
                    if ($result['error']) {
                        $db_error = true;
                        $db_error_msg = "Error SQL Server: " . print_r(sqlsrv_errors(), true);
                    }
                } else {
                    $db_error = true;
                    $db_error_msg = "Koneksi Database Gagal.";
                }
                ?>

                <!-- TABEL DATA MATRIK -->
                <div class="card card-success card-outline shadow-sm">
                    <div class="card-header py-2">
                        <h3 class="card-title text-sm"><i class="fas fa-table mr-1"></i> Matrik In/Out Tahun <?php echo $year; ?></h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-bordered table-hover text-nowrap m-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle">No</th>
                                    <th rowspan="2" class="align-middle min-w-cust">Customer</th>
                                    <th rowspan="2" class="align-middle min-w-pack">Kode Pack</th>
                                    <th rowspan="2" class="align-middle min-w-pack">Pack Desk</th>
                                    <?php for ($i=1; $i<=12; $i++): ?>
                                        <th colspan="3" class="bg-light"><?php echo $months_name[$i]; ?></th>
                                    <?php endfor; ?>
                                    <th colspan="3" class="bg-total">TOTAL <?php echo $year; ?></th>
                                </tr>
                                <tr>
                                    <?php for ($i=1; $i<=13; $i++): 
                                        $bg_class = ($i == 13) ? 'bg-total' : 'bg-light';
                                    ?>
                                        <th class="min-w-num <?php echo $bg_class; ?>"><small>Out</small></th>
                                        <th class="min-w-num <?php echo $bg_class; ?>"><small>In</small></th>
                                        <th class="min-w-num <?php echo $bg_class; ?>"><small>Bal</small></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($db_error) {
                                    echo '<tr><td colspan="43" class="text-danger text-center font-weight-bold">' . htmlspecialchars($db_error_msg) . '</td></tr>';
                                } else {
                                    if (empty($data_matrix)) {
                                        echo '<tr><td colspan="43" class="text-center text-muted font-italic">Tidak ada transaksi ditemukan pada tahun ' . $year . '.</td></tr>';
                                    } else {
                                        $no = 1;
                                        foreach ($data_matrix as $row) {
                                            echo '<tr>';
                                            echo '<td class="text-center">' . $no++ . '</td>';
                                            echo '<td>' . htmlspecialchars((isset($row['CUST_CODE']) ? $row['CUST_CODE'] : '') . ' - ' . (isset($row['CUST_COMP']) ? $row['CUST_COMP'] : '')) . '</td>';
                                            echo '<td>' . htmlspecialchars(isset($row['PACK_CODE']) ? $row['PACK_CODE'] : '-') . '</td>';
                                            
                                            // Mengambil dari kolom PACK_DESC di SQL
                                            echo '<td>' . htmlspecialchars(isset($row['PACK_DESC']) ? $row['PACK_DESC'] : '-') . '</td>';
                                            
                                            // Loop untuk 12 Bulan (Panggil Kolom Langsung dari SQL Pivot)
                                            for ($i = 1; $i <= 12; $i++) {
                                                $m = $months_name[$i];
                                                $val_out = isset($row[$m.'_OUT']) ? $row[$m.'_OUT'] : 0;
                                                $val_in  = isset($row[$m.'_IN'])  ? $row[$m.'_IN']  : 0;
                                                $val_bal = isset($row[$m.'_BAL']) ? $row[$m.'_BAL'] : 0;

                                                echo '<td class="text-right col-out">' . formatVal($val_out) . '</td>';
                                                echo '<td class="text-right col-in">'  . formatVal($val_in)  . '</td>';
                                                echo '<td class="text-right col-bal">' . formatVal($val_bal) . '</td>';
                                            }
                                            
                                            // Kolom Total 1 Tahun
                                            echo '<td class="text-right bg-total col-out font-weight-bold">' . formatVal(isset($row['TOTAL_OUT']) ? $row['TOTAL_OUT'] : 0) . '</td>';
                                            echo '<td class="text-right bg-total col-in font-weight-bold">'  . formatVal(isset($row['TOTAL_IN'])  ? $row['TOTAL_IN']  : 0) . '</td>';
                                            echo '<td class="text-right bg-total col-bal font-weight-bold text-dark">' . formatVal(isset($row['TOTAL_BAL']) ? $row['TOTAL_BAL'] : 0) . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                }
                                ?>
                            </tbody>
                            
                            <?php if (!empty($data_matrix) && !$db_error): ?>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right text-uppercase align-middle">Grand Total</th>
                                    <?php for ($i = 1; $i <= 12; $i++): 
                                        $m = $months_name[$i];
                                    ?>
                                        <th class="text-right col-out"><?php echo formatVal($grand_total[$m.'_OUT']); ?></th>
                                        <th class="text-right col-in"><?php echo formatVal($grand_total[$m.'_IN']); ?></th>
                                        <th class="text-right col-bal text-dark"><?php echo formatVal($grand_total[$m.'_BAL']); ?></th>
                                    <?php endfor; ?>
                                    <th class="text-right bg-total col-out"><?php echo formatVal($grand_total['TOTAL_OUT']); ?></th>
                                    <th class="text-right bg-total col-in"><?php echo formatVal($grand_total['TOTAL_IN']); ?></th>
                                    <th class="text-right bg-total text-dark font-weight-bold"><?php echo formatVal($grand_total['TOTAL_BAL']); ?></th>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-customer').select2({
        theme: 'bootstrap4',
        placeholder: '-- Semua Customer --',
        allowClear: true,
        ajax: {
            url: 'ajax_customer_autocomplete.php', 
            type: 'POST', 
            dataType: 'json',
            delay: 250, 
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return {
                    results: $.map(data, function (item) {
                        return {
                            id: item.CUST_CODE, 
                            text: item.CUST_CODE + ' - ' + item.CUST_COMP
                        }
                    })
                };
            },
            cache: true
        }
    });

    $('.select2-customer').on('select2:select', function (e) {
        var data = e.params.data;
        $('#cust_name_hidden').val(data.text);
    });

    $('.select2-customer').on('select2:unselect', function (e) {
        $('#cust_name_hidden').val('');
    });
});
</script>
</body>
</html>