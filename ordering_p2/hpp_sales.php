<?php
// ============================================
// PHP 5.4 + SQL SERVER 2008: STOK AWAL, PEMBELIAN, STOK AKHIR, SALES — IDR + USD
// ============================================
set_time_limit(120);
// Kompatibilitas target: PHP 5.4 dan Microsoft SQL Server 2008.
session_start();

require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;background:#FFFFFF;font-family:monospace;">Silakan login terlebih dahulu.</div>');
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$dbName = "msData";

$servers_config = array(
    'p1' => array('ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'),
    'p2' => array('ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2')
);

// ============================================
// BLOK API AJAX: STOK AWAL, PEMBELIAN, STOK AKHIR, SALES
// ============================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];
    $selected_plant = isset($_GET['plant']) ? strtolower($_GET['plant']) : 'all';
    if (!in_array($selected_plant, array('p1', 'p2', 'all'), true)) $selected_plant = 'all';

    $conn_opts_base = array(
        "Database" => $dbName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8",
        "LoginTimeout" => 3
    );
    $servers_to_try = ($selected_plant === 'all') ? array('p1', 'p2') : array($selected_plant);
    $server_available = array('p1' => false, 'p2' => false);
    $conn_errors = array();

    // Nilai quantity ITTY 02 diasumsikan gram dan dikonversi ke kg sebelum dikali harga.
    $stockItty02Divisor = 1000.0;
    $bomItty02Divisor = 1000.0;

    // ----------------------------------------
    // ACTION 1: FETCH TAHUNAN
    // Stok awal = 1 Januari tahun berjalan
    // Stok akhir = 1 Januari tahun berikutnya
    // ----------------------------------------
    if ($action === 'fetch_yearly') {
        $startYear = isset($_GET['startYear']) ? (int)$_GET['startYear'] : (int)date('Y');
        $endYear = isset($_GET['endYear']) ? (int)$_GET['endYear'] : (int)date('Y');
        if ($startYear > $endYear) {
            $temp = $startYear;
            $startYear = $endYear;
            $endYear = $temp;
        }

        $startDate = $startYear . '-01-01';
        $endDate = $endYear . '-12-31 23:59:59';

        $raw_yearly = array();
        for ($y = $startYear; $y <= $endYear; $y++) {
            $raw_yearly[$y] = array(
                'year' => $y,
                'stock_awal_idr' => 0,
                'stock_awal_usd' => 0,
                'purchase_idr' => 0,
                'purchase_usd' => 0,
                'stock_akhir_idr' => 0,
                'stock_akhir_usd' => 0,
                'sales_idr' => 0,
                'sales_usd' => 0,
                'stock_error_lines' => 0
            );
        }

        $sql = <<<SQL
        ;WITH YearList AS (
            SELECT CAST({$startYear} AS INT) AS PERIOD_KEY
            UNION ALL
            SELECT PERIOD_KEY + 1
            FROM YearList
            WHERE PERIOD_KEY < {$endYear}
        ),
        SnapshotDates AS (
            SELECT PERIOD_KEY, CAST('STOCK_AWAL' AS VARCHAR(20)) AS STOCK_TYPE,
                   DATEADD(YEAR, PERIOD_KEY - 1900, 0) AS STOCK_DATE
            FROM YearList
            UNION ALL
            SELECT PERIOD_KEY, CAST('STOCK_AKHIR' AS VARCHAR(20)),
                   DATEADD(YEAR, PERIOD_KEY + 1 - 1900, 0)
            FROM YearList
        ),
        StockBase AS (
            SELECT
                SD.PERIOD_KEY,
                SD.STOCK_TYPE,
                SD.STOCK_DATE,
                I.ITEM_ID AS STOCK_ITEM_ID,
                LTRIM(RTRIM(I.ITTY_CODE)) AS STOCK_ITTY_CODE,
                CAST(SUM(ISNULL(T.TAG_QTY, 0)) AS DECIMAL(38,8)) AS STOCK_QTY
            FROM SnapshotDates SD
            INNER JOIN dbo.SOP S
                ON S.SOP_SDATE >= SD.STOCK_DATE
               AND S.SOP_SDATE < DATEADD(DAY, 1, SD.STOCK_DATE)
            INNER JOIN dbo.TAGS T ON S.SOP_ID = T.SOP_ID
            INNER JOIN dbo.ITEMS I ON T.ITEM_ID = I.ITEM_ID
            WHERE LTRIM(RTRIM(I.ITTY_CODE)) IN ('01', '02', '03')
            GROUP BY SD.PERIOD_KEY, SD.STOCK_TYPE, SD.STOCK_DATE,
                     I.ITEM_ID, LTRIM(RTRIM(I.ITTY_CODE))
        ),
        BOM_TREE AS (
            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID, SB.STOCK_QTY,
                BD.ITEM_ID AS COMPONENT_ID,
                LTRIM(RTRIM(C.ITTY_CODE)) AS COMPONENT_ITTY_CODE,
                CAST(BD.QTY AS DECIMAL(38,12)) AS TOTAL_BOM_QTY,
                CAST(1 AS INT) AS BOM_LEVEL,
                CAST('/' + CAST(SB.STOCK_ITEM_ID AS VARCHAR(20)) + '/' +
                     CAST(BD.ITEM_ID AS VARCHAR(20)) + '/' AS VARCHAR(MAX)) AS ITEM_PATH
            FROM StockBase SB
            INNER JOIN dbo.BOM_DEFAULT BD ON SB.STOCK_ITEM_ID = BD.PART_ID
            INNER JOIN dbo.ITEMS C ON BD.ITEM_ID = C.ITEM_ID
            WHERE SB.STOCK_ITTY_CODE = '01'

            UNION ALL

            SELECT
                BT.PERIOD_KEY, BT.STOCK_TYPE, BT.STOCK_DATE,
                BT.STOCK_ITEM_ID, BT.STOCK_QTY,
                BD.ITEM_ID,
                LTRIM(RTRIM(C.ITTY_CODE)),
                CAST(BT.TOTAL_BOM_QTY * BD.QTY AS DECIMAL(38,12)),
                BT.BOM_LEVEL + 1,
                CAST(BT.ITEM_PATH + CAST(BD.ITEM_ID AS VARCHAR(20)) + '/' AS VARCHAR(MAX))
            FROM BOM_TREE BT
            INNER JOIN dbo.BOM_DEFAULT BD ON BT.COMPONENT_ID = BD.PART_ID
            INNER JOIN dbo.ITEMS C ON BD.ITEM_ID = C.ITEM_ID
            WHERE BT.COMPONENT_ITTY_CODE = '01'
              AND BT.BOM_LEVEL < 20
              AND CHARINDEX('/' + CAST(BD.ITEM_ID AS VARCHAR(20)) + '/', BT.ITEM_PATH) = 0
        ),
        FinalMaterial AS (
            SELECT
                PERIOD_KEY, STOCK_TYPE, STOCK_DATE, STOCK_ITEM_ID, STOCK_QTY,
                COMPONENT_ID AS MATERIAL_ID,
                COMPONENT_ITTY_CODE AS MATERIAL_ITTY_CODE,
                CAST(SUM(TOTAL_BOM_QTY) AS DECIMAL(38,12)) AS BOM_QTY_PER_UNIT
            FROM BOM_TREE
            WHERE COMPONENT_ITTY_CODE IN ('02', '03')
            GROUP BY PERIOD_KEY, STOCK_TYPE, STOCK_DATE, STOCK_ITEM_ID, STOCK_QTY,
                     COMPONENT_ID, COMPONENT_ITTY_CODE
        ),
        ConvertedQty AS (
            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID,
                SB.STOCK_ITEM_ID AS MATERIAL_ID,
                SB.STOCK_ITTY_CODE AS MATERIAL_ITTY_CODE,
                CAST(
                    CASE
                        WHEN SB.STOCK_ITTY_CODE = '02' THEN SB.STOCK_QTY / NULLIF({$stockItty02Divisor}, 0)
                        ELSE SB.STOCK_QTY
                    END AS DECIMAL(38,12)
                ) AS MATERIAL_QTY_PRICE,
                CAST('DIRECT_MATERIAL' AS VARCHAR(40)) AS CONVERSION_STATUS
            FROM StockBase SB
            WHERE SB.STOCK_ITTY_CODE IN ('02', '03')

            UNION ALL

            SELECT
                FM.PERIOD_KEY, FM.STOCK_TYPE, FM.STOCK_DATE,
                FM.STOCK_ITEM_ID,
                FM.MATERIAL_ID,
                FM.MATERIAL_ITTY_CODE,
                CAST(
                    CASE
                        WHEN FM.MATERIAL_ITTY_CODE = '02'
                            THEN (FM.STOCK_QTY * FM.BOM_QTY_PER_UNIT) / NULLIF({$bomItty02Divisor}, 0)
                        ELSE FM.STOCK_QTY * FM.BOM_QTY_PER_UNIT
                    END AS DECIMAL(38,12)
                ),
                CAST('BOM_TO_MATERIAL' AS VARCHAR(40))
            FROM FinalMaterial FM

            UNION ALL

            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID,
                NULL, NULL, NULL,
                CAST('TANPA_BOM_MATERIAL' AS VARCHAR(40))
            FROM StockBase SB
            WHERE SB.STOCK_ITTY_CODE = '01'
              AND NOT EXISTS (
                  SELECT 1 FROM FinalMaterial FM
                  WHERE FM.PERIOD_KEY = SB.PERIOD_KEY
                    AND FM.STOCK_TYPE = SB.STOCK_TYPE
                    AND FM.STOCK_ITEM_ID = SB.STOCK_ITEM_ID
              )
        ),
        StockValuation AS (
            SELECT
                CQ.PERIOD_KEY,
                CQ.STOCK_TYPE,
                CAST(
                    CQ.MATERIAL_QTY_PRICE *
                    CASE
                        WHEN LP.POD_PRICE IS NULL THEN NULL
                        WHEN LP.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(LP.PO_CUR))) IN ('IDR','RP','RP.')
                            THEN LP.POD_PRICE
                        WHEN ITEM_RATE.CURR_VRATE IS NOT NULL
                            THEN LP.POD_PRICE * ITEM_RATE.CURR_VRATE
                        ELSE NULL
                    END AS DECIMAL(38,2)
                ) AS AMOUNT_IDR,
                CAST(
                    CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) <= 0 THEN NULL
                    ELSE
                        (CQ.MATERIAL_QTY_PRICE *
                            CASE
                                WHEN LP.POD_PRICE IS NULL THEN NULL
                                WHEN LP.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(LP.PO_CUR))) IN ('IDR','RP','RP.')
                                    THEN LP.POD_PRICE
                                WHEN ITEM_RATE.CURR_VRATE IS NOT NULL
                                    THEN LP.POD_PRICE * ITEM_RATE.CURR_VRATE
                                ELSE NULL
                            END
                        ) / USD_RATE.CURR_VRATE
                    END AS DECIMAL(38,2)
                ) AS AMOUNT_USD,
                CASE
                    WHEN CQ.CONVERSION_STATUS = 'TANPA_BOM_MATERIAL' THEN 1
                    WHEN LP.POD_PRICE IS NULL THEN 1
                    WHEN LP.PO_CUR IS NOT NULL
                     AND UPPER(LTRIM(RTRIM(LP.PO_CUR))) NOT IN ('IDR','RP','RP.')
                     AND ITEM_RATE.CURR_VRATE IS NULL THEN 1
                    WHEN ISNULL(USD_RATE.CURR_VRATE, 0) <= 0 THEN 1
                    ELSE 0
                END AS ERROR_LINE
            FROM ConvertedQty CQ
            OUTER APPLY (
                SELECT TOP 1
                    P.PO_DATE, P.PO_CUR, PD.POD_PRICE
                FROM dbo.PO_DETAIL PD
                INNER JOIN dbo.PO P ON PD.PO_ID = P.PO_ID
                WHERE PD.ITEM_ID = CQ.MATERIAL_ID
                  AND P.PO_DATE < DATEADD(DAY, 1, CQ.STOCK_DATE)
                ORDER BY P.PO_DATE DESC, P.PO_ID DESC
            ) LP
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(LP.PO_CUR)))
                  AND LP.PO_DATE >= CR.CURR_SDATE
                  AND (LP.PO_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) ITEM_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND CQ.STOCK_DATE >= CR.CURR_SDATE
                  AND (CQ.STOCK_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
        ),
        StockTotals AS (
            SELECT
                PERIOD_KEY AS DATA_YEAR,
                STOCK_TYPE AS DATA_TYPE,
                CAST(SUM(ISNULL(AMOUNT_IDR, 0)) AS DECIMAL(38,2)) AS TOTAL_IDR,
                CAST(SUM(ISNULL(AMOUNT_USD, 0)) AS DECIMAL(38,2)) AS TOTAL_USD,
                SUM(ERROR_LINE) AS ERROR_LINES
            FROM StockValuation
            GROUP BY PERIOD_KEY, STOCK_TYPE
        ),
        FlowData AS (
            SELECT
                YEAR(DI.DI_DATE) AS DATA_YEAR,
                CAST('SALES' AS VARCHAR(20)) AS DATA_TYPE,
                SUM(
                    DIPA_PAR.QTY * DIPA_PAR.PART_PRICE *
                    CASE
                        WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.') THEN 1
                        ELSE ISNULL(SALES_RATE.CURR_VRATE, 1)
                    END
                ) AS TOTAL_IDR,
                SUM(
                    CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) > 0 THEN
                        (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE *
                            CASE
                                WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.') THEN 1
                                ELSE ISNULL(SALES_RATE.CURR_VRATE, 1)
                            END
                        ) / USD_RATE.CURR_VRATE
                    ELSE 0 END
                ) AS TOTAL_USD,
                CAST(0 AS INT) AS ERROR_LINES
            FROM dbo.DI
            INNER JOIN dbo.DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
            INNER JOIN dbo.PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
            LEFT JOIN dbo.ACTIVE_PRICE_VIEW APV ON PRICE.PRICE_ID = APV.PRICE_ID
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(APV.CURR_CODE)))
                  AND DI.DI_DATE >= CR.CURR_SDATE
                  AND (DI.DI_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) SALES_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND DI.DI_DATE >= CR.CURR_SDATE
                  AND (DI.DI_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
            WHERE DI.DI_DATE >= '{$startDate}' AND DI.DI_DATE <= '{$endDate}'
            GROUP BY YEAR(DI.DI_DATE)

            UNION ALL

            SELECT
                YEAR(R.RCV_DATE),
                CAST('PURCHASE' AS VARCHAR(20)),
                SUM(
                    RD.RCVD_QTY * RD.POD_PRICE *
                    CASE
                        WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.') THEN 1
                        ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1)
                    END
                ),
                SUM(
                    CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) > 0 THEN
                        (RD.RCVD_QTY * RD.POD_PRICE *
                            CASE
                                WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.') THEN 1
                                ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1)
                            END
                        ) / USD_RATE.CURR_VRATE
                    ELSE 0 END
                ),
                CAST(0 AS INT)
            FROM dbo.RECEIVE R
            INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
            INNER JOIN dbo.PO PO ON RD.PO_ID = PO.PO_ID
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(PO.PO_CUR)))
                  AND R.RCV_DATE >= CR.CURR_SDATE
                  AND (R.RCV_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) PURCHASE_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND R.RCV_DATE >= CR.CURR_SDATE
                  AND (R.RCV_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
            WHERE R.RCV_TYPE = 1
              AND R.RCV_DATE >= '{$startDate}' AND R.RCV_DATE <= '{$endDate}'
            GROUP BY YEAR(R.RCV_DATE)
        )
        SELECT DATA_YEAR, DATA_TYPE, TOTAL_IDR, TOTAL_USD, ERROR_LINES
        FROM (
            SELECT DATA_YEAR, DATA_TYPE, TOTAL_IDR, TOTAL_USD, ERROR_LINES FROM FlowData
            UNION ALL
            SELECT DATA_YEAR, DATA_TYPE, TOTAL_IDR, TOTAL_USD, ERROR_LINES FROM StockTotals
        ) CombinedResult
        ORDER BY DATA_YEAR,
            CASE DATA_TYPE
                WHEN 'STOCK_AWAL' THEN 1
                WHEN 'PURCHASE' THEN 2
                WHEN 'STOCK_AKHIR' THEN 3
                WHEN 'SALES' THEN 4
                ELSE 5
            END
        OPTION (MAXRECURSION 32767)
