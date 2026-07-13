<?php
require_once "../config/database_aging.php";

// 1. Tentukan tahun yang dipilih (default: tahun berjalan saat ini)
$current_year = (int)date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// 2. Query data berdasarkan tahun yang dipilih
$sql_chart = "
SELECT 
    BULAN, 
    ISNULL(AMOUNT_KONSUMSI, 0) AS AMOUNT_KONSUMSI, 
    ISNULL(SALES_AMOUNT_IDR, 0) AS SALES_AMOUNT_IDR
FROM dbo.T_MATERIAL_FINANCIAL_LOG
WHERE YEAR(BULAN) = ?
ORDER BY BULAN ASC
";

$stmt = q($sql_chart, [$selected_year]);

$labels = [];
$data_persentase = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format label bulan (contoh: "Jan 2026")
        $tgl = $row['BULAN'] instanceof DateTime ? $row['BULAN']->format('Y-m-d') : $row['BULAN'];
        $labels[] = date('M Y', strtotime($tgl));
        
        $konsumsi = (float)$row['AMOUNT_KONSUMSI'];
        $sales = (float)$row['SALES_AMOUNT_IDR'];
        
        // Hitung persentase rasio
        $persentase = 0;
        if ($sales > 0) {
            $persentase = ($konsumsi / $sales) * 100;
        }
        
        $data_persentase[] = round($persentase, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Grafik Rasio Material</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h4 class="mb-0">Rasio Material Terhadap Sales</h4>
            
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <label for="year" class="fw-bold text-secondary text-nowrap mb-0">Pilih Tahun:</label>
                <select name="year" id="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    // Menampilkan pilihan 5 tahun ke belakang hingga tahun ini
                    for ($y = $current_year; $y >= $current_year - 5; $y--) {
                        $selected = ($y == $selected_year) ? "selected" : "";
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
                <a href="process_material_log.php" class="btn btn-outline-secondary btn-sm text-nowrap">⬅ Kembali</a>
            </form>
        </div>
        
        <div class="card-body">
            <?php if (empty($labels)): ?>
                <div class="alert alert-info text-center my-4">
                    Belum ada data log financial yang tersimpan untuk tahun <b><?php echo $selected_year; ?></b>.
                </div>
            <?php else: ?>
                <div style="position: relative; width: 100%; height: 400px;">
                    <canvas id="ratioChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Ambil data dari PHP
    const labels = <?php echo json_encode($labels); ?>;
    const dataPersentase = <?php echo json_encode($data_persentase); ?>;
    
    // Custom Plugin untuk memunculkan angka di atas setiap batang grafik
    const chartLabelsPlugin = {
        id: 'chartLabelsPlugin',
        afterDatasetsDraw(chart, args, options) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = 'bold 11px Arial';
            ctx.fillStyle = '#212529'; // Warna teks angka (Dark Gray)
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';

            chart.data.datasets.forEach((dataset, i) => {
                const meta = chart.getDatasetMeta(i);
                meta.data.forEach((bar, index) => {
                    const dataVal = dataset.data[index];
                    // Tampilkan angka jika nilainya lebih dari 0
                    if (dataVal > 0) {
                        const labelText = dataVal + '%';
                        // bar.y - 5 artinya diletakkan 5 pixel di atas ujung batang grafik
                        ctx.fillText(labelText, bar.x, bar.y - 5);
                    }
                });
            });
            ctx.restore();
        }
    };

    const ctx = document.getElementById('ratioChart').getContext('2d');
    const ratioChart = new Chart(ctx, {
        type: 'bar', // Mengubah tipe grafik menjadi BATANG
        data: {
            labels: labels,
            datasets: [{
                label: 'Persentase Konsumsi / Sales',
                data: dataPersentase,
                backgroundColor: 'rgba(13, 110, 253, 0.85)', // Warna biru solid untuk batang
                borderColor: '#0d6efd',
                borderWidth: 1,
                barPercentage: 0.6, // Mengatur ketebalan batang agar proporsional
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.max(...dataPersentase) + 15, // Memberikan ruang di bagian atas agar angka tidak terpotong bingkai
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false // Menghilangkan garis grid vertikal agar tampilan bersih
                    }
                }
            }
        },
        plugins: [chartLabelsPlugin] // Daftarkan plugin angka di sini
    });
</script>

</body>
</html>