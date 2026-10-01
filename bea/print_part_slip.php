<?php
require_once __DIR__ . '/config/database.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;

// 1. Ambil Header
$sqlHead = "SELECT T.*, TY.TRTY_DESC 
            FROM TRANS T 
            LEFT JOIN TRTY TY ON T.TRTY_CODE = TY.TRTY_CODE
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));
$rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC);

if (!$rHead) die("Data Transaksi Tidak Ditemukan.");

// --- LOGIKA FROM & TO ---
$tipe = $rHead['TRTY_CODE'];
$txtFrom = "-";
$txtTo   = "-";

if ($tipe == '08') {
    $txtFrom = "Production";
    $txtTo   = "Warehouse";
} elseif ($tipe == '09') {
    $txtFrom = "Warehouse";
    $txtTo   = "Production";
}

// 2. Ambil Detail Barang (REVISI: PASTIKAN ITEM CODE MUNCUL)
$sqlDet = "SELECT 
                -- Ambil Item Code dari Master dulu, kalau kosong baru dari Transaksi
                -- LTRIM(RTRIM(...)) gunanya membuang spasi kosong di depan/belakang
                COALESCE(NULLIF(LTRIM(RTRIM(I.ITEM_CODE)), ''), LTRIM(RTRIM(T.ITEM_CODE)), '-') as ITEM_CODE_DISPLAY,
                
                -- Ambil Nama Barang
                COALESCE(I.ITEM_NAME, T.TRAN_REMARK, '-') as ITEM_NAME_DISPLAY,
                
                -- Ambil Unit
                COALESCE(I.ITEM_UNIT, '-') as ITEM_UNIT_DISPLAY,
                
                T.IT_QTY, 
                T.TRAN_REMARK
           FROM INV_TRAN T
           -- Join 'Pintar': Cocokkan ID ATAU Cocokkan Kode (yang sudah dibersihkan spasinya)
           LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE)))
           WHERE T.TRAN_ID = ? 
           ORDER BY T.IT_LINENO ASC";

$qDet = sqlsrv_query($conn, $sqlDet, array($id));

// Format Tanggal
$tgl = ($rHead['TRAN_DATE'] instanceof DateTime) ? $rHead['TRAN_DATE']->format('d-M-Y') : $rHead['TRAN_DATE'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Part Slip - <?php echo $rHead['TRAN_DOC']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; max-width: 210mm; margin: 0 auto; padding: 15px; }
        .header { text-align: center; font-weight: bold; margin-bottom: 25px; }
        .judul { font-size: 16px; text-decoration: underline; margin-top: 5px; display: block; }
        
        /* TABLE INFO HEADER (Tanpa Garis) */
        .info-table { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .info-table td { padding: 2px 0; vertical-align: top; border: none; }
        
        /* TABLE DETAIL BARANG (Garis Hitam) */
        .data-table { width: 100%; border-collapse: collapse; border: 2px solid black; margin-top: 5px; }
        .data-table th, .data-table td { border: 1px solid black; padding: 4px; }
        .data-table th { text-align: center; background-color: #f0f0f0; font-weight: bold; }
        
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .font-bold { font-weight: bold; }
        
        /* Footer Tanda Tangan */
        .footer { margin-top: 30px; display: flex; justify-content: space-between; padding: 0 40px; }
        .sign-box { text-align: center; width: 150px; }
        .sign-line { border-bottom: 1px solid black; margin-top: 60px; }

        @media print {
            @page { margin: 10mm; size: auto; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        PT. IMC TEKNO INDONESIA PLANT 1<br>
        PPIC DEPARTMENT<br>
        <span class="judul">PART SLIP</span>
    </div>

    <table class="info-table">
        <tr>
            <td width="10%">NOMOR</td>
            <td width="1%">:</td>
            <td width="40%" class="font-bold"><?php echo $rHead['TRAN_DOC']; ?> / <?php echo $tipe; ?></td>
            
            <td width="5%"></td> 
            
            <td width="10%">FROM</td>
            <td width="1%">:</td>
            <td width="33%" class="font-bold"><?php echo $txtFrom; ?></td>
        </tr>
        <tr>
            <td>DATE</td>
            <td>:</td>
            <td><?php echo $tgl; ?></td>
            
            <td></td>
            
            <td>TO</td>
            <td>:</td>
            <td class="font-bold"><?php echo $txtTo; ?></td>
        </tr>
        <tr>
            <td>REMARK</td>
            <td>:</td>
            <td colspan="5"><?php echo $rHead['TRAN_REM']; ?></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="30">NO</th>
                <th width="100">ITEM CODE</th>
                <th>ITEM NAME</th>
                <th width="50">UNIT</th>
                <th width="80">QTY</th>
                <th>REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            $totalQty = 0;
            if ($qDet) {
                while($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) { 
                    $qty = (float)$row['IT_QTY'];
                    $totalQty += $qty;
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td class="text-center font-bold"><?php echo $row['ITEM_CODE_DISPLAY']; ?></td>
                <td><?php echo $row['ITEM_NAME_DISPLAY']; ?></td>
                <td class="text-center"><?php echo $row['ITEM_UNIT_DISPLAY']; ?></td>
                <td class="text-end"><?php echo number_format($qty, 2); ?></td>
                <td><?php echo $row['TRAN_REMARK']; ?></td>
            </tr>
            <?php 
                } 
            }
            ?>
            
            <tr class="font-bold">
                <td colspan="4" class="text-end">TOTAL:</td>
                <td class="text-end"><?php echo number_format($totalQty, 2); ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div class="sign-box">
            DELIVERED
            <div class="sign-line"></div>
        </div>
        <div class="sign-box">
            RECEIVED
            <div class="sign-line"></div>
        </div>
    </div>

</body>
</html>