<?php
require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function fmt_print_date() {
    return date("d-M-Y");
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        CUST_ID,
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_ABBR, '') AS CUST_ABBR,
        ISNULL(CUST_COMP, '') AS CUST_COMP,
        ISNULL(CURR_CODE, '') AS CURR_CODE,
        ISNULL(CUST_ADDR1, '') AS CUST_ADDR1,
        ISNULL(CUST_ADDR2, '') AS CUST_ADDR2,
        ISNULL(CUST_CITY, '') AS CUST_CITY,
        ISNULL(CUST_PHONE, '') AS CUST_PHONE,
        ISNULL(CUST_FAX, '') AS CUST_FAX,
        ISNULL(CUST_EMAIL, '') AS CUST_EMAIL,
        ISNULL(CUST_CONTA, '') AS CUST_CONTA,
        ISNULL(CUST_TERM, '') AS CUST_TERM,
        ISNULL(CUST_NPWP, '') AS CUST_NPWP,
        ISNULL(CUST_ALIAS, '') AS CUST_ALIAS,
        ISNULL(KPBC_ID, 0) AS KPBC_ID,
        ISNULL(CUST_INACTIVE, 0) AS CUST_INACTIVE,
        ISNULL(CUST_HSNO, '') AS CUST_HSNO,
        ISNULL(CUST_TPB, '') AS CUST_TPB
    FROM dbo.CUST
    ORDER BY CUST_CODE
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<pre>Query Customer List gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "CUST_ADDR1" => safe_trim($r["CUST_ADDR1"]),
        "CUST_ADDR2" => safe_trim($r["CUST_ADDR2"]),
        "CUST_CITY"  => safe_trim($r["CUST_CITY"])
    );
}

$rowsPerPage = 8;

if (count($rows) == 0) {
    $rows[] = array(
        "CUST_CODE"  => "",
        "CUST_COMP"  => "Data customer tidak ditemukan.",
        "CUST_ADDR1" => "",
        "CUST_ADDR2" => "",
        "CUST_CITY"  => ""
    );
}

$pages = array_chunk($rows, $rowsPerPage);
$totalPages = count($pages);

if ($totalPages <= 0) {
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer List</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 12px;
            color: #000000;
        }

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .page {
            width: 285mm;
            min-height: 198mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 10mm 8mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 36%;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 15px;
        }

        .company-title {
            font-size: 18px;
            font-weight: normal;
            margin-bottom: 2px;
        }

        .title-area {
            width: 34%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 24px;
            font-weight: normal;
            margin-top: 34px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 18px;
        }

        .print-date {
            margin-top: 20px;
        }

        .cust-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .cust-table th {
            font-size: 14px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #000000;
            padding: 0 4px 4px 4px;
            height: 22px;
        }

        .cust-table td {
            border: none;
            padding: 4px 4px;
            vertical-align: top;
            line-height: 16px;
            height: 58px;
            box-sizing: border-box;
            white-space: normal;
            overflow: hidden;
        }

        .col-code {
            width: 8%;
        }

        .col-company {
            width: 21%;
        }

        .col-address {
            width: 42%;
        }

        .col-city {
            width: 29%;
        }

        .company-cell {
            white-space: normal;
            word-wrap: break-word;
        }

        .address-cell {
            white-space: normal;
            word-wrap: break-word;
        }

        .city-cell {
            white-space: normal;
            word-wrap: break-word;
        }

        @media print {
            html,
            body {
                width: 297mm;
                height: 210mm;
                background: #ffffff;
            }

            .print-bar {
                display: none;
            }

            .page {
                width: 285mm;
                min-height: 198mm;
                margin: 0 auto;
                border: none;
                padding: 8mm 6mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="window.close()">CLOSE</button>
</div>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>

    <div class="page">

        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    E.P.Z. Kota Bukit Indah, Blok A-II No.29 E<br>
                    Purwakarta, Jawa Barat, INDONESIA<br>
                    Phone : (0264) 351440, (0264) 350441<br>
                    Fax : (0264) 351442
                </td>

                <td class="title-area">
                    <div class="report-title">CUSTOMER LIST</div>
                </td>

                <td class="right-info">
                    FM.CO.00-55<br>
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>

                    <div class="print-date">
                        Print Date : &nbsp; <?php echo h(fmt_print_date()); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="cust-table">
            <thead>
                <tr>
                    <th class="col-code">CODE</th>
                    <th class="col-company">COMPANY</th>
                    <th class="col-address">ADDRESS</th>
                    <th class="col-city">CITY</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php
                        $r = $pageRows[$i];

                        $address = $r["CUST_ADDR1"];

                        if ($r["CUST_ADDR2"] != "") {
                            if ($address != "") {
                                $address .= "<br>";
                            }

                            $address .= $r["CUST_ADDR2"];
                        }
                    ?>

                    <tr>
                        <td class="col-code">
                            <?php echo h($r["CUST_CODE"]); ?>
                        </td>

                        <td class="col-company company-cell">
                            <?php echo nl2br(h($r["CUST_COMP"])); ?>
                        </td>

                        <td class="col-address address-cell">
                            <?php echo $address == "" ? "&nbsp;" : nl2br(h($address)); ?>
                        </td>

                        <td class="col-city city-cell">
                            <?php echo h($r["CUST_CITY"]); ?>
                        </td>
                    </tr>
                <?php } ?>

                <?php
                    $fillCount = $rowsPerPage - count($pageRows);

                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>

                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>
<?php } ?>

</body>
</html>