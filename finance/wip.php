<?php
/**
 * WIP MATERIAL VIRGIN - RANGKUMAN
 * PHP 5.4 + SQLSRV + SQL Server 2008 + Bootstrap 3
 *
 * Hanya material VIRGIN:
 *   ITEMS.ITTY_CODE = '02'
 *   dan ITEM_CODE berakhiran '-0'
 *
 * Rumus:
 *   WIP / SELISIH =
 *       STOK MATERIAL TAG LOKASI PRD PADA start_date
 *       + MATERIAL OUT WHS KE PRODUKSI
 *       - TOTAL ACTUAL PRODUKSI KONVERSI BOM
 *
 *   NILAI SELISIH =
 *       SELISIH WIP x ITEM_COST
 *
 * Konversi BOM material virgin:
 *   ACTUAL PRODUKSI x BOM_DEFAULT.QTY / 1000
 *
 * Periode:
 *   start_date ikut transaksi.
 *   end_date adalah batas eksklusif dan tidak ikut transaksi.
 *
 * Contoh:
 *   start_date = 2026-06-01
 *   end_date   = 2026-07-01
 *
 * Maka transaksi:
 *   01-Jun-2026 s/d 30-Jun-2026.
 *
 * Mode:
 *   - Default              : rangkuman per material virgin.
 *   - detail_material=CODE : detail produksi yang memakai material.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
 * KONEKSI DATABASE
 * ========================================================= */
$connectionFiles = array(
    __DIR__ . "/config/database.php",
    __DIR__ . "/../config/db_plant1.php",
    __DIR__ . "/../config/db_plan1.php",
    __DIR__ . "/../config/global.php"
);

$connectionLoaded = false;

foreach ($connectionFiles as $connectionFile) {
    if (file_exists($connectionFile)) {
        require_once $connectionFile;
        $connectionLoaded = true;
        break;
    }
}

if (!$connectionLoaded) {
    die("File koneksi database tidak ditemukan.");
}

$db = null;

if (isset($conn)) {
    $db = $conn;
} elseif (isset($connection)) {
    $db = $connection;
} elseif (isset($dbconn)) {
    $db = $dbconn;
}

if (!$db) {
    die("Koneksi database tidak ditemukan.");
}

if (!function_exists("sqlsrv_query")) {
    die("Extension SQLSRV belum aktif.");
}

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function wv_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function wv_num($value)
{
    return ($value === null || $value === "")
        ? 0.0
        : (float) $value;
}

function wv_qty($value)
{
    return number_format(
        wv_num($value),
        3,
        ".",
        ","
    );
}

function wv_production_qty($value)
{
    return number_format(
        wv_num($value),
        0,
        ".",
        ","
    );
}

function wv_money($value)
{
    return number_format(
        wv_num($value),
        0,
        ",",
        "."
    );
}

function wv_valid_date($value)
{
    if (
        !is_string($value) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
    ) {
        return false;
    }

    $parts = explode("-", $value);

    return count($parts) == 3 &&
        checkdate(
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[0]
        );
}

function wv_date($value, $format)
{
    if ($value instanceof DateTime) {
        return $value->format($format);
    }

    if ($value === null || $value === "") {
        return "";
    }

    $time = strtotime((string) $value);

    if ($time === false) {
        return (string) $value;
    }

    return date($format, $time);
}

function wv_sql_error()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);

    if (!$errors) {
        return "Unknown SQL Server error.";
    }

    $messages = array();

    foreach ($errors as $error) {
        $messages[] =
            "[" . $error["SQLSTATE"] . "] "
            . $error["code"]
            . " - "
            . $error["message"];
    }

    return implode("\n", $messages);
}

