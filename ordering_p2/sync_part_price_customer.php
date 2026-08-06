<?php
// sync_part_price_customer.php
// Kompatibel PHP 5.4 + Microsoft SQL Server Driver (sqlsrv).

date_default_timezone_set('Asia/Jakarta');

if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/database_ordering.php";

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function createCsrfToken()
{
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(24));
    }

    return sha1(uniqid(mt_rand(), true));
}

// Pengganti hash_equals() agar tetap kompatibel dengan PHP 5.4.
function safeEquals($known, $given)
{
    $known = (string)$known;
    $given = (string)$given;

    if (strlen($known) !== strlen($given)) {
        return false;
    }

    $result = 0;
    $length = strlen($known);

    for ($i = 0; $i < $length; $i++) {
        $result |= ord($known[$i]) ^ ord($given[$i]);
    }

    return $result === 0;
}

function sqlsrvErrorText()
{
    if (!function_exists('sqlsrv_errors')) {
        return 'Query database gagal.';
    }

    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!is_array($errors) || count($errors) === 0) {
        return 'Query database gagal tanpa detail error.';
    }

    $messages = array();

    foreach ($errors as $error) {
        $sqlState = isset($error['SQLSTATE']) ? $error['SQLSTATE'] : '-';
        $code = isset($error['code']) ? $error['code'] : '-';
        $message = isset($error['message']) ? $error['message'] : 'Unknown error';

        $messages[] = '[' . $sqlState . '/' . $code . '] ' . $message;
    }

    return implode(' | ', $messages);
}

/**
 * Menjalankan query melalui helper q() jika tersedia,
 * atau langsung memakai $conn dari database_ordering.php.
 */
function executeDatabaseQuery($sql, $params)
{
    global $conn;

    if (function_exists('q')) {
        return q($sql, $params);
    }

    if (isset($conn) && $conn !== false && function_exists('sqlsrv_query')) {
        return sqlsrv_query($conn, $sql, $params);
    }

    return false;
}

/**
 * Membaca result set bila stored procedure mengembalikan:
 * SELECT @RowsUpdated AS RowsUpdated;
 *
 * Stored procedure versi sekarang hanya memakai PRINT, jadi nilai ini
 * kemungkinan tetap NULL sampai SELECT tersebut ditambahkan.
 */
function consumeProcedureResults($stmt, &$rowsUpdated)
{
    $rowsUpdated = null;

    while (true) {
        if (function_exists('sqlsrv_num_fields')) {
            $fieldCount = @sqlsrv_num_fields($stmt);

            if ($fieldCount !== false && $fieldCount > 0) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    if (isset($row['RowsUpdated'])) {
                        $rowsUpdated = (int)$row['RowsUpdated'];
                    } elseif (isset($row['ROWS_UPDATED'])) {
                        $rowsUpdated = (int)$row['ROWS_UPDATED'];
                    } elseif (isset($row['rows_updated'])) {
                        $rowsUpdated = (int)$row['rows_updated'];
                    }
                }
            }
        }

        $nextResult = sqlsrv_next_result($stmt);

        if ($nextResult === true) {
            continue;
        }

        // NULL berarti sudah tidak ada result set lagi.
        // FALSE berarti terjadi error saat memproses result berikutnya.
        return $nextResult !== false;
    }
}

if (!isset($_SESSION['sync_part_price_csrf'])) {
    $_SESSION['sync_part_price_csrf'] = createCsrfToken();
}

$message = '';
$messageType = '';
$rowsUpdated = null;

