<?php
// Include koneksi database Anda
require_once dirname(__DIR__) . "/config/db_plant2.php";

// Tangkap filter supplier dari form
$sup_code = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
$sup_name = isset($_GET['sup_name']) ? trim($_GET['sup_name']) : '';

// Susun query dasar
$params = array();
$whereClause = " WHERE (dbo.SUP_ITEM_QUO.SUP_CODE IS NOT NULL) ";

// Jika ada filter yang dikirim
if (!empty($sup_code)) {
    $whereClause .= " AND dbo.SUP_ITEM_QUO.SUP_CODE = ? ";
    $params[] = $sup_code;
}

$sql = "SELECT TOP (100) PERCENT 
            dbo.SUP_ITEM_QUO.SUP_CODE, 
            dbo.SUP_ITEM_QUO.SUP_COMP, 
            dbo.SUP_ITEM_QUO.SUP_ID, 
            dbo.SUP_ITEM_QUO.ITEM_ID, 
            dbo.SUP_ITEM_QUO.ITEM_CODE, 
            dbo.SUP_ITEM_QUO.ITEM_NAME, 
            dbo.SUP_ITEM_QUO.ITTY_CODE, 
            dbo.ITTY.ITTY_DESC, 
            dbo.SUP_ITEM_QUO.CURR_CODE, 
            dbo.SUP_ITEM_QUO.QUO_ID, 
            dbo.QUOT_DETAIL.QUOD_PRICE, 
            dbo.QUOT_DETAIL.QUOD_UNIT, 
            dbo.QUOT_DETAIL.QUOD_MINQTY, 
            dbo.QUOTATION.QUO_NO, 
            dbo.QUOTATION.QUO_DATE, 
            dbo.QUOTATION.QUO_EFFDATE,
            dbo.Mat_Maker.MAKER
        FROM dbo.SUP_ITEM_QUO 
        INNER JOIN dbo.ITTY ON dbo.SUP_ITEM_QUO.ITTY_CODE = dbo.ITTY.ITTY_CODE 
        LEFT OUTER JOIN dbo.QUOT_DETAIL ON dbo.SUP_ITEM_QUO.QUO_ID = dbo.QUOT_DETAIL.QUO_ID AND dbo.SUP_ITEM_QUO.ITEM_ID = dbo.QUOT_DETAIL.ITEM_ID 
        LEFT OUTER JOIN dbo.QUOTATION ON dbo.SUP_ITEM_QUO.QUO_ID = dbo.QUOTATION.QUO_ID
        LEFT OUTER JOIN dbo.Mat_Maker ON dbo.SUP_ITEM_QUO.ITEM_CODE = dbo.Mat_Maker.MAT_CODE
        $whereClause
        ORDER BY dbo.SUP_ITEM_QUO.SUP_CODE, dbo.SUP_ITEM_QUO.ITTY_CODE, dbo.SUP_ITEM_QUO.ITEM_CODE";

// Eksekusi query
$stmt = sqlsrv_query($conn, $sql, $params);

