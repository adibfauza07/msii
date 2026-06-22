<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter Tanggal
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Format StartDate dan EndDate menjadi 'YYYYMMDD'
$startFormatted = date('Ymd', strtotime($startDate));
$endFormatted   = date('Ymd', strtotime($endDate));

$periodeStr = date('d - M - Y', strtotime($startDate)) . "  ~  " . date('d - M - Y', strtotime($endDate));

// 2. Eksekusi Stored Procedure
$sql = "EXEC SP_TRANSACTION_LIST_2 @START_DATE = ?, @END_DATE = ?";
$stmt = sqlsrv_query($conn, $sql, array($startFormatted, $endFormatted));

if ($stmt === false) {
    die("<pre>Error SP_TRANSACTION_LIST_2: \n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 3. Proses Grouping Hierarki (TRTY_CODE -> TRAN_ID -> Items)
$groupedData = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $trtyKey = (isset($row['TRTY_CODE']) ? $row['TRTY_CODE'] : '') . " - " . (isset($row['TRTY_DESC']) ? $row['TRTY_DESC'] : '');
    $tranId  = isset($row['TRAN_ID']) ? $row['TRAN_ID'] : 0;

    if (!isset($groupedData[$trtyKey])) {
        $groupedData[$trtyKey] = [];
    }
    
    // Simpan Header Dokumen
    if (!isset($groupedData[$trtyKey][$tranId])) {
        $groupedData[$trtyKey][$tranId] = [
            'HEADER' => [
                'TRAN_DOC'  => isset($row['TRAN_DOC']) ? $row['TRAN_DOC'] : '',
                'TRAN_DATE' => isset($row['TRAN_DATE']) ? $row['TRAN_DATE'] : null,
                'TRAN_ADATE'=> isset($row['TRAN_ADATE']) ? $row['TRAN_ADATE'] : null,
                'CONTACT'   => isset($row['CONTACT']) ? $row['CONTACT'] : ''
            ],
            'ITEMS' => []
        ];
    }
    
    // Simpan Detail Item
    $groupedData[$trtyKey][$tranId]['ITEMS'][] = $row;
}

// Urutkan grup berdasarkan TRTY_CODE (01, 03, 05, dst) agar rapi
ksort($groupedData);

$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. IMC TEKNO INDONESIA" : "PT. IMC TEKNO INDONESIA";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaction List</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        
        .header-table { width: 100%; margin-bottom: 10px; }
        .header-table h2 { text-align: center; margin: 0 0 5px 0; font-size: 22px; font-weight: normal; letter-spacing: 1px; }
        
        /* Table Layout Fixed agar render puluhan ribu baris super cepat */
        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
        .data-table th, .data-table td { padding: 3px 2px; word-wrap: break-word; overflow: hidden; vertical-align: top; }
        
        /* Kolom Persentase */
        .data-table th:nth-child(1) { width: 22%; } /* Item Cd */
        .data-table th:nth-child(2) { width: 40%; } /* Item Name */
        .data-table th:nth-child(3) { width: 13%; } /* Date */
        .data-table th:nth-child(4) { width: 10%; } /* Act.Date */
        .data-table th:nth-child(5) { width: 15%; } /* Contacts */

        /* Border Header 2 Lapis ala Crystal Report */
        .data-table thead tr.top-th th { border-top: 2px solid #000; border-bottom: 1px solid #000; text-align: left; font-weight: bold; }
        .data-table thead tr.bot-th th { border-bottom: 2px solid #000; text-align: left; font-weight: bold; }
        
        .trty-header { font-weight: bold; font-size: 12px; padding-top: 15px !important; }
        .doc-row td { padding-top: 10px !important; }
        .item-row td { padding-top: 0px !important; padding-bottom: 0px !important; }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; box-shadow: none; }
            @page { size: portrait; margin: 10mm; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td style="width: 33%; font-size: 13px; vertical-align: top; font-weight: normal;">
                    <?php echo htmlspecialchars($companyName); ?>
                </td>
                <td style="width: 34%; text-align: center; vertical-align: top;">
                    <h2>TRANSACTION<br>LIST</h2>
                    <div style="font-size: 11px;"><?php echo $periodeStr; ?></div>
                </td>
                <td style="width: 33%; text-align: right; vertical-align: bottom; font-size: 10px;">
                    Print Date : <?php echo date('d-M-Y g:i:sA'); ?>
                </td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <div style="text-align: center; padding: 40px; border-top: 2px solid #000; border-bottom: 2px solid #000; font-weight: bold;">
                Tidak ada data transaksi pada periode yang dipilih.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr class="top-th">
                        <th>Item Cd</th>
                        <th>Item Name</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Act.Date</th>
                        <th class="text-center">Contacts</th>
                    </tr>
                    <tr class="bot-th">
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class="text-right">Qty</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $trtyName => $docs): ?>
                        
                        <tr>
                            <td colspan="5" class="trty-header"><?php echo htmlspecialchars($trtyName); ?></td>
                        </tr>

                        <?php foreach ($docs as $tranId => $docData): 
                            $head = $docData['HEADER'];
                            $tglDoc = ($head['TRAN_DATE'] instanceof DateTime) ? $head['TRAN_DATE']->format('d-M-Y') : $head['TRAN_DATE'];
                            $tglAct = ($head['TRAN_ADATE'] instanceof DateTime) ? $head['TRAN_ADATE']->format('d-M-Y') : $head['TRAN_ADATE'];
                        ?>
                            <tr class="doc-row">
                                <td colspan="2" style="padding-left: 10px;"><?php echo htmlspecialchars($head['TRAN_DOC']); ?></td>
                                <td class="text-center"><?php echo $tglDoc; ?></td>
                                <td class="text-center"><?php echo $tglAct; ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($head['CONTACT']); ?></td>
                            </tr>

                            <?php foreach ($docData['ITEMS'] as $item): 
                                $qty = isset($item['IT_QTY']) ? (float)$item['IT_QTY'] : 0;
                                $unit = isset($item['ITEM_UNIT']) ? strtolower($item['ITEM_UNIT']) : '';
                            ?>
                                <tr class="item-row">
                                    <td style="padding-left: 25px;"><?php echo htmlspecialchars(isset($item['ITEM_CODE']) ? $item['ITEM_CODE'] : ''); ?></td>
                                    <td><?php echo htmlspecialchars(isset($item['ITEM_NAME']) ? $item['ITEM_NAME'] : ''); ?></td>
                                    <td></td>
                                    <td class="text-right"><?php echo number_format($qty, 2) . " " . $unit; ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                            
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>