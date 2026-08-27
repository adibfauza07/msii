<?php
// C:\xampp\htdocs\msii\finance\tally_import_p2.php
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
   AKSES KHUSUS P2
   ========================= */

 $login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
 $active_plant_access = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

 $allow_tally_access = false;

if ($login_user == 'plant2' || $active_plant_access == 'p2') {
    $allow_tally_access = true;
}

if (!$allow_tally_access) {
    echo "<script>
        alert('Menu Import SQL To Tally P2 hanya untuk login plant2 / Plant P2.');
        window.location.href = 'dashboard.php';
    </script>";
    exit;
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

function getDoNoP2($r) {
    return gv($r, array(
        'DO_NO',
        'DONO',
        'NO_DO',
        'DO_NO2',
        'DI_DONO',
        'RCV_DONO',
        'DI_DSNO',
        'NO_DS',
        'DS_NO'
    ), '');
}

function getInvNoP2($r, $fallback) {
    $inv = gv($r, array(
        'DI_INVNO',
        'INVNO',
        'INV_NO',
        'NO_INVOICE',
        'INVOICE_NO'
    ), '');

    $inv = trim((string)$inv);

    if ($inv == '' && $fallback != '') {
        $inv = trim((string)$fallback);
    }

    return $inv;
}

function fetchRows($stmt) {
    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

/* =========================
   LOAD MASTER
   ========================= */

function loadCustomers() {
    $stmt = q("
        SELECT
            CUST_ID,
            CUST_CODE,
            CUST_COMP,
            CUST_ABBR
        FROM CUST
        WHERE ISNULL(CUST_INACTIVE, 0) = 0
        ORDER BY CUST_CODE
    ", array());

    return fetchRows($stmt);
}

function loadInvoices($fromDate, $toDate, $custId) {
    if ($custId == '') {
        return array();
    }

    $stmt = q("
        SELECT
            DI_INVNO,
            CUST_ID
        FROM DI
        WHERE DI_START_DATE BETWEEN ? AND ?
          AND CUST_ID = ?
        ORDER BY DI_INVNO
    ", array($fromDate, $toDate, $custId));

    return fetchRows($stmt);
}

/* =========================
   LOAD DATA P2
   ========================= */

function loadReceiptRows($fromDate, $toDate) {
    $stmt = q("EXECUTE sp_matrial_in_SUPPLIER ?, ?", array($fromDate, $toDate));
    return fetchRows($stmt);
}

function loadPoRows($fromDate, $toDate, $custId) {
    if ($custId == '') {
        return array();
    }

    $stmt = q("
        SELECT DISTINCT
            O.ORDR_PO
        FROM dbo.CUST C
        INNER JOIN dbo.DI D
            ON C.CUST_ID = D.CUST_ID
        INNER JOIN dbo.DIPA_PAR DP
            ON D.DI_ID = DP.DI_ID
        INNER JOIN dbo.ORDERS O
            ON O.ORDR_ID = DP.ORDR_ID
        WHERE D.DI_DATE BETWEEN ? AND ?
          AND C.CUST_ID = ?
          AND ISNULL(O.ORDR_PO, '') <> ''
        ORDER BY O.ORDR_PO
    ", array($fromDate, $toDate, $custId));

    return fetchRows($stmt);
}

function loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, $salesCurrency) {
    if ($custId == '') {
        return array();
    }

    $invNo = trim((string)$invNo);
    $poNo = trim((string)$poNo);
    $salesCurrency = strtoupper(trim((string)$salesCurrency));

    if ($salesCurrency != 'IDR' && $salesCurrency != 'USD') {
        die('Currency Sales harus IDR atau USD.');
    }

    $where = "
        WHERE D.DI_DATE BETWEEN ? AND ?
          AND C.CUST_ID = ?
          AND UPPER(LTRIM(RTRIM(PV.CURR_CODE))) = ?
    ";

    $params = array($fromDate, $toDate, $custId, $salesCurrency);

    if ($invNo != '') {
        $where .= " AND D.DI_INVNO = ? ";
        $params[] = $invNo;
    }

    if ($poNo != '') {
        $where .= " AND O.ORDR_PO LIKE ? ";
        $params[] = '%' . $poNo . '%';
    }

    $sql = "
        SELECT
            D.DI_INVNO,
            D.DI_DSNO,
            D.DI_DATE AS TRAN_DATE,
            C.CUST_CODE,
            PV.PART_CODE AS ITEM_CODE,
            PV.PART_NAME AS ITEM_NAME,
            DP.QTY,
            DP.PART_PRICE AS ITEM_COST,
            PV.CURR_CODE AS ITEM_CUR,
            PV.PART_UNIT AS ITEM_UNIT,
            CRAT.CURR_RP,
            CRAT.CURR_USD,
            REPLACE(ISNULL(BC.JENIS_BC, ''), ' ', '') + '/' + ISNULL(BC.NOMOR_BC, '') AS BC,
            PV.PART_NO,
            O.ORDR_PO
        FROM dbo.CUST C
        INNER JOIN dbo.DI D
            ON C.CUST_ID = D.CUST_ID
        INNER JOIN dbo.DIPA_PAR DP
            ON D.DI_ID = DP.DI_ID
        INNER JOIN dbo.ORDR_PAR OP
            ON DP.ORDR_ID = OP.ORDR_ID
           AND DP.ORDP_LINO = OP.ORDP_LINO
        INNER JOIN dbo.PART_VIEW PV
            ON PV.PRICE_ID = OP.PRICE_ID
        INNER JOIN dbo.ORDERS O
            ON O.ORDR_ID = DP.ORDR_ID
        INNER JOIN dbo.CURR_RAT_TALLY CRAT
            ON CRAT.CURR_CODE = PV.CURR_CODE
           AND D.DI_DATE BETWEEN CRAT.CURR_SDATE AND CRAT.CURR_EDATE
        LEFT OUTER JOIN dbo.BC_TRANS BC
            ON BC.NO_TRANS = D.DI_DSNO
        " . $where . "
        ORDER BY D.DI_INVNO, D.DI_DSNO, O.ORDR_PO, PV.PART_CODE
    ";

    $stmt = q($sql, $params);
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
                CURR_USD
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
                CURR_USD
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

    if ($oldCode != '' && $oldSdate != '' && $oldEdate != '') {
        q("
            UPDATE CURR_RAT_TALLY
            SET CURR_CODE = ?,
                CURR_SDATE = ?,
                CURR_EDATE = ?,
                CURR_RP = ?,
                CURR_USD = ?
            WHERE CURR_CODE = ?
              AND CONVERT(varchar(10), CURR_SDATE, 120) = ?
              AND CONVERT(varchar(10), CURR_EDATE, 120) = ?
        ", array(
            $currCode,
            $sdate,
            $edate,
            $currRp,
            $currUsd,
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
                CURR_USD
            )
            VALUES
            (
                ?, ?, ?, ?, ?
            )
        ", array(
            $currCode,
            $sdate,
            $edate,
            $currRp,
            $currUsd
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
   IMPORT RECEIPT P2
   ========================= */

function getBcNoReceiptP2($r) {
    return gv($r, array(
        'BC_NO',
        'NO_BC',
        'BCNO',
        'BC_NUMBER',
        'NOMOR_BC',
        'RCV_BCNO',
        'BC_DOC',
        'BC_TYPE_NO'
    ), '');
}

function getReceiptGroupKeyP2($r) {
    $rcvNo = trim((string)gv($r, 'RCV_NO', ''));
    $doNo  = trim((string)getDoNoP2($r));
    $bcNo  = trim((string)getBcNoReceiptP2($r));

    $poNo = trim((string)gv($r, array(
        'PO_NO',
        'PONO',
        'POD_PONO',
        'POM_NO',
        'POM_PONO',
        'RCV_PONO',
        'RCV_PO_NO',
        'NO_PO'
    ), ''));

    $invNo = trim((string)gv($r, array(
        'INV_NO',
        'INVOICE_NO',
        'SUP_INVNO',
        'SUPPLIER_INVNO',
        'NO_INVOICE'
    ), ''));

    return $rcvNo . '|' . $doNo . '|' . $bcNo . '|' . $poNo . '|' . $invNo;
}

function importReceipt($fromDate, $toDate) {
    $rows = loadReceiptRows($fromDate, $toDate);

    q("DELETE FROM dbo.Tally_RECEIPT", array());

    if (count($rows) == 0) {
        return 0;
    }

    $groups = array();

    foreach ($rows as $r) {
        $groupKey = getReceiptGroupKeyP2($r);

        $cur = strtoupper(trim((string)gv($r, 'PO_CUR', '')));
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'POD_PRICE', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = array(
                'IDR' => 0,
                'USD' => 0,
                'CUR' => $cur,
                'RATE' => $rate
            );
        }

        if ($cur == 'IDR') {
            $totalIDR = round4($qty * $price);
            $groups[$groupKey]['IDR'] += $totalIDR;

            if ($rate != 0) {
                $groups[$groupKey]['USD'] += round4($totalIDR / $rate);
            }
        } elseif ($cur == 'USD') {
            $totalUSD = round4($qty * $price);
            $groups[$groupKey]['USD'] += $totalUSD;
        } else {
            die('UNEXPECTED CURRENCY RECEIPT: ' . h($cur));
        }
    }

    foreach ($groups as $groupKey => $g) {
        if ($g['CUR'] == 'USD' && $g['RATE'] != 0) {
            $groups[$groupKey]['IDR'] = round4($g['USD'] * $g['RATE']);
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
            GROUP_TOTAL
        )
        VALUES
        (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?
        )
    ";

    $count = 0;

    foreach ($rows as $r) {
        $groupKey = getReceiptGroupKeyP2($r);

        $rcvNo = gv($r, 'RCV_NO', '');
        $cur = strtoupper(trim((string)gv($r, 'PO_CUR', '')));
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'POD_PRICE', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        $currUsd = gv($r, 'CURR_USD', '');
        $currRp = gv($r, 'CURR_RP', '');

        if (!isset($groups[$groupKey])) {
            die('GROUP_TOTAL Receipt tidak ditemukan untuk key: ' . h($groupKey));
        }

        if ($cur == 'IDR') {
            $unitIDR = round4($price);
            $unitUSD = ($rate != 0) ? round4($price / $rate) : 0;

            $totalIDR = round4($qty * $price);
            $totalUSD = ($rate != 0) ? round4($totalIDR / $rate) : 0;

            $itemPrice = resultIDR($unitIDR, $currUsd, $currRp, $unitUSD, 'positif');
            $totalHarga = resultIDR($totalIDR, $currUsd, $currRp, $totalUSD, 'positif');
            $totalHarga2 = resultIDR($totalIDR, $currUsd, $currRp, $totalUSD, 'negatif');

            $groupTotal = resultIDR(
                round4($groups[$groupKey]['IDR']),
                $currUsd,
                $currRp,
                round4($groups[$groupKey]['USD']),
                'positif'
            );
        } elseif ($cur == 'USD') {
            $unitUSD = round4($price);
            $totalUSD = round4($qty * $price);

            $itemPrice = $unitUSD;
            $totalHarga = $totalUSD;
            $totalHarga2 = '-' . $totalUSD;

            $groupTotal = round4($groups[$groupKey]['USD']);
        } else {
            die('UNEXPECTED CURRENCY RECEIPT: ' . h($cur));
        }

        q($sqlInsert, array(
            $rcvNo,
            getDoNoP2($r),
            fmtTallyDate(gv($r, 'RCV_DATE', '')),
            gv($r, 'SUP_CODE', ''),
            gv($r, 'ITEM_CODE', ''),
            gv($r, 'ITEM_NAME', ''),
            $qty,
            $itemPrice,
            $totalHarga,
            $totalHarga2,
            $groupTotal
        ));

        $count++;
    }

    return $count;
}

/* =========================
   IMPORT SALES P2 PER CURRENCY
   - Sales IDR dan Sales USD memakai tabel staging yang sama: dbo.Tally_SALES
   - Import dilakukan bergantian; tabel dikosongkan sebelum insert sesuai tab aktif
   - NO_INVOICE, NO_DS, Tanggal tetap bisa diedit per-row dari frontend
   ========================= */

function applySalesModifiedData($rows, $modifiedData) {
    if ($modifiedData === '') {
        return $rows;
    }

    $mods = json_decode($modifiedData, true);

    if (!is_array($mods)) {
        return $rows;
    }

    foreach ($mods as $idx => $mod) {
        $idx = (int)$idx;

        if (!isset($rows[$idx])) {
            continue;
        }

        // --- FILTER BARIS YANG DIHAPUS ---
        if (isset($mod['is_deleted']) && $mod['is_deleted'] === true) {
            unset($rows[$idx]);
            continue;
        }
        // ---------------------------------

        if (isset($mod['NO_INVOICE'])) {
            $rows[$idx]['DI_INVNO'] = $mod['NO_INVOICE'];
        }

        if (isset($mod['NO_DS'])) {
            $rows[$idx]['DI_DSNO'] = $mod['NO_DS'];
        }

        if (isset($mod['Tanggal'])) {
            $rows[$idx]['TRAN_DATE'] = $mod['Tanggal'];
        }
    }

    // Kembalikan array dengan index ulang karena ada yang di-unset
    return array_values($rows);
}

function importSalesByCurrency($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData, $salesCurrency) {
    $salesCurrency = strtoupper(trim((string)$salesCurrency));

    if ($salesCurrency != 'IDR' && $salesCurrency != 'USD') {
        die('Currency Sales harus IDR atau USD.');
    }

    /* Tabel staging dipakai bergantian sebelum data dikirim ke Tally. */
    $targetTable = 'dbo.Tally_SALES';

    $rows = loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, $salesCurrency);

    q("DELETE FROM " . $targetTable, array());

    if (count($rows) == 0) {
        return 0;
    }

    $rows = applySalesModifiedData($rows, $modifiedData);

    /* GROUP_TOTAL dihitung per invoice setelah perubahan dari frontend diterapkan. */
    $groups = array();

    foreach ($rows as $r) {
        $invKey = getInvNoP2($r, $invNo);

        if ($invKey == '') {
            die('DI_INVNO kosong. GROUP_TOTAL tidak bisa dihitung per invoice.');
        }

        $cur = strtoupper(trim((string)gv($r, 'ITEM_CUR', '')));
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'ITEM_COST', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));

        if ($cur != $salesCurrency) {
            die('UNEXPECTED CURRENCY SALES ' . h($salesCurrency) . ': ' . h($cur));
        }

        if (!isset($groups[$invKey])) {
            $groups[$invKey] = array(
                'IDR' => 0,
                'USD' => 0,
                'RATE' => $rate
            );
        }

        if ($salesCurrency == 'IDR') {
            $totalIDR = round4($qty * $price);
            $groups[$invKey]['IDR'] += $totalIDR;

            if ($rate != 0) {
                $groups[$invKey]['USD'] += round4($totalIDR / $rate);
            }
        } else {
            $totalUSD = round4($qty * $price);
            $groups[$invKey]['USD'] += $totalUSD;

            if ($rate != 0) {
                $groups[$invKey]['IDR'] += round4($totalUSD * $rate);
            }
        }
    }

    $sqlInsert = "
        INSERT INTO " . $targetTable . "
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
            GROUP_TOTAL
        )
        VALUES
        (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?
        )
    ";

    $count = 0;

    foreach ($rows as $r) {
        $inv = getInvNoP2($r, $invNo);
        $dsNoFinal = getDoNoP2($r);
        $cur = strtoupper(trim((string)gv($r, 'ITEM_CUR', '')));
        $qty = nval(gv($r, 'QTY', 0));
        $price = nval(gv($r, 'ITEM_COST', 0));
        $rate = nval(gv($r, 'CURR_RP', 0));
        $currUsd = gv($r, 'CURR_USD', '');
        $currRp = gv($r, 'CURR_RP', '');

        if ($inv == '') {
            die('DI_INVNO kosong. GROUP_TOTAL tidak bisa dihitung per invoice.');
        }

        if ($cur != $salesCurrency) {
            die('UNEXPECTED CURRENCY SALES ' . h($salesCurrency) . ': ' . h($cur));
        }

        if ($salesCurrency == 'IDR') {
            $unitIDR = $price;
            $unitUSD = ($rate != 0) ? ($price / $rate) : 0;
            $totalIDR = $qty * $price;
            $totalUSD = ($rate != 0) ? ($totalIDR / $rate) : 0;

            $itemPrice = resultIDR(round4($unitIDR), $currUsd, $currRp, round4($unitUSD), 'positif');
            $totalHarga = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'positif');
            $totalHarga2 = resultIDR(round4($totalIDR), $currUsd, $currRp, round4($totalUSD), 'positif');
            $groupTotal = resultIDR(
                round4($groups[$inv]['IDR']),
                $currUsd,
                $currRp,
                round4($groups[$inv]['USD']),
                'negatif'
            );
        } else {
            /* Sales USD diekspor sebagai angka USD murni sesuai cabang USD lama. */
            $unitUSD = round4($price);
            $totalUSD = round4($qty * $price);

            $itemPrice = $unitUSD;
            $totalHarga = $totalUSD;
            $totalHarga2 = $totalUSD;
            $groupTotal = '-' . round4($groups[$inv]['USD']);
        }

        q($sqlInsert, array(
            $inv,
            $dsNoFinal,
            fmtTallyDate(gv($r, 'TRAN_DATE', '')),
            gv($r, 'CUST_CODE', ''),
            gv($r, 'ITEM_CODE', ''),
            gv($r, 'ITEM_NAME', ''),
            $qty,
            $itemPrice,
            $totalHarga,
            $totalHarga2,
            $groupTotal
        ));

        $count++;
    }

    /*
       Untuk Sales USD, GROUP_TOTAL harus sama persis dengan jumlah TOTAL_HARGA
       yang benar-benar tersimpan pada tabel staging. Ini mencegah selisih rounding
       antara total invoice dan nilai inventory pada XML Tally.
    */
    if ($salesCurrency == 'USD' && $count > 0) {
        q("
            UPDATE S
            SET S.GROUP_TOTAL = -G.TOTAL_USD
            FROM dbo.Tally_SALES S
            INNER JOIN
            (
                SELECT
                    NO_INVOICE,
                    SUM(CONVERT(decimal(38, 4), TOTAL_HARGA)) AS TOTAL_USD
                FROM dbo.Tally_SALES
                GROUP BY NO_INVOICE
            ) G
                ON G.NO_INVOICE = S.NO_INVOICE
        ", array());
    }

    return $count;
}

function importSalesIDR($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData) {
    return importSalesByCurrency($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData, 'IDR');
}

function importSalesUSD($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData) {
    return importSalesByCurrency($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData, 'USD');
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
   RENDER FUNCTIONS
   ========================= */

function renderReceiptTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data Receipt.</div>';
        return;
    }
    ?>
    <div class="table-responsive" style="max-height:500px;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>#</th>
                    <th>RCV_NO</th>
                    <th>DO_NO</th>
                    <th>RCV_DATE</th>
                    <th>SUP_CODE</th>
                    <th>ITEM_CODE</th>
                    <th>ITEM_NAME</th>
                    <th>QTY</th>
                    <th>POD_PRICE</th>
                    <th>PO_CUR</th>
                    <th>CURR_RP</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($rows as $r) { ?>
                <tr>
                    <td class="text-end"><?php echo $no++; ?></td>
                    <td><?php echo h(gv($r, 'RCV_NO', '')); ?></td>
                    <td><?php echo h(getDoNoP2($r)); ?></td>
                    <td><?php echo h(fmtDateView(gv($r, 'RCV_DATE', ''))); ?></td>
                    <td><?php echo h(gv($r, 'SUP_CODE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_CODE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_NAME', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'QTY', 0)); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'POD_PRICE', 0)); ?></td>
                    <td><?php echo h(gv($r, 'PO_CUR', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'CURR_RP', 0)); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderSopTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data Stock Opname.</div>';
        return;
    }
    ?>
    <div class="table-responsive" style="max-height:500px;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>#</th>
                    <th>SOP_SDATE</th>
                    <th>ITEM_CODE</th>
                    <th>ITEM_NAME</th>
                    <th>STQTY</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($rows as $r) { ?>
                <tr>
                    <td class="text-end"><?php echo $no++; ?></td>
                    <td><?php echo h(fmtDateView(gv($r, 'SOP_SDATE', ''))); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_CODE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_NAME', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'STQTY', 0)); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

/*
   RENDER SALES TABLE DENGAN KOLOM EDITABLE
   - NO_INVOICE (DI_INVNO) → input text, border kuning
   - NO_DS (DI_DSNO)       → input text, border kuning
   - Tanggal (TRAN_DATE)    → input date, border biru
   - Ubah 1 row → otomatis ubah semua row dalam grup yang sama
     (grup = DI_INVNO asli + DI_DSNO asli dari database)
*/
function renderSalesTable($rows, $salesCurrency) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data Sales ' . h($salesCurrency) . '. Pilih Customer terlebih dahulu, lalu klik Load Sales ' . h($salesCurrency) . '.</div>';
        return;
    }
    ?>
    <div class="alert alert-info py-2 small mb-2">
        <span class="badge bg-warning text-dark">Kuning</span> = NO_INVOICE / NO_DS editable &nbsp;|&nbsp;
        <span class="badge bg-primary">Biru</span> = Tanggal editable (date picker) &nbsp;|&nbsp;
        <b>Ubah 1 row = otomatis ubah semua row dalam grup INV+DS yang sama.</b> Perubahan digunakan saat Import Sales <?php echo h($salesCurrency); ?>.<br>
        <span class="badge bg-danger">Merah</span> = Hapus baris agar tidak di-import ke database/Tally.
    </div>
    <div class="table-responsive" style="max-height:600px;">
        <table class="table table-bordered table-striped table-sm" id="salesTable">
            <thead class="table-dark sticky-top">
                <tr>
                    <th style="width: 50px;" class="text-center">#</th>
                    <th class="bg-warning text-dark">NO_INVOICE ✏️</th>
                    <th class="bg-warning text-dark">NO_DS ✏️</th>
                    <th class="bg-primary">Tanggal ✏️</th>
                    <th>CUST_CODE</th>
                    <th>ITEM_CODE</th>
                    <th>ITEM_NAME</th>
                    <th>QTY</th>
                    <th>ITEM_COST</th>
                    <th>ITEM_CUR</th>
                    <th>CURR_RP</th>
                    <th>BC</th>
                    <th>PART_NO</th>
                    <th>ORDR_PO</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rowIdx = 0;
            foreach ($rows as $r) {
                $origInv = trim((string)gv($r, 'DI_INVNO', ''));
                $origDs  = trim((string)gv($r, 'DI_DSNO', ''));
                $groupKey = $origInv . '|' . $origDs;
                $tglVal = fmtDateInput(gv($r, 'TRAN_DATE', ''));
            ?>
                <tr data-group-key="<?php echo h($groupKey); ?>" data-row-idx="<?php echo $rowIdx; ?>">
                    <td class="text-center align-middle">
                        <?php echo $rowIdx + 1; ?>
                        <!-- TOMBOL DELETE DITAMBAHKAN DI SINI -->
                        <br>
                        <button type="button" class="btn btn-sm btn-danger py-0 px-1 mt-1" onclick="deleteSalesRow(this, <?php echo $rowIdx; ?>)" title="Kecualikan dari Import">
                            ✖
                        </button>
                    </td>
                    <td style="background:#fffde7;">
                        <input type="text"
                               class="form-control form-control-sm sales-editable"
                               data-field="NO_INVOICE"
                               data-row-idx="<?php echo $rowIdx; ?>"
                               data-group-key="<?php echo h($groupKey); ?>"
                               value="<?php echo h($origInv); ?>"
                               onchange="onSalesFieldChange(this)"
                               style="min-width:130px; font-weight:600; border:2px solid #ffc107; background:#fffde7;">
                    </td>
                    <td style="background:#fffde7;">
                        <input type="text"
                               class="form-control form-control-sm sales-editable"
                               data-field="NO_DS"
                               data-row-idx="<?php echo $rowIdx; ?>"
                               data-group-key="<?php echo h($groupKey); ?>"
                               value="<?php echo h($origDs); ?>"
                               onchange="onSalesFieldChange(this)"
                               style="min-width:130px; font-weight:600; border:2px solid #ffc107; background:#fffde7;">
                    </td>
                    <td style="background:#e3f2fd;">
                        <input type="date"
                               class="form-control form-control-sm sales-editable"
                               data-field="Tanggal"
                               data-row-idx="<?php echo $rowIdx; ?>"
                               data-group-key="<?php echo h($groupKey); ?>"
                               value="<?php echo h($tglVal); ?>"
                               onchange="onSalesFieldChange(this)"
                               style="min-width:145px; font-weight:600; border:2px solid #2196f3; background:#e3f2fd;">
                    </td>
                    <td><?php echo h(gv($r, 'CUST_CODE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_CODE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_NAME', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'QTY', 0)); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'ITEM_COST', 0)); ?></td>
                    <td><?php echo h(gv($r, 'ITEM_CUR', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'CURR_RP', 0)); ?></td>
                    <td><?php echo h(gv($r, 'BC', '')); ?></td>
                    <td><?php echo h(gv($r, 'PART_NO', '')); ?></td>
                    <td><?php echo h(gv($r, 'ORDR_PO', '')); ?></td>
                </tr>
            <?php
                $rowIdx++;
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}


