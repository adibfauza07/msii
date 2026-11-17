<?php
// Menerima data dari form input (jika kosong, diset default string kosong)
$item_code      = isset($_POST['item_code']) ? $_POST['item_code'] : '';
$material_name  = isset($_POST['material_name']) ? $_POST['material_name'] : '';
$material_grade = isset($_POST['material_grade']) ? $_POST['material_grade'] : '';
$colour         = isset($_POST['colour']) ? $_POST['colour'] : '';
$qty            = isset($_POST['qty']) ? $_POST['qty'] : '';
$receive_date   = isset($_POST['receive_date']) && $_POST['receive_date'] != '' ? date('d-m-Y', strtotime($_POST['receive_date'])) : '';
$issue_date     = isset($_POST['issue_date']) && $_POST['issue_date'] != '' ? date('d-m-Y', strtotime($_POST['issue_date'])) : '';
$expired_date   = isset($_POST['expired_date']) && $_POST['expired_date'] != '' ? date('d-m-Y', strtotime($_POST['expired_date'])) : '';
$pic            = isset($_POST['pic']) ? $_POST['pic'] : '';
$v_pct          = isset($_POST['v_pct']) ? $_POST['v_pct'] : '';
$r_pct          = isset($_POST['r_pct']) ? $_POST['r_pct'] : '';
$lot_no         = isset($_POST['lot_no']) ? $_POST['lot_no'] : '';

// Helper agar HTML aman
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Label Material</title>
    <style>
        /* 1. Pengaturan Kertas Label Thermal 78mm x 60mm dan menghilangkan margin browser */
        @page {
            size: 78mm 60mm;
            margin: 0mm; 
        }
        
        /* 2. Atur body dengan batas ukuran pasti dan berikan padding sbg jarak aman pinggir kertas */
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 9.5px; /* Ukuran font disesuaikan agar pas di skala 100% */
            color: #000;
            margin: 0;
            padding: 2mm; 
            width: 78mm;
            height: 60mm;
            box-sizing: border-box;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            border: 1px solid #000;
            padding: 1px 3px; /* Padding tabel dirapatkan */
            vertical-align: middle;
            height: 14px; /* Tinggi baris disesuaikan agar tidak over ke bawah */
            overflow: hidden;
            word-wrap: break-word;
        }

        /* Lebar spesifik untuk grid 7 kolom agar proporsional */
        .c1 { width: 12%; }
        .c2 { width: 14%; }
        .c3 { width: 14%; }
        .c4 { width: 16%; }
        .c5 { width: 10%; }
        .c6 { width: 14%; }
        .c7 { width: 20%; }

        .center { text-align: center; }
        .bold { font-weight: bold; }
        .red-text { color: red !important; }
        
        .header-title {
            font-size: 10.5px;
            font-weight: bold;
        }

        /* Hilangkan border bawah pada footer */
        .footer-text {
            border-left: none;
            border-right: none;
            border-bottom: none;
            font-size: 8.5px;
            padding-top: 3px;
        }

        /* Memastikan warna merah dan layout tereksekusi baik di printer */
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .red-text { color: red !important; }
        }
    </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); };">

<table>
    <!-- Struktur Kolom Utama (Grid 7 Kolom) -->
    <colgroup>
        <col class="c1"><col class="c2"><col class="c3"><col class="c4"><col class="c5"><col class="c6"><col class="c7">
    </colgroup>

    <tbody>
        <!-- Baris 1: Header PT -->
        <tr>
            <td colspan="4" class="header-title">PT. IMC TEKNO INDONESIA</td>
            <td class="center">1</td>
            <td class="center">2</td>
            <td class="center">3</td>
        </tr>

        <!-- Baris 2: Material Name & Item Code Label -->
        <tr>
            <td colspan="2">MATERIAL NAME</td>
            <td colspan="2" class="bold"><?= h($material_name) ?></td>
            <td colspan="3" class="center">ITEM CODE</td>
        </tr>

        <!-- Baris 3: Material Grade & Item Code Value -->
        <tr>
            <td colspan="2">MATERIAL GRADE</td>
            <td colspan="2" class="bold"><?= h($material_grade) ?></td>
            <td colspan="3" class="center bold"><?= h($item_code) ?></td>
        </tr>

        <!-- Baris 4: Colour & Receive Date Label -->
        <tr>
            <td colspan="2">COLOUR</td>
            <td colspan="2" class="bold"><?= h($colour) ?></td>
            <td colspan="3" class="center">RECEIVE DATE</td>
        </tr>

        <!-- Baris 5: Qty, Kg, Receive Date Value -->
        <tr>
            <td>QTY</td>
            <td colspan="2" class="bold center"><?= h($qty) ?></td>
            <td class="center red-text">Kg</td>
            <td colspan="3" class="center bold"><?= h($receive_date) ?></td>
        </tr>

        <!-- Baris 6: Issue Date Label & Expired Date Label -->
        <tr>
            <td colspan="2">ISSUE DATE</td>
            <td colspan="2" class="bold center"><?= h($issue_date) ?></td>
            <td colspan="3" class="center">EXPERIED DATE</td>
        </tr>

        <!-- Baris 7: PIC & Expired Date Value -->
        <tr>
            <td colspan="2">PIC</td>
            <td colspan="2" class="bold center"><?= h($pic) ?></td>
            <td colspan="3" class="center bold"><?= h($expired_date) ?></td>
        </tr>

        <!-- Baris 8: Kategori V, R, P, Ex Part, MIX, Persentase -->
        <tr>
            <td rowspan="2" class="center">V</td>
            <td rowspan="2" class="center">R</td>
            <td rowspan="2" class="center">P</td>
            <td class="center">Ex Part</td>
            <td rowspan="2" class="center">MIX</td>
            <td colspan="2" class="red-text">
                V= <?= h($v_pct) ?> % &nbsp;&nbsp;&nbsp; R= <?= h($r_pct) ?> %
            </td>
        </tr>

        <!-- Baris 9: Kategori NG, Lot No -->
        <tr>
            <td class="center">NG</td>
            <td>LOT NO</td>
            <td class="bold"><?= h($lot_no) ?></td>
        </tr>

        <!-- Baris 10: Footer Dokumen -->
        <tr>
            <td colspan="7" class="footer-text">FM.PD.C-08-020 (Rev 4, 02 JAN 2024)</td>
        </tr>
    </tbody>
</table>

</body>
</html>