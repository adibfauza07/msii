<?php
// ====================================================
//  REPORT LIST ALL USULAN (PLANT 1) - HTML PRINT VERSION
//  Layout HTML Table (Menampilkan SEMUA data: OPEN & CLOSE)
// ====================================================

require_once '../config/Database_p1.php'; 

// === 1. Ambil Parameter Filter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';
$kode_usul  = isset($_GET['kode_usul']) ? $_GET['kode_usul'] : '';
$request_by = isset($_GET['request_by']) ? $_GET['request_by'] : '';
$remark     = isset($_GET['remark']) ? $_GET['remark'] : '';

// === 2. Query Data (Tanpa filter Status OPEN) ===
$sql = "
SELECT 
    U.KODE_USUL, U.ISSUE_DATE, U.JUDUL_DOK, U.ISI_REVISI, U.REVISI_1,
    U.PJ, U.PLANT_DATE, U.TARGET_DATE, U.REMARKS,
    I.ITEM_NO, I.ITEM_NAME,
    U.QCPC, U.IS_STD, U.FMEA, U.WI, U.STD_PACK, U.CHECK_POINT
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    ITEMS I ON I.ITEM_ID = U.ITEM_ID
WHERE 
    (U.ISSUE_DATE BETWEEN ? AND ?)
";

$params = [$start_date, $end_date];

