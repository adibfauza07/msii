<?php
require_once __DIR__ . "/../config/database_ppic.php";

// Inisialisasi parameter filter
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); 
$end_date   = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');   
$cust_code  = isset($_POST['cust_code']) ? $_POST['cust_code'] : ''; 
$cust_name  = isset($_POST['cust_name']) ? $_POST['cust_name'] : ''; 

// =========================================================================
// HANDLER EXPORT EXCEL
// =========================================================================
if (isset($_POST['btn_export'])) {
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Report_Pack_InOut_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<thead>
            <tr>
                <th style="background-color: #f2f2f2;">No</th>
                <th style="background-color: #f2f2f2;">Date</th>
                <th style="background-color: #f2f2f2;">Customer</th>
                <th style="background-color: #f2f2f2;">Kode Pack</th>
                <th style="background-color: #f2f2f2;">Nama Pack</th>
                <th style="background-color: #f2f2f2;">Out (DQTY)</th>
                <th style="background-color: #f2f2f2;">In (RQTY)</th>
                <th style="background-color: #f2f2f2;">Balance</th>
            </tr>
          </thead>';
    echo '<tbody>';

    if ($conn) {
        $sql = "EXEC SP_PACK_IN_OUT @START_DATE = ?, @END_DATE = ?, @CUST_CODE = ?";
        $param_cust_code = ($cust_code === '') ? null : $cust_code;
        $params = array($start_date, $end_date, $param_cust_code);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt !== false) {
            $data_results = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data_results[] = $row;
            }

            // SORTING DATA (Customer -> Kode Pack -> Tanggal)
            usort($data_results, function($a, $b) {
                $custA = isset($a['CUST_CODE']) ? $a['CUST_CODE'] : '';
                $custB = isset($b['CUST_CODE']) ? $b['CUST_CODE'] : '';
                if ($custA !== $custB) return strcmp($custA, $custB);
                
                $packA = isset($a['PACK_CODE']) ? $a['PACK_CODE'] : '';
                $packB = isset($b['PACK_CODE']) ? $b['PACK_CODE'] : '';
                if ($packA !== $packB) return strcmp($packA, $packB);
                
                $timeA = 0; $timeB = 0;
                if (isset($a['PACK_DATE'])) {
                    $dA = DateTime::createFromFormat('d-m-Y', $a['PACK_DATE']);
                    if ($dA) $timeA = $dA->getTimestamp();
                }
                if (isset($b['PACK_DATE'])) {
                    $dB = DateTime::createFromFormat('d-m-Y', $b['PACK_DATE']);
                    if ($dB) $timeB = $dB->getTimestamp();
                }
                if ($timeA == $timeB) return 0;
                return ($timeA < $timeB) ? -1 : 1;
            });

            $no = 1;
            // Variabel penampung Total
            $sum_out = 0; $sum_in = 0; $sum_bal = 0;

            foreach ($data_results as $row) {
                $trxDate = isset($row['PACK_DATE']) ? $row['PACK_DATE'] : '-';
                $customer = (isset($row['CUST_CODE']) ? $row['CUST_CODE'] : '') . ' - ' . (isset($row['CUST_COMP']) ? $row['CUST_COMP'] : '');
                
                $out = isset($row['DQTY']) ? (float)$row['DQTY'] : 0;
                $in  = isset($row['RQTY']) ? (float)$row['RQTY'] : 0;
                $bal = isset($row['BALANCE']) ? (float)$row['BALANCE'] : 0;

                $sum_out += $out;
                $sum_in  += $in;
                $sum_bal += $bal;

                echo '<tr>';
                echo '<td>' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($trxDate) . '</td>';
                echo '<td>' . htmlspecialchars($customer) . '</td>';
                echo '<td>' . htmlspecialchars(isset($row['PACK_CODE']) ? $row['PACK_CODE'] : '-') . '</td>';
                echo '<td>' . htmlspecialchars(isset($row['PACK_NAME']) ? $row['PACK_NAME'] : '-') . '</td>';
                echo '<td>' . $out . '</td>';
                echo '<td>' . $in . '</td>';
                echo '<td>' . $bal . '</td>';
                echo '</tr>';
            }

            // Tampilkan Baris Grand Total di Excel
            if ($no > 1) {
                echo '<tr>';
                echo '<td colspan="5" style="text-align:right; font-weight:bold; background-color:#f2f2f2;">GRAND TOTAL</td>';
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $sum_out . '</td>';
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $sum_in . '</td>';
                echo '<td style="font-weight:bold; background-color:#f2f2f2;">' . $sum_bal . '</td>';
                echo '</tr>';
            }
        }
    }
    
    echo '</tbody></table>';
    exit(); 
}
// =========================================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pack In/Out - Filter</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css">

    <style>
        body { background-color: #f4f6f9; font-size: 13px; }
        .table-responsive { max-height: 500px; }
        .table th { position: sticky; top: 0; background-color: #f4f6f9; z-index: 10; box-shadow: inset 0 -1px 0 #dee2e6;}
        .table tfoot th { position: sticky; bottom: 0; background-color: #e9ecef; z-index: 10; box-shadow: inset 0 1px 0 #dee2e6;}
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container-fluid">
            <span class="navbar-brand">
                <span class="brand-text font-weight-bold">PPIC System</span>
            </span>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Data Pack In/Out</h1>
                    </div>
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
                                        <label class="text-sm">Start Date</label>
                                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group mb-2">
                                        <label class="text-sm">End Date</label>
                                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
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
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-group mb-2 w-100">
                                        <button type="submit" name="btn_search" class="btn btn-sm btn-primary mr-1">
                                            <i class="fas fa-search"></i> Cari Data
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
                // ==========================================
                // 1. PROSES AMBIL DATA & GROUPING
                // ==========================================
                $data_results = array();
                $summary_customer = array(); 
                $db_error = false;
                $db_error_msg = "";

                if ($conn) {
                    $sql = "EXEC SP_PACK_IN_OUT @START_DATE = ?, @END_DATE = ?, @CUST_CODE = ?";
                    $param_cust_code = ($cust_code === '') ? null : $cust_code;
                    $params = array($start_date, $end_date, $param_cust_code);

                    $stmt = sqlsrv_query($conn, $sql, $params);

                    if ($stmt === false) {
                        $db_error = true;
                        $db_error_msg = print_r(sqlsrv_errors(), true);
                    } else {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $data_results[] = $row;

                            // Grouping per Customer untuk Total Minus
                            $c_name = (isset($row['CUST_CODE']) ? $row['CUST_CODE'] : '') . ' - ' . (isset($row['CUST_COMP']) ? $row['CUST_COMP'] : '');
                            $bal = isset($row['BALANCE']) ? (float)$row['BALANCE'] : 0;
                            
                            if (!isset($summary_customer[$c_name])) {
                                $summary_customer[$c_name] = 0;
                            }
                            $summary_customer[$c_name] += $bal;
                        }

                        // Mengurutkan summary dari Balance terkecil (paling minus) ke terbesar
                        asort($summary_customer);

                        // SORTING DATA UTAMA (Customer -> Kode Pack -> Tanggal)
                        usort($data_results, function($a, $b) {
                            $custA = isset($a['CUST_CODE']) ? $a['CUST_CODE'] : '';
                            $custB = isset($b['CUST_CODE']) ? $b['CUST_CODE'] : '';
                            if ($custA !== $custB) return strcmp($custA, $custB);
                            
                            $packA = isset($a['PACK_CODE']) ? $a['PACK_CODE'] : '';
                            $packB = isset($b['PACK_CODE']) ? $b['PACK_CODE'] : '';
                            if ($packA !== $packB) return strcmp($packA, $packB);
                            
                            $timeA = 0; $timeB = 0;
                            if (isset($a['PACK_DATE'])) {
                                $dA = DateTime::createFromFormat('d-m-Y', $a['PACK_DATE']);
                                if ($dA) $timeA = $dA->getTimestamp();
                            }
                            if (isset($b['PACK_DATE'])) {
                                $dB = DateTime::createFromFormat('d-m-Y', $b['PACK_DATE']);
                                if ($dB) $timeB = $dB->getTimestamp();
                            }
                            if ($timeA == $timeB) return 0;
                            return ($timeA < $timeB) ? -1 : 1;
                        });
                    }
                } else {
                    $db_error = true;
                    $db_error_msg = "Koneksi Database Gagal.";
                }
                ?>

                <!-- PANEL SUMMARY MINUS TERBESAR -->
                <?php if (!$db_error && !empty($summary_customer)): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-danger card-outline shadow-sm">
                            <div class="card-header py-2">
                                <h3 class="card-title text-sm"><i class="fas fa-arrow-down mr-1"></i> Top Minus Balance by Customer</h3>
                            </div>
                            <div class="card-body p-0 table-responsive" style="max-height: 200px;">
                                <table class="table table-sm table-striped table-hover m-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Customer</th>
                                            <th class="text-right">Total Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $has_minus = false;
                                        foreach ($summary_customer as $cust => $total_bal): 
                                            if ($total_bal < 0): 
                                                $has_minus = true;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cust); ?></td>
                                            <td class="text-right text-danger font-weight-bold"><?php echo number_format($total_bal, 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php 
                                            endif;
                                        endforeach; 

                                        if (!$has_minus) {
                                            echo '<tr><td colspan="2" class="text-center text-muted">Tidak ada customer dengan balance minus.</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TABEL DATA UTAMA -->
                <div class="card card-success card-outline shadow-sm">
                    <div class="card-header py-2">
                        <h3 class="card-title text-sm"><i class="fas fa-table mr-1"></i> Detail Hasil Pencarian</h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-bordered table-hover text-nowrap m-0">
                            <thead>
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Kode Pack</th>
                                    <th>Nama Pack</th>
                                    <th class="text-right">Out (DQTY)</th>
                                    <th class="text-right">In (RQTY)</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($db_error) {
                                    echo '<tr><td colspan="8" class="text-danger text-center">Kesalahan: ' . htmlspecialchars($db_error_msg) . '</td></tr>';
                                } else {
                                    $no = 1;
                                    $total_out = 0;
                                    $total_in  = 0;
                                    $total_bal = 0;

                                    if (empty($data_results)) {
                                        echo '<tr><td colspan="8" class="text-center text-muted font-italic">Tidak ada data ditemukan untuk rentang tanggal tersebut.</td></tr>';
                                    } else {
                                        foreach ($data_results as $row) {
                                            $trxDate = isset($row['PACK_DATE']) ? $row['PACK_DATE'] : '-';
                                            $customer = (isset($row['CUST_CODE']) ? $row['CUST_CODE'] : '') . ' - ' . (isset($row['CUST_COMP']) ? $row['CUST_COMP'] : '');
                                            
                                            $out = isset($row['DQTY']) ? (float)$row['DQTY'] : 0;
                                            $in  = isset($row['RQTY']) ? (float)$row['RQTY'] : 0;
                                            $bal = isset($row['BALANCE']) ? (float)$row['BALANCE'] : 0;

                                            $total_out += $out;
                                            $total_in  += $in;
                                            $total_bal += $bal;

                                            echo '<tr>';
                                            echo '<td class="text-center">' . $no++ . '</td>';
                                            echo '<td>' . htmlspecialchars($trxDate) . '</td>';
                                            echo '<td>' . htmlspecialchars($customer) . '</td>';
                                            echo '<td>' . htmlspecialchars(isset($row['PACK_CODE']) ? $row['PACK_CODE'] : '-') . '</td>';
                                            echo '<td>' . htmlspecialchars(isset($row['PACK_NAME']) ? $row['PACK_NAME'] : '-') . '</td>';
                                            echo '<td class="text-right font-weight-bold text-danger">' . $out . '</td>';
                                            echo '<td class="text-right font-weight-bold text-success">' . $in . '</td>';
                                            echo '<td class="text-right font-weight-bold">' . $bal . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                }
                                ?>
                            </tbody>
                            <?php if (isset($no) && $no > 1): ?>
                            <!-- BARIS GRAND TOTAL HTML -->
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right text-uppercase">Grand Total</th>
                                    <th class="text-right text-danger"><?php echo $total_out; ?></th>
                                    <th class="text-right text-success"><?php echo $total_in; ?></th>
                                    <th class="text-right"><?php echo $total_bal; ?></th>
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