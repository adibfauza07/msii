<?php
// FILE: chart_problem.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Memanggil Koneksi
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';
if(file_exists($db_path)) { require_once $db_path; }
if (!isset($conn) || $conn === false) { @include "koneksi.php"; }

// =================================================================
// 1. AJAX HANDLER: MENGAMBIL DETAIL PROBLEM KETIKA BAR DIKLIK
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_details') {
    header('Content-Type: application/json');
    if (!isset($conn) || $conn === false) {
        echo json_encode(array('status' => 'error', 'message' => 'Koneksi database terputus.'));
        exit;
    }

    $problem_clicked = isset($_POST['problem']) ? $_POST['problem'] : '';
    $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '';
    $to_date   = isset($_POST['to_date']) ? $_POST['to_date'] : '';
    $part_code = isset($_POST['part_code']) ? $_POST['part_code'] : '';
    $customer  = isset($_POST['customer']) ? $_POST['customer'] : '';

    $where = " WHERE QC_PROBLEM.TANGGAL BETWEEN ? AND ? AND QC_PROBLEM.PROBLEM = ? ";
    $params = array($from_date, $to_date, $problem_clicked);

    if (!empty($part_code)) {
        $where .= " AND ITEM_CUSTINFO_VIEW.PART_CODE = ? ";
        $params[] = $part_code;
    }
    if (!empty($customer)) {
        $where .= " AND ITEM_CUSTINFO_VIEW.CUST_COMP = ? ";
        $params[] = $customer;
    }

    $sql_det = "SELECT 
                    CONVERT(varchar, QC_PROBLEM.TANGGAL, 23) AS TANGGAL,
                    QC_PROBLEM.SHIFT,
                    ITEM_CUSTINFO_VIEW.PART_NO,
                    ITEM_CUSTINFO_VIEW.PART_NAME,
                    ITEM_CUSTINFO_VIEW.CUST_COMP,
                    S.STATUS
                FROM dbo.QC_PROBLEM 
                INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON QC_PROBLEM.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID 
                LEFT OUTER JOIN dbo.STATUS AS S ON S.ID_STATUS = QC_PROBLEM.STATUS_ID 
                $where
                ORDER BY QC_PROBLEM.TANGGAL DESC";

    $stmt_det = sqlsrv_query($conn, $sql_det, $params);
    $html = "";
    $no = 1;

    if ($stmt_det !== false) {
        while ($r = sqlsrv_fetch_array($stmt_det, SQLSRV_FETCH_ASSOC)) {
            $status_val = trim($r['STATUS']);
            $badge = ($status_val == 'OPEN') ? 'bg-danger' : 'bg-success';
            $html .= "<tr>";
            $html .= "<td class='text-center'>{$no}</td>";
            $html .= "<td>{$r['TANGGAL']}</td>";
            $html .= "<td class='text-center'>{$r['SHIFT']}</td>";
            $html .= "<td>{$r['PART_NO']}</td>";
            $html .= "<td>{$r['PART_NAME']}</td>";
            $html .= "<td>{$r['CUST_COMP']}</td>";
            $html .= "<td class='text-center'><span class='badge {$badge}'>{$status_val}</span></td>";
            $html .= "</tr>";
            $no++;
        }
    }

    if ($html == "") {
        $html = "<tr><td colspan='7' class='text-center text-muted py-3'>Tidak ada detail item yang ditemukan.</td></tr>";
    }

    echo json_encode(array('status' => 'success', 'html' => $html, 'problem' => htmlspecialchars($problem_clicked)));
    exit;
}
// =================================================================

// Cek Koneksi Halaman Utama
if (!isset($conn) || $conn === false) {
    die("<div style='color:red; text-align:center; padding:50px;'>Koneksi database tidak tersedia.</div>");
}

// Tangkap Filter GET
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$part_code = isset($_GET['part_code']) ? $_GET['part_code'] : '';
$customer  = isset($_GET['customer']) ? $_GET['customer'] : '';

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

// 2. HITUNG GRAND TOTAL UNTUK PERSENTASE (Semua problem, bukan cuma top 5)
$sql_total = "SELECT COUNT(*) AS GRAND_TOTAL FROM dbo.QC_PROBLEM INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON QC_PROBLEM.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID $where";
$stmt_tot = sqlsrv_query($conn, $sql_total, $params);
$grand_total = 1; // Default 1 mencegah division by zero
if ($stmt_tot !== false && $row_tot = sqlsrv_fetch_array($stmt_tot, SQLSRV_FETCH_ASSOC)) {
    $grand_total = ($row_tot['GRAND_TOTAL'] > 0) ? $row_tot['GRAND_TOTAL'] : 1;
}
$total_aktual = ($grand_total == 1 && empty($row_tot['GRAND_TOTAL'])) ? 0 : $grand_total;

// Susun Query untuk Top 5 Problem
$sql = "SELECT TOP 5 
            QC_PROBLEM.PROBLEM, 
            COUNT(*) AS TOTAL 
        FROM dbo.QC_PROBLEM 
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON QC_PROBLEM.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID 
        $where 
        GROUP BY QC_PROBLEM.PROBLEM 
        ORDER BY TOTAL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