/* =========================
   LOAD & IMPORT BOM
   ========================= */

function loadBomRows() {
    $stmt = q("EXECUTE sp_GenerateTallyBOM", array());
    return fetchRows($stmt);
}

function importBom() {
    $rows = loadBomRows();
    q("DELETE FROM dbo.Tally_BOM", array());

    if (count($rows) == 0) {
        return 0;
    }

    $sqlInsert = "
        INSERT INTO dbo.Tally_BOM
        (
            Col_A, Col_B, Col_C, Col_D, Col_E, Col_F, Col_G, 
            Col_H, Col_I, Col_J, Col_K, Col_L, Col_M, Col_N
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $count = 0;
    foreach ($rows as $r) {
        // Ambil nilai I dan K sebagai string, jika kosong isi '0'
        $valI = trim((string)gv($r, 'I', '0'));
        if ($valI === '') $valI = '0';
        
        $valK = trim((string)gv($r, 'K', '0'));
        if ($valK === '') $valK = '0';

        q($sqlInsert, array(
            gv($r, 'A', ''),
            gv($r, 'B', ''),
            gv($r, 'C', ''),
            gv($r, 'D', ''),
            gv($r, 'E', ''),
            gv($r, 'F', ''),
            gv($r, 'G', ''),
            gv($r, 'H', ''),
            $valI,     // Langsung kirim string
            gv($r, 'J', ''),
            $valK,     // Langsung kirim string
            gv($r, 'L', ''),
            gv($r, 'M', ''),
            gv($r, 'N', '')
        ));
        $count++;
    }

    return $count;
}

function renderBomTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data BOM. Klik Load BOM untuk mengambil data.</div>';
        return;
    }
    ?>
    <div class="table-responsive" style="max-height:600px;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>NO (A)</th>
                    <th>NAME Part (B)</th>
                    <th>OLDNAME (C)</th>
                    <th>NAME SCR (D)</th>
                    <th>ADDNAME (E)</th>
                    <th>ADDNAME SCR (F)</th>
                    <th>BASEUNIT (G)</th>
                    <th>COMP LIST (H)</th>
                    <th>COMP QTY (I)</th>
                    <th>STOCKITEM Mat (J)</th>
                    <th>ACTUALQTY (K)</th>
                    <th>BASEUNIT Mat (L)</th>
                    <th>NATURE (M)</th>
                    <th>GODOWN (N)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) { ?>
                <tr>
                    <td class="text-center"><?php echo h(gv($r, 'A', '')); ?></td>
                    <td><?php echo h(gv($r, 'B', '')); ?></td>
                    <td><?php echo h(gv($r, 'C', '')); ?></td>
                    <td><?php echo h(gv($r, 'D', '')); ?></td>
                    <td><?php echo h(gv($r, 'E', '')); ?></td>
                    <td><?php echo h(gv($r, 'F', '')); ?></td>
                    <td><?php echo h(gv($r, 'G', '')); ?></td>
                    <td><?php echo h(gv($r, 'H', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'I', 0)); ?></td>
                    <td><?php echo h(gv($r, 'J', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'K', 0)); ?></td>
                    <td><?php echo h(gv($r, 'L', '')); ?></td>
                    <td><?php echo h(gv($r, 'M', '')); ?></td>
                    <td><?php echo h(gv($r, 'N', '')); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}