SQL;

        foreach ($servers_to_try as $key) {
            $serverLabel = isset($servers_config[$key]['label']) ? $servers_config[$key]['label'] : strtoupper($key);
            $conn = @sqlsrv_connect($servers_config[$key]['ip'], $conn_opts_base);
            if (!$conn) {
                $conn_errors[] = "<strong>" . htmlspecialchars($serverLabel, ENT_QUOTES, 'UTF-8') . "</strong> offline.";
                continue;
            }

            $server_available[$key] = true;
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt === false) {
                $conn_errors[] = "<strong>" . htmlspecialchars($serverLabel, ENT_QUOTES, 'UTF-8') . "</strong>: query tahunan stok/pembelian/sales gagal.";
                sqlsrv_close($conn);
                continue;
            }

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $year = (int)$row['DATA_YEAR'];
                if (!isset($raw_yearly[$year])) continue;

                $type = strtoupper(trim((string)$row['DATA_TYPE']));
                $idr = isset($row['TOTAL_IDR']) ? (float)$row['TOTAL_IDR'] : 0;
                $usd = isset($row['TOTAL_USD']) ? (float)$row['TOTAL_USD'] : 0;
                $errors = isset($row['ERROR_LINES']) ? (int)$row['ERROR_LINES'] : 0;

                if ($type === 'STOCK_AWAL') {
                    $raw_yearly[$year]['stock_awal_idr'] += $idr;
                    $raw_yearly[$year]['stock_awal_usd'] += $usd;
                    $raw_yearly[$year]['stock_error_lines'] += $errors;
                } elseif ($type === 'PURCHASE') {
                    $raw_yearly[$year]['purchase_idr'] += $idr;
                    $raw_yearly[$year]['purchase_usd'] += $usd;
                } elseif ($type === 'STOCK_AKHIR') {
                    $raw_yearly[$year]['stock_akhir_idr'] += $idr;
                    $raw_yearly[$year]['stock_akhir_usd'] += $usd;
                    $raw_yearly[$year]['stock_error_lines'] += $errors;
                } elseif ($type === 'SALES') {
                    $raw_yearly[$year]['sales_idr'] += $idr;
                    $raw_yearly[$year]['sales_usd'] += $usd;
                }
            }

            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
        }

        $stockErrorTotal = 0;
        foreach ($raw_yearly as $yearlyRow) {
            $stockErrorTotal += isset($yearlyRow['stock_error_lines']) ? (int)$yearlyRow['stock_error_lines'] : 0;
        }
        if ($stockErrorTotal > 0) {
            $conn_errors[] = "<strong>Validasi stok:</strong> {$stockErrorTotal} baris tidak memiliki BOM, harga PO, atau kurs yang lengkap.";
        }

        echo json_encode(array(
            'status' => 'success',
            'yearly_data' => array_values($raw_yearly),
            'servers' => $server_available,
            'errors' => $conn_errors,
            'plant_active' => $selected_plant
        ));
        exit;
    }

    // ----------------------------------------
    // ACTION 2: FETCH BULANAN
    // Stok awal = tanggal 1 bulan berjalan
    // Stok akhir = tanggal 1 bulan berikutnya
    // ----------------------------------------
    if ($action === 'fetch_monthly') {
        $targetYear = isset($_GET['targetYear']) ? (int)$_GET['targetYear'] : (int)date('Y');
        $startDate = $targetYear . '-01-01';
        $endDate = $targetYear . '-12-31 23:59:59';

        $raw_monthly = array(
            'stock_awal_idr' => array_fill(0, 12, 0),
            'stock_awal_usd' => array_fill(0, 12, 0),
            'purchase_idr' => array_fill(0, 12, 0),
            'purchase_usd' => array_fill(0, 12, 0),
            'stock_akhir_idr' => array_fill(0, 12, 0),
            'stock_akhir_usd' => array_fill(0, 12, 0),
            'sales_idr' => array_fill(0, 12, 0),
            'sales_usd' => array_fill(0, 12, 0),
            'stock_error_lines' => array_fill(0, 12, 0)
        );
        $raw_monthly_rates = array_fill(0, 12, array());

        $sql = <<<SQL
        ;WITH MonthList AS (
            SELECT CAST(1 AS INT) AS PERIOD_KEY
            UNION ALL
            SELECT PERIOD_KEY + 1 FROM MonthList WHERE PERIOD_KEY < 12
        ),
        SnapshotDates AS (
            SELECT PERIOD_KEY, CAST('STOCK_AWAL' AS VARCHAR(20)) AS STOCK_TYPE,
                   DATEADD(MONTH, PERIOD_KEY - 1, CAST('{$targetYear}-01-01' AS DATETIME)) AS STOCK_DATE
            FROM MonthList
            UNION ALL
            SELECT PERIOD_KEY, CAST('STOCK_AKHIR' AS VARCHAR(20)),
                   DATEADD(MONTH, PERIOD_KEY, CAST('{$targetYear}-01-01' AS DATETIME))
            FROM MonthList
        ),
        StockBase AS (
            SELECT
                SD.PERIOD_KEY, SD.STOCK_TYPE, SD.STOCK_DATE,
                I.ITEM_ID AS STOCK_ITEM_ID,
                LTRIM(RTRIM(I.ITTY_CODE)) AS STOCK_ITTY_CODE,
                CAST(SUM(ISNULL(T.TAG_QTY, 0)) AS DECIMAL(38,8)) AS STOCK_QTY
            FROM SnapshotDates SD
            INNER JOIN dbo.SOP S
                ON S.SOP_SDATE >= SD.STOCK_DATE
               AND S.SOP_SDATE < DATEADD(DAY, 1, SD.STOCK_DATE)
            INNER JOIN dbo.TAGS T ON S.SOP_ID = T.SOP_ID
            INNER JOIN dbo.ITEMS I ON T.ITEM_ID = I.ITEM_ID
            WHERE LTRIM(RTRIM(I.ITTY_CODE)) IN ('01','02','03')
            GROUP BY SD.PERIOD_KEY, SD.STOCK_TYPE, SD.STOCK_DATE,
                     I.ITEM_ID, LTRIM(RTRIM(I.ITTY_CODE))
        ),
        BOM_TREE AS (
            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID, SB.STOCK_QTY,
                BD.ITEM_ID AS COMPONENT_ID,
                LTRIM(RTRIM(C.ITTY_CODE)) AS COMPONENT_ITTY_CODE,
                CAST(BD.QTY AS DECIMAL(38,12)) AS TOTAL_BOM_QTY,
                CAST(1 AS INT) AS BOM_LEVEL,
                CAST('/' + CAST(SB.STOCK_ITEM_ID AS VARCHAR(20)) + '/' +
                     CAST(BD.ITEM_ID AS VARCHAR(20)) + '/' AS VARCHAR(MAX)) AS ITEM_PATH
            FROM StockBase SB
            INNER JOIN dbo.BOM_DEFAULT BD ON SB.STOCK_ITEM_ID = BD.PART_ID
            INNER JOIN dbo.ITEMS C ON BD.ITEM_ID = C.ITEM_ID
            WHERE SB.STOCK_ITTY_CODE = '01'

            UNION ALL

            SELECT
                BT.PERIOD_KEY, BT.STOCK_TYPE, BT.STOCK_DATE,
                BT.STOCK_ITEM_ID, BT.STOCK_QTY,
                BD.ITEM_ID,
                LTRIM(RTRIM(C.ITTY_CODE)),
                CAST(BT.TOTAL_BOM_QTY * BD.QTY AS DECIMAL(38,12)),
                BT.BOM_LEVEL + 1,
                CAST(BT.ITEM_PATH + CAST(BD.ITEM_ID AS VARCHAR(20)) + '/' AS VARCHAR(MAX))
            FROM BOM_TREE BT
            INNER JOIN dbo.BOM_DEFAULT BD ON BT.COMPONENT_ID = BD.PART_ID
            INNER JOIN dbo.ITEMS C ON BD.ITEM_ID = C.ITEM_ID
            WHERE BT.COMPONENT_ITTY_CODE = '01'
              AND BT.BOM_LEVEL < 20
              AND CHARINDEX('/' + CAST(BD.ITEM_ID AS VARCHAR(20)) + '/', BT.ITEM_PATH) = 0
        ),
        FinalMaterial AS (
            SELECT
                PERIOD_KEY, STOCK_TYPE, STOCK_DATE, STOCK_ITEM_ID, STOCK_QTY,
                COMPONENT_ID AS MATERIAL_ID,
                COMPONENT_ITTY_CODE AS MATERIAL_ITTY_CODE,
                CAST(SUM(TOTAL_BOM_QTY) AS DECIMAL(38,12)) AS BOM_QTY_PER_UNIT
            FROM BOM_TREE
            WHERE COMPONENT_ITTY_CODE IN ('02','03')
            GROUP BY PERIOD_KEY, STOCK_TYPE, STOCK_DATE, STOCK_ITEM_ID, STOCK_QTY,
                     COMPONENT_ID, COMPONENT_ITTY_CODE
        ),
        ConvertedQty AS (
            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID, SB.STOCK_ITEM_ID AS MATERIAL_ID,
                SB.STOCK_ITTY_CODE AS MATERIAL_ITTY_CODE,
                CAST(CASE WHEN SB.STOCK_ITTY_CODE = '02'
                          THEN SB.STOCK_QTY / NULLIF({$stockItty02Divisor}, 0)
                          ELSE SB.STOCK_QTY END AS DECIMAL(38,12)) AS MATERIAL_QTY_PRICE,
                CAST('DIRECT_MATERIAL' AS VARCHAR(40)) AS CONVERSION_STATUS
            FROM StockBase SB
            WHERE SB.STOCK_ITTY_CODE IN ('02','03')

            UNION ALL

            SELECT
                FM.PERIOD_KEY, FM.STOCK_TYPE, FM.STOCK_DATE,
                FM.STOCK_ITEM_ID, FM.MATERIAL_ID, FM.MATERIAL_ITTY_CODE,
                CAST(CASE WHEN FM.MATERIAL_ITTY_CODE = '02'
                          THEN (FM.STOCK_QTY * FM.BOM_QTY_PER_UNIT) / NULLIF({$bomItty02Divisor}, 0)
                          ELSE FM.STOCK_QTY * FM.BOM_QTY_PER_UNIT END AS DECIMAL(38,12)),
                CAST('BOM_TO_MATERIAL' AS VARCHAR(40))
            FROM FinalMaterial FM

            UNION ALL

            SELECT
                SB.PERIOD_KEY, SB.STOCK_TYPE, SB.STOCK_DATE,
                SB.STOCK_ITEM_ID, NULL, NULL, NULL,
                CAST('TANPA_BOM_MATERIAL' AS VARCHAR(40))
            FROM StockBase SB
            WHERE SB.STOCK_ITTY_CODE = '01'
              AND NOT EXISTS (
                  SELECT 1 FROM FinalMaterial FM
                  WHERE FM.PERIOD_KEY = SB.PERIOD_KEY
                    AND FM.STOCK_TYPE = SB.STOCK_TYPE
                    AND FM.STOCK_ITEM_ID = SB.STOCK_ITEM_ID
              )
        ),
        StockValuation AS (
            SELECT
                CQ.PERIOD_KEY, CQ.STOCK_TYPE,
                CAST(
                    CQ.MATERIAL_QTY_PRICE *
                    CASE
                        WHEN LP.POD_PRICE IS NULL THEN NULL
                        WHEN LP.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(LP.PO_CUR))) IN ('IDR','RP','RP.')
                            THEN LP.POD_PRICE
                        WHEN ITEM_RATE.CURR_VRATE IS NOT NULL
                            THEN LP.POD_PRICE * ITEM_RATE.CURR_VRATE
                        ELSE NULL
                    END AS DECIMAL(38,2)
                ) AS AMOUNT_IDR,
                CAST(
                    CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) <= 0 THEN NULL
                    ELSE
                        (CQ.MATERIAL_QTY_PRICE *
                            CASE
                                WHEN LP.POD_PRICE IS NULL THEN NULL
                                WHEN LP.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(LP.PO_CUR))) IN ('IDR','RP','RP.')
                                    THEN LP.POD_PRICE
                                WHEN ITEM_RATE.CURR_VRATE IS NOT NULL
                                    THEN LP.POD_PRICE * ITEM_RATE.CURR_VRATE
                                ELSE NULL
                            END
                        ) / USD_RATE.CURR_VRATE
                    END AS DECIMAL(38,2)
                ) AS AMOUNT_USD,
                CASE
                    WHEN CQ.CONVERSION_STATUS = 'TANPA_BOM_MATERIAL' THEN 1
                    WHEN LP.POD_PRICE IS NULL THEN 1
                    WHEN LP.PO_CUR IS NOT NULL
                     AND UPPER(LTRIM(RTRIM(LP.PO_CUR))) NOT IN ('IDR','RP','RP.')
                     AND ITEM_RATE.CURR_VRATE IS NULL THEN 1
                    WHEN ISNULL(USD_RATE.CURR_VRATE, 0) <= 0 THEN 1
                    ELSE 0
                END AS ERROR_LINE
            FROM ConvertedQty CQ
            OUTER APPLY (
                SELECT TOP 1 P.PO_DATE, P.PO_CUR, PD.POD_PRICE
                FROM dbo.PO_DETAIL PD
                INNER JOIN dbo.PO P ON PD.PO_ID = P.PO_ID
                WHERE PD.ITEM_ID = CQ.MATERIAL_ID
                  AND P.PO_DATE < DATEADD(DAY, 1, CQ.STOCK_DATE)
                ORDER BY P.PO_DATE DESC, P.PO_ID DESC
            ) LP
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(LP.PO_CUR)))
                  AND LP.PO_DATE >= CR.CURR_SDATE
                  AND (LP.PO_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) ITEM_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE
                FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND CQ.STOCK_DATE >= CR.CURR_SDATE
                  AND (CQ.STOCK_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
        ),
        StockTotals AS (
            SELECT
                PERIOD_KEY AS DATA_MONTH,
                STOCK_TYPE AS DATA_TYPE,
                CAST(NULL AS VARCHAR(20)) AS CURR_CODE,
                CAST(NULL AS DECIMAL(38,8)) AS CURR_VRATE,
                CAST(SUM(ISNULL(AMOUNT_IDR, 0)) AS DECIMAL(38,2)) AS TOTAL_IDR,
                CAST(SUM(ISNULL(AMOUNT_USD, 0)) AS DECIMAL(38,2)) AS TOTAL_USD,
                SUM(ERROR_LINE) AS ERROR_LINES
            FROM StockValuation
            GROUP BY PERIOD_KEY, STOCK_TYPE
        ),
        FlowData AS (
            SELECT
                MONTH(DI.DI_DATE) AS DATA_MONTH,
                CAST('SALES' AS VARCHAR(20)) AS DATA_TYPE,
                UPPER(LTRIM(RTRIM(ISNULL(APV.CURR_CODE, 'IDR')))) AS CURR_CODE,
                CAST(CASE WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.')
                          THEN 1 ELSE ISNULL(SALES_RATE.CURR_VRATE, 1) END AS DECIMAL(38,8)) AS CURR_VRATE,
                SUM(DIPA_PAR.QTY * DIPA_PAR.PART_PRICE *
                    CASE WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.')
                         THEN 1 ELSE ISNULL(SALES_RATE.CURR_VRATE, 1) END) AS TOTAL_IDR,
                SUM(CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) > 0 THEN
                    (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE *
                        CASE WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.')
                             THEN 1 ELSE ISNULL(SALES_RATE.CURR_VRATE, 1) END) / USD_RATE.CURR_VRATE
                    ELSE 0 END) AS TOTAL_USD,
                CAST(0 AS INT) AS ERROR_LINES
            FROM dbo.DI
            INNER JOIN dbo.DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
            INNER JOIN dbo.PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
            LEFT JOIN dbo.ACTIVE_PRICE_VIEW APV ON PRICE.PRICE_ID = APV.PRICE_ID
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(APV.CURR_CODE)))
                  AND DI.DI_DATE >= CR.CURR_SDATE
                  AND (DI.DI_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) SALES_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND DI.DI_DATE >= CR.CURR_SDATE
                  AND (DI.DI_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
            WHERE DI.DI_DATE >= '{$startDate}' AND DI.DI_DATE <= '{$endDate}'
            GROUP BY MONTH(DI.DI_DATE),
                     UPPER(LTRIM(RTRIM(ISNULL(APV.CURR_CODE, 'IDR')))),
                     CASE WHEN APV.CURR_CODE IS NULL OR UPPER(LTRIM(RTRIM(APV.CURR_CODE))) IN ('IDR','RP','RP.')
                          THEN 1 ELSE ISNULL(SALES_RATE.CURR_VRATE, 1) END

            UNION ALL

            SELECT
                MONTH(R.RCV_DATE),
                CAST('PURCHASE' AS VARCHAR(20)),
                UPPER(LTRIM(RTRIM(ISNULL(PO.PO_CUR, 'IDR')))),
                CAST(CASE WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.')
                          THEN 1 ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1) END AS DECIMAL(38,8)),
                SUM(RD.RCVD_QTY * RD.POD_PRICE *
                    CASE WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.')
                         THEN 1 ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1) END),
                SUM(CASE WHEN ISNULL(USD_RATE.CURR_VRATE, 0) > 0 THEN
                    (RD.RCVD_QTY * RD.POD_PRICE *
                        CASE WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.')
                             THEN 1 ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1) END) / USD_RATE.CURR_VRATE
                    ELSE 0 END),
                CAST(0 AS INT)
            FROM dbo.RECEIVE R
            INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
            INNER JOIN dbo.PO PO ON RD.PO_ID = PO.PO_ID
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = UPPER(LTRIM(RTRIM(PO.PO_CUR)))
                  AND R.RCV_DATE >= CR.CURR_SDATE
                  AND (R.RCV_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) PURCHASE_RATE
            OUTER APPLY (
                SELECT TOP 1 CR.CURR_VRATE FROM dbo.CURR_RAT CR
                WHERE UPPER(LTRIM(RTRIM(CR.CURR_CODE))) = 'USD'
                  AND R.RCV_DATE >= CR.CURR_SDATE
                  AND (R.RCV_DATE <= CR.CURR_EDATE OR CR.CURR_EDATE IS NULL)
                ORDER BY CR.CURR_SDATE DESC
            ) USD_RATE
            WHERE R.RCV_TYPE = 1
              AND R.RCV_DATE >= '{$startDate}' AND R.RCV_DATE <= '{$endDate}'
            GROUP BY MONTH(R.RCV_DATE),
                     UPPER(LTRIM(RTRIM(ISNULL(PO.PO_CUR, 'IDR')))),
                     CASE WHEN PO.PO_CUR IS NULL OR UPPER(LTRIM(RTRIM(PO.PO_CUR))) IN ('IDR','RP','RP.')
                          THEN 1 ELSE ISNULL(PURCHASE_RATE.CURR_VRATE, 1) END
        )
        SELECT DATA_MONTH, DATA_TYPE, CURR_CODE, CURR_VRATE, TOTAL_IDR, TOTAL_USD, ERROR_LINES
        FROM (
            SELECT DATA_MONTH, DATA_TYPE, CURR_CODE, CURR_VRATE, TOTAL_IDR, TOTAL_USD, ERROR_LINES FROM FlowData
            UNION ALL
            SELECT DATA_MONTH, DATA_TYPE, CURR_CODE, CURR_VRATE, TOTAL_IDR, TOTAL_USD, ERROR_LINES FROM StockTotals
        ) CombinedResult
        ORDER BY DATA_MONTH,
            CASE DATA_TYPE
                WHEN 'STOCK_AWAL' THEN 1
                WHEN 'PURCHASE' THEN 2
                WHEN 'STOCK_AKHIR' THEN 3
                WHEN 'SALES' THEN 4
                ELSE 5
            END,
            CURR_CODE
        OPTION (MAXRECURSION 32767)
