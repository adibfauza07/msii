<?php
// prod_history.php
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$itemCode = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

$databaseName = "msData";
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
if ($uid !== "") { 
    $connectionOptions["Uid"] = $uid; 
    $connectionOptions["PWD"] = $pwd; 
}

$historyData = array();
$itemName = '';

if ($itemCode !== "") {
    $conn = @sqlsrv_connect($serverName, $connectionOptions);
    if ($conn) {
        // Menggunakan query persis seperti punya Anda (tambah ORDER BY agar rapi)
        $sql = "SELECT 
                    dbo.ITEMS.ITEM_CODE, 
                    dbo.ITEMS.ITEM_NAME, 
                    dbo.WO.WO_NUMBER, 
                    dbo.PRODUCTION.PD_LOT, 
                    dbo.PRODUCTION.PD_OK, 
                    dbo.PRODUCTION.PD_NG, 
                    dbo.PRODUCTION.PD_HO, 
                    dbo.PRODUCTION.PD_REM
                FROM dbo.PRODUCTION 
                INNER JOIN dbo.WO ON dbo.PRODUCTION.WO_ID = dbo.WO.WO_ID 
                INNER JOIN dbo.ITEMS ON dbo.WO.ITEM_ID = dbo.ITEMS.ITEM_ID
                WHERE (dbo.ITEMS.ITEM_CODE = ?)
                ORDER BY dbo.WO.WO_NUMBER DESC, dbo.PRODUCTION.PD_LOT ASC";
        
        $params = array($itemCode);
        
        // Hapus tanda '@' sementara jika masih tidak muncul untuk melihat errornya (contoh: sqlsrv_query)
        $stmt = @sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (empty($itemName)) {
                    $itemName = $row['ITEM_NAME'];
                }
                $historyData[] = $row;
            }
        }
        @sqlsrv_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Production History - Item <?= htmlspecialchars($itemCode, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #0056b3; margin-bottom: 12px; border-bottom: 2px solid #0056b3; padding-bottom: 6px; font-size: 14px; }
        table.grid-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        table.grid-table th, table.grid-table td { border: 1px solid #bbb; padding: 6px 10px; font-size: 11px; }
        table.grid-table th { background-color: #eaeaea; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .btn { padding: 5px 12px; font-weight: bold; cursor: pointer; border: 1px solid transparent; background: #6c757d; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 11px; border-radius: 3px; }
        .btn-primary { background-color: #0056b3; border-color: #004494; }
    </style>
</head>
<body>
    <div class="panel">
        <div class="panel-title"><i class="fa fa-history"></i> Production History Group by Work Order (WO)</div>
        <div style="margin-bottom: 12px; font-size: 13px;">
            <b>Item Code:</b> <span style="color: #d32f2f; font-weight: bold;"><?= htmlspecialchars($itemCode, ENT_QUOTES, 'UTF-8') ?></span> | 
            <b>Item Name:</b> <span><?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div style="overflow-x: auto;">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>WO Number</th>
                        <th>Lot / Batch</th>
                        <th class="text-right">Prod OK</th>
                        <th class="text-right">Prod NG</th>
                        <th class="text-right">Prod Hold</th>
                        <th class="text-right">Prod Rem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historyData) > 0): ?>
                        <?php 
                        $no = 1; 
                        $totOk = 0; $totNg = 0; $totHo = 0; $totRem = 0;
                        foreach($historyData as $row): 
                            // Pastikan data dikonversi ke angka dengan aman oleh PHP
                            $valOk = (float)$row['PD_OK'];
                            $valNg = (float)$row['PD_NG'];
                            $valHo = (float)$row['PD_HO'];
                            $valRem = (float)$row['PD_REM'];
                            
                            $totOk += $valOk;
                            $totNg += $valNg;
                            $totHo += $valHo;
                            $totRem += $valRem;
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><b><?= htmlspecialchars($row['WO_NUMBER'], ENT_QUOTES, 'UTF-8') ?></b></td>
                            <td class="text-center"><?= htmlspecialchars($row['PD_LOT'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right" style="color: #28a745; font-weight: bold;"><?= number_format($valOk) ?></td>
                            <td class="text-right" style="color: #dc3545; font-weight: bold;"><?= number_format($valNg) ?></td>
                            <td class="text-right" style="color: #ffc107; font-weight: bold;"><?= number_format($valHo) ?></td>
                            <td class="text-right"><?= number_format($valRem) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #eaeaea; font-weight: bold;">
                            <td colspan="3" class="text-right">Grand Total:</td>
                            <td class="text-right" style="color: #28a745;"><?= number_format($totOk) ?></td>
                            <td class="text-right" style="color: #dc3545;"><?= number_format($totNg) ?></td>
                            <td class="text-right" style="color: #ffc107;"><?= number_format($totHo) ?></td>
                            <td class="text-right"><?= number_format($totRem) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 20px; color: #666;">Tidak ada riwayat produksi ditemukan untuk Item Code ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 15px;">
            <button type="button" class="btn btn-primary" onclick="window.close();"><i class="fa fa-times"></i> Tutup Jendela</button>
        </div>
    </div>
</body>
</html>