/* =========================
   LOAD & IMPORT PRODUCTION
   ========================= */

function loadProdRows($fromDate, $toDate) {
    $stmt = q("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    return fetchRows($stmt);
}

function importProd($fromDate, $toDate) {
    $rows = loadProdRows($fromDate, $toDate);
    q("DELETE FROM dbo.Tally_Prod", array());

    if (count($rows) == 0) {
        return 0;
    }

    $sqlInsert = "
        INSERT INTO dbo.Tally_Prod
        (
            UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $count = 0;
    foreach ($rows as $r) {
        q($sqlInsert, array(
            gv($r, 'UNIQUEID', ''),
            gv($r, 'VCH_NO', ''),
            gv($r, 'PROD_DATE', ''),
            gv($r, 'ITEM', ''),
            gv($r, 'DEST_QTY', 0),
            gv($r, 'DEST_RATE', 0),
            gv($r, 'DEST_AMOUNT', 0),
            gv($r, 'NARRATION', '')
        ));
        $count++;
    }

    return $count;
}

function renderProdTable($rows) {
    if (count($rows) == 0) {
        echo '<div class="alert alert-secondary">Tidak ada data Production. Klik Load Production untuk mengambil data.</div>';
        return;
    }
    ?>
    <div class="table-responsive" style="max-height:600px;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>UNIQUEID</th>
                    <th>VCH-NO</th>
                    <th>DATE</th>
                    <th>ITEM</th>
                    <th>DEST. QTY</th>
                    <th>DEST. RATE</th>
                    <th>DEST. AMOUNT</th>
                    <th>NARRATION</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) { ?>
                <tr>
                    <td><?php echo h(gv($r, 'UNIQUEID', '')); ?></td>
                    <td><b><?php echo h(gv($r, 'VCH_NO', '')); ?></b></td>
                    <td><?php echo h(gv($r, 'PROD_DATE', '')); ?></td>
                    <td><?php echo h(gv($r, 'ITEM', '')); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'DEST_QTY', 0)); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'DEST_RATE', 0)); ?></td>
                    <td class="text-end"><?php echo nval(gv($r, 'DEST_AMOUNT', 0)); ?></td>
                    <td><?php echo h(gv($r, 'NARRATION', '')); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* =========================
   REQUEST HANDLER
   ========================= */

 $fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
 $toDate   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');

 $tab = isset($_POST['tab']) ? $_POST['tab'] : '';
