<?php
// C:\xampp\htdocs\msii\finance\tally_import.php
// PHP 5.4 compatible

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';

if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
} else {
    die('File config database_aging.php tidak ditemukan.');
}

/* =========================
   HELPER
   ========================= */

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function gv($row, $keys, $default) {
    if (!is_array($keys)) {
        $keys = array($keys);
    }

    foreach ($keys as $k) {
        if (isset($row[$k])) {
            return $row[$k];
        }
    }

    return $default;
}

function nval($v) {
    if ($v instanceof DateTime) {
        return 0;
    }

    if ($v === null || $v === '') {
        return 0;
    }

    $s = trim((string)$v);
    $s = str_replace(',', '', $s);

    return (float)$s;
}

function fmtDateView($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    return (string)$v;
}

function fmtDateInput($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v == '') {
        return '';
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('Y-m-d', $t);
    }

    return '';
}

function fmtTallyDate($v) {
    if ($v instanceof DateTime) {
        return $v->format('d-M-Y');
    }

    if ($v == '') {
        return '';
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('d-M-Y', $t);
    }

    return (string)$v;
}

function round4($v) {
    return round((float)$v, 4);
}

function leftPad($s, $len, $char) {
    return str_pad((string)$s, $len, $char, STR_PAD_LEFT);
}

function resultIDR($idr, $currUsd, $currRp, $usd, $jenis) {
    if ($jenis == 'negatif') {
        return '-Rp' . $idr . '@USD ' . $currUsd . '/Rp ' . $currRp . '=-USD' . $usd;
    }

    return 'Rp' . $idr . '@USD ' . $currUsd . '/Rp ' . $currRp . '=USD' . $usd;
}