// Pindahkan data ke array untuk mempermudah looping di HTML
$results = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Variabel untuk melacak perubahan group
$current_sup = '';
$current_itty = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Pricelist (All Items)</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery UI CSS (untuk Autocomplete) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    
    <style>
        /* Pengaturan Cetak A4 Portrait */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-size: 12px;
            color: #000;
        }
        /* Custom Table Styling */
        .table-report th {
            border-top: 2px solid #000 !important;
            border-bottom: 2px solid #000 !important;
            border-left: none;
            border-right: none;
            font-weight: bold;
        }
        .table-report td {
            border: none;
            padding: 4px 8px;
            vertical-align: top;
        }
        .group-sup {
            font-weight: bold;
            font-style: italic;
            font-size: 14px;
            padding-top: 20px !important;
            border-bottom: 1px solid #ddd;
        }
        .group-itty {
            font-weight: bold;
            padding-top: 10px !important;
        }
        /* Memperbaiki tampilan Autocomplete jQuery UI di Bootstrap */
        .ui-autocomplete {
            z-index: 1050;
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid bg-white p-4 my-3 shadow-sm" style="max-width: 1200px;">
    
    <!-- Area Filter (Sembunyi saat diprint berkat class d-print-none) -->
    <div class="card mb-4 d-print-none">
        <div class="card-body bg-light">
            <form method="GET" class="row gx-3 gy-2 align-items-center">
                <div class="col-sm-5">
                    <label class="visually-hidden" for="sup_name">Supplier</label>
                    <div class="input-group">
                        <div class="input-group-text">Supplier</div>
                        <!-- Input visual untuk pencarian -->
                        <input type="text" class="form-control" id="sup_name" name="sup_name" placeholder="Ketik nama atau kode supplier..." value="<?= htmlspecialchars($sup_name) ?>">
                        <!-- Input hidden untuk menampung kode aslinya -->
                        <input type="hidden" id="sup_code" name="sup_code" value="<?= htmlspecialchars($sup_code) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter Data</button>
                    <!-- Tombol Reset -->
                    <a href="matpricelist.php" class="btn btn-outline-secondary">Reset</a>
                </div>
                <div class="col-auto ms-auto">
                    <!-- Tombol Export dan Print -->
                    <button type="button" class="btn btn-success" onclick="exportExcel()">Export Excel</button>
                    <button type="button" class="btn btn-dark" onclick="window.print()">Print (A4)</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Area Laporan (Header & Tabel) -->
    <div id="print-area">
        <div class="row mb-3">
            <div class="col-12 text-center">
                <h5 class="mb-1 text-start" style="font-weight: normal;">P.T. IMCTEKNO INDONESIA</h5>
                <h4 class="mb-0 fw-bold">GENERAL PRICELIST (All Items)</h4>
            </div>
            <div class="col-12 text-end text-muted" style="font-size: 11px;">
                Print Date: <?= date('m/d/Y h:i:s A') ?>
            </div>
        </div>

        <table class="table table-report">
            <thead>
                <tr>
                    <th colspan="2">I T E M S</th>
                    <th>Maker</th>
                    <th class="text-end">Price</th>
                    <th>Unit</th>
                    <th>Quot.NO</th>
                    <th>Quot. Date</th>
                    <th>Effect. Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($results) > 0): ?>
                    <?php foreach ($results as $row): ?>
                        
                        <?php
                        // --- LOGIKA GROUPING ---
                        
                        // Cek jika Supplier berubah
                        if ($current_sup !== $row['SUP_CODE']) {
                            $current_sup = $row['SUP_CODE'];
                            $current_itty = ''; // Reset group kategori
                            
                            echo '<tr>';
                            echo '<td colspan="8" class="group-sup">' . htmlspecialchars($row['SUP_CODE']) . ' ' . htmlspecialchars($row['SUP_COMP']) . '</td>';
                            echo '</tr>';
                        }

                        // Cek jika Item Type (Kategori) berubah
                        if ($current_itty !== $row['ITTY_CODE']) {
                            $current_itty = $row['ITTY_CODE'];
                            
                            echo '<tr>';
                            echo '<td colspan="8" class="group-itty">' . htmlspecialchars($row['ITTY_CODE']) . ' ' . htmlspecialchars($row['ITTY_DESC']) . '</td>';
                            echo '</tr>';
                        }

                        // --- FORMATTING DATA ---
                        $price = is_numeric($row['QUOD_PRICE']) ? number_format($row['QUOD_PRICE'], 5, '.', ',') : '0.00000';
                        $currency = htmlspecialchars($row['CURR_CODE']);
                        
                        // Format Tanggal dengan aman untuk SQL Server (karena biasanya menjadi Object DateTime)
                        $quo_date = '';
                        if (!empty($row['QUO_DATE'])) {
                            $quo_date = is_object($row['QUO_DATE']) ? $row['QUO_DATE']->format('d-M-Y') : date('d-M-Y', strtotime($row['QUO_DATE']));
                        }

                        $eff_date = '';
                        if (!empty($row['QUO_EFFDATE'])) {
                            $eff_date = is_object($row['QUO_EFFDATE']) ? $row['QUO_EFFDATE']->format('d-M-Y') : date('d-M-Y', strtotime($row['QUO_EFFDATE']));
                        }
                        ?>

                        <!-- ROW DETAIL BARANG -->
                        <tr>
                            <td style="width: 90px;"><?= htmlspecialchars($row['ITEM_CODE']) ?></td>
                            <td><?= htmlspecialchars($row['ITEM_NAME']) ?></td>
                         <td><?= htmlspecialchars(isset($row['MAKER']) ? $row['MAKER'] : '') ?></td>
                            <td class="text-end"><?= $price ?> <?= $currency ?></td>
                            <td><?= htmlspecialchars($row['QUOD_UNIT']) ?></td>
                            <td><?= htmlspecialchars($row['QUO_NO']) ?></td>
                            <td><?= $quo_date ?></td>
                            <td><?= $eff_date ?></td>
                        </tr>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <em>Tidak ada data yang ditemukan.</em>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- jQuery (Wajib untuk jQuery UI dan Autocomplete) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Inisialisasi Autocomplete
        $("#sup_name").autocomplete({
            source: function(request, response) {
                // Panggil file search_sup.php Anda
                $.post("search_sup.php", { q: request.term }, function(data) {
                    response($.map(data, function(item) {
                        return { 
                            // Teks yang akan tampil di dropdown dan setelah dipilih
                            label: item.SUP_CODE + ' - ' + item.SUP_COMP, 
                            // Nilai tersembunyi yang akan disimpan
                            value: item.SUP_CODE 
                        };
                    }));
                });
            },
            minLength: 2, // Mulai mencari setelah ketik 2 huruf
            select: function(event, ui) {
                // Saat dipilih, set value dari input text (visual)
                event.preventDefault();
                $("#sup_name").val(ui.item.label);
                
                // Set value dari input hidden untuk difilter oleh PHP
                $("#sup_code").val(ui.item.value);
            },
            // Perbaikan jika user mengetik bebas tanpa memilih dari list
            change: function(event, ui) {
                if (!ui.item) {
                    $("#sup_code").val(""); 
                }
            }
        });
    });

    // Fungsi untuk Export Excel (mengambil parameter URL saat ini)
    function exportExcel() {
        var params = window.location.search;
        window.location.href = "export_pricelist.php" + params;
    }
</script>

</body>
</html>