if ($tab == '') {
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'receipt';
}

/* Backward compatibility: URL/tab lama sales diarahkan ke Sales IDR. */
if ($tab == 'sales') {
    $tab = 'sales_idr';
}

 $action = isset($_POST['action']) ? $_POST['action'] : '';

 $custId = isset($_POST['cust_id']) ? trim($_POST['cust_id']) : '';
 $custSearch = isset($_POST['cust_search']) ? trim($_POST['cust_search']) : '';
 $invNo = isset($_POST['inv_no']) ? trim($_POST['inv_no']) : '';
 $poNo = isset($_POST['po_no']) ? trim($_POST['po_no']) : '';
 $modifiedData = isset($_POST['modified_data']) ? $_POST['modified_data'] : '';

 $rateCurr = '';
if (isset($_POST['rate_curr_selected'])) {
    $rateCurr = trim($_POST['rate_curr_selected']);
} elseif (isset($_GET['rate_curr'])) {
    $rateCurr = trim($_GET['rate_curr']);
}

 $message = '';
 $rows = array();
 $currRows = array();
 $rateRows = array();
 $customers = loadCustomers();
 $invoices = array();
 $poRows = array();

/*
   Customer autocomplete memakai hidden CUST_ID.
   Jika user mengetik manual tanpa klik suggestion, izinkan exact match
   terhadap CUST_CODE, CUST_COMP, atau label "CODE - COMPANY".
*/
if ($custSearch != '' && $custId == '') {
    foreach ($customers as $c) {
        $cid = trim((string)gv($c, 'CUST_ID', ''));
        $ccode = trim((string)gv($c, 'CUST_CODE', ''));
        $ccomp = trim((string)gv($c, 'CUST_COMP', ''));
        $cabbr = trim((string)gv($c, 'CUST_ABBR', ''));
        $clabel = $ccode . (($ccomp != '') ? ' - ' . $ccomp : '');

        if (strcasecmp($custSearch, $ccode) == 0 ||
            strcasecmp($custSearch, $ccomp) == 0 ||
            strcasecmp($custSearch, $cabbr) == 0 ||
            strcasecmp($custSearch, $clabel) == 0) {
            $custId = $cid;
            $custSearch = $clabel;
            break;
        }
    }
}

if ($custId != '' && $custSearch == '') {
    foreach ($customers as $c) {
        if ((string)gv($c, 'CUST_ID', '') == (string)$custId) {
            $ccode = trim((string)gv($c, 'CUST_CODE', ''));
            $ccomp = trim((string)gv($c, 'CUST_COMP', ''));
            $custSearch = $ccode . (($ccomp != '') ? ' - ' . $ccomp : '');
            break;
        }
    }
}

/* =========================
   AJAX AUTOCOMPLETE CUSTOMER
   - Bisa mencari berdasarkan CUST_CODE atau CUST_COMP
   ========================= */