SQL;

        foreach ($servers_to_try as $key) {
            $serverLabel = isset($servers_config[$key]['label']) ? $servers_config[$key]['label'] : strtoupper($key);
            $conn = @sqlsrv_connect($servers_config[$key]['ip'], $conn_opts_base);
            if (!$conn) {
                $conn_errors[] = "<strong>" . htmlspecialchars($serverLabel, ENT_QUOTES, 'UTF-8') . "</strong> offline.";
                continue;
            }

            $server_available[$key] = true;
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt === false) {
                $conn_errors[] = "<strong>" . htmlspecialchars($serverLabel, ENT_QUOTES, 'UTF-8') . "</strong>: query bulanan stok/pembelian/sales gagal.";
                sqlsrv_close($conn);
                continue;
            }

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $month = (int)$row['DATA_MONTH'];
                $idx = $month - 1;
                if ($idx < 0 || $idx > 11) continue;

                $type = strtoupper(trim((string)$row['DATA_TYPE']));
                $idr = isset($row['TOTAL_IDR']) ? (float)$row['TOTAL_IDR'] : 0;
                $usd = isset($row['TOTAL_USD']) ? (float)$row['TOTAL_USD'] : 0;
                $errors = isset($row['ERROR_LINES']) ? (int)$row['ERROR_LINES'] : 0;

                if ($type === 'STOCK_AWAL') {
                    $raw_monthly['stock_awal_idr'][$idx] += $idr;
                    $raw_monthly['stock_awal_usd'][$idx] += $usd;
                    $raw_monthly['stock_error_lines'][$idx] += $errors;
                } elseif ($type === 'PURCHASE') {
                    $raw_monthly['purchase_idr'][$idx] += $idr;
                    $raw_monthly['purchase_usd'][$idx] += $usd;
                } elseif ($type === 'STOCK_AKHIR') {
                    $raw_monthly['stock_akhir_idr'][$idx] += $idr;
                    $raw_monthly['stock_akhir_usd'][$idx] += $usd;
                    $raw_monthly['stock_error_lines'][$idx] += $errors;
                } elseif ($type === 'SALES') {
                    $raw_monthly['sales_idr'][$idx] += $idr;
                    $raw_monthly['sales_usd'][$idx] += $usd;
                }

                $curr = isset($row['CURR_CODE']) ? strtoupper(trim((string)$row['CURR_CODE'])) : '';
                $rate = isset($row['CURR_VRATE']) ? (float)$row['CURR_VRATE'] : 0;
                if (in_array($type, array('PURCHASE', 'SALES'), true)
                    && $curr !== '' && !in_array($curr, array('IDR','RP','RP.'), true)) {
                    $prefix = ($type === 'PURCHASE') ? 'Pembelian' : 'Sales';
                    $rateString = $prefix . ' ' . $curr . ' ' . number_format($rate, 0, ',', '.');
                    if (!in_array($rateString, $raw_monthly_rates[$idx], true)) {
                        $raw_monthly_rates[$idx][] = $rateString;
                    }
                }
            }

            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
        }

        $stockErrorTotal = array_sum($raw_monthly['stock_error_lines']);
        if ($stockErrorTotal > 0) {
            $conn_errors[] = "<strong>Validasi stok:</strong> {$stockErrorTotal} baris tidak memiliki BOM, harga PO, atau kurs yang lengkap.";
        }

        echo json_encode(array(
            'status' => 'success',
            'monthly_data' => $raw_monthly,
            'monthly_rates' => $raw_monthly_rates,
            'servers' => $server_available,
            'errors' => $conn_errors
        ));
        exit;
    }
}

