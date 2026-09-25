<?php
require "../config/database.php";

// Ambil parameter dari URL
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

$from_disp  = $from;
$to_disp    = $to;
$mac_disp   = ($mac === "" ? "SEMUA" : $mac);
$plant_disp = ($plant === "" ? "SEMUA" : $plant);

$from_sql = $from ? date("Y-m-d", strtotime($from)) : null;
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : null;

$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

// Ambil data
$rows = [];
if ($from_sql && $to_sql) {
    $sql = "{CALL SP_MTN_HISTORY_KERUSAKAN_KATEGORI_new(?, ?, ?, ?)}";
    $params = [$from_sql, $to_sql, $mac_param, $plant_param];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
    }
}

// Grouping per KATEGORI
$grouped = [];
if (!empty($rows)) {
    foreach ($rows as $r) {
        $kat = $r['KATEGORI'];
        if (!isset($grouped[$kat])) {
            $grouped[$kat] = ['items' => [], 'total' => 0];
        }
        $grouped[$kat]['items'][] = $r;
        $grouped[$kat]['total']  += (int)$r['KERUSAKAN'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Cetak Laporan Kerusakan</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-table td {
            padding: 4px;
        }
        .kategori-title {
            font-weight: bold;
            font-size: 14px;
            background: #e0e0e0;
            padding: 8px;
            border: 1px solid #000;
            border-bottom: none;
            text-transform: uppercase;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 13px;
        }
        table.report-table th, table.report-table td {
            border: 1px solid #000;
            padding: 8px;
        }
        table.report-table th {
            background: #f0f0f0;
            text-align: center;
        }
        .total-row td {
            font-weight: bold;
            background: #f9f9f9;
        }
        
        /* Pengaturan Cetak Khusus */
        @media print {
            @page { margin: 15mm; size: portrait; }
            body { padding: 0; }
            .no-print { display: none; }
        }

        /* Trik Agar Kode Dokumen Tetap di Pojok Kiri Bawah */
        .doc-code {
            position: fixed;
            bottom: 0;
            left: 0;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<!-- Auto Print saat halaman selesai diload -->
<body onload="window.print()">

    <button class="no-print" onclick="window.print()" style="padding:10px 20px; margin-bottom:20px; background:#007bff; color:#fff; border:none; cursor:pointer;">
        Cetak Ulang
    </button>

    <h2>Laporan Kerusakan Per Periode</h2>

    <table class="info-table">
        <tr>
            <td width="100"><b>Periode</b></td>
            <td width="10">:</td>
            <td><?= $from_disp ? date("d-M-Y", strtotime($from_disp)) : "-" ?> s/d <?= $to_disp ? date("d-M-Y", strtotime($to_disp)) : "-" ?></td>
        </tr>
        <tr>
            <td><b>Mesin (MAC)</b></td>
            <td>:</td>
            <td><?= htmlspecialchars($mac_disp) ?></td>
        </tr>
        <tr>
            <td><b>Plant</b></td>
            <td>:</td>
            <td><?= htmlspecialchars($plant_disp) ?></td>
        </tr>
    </table>

    <?php if (empty($grouped)): ?>
        <p style="text-align:center; font-style:italic;">Tidak ada data kerusakan untuk periode dan filter tersebut.</p>
    <?php else: ?>
        
        <!-- Bungkus dengan tabel besar untuk memicu tfoot di setiap halaman cetak -->
        <table style="width:100%; border:none;">
            <tbody>
                <tr>
                    <td style="padding:0;">
                        
                        <?php foreach ($grouped as $kat => $data): ?>
                            <div class="kategori-title"><?= htmlspecialchars($kat) ?></div>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Detail Kerusakan</th>
                                        <th width="100">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['DETAIL']) ?></td>
                                            <td style="text-align:center;"><?= (int)$item['KERUSAKAN'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td style="text-align:right;">Total Kerusakan</td>
                                        <td style="text-align:center;"><?= (int)$data['total'] ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endforeach; ?>

                    </td>
                </tr>
            </tbody>
            
            <!-- Pengganjal ruang kosong untuk kode form -->
            <tfoot>
                <tr>
                    <td style="border:none; height:35px;"></td>
                </tr>
            </tfoot>
        </table>

    <?php endif; ?>

    <!-- Kode Form Pojok Kiri Bawah -->
    <div class="doc-code">FM.MTN.S01-43</div>

</body>
</html>