if (isset($_GET['ajax_customer']) && $_GET['ajax_customer'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $params = array();
    $whereTerm = '';

    if ($term != '') {
        $whereTerm = " AND (CUST_CODE LIKE ? OR CUST_COMP LIKE ?) ";
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
    }

    $stmtCust = q("
        SELECT TOP 30
            CUST_ID,
            CUST_CODE,
            CUST_COMP
        FROM CUST
        WHERE ISNULL(CUST_INACTIVE, 0) = 0
          " . $whereTerm . "
        ORDER BY CUST_CODE
    ", $params);

    $out = array();
    while ($rc = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
        $code = trim((string)gv($rc, 'CUST_CODE', ''));
        $comp = trim((string)gv($rc, 'CUST_COMP', ''));
        $label = $code;

        if ($comp != '') {
            $label .= ' - ' . $comp;
        }

        $out[] = array(
            'id' => (string)gv($rc, 'CUST_ID', ''),
            'code' => $code,
            'company' => $comp,
            'label' => $label
        );
    }

    echo json_encode($out);
    exit;
}

/* =========================
   AJAX AUTOCOMPLETE PO
   ========================= */
if (isset($_GET['ajax_po']) && $_GET['ajax_po'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $ajaxCust = isset($_GET['cust_id']) ? trim($_GET['cust_id']) : '';
    $ajaxFrom = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-m-01');
    $ajaxTo = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    if ($ajaxCust == '') {
        echo json_encode(array());
        exit;
    }

    $params = array($ajaxFrom, $ajaxTo, $ajaxCust);
    $whereTerm = '';

    if ($term != '') {
        $whereTerm = " AND O.ORDR_PO LIKE ? ";
        $params[] = '%' . $term . '%';
    }

    $stmtPo = q("
        SELECT TOP 30
            O.ORDR_PO
        FROM dbo.CUST C
        INNER JOIN dbo.DI D
            ON C.CUST_ID = D.CUST_ID
        INNER JOIN dbo.DIPA_PAR DP
            ON D.DI_ID = DP.DI_ID
        INNER JOIN dbo.ORDERS O
            ON O.ORDR_ID = DP.ORDR_ID
        WHERE D.DI_DATE BETWEEN ? AND ?
          AND C.CUST_ID = ?
          AND ISNULL(O.ORDR_PO, '') <> ''
          " . $whereTerm . "
        GROUP BY O.ORDR_PO
        ORDER BY O.ORDR_PO
    ", $params);

    $out = array();
    while ($rp = sqlsrv_fetch_array($stmtPo, SQLSRV_FETCH_ASSOC)) {
        $out[] = gv($rp, 'ORDR_PO', '');
    }

    echo json_encode($out);
    exit;
}

if ($custId != '') {
    $invoices = loadInvoices($fromDate, $toDate, $custId);
    $poRows = loadPoRows($fromDate, $toDate, $custId);
}

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
    $message = 'Import Receipt P2 selesai. Total baris: ' . $count;
    $rows = loadReceiptRows($fromDate, $toDate);
} elseif ($action == 'load_sales' || $action == 'load_sales_idr') {
    $tab = 'sales_idr';

    if ($custId == '') {
        $message = 'Customer tidak ditemukan. Ketik CUST_CODE persis atau pilih customer dari hasil autocomplete.';
    } else {
        $rows = loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, 'IDR');
        $invoices = loadInvoices($fromDate, $toDate, $custId);
        $poRows = loadPoRows($fromDate, $toDate, $custId);
    }
} elseif ($action == 'import_sales' || $action == 'import_sales_idr') {
    $tab = 'sales_idr';

    if ($custId == '') {
        $message = 'Import dibatalkan: customer tidak ditemukan. Ketik CUST_CODE persis atau pilih customer dari hasil autocomplete.';
    } else {
        $count = importSalesIDR($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData);
        $modCount = 0;
        if ($modifiedData !== '') {
            $mods = json_decode($modifiedData, true);
            if (is_array($mods)) $modCount = count($mods);
        }
        $message = 'Import Sales IDR P2 ke Tally_SALES selesai. Total baris yang di-import: ' . $count;
        if ($modCount > 0) {
            $message .= ' | Terdapat row yang diedit/dihapus dalam proses.';
        }
        if ($poNo != '') {
            $message .= ' | PO filter: ' . $poNo;
        }
        $rows = loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, 'IDR');
        $invoices = loadInvoices($fromDate, $toDate, $custId);
        $poRows = loadPoRows($fromDate, $toDate, $custId);
    }
} elseif ($action == 'load_sales_usd') {
    $tab = 'sales_usd';

    if ($custId == '') {
        $message = 'Customer tidak ditemukan. Ketik CUST_CODE persis atau pilih customer dari hasil autocomplete.';
    } else {
        $rows = loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, 'USD');
        $invoices = loadInvoices($fromDate, $toDate, $custId);
        $poRows = loadPoRows($fromDate, $toDate, $custId);
    }
} elseif ($action == 'import_sales_usd') {
    $tab = 'sales_usd';

    if ($custId == '') {
        $message = 'Import dibatalkan: customer tidak ditemukan. Ketik CUST_CODE persis atau pilih customer dari hasil autocomplete.';
    } else {
        $count = importSalesUSD($fromDate, $toDate, $custId, $invNo, $poNo, $modifiedData);
        $modCount = 0;
        if ($modifiedData !== '') {
            $mods = json_decode($modifiedData, true);
            if (is_array($mods)) $modCount = count($mods);
        }
        $message = 'Import Sales USD P2 ke Tally_SALES selesai. Total baris yang di-import: ' . $count;
        if ($modCount > 0) {
            $message .= ' | Terdapat row yang diedit/dihapus dalam proses.';
        }
        if ($poNo != '') {
            $message .= ' | PO filter: ' . $poNo;
        }
        $rows = loadSalesRows($fromDate, $toDate, $custId, $invNo, $poNo, 'USD');
        $invoices = loadInvoices($fromDate, $toDate, $custId);
        $poRows = loadPoRows($fromDate, $toDate, $custId);
    }
} elseif ($action == 'load_invoice') {
    $tab = ($tab == 'sales_usd') ? 'sales_usd' : 'sales_idr';
    $invoices = loadInvoices($fromDate, $toDate, $custId);
} elseif ($action == 'load_sop') {
    $tab = 'sop';
    $rows = loadSopRows($fromDate);
} elseif ($action == 'import_sop') {
    $tab = 'sop';
    $count = importSop($fromDate);
    $message = 'Import Stock Opname P2 selesai. Total baris: ' . $count;
    $rows = loadSopRows($fromDate);

} elseif ($action == 'load_bom') {
    $tab = 'bom';
    $rows = loadBomRows();
} elseif ($action == 'import_bom') {
    $tab = 'bom';
    $count = importBom();
    $message = 'Import BOM ke tabel Tally_BOM selesai. Total baris: ' . $count;
    $rows = loadBomRows();
} elseif ($action == 'load_prod') {
    $tab = 'prod';
    $rows = loadProdRows($fromDate, $toDate);
} elseif ($action == 'import_prod') {
    $tab = 'prod';
    $count = importProd($fromDate, $toDate);
    $message = 'Import Production ke tabel Tally_Prod selesai. Total baris: ' . $count;
    $rows = loadProdRows($fromDate, $toDate);
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
        <h3 class="fw-bold text-dark mb-0">IMPORT SQL TO TALLY P2</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-success">
            <?php echo h($message); ?>
        </div>
    <?php } ?>

    <div class="alert alert-warning">
        Sales P2 dipisahkan menjadi <b>Sales IDR</b> dan <b>Sales USD</b>. Kolom <b>NO_INVOICE</b>,
        <b>NO_DS</b>, dan <b>Tanggal</b> tetap bisa diedit langsung di kedua tab.
        Kedua tab memakai tabel staging yang sama, <b>dbo.Tally_SALES</b>. Setiap import akan menghapus isi lama lalu mengisi data IDR atau USD sesuai tab aktif sebelum dikirim ke Tally.
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="post" id="frmTally">
                <input type="hidden" name="tab" id="tab" value="<?php echo h($tab); ?>">
                <input type="hidden" name="action" id="action" value="">
                <input type="hidden" name="modified_data" id="modified_data" value="">

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

                    <div class="col-md-3 position-relative">
                        <label class="form-label fw-bold">Customer P2</label>

                        <input type="hidden"
                               name="cust_id"
                               id="cust_id"
                               value="<?php echo h($custId); ?>">

                        <input type="text"
                               name="cust_search"
                               id="cust_search"
                               list="customer_list"
                               class="form-control"
                               placeholder="Ketik kode / nama customer"
                               autocomplete="off"
                               value="<?php echo h($custSearch); ?>">

                        <!-- Fallback autocomplete native browser. Tetap bekerja walau AJAX/JavaScript bermasalah. -->
                        <datalist id="customer_list">
                            <?php foreach ($customers as $c) { ?>
                                <?php
                                $dlCode = trim((string)gv($c, 'CUST_CODE', ''));
                                $dlComp = trim((string)gv($c, 'CUST_COMP', ''));
                                $dlAbbr = trim((string)gv($c, 'CUST_ABBR', ''));
                                $dlLabel = $dlCode . (($dlComp != '') ? ' - ' . $dlComp : '');
                                ?>
                                <option value="<?php echo h($dlLabel); ?>"
                                        label="<?php echo h(($dlAbbr != '') ? $dlAbbr : $dlCode); ?>"></option>
                            <?php } ?>
                        </datalist>

                        <div id="customer_suggestions"
                             class="list-group position-absolute w-100 shadow-sm"
                             style="z-index:10000; display:none; max-height:250px; overflow:auto;"></div>

                        <div class="form-text">Ketik lalu pilih customer. CUST_CODE juga boleh diketik persis.</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Invoice Optional</label>
                        <select name="inv_no" id="inv_no" class="form-control">
                            <option value="">ALL Invoice</option>
                            <?php foreach ($invoices as $iv) { ?>
                                <?php
                                $ivNo = gv($iv, 'DI_INVNO', '');
                                $selInv = ($ivNo == $invNo) ? 'selected' : '';
                                ?>
                                <option value="<?php echo h($ivNo); ?>" <?php echo $selInv; ?>>
                                    <?php echo h($ivNo); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-3 position-relative">
                        <label class="form-label fw-bold">PO Optional</label>
                        <input type="text"
                               name="po_no"
                               id="po_no"
                               class="form-control"
                               placeholder="ALL PO / ketik ORDR_PO"
                               autocomplete="off"
                               value="<?php echo h($poNo); ?>">
                        <div id="po_suggestions"
                             class="list-group position-absolute w-100 shadow-sm"
                             style="z-index:9999; display:none; max-height:220px; overflow:auto;"></div>
                    </div>

                    <div class="col-md-2">
                        
                    </div>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'receipt') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=receipt">Receipt</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'sales_idr') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=sales_idr">Sales IDR</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'sales_usd') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=sales_usd">Sales USD</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'sop') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=sop">Stock Opname</a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'rate') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=rate">MASTER_RATE</a>
                        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'bom') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=bom">TALLY BOM</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab == 'prod') ? 'active' : ''; ?>"
               href="tally_import_p2.php?tab=prod">PRODUCTION</a>
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

            <?php } elseif ($tab == 'sales_idr') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="validateSalesLoad('load_sales_idr')">
                        Load Sales IDR
                    </button>

                    <button type="button" class="btn btn-primary" onclick="validateSalesImport('import_sales_idr')">
                        Import IDR ke Tally_SALES
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="copyFirstToAll()">
                        Copy INV/DS/Tgl ke Semua Baris
                    </button>

                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetAllEdits('load_sales_idr')">
                        Reset Edit
                    </button>
                </div>

                <?php renderSalesTable($rows, 'IDR'); ?>

            <?php } elseif ($tab == 'sales_usd') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="validateSalesLoad('load_sales_usd')">
                        Load Sales USD
                    </button>

                    <button type="button" class="btn btn-primary" onclick="validateSalesImport('import_sales_usd')">
                        Import USD ke Tally_SALES
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="copyFirstToAll()">
                        Copy INV/DS/Tgl ke Semua Baris
                    </button>

                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetAllEdits('load_sales_usd')">
                        Reset Edit
                    </button>
                </div>

                <?php renderSalesTable($rows, 'USD'); ?>

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
                <!-- ISI TAB RATE -->
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
                            <button type="submit" class="btn btn-success">Simpan Currency</button>
                            <button type="button" class="btn btn-secondary mt-2" onclick="clearCurrForm()">Baru</button>
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
                                        $activeStyle = ($cCode == $rateCurr) ? 'background:#0d6efd;color:#fff;font-weight:bold;' : '';
                                        ?>
                                        <tr style="<?php echo $activeStyle; ?>">
                                            <td>
                                                <a href="tally_import_p2.php?tab=rate&rate_curr=<?php echo urlencode($cCode); ?>"
                                                   style="<?php echo ($cCode == $rateCurr) ? 'color:#fff;text-decoration:none;' : 'text-decoration:none;'; ?>">
                                                    <?php echo h($cCode); ?>
                                                </a>
                                            </td>
                                            <td><?php echo h(gv($c, 'CURR_DESC', '')); ?></td>
                                            <td>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="tab" value="rate">
                                                    <input type="hidden" name="action" value="delete_curr">
                                                    <input type="hidden" name="curr_code_delete" value="<?php echo h($cCode); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus Currency <?php echo h($cCode); ?>?')">Del</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <h5 class="fw-bold">Rate untuk: <?php echo h($rateCurr); ?></h5>
                        <form method="post" class="card card-body mb-3">
                            <input type="hidden" name="tab" value="rate">
                            <input type="hidden" name="action" value="save_rate">
                            <input type="hidden" name="old_curr_code" value="<?php echo h($rateCurr); ?>">
                            <input type="hidden" name="old_sdate" value="">
                            <input type="hidden" name="old_edate" value="">
                            
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label class="form-label">CURR_CODE</label>
                                    <input type="text" name="rate_curr_code" class="form-control" value="<?php echo h($rateCurr); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">START DATE</label>
                                    <input type="date" name="rate_sdate" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">END DATE</label>
                                    <input type="date" name="rate_edate" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label class="form-label">RATE RP</label>
                                    <input type="text" name="rate_rp" class="form-control" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">RATE USD</label>
                                    <input type="text" name="rate_usd" class="form-control" value="1">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Simpan Rate</button>
                        </form>

                        <div class="table-responsive" style="max-height:500px;">
                            <table class="table table-bordered table-striped table-sm mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>CURR_CODE</th>
                                        <th>START DATE</th>
                                        <th>END DATE</th>
                                        <th>CURR_RP</th>
                                        <th>CURR_USD</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rateRows as $rt) { ?>
                                        <tr>
                                            <td><?php echo h(gv($rt, 'CURR_CODE', '')); ?></td>
                                            <td><?php echo h(fmtDateView(gv($rt, 'CURR_SDATE', ''))); ?></td>
                                            <td><?php echo h(fmtDateView(gv($rt, 'CURR_EDATE', ''))); ?></td>
                                            <td class="text-end"><?php echo nval(gv($rt, 'CURR_RP', 0)); ?></td>
                                            <td class="text-end"><?php echo nval(gv($rt, 'CURR_USD', 0)); ?></td>
                                            <td>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="tab" value="rate">
                                                    <input type="hidden" name="action" value="delete_rate">
                                                    <input type="hidden" name="delete_rate_curr_code" value="<?php echo h(gv($rt, 'CURR_CODE', '')); ?>">
                                                    <input type="hidden" name="delete_rate_sdate" value="<?php echo h(fmtDateView(gv($rt, 'CURR_SDATE', ''))); ?>">
                                                    <input type="hidden" name="delete_rate_edate" value="<?php echo h(fmtDateView(gv($rt, 'CURR_EDATE', ''))); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus Rate ini?')">Del</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                      <?php } elseif ($tab == 'bom') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_bom')">
                        <i class="bi bi-search"></i> Load BOM
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_bom')">
                        <i class="bi bi-download"></i> Import ke Tabel Tally_BOM
                    </button>
                    
                    <span class="text-muted ms-3 small">
                        *Data yang di-import hanya PART dengan kode '02' dan '03'
                    </span>
                </div>

                <?php renderBomTable($rows); ?>

            <?php } elseif ($tab == 'prod') { ?>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="submitTally('load_prod')">
                        <i class="bi bi-search"></i> Load Production
                    </button>

                    <button type="button" class="btn btn-primary" onclick="confirmImport('import_prod')">
                        <i class="bi bi-download"></i> Import ke Tabel Tally_Prod
                    </button>
                </div>

                <?php renderProdTable($rows); ?>

            <?php } ?>

        </div>
    </div>