function wv_sql_quote($value)
{
    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function wv_contains($haystack, $needle)
{
    if ($needle === "") {
        return true;
    }

    if (function_exists("mb_stripos")) {
        return mb_stripos(
            (string) $haystack,
            (string) $needle,
            0,
            "UTF-8"
        ) !== false;
    }

    return stripos(
        (string) $haystack,
        (string) $needle
    ) !== false;
}

/* =========================================================
 * PARAMETER
 * ========================================================= */
$defaultStart = date("Y-m-01");
$defaultEnd = date(
    "Y-m-d",
    strtotime(date("Y-m-01") . " +1 month")
);

$startDate = isset($_GET["start_date"])
    ? trim($_GET["start_date"])
    : $defaultStart;

$endDate = isset($_GET["end_date"])
    ? trim($_GET["end_date"])
    : $defaultEnd;

$materialSearch = isset($_GET["material"])
    ? trim($_GET["material"])
    : "";

$detailMaterial = isset($_GET["detail_material"])
    ? trim($_GET["detail_material"])
    : "";

$format = isset($_GET["format"])
    ? strtolower(trim($_GET["format"]))
    : "html";

$autoPrint = isset($_GET["print"]) &&
             $_GET["print"] == "1";

$errors = array();
$queryError = "";

if (!wv_valid_date($startDate)) {
    $errors[] = "Tanggal awal tidak valid.";
}

if (!wv_valid_date($endDate)) {
    $errors[] = "Tanggal batas akhir tidak valid.";
}

if (
    wv_valid_date($startDate) &&
    wv_valid_date($endDate) &&
    strtotime($startDate) >= strtotime($endDate)
) {
    $errors[] =
        "Tanggal batas akhir harus lebih besar dari tanggal awal.";
}

$transactionEndDate = wv_valid_date($endDate)
    ? date("Y-m-d", strtotime($endDate . " -1 day"))
    : $endDate;

$startSql = $startDate . " 00:00:00.000";
$endSql = $transactionEndDate . " 23:59:59.997";

/* =========================================================
 * 1. ACTUAL PRODUKSI PER PART
 *
 * Sumber:
 *   dbo.sp_daily_prod
 *
 * Hasil dijumlahkan per ITEM_CODE barang hasil produksi.
 * ========================================================= */
$productionByPart = array();
$productionNameByPart = array();

if (count($errors) == 0) {
    $productionStmt = sqlsrv_query(
        $db,
        "{CALL dbo.sp_daily_prod(?,?,?,?)}",
        array(
            $startSql,
            $endSql,
            "%",
            "%"
        ),
        array(
            "QueryTimeout" => 300
        )
    );

    if ($productionStmt === false) {
        $queryError = wv_sql_error();
    } else {
        do {
            while (
                $row = sqlsrv_fetch_array(
                    $productionStmt,
                    SQLSRV_FETCH_ASSOC
                )
            ) {
                $partCode = isset($row["ITEM_CODE"])
                    ? trim($row["ITEM_CODE"])
                    : "";

                if ($partCode === "") {
                    continue;
                }

                if (
                    isset($row["PD_QTY"]) &&
                    $row["PD_QTY"] !== null &&
                    $row["PD_QTY"] !== ""
                ) {
                    $actualProduction =
                        wv_num($row["PD_QTY"]);
                } else {
                    $actualProduction =
                        wv_num(isset($row["PD_OK"])
                            ? $row["PD_OK"]
                            : 0)
                        + wv_num(isset($row["PD_HO"])
                            ? $row["PD_HO"]
                            : 0)
                        + wv_num(isset($row["NG"])
                            ? $row["NG"]
                            : 0);
                }

                if (!isset($productionByPart[$partCode])) {
                    $productionByPart[$partCode] = 0;
                }

                $productionByPart[$partCode]
                    += $actualProduction;

                if (!isset($productionNameByPart[$partCode])) {
                    $productionNameByPart[$partCode] =
                        isset($row["ITEM_NAME"])
                            ? trim($row["ITEM_NAME"])
                            : "";
                }
            }
        } while (sqlsrv_next_result($productionStmt));

        sqlsrv_free_stmt($productionStmt);
    }
}

/* =========================================================
 * 2. ACTUAL PRODUKSI KONVERSI BOM PER MATERIAL VIRGIN
 *
 * Hanya:
 *   MAT.ITTY_CODE = '02'
 *   MAT.ITEM_CODE LIKE '%-0'
 *
 * Rumus:
 *   actual produksi x BOM qty / 1000
 * ========================================================= */
$conversionByMaterial = array();
$productionDetailsByMaterial = array();

if (
    count($errors) == 0 &&
    $queryError === "" &&
    count($productionByPart) > 0
) {
    $partCodes = array_keys($productionByPart);

    foreach (array_chunk($partCodes, 300) as $partChunk) {
        $quotedCodes = array();

        foreach ($partChunk as $partCode) {
            $quotedCodes[] = wv_sql_quote($partCode);
        }

        $inClause = implode(",", $quotedCodes);

        $bomSql = "
            SELECT
                PART.ITEM_CODE AS PART_CODE,
                PART.ITEM_NAME AS PART_NAME,

                MAT.ITEM_ID AS MATERIAL_ID,
                MAT.ITEM_CODE AS MATERIAL_CODE,
                MAT.ITEM_NAME AS MATERIAL_NAME,
                MAT.ITEM_UNIT AS MATERIAL_UNIT,
                ISNULL(MAT.ITEM_COST, 0) AS ITEM_COST,

                CAST(BD.QTY AS decimal(38, 8)) AS BOM_QTY
            FROM dbo.BOM_DEFAULT AS BD
            INNER JOIN dbo.ITEMS AS PART
                ON PART.ITEM_ID = BD.PART_ID
            INNER JOIN dbo.ITEMS AS MAT
                ON MAT.ITEM_ID = BD.ITEM_ID
            WHERE
                PART.ITEM_CODE IN (" . $inClause . ")
                AND MAT.ITTY_CODE = '02'
                AND RIGHT(RTRIM(MAT.ITEM_CODE), 2) = '-0'
                AND PART.ITEM_INACTIVE = 0
                AND MAT.ITEM_INACTIVE = 0
            ORDER BY
                MAT.ITEM_CODE,
                PART.ITEM_CODE
        ";

        $bomStmt = sqlsrv_query(
            $db,
            $bomSql,
            array(),
            array(
                "QueryTimeout" => 300
            )
        );

        if ($bomStmt === false) {
            $queryError = wv_sql_error();
            break;
        }

        while (
            $bomRow = sqlsrv_fetch_array(
                $bomStmt,
                SQLSRV_FETCH_ASSOC
            )
        ) {
            $partCode = trim($bomRow["PART_CODE"]);
            $materialCode =
                trim($bomRow["MATERIAL_CODE"]);

            $actualProduction =
                isset($productionByPart[$partCode])
                    ? wv_num($productionByPart[$partCode])
                    : 0;

            $bomQty = wv_num($bomRow["BOM_QTY"]);

            $convertedQty =
                ($actualProduction * $bomQty) / 1000.0;

            if (!isset(
                $conversionByMaterial[$materialCode]
            )) {
                $conversionByMaterial[$materialCode] = array(
                    "MATERIAL_ID"       =>
                        (int) $bomRow["MATERIAL_ID"],
                    "MATERIAL_CODE"     => $materialCode,
                    "MATERIAL_NAME"     =>
                        $bomRow["MATERIAL_NAME"],
                    "MATERIAL_UNIT"     =>
                        $bomRow["MATERIAL_UNIT"],
                    "ITEM_COST"         =>
                        wv_num($bomRow["ITEM_COST"]),
                    "TOTAL_CONVERSION"  => 0,
                    "PART_COUNT"        => 0
                );

                $productionDetailsByMaterial[$materialCode]
                    = array();
            }

            $conversionByMaterial[$materialCode]
                ["TOTAL_CONVERSION"] += $convertedQty;

            $conversionByMaterial[$materialCode]
                ["PART_COUNT"]++;

            $productionDetailsByMaterial[$materialCode][] =
                array(
                    "PART_CODE"         => $partCode,
                    "PART_NAME"         =>
                        $bomRow["PART_NAME"],
                    "ACTUAL_PRODUCTION" =>
                        $actualProduction,
                    "BOM_QTY"           => $bomQty,
                    "CONVERTED_QTY"     => $convertedQty
                );
        }

        sqlsrv_free_stmt($bomStmt);
    }
}

/* =========================================================
 * 3. MATERIAL VIRGIN OUT WHS KE PRODUKSI
 *
 * Kriteria:
 * - Material ITTY 02.
 * - Kode material berakhiran -0.
 * - Mapping WHS TRTY_INOUT = 2.
 * - TRTY_CODE yang sama mempunyai mapping lokasi non-WHS.
 *
 * Baris INV_TRAN yang sama dijumlahkan berdasarkan transaksi.
 * ========================================================= */
$outByMaterial = array();

if (
    count($errors) == 0 &&
    $queryError === ""
) {
    $outSql = "
        SELECT
            Q.MATERIAL_ID,
            Q.MATERIAL_CODE,
            Q.MATERIAL_NAME,
            Q.MATERIAL_UNIT,
            Q.ITEM_COST,

            SUM(
                Q.IT_QTY * Q.TRTY_SIGN
            ) AS MATERIAL_OUT_PRODUCTION
        FROM
        (
            SELECT
                TR.TRAN_ID,
                TR.TRAN_DATE,
                TR.TRTY_CODE,

                MAT.ITEM_ID AS MATERIAL_ID,
                MAT.ITEM_CODE AS MATERIAL_CODE,
                MAT.ITEM_NAME AS MATERIAL_NAME,
                MAT.ITEM_UNIT AS MATERIAL_UNIT,
                ISNULL(MAT.ITEM_COST, 0) AS ITEM_COST,

                WHS_MAP.TRTY_SIGN,

                SUM(ISNULL(IT.IT_QTY, 0)) AS IT_QTY
            FROM dbo.TRANS AS TR
            INNER JOIN dbo.INV_TRAN AS IT
                ON IT.TRAN_ID = TR.TRAN_ID
            INNER JOIN dbo.ITEMS AS MAT
                ON MAT.ITEM_ID = IT.ITEM_ID
            CROSS APPLY
            (
                SELECT TOP 1
                    TL.TRTY_SIGN
                FROM dbo.TRTY_LOC AS TL
                INNER JOIN dbo.LOC AS L
                    ON L.LOC_ID = TL.LOC_ID
                WHERE
                    TL.TRTY_CODE = TR.TRTY_CODE
                    AND L.LOC_CODE = 'WHS'
                    AND TL.TRTY_INOUT = 2
                ORDER BY
                    TL.LOC_ID
            ) AS WHS_MAP
            WHERE
                TR.TRAN_DATE >= ?
                AND TR.TRAN_DATE < ?
                AND MAT.ITTY_CODE = '02'
                AND RIGHT(RTRIM(MAT.ITEM_CODE), 2) = '-0'
                AND MAT.ITEM_INACTIVE = 0
                AND EXISTS
                (
                    SELECT 1
                    FROM dbo.TRTY_LOC AS PROD_TL
                    INNER JOIN dbo.LOC AS PROD_L
                        ON PROD_L.LOC_ID = PROD_TL.LOC_ID
                    WHERE
                        PROD_TL.TRTY_CODE = TR.TRTY_CODE
                        AND PROD_L.LOC_CODE <> 'WHS'
                )
            GROUP BY
                TR.TRAN_ID,
                TR.TRAN_DATE,
                TR.TRTY_CODE,

                MAT.ITEM_ID,
                MAT.ITEM_CODE,
                MAT.ITEM_NAME,
                MAT.ITEM_UNIT,
                MAT.ITEM_COST,

                WHS_MAP.TRTY_SIGN
        ) AS Q
        GROUP BY
            Q.MATERIAL_ID,
            Q.MATERIAL_CODE,
            Q.MATERIAL_NAME,
            Q.MATERIAL_UNIT,
            Q.ITEM_COST
        ORDER BY
            Q.MATERIAL_CODE
    ";

    $outStmt = sqlsrv_query(
        $db,
        $outSql,
        array(
            $startDate,
            $endDate
        ),
        array(
            "QueryTimeout" => 300
        )
    );

    if ($outStmt === false) {
        $queryError = wv_sql_error();
    } else {
        while (
            $outRow = sqlsrv_fetch_array(
                $outStmt,
                SQLSRV_FETCH_ASSOC
            )
        ) {
            $materialCode =
                trim($outRow["MATERIAL_CODE"]);

            $outByMaterial[$materialCode] = array(
                "MATERIAL_ID"       =>
                    (int) $outRow["MATERIAL_ID"],
                "MATERIAL_CODE"     => $materialCode,
                "MATERIAL_NAME"     =>
                    $outRow["MATERIAL_NAME"],
                "MATERIAL_UNIT"     =>
                    $outRow["MATERIAL_UNIT"],
                "ITEM_COST"         =>
                    wv_num($outRow["ITEM_COST"]),
                "MATERIAL_OUT"      =>
                    wv_num(
                        $outRow["MATERIAL_OUT_PRODUCTION"]
                    )
            );
        }

        sqlsrv_free_stmt($outStmt);
    }
}

/* =========================================================
 * 4. STOK MATERIAL VIRGIN DI PRODUKSI PADA TANGGAL AWAL
 *
 * Sumber:
 *   TAGS + SOP + LOC
 *
 * Hanya LOC_CODE = 'PRD'.
 * Tanggal start_date diperlakukan sebagai stok awal produksi.
 * Material crusher tidak diambil.
 * ========================================================= */
$stockProductionByMaterial = array();

if (
    count($errors) == 0 &&
    $queryError === ""
) {
    $stockProductionSql = "
        SELECT
            MAT.ITEM_ID AS MATERIAL_ID,
            MAT.ITEM_CODE AS MATERIAL_CODE,
            MAT.ITEM_NAME AS MATERIAL_NAME,
            MAT.ITEM_UNIT AS MATERIAL_UNIT,
            ISNULL(MAT.ITEM_COST, 0) AS ITEM_COST,

            SUM(ISNULL(T.TAG_QTY, 0)) AS STOCK_PRODUCTION
        FROM dbo.TAGS AS T
        INNER JOIN dbo.SOP AS S
            ON S.SOP_ID = T.SOP_ID
        INNER JOIN dbo.LOC AS L
            ON L.LOC_ID = T.LOC_ID
        INNER JOIN dbo.ITEMS AS MAT
            ON MAT.ITEM_ID = T.ITEM_ID
        WHERE
            S.SOP_SDATE >= ?
            AND S.SOP_SDATE < DATEADD(day, 1, ?)
            AND L.LOC_CODE = 'PRD'
            AND MAT.ITTY_CODE = '02'
            AND RIGHT(RTRIM(MAT.ITEM_CODE), 2) = '-0'
            AND MAT.ITEM_INACTIVE = 0
        GROUP BY
            MAT.ITEM_ID,
            MAT.ITEM_CODE,
            MAT.ITEM_NAME,
            MAT.ITEM_UNIT,
            MAT.ITEM_COST
        ORDER BY
            MAT.ITEM_CODE
    ";

    $stockProductionStmt = sqlsrv_query(
        $db,
        $stockProductionSql,
        array(
            $startDate,
            $startDate
        ),
        array(
            "QueryTimeout" => 300
        )
    );

    if ($stockProductionStmt === false) {
        $queryError = wv_sql_error();
    } else {
        while (
            $stockRow = sqlsrv_fetch_array(
                $stockProductionStmt,
                SQLSRV_FETCH_ASSOC
            )
        ) {
            $materialCode =
                trim($stockRow["MATERIAL_CODE"]);

            $stockProductionByMaterial[$materialCode] = array(
                "MATERIAL_ID"      =>
                    (int) $stockRow["MATERIAL_ID"],
                "MATERIAL_CODE"    => $materialCode,
                "MATERIAL_NAME"    =>
                    $stockRow["MATERIAL_NAME"],
                "MATERIAL_UNIT"    =>
                    $stockRow["MATERIAL_UNIT"],
                "ITEM_COST"        =>
                    wv_num($stockRow["ITEM_COST"]),
                "STOCK_PRODUCTION" =>
                    wv_num($stockRow["STOCK_PRODUCTION"])
            );
        }

        sqlsrv_free_stmt($stockProductionStmt);
    }
}

/* =========================================================
 * 5. GABUNGKAN RANGKUMAN
 * ========================================================= */
$materialKeys = array();

foreach ($outByMaterial as $materialCode => $row) {
    $materialKeys[$materialCode] = true;
}

foreach (
    $conversionByMaterial as $materialCode => $row
) {
    $materialKeys[$materialCode] = true;
}

foreach (
    $stockProductionByMaterial as $materialCode => $row
) {
    $materialKeys[$materialCode] = true;
}

$reportRows = array();

foreach (array_keys($materialKeys) as $materialCode) {
    $out = isset($outByMaterial[$materialCode])
        ? $outByMaterial[$materialCode]
        : null;

    $conversion =
        isset($conversionByMaterial[$materialCode])
            ? $conversionByMaterial[$materialCode]
            : null;

    $stockProduction =
        isset($stockProductionByMaterial[$materialCode])
            ? $stockProductionByMaterial[$materialCode]
            : null;

    if ($out !== null) {
        $source = $out;
    } elseif ($conversion !== null) {
        $source = $conversion;
    } else {
        $source = $stockProduction;
    }

    $stockProductionQty = $stockProduction !== null
        ? wv_num($stockProduction["STOCK_PRODUCTION"])
        : 0;

    $materialOut = $out !== null
        ? wv_num($out["MATERIAL_OUT"])
        : 0;

    $totalAvailable =
        $stockProductionQty + $materialOut;

    $totalConversion = $conversion !== null
        ? wv_num($conversion["TOTAL_CONVERSION"])
        : 0;

    $difference =
        $totalAvailable - $totalConversion;

    $itemCost = isset($source["ITEM_COST"])
        ? wv_num($source["ITEM_COST"])
        : 0;

    $differenceValue = $difference * $itemCost;

    $reportRows[] = array(
        "MATERIAL_CODE"    => $materialCode,
        "MATERIAL_NAME"    =>
            isset($source["MATERIAL_NAME"])
                ? $source["MATERIAL_NAME"]
                : "",
        "MATERIAL_UNIT"    =>
            isset($source["MATERIAL_UNIT"])
                ? $source["MATERIAL_UNIT"]
                : "",
        "STOCK_PRODUCTION" => $stockProductionQty,
        "MATERIAL_OUT"     => $materialOut,
        "TOTAL_AVAILABLE"  => $totalAvailable,
        "TOTAL_CONVERSION" => $totalConversion,
        "DIFFERENCE"       => $difference,
        "ITEM_COST"        => $itemCost,
        "DIFFERENCE_VALUE" => $differenceValue,
        "PART_COUNT"       => $conversion !== null
            ? (int) $conversion["PART_COUNT"]
            : 0
    );
}

/*
 * Filter kode/nama material.
 */
if ($materialSearch !== "") {
    $filteredRows = array();

    foreach ($reportRows as $row) {
        $searchText =
            $row["MATERIAL_CODE"]
            . " "
            . $row["MATERIAL_NAME"];

        if (wv_contains($searchText, $materialSearch)) {
            $filteredRows[] = $row;
        }
    }

    $reportRows = $filteredRows;
}

/*
 * Selisih absolut terbesar ditampilkan dahulu.
 */
usort(
    $reportRows,
    function ($a, $b) {
        $absA = abs(wv_num($a["DIFFERENCE"]));
        $absB = abs(wv_num($b["DIFFERENCE"]));

        if ($absA == $absB) {
            return strnatcasecmp(
                $a["MATERIAL_CODE"],
                $b["MATERIAL_CODE"]
            );
        }

        return ($absA > $absB) ? -1 : 1;
    }
);

/* =========================================================
 * TOTAL RANGKUMAN
 * ========================================================= */
$totalStockProduction = 0;
$totalMaterialOut = 0;
$totalAvailable = 0;
$totalConversion = 0;
$totalDifference = 0;
$totalDifferenceValue = 0;

foreach ($reportRows as $row) {
    $totalStockProduction += wv_num($row["STOCK_PRODUCTION"]);
    $totalMaterialOut += wv_num($row["MATERIAL_OUT"]);
    $totalAvailable += wv_num($row["TOTAL_AVAILABLE"]);
    $totalConversion += wv_num($row["TOTAL_CONVERSION"]);
    $totalDifference += wv_num($row["DIFFERENCE"]);
    $totalDifferenceValue += wv_num($row["DIFFERENCE_VALUE"]);
}

/* =========================================================
 * DETAIL PRODUKSI PER MATERIAL & QUERY SALES PER PART
 * ========================================================= */
$detailRows = array();
$detailMaterialName = "";
$detailTotalConversion = 0;
$detailItemCost = 0;

// AMBIL TOTAL SALES PER PART JIKA SEDANG MEMBUKA TAB DETAIL
$salesByPart = array();

if ($detailMaterial !== "" && count($errors) == 0 && $queryError === "") {
    
    // Query untuk mengambil Total Revenue Penjualan berdasarkan Part pada Periode yang sama
    $salesSql = "
        SELECT i.ITEM_CODE, 
               SUM(dp.QTY * dp.PART_PRICE * CASE WHEN ISNULL(apv.CURR_CODE, 'IDR') IN ('IDR', 'RP') THEN 1 ELSE ISNULL(c.CURR_VRATE, 1) END) AS TOTAL_REVENUE
        FROM DI d
        INNER JOIN DIPA_PAR dp ON d.DI_ID = dp.DI_ID
        INNER JOIN PRICE p ON dp.PRICE_ID = p.PRICE_ID
        INNER JOIN ITEMS i ON p.PART_ID = i.ITEM_ID
        LEFT JOIN ACTIVE_PRICE_VIEW apv ON p.PRICE_ID = apv.PRICE_ID
        LEFT JOIN CURR_RAT c ON apv.CURR_CODE = c.CURR_CODE AND d.DI_DATE BETWEEN c.CURR_SDATE AND ISNULL(c.CURR_EDATE, d.DI_DATE)
        WHERE d.DI_DATE >= ? AND d.DI_DATE <= ?
        GROUP BY i.ITEM_CODE
    ";
    
    $salesStmt = sqlsrv_query($db, $salesSql, array($startSql, $endSql));
    if ($salesStmt !== false) {
        while ($sRow = sqlsrv_fetch_array($salesStmt, SQLSRV_FETCH_ASSOC)) {
            $salesByPart[trim($sRow['ITEM_CODE'])] = (float)$sRow['TOTAL_REVENUE'];
        }
        sqlsrv_free_stmt($salesStmt);
    }
    
    // Set Detail Data 
    if (isset($productionDetailsByMaterial[$detailMaterial])) {
        $detailRows = $productionDetailsByMaterial[$detailMaterial];
        foreach ($detailRows as $detailRow) {
            $detailTotalConversion += wv_num($detailRow["CONVERTED_QTY"]);
        }
    }

    if (isset($conversionByMaterial[$detailMaterial])) {
        $detailMaterialName = $conversionByMaterial[$detailMaterial]["MATERIAL_NAME"];
        $detailItemCost = wv_num($conversionByMaterial[$detailMaterial]["ITEM_COST"]);
    } elseif (isset($outByMaterial[$detailMaterial])) {
        $detailMaterialName = $outByMaterial[$detailMaterial]["MATERIAL_NAME"];
        $detailItemCost = wv_num($outByMaterial[$detailMaterial]["ITEM_COST"]);
    } elseif (isset($stockProductionByMaterial[$detailMaterial])) {
        $detailMaterialName = $stockProductionByMaterial[$detailMaterial]["MATERIAL_NAME"];
        $detailItemCost = wv_num($stockProductionByMaterial[$detailMaterial]["ITEM_COST"]);
    }
}

/* =========================================================
 * EXPORT EXCEL
 * ========================================================= */
if (
    $format === "excel" &&
    count($errors) == 0 &&
    $queryError === "" &&
    $detailMaterial === ""
) {
    $filename =
        "WIP_Virgin_"
        . $startDate
        . "_sd_"
        . $transactionEndDate
        . ".xls";

    header(
        "Content-Type: application/vnd.ms-excel; charset=UTF-8"
    );

    header(
        "Content-Disposition: attachment; filename=\""
        . $filename
        . "\""
    );

    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #555555;
            padding: 4px;
        }

        th {
            background: #d9eaf7;
            text-align: center;
        }

        .num {
            text-align: right;
        }

        .title {
            border: 0;
            font-size: 13px;
            font-weight: bold;
        }

        .total {
            background: #fff2cc;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="13" class="title">
            RANGKUMAN WIP MATERIAL VIRGIN
        </td>
    </tr>

    <tr>
        <td colspan="13" class="title">
            Periode
            <?php echo wv_h(wv_date(
                $startDate,
                "d-m-Y"
            )); ?>
            s/d
            <?php echo wv_h(wv_date(
                $transactionEndDate,
                "d-m-Y"
            )); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13"></td>
    </tr>

    <tr>
        <th>NO</th>
        <th>KODE MATERIAL</th>
        <th>NAMA MATERIAL</th>
        <th>SATUAN</th>
        <th>
            STOK MATERIAL LOKASI PRD
            <?php echo wv_h(wv_date($startDate, "d-m-Y")); ?>
        </th>
        <th>MATERIAL OUT PRODUKSI</th>
        <th>TOTAL TERSEDIA DI PRODUKSI</th>
        <th>ACTUAL PRODUKSI KONVERSI</th>
        <th>WIP / SELISIH</th>
        <th>ITEM COST</th>
        <th>NILAI SELISIH</th>
        <th>JUMLAH PART</th>
        <th>KETERANGAN</th>
    </tr>

    <?php foreach ($reportRows as $index => $row) { ?>
        <tr>
            <td><?php echo $index + 1; ?></td>
            <td><?php echo wv_h($row["MATERIAL_CODE"]); ?></td>
            <td><?php echo wv_h($row["MATERIAL_NAME"]); ?></td>
            <td><?php echo wv_h($row["MATERIAL_UNIT"]); ?></td>

            <td class="num">
                <?php echo wv_h(wv_production_qty(
                    $row["STOCK_PRODUCTION"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_production_qty(
                    $row["MATERIAL_OUT"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_production_qty(
                    $row["TOTAL_AVAILABLE"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_qty(
                    $row["TOTAL_CONVERSION"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_qty(
                    $row["DIFFERENCE"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_money(
                    $row["ITEM_COST"]
                )); ?>
            </td>

            <td class="num">
                <?php echo wv_h(wv_money(
                    $row["DIFFERENCE_VALUE"]
                )); ?>
            </td>

            <td class="num">
                <?php echo (int) $row["PART_COUNT"]; ?>
            </td>

            <td>
                <?php echo $row["DIFFERENCE"] < -0.000001
                    ? "KONVERSI LEBIH BESAR"
                    : "SISA / WIP"; ?>
            </td>
        </tr>
    <?php } ?>

    <tr class="total">
        <td colspan="4">GRAND TOTAL</td>

        <td class="num">
            <?php echo wv_h(wv_production_qty(
                $totalStockProduction
            )); ?>
        </td>

        <td class="num">
            <?php echo wv_h(wv_production_qty(
                $totalMaterialOut
            )); ?>
        </td>

        <td class="num">
            <?php echo wv_h(wv_production_qty(
                $totalAvailable
            )); ?>
        </td>

        <td class="num">
            <?php echo wv_h(wv_qty(
                $totalConversion
            )); ?>
        </td>

        <td class="num">
            <?php echo wv_h(wv_qty(
                $totalDifference
            )); ?>
        </td>

        <td></td>

        <td class="num">
            <?php echo wv_h(wv_money(
                $totalDifferenceValue
            )); ?>
        </td>

        <td colspan="2"></td>
    </tr>
</table>

</body>
</html>
<?php
    exit();
}

/* =========================================================
 * URL
 * ========================================================= */
$baseParams = array(
    "start_date" => $startDate,
    "end_date"   => $endDate,
    "material"   => $materialSearch
);

$excelUrl = "wip.php?"
    . http_build_query(
        array_merge(
            $baseParams,
            array("format" => "excel")
        )
    );

$backSummaryUrl = "wip.php?"
    . http_build_query($baseParams);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>WIP Material Virgin</title>

    <link
        rel="stylesheet"
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    >

    <style>
        body {
            background: #eef1f5;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        .page-wrap {
            padding: 15px;
        }

        .filter-panel .panel-heading {
            font-weight: bold;
        }

        .filter-panel .form-control,
        .filter-panel .btn {
            font-size: 11px;
            height: 31px;
        }

        .info-box {
            background: #ffffff;
            border: 1px solid #cbd4dd;
            margin-bottom: 12px;
            padding: 10px;
        }

        .formula-box {
            background: #eef7ff;
            border-left: 4px solid #337ab7;
            font-family: Consolas, monospace;
            line-height: 1.7;
            margin-bottom: 12px;
            padding: 10px;
        }

        .table-box {
            background: #ffffff;
            border: 1px solid #c5cdd5;
            overflow-x: auto;
        }

        .report-table {
            border-collapse: collapse;
            margin: 0;
            min-width: 1350px;
            width: 100%;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #929ca5 !important;
            font-size: 10px;
            padding: 5px !important;
            vertical-align: top !important;
        }

        .report-table th {
            background: #345b8c !important;
            color: #ffffff;
            text-align: center;
            vertical-align: middle !important;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #f7f9fb;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .positive {
            color: #a15e00;
            font-weight: bold;
        }

        .negative {
            color: #c00000;
            font-weight: bold;
        }

        .matching {
            color: #15843c;
            font-weight: bold;
        }

        .total-row td {
            background: #fff2cc !important;
            border-top: 2px solid #7b6817 !important;
            font-weight: bold;
        }

        .detail-title {
            background: #345b8c;
            color: #ffffff;
            font-weight: bold;
            margin: 0;
            padding: 9px;
        }

        @media print {
            @page {
                size: A3 landscape;
                margin: 8mm;
            }

            body {
                background: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .page-wrap {
                padding: 0;
            }

            .report-table {
                min-width: 0;
            }
        }
    </style>
</head>

<body>

<div class="page-wrap">

    <?php if ($detailMaterial === "") { ?>

        <div class="panel panel-primary filter-panel no-print">
            <div class="panel-heading">
                FILTER WIP MATERIAL VIRGIN
            </div>

            <div class="panel-body">
                <form method="get" class="form-inline">

                    <div class="form-group">
                        <label>Tanggal Awal</label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="<?php echo wv_h($startDate); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Tanggal Batas Akhir</label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="<?php echo wv_h($endDate); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Material</label>

                        <input
                            type="text"
                            name="material"
                            class="form-control"
                            value="<?php echo wv_h(
                                $materialSearch
                            ); ?>"
                            placeholder="Kode / nama virgin"
                        >
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa fa-search"></i>
                        Tampilkan
                    </button>

                    <button
                        type="button"
                        class="btn btn-default"
                        onclick="window.print();"
                    >
                        <i class="fa fa-print"></i>
                        Print
                    </button>

                    <a
                        href="<?php echo wv_h($excelUrl); ?>"
                        class="btn btn-success"
                    >
                        <i class="fa fa-file-excel-o"></i>
                        Excel
                    </a>

                    <a
                        href="laporan.php"
                        class="btn btn-default"
                    >
                        <i class="fa fa-arrow-left"></i>
                        Kembali
                    </a>
                </form>
            </div>
        </div>

    <?php } else { ?>

        <div class="no-print" style="margin-bottom:10px;">
            <a
                href="<?php echo wv_h($backSummaryUrl); ?>"
                class="btn btn-default"
            >
                <i class="fa fa-arrow-left"></i>
                Kembali ke Rangkuman
            </a>

            <button
                type="button"
                class="btn btn-default"
                onclick="window.print();"
            >
                <i class="fa fa-print"></i>
                Print
            </button>
        </div>

    <?php } ?>

    <?php if (count($errors) > 0) { ?>
        <div class="alert alert-danger">
            <?php echo wv_h(implode("\n", $errors)); ?>
        </div>
    <?php } ?>

    <?php if ($queryError !== "") { ?>
        <div class="alert alert-danger">
            <strong>Query gagal:</strong>

            <pre><?php echo wv_h($queryError); ?></pre>
        </div>
    <?php } ?>

    <?php if (
        count($errors) == 0 &&
        $queryError === ""
    ) { ?>

        <?php if ($detailMaterial === "") { ?>

            <div class="info-box">
                <h4 style="margin-top:0;">
                    RANGKUMAN WIP MATERIAL VIRGIN
                </h4>

                <div>
                    Periode transaksi:
                    <strong>
                        <?php echo wv_h(wv_date(
                            $startDate,
                            "d-m-Y"
                        )); ?>
                        s/d
                        <?php echo wv_h(wv_date(
                            $transactionEndDate,
                            "d-m-Y"
                        )); ?>
                    </strong>
                </div>

                <div>
                    Material:
                    <strong>
                        hanya virgin ITTY 02 dengan kode berakhiran -0
                    </strong>
                </div>

                <div>
                    Material crusher:
                    <strong>diabaikan dan tidak masuk perhitungan.</strong>
                </div>

                <div>
                    Stok material produksi:
                    <strong>
                        posisi
                        <?php echo wv_h(wv_date(
                            $startDate,
                            "d-m-Y"
                        )); ?>
                        dari TAGS/SOP lokasi PRD.
                    </strong>
                </div>

                <div>
                    Jumlah material:
                    <strong><?php echo count($reportRows); ?></strong>
                </div>
            </div>

            <div class="formula-box">
                TOTAL TERSEDIA DI PRODUKSI =
                STOK MATERIAL TAG LOKASI PRD
                +
                MATERIAL OUT WHS KE PRODUKSI

                <br>

                WIP / SELISIH =
                TOTAL TERSEDIA DI PRODUKSI
                -
                TOTAL ACTUAL PRODUKSI KONVERSI BOM

                <br>

                ACTUAL PRODUKSI KONVERSI =
                ACTUAL PRODUKSI
                ×
                BOM QTY
                ÷
                1000

                <br>

                NILAI SELISIH =
                SELISIH WIP
                ×
                ITEM COST
            </div>

            <div class="table-box">
                <table class="table report-table">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>KODE MATERIAL VIRGIN</th>
                            <th>NAMA MATERIAL</th>
                            <th>SATUAN</th>
                            <th>
                                STOK MATERIAL LOKASI PRD
                                <br>
                                <?php echo wv_h(wv_date(
                                    $startDate,
                                    "d-m-Y"
                                )); ?>
                            </th>
                            <th>MATERIAL OUT KE PRODUKSI</th>
                            <th>TOTAL TERSEDIA DI PRODUKSI</th>
                            <th>ACTUAL PRODUKSI KONVERSI</th>
                            <th>WIP / SELISIH</th>
                            <th>ITEM COST</th>
                            <th>NILAI SELISIH</th>
                            <th>JUMLAH PART PRODUKSI</th>
                            <th>KETERANGAN</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $reportRows as $index => $row
                        ) { ?>

                            <?php
                            $differenceClass = "matching";
                            $description = "SESUAI";

                            if (
                                $row["DIFFERENCE"] > 0.000001
                            ) {
                                $differenceClass = "positive";
                                $description = "SISA / WIP";
                            } elseif (
                                $row["DIFFERENCE"] < -0.000001
                            ) {
                                $differenceClass = "negative";
                                $description =
                                    "AKTUAL PRODUKSI > STOK + MATERIAL OUT";
                            }

                            $detailUrl = "wip.php?"
                                . http_build_query(
                                    array(
                                        "start_date" =>
                                            $startDate,
                                        "end_date" =>
                                            $endDate,
                                        "material" =>
                                            $materialSearch,
                                        "detail_material" =>
                                            $row["MATERIAL_CODE"]
                                    )
                                );
                            ?>

                            <tr>
                                <td class="center">
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>
                                    <a
                                        href="<?php echo wv_h(
                                            $detailUrl
                                        ); ?>"
                                        target="_blank"
                                        title="Lihat produksi yang memakai material ini"
                                    >
                                        <i class="fa fa-industry"></i>

                                        <?php echo wv_h(
                                            $row["MATERIAL_CODE"]
                                        ); ?>
                                    </a>
                                </td>

                                <td>
                                    <?php echo wv_h(
                                        $row["MATERIAL_NAME"]
                                    ); ?>
                                </td>

                                <td class="center">
                                    <?php echo wv_h(
                                        $row["MATERIAL_UNIT"]
                                    ); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(wv_production_qty(
                                        $row["STOCK_PRODUCTION"]
                                    )); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(wv_production_qty(
                                        $row["MATERIAL_OUT"]
                                    )); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(wv_production_qty(
                                        $row["TOTAL_AVAILABLE"]
                                    )); ?>
                                </td>

                                <td class="number">
                                    <a
                                        href="<?php echo wv_h(
                                            $detailUrl
                                        ); ?>"
                                        target="_blank"
                                        title="Lihat rincian actual produksi × BOM"
                                    >
                                        <?php echo wv_h(wv_qty(
                                            $row[
                                                "TOTAL_CONVERSION"
                                            ]
                                        )); ?>
                                    </a>
                                </td>

                                <td class="number <?php echo
                                    wv_h($differenceClass); ?>">
                                    <?php echo wv_h(wv_qty(
                                        $row["DIFFERENCE"]
                                    )); ?>
                                </td>

                                <td class="number">
                                    Rp
                                    <?php echo wv_h(wv_money(
                                        $row["ITEM_COST"]
                                    )); ?>
                                </td>

                                <td class="number <?php echo
                                    wv_h($differenceClass); ?>">
                                    Rp
                                    <?php echo wv_h(wv_money(
                                        $row["DIFFERENCE_VALUE"]
                                    )); ?>
                                </td>

                                <td class="number">
                                    <a
                                        href="<?php echo wv_h(
                                            $detailUrl
                                        ); ?>"
                                        target="_blank"
                                    >
                                        <?php echo
                                            (int) $row["PART_COUNT"]; ?>
                                        part
                                    </a>
                                </td>

                                <td class="<?php echo
                                    wv_h($differenceClass); ?>">
                                    <?php echo wv_h($description); ?>
                                </td>
                            </tr>

                        <?php } ?>

                        <?php if (count($reportRows) == 0) { ?>
                            <tr>
                                <td colspan="13" class="center">
                                    Data material virgin tidak ditemukan.
                                </td>
                            </tr>
                        <?php } ?>

                        <tr class="total-row">
                            <td colspan="4">
                                GRAND TOTAL
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_production_qty(
                                    $totalStockProduction
                                )); ?>
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_production_qty(
                                    $totalMaterialOut
                                )); ?>
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_production_qty(
                                    $totalAvailable
                                )); ?>
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_qty(
                                    $totalConversion
                                )); ?>
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_qty(
                                    $totalDifference
                                )); ?>
                            </td>

                            <td></td>

                            <td class="number">
                                Rp
                                <?php echo wv_h(wv_money(
                                    $totalDifferenceValue
                                )); ?>
                            </td>

                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php } else { ?>

            <div class="info-box">
                <h4 style="margin-top:0;">
                    PRODUKSI YANG MEMAKAI MATERIAL VIRGIN
                </h4>

                <div>
                    Material:
                    <strong>
                        <?php echo wv_h($detailMaterial); ?>
                        -
                        <?php echo wv_h($detailMaterialName); ?>
                    </strong>
                </div>

                <div>
                    Periode:
                    <strong>
                        <?php echo wv_h(wv_date(
                            $startDate,
                            "d-m-Y"
                        )); ?>
                        s/d
                        <?php echo wv_h(wv_date(
                            $transactionEndDate,
                            "d-m-Y"
                        )); ?>
                    </strong>
                </div>
            </div>

            <div class="formula-box">
                NILAI KONSUMSI MATERIAL =
                (ACTUAL PRODUKSI × BOM QTY ÷ 1000) × ITEM COST

                <br>

                % KONSUMSI vs SALES =
                (NILAI KONSUMSI MATERIAL ÷ NILAI SALES PART) × 100%
            </div>

            <div class="table-box">
                <h4 class="detail-title">
                    DETAIL KONSUMSI MATERIAL vs SALES
                </h4>

                <table class="table report-table">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>KODE PART PRODUKSI</th>
                            <th>NAMA PART PRODUKSI</th>
                            <th>ACTUAL PRODUKSI (Pcs)</th>
                            <th>BOM QTY</th>
                            <th>MATERIAL KONVERSI (Satuan)</th>
                            <!-- MENAMBAHKAN 3 KOLOM BARU -->
                            <th title="Material Konversi x Item Cost">NILAI KONSUMSI MATERIAL (Rp)</th>
                            <th title="Total Sales Part ini di periode yang sama">NILAI SALES PART (Rp)</th>
                            <th title="(Nilai Konsumsi / Nilai Sales) * 100">% KONSUMSI vs SALES</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php 
                        $totalNilaiKonsumsi = 0;
                        $totalNilaiSales = 0;

                        foreach (
                            $detailRows as $index => $detail
                        ) { 
                            $qtyConv = wv_num($detail["CONVERTED_QTY"]);
                            $nilaiKonsumsi = $qtyConv * $detailItemCost;
                            
                            $partCode = trim($detail["PART_CODE"]);
                            $nilaiSales = isset($salesByPart[$partCode]) ? $salesByPart[$partCode] : 0;
                            
                            $persentase = ($nilaiSales > 0) ? ($nilaiKonsumsi / $nilaiSales) * 100 : 0;
                            
                            $totalNilaiKonsumsi += $nilaiKonsumsi;
                            $totalNilaiSales += $nilaiSales;
                        ?>
                            <tr>
                                <td class="center">
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>
                                    <?php echo wv_h(
                                        $detail["PART_CODE"]
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo wv_h(
                                        $detail["PART_NAME"]
                                    ); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(
                                        wv_production_qty(
                                            $detail["ACTUAL_PRODUCTION"]
                                        )
                                    ); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(
                                        number_format(
                                            wv_num($detail["BOM_QTY"]),
                                            6,
                                            ".",
                                            ","
                                        )
                                    ); ?>
                                </td>

                                <td class="number">
                                    <?php echo wv_h(wv_qty($qtyConv)); ?>
                                </td>

                                <!-- KOLOM NILAI KONSUMSI -->
                                <td class="number">
                                    <?php echo wv_h(wv_money($nilaiKonsumsi)); ?>
                                </td>

                                <!-- KOLOM NILAI SALES -->
                                <td class="number" style="color:#059669; font-weight:bold;">
                                    <?php echo wv_h(wv_money($nilaiSales)); ?>
                                </td>

                                <!-- KOLOM PERSENTASE (KONSUMSI / SALES) -->
                                <td class="number" style="font-weight:bold; color:<?php echo ($persentase > 0) ? '#15843c' : '#c00000'; ?>;">
                                    <?php echo wv_h(number_format($persentase, 2, ',', '.')) . ' %'; ?>
                                </td>
                            </tr>
                        <?php } ?>

                        <?php if (count($detailRows) == 0) { ?>
                            <tr>
                                <td colspan="9" class="center">
                                    Tidak ada actual produksi yang
                                    menggunakan material ini.
                                </td>
                            </tr>
                        <?php } ?>

                        <tr class="total-row">
                            <td colspan="5">
                                TOTAL ACTUAL PRODUKSI KONVERSI
                            </td>

                            <td class="number">
                                <?php echo wv_h(wv_qty(
                                    $detailTotalConversion
                                )); ?>
                            </td>

                            <!-- TOTAL NILAI KONSUMSI -->
                            <td class="number">
                                <?php echo wv_h(wv_money($totalNilaiKonsumsi)); ?>
                            </td>

                            <!-- TOTAL NILAI SALES (Hanya Sales Part yg terkait) -->
                            <td class="number" style="color:#059669;">
                                <?php echo wv_h(wv_money($totalNilaiSales)); ?>
                            </td>

                            <!-- TOTAL PERSENTASE -->
                            <td class="number">
                                <?php 
                                    $totPersen = ($totalNilaiSales > 0) ? ($totalNilaiKonsumsi / $totalNilaiSales) * 100 : 0;
                                    echo wv_h(number_format($totPersen, 2, ',', '.')) . ' %'; 
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php } ?>

    <?php } ?>
</div>

<?php if (
    $autoPrint &&
    count($errors) == 0 &&
    $queryError === ""
) { ?>
<script>
window.onload = function () {
    window.print();
};
</script>
<?php } ?>

</body>
</html>