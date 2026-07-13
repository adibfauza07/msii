<?php
require_once __DIR__ . "/../config/database_ppic.php";

$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$to_date   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-t');
$cust_code = isset($_POST['cust_code']) ? $_POST['cust_code'] : '';
$action    = isset($_POST['action']) ? $_POST['action'] : '';

$data_stok = array();
$error_msg = "";

if (isset($_POST['action'])) {
    if ($conn) {
        $tsql = "EXEC sp_stok_actual @from_date = ?, @to_date = ?, @cust_code = ?";
        // Jika cust_code kosong, set ke '%' agar tampil semua
        $params = array($from_date, $to_date, ($cust_code == '' ? '%' : $cust_code));
        $stmt = sqlsrv_query($conn, $tsql, $params);
        
        if ($stmt === false) {
            $error_msg = "Gagal menjalankan Stored Procedure.";
        } else {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data_stok[] = $row;
            }
        }
    }
}

// Logika Export ke Excel
if ($action == 'export_excel' && !empty($data_stok)) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Stok_Actual_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "<table border='1'><tr>
            <th>Item Code</th><th>Item Name</th><th>Item No</th><th>Unit</th>
            <th>Tags</th><th>Cust Code</th><th>Cust Comp</th><th>PS</th>
            <th>PD Actual</th><th>Del Sch</th><th>Del Actual</th><th>NG RW</th>
            <th>Sub Qty</th><th>Fore Qty</th>
          </tr>";
    foreach ($data_stok as $row) {
        echo "<tr>
                <td>{$row['item_code']}</td><td>{$row['item_name']}</td>
                <td>{$row['ITEM_NO']}</td><td>{$row['item_unit']}</td>
                <td>{$row['tags']}</td><td>{$row['CUST_CODE']}</td>
                <td>{$row['CUST_COMP']}</td><td>{$row['ps']}</td>
                <td>{$row['pd_actual']}</td><td>{$row['del_sch']}</td>
                <td>{$row['del_actual']}</td><td>{$row['ng_rw']}</td>
                <td>{$row['sub_qty']}</td><td>{$row['fore_qty']}</td>
              </tr>";
    }
    echo "</table>";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Stok Actual</title>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $("#cust_autocomplete").autocomplete({
            source: "get_customer.php",
            minLength: 1
        });
    });
    </script>
    
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filter-form { background-color: #f2f2f2; padding: 15px; text-align: center; }
        table.report-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        table.report-table th, table.report-table td { border: 1px solid #ddd; padding: 8px; }
        table.report-table th { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h2>Laporan Analisa Stok Aktual</h2>
    <div class="filter-form">
        <form method="POST" action="">
            <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
            <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
            <input type="text" name="cust_code" id="cust_autocomplete" value="<?php echo htmlspecialchars($cust_code); ?>" placeholder="Cari Customer...">
            <button type="submit" name="action" value="view">View</button>
            <button type="submit" name="action" value="export_excel">Export Excel</button>
        </form>
    </div>

    <?php if(count($data_stok) > 0): ?>
    <table class="report-table">
        </table>
    <?php endif; ?>
</body>
</html>