</div>

<script>
/* ===========================
   MODIFIKASI SALES - EDITABLE & DELETE
   Object untuk menyimpan semua perubahan dari user
   Key = row index, Value = { NO_INVOICE: '...', NO_DS: '...', Tanggal: '...', is_deleted: true }
   =========================== */
var salesModifications = {};

/**
 * Hapus (kecualikan) baris dari import
 */
function deleteSalesRow(btn, rowIdx) {
    if (!confirm('Kecualikan baris ini dari Import?')) {
        return;
    }

    // Catat baris sebagai 'deleted' dalam JSON modifications
    if (!salesModifications[rowIdx]) {
        salesModifications[rowIdx] = {};
    }
    salesModifications[rowIdx]['is_deleted'] = true;

    // Update hidden field untuk dikirim ke backend
    document.getElementById('modified_data').value = JSON.stringify(salesModifications);

    // Hapus elemen <tr> secara visual dengan efek transisi
    var tr = btn.closest('tr');
    if (tr) {
        tr.style.transition = "all 0.3s ease";
        tr.style.opacity = 0;
        setTimeout(function() {
            tr.remove();
        }, 300);
    }

    showSalesFeedback('🗑️ 1 baris dihapus dan tidak akan di-import.');
}

/**
 * Dipanggil saat user mengubah nilai di field editable (NO_INVOICE, NO_DS, Tanggal)
 * Otomatis mengubah semua row dalam grup yang sama (berdasarkan group key asli)
 */
