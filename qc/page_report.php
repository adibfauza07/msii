<?php
// FILE: page_report.php
if (!isset($conn) || $conn === false) {
    die("<div class='alert alert-danger'>Koneksi database tidak tersedia.</div>");
}

// Tangkap Filter
$from_date = isset($_REQUEST['from_date']) ? $_REQUEST['from_date'] : date('Y-m-01');
$to_date   = isset($_REQUEST['to_date']) ? $_REQUEST['to_date'] : date('Y-m-d');
$part_code = isset($_REQUEST['part_code']) ? $_REQUEST['part_code'] : '';
$customer  = isset($_REQUEST['customer']) ? $_REQUEST['customer'] : '';
$action    = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Query Filter Utama
$where = " WHERE QC_PROBLEM.TANGGAL BETWEEN ? AND ? ";
$params = array($from_date, $to_date);

if (!empty($part_code)) {
    $where .= " AND ITEM_CUSTINFO_VIEW.PART_CODE = ? ";
    $params[] = $part_code;
}
if (!empty($customer)) {
    $where .= " AND ITEM_CUSTINFO_VIEW.CUST_COMP = ? ";
    $params[] = $customer;
}

$sql = "SELECT 
            QC_PROBLEM.ID_PROBLEM,
            CONVERT(varchar, QC_PROBLEM.TANGGAL, 23) AS TANGGAL,
            QC_PROBLEM.SHIFT,
            ITEM_CUSTINFO_VIEW.PART_NO,
            ITEM_CUSTINFO_VIEW.PART_CODE,
            ITEM_CUSTINFO_VIEW.PART_NAME,
            ITEM_CUSTINFO_VIEW.CUST_COMP,
            QC_PROBLEM.PROBLEM,
            QC_PROBLEM.Penangan,
            QC_PROBLEM.Perbaikan,
            S.STATUS,
            CAST(QC_PROBLEM.Tempat_Foto AS varchar(255)) AS tempat_foto
        FROM dbo.QC_PROBLEM 
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON QC_PROBLEM.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID 
        LEFT OUTER JOIN dbo.STATUS AS S ON S.ID_STATUS = QC_PROBLEM.STATUS_ID 
        $where
        ORDER BY QC_PROBLEM.TANGGAL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
$rows = array();

// Variabel untuk menghitung persentase
$total_data = 0;
$count_open = 0;
$count_closed = 0;

if ($stmt !== false) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Hitung status Open dan Closed
        $status_text = strtoupper(trim($r['STATUS']));
        if ($status_text == 'OPEN') {
            $count_open++;
        } elseif ($status_text == 'CLOSED' || $status_text == 'CLOSE') {
            $count_closed++;
        }

        // Ambil PIC terkait
        $id_prob = $r['ID_PROBLEM'];
        $sql_pic = "SELECT PIC FROM dbo.QC_DIC_PIC WHERE ID_PROBLEM = ?";
        $stmt_pic = sqlsrv_query($conn, $sql_pic, array($id_prob));
        $pics = array();
        if ($stmt_pic) {
            while ($p = sqlsrv_fetch_array($stmt_pic, SQLSRV_FETCH_ASSOC)) {
                $pics[] = trim($p['PIC']);
            }
        }
        $r['PIC'] = implode(', ', $pics);
        $rows[] = $r;
    }
    $total_data = count($rows);
}

// Hitung Persentase Status
$pct_open = ($total_data > 0) ? round(($count_open / $total_data) * 100, 1) : 0;
$pct_closed = ($total_data > 0) ? round(($count_closed / $total_data) * 100, 1) : 0;

// JIKA TOMBOL EXPORT EXCEL DIKLIK
if ($action == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=QC_Problem_Report_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "<table border='1'>";
    echo "<thead>
            <tr style='background-color: #f2f2f2; font-weight: bold;'>
                <th>No</th>
                <th>Tanggal</th>
                <th>Shift</th>
                <th>Part Number</th>
                <th>Part Code</th>
                <th>Part Name</th>
                <th>Customer</th>
                <th>Problem</th>
                <th>Penanganan</th>
                <th>Perbaikan</th>
                <th>PIC</th>
                <th>Status</th>
                <th>Tempat Foto</th>
            </tr>
          </thead>";
    echo "<tbody>";
    $no = 1;
    foreach ($rows as $row) {
        echo "<tr>
                <td>{$no}</td>
                <td>{$row['TANGGAL']}</td>
                <td>{$row['SHIFT']}</td>
                <td>{$row['PART_NO']}</td>
                <td>{$row['PART_CODE']}</td>
                <td>{$row['PART_NAME']}</td>
                <td>{$row['CUST_COMP']}</td>
                <td>{$row['PROBLEM']}</td>
                <td>{$row['Penangan']}</td>
                <td>{$row['Perbaikan']}</td>
                <td>{$row['PIC']}</td>
                <td>{$row['STATUS']}</td>
                <td>{$row['tempat_foto']}</td>
              </tr>";
        $no++;
    }
    echo "</tbody>";
    echo "</table>";
    exit;
}
?>

