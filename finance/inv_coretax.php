<?php
// /msii/finance/inv_coretax.php

// Cari config otomatis
$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';

if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
} else {
    die('File config database_aging.php tidak ditemukan.');
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmt($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v === null) {
        return '';
    }

    return (string)$v;
}

function getCoretaxRows($invNo) {
    $cols = array();
    $rows = array();

    $stmt = q("EXEC dbo.SP_INVOICE_CORETAX ?", array($invNo));

    $meta = sqlsrv_field_metadata($stmt);

    if ($meta !== false) {
        foreach ($meta as $m) {
            $cols[] = $m['Name'];
        }
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return array($cols, $rows);
}

/* =========================
   AUTOCOMPLETE INVOICE
   ========================= */
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'search_invoice') {
    header('Content-Type: application/json; charset=utf-8');

    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    if ($term == '') {
        echo json_encode(array());
        exit;
    }

    $sql = "
        SELECT TOP 30
            DI.DI_INVNO,
            CONVERT(varchar(10), DI.DI_DATE, 120) AS DI_DATE,
            ISNULL(CUST.CUST_COMP, '') AS CUST_COMP
        FROM dbo.DI DI
        LEFT JOIN dbo.CUST CUST ON CUST.CUST_ID = DI.CUST_ID
        WHERE DI.DI_INVNO LIKE ?
        ORDER BY DI.DI_DATE DESC, DI.DI_INVNO DESC
    ";

    $stmt = q($sql, array('%' . $term . '%'));

    $data = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array(
            'inv_no' => $row['DI_INVNO'],
            'label'  => $row['DI_INVNO'] . ' | ' . $row['DI_DATE'] . ' | ' . $row['CUST_COMP']
        );
    }

    echo json_encode($data);
    exit;
}

/* =========================
   EXPORT EXCEL
   ========================= */
if ($action == 'export') {
    $invNo = isset($_GET['inv_no']) ? trim($_GET['inv_no']) : '';

    if ($invNo == '') {
        die('Invoice No kosong.');
    }

    $result = getCoretaxRows($invNo);
    $cols = $result[0];
    $rows = $result[1];

    $filename = 'CORETAX_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $invNo) . '.xls';

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr>";

    foreach ($cols as $c) {
        echo "<th>" . h($c) . "</th>";
    }

    echo "</tr>";

    foreach ($rows as $r) {
        echo "<tr>";

        foreach ($cols as $c) {
            $val = isset($r[$c]) ? $r[$c] : '';
            echo "<td>" . h(fmt($val)) . "</td>";
        }

        echo "</tr>";
    }

    echo "</table>";
    exit;
}

/* =========================
   LOAD REPORT HANYA SAAT POST
   ========================= */
$invNo = '';
$cols = array();
$rows = array();
$isLoaded = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_load'])) {
    $invNo = isset($_POST['inv_no']) ? trim($_POST['inv_no']) : '';

    if ($invNo != '') {
        $result = getCoretaxRows($invNo);
        $cols = $result[0];
        $rows = $result[1];
        $isLoaded = true;
    }
}
?>

<?php include 'layout.php'; ?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">Invoice Coretax Report</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white fw-bold">
            Parameter Report
        </div>

        <div class="card-body">
            <form method="post" autocomplete="off">
                <div class="row g-3 align-items-end">

                    <div class="col-md-5 position-relative">
                        <label class="form-label fw-bold">Invoice No</label>
                        <input type="text"
                               name="inv_no"
                               id="inv_no"
                               class="form-control"
                               value="<?php echo h($invNo); ?>"
                               placeholder="Ketik Invoice No..."
                               required>

                        <div id="autocomplete-box"
                             class="list-group position-absolute w-100"
                             style="z-index:9999; display:none; max-height:260px; overflow-y:auto;">
                        </div>
                    </div>

                    <div class="col-md-7">
                        <button type="submit" name="btn_load" class="btn btn-success">
                            <i class="bi bi-search"></i> Load Report
                        </button>

                        <?php if ($isLoaded && count($rows) > 0) { ?>
                            <a href="inv_coretax.php?action=export&inv_no=<?php echo urlencode($invNo); ?>"
                               class="btn btn-primary">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>

                            <button type="button" onclick="window.print()" class="btn btn-dark">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        <?php } ?>

                        <a href="inv_coretax.php" class="btn btn-outline-danger">
                            Clear
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <?php if ($isLoaded) { ?>

        <?php if (count($rows) == 0) { ?>
            <div class="alert alert-warning">
                Data tidak ditemukan untuk invoice <b><?php echo h($invNo); ?></b>.
            </div>
        <?php } else { ?>

            <div class="alert alert-info">
                Invoice: <b><?php echo h($invNo); ?></b> |
                Total Baris: <b><?php echo count($rows); ?></b>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-bold">
                    Hasil Report Coretax
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:650px;">
                        <table class="table table-bordered table-striped table-sm mb-0">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <?php foreach ($cols as $c) { ?>
                                        <th style="white-space:nowrap;">
                                            <?php echo h($c); ?>
                                        </th>
                                    <?php } ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rows as $r) { ?>
                                    <tr>
                                        <?php foreach ($cols as $c) { ?>
                                            <td style="white-space:nowrap;">
                                                <?php
                                                $val = isset($r[$c]) ? $r[$c] : '';
                                                echo h(fmt($val));
                                                ?>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

    <?php } else { ?>

        <div class="alert alert-secondary">
            Silakan isi parameter <b>Invoice No</b>, lalu klik <b>Load Report</b>.
        </div>

    <?php } ?>

</div>

<script>
var input = document.getElementById('inv_no');
var box = document.getElementById('autocomplete-box');
var timer = null;

input.onkeyup = function(e) {
    e = e || window.event;

    var term = input.value.replace(/^\s+|\s+$/g, '');

    if (e.keyCode == 13) {
        box.style.display = 'none';
        return;
    }

    clearTimeout(timer);

    if (term.length < 2) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    timer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'inv_coretax.php?action=search_invoice&term=' + encodeURIComponent(term), true);

        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    var data = [];

                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch (err) {
                        box.style.display = 'none';
                        return;
                    }

                    box.innerHTML = '';

                    if (!data || data.length == 0) {
                        box.style.display = 'none';
                        return;
                    }

                    for (var i = 0; i < data.length; i++) {
                        (function(item) {
                            var a = document.createElement('button');
                            a.type = 'button';
                            a.className = 'list-group-item list-group-item-action';
                            a.innerHTML = item.label;

                            a.onclick = function() {
                                input.value = item.inv_no;
                                box.style.display = 'none';
                            };

                            box.appendChild(a);
                        })(data[i]);
                    }

                    box.style.display = 'block';
                } else {
                    box.style.display = 'none';
                }
            }
        };

        xhr.send();
    }, 250);
};

document.onclick = function(e) {
    e = e || window.event;
    var target = e.target || e.srcElement;

    if (target != input && !isChildOf(target, box)) {
        box.style.display = 'none';
    }
};

function isChildOf(child, parent) {
    while (child) {
        if (child == parent) {
            return true;
        }
        child = child.parentNode;
    }
    return false;
}
</script>

<style>
@media print {
    .btn,
    .card-header,
    form,
    .alert,
    .d-flex {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
    }

    .table-responsive {
        max-height: none !important;
        overflow: visible !important;
    }

    table {
        font-size: 10px;
    }
}
</style>

</body>
</html>