function onSalesFieldChange(el) {
    var fieldName = el.getAttribute('data-field');
    var groupKey  = el.getAttribute('data-group-key');
    var newValue  = el.value;

    // Cari semua input dengan field name & group key yang sama
    var allInputs = document.querySelectorAll('input.sales-editable[data-field="' + fieldName + '"]');
    var changedCount = 0;

    allInputs.forEach(function(input) {
        if (input.getAttribute('data-group-key') === groupKey) {
            input.value = newValue;
            var idx = input.getAttribute('data-row-idx');

            // Simpan ke object modifications
            if (!salesModifications[idx]) salesModifications[idx] = {};
            salesModifications[idx][fieldName] = newValue;
            changedCount++;
        }
    });

    // Update hidden field
    document.getElementById('modified_data').value = JSON.stringify(salesModifications);

    // Feedback
    var label = fieldName;
    showSalesFeedback('✏️ ' + label + ' diubah untuk ' + changedCount + ' row dalam grup ini.');
}

/**
 * Copy nilai INV/DS/Tanggal dari baris pertama ke SEMUA baris
 */
function copyFirstToAll() {
    var allInv = document.querySelectorAll('input.sales-editable[data-field="NO_INVOICE"]');
    var allDs  = document.querySelectorAll('input.sales-editable[data-field="NO_DS"]');
    var allTgl = document.querySelectorAll('input.sales-editable[data-field="Tanggal"]');

    if (allInv.length === 0) {
        alert('Tidak ada data Sales pada tab ini. Load data terlebih dahulu.');
        return;
    }

    var firstInv = allInv[0].value;
    var firstDs  = allDs[0].value;
    var firstTgl = allTgl[0].value;

    if (firstInv === '' && firstDs === '') {
        alert('Baris pertama kosong.');
        return;
    }

    if (!confirm('Copy INV=' + firstInv + ', DS=' + firstDs + ', Tgl=' + firstTgl + ' ke SEMUA ' + allInv.length + ' baris?')) {
        return;
    }

    allInv.forEach(function(input) {
        input.value = firstInv;
        var idx = input.getAttribute('data-row-idx');
        if (!salesModifications[idx]) salesModifications[idx] = {};
        salesModifications[idx]['NO_INVOICE'] = firstInv;
    });

    allDs.forEach(function(input) {
        input.value = firstDs;
        var idx = input.getAttribute('data-row-idx');
        if (!salesModifications[idx]) salesModifications[idx] = {};
        salesModifications[idx]['NO_DS'] = firstDs;
    });

    allTgl.forEach(function(input) {
        input.value = firstTgl;
        var idx = input.getAttribute('data-row-idx');
        if (!salesModifications[idx]) salesModifications[idx] = {};
        salesModifications[idx]['Tanggal'] = firstTgl;
    });

    document.getElementById('modified_data').value = JSON.stringify(salesModifications);

    showSalesFeedback('📋 INV/DS/Tanggal baris pertama di-copy ke semua ' + allInv.length + ' baris.');
}

/**
 * Reset semua edit kembali ke nilai asli (reload data)
 */
function resetAllEdits(loadAction) {
    if (!confirm('Reset semua edit dan baris yang dihapus? Data akan di-reload dari database.')) return;
    salesModifications = {};
    document.getElementById('modified_data').value = '';
    validateSalesLoad(loadAction);
}

/**
 * Feedback kecil
 */
function showSalesFeedback(msg) {
    var existing = document.getElementById('salesEditFeedback');
    if (existing) existing.remove();

    var table = document.getElementById('salesTable');
    if (!table) return;

    var div = document.createElement('div');
    div.id = 'salesEditFeedback';
    div.className = 'alert alert-success py-1 px-3 mb-2 small';
    div.style.cssText = 'position:sticky; top:0; z-index:10; animation: salesFadeInOut 2.5s forwards;';
    div.innerHTML = msg;
    table.parentNode.insertBefore(div, table);

    setTimeout(function(){ if(div.parentNode) div.remove(); }, 2600);
}

/* ===========================
   FORM SUBMIT HELPERS
   =========================== */

function submitTally(act) {
    document.getElementById('action').value = act;
    document.getElementById('frmTally').submit();
}

function confirmImport(act) {
    if (confirm('Lanjutkan import?')) {
        document.getElementById('action').value = act;
        document.getElementById('frmTally').submit();
    }
}

function validateSalesLoad(act) {
    var custInput = document.getElementById('cust_search');
    var custText = custInput ? custInput.value.replace(/^\s+|\s+$/g, '') : '';

    if (custText === '') {
        alert('Ketik atau pilih Customer terlebih dahulu.');
        if (custInput) custInput.focus();
        return;
    }

    // Reset modifications saat load ulang
    salesModifications = {};
    document.getElementById('modified_data').value = '';
    document.getElementById('action').value = act;
    document.getElementById('frmTally').submit();
}

function validateSalesImport(act) {
    var custInput = document.getElementById('cust_search');
    var custText = custInput ? custInput.value.replace(/^\s+|\s+$/g, '') : '';

    if (custText === '') {
        alert('Ketik atau pilih Customer terlebih dahulu.');
        if (custInput) custInput.focus();
        return;
    }

    // Pastikan modifications terkirim
    document.getElementById('modified_data').value = JSON.stringify(salesModifications);

    var modCount = Object.keys(salesModifications).length;
    var extraMsg = modCount > 0
        ? '\n\nTerdapat baris yang sudah diedit/dihapus. Perubahan akan digunakan saat import.'
        : '';

    var targetTable = 'Tally_SALES';
    var currencyLabel = (act === 'import_sales_usd') ? 'USD' : 'IDR';

    if (confirm('Import Sales ' + currencyLabel + ' ke tabel ' + targetTable + ' sekarang?' + extraMsg)) {
        document.getElementById('action').value = act;
        document.getElementById('frmTally').submit();
    }
}

/* ===========================
   CUSTOMER + PO AUTOCOMPLETE
   - Vanilla JavaScript: tidak bergantung pada jQuery
   - Customer memakai endpoint AJAX di file ini sendiri
   =========================== */