$selectedMonth = (int)date('n');
$selectedYear = (int)date('Y');
$selectedCustomer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedMonth = isset($_POST['mm']) && !is_array($_POST['mm'])
        ? (int)$_POST['mm']
        : 0;

    $selectedYear = isset($_POST['yy']) && !is_array($_POST['yy'])
        ? (int)$_POST['yy']
        : 0;

    $selectedCustomer = isset($_POST['cust_code']) && !is_array($_POST['cust_code'])
        ? trim($_POST['cust_code'])
        : '';

    $postedToken = isset($_POST['csrf_token']) && !is_array($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (!safeEquals($_SESSION['sync_part_price_csrf'], $postedToken)) {
        $message = 'Sesi form tidak valid. Muat ulang halaman lalu coba kembali.';
        $messageType = 'error';
    } elseif ($selectedMonth < 1 || $selectedMonth > 12) {
        $message = 'Bulan harus berada antara 1 sampai 12.';
        $messageType = 'error';
    } elseif ($selectedYear < 1900 || $selectedYear > 2100) {
        $message = 'Tahun tidak valid.';
        $messageType = 'error';
    } elseif ($selectedCustomer === '') {
        $message = 'Customer harus dipilih atau diisi.';
        $messageType = 'error';
    } elseif (strlen($selectedCustomer) > 50) {
        $message = 'Kode customer maksimal 50 karakter.';
        $messageType = 'error';
    } else {
        $sql = "
            EXEC dbo.usp_SyncPartPrice_cust
                @MM = ?,
                @YY = ?,
                @CUST_CODE = ?
        ";

        $params = array(
            $selectedMonth,
            $selectedYear,
            $selectedCustomer
        );

        $stmt = executeDatabaseQuery($sql, $params);

        if ($stmt === false) {
            $message = 'Sinkronisasi gagal. ' . sqlsrvErrorText();
            $messageType = 'error';
        } else {
            $resultOk = consumeProcedureResults($stmt, $rowsUpdated);

            if (function_exists('sqlsrv_free_stmt')) {
                sqlsrv_free_stmt($stmt);
            }

            if (!$resultOk) {
                $message = 'Sinkronisasi gagal saat memproses stored procedure. ' . sqlsrvErrorText();
                $messageType = 'error';
            } else {
                $periodLabel = str_pad((string)$selectedMonth, 2, '0', STR_PAD_LEFT)
                    . '/' . $selectedYear;

                $message = 'Sinkronisasi harga berhasil dijalankan untuk customer '
                    . $selectedCustomer . ' periode ' . $periodLabel . '.';

                if ($rowsUpdated !== null) {
                    $message .= ' Baris yang diperbarui: ' . number_format($rowsUpdated, 0, ',', '.') . '.';
                }

                $messageType = 'success';

                // Token diperbarui setelah submit sukses untuk mencegah pengiriman ulang.
                $_SESSION['sync_part_price_csrf'] = createCsrfToken();
            }
        }
    }
}

$monthNames = array(
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
);

