<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function ymd_param($value) {
    $value = trim($value);

    if ($value == "") return "";
    if (preg_match('/^\d{8}$/', $value)) return $value;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
    }

    $ts = strtotime($value);
    if ($ts === false) return "";

    return date("Ymd", $ts);
}

function date_input_value($value, $default) {
    $value = trim($value);

    if ($value == "") return $default;

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }

    $ts = strtotime($value);
    if ($ts === false) return $default;

    return date("Y-m-d", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num($value) {
    if ($value === null || $value === "") return "-";

    $n = (float)$value;

    if ($n == 0) return "-";

    if ($n < 0) {
        return "(" . number_format(abs($n), 0, ".", ",") . ")";
    }

    return number_format($n, 0, ".", ",");
}

function ymd_to_dmy($ymd) {
    $ymd = trim((string)$ymd);

    if (!preg_match('/^\d{8}$/', $ymd)) return $ymd;

    return substr($ymd, 6, 2) . "-" . substr($ymd, 4, 2) . "-" . substr($ymd, 0, 4);
}

$is_filter = get_param("RUN", "") == "1";

/* ======================================================
   DEFAULT: HARI INI SAMPAI AKHIR BULAN
====================================================== */
$defaultStart = date("Y-m-d");
$defaultEnd   = date("Y-m-t");

$start_input = date_input_value(get_param("START_DATE", ""), $defaultStart);
$end_input   = date_input_value(get_param("END_DATE", ""), $defaultEnd);
$cust_code   = get_param("CUST_CODE", "");
$stock_type  = get_param("STOCK_TYPE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = ymd_param($start_input);
$end_ymd   = ymd_param($end_input);

/* ======================================================
   ABAIKAN TANGGAL YANG SUDAH LEWAT
   JIKA START DATE < HARI INI, DIPAKSA MULAI HARI INI
====================================================== */
$today_ymd = date("Ymd");

if ($start_ymd != "" && $start_ymd < $today_ymd) {
    $start_ymd   = $today_ymd;
    $start_input = date("Y-m-d");
}

$rows = array();

/* ======================================================
   CUSTOMER LIST UNTUK AUTOCOMPLETE
====================================================== */
$custList = array();

$sqlCust = "
    SELECT DISTINCT
        LTRIM(RTRIM(CUST_CODE)) AS CUST_CODE,
        LTRIM(RTRIM(CUST_COMP)) AS CUST_COMP
    FROM dbo.CUST
    WHERE ISNULL(CUST_CODE, '') <> ''
    ORDER BY LTRIM(RTRIM(CUST_CODE))
";

$stmtCust = sqlsrv_query($conn, $sqlCust);

if ($stmtCust !== false) {
    while ($cr = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
        $custList[] = array(
            "CUST_CODE" => safe_trim($cr["CUST_CODE"]),
            "CUST_COMP" => safe_trim($cr["CUST_COMP"])
        );
    }
}

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    if ($end_ymd < $start_ymd) {
        die("End Date tidak boleh lebih kecil dari hari ini / Start Date.");
    }

    $periode = substr($start_ymd, 0, 6);

    $sql = "
        SELECT
            C.CUST_CODE,
            C.CUST_COMP,
            P.ITEM_CODE,
            MAX(P.PART_NAME) AS ITEM_NAME,
            MAX(P.PART_NO) AS PART_NO,
            LTRIM(RTRIM(D.DESC_PROD)) AS ROW_NAME,

            SUM(ISNULL(D.G_TOTAL, 0)) AS G_TOTAL,

            SUM(ISNULL(D.D1, 0)) AS D1,
            SUM(ISNULL(D.D2, 0)) AS D2,
            SUM(ISNULL(D.D3, 0)) AS D3,
            SUM(ISNULL(D.D4, 0)) AS D4,
            SUM(ISNULL(D.D5, 0)) AS D5,
            SUM(ISNULL(D.D6, 0)) AS D6,
            SUM(ISNULL(D.D7, 0)) AS D7,
            SUM(ISNULL(D.D8, 0)) AS D8,
            SUM(ISNULL(D.D9, 0)) AS D9,
            SUM(ISNULL(D.D10, 0)) AS D10,
            SUM(ISNULL(D.D11, 0)) AS D11,
            SUM(ISNULL(D.D12, 0)) AS D12,
            SUM(ISNULL(D.D13, 0)) AS D13,
            SUM(ISNULL(D.D14, 0)) AS D14,
            SUM(ISNULL(D.D15, 0)) AS D15,
            SUM(ISNULL(D.D16, 0)) AS D16,
            SUM(ISNULL(D.D17, 0)) AS D17,
            SUM(ISNULL(D.D18, 0)) AS D18,
            SUM(ISNULL(D.D19, 0)) AS D19,
            SUM(ISNULL(D.D20, 0)) AS D20,
            SUM(ISNULL(D.D21, 0)) AS D21,
            SUM(ISNULL(D.D22, 0)) AS D22,
            SUM(ISNULL(D.D23, 0)) AS D23,
            SUM(ISNULL(D.D24, 0)) AS D24,
            SUM(ISNULL(D.D25, 0)) AS D25,
            SUM(ISNULL(D.D26, 0)) AS D26,
            SUM(ISNULL(D.D27, 0)) AS D27,
            SUM(ISNULL(D.D28, 0)) AS D28,
            SUM(ISNULL(D.D29, 0)) AS D29,
            SUM(ISNULL(D.D30, 0)) AS D30,
            SUM(ISNULL(D.D31, 0)) AS D31
        FROM dbo.RPT_PPIC P
        INNER JOIN dbo.RPT_PPIC_DTL D
            ON P.ID_NO = D.ID_NO
        INNER JOIN dbo.CUST C
            ON P.CUST = C.CUST_ID
        WHERE P.periode = ?
          AND (? = '%' OR C.CUST_CODE = ?)
          AND LTRIM(RTRIM(D.DESC_PROD)) IN (
                'Est Stock Plan',
                'Est Stock Actual'
          )
          AND (
                ? = ''
                OR LTRIM(RTRIM(D.DESC_PROD)) = ?
          )
        GROUP BY
            C.CUST_CODE,
            C.CUST_COMP,
            P.ITEM_CODE,
            LTRIM(RTRIM(D.DESC_PROD))
        ORDER BY
            C.CUST_CODE,
            P.ITEM_CODE,
            LTRIM(RTRIM(D.DESC_PROD))
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $periode,
        $cust_code,
        $cust_code,
        $stock_type,
        $stock_type
    ));

    if ($stmt === false) {
        die("<pre>Query Summary Minus gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rowName = safe_trim($r["ROW_NAME"]);

        $firstMinusDate = "";
        $firstMinusQty = 0;
        $lowestQty = 0;
        $lowestDate = "";

        for ($d = 1; $d <= 31; $d++) {
            $col = "D" . $d;
            $val = isset($r[$col]) ? (float)$r[$col] : 0;

            if ($val < 0) {
                $dateYmd = substr($periode, 0, 4) . substr($periode, 4, 2) . str_pad($d, 2, "0", STR_PAD_LEFT);

                /* HANYA CEK MULAI HARI INI / START DATE BARU SAMPAI END DATE */
                if ($dateYmd < $start_ymd || $dateYmd > $end_ymd) {
                    continue;
                }

                if ($firstMinusDate == "") {
                    $firstMinusDate = $dateYmd;
                    $firstMinusQty = $val;
                }

                if ($lowestDate == "" || $val < $lowestQty) {
                    $lowestDate = $dateYmd;
                    $lowestQty = $val;
                }
            }
        }

        if ($firstMinusDate != "") {
            $rows[] = array(
                "CUST_CODE"   => safe_trim($r["CUST_CODE"]),
                "CUST_COMP"   => safe_trim($r["CUST_COMP"]),
                "ITEM_CODE"   => safe_trim($r["ITEM_CODE"]),
                "ITEM_NAME"   => safe_trim($r["ITEM_NAME"]),
                "PART_NO"     => safe_trim($r["PART_NO"]),
                "ROW_NAME"    => ($rowName == "Est Stock Plan") ? "Stock Plan" : "Stock Actual",
                "FIRST_DATE"  => $firstMinusDate,
                "FIRST_QTY"   => $firstMinusQty,
                "LOWEST_DATE" => $lowestDate,
                "LOWEST_QTY"  => $lowestQty
            );
        }
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Logical Stock Minus Summary</title>

    <style>
        body {
            margin: 0;
            background: #9a9a9a;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .filter-bar {
            width: 1120px;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-size: 12px;
            white-space: nowrap;
        }

        .filter-bar input,
        .filter-bar select {
            height: 26px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-date {
            width: 130px;
        }

        .filter-cust {
            width: 190px;
        }

        .filter-stock {
            width: 130px;
        }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .page {
            width: 1120px;
            min-height: 740px;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 18px;
            box-sizing: border-box;
        }

        .title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .info {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 8px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        table.report th,
        table.report td {
            border: 1px solid #000000;
            padding: 4px;
            vertical-align: top;
        }

        table.report th {
            background: #d9eaf7;
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .minus {
            color: red;
            font-weight: bold;
        }

        .stock-plan {
            background: #fff2cc;
        }

        .stock-actual {
            background: #00b0f0;
            font-weight: bold;
        }

        .no-data {
            padding: 60px;
            text-align: center;
            font-size: 14px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .filter-bar {
                display: none;
            }

            .page {
                width: auto;
                margin: 0;
                border: none;
                padding: 8px;
            }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>">
        <input type="hidden" name="RUN" value="1">

        Start:
        <input type="date"
               name="START_DATE"
               class="filter-date"
               value="<?php echo h($start_input); ?>">

        End:
        <input type="date"
               name="END_DATE"
               class="filter-date"
               value="<?php echo h($end_input); ?>">

        Customer:
        <input type="text"
               name="CUST_CODE"
               class="filter-cust"
               value="<?php echo h($cust_code); ?>"
               list="cust_list"
               placeholder="Kode customer / %">

        <datalist id="cust_list">
            <option value="%">ALL CUSTOMER</option>
            <?php foreach ($custList as $c) { ?>
                <option value="<?php echo h($c["CUST_CODE"]); ?>">
                    <?php echo h($c["CUST_CODE"] . " - " . $c["CUST_COMP"]); ?>
                </option>
            <?php } ?>
        </datalist>

        Jenis:
        <select name="STOCK_TYPE" class="filter-stock">
            <option value="" <?php echo ($stock_type == "") ? "selected" : ""; ?>>ALL</option>
            <option value="Est Stock Plan" <?php echo ($stock_type == "Est Stock Plan") ? "selected" : ""; ?>>Stock Plan</option>
            <option value="Est Stock Actual" <?php echo ($stock_type == "Est Stock Actual") ? "selected" : ""; ?>>Stock Actual</option>
        </select>

        <button type="submit">FILTER</button>
        <button type="button" onclick="document.getElementsByName('CUST_CODE')[0].value='%';document.forms[0].submit();">ALL</button>
        <button type="button" onclick="window.print()">PRINT</button>
    </form>
</div>

<div class="page">
    <div class="title">LOGICAL STOCK MINUS SUMMARY</div>
    <div class="subtitle">
        Period: <?php echo h($start_input); ?> s/d <?php echo h($end_input); ?>
    </div>

    <div class="info">
        <div>P.T. IMC TEKNO INDONESIA - PPIC Department</div>
        <div>Print Date: <?php echo h(fmt_print_datetime()); ?></div>
    </div>

    <?php if (!$is_filter) { ?>
        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Report hanya membaca minus mulai hari ini sampai akhir bulan.
        </div>
    <?php } elseif (count($rows) == 0) { ?>
        <div class="no-data">
            Tidak ada stock minus mulai hari ini sampai end date.
        </div>
    <?php } else { ?>
        <table class="report">
            <thead>
                <tr>
                    <th style="width:90px;">Customer</th>
                    <th style="width:110px;">Item Code</th>
                    <th>Item Name</th>
                    <th style="width:100px;">Jenis Stock</th>
                    <th style="width:95px;">Mulai Minus</th>
                    <th style="width:95px;">Qty Awal Minus</th>
                    <th style="width:95px;">Minus Terendah</th>
                    <th style="width:95px;">Qty Terendah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) { ?>
                    <?php
                        $rowClass = ($r["ROW_NAME"] == "Stock Actual") ? "stock-actual" : "stock-plan";
                    ?>
                    <tr class="<?php echo h($rowClass); ?>">
                        <td>
                            <?php echo h($r["CUST_CODE"]); ?><br>
                            <?php echo h($r["CUST_COMP"]); ?>
                        </td>
                        <td><?php echo h($r["ITEM_CODE"]); ?></td>
                        <td>
                            <?php echo h($r["ITEM_NAME"]); ?>
                            <?php if ($r["PART_NO"] != "") { ?>
                                <br>Part No: <?php echo h($r["PART_NO"]); ?>
                            <?php } ?>
                        </td>
                        <td><?php echo h($r["ROW_NAME"]); ?></td>
                        <td class="minus"><?php echo h(ymd_to_dmy($r["FIRST_DATE"])); ?></td>
                        <td class="num minus"><?php echo h(fmt_num($r["FIRST_QTY"])); ?></td>
                        <td class="minus"><?php echo h(ymd_to_dmy($r["LOWEST_DATE"])); ?></td>
                        <td class="num minus"><?php echo h(fmt_num($r["LOWEST_QTY"])); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>