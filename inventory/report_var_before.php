<?php
// FILE: report_var_before.php
session_start();

// --- PENGATURAN ANTI TIMEOUT (LOADING LAMA) ---
set_time_limit(0); 
ini_set('memory_limit', '512M'); 

// Pastikan file koneksi sudah sesuai dengan sistem Inventory Anda
require_once '../config/database_p1.php'; 

// Tangkap ID SOP dari URL (Misal: ?id=115)
$sop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sop_id == 0) {
    die("<h3 style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>Error: No SOP Reference ID Provided.</h3>");
}

// Eksekusi Stored Procedure
$sql = "EXEC sp_Var_BeforeAdjust_new @SOP_ID = ?";
$params = array($sop_id);

// Tambahkan Opsi QueryTimeout (Tunggu sampai 5 Menit) agar tidak Error 30 Seconds
$options = array("QueryTimeout" => 300); 
$stmt = sqlsrv_query($conn, $sql, $params, $options);

if ($stmt === false) {
    die("<pre>Error executing SP: \n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// Array untuk mengelompokkan data berdasarkan Lokasi (LOC_NAME)
$dataByLocation = array();
$sop_date_str = "-";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Ambil tanggal SOP
    if ($sop_date_str == "-" && !empty($row['SOPDATE'])) {
        $sop_date_str = $row['SOPDATE']->format('d-F-Y'); 
    }
    
    // Kelompokkan data
    $locName = trim($row['LOC_NAME']);
    if ($locName == '') {
        $locName = 'UNASSIGNED LOCATION';
    }
    $dataByLocation[$locName][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Var Before Adjust - Ref: <?= $sop_id ?></title>
    <style>
        /* Styling agar cetakan Web mirip dengan Crystal Report */
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            color: #000;
            background-color: #fff;
            margin: 20px 40px; 
        }
        
        .header-table { width: 100%; margin-bottom: 5px; }
        .report-table { width: 100%; border-collapse: collapse; }
        
        /* Garis putus-putus untuk header tabel */
        .report-table thead th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 2px;
            font-weight: normal;
            vertical-align: bottom;
        }
        
        .report-table tbody td {
            padding: 2px 2px;
            vertical-align: top;
        }
        
        /* Styling Judul Lokasi */
        .loc-header {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            padding-top: 15px !important;
            padding-bottom: 5px !important;
        }
        
        /* Garis Lurus (Solid) untuk Subtotal seperti di Screenshot */
        .subtotal-row td {
            border-top: 1px solid #000;
            font-weight: bold;
            padding-top: 10px;
            padding-bottom: 25px; /* Jarak dengan lokasi berikutnya */
        }

        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* --- STYLING KOTAK TANDA TANGAN --- */
        .signature-table {
            border-collapse: collapse;
            text-align: center;
            margin-top: 10px;
            margin-left: 20px;
        }
        .signature-table th, .signature-table td {
            border: 1px solid #000;
            width: 110px;
            font-size: 11px;
            font-family: Arial, sans-serif; /* Font standar untuk tabel TTD */
        }
        .signature-table th {
            padding: 4px;
            font-weight: normal;
        }
        .signature-table td {
            height: 60px; /* Ruang kosong untuk tanda tangan */
        }
        
        /* Tampilan Tombol Print (Sembunyi saat di-print) */
        .no-print {
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: Arial, sans-serif;
        }
        .btn-print {
            padding: 8px 16px;
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            border-radius: 4px;
        }
        .btn-print:hover { background: #0b5ed7; }
        
        @media print {
            .no-print { display: none; }
            body { margin: 0; font-size: 11px; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <div><strong>Preview Report:</strong> Var. Before Adjust</div>
        <button class="btn-print" onclick="window.print()">🖨️ PRINT REPORT</button>
    </div>

    <table class="header-table">
        <tr>
            <td width="33%" class="text-left">
                <div style="font-weight: bold; font-size: 14px;">P.T. ISHIKAWA INDONESIA</div>
                <div>Commercial Business</div>
            </td>
            <td width="33%" class="text-center" style="vertical-align: top; padding-top: 15px;">
                SOP: <?= $sop_date_str ?>
            </td>
            <td width="33%" class="text-right" style="vertical-align: top; padding-top: 15px;">
                Print Date : <?= date('d-M-Y H:i') ?>
            </td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th width="40%" class="text-left" style="font-weight:bold;">I &nbsp; T &nbsp; E &nbsp; M</th>
                <th width="10%" class="text-right">BAL</th>
                <th width="10%" class="text-right">TAG</th>
                <th width="10%" class="text-right">DIFF</th>
                <th width="15%" class="text-right">
                    COST<br>
                    <span style="font-size: 10px;">(as per sop date)</span>
                </th>
                <th width="15%" class="text-right">USD AMNT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grand_total_usd = 0;

            // Looping per Kelompok Lokasi
            foreach ($dataByLocation as $location => $items): 
                $subtotal_usd = 0;
            ?>
                <tr>
                    <td colspan="6" class="loc-header text-left"><?= htmlspecialchars($location) ?></td>
                </tr>

<?php foreach ($items as $row): 
                    // Perbaikan: Cek apakah nama kolomnya ITEM atau ITEM_CODE
                    $item_code = isset($row['ITEM']) ? trim($row['ITEM']) : (isset($row['ITEM_CODE']) ? trim($row['ITEM_CODE']) : '-');
                    
                    // Gunakan pengecekan (isset) agar aman dari error Notice
                    $item_name = isset($row['ITEM_NAME']) ? trim($row['ITEM_NAME']) : '-';
                    $item_cur  = isset($row['ITEM_CUR']) ? trim($row['ITEM_CUR']) : '';
                    
                    $bal  = isset($row['BBAL']) ? (float)$row['BBAL'] : 0;
                    $tag  = isset($row['TTAG']) ? (float)$row['TTAG'] : 0;
                    $cost = isset($row['ITEM_COST']) ? (float)$row['ITEM_COST'] : 0;
                    
                    $diff = $tag - $bal; 
                    
                    $usd_amount = 0;
                    if ($diff != 0 && $cost > 0) {
                        if (strtoupper($item_cur) == 'USD') {
                            $usd_amount = $diff * $cost;
                        } else {
                            $usrate = (float)$row['USRATE'];
                            $curr_vrate = (float)$row['CURR_VRATE'];
                            
                            if ($usrate > 0) {
                                $usd_amount = ($diff * $cost * $curr_vrate) / $usrate;
                            }
                        }
                    }
                    
                    $subtotal_usd += $usd_amount;
                    $grand_total_usd += $usd_amount;
                ?>
                <tr>
                    <td class="text-left"><?= htmlspecialchars($item_code . " " . $item_name) ?></td>
                    <td class="text-right"><?= ($bal != 0) ? number_format($bal, 0) : '0' ?></td>
                    <td class="text-right"><?= ($tag != 0) ? number_format($tag, 0) : '0' ?></td>
                    <td class="text-right"><?= ($diff != 0) ? number_format($diff, 2) : '-' ?></td>
                    <td class="text-right">
                        <?php if ($cost > 0): ?>
                            <?= number_format($cost, 4) . " " . htmlspecialchars($item_cur) ?>
                        <?php else: ?>
                            - <?= htmlspecialchars($item_cur) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= ($usd_amount != 0) ? number_format($usd_amount, 2) : '-' ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="subtotal-row">
                    <td colspan="4"></td>
                    <td class="text-right">SUBTOTAL <?= htmlspecialchars($location) ?></td>
                    <td class="text-right"><?= number_format($subtotal_usd, 2) ?></td>
                </tr>
            <?php endforeach; ?>

            <tr>
                <td colspan="4" class="text-left" style="vertical-align: top;">
                    <table class="signature-table">
                        <tr>
                            <th>Approved by</th>
                            <th>Checked by</th>
                            <th>Prepared by</th>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </table>
                </td>
                
                <td class="text-right" style="vertical-align: middle; font-weight: bold; font-size: 13px; padding-right: 15px;">
                    GRAND TOTAL
                </td>
                
                <td class="text-right" style="vertical-align: middle; font-weight: bold; font-size: 13px;">
                    <?= number_format($grand_total_usd, 2) ?>
                </td>
            </tr>

        </tbody>
    </table>

</body>
</html>