<?php
require_once __DIR__ . '/../config/database_p1.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'SPB'; 

if (empty($id)) {
    die("Error: ID Transaksi tidak ditemukan.");
}

// 1. AMBIL DATA HEADER TRANSAKSI
$sqlHead = "SELECT T.*, S.SUP_COMP, S.SUP_ADDR1 
            FROM TRANS T 
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE 
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));

if ($qHead === false || !($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC))) {
    die("Error: Data transaksi tidak ditemukan.");
}

// Format Tanggal
$tranDate = ($rHead['TRAN_DATE'] instanceof DateTime) ? $rHead['TRAN_DATE']->format('d-M-Y') : $rHead['TRAN_DATE'];
$noKendaraan = ""; 
$kepada = isset($rHead['SUP_COMP']) ? $rHead['SUP_COMP'] : '';

// =========================================================================
// 2. AMBIL DATA DETAIL BARANG (DENGAN PHP FALLBACK & ERROR TRAPPING)
// =========================================================================
$sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, T.ITEM_CODE as TRAN_CODE, T.TRAN_REMARK,
                     I.ITEM_CODE as MASTER_CODE, I.ITEM_NAME as MASTER_NAME, I.ITEM_UNIT as MASTER_UNIT
              FROM INV_TRAN T 
              LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND T.ITEM_CODE = I.ITEM_CODE))
              WHERE T.TRAN_ID = ? 
              ORDER BY T.IT_LINENO ASC";

$qDet = sqlsrv_query($conn, $sqlDetail, array($id));

// JIKA QUERY ERROR, MUNCULKAN PESANNYA DI LAYAR
if ($qDet === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red; font-family:sans-serif;'>
            <b>Terjadi Kesalahan SQL Detail:</b><br>" . print_r(sqlsrv_errors(), true) . 
        "</div>");
}

$dataDetail = [];
while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    // Logika Pengganti COALESCE di SQL (Dijalankan dengan aman di PHP)
    $row['ITEM_CODE'] = !empty($row['MASTER_CODE']) ? $row['MASTER_CODE'] : (!empty($row['TRAN_CODE']) ? $row['TRAN_CODE'] : '???');
    $row['ITEM_NAME'] = !empty($row['MASTER_NAME']) ? $row['MASTER_NAME'] : (!empty($row['TRAN_REMARK']) ? $row['TRAN_REMARK'] : '');
    $row['ITEM_UNIT'] = !empty($row['MASTER_UNIT']) ? $row['MASTER_UNIT'] : '';
    
    $dataDetail[] = $row;
}
// =========================================================================