// ============================================
// BLOK UI / TAMPILAN AWAL 
// ============================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Awal, Pembelian, Stok Akhir & Sales — IDR & USD</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
    <style>
        :root{--bg:#FFFFFF;--card:#FFFFFF;--border:#E5E7EB;--text:#000000;--muted:#000000;--vendor:#EA580C;--vendor-dim:rgba(234,88,12,0.10);--internal:#059669;--internal-dim:rgba(5,150,105,0.10);--accent:#D97706;--accent-dim:rgba(217,119,6,0.10);--danger:#DC2626;--r:12px;--rs:6px;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:24px 20px 60px;position:relative;overflow-x:hidden;}
        
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(4px);
            z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;
            opacity: 1; transition: opacity 0.4s ease;
        }
        #loadingOverlay.hidden { opacity: 0; pointer-events: none; }
        .spinner-ring {
            width: 50px; height: 50px; border: 4px solid var(--border); border-top-color: var(--accent);
            border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;
        }
        .mini-loader { display:none; align-items:center; gap:10px; color:var(--accent); font-size:13px; font-weight:600; font-family:'Space Grotesk',sans-serif; margin-bottom:15px; justify-content:center;}
        .mini-loader i { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-text { font-family: 'Space Grotesk', sans-serif; font-weight: 700; color: var(--accent); letter-spacing: 1px; }

        body::before{content:'';position:fixed;top:-250px;right:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(234,88,12,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 12s ease-in-out infinite alternate;}
        body::after{content:'';position:fixed;bottom:-250px;left:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(5,150,105,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 14s ease-in-out infinite alternate-reverse;}
        @keyframes floatB{0%{transform:translate(0,0) scale(1);}100%{transform:translate(30px,-20px) scale(1.08);}}
        
        .container{max-width:1100px;margin:0 auto;position:relative;z-index:1;}
        .header{margin-bottom:16px;}
        .header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:15px;}
        .header-title-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.3px;}
        .header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}
        .plant-badge{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.5px;}
        .plant-badge.offline-badge{background:rgba(248,81,73,0.12);color:var(--danger);}
        .header-sub{color:var(--muted);font-size:13px;}
        .header-sub b{color:var(--text);font-weight:600;}

        .header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap; display:none;}
        .btn-pdf,.btn-print,.btn-excel{border:none;padding:8px 16px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s;white-space:nowrap;}
        .btn-pdf{background:var(--accent);color:#FFFFFF;box-shadow:0 4px 12px rgba(217,119,6,0.20);}
        .btn-pdf:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(217,119,6,0.28);}
        .btn-print{background:#2563EB;color:#FFFFFF;box-shadow:0 4px 12px rgba(37,99,235,0.18);}
        .btn-print:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(37,99,235,0.28);}
        .btn-excel{background:#15803D;color:#FFFFFF;box-shadow:0 4px 12px rgba(21,128,61,0.20);}
        .btn-excel:hover{transform:translateY(-2px);background:#166534;box-shadow:0 6px 16px rgba(21,128,61,0.28);}

        .err-box{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.25);border-radius:10px;padding:14px 18px;margin-bottom:12px; display:none;}
        .err-box .err-title{color:var(--danger);font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
        .err-box .err-content { color:rgba(248,81,73,0.85);font-size:12px;line-height:1.7;}

        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}
        .filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        
        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
        .preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}
        .preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}
        .preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}
        .preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}

        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;margin-bottom:16px;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--vendor) 0%,var(--accent) 50%,var(--internal) 100%);}
        .chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}
        .chart-title i{color:var(--accent);font-size:14px;}
        .chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}
        .chart-area{position:relative;width:100%;height:380px;}
        .chart-area canvas{width:100%!important;height:100%!important;}
        .consumption-chart-area{position:relative;width:100%;height:320px;}
        .consumption-chart-area canvas{width:100%!important;height:100%!important;}
        .formula-note{font-size:12px;color:var(--muted);margin:-12px 0 18px;line-height:1.6;}
        .formula-note strong{color:var(--accent);}

        .empty-state{display:none;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}
        .empty-state .empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(248,81,73,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--danger);margin-bottom:20px;}
        .empty-state h3{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;}

        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}
        .legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;}
        .legend-dot{width:12px;height:12px;border-radius:3px;}
        .legend-dot.vendor{background:linear-gradient(180deg,#FF8C5A,#FF6B35);}
        .legend-dot.internal{background:linear-gradient(180deg,#33E0BE,#00D4AA);}

        .monthly-panel{max-height:0;overflow:hidden;opacity:0;transform:translateY(-8px);transition:max-height .5s ease,opacity .4s ease,transform .4s ease;margin-top:0;}
        .monthly-panel.open{max-height:1400px;opacity:1;transform:translateY(0);margin-bottom:16px;}
        .monthly-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px 28px 20px;position:relative;overflow:hidden;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .monthly-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent) 0%,var(--internal) 50%,var(--accent) 100%);}
        .monthly-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
        .monthly-header-left{display:flex;align-items:center;gap:10px;}
        .monthly-header-left .year-badge{background:var(--accent-dim);color:var(--accent);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;padding:5px 14px;border-radius:8px;}
        .close-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;}
        .monthly-chart-area{position:relative;width:100%;height:250px;}
        .monthly-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}
        
        .ms-item{display:flex;flex-direction:column;gap:2px;}
        .ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
        .ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}
        .ms-item .ms-val.mv{color:var(--vendor);}.ms-item .ms-val.mi{color:var(--internal);}.ms-item .ms-val.ma{color:var(--accent);}

        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;}
        .summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
        .summary-card.sv::before{background:var(--vendor);}.summary-card.si::before{background:var(--internal);}.summary-card.sa::before{background:var(--accent);}
        .sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}
        .sv .sc-icon{background:var(--vendor-dim);color:var(--vendor);}.si .sc-icon{background:var(--internal-dim);color:var(--internal);}.sa .sc-icon{background:var(--accent-dim);color:var(--accent);}
        .sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px;}
        .sc-num{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;line-height:1;}
        .sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}
        .sc-sub .pct{font-weight:700;}
        .pv{color:var(--vendor);}.pi{color:var(--internal);}

        .table-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 20px;position:relative;box-shadow:0 6px 20px rgba(15,23,42,0.045); display:none;}
        .table-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--internal) 0%,var(--accent) 50%,var(--vendor) 100%);}
        .table-wrapper{overflow-x:auto;margin-top:12px;}
        .data-table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;}
        .data-table th{font-family:'Space Grotesk',sans-serif;background:#F3F4F6;padding:8px 10px;font-weight:600;border-bottom:2px solid var(--border);white-space:nowrap;font-size:11px;}
        .data-table td{padding:7px 10px;border-bottom:1px solid #EEF2F7;font-family:'Space Grotesk',sans-serif;font-size:11px;white-space:nowrap;}
        .data-table tr.total-row{background:rgba(255,217,61,0.05)!important;font-weight:bold;}
        .data-table tr.total-row td{border-top:2px solid var(--border);border-bottom:2px solid var(--border);color:var(--accent);}
        .t-vendor{color:var(--vendor)!important;}
        .t-internal{color:var(--internal)!important;}
        .pct-bar-cell{display:flex;align-items:center;justify-content:center;}
        .pct-bar-track{width:38px;height:6px;border-radius:3px;background:rgba(17,24,39,0.08);overflow:hidden;position:relative;}
        .pct-bar-fill-v{position:absolute;left:0;top:0;height:100%;border-radius:3px;background:var(--vendor);}
        .pct-bar-fill-i{position:absolute;right:0;top:0;height:100%;border-radius:3px;background:var(--internal);}

        .table-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-left:auto;}
        .table-tabs{display:flex;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}
        .tab-btn{background:transparent;border:1px solid var(--border);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;}
        .tab-btn.active{background:var(--accent-dim);color:var(--accent);border-color:var(--accent);}
        .table-title-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}
        
        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;}
        .toast.show{opacity:1;transform:translateY(0);}
        
        body, th, td, h1, .chart-title, .sc-label, .sc-num { color: #000000 !important; }
    </style>
</head>
<body>
<!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="spinner-ring"></div>
    <div class="spinner-text">Menarik Data Tahun Ini...</div>
</div>

<div class="container" id="mainContainer">
    <div class="header">
        <div class="header-top">
            <div class="header-title-group">
                <h1><i class="fas fa-chart-column"></i> Stok, Pembelian & Sales</h1>
                <span class="plant-badge" id="topPlantBadge">PLANT ...</span>
            </div>
            <div class="header-actions" id="exportBtnGroup">
                <button id="printBtn" class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button id="pdfBtn" class="btn-pdf" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
            </div>
        </div>
        <div class="header-sub">Urutan analisis: Stok Awal → Pembelian → Stok Akhir → Sales dalam IDR dan USD &nbsp;|&nbsp; Periode: <b id="periodLabel">...</b></div>
    </div>
    
    <div class="err-box" id="errorBox">
        <div class="err-title"><i class="fas fa-triangle-exclamation"></i> Peringatan Koneksi</div>
        <div class="err-content" id="errorContent"></div>
    </div>
    
    <div class="filter-bar">
        <label for="plantFilter"><i class="fas fa-building" style="color:var(--accent);"></i> Lokasi Plant</label>
        <select id="plantFilter">
            <option value="all">Gabungan (P1 & P2)</option>
            <option value="p1">Plant 1 Saja</option>
            <option value="p2">Plant 2 Saja</option>
        </select>
        <div class="sep"></div><label for="startYear">Dari</label><select id="startYear"></select>
        <div class="sep"></div><label for="endYear">Sampai</label><select id="endYear"></select>
        <div class="range-hint"><i class="fas fa-calendar-days"></i><span id="rangeText">... tahun</span></div>
        <button class="preset-btn" style="margin-left:10px; background:var(--accent); color:#fff; border:none;" onclick="triggerFetchYearly()">Terapkan Filter</button>
    </div>
    
    <div class="preset-row">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn active" onclick="applyPreset(1)">Tahun Ini</button>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(10)">10 Tahun</button>
    </div>

    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-chart-bar"></i> Perbandingan Tahunan — Stok Awal, Pembelian, Stok Akhir, Sales<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik grafik untuk detail bulanan</span></div>
        <div class="empty-state" id="emptyState">
            <div class="empty-icon"><i class="fas fa-server"></i></div><h3>Tidak Ada Data</h3><p>Tidak ada data pada rentang waktu ini atau server offline.</p>
        </div>
        <div class="chart-area" id="chartAreaContainer"><canvas id="annualBarChart"></canvas></div>
        <div class="custom-legend" id="legendBar">
            <div class="legend-item"><div style="width:12px;height:12px;border-radius:3px;background:#D97706;"></div>Stok Awal <small>(bar IDR / garis USD)</small></div>
            <div class="legend-item"><div style="width:12px;height:12px;border-radius:3px;background:#2563EB;"></div>Pembelian <small>(bar IDR / garis USD)</small></div>
            <div class="legend-item"><div style="width:12px;height:12px;border-radius:3px;background:#7C3AED;"></div>Stok Akhir <small>(bar IDR / garis USD)</small></div>
            <div class="legend-item"><div style="width:12px;height:12px;border-radius:3px;background:#059669;"></div>Sales <small>(bar IDR / garis USD)</small></div>
        </div>
    </div>

    <div class="chart-card" id="consumptionYearlyCard" style="display:none;">
        <div class="chart-title"><i class="fas fa-percent"></i> Persentase Konsumsi Material Tahunan<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik titik untuk detail bulanan</span></div>
        <div class="formula-note"><strong>Rumus:</strong> (Stok Awal + Pembelian − Stok Akhir) ÷ Sales × 100%</div>
        <div class="consumption-chart-area"><canvas id="annualConsumptionChart"></canvas></div>
    </div>

    <div class="monthly-panel" id="monthlyPanel">
        <div class="monthly-card">
            <div class="monthly-header">
                <div class="monthly-header-left"><span class="year-badge" id="monthlyYearBadge">...</span><h3>Perbandingan Bulanan — Stok Awal, Pembelian, Stok Akhir, Sales</h3></div>
                <button class="close-btn" id="closeMonthly"><i class="fas fa-xmark"></i></button>
            </div>
            
            <div class="mini-loader" id="monthlyLoader"><i class="fas fa-circle-notch"></i> Menarik data bulan...</div>
            
            <div id="monthlyContentContainer" style="display:none;">
                <div class="monthly-chart-area"><canvas id="monthlyBarChart"></canvas></div>
                <div class="chart-title" style="margin-top:24px;margin-bottom:8px;"><i class="fas fa-percent"></i> Persentase Konsumsi Material Bulanan</div>
                <div class="formula-note"><strong>Rumus:</strong> (Stok Awal + Pembelian − Stok Akhir) ÷ Sales × 100%</div>
                <div class="consumption-chart-area"><canvas id="monthlyConsumptionChart"></canvas></div>
                <div class="monthly-summary" id="monthlySummary"></div>
            </div>
        </div>
    </div>

    <div class="summary-row" id="summaryRow"></div>

    <div class="table-card" id="tableDataContainer">
        <div class="table-title-container">
            <div class="chart-title" style="margin-bottom:0;"><i class="fas fa-table"></i> Tabulasi Rincian</div>
            <div class="table-actions">
                <button type="button" id="excelBtn" class="btn-excel" onclick="exportTableExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
                <div class="table-tabs">
                    <button type="button" class="tab-btn active" id="tabTahunanBtn" onclick="switchTableTab('tahunan')">Tahunan</button>
                    <button type="button" class="tab-btn" id="tabBulananBtn" onclick="switchTableTab('bulanan')">Bulanan (<span id="tabBulananYear">-</span>)</button>
                </div>
            </div>
        </div>
        <div class="table-wrapper" id="wrapperTahunan">
            <table class="data-table" id="tableTahunan">
                <thead><tr>
                    <th style="text-align:center">Tahun</th>
                    <th style="text-align:right">Stok Awal (IDR)</th><th style="text-align:right">Stok Awal (USD)</th>
                    <th style="text-align:right">Pembelian (IDR)</th><th style="text-align:right">Pembelian (USD)</th>
                    <th style="text-align:right">Stok Akhir (IDR)</th><th style="text-align:right">Stok Akhir (USD)</th>
                    <th style="text-align:right">Sales (IDR)</th><th style="text-align:right">Sales (USD)</th>
                    <th style="text-align:right">Konsumsi Material (IDR)</th><th style="text-align:right">Konsumsi Material (USD)</th>
                    <th style="text-align:right">Konsumsi / Sales (IDR)</th><th style="text-align:right">Konsumsi / Sales (USD)</th>
                </tr></thead>
                <tbody id="tbodyTahunan"></tbody>
            </table>
        </div>
        <div class="table-wrapper" id="wrapperBulanan" style="display:none;">
            <table class="data-table" id="tableBulanan">
                <thead><tr>
                    <th style="text-align:center">Bulan</th>
                    <th style="text-align:center;color:var(--accent);">Kurs Aktif</th>
                    <th style="text-align:right">Stok Awal (IDR)</th><th style="text-align:right">Stok Awal (USD)</th>
                    <th style="text-align:right">Pembelian (IDR)</th><th style="text-align:right">Pembelian (USD)</th>
                    <th style="text-align:right">Stok Akhir (IDR)</th><th style="text-align:right">Stok Akhir (USD)</th>
                    <th style="text-align:right">Sales (IDR)</th><th style="text-align:right">Sales (USD)</th>
                    <th style="text-align:right">Konsumsi Material (IDR)</th><th style="text-align:right">Konsumsi Material (USD)</th>
                    <th style="text-align:right">Konsumsi / Sales (IDR)</th><th style="text-align:right">Konsumsi / Sales (USD)</th>
                </tr></thead>
                <tbody id="tbodyBulanan"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="toast" id="toast"><i class="fas fa-check-circle" style="color:var(--internal);"></i><span id="toastMsg"></span></div>

<script>
let ALL_DATA = [];
let CACHED_MONTHLY = {};
let chInst = null;
let chMInst = null;
let chConsumptionInst = null;
let chConsumptionMInst = null;
let selYear = null;
let activeTableTab = 'tahunan';

const ML = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const CURRENT_YEAR = new Date().getFullYear();

function initFilters() {
    const start = document.getElementById('startYear');
    const end = document.getElementById('endYear');
    for (let year = 2015; year <= CURRENT_YEAR + 1; year++) {
        start.add(new Option(year, year));
        end.add(new Option(year, year));
    }
    start.value = CURRENT_YEAR;
    end.value = CURRENT_YEAR;
    const params = new URLSearchParams(window.location.search);
    if (params.has('plant')) document.getElementById('plantFilter').value = params.get('plant');
}

function fS(value) {
    const number = Number(value || 0);
    const absolute = Math.abs(number);
    const sign = number < 0 ? '-' : '';
    if (absolute >= 1e12) return sign + (absolute / 1e12).toFixed(1) + 'T';
    if (absolute >= 1e9) return sign + (absolute / 1e9).toFixed(1) + 'M';
    if (absolute >= 1e6) return sign + (absolute / 1e6).toFixed(1) + 'Jt';
    return number.toLocaleString('id-ID');
}
function fF(value) { return 'Rp ' + Math.round(Number(value || 0)).toLocaleString('id-ID'); }
function fUSD(value) { return '$ ' + Number(value || 0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fPct(value) {
    return value === null || typeof value === 'undefined' || !isFinite(Number(value))
        ? '-'
        : Number(value).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}) + '%';
}
function materialMetrics(row) {
    const consumptionIDR = Number(row.stock_awal_idr || 0) + Number(row.purchase_idr || 0) - Number(row.stock_akhir_idr || 0);
    const consumptionUSD = Number(row.stock_awal_usd || 0) + Number(row.purchase_usd || 0) - Number(row.stock_akhir_usd || 0);
    const salesIDR = Number(row.sales_idr || 0);
    const salesUSD = Number(row.sales_usd || 0);
    return {
        consumptionIDR:consumptionIDR,
        consumptionUSD:consumptionUSD,
        percentageIDR:salesIDR !== 0 ? (consumptionIDR / salesIDR * 100) : null,
        percentageUSD:salesUSD !== 0 ? (consumptionUSD / salesUSD * 100) : null
    };
}
function showToast(message) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function triggerFetchYearly() {
    const plant = document.getElementById('plantFilter').value;
    const startYear = document.getElementById('startYear').value;
    const endYear = document.getElementById('endYear').value;

    CACHED_MONTHLY = {};
    selYear = null;
    document.getElementById('monthlyPanel').classList.remove('open');
    switchTableTab('tahunan');
    document.getElementById('loadingOverlay').classList.remove('hidden');

    fetch(`?action=fetch_yearly&plant=${plant}&startYear=${startYear}&endYear=${endYear}`)
        .then(response => response.json())
        .then(data => {
            ALL_DATA = data.yearly_data || [];
            updateUIYearly(data);
            document.getElementById('loadingOverlay').classList.add('hidden');
        })
        .catch(() => {
            showToast('Gagal menarik data');
            document.getElementById('loadingOverlay').classList.add('hidden');
        });
}

function updateUIYearly(data) {
    const startYear = document.getElementById('startYear').value;
    const endYear = document.getElementById('endYear').value;
    document.getElementById('periodLabel').textContent = startYear + (startYear !== endYear ? ' — ' + endYear : '');
    document.getElementById('rangeText').textContent = (endYear - startYear + 1) + ' tahun';

    const badge = document.getElementById('topPlantBadge');
    const p1 = Boolean(data.servers && data.servers.p1);
    const p2 = Boolean(data.servers && data.servers.p2);
    if (data.plant_active === 'p1') {
        badge.innerHTML = 'PLANT 1' + (!p1 ? ' (OFFLINE)' : '');
        badge.className = 'plant-badge' + (!p1 ? ' offline-badge' : '');
    } else if (data.plant_active === 'p2') {
        badge.innerHTML = 'PLANT 2' + (!p2 ? ' (OFFLINE)' : '');
        badge.className = 'plant-badge' + (!p2 ? ' offline-badge' : '');
    } else {
        const active = [];
        if (p1) active.push('P1');
        if (p2) active.push('P2');
        badge.innerHTML = active.length ? active.join(' & ') : 'SEMUA OFFLINE';
        badge.className = 'plant-badge' + (!active.length ? ' offline-badge' : '');
    }

    const errorBox = document.getElementById('errorBox');
    if (data.errors && data.errors.length) {
        errorBox.style.display = 'block';
        document.getElementById('errorContent').innerHTML = data.errors
            .map(error => `<div><i class="fas fa-circle" style="font-size:6px;vertical-align:middle;"></i> ${error}</div>`)
            .join('');
    } else {
        errorBox.style.display = 'none';
    }

    const fields = ['stock_awal_idr','stock_awal_usd','purchase_idr','purchase_usd','stock_akhir_idr','stock_akhir_usd','sales_idr','sales_usd'];
    const hasData = ALL_DATA.some(row => fields.some(field => Number(row[field] || 0) !== 0));
    document.getElementById('emptyState').style.display = hasData ? 'none' : 'flex';
    document.getElementById('chartAreaContainer').style.display = hasData ? 'block' : 'none';
    document.getElementById('consumptionYearlyCard').style.display = hasData ? 'block' : 'none';
    document.getElementById('legendBar').style.display = hasData ? 'flex' : 'none';
    document.getElementById('tableDataContainer').style.display = hasData ? 'block' : 'none';
    document.getElementById('exportBtnGroup').style.display = hasData ? 'flex' : 'none';

    if (hasData) {
        buildChartYearly();
        buildConsumptionChartYearly();
        buildSummaryYearly();
        buildTableYearly();
    } else {
        document.getElementById('summaryRow').innerHTML = '';
    }
}

function makeGradient(ctx, top, bottom, height) {
    const gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, top);
    gradient.addColorStop(1, bottom);
    return gradient;
}