<!-- Library jQuery UI untuk kompatibilitas browser lama -->
<!-- Pastikan jQuery standard (misal v1.12.4) sudah di-load di header utama aplikasi Anda -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<!-- CSS Khusus -->
<style>
    @media print {
        body { background: #fff !important; }
        .no-print { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header, .btn, form, nav, header, footer { display: none !important; }
        .table-responsive { max-height: none !important; overflow: visible !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        th, td { border: 1px solid #000 !important; padding: 4px !important; font-size: 10px !important; }
        .badge { color: #000 !important; background: none !important; border: none !important; font-weight: bold; }
        .print-title { display: block !important; text-align: center; margin-bottom: 15px; }
    }
    .print-title { display: none; }
    
    /* Style untuk Autocomplete Dropdown agar rapi */
    .ui-autocomplete {
        max-height: 200px;
        overflow-y: auto;
        overflow-x: hidden;
        font-size: 12px;
        z-index: 9999;
    }
</style>

<div class="container-fluid">
    <h4 class="mb-3 fw-bold no-print"><i class="bi bi-file-earmark-excel text-success"></i> QC Problem Report</h4>

    <!-- Judul yang hanya muncul saat di Print -->
    <div class="print-title">
        <h4>LAPORAN QC PROBLEM</h4>
        <p>Periode: <?php echo date('d-m-Y', strtotime($from_date)); ?> s/d <?php echo date('d-m-Y', strtotime($to_date)); ?></p>
    </div>

    <!-- FILTER CARD (Disembunyikan saat Print) -->
    <div class="card shadow-sm mb-3 no-print">
        <div class="card-header bg-secondary text-white py-2">
            <h6 class="mb-0"><i class="bi bi-filter"></i> Filter Laporan</h6>
        </div>
        <div class="card-body bg-light">
            <form method="GET" action="" id="formReport">
                <input type="hidden" name="page" value="report_qc">
                <input type="hidden" name="plant" value="<?php echo isset($active_plant) ? $active_plant : 'p1'; ?>">
                <input type="hidden" name="action" id="formAction" value="">

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">From Date</label>
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo $from_date; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">To Date</label>
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo $to_date; ?>" required>
                    </div>
                    <!-- Menggunakan Input Text Text untuk Kompatibilitas UI Autocomplete -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Part Number / Code</label>
                        <input type="text" name="part_code" id="filter_part" class="form-control form-control-sm" 
                               value="<?php echo htmlspecialchars($part_code); ?>" placeholder="Ketik untuk mencari Part...">
                    </div>
                    <!-- Menggunakan Input Text Text untuk Kompatibilitas UI Autocomplete -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Customer</label>
                        <input type="text" name="customer" id="filter_customer" class="form-control form-control-sm" 
                               value="<?php echo htmlspecialchars($customer); ?>" placeholder="Ketik untuk mencari Customer...">
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <div>
                        <!-- TOMBOL BUKA GRAFIK -->
                        <button type="button" class="btn btn-info btn-sm fw-bold px-3 text-white" onclick="openChart();">
                            <i class="bi bi-bar-chart-fill"></i> Lihat Grafik Top 5
                        </button>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" onclick="$('#formAction').val('');">
                            <i class="bi bi-search"></i> Tampilkan
                        </button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-4 ms-1" onclick="$('#formAction').val('excel');">
                            <i class="bi bi-file-earmark-excel"></i> Excel
                        </button>
                        <!-- TOMBOL PRINT -->
                        <button type="button" class="btn btn-warning btn-sm fw-bold px-4 ms-1 text-dark" onclick="window.print();">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- WIDGET PERSENTASE (Ditampilkan juga saat print agar rapi) -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="p-2 border rounded shadow-sm d-flex justify-content-around align-items-center" style="background-color:#fdfdfe;">
                <div class="text-center">
                    <span class="text-secondary fw-bold small">TOTAL DATA</span><br>
                    <span class="fs-5 fw-bold"><?php echo $total_data; ?></span>
                </div>
                <div class="text-center">
                    <span class="text-danger fw-bold small">OPEN</span><br>
                    <span class="fs-5 fw-bold text-danger"><?php echo $count_open; ?> <small>(<?php echo $pct_open; ?>%)</small></span>
                </div>
                <div class="text-center">
                    <span class="text-success fw-bold small">CLOSED</span><br>
                    <span class="fs-5 fw-bold text-success"><?php echo $count_closed; ?> <small>(<?php echo $pct_closed; ?>%)</small></span>
                </div>
            </div>
        </div>
    </div>

    <!-- PREVIEW TABLE -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center no-print">
            <h6 class="mb-0 fw-bold text-secondary">Hasil Pratinjau Data (Total: <?php echo count($rows); ?> Baris)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px;">
                <table class="table table-bordered table-striped table-hover mb-0" style="font-size: 12px;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Part Number</th>
                            <th>Part Code</th>
                            <th>Part Name</th>
                            <th>Customer</th>
                            <th>Problem</th>
                            <th>Penanganan</th>
                            <th>Perbaikan</th>
                            <th>PIC</th>
                            <th>Status</th>
                            <th>Tmpt Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): ?>
                            <?php $no = 1; foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $r['TANGGAL']; ?></td>
                                    <td><?php echo $r['SHIFT']; ?></td>
                                    <td><?php echo $r['PART_NO']; ?></td>
                                    <td><?php echo $r['PART_CODE']; ?></td>
                                    <td><?php echo $r['PART_NAME']; ?></td>
                                    <td><?php echo $r['CUST_COMP']; ?></td>
                                    <td><?php echo htmlspecialchars($r['PROBLEM']); ?></td>
                                    <td><?php echo htmlspecialchars($r['Penangan']); ?></td>
                                    <td><?php echo htmlspecialchars($r['Perbaikan']); ?></td>
                                    <td><?php echo htmlspecialchars($r['PIC']); ?></td>
                                    <td><span class="badge <?php echo ($r['STATUS']=='OPEN')?'bg-danger':'bg-success'; ?>"><?php echo $r['STATUS']; ?></span></td>
                                    <td><?php echo htmlspecialchars($r['tempat_foto']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center py-4 text-muted">Tidak ada data ditemukan pada rentang tanggal/filter tersebut.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi Buka Tab Baru Untuk Grafik Top 5
function openChart() {
    var fd = $('input[name="from_date"]').val();
    var td = $('input[name="to_date"]').val();
    var pc = $('#filter_part').val() || '';
    var ct = $('#filter_customer').val() || '';
    
    // Sesuaikan URL jika file berada di folder lain, misal: 'chart_problem.php'
    var url = 'chart_problem.php?from_date=' + fd + '&to_date=' + td + '&part_code=' + encodeURIComponent(pc) + '&customer=' + encodeURIComponent(ct);
    
    // Buka di tab baru
    window.open(url, '_blank');
}

$(document).ready(function() {
    // Autocomplete jQuery UI untuk Part
    $('#filter_part').autocomplete({
        minLength: 2,
        source: function(request, response) {
            $.ajax({
                url: 'ajax_get_item.php',
                dataType: 'json',
                data: { q: request.term },
                success: function(data) {
                    var results = [];
                    // Mengantisipasi format data dari select2 sebelumnya yang berbentuk data.results
                    var items = data.results ? data.results : data; 
                    
                    $.each(items, function(index, item) {
                        results.push({
                            label: item.part_no + ' | ' + item.part_code, // Teks yang muncul di dropdown list
                            value: item.part_code                         // Value yang masuk ke input box saat dipilih
                        });
                    });
                    response(results);
                }
            });
        }
    });

    // Autocomplete jQuery UI untuk Customer
    $('#filter_customer').autocomplete({
        minLength: 1,
        source: function(request, response) {
            $.ajax({
                url: 'ajax_get_customer.php',
                dataType: 'json',
                data: { q: request.term },
                success: function(data) {
                    var results = [];
                    // Mengantisipasi format data dari select2 sebelumnya yang berbentuk data.results
                    var items = data.results ? data.results : data;
                    
                    $.each(items, function(index, item) {
                        // Asumsi ajax customer mengembalikan key "text" atau "id" sama seperti format select2
                        // Jika JSON Anda mengembalikan key berbeda misal item.customer_name, silakan ganti
                        results.push({
                            label: item.text,  
                            value: item.text 
                        });
                    });
                    response(results);
                }
            });
        }
    });
});
</script>