function fetchRows($stmt) {
    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

/* =========================
   LOAD DATA
   ========================= */

function loadReceiptRows($fromDate, $toDate) {
    $stmt = q("EXECUTE sp_matrial_in_SUPPLIER_tally ?, ?", array($fromDate, $toDate));
    return fetchRows($stmt);
}

function loadSalesRows($fromDate, $toDate, $jenis) {
    if (strtolower($jenis) == 'epson') {
        $stmt = q("EXECUTE sp_LOAD_SALES_tally_EPSON ?, ?", array($fromDate, $toDate));
    } else {
        $stmt = q("EXECUTE SP_LOAD_SALES_tally ?, ?", array($fromDate, $toDate));
    }

    return fetchRows($stmt);
}

function loadSopRows($fromDate) {
    $stmt = q("EXECUTE SP_TAGS_TALLY ?", array($fromDate));
    return fetchRows($stmt);
}

function getCurrRows() {
    $stmt = q("
        SELECT
            CURR_CODE,
            CURR_DESC,
            CURR_SYMBOL,
            CURR_DEC
        FROM CURR
        ORDER BY CURR_CODE
    ", array());

    return fetchRows($stmt);
}

function getRateRows($currCode) {
    if ($currCode == '') {
        $stmt = q("
            SELECT
                CURR_CODE,
                CURR_SDATE,
                CURR_EDATE,
                CURR_RP,
                CURR_USD,
                CURR_MM,
                CURR_YY
            FROM CURR_RAT_TALLY
            WHERE 1 = 0
        ", array());
    } else {
        $stmt = q("
            SELECT
                CURR_CODE,
                CURR_SDATE,
                CURR_EDATE,
                CURR_RP,
                CURR_USD,
                CURR_MM,
                CURR_YY
            FROM CURR_RAT_TALLY
            WHERE CURR_CODE = ?
            ORDER BY CURR_SDATE DESC
        ", array($currCode));
    }

    return fetchRows($stmt);
}

/* =========================
   MASTER RATE ACTION
   ========================= */

function saveCurrencyAction() {
    $currCode   = isset($_POST['curr_code']) ? strtoupper(trim($_POST['curr_code'])) : '';
    $currDesc   = isset($_POST['curr_desc']) ? trim($_POST['curr_desc']) : '';
    $currSymbol = isset($_POST['curr_symbol']) ? trim($_POST['curr_symbol']) : '';
    $currDec    = isset($_POST['curr_dec']) ? trim($_POST['curr_dec']) : '0';

    if ($currCode == '') {
        return 'CURR_CODE belum diisi.';
    }

    $cek = q("SELECT COUNT(*) AS JML FROM CURR WHERE CURR_CODE = ?", array($currCode));
    $rCek = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);

    if ($rCek && $rCek['JML'] > 0) {
        q("
            UPDATE CURR
            SET CURR_DESC = ?,
                CURR_SYMBOL = ?,
                CURR_DEC = ?
            WHERE CURR_CODE = ?
        ", array($currDesc, $currSymbol, $currDec, $currCode));

        return 'Currency berhasil diupdate.';
    } else {
        q("
            INSERT INTO CURR
            (
                CURR_CODE,
                CURR_DESC,
                CURR_SYMBOL,
                CURR_DEC
            )
            VALUES
            (
                ?, ?, ?, ?
            )
        ", array($currCode, $currDesc, $currSymbol, $currDec));

        return 'Currency berhasil ditambahkan.';
    }
}

function deleteCurrencyAction() {
    $currCode = isset($_POST['curr_code_delete']) ? strtoupper(trim($_POST['curr_code_delete'])) : '';

    if ($currCode == '') {
        return 'CURR_CODE kosong.';
    }

    q("DELETE FROM CURR WHERE CURR_CODE = ?", array($currCode));

    return 'Currency berhasil dihapus.';
}

function saveRateAction() {
    $oldCode  = isset($_POST['old_curr_code']) ? trim($_POST['old_curr_code']) : '';
    $oldSdate = isset($_POST['old_sdate']) ? trim($_POST['old_sdate']) : '';
    $oldEdate = isset($_POST['old_edate']) ? trim($_POST['old_edate']) : '';

    $currCode = isset($_POST['rate_curr_code']) ? strtoupper(trim($_POST['rate_curr_code'])) : '';
    $sdate    = isset($_POST['rate_sdate']) ? trim($_POST['rate_sdate']) : '';
    $edate    = isset($_POST['rate_edate']) ? trim($_POST['rate_edate']) : '';
    $currRp   = isset($_POST['rate_rp']) ? trim($_POST['rate_rp']) : '0';
    $currUsd  = isset($_POST['rate_usd']) ? trim($_POST['rate_usd']) : '1';

    if ($currCode == '' || $sdate == '' || $edate == '') {
        return 'Rate belum lengkap.';
    }

    $mm = date('m', strtotime($sdate));
    $yy = date('Y', strtotime($sdate));

    if ($oldCode != '' && $oldSdate != '' && $oldEdate != '') {
        q("
            UPDATE CURR_RAT_TALLY
            SET CURR_CODE = ?,
                CURR_SDATE = ?,
                CURR_EDATE = ?,
                CURR_RP = ?,
                CURR_USD = ?,
                CURR_MM = ?,
                CURR_YY = ?
            WHERE CURR_CODE = ?
              AND CONVERT(varchar(10), CURR_SDATE, 120) = ?
              AND CONVERT(varchar(10), CURR_EDATE, 120) = ?
        ", array(
            $currCode,
            $sdate,
            $edate,
            $currRp,
            $currUsd,
            $mm,
            $yy,
            $oldCode,
            $oldSdate,
            $oldEdate
        ));

        return 'Rate berhasil diupdate.';
    } else {
        q("
            INSERT INTO CURR_RAT_TALLY
            (
                CURR_CODE,
                CURR_SDATE,
                CURR_EDATE,
                CURR_RP,
                CURR_USD,
                CURR_MM,
                CURR_YY
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?
            )
        ", array(
            $currCode,
            $sdate,
            $edate,
            $currRp,
            $currUsd,
            $mm,
            $yy
        ));

        return 'Rate berhasil ditambahkan.';
    }
}

function deleteRateAction() {
    $currCode = isset($_POST['delete_rate_curr_code']) ? trim($_POST['delete_rate_curr_code']) : '';
    $sdate    = isset($_POST['delete_rate_sdate']) ? trim($_POST['delete_rate_sdate']) : '';
    $edate    = isset($_POST['delete_rate_edate']) ? trim($_POST['delete_rate_edate']) : '';

    if ($currCode == '' || $sdate == '' || $edate == '') {
        return 'Data rate tidak lengkap.';
    }

    q("
        DELETE FROM CURR_RAT_TALLY
        WHERE CURR_CODE = ?
          AND CONVERT(varchar(10), CURR_SDATE, 120) = ?
          AND CONVERT(varchar(10), CURR_EDATE, 120) = ?
    ", array($currCode, $sdate, $edate));

    return 'Rate berhasil dihapus.';
}

/* =========================
   IMPORT RECEIPT
   ========================= */

function importReceipt($fromDate, $toDate) {
    $rows = loadReceiptRows($fromDate, $toDate);

    q("DELETE FROM dbo.Tally_RECEIPT", array());

    if (count($rows) == 0) {
        return 0;
    }

    $groups = array();

    foreach ($rows as $r) {
        $rcvNo = gv($r, 'RCV_NO', '');
        $cur = gv($r, 'PO_CUR', '');
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'POD_PRICE', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        if (!isset($groups[$rcvNo])) {
            $groups[$rcvNo] = array(
                'IDR' => 0,
                'USD' => 0,
                'CUR' => $cur,
                'RATE' => $rate
            );
        }

        if ($cur == 'IDR') {
            $totalIDR = round4($qty * $price);
            $groups[$rcvNo]['IDR'] += $totalIDR;

            if ($rate != 0) {
                $groups[$rcvNo]['USD'] += round4($totalIDR / $rate);
            }
        } elseif ($cur == 'USD') {
            $groups[$rcvNo]['USD'] += round4($qty * $price);
        }
    }

    foreach ($groups as $rcvNo => $g) {
        if ($g['CUR'] == 'USD') {
            $groups[$rcvNo]['IDR'] = round4($g['USD'] * $g['RATE']);
        }
    }

    $sqlInsert = "
        INSERT INTO dbo.Tally_RECEIPT
        (
            ICL_NO,
            NO_DS,
            Tanggal,
            Supplier_Code,
            ITEM_CODE,
            ITEM_NAME,
            QTY,
            ITEM_PRICE,
            TOTAL_HARGA,
            TOTAL_HARGA2,
            GROUP_TOTAL,
            BC
        )
        VALUES
        (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ";

    $count = 0;

    foreach ($rows as $r) {
        $rcvNo = gv($r, 'RCV_NO', '');
        $cur = gv($r, 'PO_CUR', '');
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'POD_PRICE', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        $currUsd = gv($r, 'CURR_USD', '');
        $currRp = gv($r, 'CURR_RP', '');

        if ($cur == 'IDR') {
            $unitIDR = $price;
            $unitUSD = ($rate != 0) ? ($price / $rate) : 0;
            $totalIDR = $qty * $price;
            $totalUSD = ($rate != 0) ? ($totalIDR / $rate) : 0;

            $itemPrice = resultIDR(round4($unitIDR), $currUsd, $currRp, round4($unitUSD), 'positif');
            $totalHarga = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'positif');
            $totalHarga2 = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'negatif');
            $groupTotal = resultIDR(round4($groups[$rcvNo]['IDR']), $currUsd, $currRp, round4($groups[$rcvNo]['USD']), 'positif');
        } elseif ($cur == 'USD') {
            $unitUSD = $price;
            $totalUSD = $qty * $price;

            $itemPrice = round4($unitUSD);
            $totalHarga = round4($totalUSD);
            $totalHarga2 = '-' . round4($totalUSD);
            $groupTotal = round4($groups[$rcvNo]['USD']);
        } else {
            $itemPrice = 0;
            $totalHarga = 0;
            $totalHarga2 = 0;
            $groupTotal = 0;
        }

        q($sqlInsert, array(
            $rcvNo,
            gv($r, 'RCV_DONO', ''),
            fmtTallyDate(gv($r, 'RCV_DATE', '')),
            gv($r, 'SUP_CODE', ''),
            gv($r, 'ITEM_CODE', ''),
            gv($r, 'ITEM_NAME', ''),
            $qty,
            $itemPrice,
            $totalHarga,
            $totalHarga2,
            $groupTotal,
            gv($r, 'BC', '')
        ));

        $count++;
    }

    return $count;
}

/* =========================
   IMPORT SALES
   ========================= */

function importSales($fromDate, $toDate, $jenis) {
    $rows = loadSalesRows($fromDate, $toDate, $jenis);

    q("DELETE FROM dbo.Tally_SALES", array());

    if (count($rows) == 0) {
        return 0;
    }

    $groups = array();

    foreach ($rows as $r) {
        $invNo = gv($r, 'DI_INVNO', '');
        $cur = gv($r, 'ITEM_CUR', '');
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'ITEM_COST', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        if (!isset($groups[$invNo])) {
            $groups[$invNo] = array(
                'IDR' => 0,
                'USD' => 0,
                'CUR' => $cur,
                'RATE' => $rate
            );
        }

        if ($cur == 'IDR') {
            $totalIDR = round4($qty * $price);
            $groups[$invNo]['IDR'] += $totalIDR;

            if ($rate != 0) {
                $groups[$invNo]['USD'] += round4($totalIDR / $rate);
            }
        } elseif ($cur == 'USD') {
            $groups[$invNo]['USD'] += round4($qty * $price);
        }
    }

    foreach ($groups as $invNo => $g) {
        if ($g['CUR'] == 'USD') {
            $groups[$invNo]['IDR'] = round4($g['USD'] * $g['RATE']);
        }
    }

    $sqlInsert = "
        INSERT INTO dbo.Tally_SALES
        (
            NO_INVOICE,
            NO_DS,
            Tanggal,
            CUST_CODE,
            ITEM_CODE,
            ITEM_NAME,
            QTY,
            ITEM_PRICE,
            TOTAL_HARGA,
            TOTAL_HARGA2,
            GROUP_TOTAL,
            BC,
            ITEM_NO,
            PO
        )
        VALUES
        (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?
        )
    ";

    $count = 0;

    foreach ($rows as $r) {
        $invNo = gv($r, 'DI_INVNO', '');
        $cur = gv($r, 'ITEM_CUR', '');
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'ITEM_COST', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        $currUsd = gv($r, 'CURR_USD', '');
        $currRp = gv($r, 'CURR_RP', '');

        if ($cur == 'IDR') {
            $unitIDR = $price;
            $unitUSD = ($rate != 0) ? ($price / $rate) : 0;
            $totalIDR = $qty * $price;
            $totalUSD = ($rate != 0) ? ($totalIDR / $rate) : 0;

            $itemPrice = resultIDR(round4($unitIDR), $currUsd, $currRp, round4($unitUSD), 'positif');
            $totalHarga = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'positif');
            $totalHarga2 = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'positif');
            $groupTotal = resultIDR(round4($groups[$invNo]['IDR']), $currUsd, $currRp, round4($groups[$invNo]['USD']), 'negatif');
        } elseif ($cur == 'USD') {
            $unitUSD = $price;
            $totalUSD = $qty * $price;

            $itemPrice = round4($unitUSD);
            $totalHarga = round4($totalUSD);
            $totalHarga2 = round4($totalUSD);
            $groupTotal = '-' . round4($groups[$invNo]['USD']);
        } else {
            $itemPrice = 0;
            $totalHarga = 0;
            $totalHarga2 = 0;
            $groupTotal = 0;
        }

        q($sqlInsert, array(
            $invNo,
            gv($r, 'DI_DSNO', ''),
            fmtTallyDate(gv($r, 'TRAN_DATE', '')),
            gv($r, 'CUST_CODE', ''),
            gv($r, 'ITEM_CODE', ''),
            gv($r, 'ITEM_NAME', ''),
            $qty,
            $itemPrice,
            $totalHarga,
            $totalHarga2,
            $groupTotal,
            gv($r, 'BC', ''),
            gv($r, 'PART_NO', ''),
            gv($r, 'ORDR_PO', '')
        ));

        $count++;
    }

    return $count;
}

/* =========================
   IMPORT SOP
   ========================= */

function importSop($fromDate) {
    $rows = loadSopRows($fromDate);

    q("DELETE FROM dbo.Tally_SOP", array());

    if (count($rows) == 0) {
        return 0;
    }

    $sqlInsert = "
        INSERT INTO dbo.Tally_SOP
        (
            Nomor,
            Tanggal,
            ITEM_CODE,
            ITEM_NAME,
            QTY
        )
        VALUES
        (
            ?, ?, ?, ?, ?
        )
    ";

    $count = 0;

    foreach ($rows as $r) {
        $dateVal = gv($r, 'SOP_SDATE', '');

        if ($dateVal instanceof DateTime) {
            $yy = $dateVal->format('y');
            $mm = $dateVal->format('m');
        } else {
            $t = strtotime((string)$dateVal);

            if ($t === false) {
                $yy = date('y');
                $mm = date('m');
            } else {
                $yy = date('y', $t);
                $mm = date('m', $t);
            }
        }

        $urut = gv($r, 'Urut', 0);
        $nomor = 'SOP/' . $yy . '/' . $mm . '/' . leftPad($urut, 4, '0');

        q($sqlInsert, array(
            $nomor,
            fmtTallyDate($dateVal),
            gv($r, 'ITEM_CODE', ''),
            gv($r, 'ITEM_NAME', ''),
            nval(gv($r, 'STQTY', 0))
        ));

        $count++;
    }

    return $count;
}

/* =========================
   REQUEST HANDLER
   ========================= */

$fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$toDate   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
$rateCurr = '';

if (isset($_POST['rate_curr_selected'])) {
    $rateCurr = trim($_POST['rate_curr_selected']);
} elseif (isset($_GET['rate_curr'])) {
    $rateCurr = trim($_GET['rate_curr']);
}

$tab = isset($_POST['tab']) ? $_POST['tab'] : '';
if ($tab == '') {
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'receipt';
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

$message = '';
$rows = array();
$currRows = array();
$rateRows = array();

if ($action == 'save_curr') {
    $tab = 'rate';
    $message = saveCurrencyAction();
} elseif ($action == 'delete_curr') {
    $tab = 'rate';
    $message = deleteCurrencyAction();
} elseif ($action == 'save_rate') {
    $tab = 'rate';
    $message = saveRateAction();
    $rateCurr = isset($_POST['rate_curr_code']) ? strtoupper(trim($_POST['rate_curr_code'])) : $rateCurr;
} elseif ($action == 'delete_rate') {
    $tab = 'rate';
    $rateCurr = isset($_POST['delete_rate_curr_code']) ? strtoupper(trim($_POST['delete_rate_curr_code'])) : $rateCurr;
    $message = deleteRateAction();
} elseif ($action == 'load_receipt') {
    $tab = 'receipt';
    $rows = loadReceiptRows($fromDate, $toDate);
} elseif ($action == 'import_receipt') {
    $tab = 'receipt';
    $count = importReceipt($fromDate, $toDate);
    $message = 'Import Receipt selesai. Total baris: ' . $count;
    $rows = loadReceiptRows($fromDate, $toDate);
} elseif ($action == 'load_sales') {
    $tab = 'sales';
    $rows = loadSalesRows($fromDate, $toDate, 'normal');
} elseif ($action == 'import_sales') {
    $tab = 'sales';
    $count = importSales($fromDate, $toDate, 'normal');
    $message = 'Import Sales selesai. Total baris: ' . $count;
    $rows = loadSalesRows($fromDate, $toDate, 'normal');
} elseif ($action == 'load_sop') {
    $tab = 'sop';
    $rows = loadSopRows($fromDate);
} elseif ($action == 'import_sop') {
    $tab = 'sop';
    $count = importSop($fromDate);
    $message = 'Import Stock Opname selesai. Total baris: ' . $count;
    $rows = loadSopRows($fromDate);
} elseif ($action == 'load_sales_epson') {
    $tab = 'epson';
    $rows = loadSalesRows($fromDate, $toDate, 'epson');
} elseif ($action == 'import_sales_epson') {
    $tab = 'epson';
    $count = importSales($fromDate, $toDate, 'epson');
    $message = 'Import Sales EPSON selesai. Total baris: ' . $count;
    $rows = loadSalesRows($fromDate, $toDate, 'epson');
}

if ($tab == 'rate') {
    $currRows = getCurrRows();

    if ($rateCurr == '' && count($currRows) > 0) {
        $rateCurr = gv($currRows[0], 'CURR_CODE', '');
    }

    $rateRows = getRateRows($rateCurr);
}
?>

<?php include 'layout.php'; ?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">IMPORT SQL TO TALLY</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-success">
            <?php echo h($message); ?>
        </div>
    <?php } ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="post" id="frmTally">
                <input type="hidden" name="tab" id="tab" value="<?php echo h($tab); ?>">
                <input type="hidden" name="action" id="action" value="">

                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Dari</label>
                        <input type="date"
                               name="from_date"
                               id="from_date"
                               class="form-control"
                               value="<?php echo h($fromDate); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Sampai</label>
                        <input type="date"
                               name="to_date"
                               id="to_date"
                               class="form-control"
                               value="<?php echo h($toDate); ?>">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'receipt') ? 'active' : ''; ?>"
               href="tally_import.php?tab=receipt">Receipt</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'sales') ? 'active' : ''; ?>"
               href="tally_import.php?tab=sales">Sales</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'sop') ? 'active' : ''; ?>"
               href="tally_import.php?tab=sop">Stock Opname</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'rate') ? 'active' : ''; ?>"
               href="tally_import.php?tab=rate">MASTER_RATE</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'epson') ? 'active' : ''; ?>"
               href="tally_import.php?tab=epson">Sales EPSON</a>
        </li>
    </ul>

    <div class="card shadow-sm">
        <div class="card-body">

            <?php if ($tab == 'receipt') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_receipt')">
                        Load
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_receipt')">
                        Import
                    </button>
                </div>

                <?php renderReceiptTable($rows); ?>

            <?php } elseif ($tab == 'sales') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_sales')">
                        Load
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_sales')">
                        Import
                    </button>
                </div>

                <?php renderSalesTable($rows); ?>

            <?php } elseif ($tab == 'sop') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_sop')">
                        Load
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_sop')">
                        Import
                    </button>
                </div>

                <?php renderSopTable($rows); ?>

            <?php } elseif ($tab == 'rate') { ?>

                <div class="row">

                    <div class="col-md-4">
                        <h5 class="fw-bold">Currency</h5>

                        <form method="post" class="card card-body mb-3">
                            <input type="hidden" name="tab" value="rate">
                            <input type="hidden" name="action" value="save_curr">

                            <div class="mb-2">
                                <label class="form-label">CURR_CODE</label>
                                <input type="text" name="curr_code" id="curr_code" class="form-control" maxlength="10" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">CURR_DESC</label>
                                <input type="text" name="curr_desc" id="curr_desc" class="form-control" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">CURR_SYMBOL</label>
                                <input type="text" name="curr_symbol" id="curr_symbol" class="form-control">
                            </div>

                            <div class="mb-2">
                                <label class="form-label">CURR_DEC</label>
                                <input type="number" name="curr_dec" id="curr_dec" class="form-control" value="0">
                            </div>

                            <button type="submit" class="btn btn-success">
                                Simpan Currency
                            </button>

                            <button type="button" class="btn btn-secondary mt-2" onclick="clearCurrForm()">
                                Baru
                            </button>
                        </form>

                        <div class="table-responsive" style="max-height:500px;">
                            <table class="table table-bordered table-striped table-sm mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>CURR_CODE</th>
                                        <th>CURR_DESC</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currRows as $c) { ?>
    <?php
    $cCode = gv($c, 'CURR_CODE', '');
    $activeStyle = '';

    if ($cCode == $rateCurr) {
        $activeStyle = 'background:#0d6efd;color:#fff;font-weight:bold;';
    }
    ?>
    <tr style="<?php echo $activeStyle; ?>">
        <td>
            <a href="tally_import.php?tab=rate&rate_curr=<?php echo urlencode($cCode); ?>"
               style="<?php echo ($cCode == $rateCurr) ? 'color:#fff;text-decoration:none;' : 'text-decoration:none;'; ?>">
                <?php echo h($cCode); ?>
            </a>
        </td>

        <td>
            <a href="tally_import.php?tab=rate&rate_curr=<?php echo urlencode($cCode); ?>"
               style="<?php echo ($cCode == $rateCurr) ? 'color:#fff;text-decoration:none;' : 'text-decoration:none;'; ?>">
                <?php echo h(gv($c, 'CURR_DESC', '')); ?>
            </a>
        </td>

        <td>
            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    onclick="editCurr('<?php echo h(gv($c, 'CURR_CODE', '')); ?>',
                                     '<?php echo h(gv($c, 'CURR_DESC', '')); ?>',
                                     '<?php echo h(gv($c, 'CURR_SYMBOL', '')); ?>',
                                     '<?php echo h(gv($c, 'CURR_DEC', '')); ?>')">
                Edit
            </button>

            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus currency ini?');">
                <input type="hidden" name="tab" value="rate">
                <input type="hidden" name="action" value="delete_curr">
                <input type="hidden" name="rate_curr_selected" value="<?php echo h($rateCurr); ?>">
                <input type="hidden" name="curr_code_delete" value="<?php echo h(gv($c, 'CURR_CODE', '')); ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    Del
                </button>
            </form>
        </td>
    </tr>