function chartDatasets(ctx, data, height) {
    const categories = [
        {key:'stock_awal', label:'Stok Awal', color:'#D97706', top:'rgba(217,119,6,.88)', bottom:'rgba(217,119,6,.25)', dash:[3,3]},
        {key:'purchase', label:'Pembelian', color:'#2563EB', top:'rgba(37,99,235,.88)', bottom:'rgba(37,99,235,.25)', dash:[7,4]},
        {key:'stock_akhir', label:'Stok Akhir', color:'#7C3AED', top:'rgba(124,58,237,.88)', bottom:'rgba(124,58,237,.25)', dash:[3,3]},
        {key:'sales', label:'Sales', color:'#059669', top:'rgba(5,150,105,.88)', bottom:'rgba(5,150,105,.25)', dash:[]}
    ];
    const bars = categories.map((category, index) => ({
        label: category.label + ' (IDR)',
        data: data.map(row => Number(row[category.key + '_idr'] || 0)),
        yAxisID: 'yIDR',
        backgroundColor: makeGradient(ctx, category.top, category.bottom, height),
        borderColor: category.color,
        borderWidth: 1,
        borderRadius: 4,
        order: 10 + index
    }));
    const lines = categories.map((category, index) => ({
        label: category.label + ' (USD)',
        data: data.map(row => Number(row[category.key + '_usd'] || 0)),
        type: 'line',
        yAxisID: 'yUSD',
        borderColor: category.color,
        backgroundColor: 'transparent',
        borderWidth: 2,
        borderDash: category.dash,
        pointBackgroundColor: category.color,
        pointBorderColor: '#FFFFFF',
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: .25,
        fill: false,
        order: 1 + index
    }));
    return [...bars, ...lines];
}