(function() {
    'use strict';

    var timerCustomer = null;
    var timerPo = null;
    var customerRequestNo = 0;
    var poRequestNo = 0;
    var ajaxUrl = window.location.pathname;

    function byId(id) {
        return document.getElementById(id);
    }

    function trimText(value) {
        return String(value == null ? '' : value).replace(/^\s+|\s+$/g, '');
    }

    function clearSuggestions(box) {
        if (!box) return;
        while (box.firstChild) box.removeChild(box.firstChild);
        box.style.display = 'none';
    }

    function showMessage(box, message, isError) {
        if (!box) return;
        while (box.firstChild) box.removeChild(box.firstChild);

        var item = document.createElement('div');
        item.className = 'list-group-item py-2 ' + (isError ? 'text-danger' : 'text-muted');
        item.style.fontSize = '13px';
        item.textContent = message;

        box.appendChild(item);
        box.style.display = 'block';
    }

    function hideCustomerSuggestions() {
        clearSuggestions(byId('customer_suggestions'));
    }

    function hidePoSuggestions() {
        clearSuggestions(byId('po_suggestions'));
    }

    function appendTextLine(parent, text, className) {
        var line = document.createElement('div');
        if (className) line.className = className;
        line.textContent = text;
        parent.appendChild(line);
    }

    function renderCustomers(list) {
        var box = byId('customer_suggestions');
        if (!box) return;

        while (box.firstChild) box.removeChild(box.firstChild);

        if (!list || !list.length) {
            showMessage(box, 'Customer tidak ditemukan', false);
            return;
        }

        for (var i = 0; i < list.length; i++) {
            var item = list[i] || {};
            // Mendukung format endpoint internal (id/code/company/label)
            // dan format endpoint lama (CUST_ID/CUST_CODE/CUST_COMP/CUST_ABBR).
            var id = trimText(item.id != null ? item.id : item.CUST_ID);
            var code = trimText(item.code != null ? item.code : item.CUST_CODE);
            var comp = trimText(item.company != null ? item.company : item.CUST_COMP);
            var abbr = trimText(item.abbr != null ? item.abbr : item.CUST_ABBR);
            var label = trimText(item.label);

            if (label === '') {
                label = code + (comp !== '' ? ' - ' + comp : '');
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action py-2';
            button.style.fontSize = '13px';
            button.setAttribute('data-cust-id', id);
            button.setAttribute('data-cust-label', label);

            appendTextLine(button, code, 'fw-bold');
            if (comp !== '') appendTextLine(button, comp, 'text-muted small');
            if (abbr !== '') appendTextLine(button, 'ABBR: ' + abbr, 'text-muted small');

            button.addEventListener('mousedown', function(event) {
                // Mencegah input kehilangan fokus sebelum event click selesai.
                event.preventDefault();
            });

            button.addEventListener('click', function() {
                selectCustomer(
                    this.getAttribute('data-cust-id'),
                    this.getAttribute('data-cust-label')
                );
            });

            box.appendChild(button);
        }

        box.style.display = 'block';
    }

    function loadCustomerSuggestions() {
        var input = byId('cust_search');
        var term = input ? trimText(input.value) : '';

        clearTimeout(timerCustomer);

        if (term === '') {
            hideCustomerSuggestions();
            return;
        }

        timerCustomer = setTimeout(function() {
            var currentRequest = ++customerRequestNo;
            var url = ajaxUrl + '?ajax_customer=1&term=' + encodeURIComponent(term);

            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(list) {
                // Abaikan response lama jika user sudah mengetik term baru.
                if (currentRequest !== customerRequestNo) return;
                renderCustomers(list);
            })
            .catch(function(error) {
                if (currentRequest !== customerRequestNo) return;
                if (window.console && console.error) {
                    console.error('Customer autocomplete gagal:', error);
                }
                showMessage(byId('customer_suggestions'), 'Gagal memuat customer', true);
            });
        }, 250);
    }

    function renderPoList(list) {
        var box = byId('po_suggestions');
        if (!box) return;

        while (box.firstChild) box.removeChild(box.firstChild);

        if (!list || !list.length) {
            showMessage(box, 'PO tidak ditemukan', false);
            return;
        }

        for (var i = 0; i < list.length; i++) {
            var po = trimText(list[i]);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action list-group-item-light py-2';
            button.style.fontSize = '13px';
            button.textContent = po;
            button.setAttribute('data-po', po);

            button.addEventListener('mousedown', function(event) {
                event.preventDefault();
            });

            button.addEventListener('click', function() {
                selectPo(this.getAttribute('data-po'));
            });

            box.appendChild(button);
        }

        box.style.display = 'block';
    }

    function loadPoSuggestions() {
        var poInput = byId('po_no');
        var custInput = byId('cust_id');
        var fromInput = byId('from_date');
        var toInput = byId('to_date');

        var term = poInput ? trimText(poInput.value) : '';
        var custId = custInput ? trimText(custInput.value) : '';
        var fromD = fromInput ? fromInput.value : '';
        var toD = toInput ? toInput.value : '';

        if (custId === '') {
            hidePoSuggestions();
            return;
        }

        clearTimeout(timerPo);
        timerPo = setTimeout(function() {
            var currentRequest = ++poRequestNo;
            var url = ajaxUrl
                + '?ajax_po=1'
                + '&cust_id=' + encodeURIComponent(custId)
                + '&from_date=' + encodeURIComponent(fromD)
                + '&to_date=' + encodeURIComponent(toD)
                + '&term=' + encodeURIComponent(term);

            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(list) {
                if (currentRequest !== poRequestNo) return;
                renderPoList(list);
            })
            .catch(function(error) {
                if (currentRequest !== poRequestNo) return;
                if (window.console && console.error) {
                    console.error('PO autocomplete gagal:', error);
                }
                showMessage(byId('po_suggestions'), 'Gagal memuat PO', true);
            });
        }, 250);
    }

    function initAutocomplete() {
        var customerInput = byId('cust_search');
        var poInput = byId('po_no');

        if (customerInput) {
            customerInput.addEventListener('input', function() {
                var custId = byId('cust_id');
                var invNo = byId('inv_no');
                var poNo = byId('po_no');

                if (custId) custId.value = '';
                if (invNo) invNo.value = '';
                if (poNo) poNo.value = '';

                hidePoSuggestions();
                loadCustomerSuggestions();
            });

            customerInput.addEventListener('focus', loadCustomerSuggestions);
            customerInput.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' || event.keyCode === 27) {
                    hideCustomerSuggestions();
                }
            });
        }

        if (poInput) {
            poInput.addEventListener('input', loadPoSuggestions);
            poInput.addEventListener('focus', loadPoSuggestions);
            poInput.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' || event.keyCode === 27) {
                    hidePoSuggestions();
                }
            });
        }

        document.addEventListener('click', function(event) {
            var customerBox = byId('customer_suggestions');
            var poBox = byId('po_suggestions');

            if (customerInput && customerBox
                && event.target !== customerInput
                && !customerBox.contains(event.target)) {
                hideCustomerSuggestions();
            }

            if (poInput && poBox
                && event.target !== poInput
                && !poBox.contains(event.target)) {
                hidePoSuggestions();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    } else {
        initAutocomplete();
    }
})();

function selectCustomer(id, label) {
    var custId = document.getElementById('cust_id');
    var custSearch = document.getElementById('cust_search');
    var customerBox = document.getElementById('customer_suggestions');
    var invNo = document.getElementById('inv_no');
    var poNo = document.getElementById('po_no');
    var poBox = document.getElementById('po_suggestions');

    if (custId) custId.value = id || '';
    if (custSearch) custSearch.value = label || '';
    if (customerBox) {
        customerBox.innerHTML = '';
        customerBox.style.display = 'none';
    }

    // Filter lain berasal dari customer sebelumnya, jadi dikosongkan.
    if (invNo) invNo.value = '';
    if (poNo) poNo.value = '';
    if (poBox) {
        poBox.innerHTML = '';
        poBox.style.display = 'none';
    }
}

function selectPo(val) {
    var poNo = document.getElementById('po_no');
    var poBox = document.getElementById('po_suggestions');

    if (poNo) poNo.value = val || '';
    if (poBox) {
        poBox.innerHTML = '';
        poBox.style.display = 'none';
    }
}

/* ===========================
   MASTER RATE FORM HELPERS
   =========================== */

function editCurr(code, desc, symbol, dec) {
    document.getElementById('curr_code').value = code;
    document.getElementById('curr_desc').value = desc;
    document.getElementById('curr_symbol').value = symbol;
    document.getElementById('curr_dec').value = dec;
}

function clearCurrForm() {
    document.getElementById('curr_code').value = '';
    document.getElementById('curr_desc').value = '';
    document.getElementById('curr_symbol').value = '';
    document.getElementById('curr_dec').value = '0';
    document.getElementById('old_curr_code').value = '';
    document.getElementById('old_sdate').value = '';
    document.getElementById('old_edate').value = '';
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
}

function clearRateForm() {
    document.getElementById('rate_curr_code').value = '';
    document.getElementById('rate_sdate').value = '';
    document.getElementById('rate_edate').value = '';
    document.getElementById('rate_rp').value = '';
    document.getElementById('rate_usd').value = '1';
    document.getElementById('old_curr_code').value = '';
    document.getElementById('old_sdate').value = '';
    document.getElementById('old_edate').value = '';
}
</script>

<style>
/* Customer autocomplete harus berada di atas card/tab dan tidak terpotong. */
#frmTally,
#frmTally .row,
#frmTally .position-relative {
    overflow: visible !important;
}
#customer_suggestions,
#po_suggestions {
    top: 100%;
    left: 0;
    margin-top: 2px;
    background: #fff;
}

@keyframes salesFadeInOut {
    0%   { opacity: 0; transform: translateY(-8px); }
    15%  { opacity: 1; transform: translateY(0); }
    75%  { opacity: 1; }
    100% { opacity: 0; }
}
input.sales-editable:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.4);
    transform: scale(1.02);
    transition: all 0.15s ease;
}
input.sales-editable {
    transition: all 0.15s ease;
}
</style>

</body>
</html>