// Format Data untuk JavaScript Chart.js
$labels_truncated = array();
$labels_full = array();
$data_counts = array();

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $prob_full = trim($row['PROBLEM']);
        $prob_trunc = (strlen($prob_full) > 25) ? substr($prob_full, 0, 25) . '...' : $prob_full;
        
        $labels_full[] = $prob_full; 
        $labels_truncated[] = $prob_trunc; 
        $data_counts[] = $row['TOTAL'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top 5 QC Problem</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js dan DataLabels Plugin untuk Persentase -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <!-- jQuery (Wajib untuk AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; }
        .chart-container { position: relative; height: 50vh; width: 100%; margin-bottom: 30px; }
        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center no-print">
            <h5 class="mb-0 fw-bold">Grafik 5 Besar QC Problem Terbanyak</h5>
            <button class="btn btn-light btn-sm fw-bold" onclick="window.print()">Print Grafik</button>
        </div>
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="fw-bold">TOP 5 QC PROBLEM</h4>
                <p class="text-muted mb-0">Periode: <?php echo date('d-m-Y', strtotime($from_date)); ?> s/d <?php echo date('d-m-Y', strtotime($to_date)); ?></p>
                <div class="mt-2">
                    <span class="badge bg-dark">Total Semua Problem: <?php echo $total_aktual; ?> Kejadian</span>
                    <?php if(!empty($customer)) echo "<span class='badge bg-secondary ms-1'>Cust: " . htmlspecialchars($customer) . "</span>"; ?>
                    <?php if(!empty($part_code)) echo "<span class='badge bg-secondary ms-1'>Part: " . htmlspecialchars($part_code) . "</span>"; ?>
                </div>
                <p class="small text-info mt-2 no-print"><i class="bi bi-info-circle"></i> <em>Klik pada batang grafik (bar) di bawah untuk melihat rincian item.</em></p>
            </div>
            
            <?php if (count($labels_full) > 0): ?>
                <div class="chart-container">
                    <canvas id="problemChart"></canvas>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    Tidak ada data ditemukan pada rentang filter ini.
                </div>
            <?php endif; ?>
            
            <div class="no-print text-center">
                <button class="btn btn-outline-secondary px-4" onclick="window.close();">Tutup Halaman</button>
            </div>
        </div>
    </div>

    <!-- AREA DETAIL ITEM (Muncul Setelah Grafik Diklik) -->
    <div id="detailContainer" class="card shadow-sm border-top border-info border-3 no-print" style="display:none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h5 class="fw-bold text-info mb-0">
                    Detail Item: <span id="detailProblemName" class="text-dark"></span>
                </h5>
                <button class="btn btn-sm btn-close" onclick="$('#detailContainer').slideUp();"></button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm" style="font-size: 12px;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Tanggal</th>
                            <th width="8%">Shift</th>
                            <th width="15%">Part Number</th>
                            <th width="25%">Part Name</th>
                            <th width="25%">Customer</th>
                            <th width="12%">Status</th>
                        </tr>
                    </thead>
                    <tbody id="detailTableBody">
                        <!-- Data akan di-load via AJAX ke sini -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (count($labels_full) > 0): ?>
<script>
    // Daftarkan plugin datalabels
    Chart.register(ChartDataLabels);

    var chartLabelsTruncated = <?php echo json_encode($labels_truncated); ?>;
    var chartLabelsFull = <?php echo json_encode($labels_full); ?>;
    var chartData = <?php echo json_encode($data_counts); ?>;
    var grandTotal = <?php echo $grand_total; ?>; // Variabel dari PHP

    var ctx = document.getElementById('problemChart').getContext('2d');
    var problemChart = new Chart(ctx, {
        type: 'bar', 
        data: {
            labels: chartLabelsTruncated,
            datasets: [{
                label: 'Jumlah Kejadian',
                data: chartData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 30 // Beri jarak di atas bar agar text presentase tidak terpotong
                }
            },
            onClick: function(event, activeElements) {
                if (activeElements.length > 0) {
                    var dataIndex = activeElements[0].index;
                    var fullProblemName = chartLabelsFull[dataIndex];
                    loadProblemDetails(fullProblemName);
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false },
                // Konfigurasi Label Text (Angka & Persentase) di atas Bar
                datalabels: {
                    anchor: 'end',
                    align: 'top',
                    color: '#333',
                    font: {
                        weight: 'bold',
                        size: 13
                    },
                    formatter: function(value, context) {
                        // Rumus hitung persentase terhadap Total Keseluruhan Data
                        var percentage = ((value / grandTotal) * 100).toFixed(1) + '%';
                        return value + ' (' + percentage + ')';
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            // Menampilkan nama problem utuh (tidak dipotong) saat dihover
                            return chartLabelsFull[context[0].dataIndex];
                        },
                        label: function(context) { 
                            return ' Total: ' + context.parsed.y + ' Kejadian (Klik untuk melihat detail)'; 
                        }
                    }
                }
            }
        }
    });

    // Fungsi AJAX Untuk Menarik Detail ke Tabel
    function loadProblemDetails(problemName) {
        var fd = '<?php echo $from_date; ?>';
        var td = '<?php echo $to_date; ?>';
        var pc = '<?php echo addslashes($part_code); ?>';
        var ct = '<?php echo addslashes($customer); ?>';

        $('#detailProblemName').text(problemName);
        $('#detailTableBody').html('<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> Mengambil data...</td></tr>');
        $('#detailContainer').fadeIn();

        $('html, body').animate({
            scrollTop: $("#detailContainer").offset().top - 20
        }, 500);

        $.ajax({
            url: '', 
            type: 'POST',
            data: {
                ajax_action: 'get_details',
                problem: problemName,
                from_date: fd,
                to_date: td,
                part_code: pc,
                customer: ct
            },
            dataType: 'json',
            success: function(res) {
                if (res.status == 'success') {
                    $('#detailTableBody').hide().html(res.html).fadeIn('fast');
                } else {
                    $('#detailTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error: ' + res.message + '</td></tr>');
                }
            },
            error: function() {
                $('#detailTableBody').html('<tr><td colspan="7" class="text-center text-danger">Gagal menghubungi server.</td></tr>');
            }
        });
    }
</script>
<?php endif; ?>

</body>
</html>