function commonChartOptions(onClick) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {mode:'index', intersect:false},
        plugins: {
            legend: {display:false},
            tooltip: {callbacks: {label: context => context.dataset.yAxisID === 'yUSD'
                ? `${context.dataset.label}: ${fUSD(context.parsed.y)}`
                : `${context.dataset.label}: ${fF(context.parsed.y)}`}}
        },
        scales: {
            yIDR: {type:'linear', position:'left', beginAtZero:true, title:{display:true,text:'Nilai IDR'}, ticks:{callback:value=>fS(value)}},
            yUSD: {type:'linear', position:'right', beginAtZero:true, title:{display:true,text:'Nilai USD'}, ticks:{callback:value=>'$ '+fS(value)}, grid:{drawOnChartArea:false}}
        },
        onClick
    };
}

function buildChartYearly() {
    const labels = ALL_DATA.map(row => row.year);
    const ctx = document.getElementById('annualBarChart').getContext('2d');
    if (chInst) chInst.destroy();
    chInst = new Chart(ctx, {
        type:'bar',
        data:{labels, datasets:chartDatasets(ctx, ALL_DATA, 380)},
        options:commonChartOptions((event, elements) => {
            if (elements.length) loadMonthlyOnDemand(labels[elements[0].index]);
        })
    });
}

function consumptionChartOptions(onClick) {
    return {
        responsive:true,
        maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        plugins:{
            legend:{display:true,position:'top'},
            tooltip:{callbacks:{label:context => context.dataset.label + ': ' + fPct(context.parsed.y)}}
        },
        scales:{y:{beginAtZero:true,title:{display:true,text:'Persentase Konsumsi Material'},ticks:{callback:value=>value+'%'}}},
        onClick:onClick
    };
}

function consumptionDatasets(data) {
    return [
        {label:'Konsumsi / Sales (IDR)',data:data.map(row=>materialMetrics(row).percentageIDR),borderColor:'#EA580C',backgroundColor:'rgba(234,88,12,.18)',borderWidth:2.5,pointRadius:4,pointHoverRadius:7,tension:.25,spanGaps:false,fill:false},
        {label:'Konsumsi / Sales (USD)',data:data.map(row=>materialMetrics(row).percentageUSD),borderColor:'#2563EB',backgroundColor:'rgba(37,99,235,.18)',borderWidth:2.5,borderDash:[7,4],pointRadius:4,pointHoverRadius:7,tension:.25,spanGaps:false,fill:false}
    ];
}

