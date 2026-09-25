<?php
if (session_id() == "") {
    session_start();
}

// Konfigurasi khusus PPIC
require_once __DIR__ . "/../config/database_ppic.php";

if ($conn === false) {
    echo "Koneksi database gagal.";
    exit();
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// 1. MASUKKAN SEMUA MENU KE DALAM ARRAY (Khusus PPIC Plant 1)
$rawReports = [
    // Kategori: Master & Planning
    ["name" => "BOM(BILL OF MATERIAL)", "url" => "bom.php", "category" => "Master & Planning", "target" => "_blank"],
    ["name" => "MPS", "url" => "mps.php", "category" => "Master & Planning", "target" => "_blank"],
    ["name" => "MRP", "url" => "mrp.php", "category" => "Master & Planning", "target" => "_blank"],
    ["name" => "MOR", "url" => "mor.php", "category" => "Master & Planning", "target" => "_blank"],

    // Kategori: Capacity & Production
    ["name" => "LOADING CAPACITY PER TONASE", "url" => "loading_capacity_tonase.php", "category" => "Capacity & Production", "target" => "_blank"],
    ["name" => "MACHINE CAPICITY", "url" => "mc_capacity.php", "category" => "Capacity & Production", "target" => "_blank"],
    ["name" => "MACHINE CAPICITY PLAN 3 MONTH", "url" => "capacity_plan.php", "category" => "Capacity & Production", "target" => "_blank"],
    ["name" => "LOADING CAPACITY YEARLY", "url" => "loading_capacity_yearly.php", "category" => "Capacity & Production", "target" => "_blank"],
    ["name" => "Montly Injection Report", "url" => "monthly_injection.php", "category" => "Capacity & Production", "target" => "_blank"],
    ["name" => "MC RUN", "url" => "report_mc_run.php", "category" => "Capacity & Production", "target" => "_blank"],

    // Kategori: Material & Cost
    ["name" => "SALES & MATERIAL USE PLAN USD", "url" => "sales_material_cost_usd.php", "category" => "Material & Cost", "target" => "_blank"],
    ["name" => "SALES & MATERIAL USE ACTUAL USD", "url" => "actual_material_cost_usd.php", "category" => "Material & Cost", "target" => "_blank"],
    ["name" => "SALES & MATERIAL USE PLAN IDR", "url" => "sales_material_cost_idr.php", "category" => "Material & Cost", "target" => "_blank"],
    ["name" => "SALES & MATERIAL USE ACTUAL IDR", "url" => "actual_material_cost_idr.php", "category" => "Material & Cost", "target" => "_blank"],
    ["name" => "INSTRUCTION MATERIAL CHILD PART", "url" => "report_material_child.php", "category" => "Material & Cost", "target" => "_blank"],

    // Kategori: Inventory & Stock
    ["name" => "STOK PLAN VS ACTUAL", "url" => "report_stok_actual.php", "category" => "Inventory & Stock", "target" => "_blank"],
    ["name" => "STOK PLAN VS ACTUAL PART COMMON", "url" => "part_common.php", "category" => "Inventory & Stock", "target" => "_blank"],
    ["name" => "DEAD STOCK", "url" => "dead_stok.php", "category" => "Inventory & Stock", "target" => "_blank"]
];

// 2. URUTKAN A-Z BERDASARKAN NAMA REPORT
usort($rawReports, function($a, $b) {
    return strcmp($a["name"], $b["name"]);
});

// 3. KELOMPOKKAN BERDASARKAN KATEGORI
$groupedReports = [];
foreach ($rawReports as $report) {
    $groupedReports[$report['category']][] = $report;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report Menu</title>
    <style>
        body {
            margin: 0; padding: 18px; background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000;
        }
        .content { width: 980px; margin: 0 auto; box-sizing: border-box; }
        .header-box { border: 1px solid #808080; background: #eeeeee; padding: 15px; margin-bottom: 14px; text-align: center; }
        .header-title { font-size: 26px; font-weight: bold; margin-bottom: 8px; }
        .info-box { border: 1px solid #808080; background: #ffffff; padding: 10px; margin-bottom: 14px; line-height: 22px; }
        
        .report-box { border: 1px solid #808080; background: #f0f0f0; padding: 14px; box-sizing: border-box; }
        
        /* Search Bar Style */
        .search-container { margin-bottom: 15px; text-align: right; }
        .search-input { padding: 6px 10px; width: 250px; font-family: Tahoma; border: 1px solid #808080; }
        
        /* Category Header */
        .category-header { font-size: 14px; font-weight: bold; background: #808080; color: white; padding: 5px 10px; margin: 15px 0 10px 0; }
        
        .report-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .report-item { border: 1px solid #808080; background: #ffffff; padding: 10px; min-height: 70px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; }
        .report-name { font-weight: bold; margin-bottom: 8px; }
        
        button, a.button { display: inline-block; text-decoration: none; color: #000000; background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 14px; font-size: 12px; cursor: pointer; text-align: center; font-family: Tahoma, Arial, sans-serif; }
        button:active, a.button:active { border: 2px inset #ffffff; }
        .status { border: 1px solid #808080; background: #ffffff; padding: 8px; margin-top: 12px; }
        .footer { margin-top: 18px; text-align: center; color: #333333; font-size: 11px; }
    </style>
</head>
<body>

<div class="content">
    <div class="header-box">
        <div class="header-title">REPORT MENU</div>
        <div class="header-subtitle">PPIC System plant 1</div>
    </div>

    <div class="info-box">
        Login sebagai: <b><?php echo h($dbUser); ?></b><br>
        Server: <b>192.168.0.4</b> &nbsp; | &nbsp; Database: <b>msData</b> &nbsp; | &nbsp; Status: <b>Connected</b><br>
        Login time: <b><?php echo h($loginTime); ?></b>
    </div>

    <div class="report-box">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #808080; margin-bottom: 10px; padding-bottom: 8px;">
            <div style="font-size: 18px; font-weight: bold;">Report List</div>
            <div class="search-container">
                <input type="text" id="searchInput" class="search-input" placeholder="Cari nama report..." onkeyup="filterReports()">
            </div>
        </div>

        <div id="reportContainer">
            <?php foreach ($groupedReports as $category => $reports): ?>
                <div class="category-section">
                    <div class="category-header"><?php echo h($category); ?></div>
                    <div class="report-grid">
                        <?php foreach ($reports as $r): ?>
                            <div class="report-item" data-name="<?php echo strtolower(h($r['name'])); ?>">
                                <div class="report-name"><?php echo h($r['name']); ?></div>
                                <?php if (isset($r['onclick'])): ?>
                                    <button type="button" onclick="<?php echo h($r['onclick']); ?>">OPEN</button>
                                <?php else: ?>
                                    <a class="button" href="<?php echo h($r['url']); ?>" <?php echo $r['target'] ? 'target="'.h($r['target']).'"' : ''; ?>>OPEN</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="status">Status koneksi: <b>Connected</b></div>
    <div class="footer">P.T. IMC TEKNO INDONESIA - Ordering System plant 1</div>
</div>

<script>
function notReady(reportName) {
    alert("Report belum dibuat: " + reportName);
}

// Fungsi Live Search
function filterReports() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let categories = document.querySelectorAll('.category-section');

    categories.forEach(category => {
        let items = category.querySelectorAll('.report-item');
        let hasVisibleItem = false;

        items.forEach(item => {
            let name = item.getAttribute('data-name');
            if (name.includes(input)) {
                item.style.display = "flex";
                hasVisibleItem = true;
            } else {
                item.style.display = "none";
            }
        });

        // Sembunyikan kategori jika semua report di dalamnya tidak cocok dengan pencarian
        category.style.display = hasVisibleItem ? "block" : "none";
    });
}
</script>

</body>
</html>