<?php } ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="col-md-8">
                        <h5 class="fw-bold">
    Rate <?php echo ($rateCurr != '') ? ' - ' . h($rateCurr) : ''; ?>
</h5>

                        <form method="post" class="card card-body mb-3" id="frmRate">
                            <input type="hidden" name="tab" value="rate">
                            <input type="hidden" name="action" value="save_rate">
							<input type="hidden" name="rate_curr_selected" id="rate_curr_selected" value="<?php echo h($rateCurr); ?>">

                            <input type="hidden" name="old_curr_code" id="old_curr_code">
                            <input type="hidden" name="old_sdate" id="old_sdate">
                            <input type="hidden" name="old_edate" id="old_edate">

                            <div class="row g-2">

                                <div class="col-md-2">
                                    <label class="form-label">CURR</label>
                                    <select name="rate_curr_code" id="rate_curr_code" class="form-control" required>
                                        <option value="">--</option>
<?php foreach ($currRows as $c) { ?>
    <?php
    $codeOpt = gv($c, 'CURR_CODE', '');
    $selected = ($codeOpt == $rateCurr) ? 'selected' : '';
    ?>
    <option value="<?php echo h($codeOpt); ?>" <?php echo $selected; ?>>
        <?php echo h($codeOpt); ?>
    </option>
<?php } ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="rate_sdate" id="rate_sdate" class="form-control" required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="rate_edate" id="rate_edate" class="form-control" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">CURR_IDR</label>
                                    <input type="text" name="rate_rp" id="rate_rp" class="form-control" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">CURR_USD</label>
                                    <input type="text" name="rate_usd" id="rate_usd" class="form-control" value="1" required>
                                </div>

                                <div class="col-md-12 mt-2">
                                    <button type="submit" class="btn btn-success">
                                        Simpan Rate
                                    </button>

                                    <button type="button" class="btn btn-secondary" onclick="clearRateForm()">
                                        Baru
                                    </button>
                                </div>

                            </div>
                        </form>

                        <div class="table-responsive" style="max-height:500px;">
                            <table class="table table-bordered table-striped table-sm mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>CURR_CODE</th>
                                        <th>CURR_SDATE</th>
                                        <th>CURR_EDATE</th>
                                        <th>CURR_IDR</th>
                                        <th>CURR_USD</th>
                                        <th>CURR_MM</th>
                                        <th>CURR_YY</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($rateRows as $r) { ?>
                                        <?php
                                        $code = gv($r, 'CURR_CODE', '');
                                        $sdate = fmtDateInput(gv($r, 'CURR_SDATE', ''));
                                        $edate = fmtDateInput(gv($r, 'CURR_EDATE', ''));
                                        $rp = gv($r, 'CURR_RP', '');
                                        $usd = gv($r, 'CURR_USD', '');
                                        $mm = gv($r, 'CURR_MM', '');
                                        $yy = gv($r, 'CURR_YY', '');
                                        ?>
                                        <tr>
                                            <td><?php echo h($code); ?></td>
                                            <td><?php echo h($sdate); ?></td>
                                            <td><?php echo h($edate); ?></td>
                                            <td class="text-end"><?php echo h($rp); ?></td>
                                            <td class="text-end"><?php echo h($usd); ?></td>
                                            <td><?php echo h($mm); ?></td>
                                            <td><?php echo h($yy); ?></td>
                                            <td>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        onclick="editRate('<?php echo h($code); ?>',
                                                                          '<?php echo h($sdate); ?>',
                                                                          '<?php echo h($edate); ?>',
                                                                          '<?php echo h($rp); ?>',
                                                                          '<?php echo h($usd); ?>')">
                                                    Edit
                                                </button>

                                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus rate ini?');">
                                                    <input type="hidden" name="tab" value="rate">
                                                    <input type="hidden" name="action" value="delete_rate">
                                                    <input type="hidden" name="delete_rate_curr_code" value="<?php echo h($code); ?>">
                                                    <input type="hidden" name="delete_rate_sdate" value="<?php echo h($sdate); ?>">
                                                    <input type="hidden" name="delete_rate_edate" value="<?php echo h($edate); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Del
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>

                            </table>
                        </div>

                    </div>

                </div>

            <?php } elseif ($tab == 'epson') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_sales_epson')">
                        Load
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_sales_epson')">
                        Import
                    </button>
                </div>

                <?php renderSalesTable($rows); ?>

            <?php } ?>

        </div>
    </div>