function buildConsumptionChartYearly() {
    const labels = ALL_DATA.map(row => row.year);
    const ctx = document.getElementById('annualConsumptionChart').getContext('2d');
    if (chConsumptionInst) chConsumptionInst.destroy();
    chConsumptionInst = new Chart(ctx,{type:'line',data:{labels:labels,datasets:consumptionDatasets(ALL_DATA)},options:consumptionChartOptions((event,elements)=>{if(elements.length) loadMonthlyOnDemand(labels[elements[0].index]);})});
}

function periodTotals(data) {
    if (!data.length) return {stockAwalIDR:0,stockAwalUSD:0,purchaseIDR:0,purchaseUSD:0,stockAkhirIDR:0,stockAkhirUSD:0,salesIDR:0,salesUSD:0,consumptionIDR:0,consumptionUSD:0,percentageIDR:null,percentageUSD:null};
    const result = {
        stockAwalIDR:Number(data[0].stock_awal_idr || 0),
        stockAwalUSD:Number(data[0].stock_awal_usd || 0),
        purchaseIDR:data.reduce((sum,row)=>sum+Number(row.purchase_idr||0),0),
        purchaseUSD:data.reduce((sum,row)=>sum+Number(row.purchase_usd||0),0),
        stockAkhirIDR:Number(data[data.length-1].stock_akhir_idr || 0),
        stockAkhirUSD:Number(data[data.length-1].stock_akhir_usd || 0),
        salesIDR:data.reduce((sum,row)=>sum+Number(row.sales_idr||0),0),
        salesUSD:data.reduce((sum,row)=>sum+Number(row.sales_usd||0),0)
    };
    result.consumptionIDR = result.stockAwalIDR + result.purchaseIDR - result.stockAkhirIDR;
    result.consumptionUSD = result.stockAwalUSD + result.purchaseUSD - result.stockAkhirUSD;
    result.percentageIDR = result.salesIDR !== 0 ? (result.consumptionIDR / result.salesIDR * 100) : null;
    result.percentageUSD = result.salesUSD !== 0 ? (result.consumptionUSD / result.salesUSD * 100) : null;
    return result;
}

function buildSummaryYearly() {
    const total = periodTotals(ALL_DATA);
    document.getElementById('summaryRow').innerHTML = `
        <div class="summary-card" style="border-top:2px solid #D97706"><div class="sc-icon" style="background:rgba(217,119,6,.10);color:#D97706"><i class="fas fa-warehouse"></i></div><div class="sc-label">Stok Awal Periode</div><div class="sc-num">${fF(total.stockAwalIDR)}</div><div class="sc-sub">${fUSD(total.stockAwalUSD)}</div></div>
        <div class="summary-card" style="border-top:2px solid #2563EB"><div class="sc-icon" style="background:rgba(37,99,235,.10);color:#2563EB"><i class="fas fa-cart-flatbed"></i></div><div class="sc-label">Total Pembelian</div><div class="sc-num" style="color:#2563EB">${fF(total.purchaseIDR)}</div><div class="sc-sub">${fUSD(total.purchaseUSD)}</div></div>
        <div class="summary-card" style="border-top:2px solid #7C3AED"><div class="sc-icon" style="background:rgba(124,58,237,.10);color:#7C3AED"><i class="fas fa-boxes-stacked"></i></div><div class="sc-label">Stok Akhir Periode</div><div class="sc-num" style="color:#7C3AED">${fF(total.stockAkhirIDR)}</div><div class="sc-sub">${fUSD(total.stockAkhirUSD)}</div></div>
        <div class="summary-card si"><div class="sc-icon"><i class="fas fa-chart-line"></i></div><div class="sc-label">Total Sales</div><div class="sc-num t-internal">${fF(total.salesIDR)}</div><div class="sc-sub">${fUSD(total.salesUSD)}</div></div>
        <div class="summary-card" style="border-top:2px solid #EA580C"><div class="sc-icon" style="background:rgba(234,88,12,.10);color:#EA580C"><i class="fas fa-industry"></i></div><div class="sc-label">Konsumsi Material</div><div class="sc-num" style="color:#EA580C">${fF(total.consumptionIDR)}</div><div class="sc-sub">${fUSD(total.consumptionUSD)}</div></div>
        <div class="summary-card" style="border-top:2px solid #0F766E"><div class="sc-icon" style="background:rgba(15,118,110,.10);color:#0F766E"><i class="fas fa-percent"></i></div><div class="sc-label">Konsumsi / Sales</div><div class="sc-num" style="color:#0F766E">IDR ${fPct(total.percentageIDR)}</div><div class="sc-sub">USD ${fPct(total.percentageUSD)}</div></div>`;
}

function buildTableYearly() {
    let html = '';
    ALL_DATA.forEach(row => {
        const metrics = materialMetrics(row);
        html += `<tr>
            <td style="text-align:center">${row.year}</td>
            <td style="text-align:right;color:#D97706;font-weight:600">${fF(row.stock_awal_idr)}</td><td style="text-align:right">${fUSD(row.stock_awal_usd)}</td>
            <td style="text-align:right;color:#2563EB;font-weight:600">${fF(row.purchase_idr)}</td><td style="text-align:right">${fUSD(row.purchase_usd)}</td>
            <td style="text-align:right;color:#7C3AED;font-weight:600">${fF(row.stock_akhir_idr)}</td><td style="text-align:right">${fUSD(row.stock_akhir_usd)}</td>
            <td style="text-align:right;color:#059669;font-weight:600">${fF(row.sales_idr)}</td><td style="text-align:right">${fUSD(row.sales_usd)}</td>
            <td style="text-align:right;color:#EA580C;font-weight:600">${fF(metrics.consumptionIDR)}</td><td style="text-align:right">${fUSD(metrics.consumptionUSD)}</td>
            <td style="text-align:right;font-weight:700">${fPct(metrics.percentageIDR)}</td><td style="text-align:right;font-weight:700">${fPct(metrics.percentageUSD)}</td>
        </tr>`;
    });
    const total = periodTotals(ALL_DATA);
    html += `<tr class="total-row"><td style="text-align:center">TOTAL PERIODE</td>
        <td style="text-align:right">${fF(total.stockAwalIDR)}</td><td style="text-align:right">${fUSD(total.stockAwalUSD)}</td>
        <td style="text-align:right">${fF(total.purchaseIDR)}</td><td style="text-align:right">${fUSD(total.purchaseUSD)}</td>
        <td style="text-align:right">${fF(total.stockAkhirIDR)}</td><td style="text-align:right">${fUSD(total.stockAkhirUSD)}</td>
        <td style="text-align:right">${fF(total.salesIDR)}</td><td style="text-align:right">${fUSD(total.salesUSD)}</td>
        <td style="text-align:right">${fF(total.consumptionIDR)}</td><td style="text-align:right">${fUSD(total.consumptionUSD)}</td>
        <td style="text-align:right">${fPct(total.percentageIDR)}</td><td style="text-align:right">${fPct(total.percentageUSD)}</td></tr>`;
    document.getElementById('tbodyTahunan').innerHTML = html;
}

function loadMonthlyOnDemand(year) {
    selYear = year;
    document.getElementById('monthlyYearBadge').textContent = year;
    document.getElementById('tabBulananYear').textContent = year;
    const panel = document.getElementById('monthlyPanel');
    panel.classList.add('open');
    panel.scrollIntoView({behavior:'smooth'});

    if (CACHED_MONTHLY[year]) {
        renderMonthlyUI(year, CACHED_MONTHLY[year].data, CACHED_MONTHLY[year].rates, CACHED_MONTHLY[year].errors);
        return;
    }

    document.getElementById('monthlyContentContainer').style.display = 'none';
    document.getElementById('monthlyLoader').style.display = 'flex';
    const plant = document.getElementById('plantFilter').value;
    fetch(`?action=fetch_monthly&plant=${plant}&targetYear=${year}`)
        .then(response => response.json())
        .then(data => {
            CACHED_MONTHLY[year] = {data:data.monthly_data, rates:data.monthly_rates, errors:data.errors || []};
            renderMonthlyUI(year, data.monthly_data, data.monthly_rates, data.errors || []);
            document.getElementById('monthlyLoader').style.display = 'none';
            document.getElementById('monthlyContentContainer').style.display = 'block';
        })
        .catch(() => {
            showToast('Gagal menarik detail bulanan');
            document.getElementById('monthlyLoader').style.display = 'none';
        });
}

function monthlyRows(md) {
    return ML.map((month, index) => ({
        month,
        stock_awal_idr:Number(md.stock_awal_idr[index] || 0), stock_awal_usd:Number(md.stock_awal_usd[index] || 0),
        purchase_idr:Number(md.purchase_idr[index] || 0), purchase_usd:Number(md.purchase_usd[index] || 0),
        stock_akhir_idr:Number(md.stock_akhir_idr[index] || 0), stock_akhir_usd:Number(md.stock_akhir_usd[index] || 0),
        sales_idr:Number(md.sales_idr[index] || 0), sales_usd:Number(md.sales_usd[index] || 0)
    }));
}