if (!empty($cust_id)) { $sql .= " AND U.CUST_ID = ?"; $params[] = $cust_id; }
if (!empty($kode_usul)) { $sql .= " AND U.KODE_USUL LIKE ?"; $params[] = "%".$kode_usul."%"; }
if (!empty($request_by)) { $sql .= " AND U.PJ = ?"; $params[] = $request_by; }
if (!empty($remark)) { $sql .= " AND U.REMARKS = ?"; $params[] = $remark; }

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) die("Error: " . print_r(sqlsrv_errors(), true));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>List All Usulan Perubahan (Plant 1)</title>
    <style>
        body { font-family: "Arial", sans-serif; font-size: 10px; color: #000; -webkit-print-color-adjust: exact; }
        
        /* Header Styling */
        .header-table { width: 100%; border-bottom: 0; margin-bottom: 10px; }
        .company-name { font-weight: bold; font-size: 14px; }
        .dept-name { font-weight: bold; font-size: 12px; }
        .report-title { font-weight: bold; font-size: 16px; text-align: center; text-transform: uppercase; }
        .page-number { text-align: right; font-size: 10px; }

        /* Table Styling */
        .data-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 5px; }
        .data-table th, .data-table td { 
            border: 1px solid #000; 
            padding: 4px; 
            vertical-align: top; 
        }
        
        .data-table th { 
            text-align: center; 
            font-weight: bold; 
            background-color: #fff; 
            vertical-align: middle;
        }

        /* Specific Column Widths */
        .col-no { width: 25px; text-align: center; }
        .col-req { width: 200px; }
        .col-doc { width: 120px; }
        .col-usulan { width: auto; }
        .col-rev { width: 30px; text-align: center; }
        .col-check { width: 25px; text-align: center; padding: 0 !important;}
        .col-date { width: 60px; text-align: center; }
        .col-status { width: 60px; text-align: center; }

        /* Checkbox Styling */
        .chk-box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid red;
            margin-top: 5px;
        }
        .chk-checked {
            /* background-color: red; */ 
        }

        /* Text Colors */
        .text-blue { color: blue; }
        .text-red { color: red; font-weight: bold; }
        .text-black { color: black; font-weight: bold; }
        
        /* Print Settings */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <!-- Tombol Print -->
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight:bold;">🖨️ Cetak / Simpan PDF</button>
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 30%;">
                <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                <div class="dept-name">DCC DEPT</div>
            </td>
            <td style="width: 40%;" class="report-title">
                LIST ALL USULAN PERUBAHAN
            </td>
            <td style="width: 30%;" class="page-number">
                Printed: <?= date('d-M-Y H:i') ?>
            </td>
        </tr>
    </table>

    <!-- Tabel Data -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-no">No</th>
                <th rowspan="2" class="col-req" style="text-align: left; padding-left: 5px;">Request By &nbsp;&nbsp;&nbsp; Item Name</th>
                <th rowspan="2" class="col-doc">Judul Dokumen</th>
                <th rowspan="2" class="col-usulan">Usulan Perubahan</th>
                <th rowspan="2" class="col-rev">Revisi</th>
                <th colspan="6" style="height: 25px;">ITEM DOCUMENT SUPPORT</th>
                <th rowspan="2" class="col-date">PLAN<br>DATE</th>
                <th rowspan="2" class="col-date">FINISH<br>DATE</th>
                <th rowspan="2" class="col-status">STATUS</th>
            </tr>
            <tr>
                <th class="col-check">QCPC</th>
                <th class="col-check">IS</th>
                <th class="col-check">FMEA</th>
                <th class="col-check">WI</th>
                <th class="col-check" style="font-size: 9px;">STD<br>PACK</th>
                <th class="col-check" style="font-size: 9px;">CHECK<br>POINT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) : 
                $issue_date = $row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('d-M-Y') : '-';
                $plan_date = $row['PLANT_DATE'] ? $row['PLANT_DATE']->format('d-M-y') : '-';
                $finish_date = $row['TARGET_DATE'] ? $row['TARGET_DATE']->format('d-M-y') : '-';
                
                // LOGIKA STATUS (DIPERBAIKI UNTUK PHP LAMA)
                // Ganti '??' dengan pengecekan manual 'isset()'
                $remark_val = isset($row['REMARKS']) ? $row['REMARKS'] : '';
                $remark_upper = strtoupper($remark_val);
                
                $is_closed = (strpos($remark_upper, 'CLOSE') !== false);
                $status_text = $is_closed ? "CLOSE" : "OPEN";
                $status_class = $is_closed ? "text-black" : "text-red";
            ?>
            <tr>
                <td style="text-align: center;"><?= $no++ ?></td>
                
                <!-- Kolom Komposit -->
                <td>
                    <b><?= htmlspecialchars($row['PJ']) ?></b><br>
                    Issue Date : <?= $issue_date ?><br>
                    <?= htmlspecialchars($row['ITEM_NAME']) ?><br>
                    <span class="text-blue" style="text-decoration: underline;"><?= htmlspecialchars($row['KODE_USUL']) ?></span>
                    &nbsp;&nbsp;
                    <span class="text-blue"><?= htmlspecialchars($row['ITEM_NO']) ?></span>
                </td>

                <td><?= htmlspecialchars($row['JUDUL_DOK']) ?></td>
                <td><?= htmlspecialchars($row['ISI_REVISI']) ?></td>
                <td style="text-align: center;"><?= htmlspecialchars($row['REVISI_1']) ?></td>

                <!-- Checkboxes -->
                <td style="text-align: center;"><?= ($row['QCPC'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>
                <td style="text-align: center;"><?= ($row['IS_STD'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>
                <td style="text-align: center;"><?= ($row['FMEA'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>
                <td style="text-align: center;"><?= ($row['WI'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>
                <td style="text-align: center;"><?= ($row['STD_PACK'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>
                <td style="text-align: center;"><?= ($row['CHECK_POINT'] == 1) ? '<div class="chk-box chk-checked"></div>' : '<div class="chk-box"></div>' ?></td>

                <td style="text-align: center;"><?= $plan_date ?></td>
                <td style="text-align: center;"><?= $finish_date ?></td>
                
                <!-- Status Dinamis -->
                <td class="<?= $status_class ?>" style="text-align: center; vertical-align: middle;">
                    <?= $status_text ?>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <?php if ($no == 1): ?>
            <tr>
                <td colspan="15" style="text-align: center; padding: 20px;">Tidak ada data ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>