</div>

<script>
function submitTally(actionName) {
    document.getElementById('action').value = actionName;
    document.getElementById('frmTally').submit();
}

function confirmImport(actionName) {
    if (confirm('Import akan menghapus data Tally lama dan insert ulang. Lanjutkan?')) {
        submitTally(actionName);
    }
}

function editCurr(code, desc, symbol, dec) {
    document.getElementById('curr_code').value = code;
    document.getElementById('curr_desc').value = desc;
    document.getElementById('curr_symbol').value = symbol;
    document.getElementById('curr_dec').value = dec;
    document.getElementById('curr_code').focus();
}

function clearCurrForm() {
    document.getElementById('curr_code').value = '';
    document.getElementById('curr_desc').value = '';
    document.getElementById('curr_symbol').value = '';
    document.getElementById('curr_dec').value = '0';
    document.getElementById('curr_code').focus();
}

function editRate(code, sdate, edate, rp, usd) {
    document.getElementById('rate_curr_code').value = code;
    document.getElementById('rate_sdate').value = sdate;
    document.getElementById('rate_edate').value = edate;
    document.getElementById('rate_rp').value = rp;
    document.getElementById('rate_usd').value = usd;

    document.getElementById('old_curr_code').value = code;
    document.getElementById('old_sdate').value = sdate;
    document.getElementById('old_edate').value = edate;

    document.getElementById('rate_curr_code').focus();
}