function renderMonthlyUI(year, md, rates, errors) {
    document.getElementById('monthlyContentContainer').style.display = 'block';
    const rows = monthlyRows(md);
    const ctx = document.getElementById('monthlyBarChart').getContext('2d');
    if (chMInst) chMInst.destroy();
    chMInst = new Chart(ctx, {
        type:'bar',
        data:{labels:ML, datasets:chartDatasets(ctx, rows, 250)},
        options:commonChartOptions(null)
    });
    const consumptionCtx = document.getElementById('monthlyConsumptionChart').getContext('2d');
    if (chConsumptionMInst) chConsumptionMInst.destroy();
    chConsumptionMInst = new Chart(consumptionCtx,{type:'line',data:{labels:ML,datasets:consumptionDatasets(rows)},options:consumptionChartOptions(null)});

    let html = '';
    rows.forEach((row, index) => {
        const rateText = rates && rates[index] && rates[index].length ? rates[index].join('<br>') : '-';
        const metrics = materialMetrics(row);
        html += `<tr><td style="text-align:center">${row.month}</td><td style="text-align:center;font-size:10px;color:var(--accent);font-weight:600">${rateText}</td>
            <td style="text-align:right;color:#D97706;font-weight:600">${fF(row.stock_awal_idr)}</td><td style="text-align:right">${fUSD(row.stock_awal_usd)}</td>
            <td style="text-align:right;color:#2563EB;font-weight:600">${fF(row.purchase_idr)}</td><td style="text-align:right">${fUSD(row.purchase_usd)}</td>
            <td style="text-align:right;color:#7C3AED;font-weight:600">${fF(row.stock_akhir_idr)}</td><td style="text-align:right">${fUSD(row.stock_akhir_usd)}</td>
            <td style="text-align:right;color:#059669;font-weight:600">${fF(row.sales_idr)}</td><td style="text-align:right">${fUSD(row.sales_usd)}</td>
            <td style="text-align:right;color:#EA580C;font-weight:600">${fF(metrics.consumptionIDR)}</td><td style="text-align:right">${fUSD(metrics.consumptionUSD)}</td>
            <td style="text-align:right;font-weight:700">${fPct(metrics.percentageIDR)}</td><td style="text-align:right;font-weight:700">${fPct(metrics.percentageUSD)}</td></tr>`;
    });
    const total = periodTotals(rows);
    html += `<tr class="total-row"><td style="text-align:center">TOTAL TAHUN</td><td></td>
        <td style="text-align:right">${fF(total.stockAwalIDR)}</td><td style="text-align:right">${fUSD(total.stockAwalUSD)}</td>
        <td style="text-align:right">${fF(total.purchaseIDR)}</td><td style="text-align:right">${fUSD(total.purchaseUSD)}</td>
        <td style="text-align:right">${fF(total.stockAkhirIDR)}</td><td style="text-align:right">${fUSD(total.stockAkhirUSD)}</td>
        <td style="text-align:right">${fF(total.salesIDR)}</td><td style="text-align:right">${fUSD(total.salesUSD)}</td>
        <td style="text-align:right">${fF(total.consumptionIDR)}</td><td style="text-align:right">${fUSD(total.consumptionUSD)}</td>
        <td style="text-align:right">${fPct(total.percentageIDR)}</td><td style="text-align:right">${fPct(total.percentageUSD)}</td></tr>`;
    document.getElementById('tbodyBulanan').innerHTML = html;

    document.getElementById('monthlySummary').innerHTML = `
        <div class="ms-item"><span class="ms-label">Stok Awal Januari</span><span class="ms-val" style="color:#D97706">${fF(total.stockAwalIDR)}</span><span class="ms-label">${fUSD(total.stockAwalUSD)}</span></div>
        <div class="ms-item"><span class="ms-label">Pembelian Setahun</span><span class="ms-val" style="color:#2563EB">${fF(total.purchaseIDR)}</span><span class="ms-label">${fUSD(total.purchaseUSD)}</span></div>
        <div class="ms-item"><span class="ms-label">Stok Akhir 1 Jan Berikutnya</span><span class="ms-val" style="color:#7C3AED">${fF(total.stockAkhirIDR)}</span><span class="ms-label">${fUSD(total.stockAkhirUSD)}</span></div>
        <div class="ms-item"><span class="ms-label">Sales Setahun</span><span class="ms-val mi">${fF(total.salesIDR)}</span><span class="ms-label">${fUSD(total.salesUSD)}</span></div>
        <div class="ms-item"><span class="ms-label">Konsumsi Material</span><span class="ms-val" style="color:#EA580C">${fF(total.consumptionIDR)}</span><span class="ms-label">${fUSD(total.consumptionUSD)}</span></div>
        <div class="ms-item"><span class="ms-label">Konsumsi / Sales</span><span class="ms-val" style="color:#0F766E">IDR ${fPct(total.percentageIDR)}</span><span class="ms-label">USD ${fPct(total.percentageUSD)}</span></div>`;

    if (errors && errors.length) showToast('Detail dimuat dengan peringatan validasi stok');
    switchTableTab('bulanan');
}

function switchTableTab(tab) {
    activeTableTab = tab;
    document.getElementById('tabTahunanBtn').classList.toggle('active', tab === 'tahunan');
    document.getElementById('tabBulananBtn').classList.toggle('active', tab === 'bulanan');
    document.getElementById('wrapperTahunan').style.display = tab === 'tahunan' ? 'block' : 'none';
    document.getElementById('wrapperBulanan').style.display = tab === 'bulanan' ? 'block' : 'none';
}

function applyPreset(value) {
    document.querySelectorAll('.preset-btn').forEach(button => button.classList.remove('active'));
    if (window.event && window.event.target) window.event.target.classList.add('active');
    document.getElementById('endYear').value = CURRENT_YEAR;
    document.getElementById('startYear').value = CURRENT_YEAR - value + 1;
    triggerFetchYearly();
}

document.getElementById('closeMonthly').onclick = function() {
    document.getElementById('monthlyPanel').classList.remove('open');
    if (activeTableTab === 'bulanan') switchTableTab('tahunan');
};

document.addEventListener('DOMContentLoaded', () => { initFilters(); triggerFetchYearly(); });

function exportTableExcel() {
    if (!ALL_DATA.length) return;
    const wb = XLSX.utils.book_new();
    const startYear = document.getElementById('startYear').value;
    const endYear = document.getElementById('endYear').value;
    const period = startYear === endYear ? 'Tahun ' + startYear : startYear + ' - ' + endYear;

    if (activeTableTab === 'tahunan') {
        const rows = [
            ['PT. IMC TEKNO INDONESIA'],
            ['LAPORAN STOK AWAL, PEMBELIAN, STOK AKHIR, SALES & KONSUMSI MATERIAL (TAHUNAN)'],
            ['Periode: ' + period], [],
            ['Tahun','Stok Awal (IDR)','Stok Awal (USD)','Pembelian (IDR)','Pembelian (USD)','Stok Akhir (IDR)','Stok Akhir (USD)','Sales (IDR)','Sales (USD)','Konsumsi Material (IDR)','Konsumsi Material (USD)','Konsumsi / Sales (IDR)','Konsumsi / Sales (USD)']
        ];
        ALL_DATA.forEach(row => {
            const metrics = materialMetrics(row);
            rows.push([row.year,Number(row.stock_awal_idr||0),Number(row.stock_awal_usd||0),Number(row.purchase_idr||0),Number(row.purchase_usd||0),Number(row.stock_akhir_idr||0),Number(row.stock_akhir_usd||0),Number(row.sales_idr||0),Number(row.sales_usd||0),metrics.consumptionIDR,metrics.consumptionUSD,metrics.percentageIDR===null?'':metrics.percentageIDR/100,metrics.percentageUSD===null?'':metrics.percentageUSD/100]);
        });
        const total = periodTotals(ALL_DATA);
        rows.push(['TOTAL PERIODE',total.stockAwalIDR,total.stockAwalUSD,total.purchaseIDR,total.purchaseUSD,total.stockAkhirIDR,total.stockAkhirUSD,total.salesIDR,total.salesUSD,total.consumptionIDR,total.consumptionUSD,total.percentageIDR===null?'':total.percentageIDR/100,total.percentageUSD===null?'':total.percentageUSD/100]);
        const ws = XLSX.utils.aoa_to_sheet(rows);
        styleExcelSheet(ws, rows.length, 13, [1,3,5,7,9], [2,4,6,8,10], [11,12]);
        XLSX.utils.book_append_sheet(wb, ws, 'Tahunan');
        XLSX.writeFile(wb, 'Stok_Pembelian_Stok_Akhir_Sales_Konsumsi_Tahunan_' + period.replace(' - ','_') + '.xlsx');
    } else if (selYear && CACHED_MONTHLY[selYear]) {
        const md = CACHED_MONTHLY[selYear].data;
        const rates = CACHED_MONTHLY[selYear].rates;
        const data = monthlyRows(md);
        const rows = [
            ['PT. IMC TEKNO INDONESIA'],
            ['LAPORAN STOK AWAL, PEMBELIAN, STOK AKHIR, SALES & KONSUMSI MATERIAL (BULANAN)'],
            ['Periode: Tahun ' + selYear], [],
            ['Bulan','Kurs Aktif','Stok Awal (IDR)','Stok Awal (USD)','Pembelian (IDR)','Pembelian (USD)','Stok Akhir (IDR)','Stok Akhir (USD)','Sales (IDR)','Sales (USD)','Konsumsi Material (IDR)','Konsumsi Material (USD)','Konsumsi / Sales (IDR)','Konsumsi / Sales (USD)']
        ];
        data.forEach((row,index) => {
            const metrics = materialMetrics(row);
            rows.push([row.month,(rates[index]||[]).join(', ')||'-',row.stock_awal_idr,row.stock_awal_usd,row.purchase_idr,row.purchase_usd,row.stock_akhir_idr,row.stock_akhir_usd,row.sales_idr,row.sales_usd,metrics.consumptionIDR,metrics.consumptionUSD,metrics.percentageIDR===null?'':metrics.percentageIDR/100,metrics.percentageUSD===null?'':metrics.percentageUSD/100]);
        });
        const total = periodTotals(data);
        rows.push(['TOTAL TAHUN','',total.stockAwalIDR,total.stockAwalUSD,total.purchaseIDR,total.purchaseUSD,total.stockAkhirIDR,total.stockAkhirUSD,total.salesIDR,total.salesUSD,total.consumptionIDR,total.consumptionUSD,total.percentageIDR===null?'':total.percentageIDR/100,total.percentageUSD===null?'':total.percentageUSD/100]);
        const ws = XLSX.utils.aoa_to_sheet(rows);
        styleExcelSheet(ws, rows.length, 14, [2,4,6,8,10], [3,5,7,9,11], [12,13]);
        XLSX.utils.book_append_sheet(wb, ws, 'Bulanan ' + selYear);
        XLSX.writeFile(wb, 'Stok_Pembelian_Stok_Akhir_Sales_Konsumsi_Bulanan_' + selYear + '.xlsx');
    }
    showToast('Excel berhasil diunduh');
}

function styleExcelSheet(ws, rowCount, columnCount, idrColumns, usdColumns, percentageColumns) {
    ws['!cols'] = Array.from({length:columnCount}, (_,index) => ({wch:index < 2 ? 22 : 20}));
    ws['!merges'] = [{s:{r:0,c:0},e:{r:0,c:columnCount-1}},{s:{r:1,c:0},e:{r:1,c:columnCount-1}},{s:{r:2,c:0},e:{r:2,c:columnCount-1}}];
    ['A1','A2','A3'].forEach((cell,index) => { if (ws[cell]) ws[cell].s = {font:{bold:index<2,sz:index===0?14:(index===1?12:11)},alignment:{horizontal:'center'}}; });
    for (let column=0; column<columnCount; column++) {
        const cell = ws[XLSX.utils.encode_cell({r:4,c:column})];
        if (cell) cell.s = {font:{bold:true},fill:{fgColor:{rgb:'F3F4F6'}},alignment:{horizontal:'center'}};
    }
    for (let row=5; row<rowCount; row++) {
        idrColumns.forEach(column => { const cell=ws[XLSX.utils.encode_cell({r:row,c:column})]; if(cell) cell.z='Rp #,##0;[Red]-Rp #,##0'; });
        usdColumns.forEach(column => { const cell=ws[XLSX.utils.encode_cell({r:row,c:column})]; if(cell) cell.z='$#,##0.00;[Red]-$#,##0.00'; });
        (percentageColumns || []).forEach(column => { const cell=ws[XLSX.utils.encode_cell({r:row,c:column})]; if(cell) cell.z='0.00%'; });
    }
}

function generatePDF() {
    const element = document.getElementById('mainContainer');
    html2pdf().set({margin:6,filename:'Stok_Pembelian_Stok_Akhir_Sales_Konsumsi_IDR_USD.pdf',image:{type:'jpeg',quality:.98},html2canvas:{scale:2},jsPDF:{unit:'mm',format:'a3',orientation:'landscape'}}).from(element).save();
}
</script>
</body>
</html>