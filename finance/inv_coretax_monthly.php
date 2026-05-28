<?php
// C:\xampp\htdocs\msii\finance\inv_coretax_monthly.php

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';

if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
} else {
    die('File config database_aging.php tidak ditemukan.');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmt($v) {
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    if ($v === null) return '';
    return (string)$v;
}

function toNumberCoretax($v) {
    if ($v instanceof DateTime || $v === null || $v === '') return 0;

    $s = trim((string)$v);
    $s = str_replace(' ', '', $s);

    if (preg_match('/^0[\.,][0-9]+$/', $s)) {
        $s = str_replace(',', '.', $s);
        return (float)$s;
    }

    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $lastComma = strrpos($s, ',');
        $lastDot   = strrpos($s, '.');

        if ($lastComma > $lastDot) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return (float)$s;
    }

    if (strpos($s, ',') !== false) {
        if (preg_match('/^[0-9]{1,3}(,[0-9]{3})+$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return (float)$s;
    }

    if (strpos($s, '.') !== false) {
        if (preg_match('/^[0-9]{1,3}(\.[0-9]{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return (float)$s;
    }

    return (float)$s;
}

function getExportCoretaxColumns() {
    return array(
        array('field' => 'Baris',              'title' => 'Baris',              'type' => 'number'),
        array('field' => 'Barang/Jasa',        'title' => 'Barang/Jasa',        'type' => 'text'),
        array('field' => 'Kode Barang Jasa',   'title' => 'Kode Barang Jasa',   'type' => 'text'),
        array('field' => 'Nama Barang/Jasa',   'title' => 'Nama Barang/Jasa',   'type' => 'text'),
        array('field' => 'Nama Satuan Ukur',   'title' => 'Nama Satuan Ukur',   'type' => 'text'),
        array('field' => 'Harga Satuan',       'title' => 'Harga Satuan',       'type' => 'number'),
        array('field' => 'Jumlah Barang Jasa', 'title' => 'Jumlah Barang Jasa', 'type' => 'number'),
        array('field' => 'Total Diskon',       'title' => 'Total Diskon',       'type' => 'number'),
        array('field' => 'DPP',                'title' => 'DPP',                'type' => 'number'),
        array('field' => 'DPP Nilai Lain',     'title' => 'DPP Nilai Lain',     'type' => 'number'),
        array('field' => 'Tarif PPN',          'title' => 'tarif ppn',          'type' => 'number'),
        array('field' => 'PPN',                'title' => 'PPN',                'type' => 'number'),
        array('field' => 'Tarif PPnBM',        'title' => 'Tarif PPnBM',        'type' => 'number'),
        array('field' => 'PPnBM',              'title' => 'PPnBM',              'type' => 'number')
    );
}

function getCoretaxMonthlyRows($fromDate, $toDate, $custCode) {
    $cols = array();
    $rows = array();

    $custParam = $custCode == '' ? '%' : $custCode . '%';

    $stmt = q("EXEC dbo.SP_INVOICE_CORETAX_MONTLY ?, ?, ?", array($fromDate, $toDate, $custParam));

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

function applyManualRateCoretax($rows, $rate) {
    $newRows = array();
    $rateNum = toNumberCoretax($rate);

    if ($rateNum <= 0) $rateNum = 16922;

    foreach ($rows as $r) {
        $hargaUsd = isset($r['Harga Satuan']) ? toNumberCoretax($r['Harga Satuan']) : 0;
        $qty      = isset($r['Jumlah Barang Jasa']) ? toNumberCoretax($r['Jumlah Barang Jasa']) : 0;

        $priceSebelumRound = $hargaUsd * $rateNum;
        $dppSebelumRound   = $priceSebelumRound * $qty;
        $dppNilaiLain      = $dppSebelumRound * 11 / 12;
        $ppnSebelumRound   = $dppNilaiLain * 0.12;

        $r['Harga Satuan']   = round($priceSebelumRound, 0);
        $r['DPP']            = round($dppSebelumRound, 0);
        $r['DPP Nilai Lain'] = round($dppNilaiLain, 0);
        $r['Tarif PPN']      = 12;
        $r['PPN']            = round($ppnSebelumRound, 0);
        $r['Tarif PPnBM']    = 0;
        $r['PPnBM']          = 0;

        $newRows[] = $r;
    }

    return $newRows;
}

function coretaxRowKey($r) {
    if (isset($r['RowKey']) && trim((string)$r['RowKey']) != '') {
        return trim((string)$r['RowKey']);
    }

    $ref = isset($r['Referensi']) ? trim((string)$r['Referensi']) : '';
    $baris = isset($r['Baris']) ? trim((string)$r['Baris']) : '';

    return $ref . '|' . $baris;
}

function getManualPriceScope($type, $a, $b, $c) {
    return $type . '|' . $a . '|' . $b . '|' . $c;
}

function applyManualPriceOverrideCoretax($rows, $scope) {
    if (!isset($_SESSION['coretax_manual_price'])) return $rows;
    if (!isset($_SESSION['coretax_manual_price'][$scope])) return $rows;

    $manual = $_SESSION['coretax_manual_price'][$scope];
    $newRows = array();

    foreach ($rows as $r) {
        $key = coretaxRowKey($r);

        if (isset($manual[$key])) {
            $hargaManual = toNumberCoretax($manual[$key]);
            $qty = isset($r['Jumlah Barang Jasa']) ? toNumberCoretax($r['Jumlah Barang Jasa']) : 0;

            $dpp = $hargaManual * $qty;
            $dppLain = $dpp * 11 / 12;
            $ppn = $dppLain * 0.12;

            $r['Harga Satuan']   = round($hargaManual, 0);
            $r['DPP']            = round($dpp, 0);
            $r['DPP Nilai Lain'] = round($dppLain, 0);
            $r['Tarif PPN']      = 12;
            $r['PPN']            = round($ppn, 0);
            $r['Tarif PPnBM']    = 0;
            $r['PPnBM']          = 0;
        }

        $newRows[] = $r;
    }

    return $newRows;
}

function xmlGet($row, $field, $default) {
    return isset($row[$field]) ? fmt($row[$field]) : $default;
}

function xmlDateCoretax($v) {
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    $v = trim((string)$v);
    if (strlen($v) >= 10) return substr($v, 0, 10);
    return $v;
}

function xmlNumberCoretax($v) {
    return (string)round(toNumberCoretax($v), 0);
}

function xmlAddText($doc, $parent, $name, $value) {
    $el = $doc->createElement($name);

    if ($value !== null && $value !== '') {
        $el->appendChild($doc->createTextNode((string)$value));
    }

    $parent->appendChild($el);
    return $el;
}

function buildCoretaxXml($rows) {
    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('TaxInvoiceBulk');
    $root->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $doc->appendChild($root);

    $tin = count($rows) > 0 ? xmlGet($rows[0], 'NPWP Penjual', '0010714269052000') : '0010714269052000';
    xmlAddText($doc, $root, 'TIN', $tin);

    $listInvoice = $doc->createElement('ListOfTaxInvoice');
    $root->appendChild($listInvoice);

    $groups = array();
    $order = array();

    foreach ($rows as $r) {
        $ref = xmlGet($r, 'Referensi', '');

        if ($ref == '') $ref = xmlGet($r, 'Nomor Dokumen Pembeli', '');
        if ($ref == '') $ref = 'NO_REF';

        if (!isset($groups[$ref])) {
            $groups[$ref] = array();
            $order[] = $ref;
        }

        $groups[$ref][] = $r;
    }

    foreach ($order as $refKey) {
        $items = $groups[$refKey];
        $head = $items[0];

        $taxInvoice = $doc->createElement('TaxInvoice');
        $listInvoice->appendChild($taxInvoice);

        $trxCode = xmlGet($head, 'Kode Transaksi', '');
        $addInfo = xmlGet($head, 'Keterangan Tambahan', '');

        if ($addInfo == '' && $trxCode == '07') {
            $addInfo = 'TD.00501';
        }

        xmlAddText($doc, $taxInvoice, 'TaxInvoiceDate', xmlDateCoretax(isset($head['Tanggal Faktur']) ? $head['Tanggal Faktur'] : ''));
        xmlAddText($doc, $taxInvoice, 'TaxInvoiceOpt', xmlGet($head, 'Jenis Faktur', 'Normal'));
        xmlAddText($doc, $taxInvoice, 'TrxCode', $trxCode);
        xmlAddText($doc, $taxInvoice, 'AddInfo', $addInfo);
        xmlAddText($doc, $taxInvoice, 'CustomDoc', xmlGet($head, 'Dokumen Pendukung', '0'));
        xmlAddText($doc, $taxInvoice, 'RefDesc', xmlGet($head, 'Referensi', ''));
        xmlAddText($doc, $taxInvoice, 'FacilityStamp', xmlGet($head, 'Cap Fasilitas', ''));
        xmlAddText($doc, $taxInvoice, 'SellerIDTKU', xmlGet($head, 'ID TKU Penjual', '0010714269052000000000'));
        xmlAddText($doc, $taxInvoice, 'BuyerTin', xmlGet($head, 'NPWP/NIK Pembeli', '0000000000000000'));
        xmlAddText($doc, $taxInvoice, 'BuyerDocument', xmlGet($head, 'Jenis ID Pembeli', 'Other ID'));
        xmlAddText($doc, $taxInvoice, 'BuyerCountry', xmlGet($head, 'Negara Pembeli', 'IDN'));
        xmlAddText($doc, $taxInvoice, 'BuyerDocumentNumber', xmlGet($head, 'Nomor Dokumen Pembeli', ''));
        xmlAddText($doc, $taxInvoice, 'BuyerName', xmlGet($head, 'Nama Pembeli', ''));
        xmlAddText($doc, $taxInvoice, 'BuyerAdress', xmlGet($head, 'Alamat Pembeli', ''));
        xmlAddText($doc, $taxInvoice, 'BuyerEmail', xmlGet($head, 'Email Pembeli', ''));
        xmlAddText($doc, $taxInvoice, 'BuyerIDTKU', xmlGet($head, 'ID TKU Pembeli', '000000'));

        $listGood = $doc->createElement('ListOfGoodService');
        $taxInvoice->appendChild($listGood);

        foreach ($items as $r) {
            $good = $doc->createElement('GoodService');
            $listGood->appendChild($good);

            xmlAddText($doc, $good, 'Opt', xmlGet($r, 'Barang/Jasa', 'A'));
            xmlAddText($doc, $good, 'Code', xmlGet($r, 'Kode Barang Jasa', '000000'));
            xmlAddText($doc, $good, 'Name', xmlGet($r, 'Nama Barang/Jasa', ''));
            xmlAddText($doc, $good, 'Unit', xmlGet($r, 'Nama Satuan Ukur', 'UM.0021'));
            xmlAddText($doc, $good, 'Price', xmlNumberCoretax(isset($r['Harga Satuan']) ? $r['Harga Satuan'] : 0));
            xmlAddText($doc, $good, 'Qty', xmlNumberCoretax(isset($r['Jumlah Barang Jasa']) ? $r['Jumlah Barang Jasa'] : 0));
            xmlAddText($doc, $good, 'TotalDiscount', xmlNumberCoretax(isset($r['Total Diskon']) ? $r['Total Diskon'] : 0));
            xmlAddText($doc, $good, 'TaxBase', xmlNumberCoretax(isset($r['DPP']) ? $r['DPP'] : 0));
            xmlAddText($doc, $good, 'OtherTaxBase', xmlNumberCoretax(isset($r['DPP Nilai Lain']) ? $r['DPP Nilai Lain'] : 0));
            xmlAddText($doc, $good, 'VATRate', xmlNumberCoretax(isset($r['Tarif PPN']) ? $r['Tarif PPN'] : 12));
            xmlAddText($doc, $good, 'VAT', xmlNumberCoretax(isset($r['PPN']) ? $r['PPN'] : 0));
            xmlAddText($doc, $good, 'STLGRate', xmlNumberCoretax(isset($r['Tarif PPnBM']) ? $r['Tarif PPnBM'] : 0));
            xmlAddText($doc, $good, 'STLG', xmlNumberCoretax(isset($r['PPnBM']) ? $r['PPnBM'] : 0));
        }
    }

    return $doc->saveXML();
}

function downloadCoretaxXml($rows, $filename) {
    header("Content-Type: application/xml; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo buildCoretaxXml($rows);
    exit;
}

function downloadCoretaxExcel($rows, $filename) {
    $exportCols = getExportCoretaxColumns();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    echo "<html><head><meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; font-family: Arial; font-size: 10pt; }";
    echo "th { border: 1px solid #999; font-weight: normal; background: #ffffff; white-space: nowrap; }";
    echo "td { border: 1px solid #999; white-space: nowrap; }";
    echo ".txt { mso-number-format:'\\@'; }";
    echo ".num { mso-number-format:'0'; text-align:right; }";
    echo ".head-red { color:red; }";
    echo "</style></head><body><table>";

    echo "<tr>";
    foreach ($exportCols as $col) {
        $title = $col['title'];
        $headClass = '';

        if ($title == 'Nama Barang/Jasa' || $title == 'Harga Satuan' || $title == 'Jumlah Barang Jasa') {
            $headClass = 'head-red';
        }

        echo "<th class=\"" . $headClass . "\">" . h($title) . "</th>";
    }
    echo "</tr>";

    foreach ($rows as $r) {
        echo "<tr>";

        foreach ($exportCols as $col) {
            $field = $col['field'];
            $type  = $col['type'];
            $val = isset($r[$field]) ? $r[$field] : '';

            if ($type == 'number') {
                echo "<td class=\"num\">" . h(xmlNumberCoretax($val)) . "</td>";
            } else {
                echo "<td class=\"txt\">" . h(fmt($val)) . "</td>";
            }
        }

        echo "</tr>";
    }

    echo "</table></body></html>";
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'save_price') {
    header('Content-Type: application/json; charset=utf-8');

    $fromDate = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
    $toDate   = isset($_POST['to_date']) ? trim($_POST['to_date']) : '';
    $custCode = isset($_POST['cust_code']) ? trim($_POST['cust_code']) : '';
    $key      = isset($_POST['key']) ? trim($_POST['key']) : '';
    $price    = isset($_POST['price']) ? trim($_POST['price']) : '';

    if ($fromDate == '' || $toDate == '' || $key == '') {
        echo json_encode(array('success' => false, 'message' => 'Parameter tidak lengkap.'));
        exit;
    }

    $scope = getManualPriceScope('MONTHLY', $fromDate, $toDate, $custCode);

    if (!isset($_SESSION['coretax_manual_price'])) {
        $_SESSION['coretax_manual_price'] = array();
    }

    if (!isset($_SESSION['coretax_manual_price'][$scope])) {
        $_SESSION['coretax_manual_price'][$scope] = array();
    }

    $_SESSION['coretax_manual_price'][$scope][$key] = toNumberCoretax($price);

    echo json_encode(array('success' => true));
    exit;
}

if ($action == 'search_customer') {
    header('Content-Type: application/json; charset=utf-8');

    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    if ($term == '') {
        echo json_encode(array());
        exit;
    }

    $sql = "
        SELECT TOP 30
            CUST_CODE,
            CUST_COMP
        FROM dbo.CUST
        WHERE CUST_CODE LIKE ?
           OR CUST_COMP LIKE ?
        ORDER BY CUST_CODE
    ";

    $stmt = q($sql, array('%' . $term . '%', '%' . $term . '%'));

    $data = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array(
            'cust_code' => $row['CUST_CODE'],
            'label'     => $row['CUST_CODE'] . ' | ' . $row['CUST_COMP']
        );
    }

    echo json_encode($data);
    exit;
}

if ($action == 'export_excel' || $action == 'export_xml') {
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
    $toDate   = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
    $custCode = isset($_GET['cust_code']) ? trim($_GET['cust_code']) : '';
    $rate     = isset($_GET['rate']) ? trim($_GET['rate']) : '';

    if ($fromDate == '' || $toDate == '') {
        die('Tanggal belum lengkap.');
    }

    $result = getCoretaxMonthlyRows($fromDate, $toDate, $custCode);
    $rows = $result[1];

    $rows = applyManualRateCoretax($rows, $rate);

    $scope = getManualPriceScope('MONTHLY', $fromDate, $toDate, $custCode);
    $rows = applyManualPriceOverrideCoretax($rows, $scope);

    $safeCust = $custCode == '' ? 'ALL' : preg_replace('/[^A-Za-z0-9_\-]/', '_', $custCode);
    $safeFrom = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fromDate);
    $safeTo   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $toDate);

    if ($action == 'export_xml') {
        downloadCoretaxXml($rows, 'CORETAX_BY_DATE_' . $safeFrom . '_' . $safeTo . '_' . $safeCust . '_' . date('YmdHis') . '.xml');
    } else {
        downloadCoretaxExcel($rows, 'CORETAX_BY_DATE_' . $safeFrom . '_' . $safeTo . '_' . $safeCust . '_' . date('YmdHis') . '.xls');
    }
}

$fromDate = '';
$toDate = '';
$custCode = '';
$rate = '16922';
$cols = array();
$rows = array();
$isLoaded = false;
$exportCols = getExportCoretaxColumns();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_load'])) {
    $fromDate = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
    $toDate   = isset($_POST['to_date']) ? trim($_POST['to_date']) : '';
    $custCode = isset($_POST['cust_code']) ? trim($_POST['cust_code']) : '';
    $rate     = isset($_POST['rate']) ? trim($_POST['rate']) : '16922';

    if ($fromDate != '' && $toDate != '') {
        $result = getCoretaxMonthlyRows($fromDate, $toDate, $custCode);
        $cols = $result[0];
        $rows = $result[1];

        $rows = applyManualRateCoretax($rows, $rate);

        $scope = getManualPriceScope('MONTHLY', $fromDate, $toDate, $custCode);
        $rows = applyManualPriceOverrideCoretax($rows, $scope);

        $isLoaded = true;
    }
}
?>

<?php include 'layout.php'; ?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3 class="fw-bold text-dark mb-0">Invoice Coretax By Date / Customer</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-success text-white fw-bold">
            Parameter Report
        </div>

        <div class="card-body">
            <form method="post" autocomplete="off">
                <div class="row g-3 align-items-end">

                    <div class="col-md-2">
                        <label class="form-label fw-bold">From Date</label>
                        <input type="date"
                               name="from_date"
                               id="from_date"
                               class="form-control"
                               value="<?php echo h($fromDate); ?>"
                               required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">To Date</label>
                        <input type="date"
                               name="to_date"
                               id="to_date"
                               class="form-control"
                               value="<?php echo h($toDate); ?>"
                               required>
                    </div>

                    <div class="col-md-3 position-relative">
                        <label class="form-label fw-bold">Customer</label>
                        <input type="text"
                               name="cust_code"
                               id="cust_code"
                               class="form-control"
                               value="<?php echo h($custCode); ?>"
                               placeholder="Kosongkan untuk semua customer">

                        <div id="autocomplete-box"
                             class="list-group position-absolute w-100"
                             style="z-index:9999; display:none; max-height:260px; overflow-y:auto;">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Rate USD ke IDR</label>
                        <input type="text"
                               name="rate"
                               id="rate"
                               class="form-control"
                               value="<?php echo h($rate); ?>"
                               placeholder="Contoh: 16922"
                               required>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" name="btn_load" class="btn btn-success">
                            <i class="bi bi-search"></i> Load
                        </button>

                        <?php if ($isLoaded && count($rows) > 0) { ?>
                            <button type="button" class="btn btn-primary" onclick="exportCoretaxMonthly('excel')">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </button>

                            <button type="button" class="btn btn-warning" onclick="exportCoretaxMonthly('xml')">
                                <i class="bi bi-file-earmark-code"></i> Export XML
                            </button>

                            <button type="button" onclick="window.print()" class="btn btn-dark">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        <?php } ?>

                        <a href="inv_coretax_monthly.php" class="btn btn-outline-danger">
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
                Data tidak ditemukan.
            </div>
        <?php } else { ?>

            <div class="alert alert-info no-print">
                Periode:
                <b><?php echo h($fromDate); ?></b>
                s/d
                <b><?php echo h($toDate); ?></b>
                |
                Customer:
                <b><?php echo h($custCode == '' ? 'ALL' : $custCode); ?></b>
                |
                Rate USD ke IDR:
                <b><?php echo h($rate); ?></b>
                |
                Total Baris:
                <b><?php echo count($rows); ?></b>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-bold">
                    Hasil Report Coretax By Date / Customer
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:650px;">
                        <table class="table table-bordered table-striped table-sm mb-0">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <?php foreach ($exportCols as $col) { ?>
                                        <?php
                                        $title = $col['title'];
                                        $headStyle = 'white-space:nowrap;';
                                        if ($title == 'Nama Barang/Jasa' || $title == 'Harga Satuan' || $title == 'Jumlah Barang Jasa') {
                                            $headStyle .= 'color:red;';
                                        }
                                        ?>
                                        <th style="<?php echo $headStyle; ?>">
                                            <?php echo h($title); ?>
                                        </th>
                                    <?php } ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rows as $r) { ?>
                                    <?php
                                    $rowKey = coretaxRowKey($r);
                                    $qtyRow = isset($r['Jumlah Barang Jasa']) ? xmlNumberCoretax($r['Jumlah Barang Jasa']) : 0;
                                    ?>
                                    <tr data-key="<?php echo h($rowKey); ?>" data-qty="<?php echo h($qtyRow); ?>">
                                        <?php foreach ($exportCols as $col) { ?>
                                            <?php
                                            $field = $col['field'];
                                            $type  = $col['type'];
                                            $val = isset($r[$field]) ? $r[$field] : '';

                                            $cellClass = '';

                                            if ($field == 'DPP') {
                                                $cellClass = 'cell-dpp';
                                            } elseif ($field == 'DPP Nilai Lain') {
                                                $cellClass = 'cell-dpp-lain';
                                            } elseif ($field == 'PPN') {
                                                $cellClass = 'cell-ppn';
                                            }

                                            if ($type == 'number') {
                                                $val = xmlNumberCoretax($val);
                                            }
                                            ?>

                                            <?php if ($field == 'Harga Satuan') { ?>
                                                <td style="white-space:nowrap; text-align:right;">
                                                    <input type="text"
                                                           class="form-control form-control-sm text-end price-edit"
                                                           value="<?php echo h($val); ?>"
                                                           data-key="<?php echo h($rowKey); ?>"
                                                           style="width:90px; padding:2px 4px;">
                                                </td>
                                            <?php } else { ?>
                                                <td class="<?php echo $cellClass; ?>"
                                                    style="white-space:nowrap; <?php echo ($type == 'number') ? 'text-align:right;' : ''; ?>">
                                                    <?php echo h(fmt($val)); ?>
                                                </td>
                                            <?php } ?>
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

        <div class="alert alert-secondary no-print">
            Isi parameter <b>tanggal</b>, <b>customer</b>, dan <b>rate USD ke IDR</b>, lalu klik <b>Load</b>.
            Customer boleh dikosongkan untuk semua customer. Jika invoice sudah IDR, isi rate <b>1</b>.
        </div>

    <?php } ?>

</div>

<script>
var input = document.getElementById('cust_code');
var box = document.getElementById('autocomplete-box');
var timer = null;

if (input) {
    input.onkeyup = function(e) {
        e = e || window.event;

        var term = input.value.replace(/^\s+|\s+$/g, '');

        if (e.keyCode == 13) {
            box.style.display = 'none';
            return;
        }

        clearTimeout(timer);

        if (term.length < 1) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        timer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'inv_coretax_monthly.php?action=search_customer&term=' + encodeURIComponent(term), true);

            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        var data = parseJsonSafe(xhr.responseText);

                        box.innerHTML = '';

                        if (!data || data.length == 0) {
                            box.style.display = 'none';
                            return;
                        }

                        for (var i = 0; i < data.length; i++) {
                            createAutoItem(data[i]);
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
}

function parseJsonSafe(text) {
    var data = [];

    try {
        data = JSON.parse(text);
    } catch (err) {
        data = [];
    }

    return data;
}

function createAutoItem(item) {
    var a = document.createElement('button');
    a.type = 'button';
    a.className = 'list-group-item list-group-item-action';
    a.appendChild(document.createTextNode(item.label));

    a.onclick = function() {
        input.value = item.cust_code;
        box.style.display = 'none';
    };

    box.appendChild(a);
}

function cleanNumberCoretax(v) {
    v = String(v);
    v = v.replace(/,/g, '');
    v = v.replace(/[^\d\.\-]/g, '');

    var n = parseFloat(v);

    if (isNaN(n)) {
        return 0;
    }

    return n;
}

function round0Coretax(n) {
    return Math.round(n);
}

function findChildByClass(parent, className) {
    var els = parent.getElementsByTagName('*');

    for (var i = 0; i < els.length; i++) {
        if ((' ' + els[i].className + ' ').indexOf(' ' + className + ' ') > -1) {
            return els[i];
        }
    }

    return null;
}

function recalcGridRow(inputObj) {
    var tr = inputObj.parentNode.parentNode;
    var price = cleanNumberCoretax(inputObj.value);
    var qty = cleanNumberCoretax(tr.getAttribute('data-qty'));

    var dpp = round0Coretax(price * qty);
    var dppLain = round0Coretax((price * qty) * 11 / 12);
    var ppn = round0Coretax(((price * qty) * 11 / 12) * 0.12);

    var dppCell = findChildByClass(tr, 'cell-dpp');
    var dppLainCell = findChildByClass(tr, 'cell-dpp-lain');
    var ppnCell = findChildByClass(tr, 'cell-ppn');

    if (dppCell) dppCell.innerHTML = dpp;
    if (dppLainCell) dppLainCell.innerHTML = dppLain;
    if (ppnCell) ppnCell.innerHTML = ppn;

    inputObj.value = round0Coretax(price);
}

function savePriceMonthly(inputObj, asyncMode) {
    var fromDate = document.getElementById('from_date').value;
    var toDate = document.getElementById('to_date').value;
    var custCode = document.getElementById('cust_code').value;
    var key = inputObj.getAttribute('data-key');
    var price = cleanNumberCoretax(inputObj.value);

    if (fromDate == '' || toDate == '' || key == '') {
        return;
    }

    recalcGridRow(inputObj);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'inv_coretax_monthly.php?action=save_price', asyncMode);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.send(
        'from_date=' + encodeURIComponent(fromDate) +
        '&to_date=' + encodeURIComponent(toDate) +
        '&cust_code=' + encodeURIComponent(custCode) +
        '&key=' + encodeURIComponent(key) +
        '&price=' + encodeURIComponent(price)
    );
}

function focusPriceInput(currentInput, direction) {
    var inputs = document.getElementsByClassName('price-edit');
    var idx = -1;

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i] == currentInput) {
            idx = i;
            break;
        }
    }

    if (idx < 0) {
        return;
    }

    var nextIdx = idx + direction;

    if (nextIdx < 0 || nextIdx >= inputs.length) {
        return;
    }

    inputs[nextIdx].focus();
    inputs[nextIdx].select();
}