// Sesuaikan bila endpoint autocomplete berada di folder berbeda.
$autocompleteUrl = 'ajax_customer_autocomplete.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Sinkronisasi Harga Part per Customer</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 16px;
            background: #f4f6f9;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .page {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
        }

        .card {
            overflow: visible;
            background: #ffffff;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h1 {
            margin: 0 0 6px;
            font-size: 22px;
            line-height: 1.3;
        }

        .card-header p {
            margin: 0;
            color: #6b7280;
            line-height: 1.5;
        }

        .card-body {
            padding: 24px;
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-group {
            position: relative;
            flex: 1;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        button {
            font: inherit;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            height: 42px;
            padding: 9px 11px;
            background: #fff;
            border: 1px solid #cfd5dc;
            border-radius: 5px;
            color: #111827;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.13);
        }

        .help {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.4;
        }

        .selected-customer {
            display: none;
            margin-top: 7px;
            color: #166534;
            font-size: 13px;
            font-weight: bold;
        }

        .autocomplete-list {
            position: absolute;
            z-index: 1000;
            top: 69px;
            left: 0;
            right: 0;
            display: none;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #cfd5dc;
            border-radius: 5px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.13);
        }

        .autocomplete-item {
            padding: 10px 12px;
            border-bottom: 1px solid #edf0f2;
            cursor: pointer;
            line-height: 1.35;
        }

        .autocomplete-item:last-child {
            border-bottom: 0;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #eff6ff;
        }

        .autocomplete-code {
            display: block;
            color: #111827;
            font-weight: bold;
        }

        .autocomplete-company {
            display: block;
            margin-top: 2px;
            color: #6b7280;
            font-size: 12px;
        }

        .autocomplete-empty {
            padding: 12px;
            color: #6b7280;
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 5px;
            line-height: 1.5;
            word-break: break-word;
        }

        .alert-success {
            background: #ecfdf3;
            border: 1px solid #a7f3d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 4px;
        }

        .btn {
            min-width: 190px;
            padding: 11px 18px;
            border: 0;
            border-radius: 5px;
            background: #2563eb;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px 10px;
            }

            .form-row {
                display: block;
            }

            .card-header,
            .card-body {
                padding: 18px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="card-header">
            <h1>Sinkronisasi Harga Part</h1>
            <p>
                Memperbarui harga part berdasarkan customer dan periode
                menggunakan <strong>usp_SyncPartPrice_cust</strong>.
            </p>
        </div>

        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <form id="syncForm" method="post" action="" autocomplete="off">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo h($_SESSION['sync_part_price_csrf']); ?>"
                >

                <div class="form-row">
                    <div class="form-group">
                        <label for="mm">Bulan</label>
                        <select id="mm" name="mm" required>
                            <?php foreach ($monthNames as $monthNumber => $monthName): ?>
                                <option
                                    value="<?php echo (int)$monthNumber; ?>"
                                    <?php echo $selectedMonth === (int)$monthNumber ? 'selected' : ''; ?>
                                >
                                    <?php echo h($monthName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="yy">Tahun</label>
                        <input
                            id="yy"
                            name="yy"
                            type="number"
                            min="1900"
                            max="2100"
                            step="1"
                            value="<?php echo h($selectedYear); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group" id="customerGroup">
                    <label for="cust_code">Customer</label>
                    <input
                        id="cust_code"
                        name="cust_code"
                        type="text"
                        maxlength="50"
                        value="<?php echo h($selectedCustomer); ?>"
                        placeholder="Ketik kode atau nama customer..."
                        aria-autocomplete="list"
                        aria-expanded="false"
                        required
                    >

                    <div
                        id="customerSuggestions"
                        class="autocomplete-list"
                        role="listbox"
                    ></div>

                    <span class="help">
                        Pilih hasil autocomplete agar kode customer yang dikirim tepat.
                        Kode customer juga dapat diketik langsung.
                    </span>

                    <span id="selectedCustomerInfo" class="selected-customer"></span>
                </div>

                <div class="actions">
                    <button id="submitButton" class="btn" type="submit">
                        Sinkronisasi Harga
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var autocompleteUrl = <?php echo json_encode($autocompleteUrl); ?>;
    var input = document.getElementById('cust_code');
    var list = document.getElementById('customerSuggestions');
    var info = document.getElementById('selectedCustomerInfo');
    var form = document.getElementById('syncForm');
    var submitButton = document.getElementById('submitButton');

    var timer = null;
    var xhr = null;
    var items = [];
    var activeIndex = -1;

    function trim(value) {
        return String(value).replace(/^\s+|\s+$/g, '');
    }

    function hideList() {
        list.style.display = 'none';
        list.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        items = [];
        activeIndex = -1;
    }

    function showInfo(customer) {
        if (!customer) {
            info.style.display = 'none';
            info.innerHTML = '';
            return;
        }

        var text = customer.code;

        if (customer.company) {
            text += ' - ' + customer.company;
        }

        info.innerHTML = '';
        info.appendChild(document.createTextNode('Dipilih: ' + text));
        info.style.display = 'block';
    }

    function setActive(index) {
        var nodes = list.getElementsByClassName('autocomplete-item');
        var i;

        activeIndex = index;

        for (i = 0; i < nodes.length; i++) {
            if (i === activeIndex) {
                nodes[i].className = 'autocomplete-item active';
                nodes[i].scrollIntoView(false);
            } else {
                nodes[i].className = 'autocomplete-item';
            }
        }
    }

    function chooseCustomer(customer) {
        input.value = customer.code || '';
        showInfo(customer);
        hideList();
        input.focus();
    }

    function renderResults(data) {
        var i;
        var item;
        var codeElement;
        var companyElement;

        list.innerHTML = '';
        items = data || [];
        activeIndex = -1;

        if (!items.length) {
            item = document.createElement('div');
            item.className = 'autocomplete-empty';
            item.appendChild(document.createTextNode('Customer tidak ditemukan.'));
            list.appendChild(item);
            list.style.display = 'block';
            input.setAttribute('aria-expanded', 'true');
            return;
        }

        for (i = 0; i < items.length; i++) {
            (function (customer, index) {
                item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.setAttribute('role', 'option');

                codeElement = document.createElement('span');
                codeElement.className = 'autocomplete-code';
                codeElement.appendChild(
                    document.createTextNode(customer.code || customer.CUST_CODE || '')
                );

                companyElement = document.createElement('span');
                companyElement.className = 'autocomplete-company';
                companyElement.appendChild(
                    document.createTextNode(
                        customer.company ||
                        customer.CUST_COMP ||
                        customer.abbr ||
                        customer.CUST_ABBR ||
                        ''
                    )
                );

                item.appendChild(codeElement);
                item.appendChild(companyElement);

                item.onmousedown = function (event) {
                    if (event && event.preventDefault) {
                        event.preventDefault();
                    }

                    chooseCustomer({
                        code: customer.code || customer.CUST_CODE || '',
                        company: customer.company || customer.CUST_COMP || ''
                    });
                };

                list.appendChild(item);
            }(items[i], i));
        }

        list.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
    }

    function loadCustomers(term) {
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
        }

        xhr = new XMLHttpRequest();
        xhr.open(
            'GET',
            autocompleteUrl + '?term=' + encodeURIComponent(term) + '&_=' + new Date().getTime(),
            true
        );

        xhr.onreadystatechange = function () {
            var response;

            if (xhr.readyState !== 4) {
                return;
            }

            if (xhr.status < 200 || xhr.status >= 300) {
                hideList();
                return;
            }

            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                hideList();
                return;
            }

            renderResults(response);
        };

        xhr.send(null);
    }

    input.oninput = function () {
        var term = trim(input.value);

        showInfo(null);

        if (timer) {
            window.clearTimeout(timer);
        }

        if (term.length < 1) {
            hideList();
            return;
        }

        timer = window.setTimeout(function () {
            loadCustomers(term);
        }, 250);
    };

    input.onkeydown = function (event) {
        event = event || window.event;

        if (list.style.display !== 'block' || !items.length) {
            return;
        }

        if (event.keyCode === 40) {
            // Panah bawah.
            setActive(activeIndex < items.length - 1 ? activeIndex + 1 : 0);
            event.preventDefault();
        } else if (event.keyCode === 38) {
            // Panah atas.
            setActive(activeIndex > 0 ? activeIndex - 1 : items.length - 1);
            event.preventDefault();
        } else if (event.keyCode === 13 && activeIndex >= 0) {
            // Enter.
            chooseCustomer({
                code: items[activeIndex].code || items[activeIndex].CUST_CODE || '',
                company: items[activeIndex].company || items[activeIndex].CUST_COMP || ''
            });
            event.preventDefault();
        } else if (event.keyCode === 27) {
            // Escape.
            hideList();
        }
    };

    document.onclick = function (event) {
        event = event || window.event;
        var target = event.target || event.srcElement;

        if (target !== input && target !== list) {
            hideList();
        }
    };

    form.onsubmit = function () {
        var customerCode = trim(input.value);

        if (customerCode === '') {
            alert('Customer harus dipilih atau diisi.');
            input.focus();
            return false;
        }

        input.value = customerCode;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Memproses...';

        return true;
    };
}());
</script>
</body>
</html>