function clearRateForm() {
    document.getElementById('rate_curr_code').value = document.getElementById('rate_curr_selected').value;
    document.getElementById('rate_sdate').value = '';
    document.getElementById('rate_edate').value = '';
    document.getElementById('rate_rp').value = '';
    document.getElementById('rate_usd').value = '1';

    document.getElementById('old_curr_code').value = '';
    document.getElementById('old_sdate').value = '';
    document.getElementById('old_edate').value = '';

    document.getElementById('rate_sdate').focus();
}
</script>

</body>
</html>

<?php
/* =========================
   RENDER TABLE
   ========================= */

function renderTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data.</div>';
        return;
    }

    echo '<div class="table-responsive" style="max-height:650px;">';
    echo '<table class="table table-bordered table-striped table-sm mb-0">';
    echo '<thead class="table-dark sticky-top"><tr>';

    foreach ($rows[0] as $k => $v) {
        echo '<th style="white-space:nowrap;">' . h($k) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';

        foreach ($r as $v) {
            echo '<td style="white-space:nowrap;">' . h(fmtDateView($v)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function renderReceiptTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Data Receipt belum ada.</div>';
        return;
    }

    echo '<div class="table-responsive" style="max-height:650px;">';
    echo '<table class="table table-bordered table-striped table-sm mb-0">';
    echo '<thead class="table-dark sticky-top">';
    echo '<tr>';
    echo '<th>ICL_NO</th>';
    echo '<th>NO DS</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Code Supplier</th>';
    echo '<th>Kode Barang</th>';
    echo '<th>Nama Barang</th>';
    echo '<th>Qty</th>';
    echo '<th>Harga per pcs</th>';
    echo '<th>Currency</th>';
    echo '<th>BC</th>';
    echo '</tr>';
    echo '</thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . h(gv($r, 'RCV_NO', '')) . '</td>';
        echo '<td>' . h(gv($r, 'RCV_DONO', '')) . '</td>';
        echo '<td>' . h(fmtDateView(gv($r, 'RCV_DATE', ''))) . '</td>';
        echo '<td>' . h(gv($r, 'SUP_CODE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_CODE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_NAME', '')) . '</td>';
        echo '<td class="text-end">' . h(gv($r, 'QTY', '')) . '</td>';
        echo '<td class="text-end">' . h(gv($r, 'POD_PRICE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'PO_CUR', '')) . '</td>';
        echo '<td>' . h(gv($r, 'BC', '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function renderSalesTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Data Sales belum ada.</div>';
        return;
    }

    echo '<div class="table-responsive" style="max-height:650px;">';
    echo '<table class="table table-bordered table-striped table-sm mb-0">';
    echo '<thead class="table-dark sticky-top">';
    echo '<tr>';
    echo '<th>No Invoice</th>';
    echo '<th>No DS</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Customer</th>';
    echo '<th>Kode Barang</th>';
    echo '<th>Nama Barang</th>';
    echo '<th>Qty</th>';
    echo '<th>Harga per pcs</th>';
    echo '<th>Unit</th>';
    echo '<th>Currency</th>';
    echo '<th>BC</th>';
    echo '<th>Item No</th>';
    echo '<th>PO</th>';
    echo '</tr>';
    echo '</thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . h(gv($r, 'DI_INVNO', '')) . '</td>';
        echo '<td>' . h(gv($r, 'DI_DSNO', '')) . '</td>';
        echo '<td>' . h(fmtDateView(gv($r, 'TRAN_DATE', ''))) . '</td>';
        echo '<td>' . h(gv($r, 'CUST_CODE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_CODE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_NAME', '')) . '</td>';
        echo '<td class="text-end">' . h(gv($r, 'QTY', '')) . '</td>';
        echo '<td class="text-end">' . h(gv($r, 'ITEM_COST', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_UNIT', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_CUR', '')) . '</td>';
        echo '<td>' . h(gv($r, 'BC', '')) . '</td>';
        echo '<td>' . h(gv($r, 'PART_NO', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ORDR_PO', '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function renderSopTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Data Stock Opname belum ada.</div>';
        return;
    }

    echo '<div class="table-responsive" style="max-height:650px;">';
    echo '<table class="table table-bordered table-striped table-sm mb-0">';
    echo '<thead class="table-dark sticky-top">';
    echo '<tr>';
    echo '<th>Nomor</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Item Code</th>';
    echo '<th>Item Name</th>';
    echo '<th>Qty</th>';
    echo '</tr>';
    echo '</thead><tbody>';

    foreach ($rows as $r) {
        $dateVal = gv($r, 'SOP_SDATE', '');

        if ($dateVal instanceof DateTime) {
            $yy = $dateVal->format('y');
            $mm = $dateVal->format('m');
        } else {
            $t = strtotime((string)$dateVal);

            if ($t === false) {
                $yy = date('y');
                $mm = date('m');
            } else {
                $yy = date('y', $t);
                $mm = date('m', $t);
            }
        }

        $nomor = 'SOP/' . $yy . '/' . $mm . '/' . leftPad(gv($r, 'Urut', 0), 4, '0');

        echo '<tr>';
        echo '<td>' . h($nomor) . '</td>';
        echo '<td>' . h(fmtDateView($dateVal)) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_CODE', '')) . '</td>';
        echo '<td>' . h(gv($r, 'ITEM_NAME', '')) . '</td>';
        echo '<td class="text-end">' . h(gv($r, 'STQTY', '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
?>