document.onkeydown = function(e) {
    e = e || window.event;
    var target = e.target || e.srcElement;

    if (!target || !target.className || target.className.indexOf('price-edit') < 0) {
        return;
    }

    if (e.keyCode == 13 || e.keyCode == 40 || e.keyCode == 38) {
        if (e.preventDefault) e.preventDefault();
        e.returnValue = false;

        savePriceMonthly(target, true);

        if (e.keyCode == 38) {
            focusPriceInput(target, -1);
        } else {
            focusPriceInput(target, 1);
        }

        return false;
    }
};

document.onfocusout = function(e) {
    e = e || window.event;
    var target = e.target || e.srcElement;

    if (target && target.className && target.className.indexOf('price-edit') >= 0) {
        savePriceMonthly(target, true);
    }
};

function saveAllManualPricesMonthlySync() {
    var inputs = document.getElementsByClassName('price-edit');

    for (var i = 0; i < inputs.length; i++) {
        savePriceMonthly(inputs[i], false);
    }
}

function exportCoretaxMonthly(type) {
    saveAllManualPricesMonthlySync();

    var fromDate = document.getElementById('from_date').value;
    var toDate   = document.getElementById('to_date').value;
    var custCode = document.getElementById('cust_code').value;
    var rate     = document.getElementById('rate').value;

    if (fromDate == '') {
        alert('From Date belum diisi.');
        document.getElementById('from_date').focus();
        return;
    }

    if (toDate == '') {
        alert('To Date belum diisi.');
        document.getElementById('to_date').focus();
        return;
    }

    if (rate == '') {
        alert('Rate USD ke IDR belum diisi.');
        document.getElementById('rate').focus();
        return;
    }

    var action = type == 'xml' ? 'export_xml' : 'export_excel';

    window.location.href =
        'inv_coretax_monthly.php?action=' + action +
        '&from_date=' + encodeURIComponent(fromDate) +
        '&to_date=' + encodeURIComponent(toDate) +
        '&cust_code=' + encodeURIComponent(custCode) +
        '&rate=' + encodeURIComponent(rate);
}

document.onclick = function(e) {
    e = e || window.event;
    var target = e.target || e.srcElement;

    if (box && input) {
        if (target != input && !isChildOf(target, box)) {
            box.style.display = 'none';
        }
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
    .no-print,
    .btn,
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

    .card-header {
        font-weight: bold;
        background: #fff !important;
        color: #000 !important;
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