// Nama Perusahaan berdasarkan Session (Plant 1 atau Plant 2)
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Industri Mitra Karawang<br>ST-1 Blok A-III Lot No. 15E - 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181<br>Phone (0264) 351441";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>ST-1 Blok A-III Lot No. 15E - 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181<br>Phone (0264) 351441";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pengantar Barang - <?php echo $rHead['TRAN_DOC']; ?></title>
    <style>
        /* RESET & DASAR */
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 13px; 
            color: #000; 
            padding: 20px; 
            background: #f0f0f0; 
            margin: 0;
        }
        
        .page-container {
            background: #fff;
            width: 210mm; 
            min-height: 140mm; 
            margin: 0 auto;
            padding: 15px 20px; 
            box-sizing: border-box;
        }

        /* KOP SURAT */
        .kop-surat { margin-bottom: 20px; }
        .company-name { font-size: 15px; font-weight: bold; text-decoration: underline; margin-bottom: 3px; font-family: Arial, sans-serif;}
        .company-addr { font-size: 12px; line-height: 1.2; font-family: Arial, sans-serif;}

        /* JUDUL DOKUMEN */
        .doc-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-top: -15px;
            margin-bottom: 5px;
            letter-spacing: 1px;
            font-family: Arial, sans-serif;
        }

        /* WADAH TABEL (BORDER LUAR TEBAL) */
        .table-wrapper {
            border: 2px solid #000;
            margin-bottom: 15px;
        }

        /* TABEL HEADER (NOMOR, TANGGAL, KEPADA) */
        .header-table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px;}
        .header-table td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        .header-table .col-label { width: 12%; font-weight: bold; border-left: none; }
        .header-table .col-colon { width: 2%; text-align: center; }
        .header-table .col-val { width: 36%; }
        .header-table .col-kepada { width: 50%; border-right: none; border-top: none; }

        /* TABEL ITEM DETAIL */
        .item-table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px;}
        .item-table th, .item-table td { border: 1px solid #000; padding: 4px 5px; }
        .item-table th { font-weight: bold; text-align: center; border-top: 2px solid #000; }
        
        /* Hilangkan border kiri kanan pada table item agar menyatu dengan wrapper */
        .item-table th:first-child, .item-table td:first-child { border-left: none; }
        .item-table th:last-child, .item-table td:last-child { border-right: none; }
        .item-table tr:last-child td { border-bottom: none; }

        /* SECTION TANDA TANGAN */
        .ttd-table {
            width: 100%;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin-top: 5px;
        }
        .ttd-table td { width: 33.33%; }
        .ttd-table .space { height: 70px; vertical-align: bottom; }

        /* FOOTER KETERANGAN BAWAH */
        .footer-note {
            margin-top: 10px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
        }
        .footer-colors { margin-left: 50px; }

        /* PRINT SETTINGS */
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        .btn:hover { background: #e0e0e0; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; }
            @page { size: auto; margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print SPB</button>
    </div>

    <div class="page-container">
        
        <div class="kop-surat">
            <div class="company-name"><?php echo $companyName; ?></div>
            <div class="company-addr"><?php echo $companyAddress; ?></div>
        </div>

        <div class="doc-title">SURAT PENGANTAR BARANG</div>

        <div class="table-wrapper">
            
            <table class="header-table">
                <tr>
                    <td class="col-label" style="border-top: none;">Nomor</td>
                    <td class="col-colon" style="border-top: none;">:</td>
                    <td class="col-val" style="border-top: none;"><?php echo $rHead['TRAN_DOC']; ?></td>
                    <td rowspan="3" class="col-kepada">
                        <b>Kepada Yth.</b><br><br>
                        <?php echo $kepada; ?>
                    </td>
                </tr>
                <tr>
                    <td class="col-label">Tanggal</td>
                    <td class="col-colon">:</td>
                    <td class="col-val"><?php echo $tranDate; ?></td>
                </tr>
                <tr>
                    <td class="col-label">No Kend</td>
                    <td class="col-colon">:</td>
                    <td class="col-val"><?php echo $noKendaraan; ?></td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">CODE</th>
                        <th style="width: 45%;">NAME</th>
                        <th style="width: 10%;">UNIT</th>
                        <th style="width: 10%;">JUMLAH</th>
                        <th style="width: 20%;">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($dataDetail)) {
                        echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>";
                    } else {
                        foreach ($dataDetail as $row) {
                            $qty = (floor($row['IT_QTY']) == $row['IT_QTY']) ? number_format($row['IT_QTY'], 0) : number_format($row['IT_QTY'], 2);
                            echo "<tr>
                                    <td>{$row['ITEM_CODE']}</td>
                                    <td>{$row['ITEM_NAME']}</td>
                                    <td style='text-align: center;'>{$row['ITEM_UNIT']}</td>
                                    <td style='text-align: center;'>{$qty}</td>
                                    <td></td>
                                  </tr>";
                        }
                    }
                    
                    // Baris Kosong / Padding untuk ruang bawah tabel
                    for ($i=0; $i<2; $i++) {
                        echo "<tr>
                                <td style='border-bottom:none; border-top:none;'>&nbsp;</td>
                                <td style='border-bottom:none; border-top:none;'></td>
                                <td style='border-bottom:none; border-top:none;'></td>
                                <td style='border-bottom:none; border-top:none;'></td>
                                <td style='border-bottom:none; border-top:none;'></td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div> <table class="ttd-table">
            <tr>
                <td>Penerima</td>
                <td>Mengetahui</td>
                <td>Hormat Kami</td>
            </tr>
            <tr>
                <td class="space">(.....................................)</td>
                <td class="space">(.....................................)</td>
                <td class="space">(.....................................)</td>
            </tr>
        </table>

        <div class="footer-note">
            <div class="footer-colors">
                1.White : Customer &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                2.Pink : Accounting &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                3.Yellow : Store &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                4.Blue : Customer &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                5.Green : Security
            </div>
            <div>
                FM.CO.01-20 (Revisi 4 : Tgl.1 Mei 12)
            </div>
        </div>

    </div>

</body>
</html>