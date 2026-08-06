<?php
/**
 * List Harga Material Konsumsi + HPP Assembling
 * Parent ITEM_CODE 02% mengambil komponen ITTY 01 memakai HPP BOM.
 * Gabung IDR & USD, Total Harga dikonversi ke IDR
 * PHP 5.4 + SQL Server 2008
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ============================================
// UPDATE SESSION JIKA ADA PERUBAHAN DARI DROPDOWN
// ============================================
if (isset($_GET['plant']) && in_array(trim($_GET['plant']), array('p1', 'p2'))) {
    $_SESSION['active_plant'] = trim($_GET['plant']);
}

// ============================================
// LOAD CONFIG DATABASE
// ============================================
$config_path = __DIR__ . '/config/database_aging.php';

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    $config_path2 = __DIR__ . '/../config/database_aging.php';

    if (file_exists($config_path2)) {
        require_once $config_path2;
    } else {
        die('File config database_aging.php tidak ditemukan.');
    }
}

// ============================================
// VARIABEL GET UNTUK FILTER
// ============================================
$filter_part_id   = isset($_GET['part_id']) ? trim($_GET['part_id']) : '';
$filter_itty_code = isset($_GET['itty_code']) ? trim($_GET['itty_code']) : '';
$filter_periode   = isset($_GET['periode']) ? trim($_GET['periode']) : '';
$action           = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// FUNGSI AMBIL DATA MATERIAL HARGA INTERNAL
// ============================================
function getMaterialHarga($filter_part_id = '', $filter_itty_code = '', $filter_periode = '') {
    /*
     * ATURAN HARGA:
     *
     * 1. Parent/part biasa:
     *    - menampilkan material ITTY 02 dan 03 dari BOM_DEFAULT.
     *
     * 2. Parent assembling dengan ITEM_CODE LIKE '02%':
     *    - menampilkan material ITTY 01, 02 dan 03.
     *    - ITTY 01 memakai HPP item tersebut yang dihitung dari BOM turunannya.
     *
     * 3. Rumus:
     *    - ITTY 01 = BOM_QTY x HPP item ITTY 01.
     *    - ITTY 02 = BOM_QTY x harga PO IDR / 1000.
     *    - ITTY 03 = BOM_QTY x harga PO IDR.
     *
     * 4. HPP ITTY 01 dihitung recursive maksimal 20 level sampai material
     *    daun ITTY 02/03. Harga daun memakai PO terakhir dan kurs sesuai PO_DATE.
     *
     * Kompatibel SQL Server 2008 dan PHP 5.4.
     */

    $sql = "
        ;WITH LatestPO AS
        (
            SELECT
                pd.ITEM_ID AS MAT_ID,
                pd.POD_PRICE,
                po.PO_DATE AS PRICE_DATE_RAW,
                pd.POD_UNIT,
                po.PO_CUR,
                ROW_NUMBER() OVER
                (
                    PARTITION BY pd.ITEM_ID
                    ORDER BY po.PO_DATE DESC, po.PO_ID DESC
                ) AS rn
            FROM PO_DETAIL pd
            INNER JOIN PO po
                ON pd.PO_ID = po.PO_ID
        ),
        PriceData AS
        (
            SELECT
                p.MAT_ID,
                p.POD_PRICE,
                p.PRICE_DATE_RAW,
                p.POD_UNIT,
                p.PO_CUR,
                c.CURR_VRATE,

                CAST
                (
                    CASE
                        WHEN p.POD_PRICE IS NULL THEN NULL
                        WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR'
                            THEN p.POD_PRICE
                        WHEN c.CURR_VRATE IS NULL THEN NULL
                        ELSE p.POD_PRICE * c.CURR_VRATE
                    END
                    AS DECIMAL(38, 8)
                ) AS PRICE_IDR,

                CASE
                    WHEN p.POD_PRICE IS NULL THEN 'TANPA_HARGA'
                    WHEN ISNULL(p.PO_CUR, 'IDR') <> 'IDR'
                         AND c.CURR_VRATE IS NULL THEN 'TANPA_KURS'
                    ELSE 'OK'
                END AS PRICE_STATUS

            FROM LatestPO p
            LEFT JOIN CURR_RAT c
                ON p.PO_CUR = c.CURR_CODE
               AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND c.CURR_EDATE
            WHERE p.rn = 1
        ),
        HppBomTree AS
        (
            /* Level pertama BOM untuk seluruh item ITTY 01. */
            SELECT
                bd.PART_ID AS ROOT_PART_ID,
                bd.ITEM_ID AS COMPONENT_ID,
                m.ITTY_CODE,
                CAST(bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY,
                CAST
                (
                    '/' + CONVERT(VARCHAR(50), bd.PART_ID)
                    + '/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/'
                    AS VARCHAR(MAX)
                ) AS BOM_PATH,
                1 AS BOM_LEVEL

            FROM BOM_DEFAULT bd
            INNER JOIN ITEMS root_item
                ON bd.PART_ID = root_item.ITEM_ID
            INNER JOIN ITEMS m
                ON bd.ITEM_ID = m.ITEM_ID

            WHERE root_item.ITTY_CODE = '01'
              AND root_item.ITEM_INACTIVE = 0
              AND m.ITEM_INACTIVE = 0

            UNION ALL

            /* Turunkan kembali bila komponen masih ITTY 01. */
            SELECT
                bt.ROOT_PART_ID,
                bd.ITEM_ID AS COMPONENT_ID,
                m.ITTY_CODE,
                CAST(bt.TOTAL_QTY * bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY,
                CAST
                (
                    bt.BOM_PATH + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/'
                    AS VARCHAR(MAX)
                ) AS BOM_PATH,
                bt.BOM_LEVEL + 1

            FROM HppBomTree bt
            INNER JOIN ITEMS current_item
                ON bt.COMPONENT_ID = current_item.ITEM_ID
               AND current_item.ITTY_CODE = '01'
            INNER JOIN BOM_DEFAULT bd
                ON bt.COMPONENT_ID = bd.PART_ID
            INNER JOIN ITEMS m
                ON bd.ITEM_ID = m.ITEM_ID

            WHERE bt.BOM_LEVEL < 20
              AND current_item.ITEM_INACTIVE = 0
              AND m.ITEM_INACTIVE = 0
              AND bt.BOM_PATH NOT LIKE
                  '%/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/%'
        ),
        Hpp01Summary AS
        (
            SELECT
                bt.ROOT_PART_ID,

                CAST
                (
                    SUM
                    (
                        CASE
                            WHEN bt.ITTY_CODE = '02' THEN
                                ROUND
                                (
                                    (bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0,
                                    2
                                )

                            WHEN bt.ITTY_CODE = '03' THEN
                                ROUND
                                (
                                    bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0),
                                    2
                                )

                            ELSE 0
                        END
                    )
                    AS DECIMAL(38, 8)
                ) AS HPP_IDR_PER_UNIT,

                MAX(pd.PRICE_DATE_RAW) AS HPP_DATE_RAW,

                SUM
                (
                    CASE
                        WHEN bt.ITTY_CODE IN ('02', '03') THEN 1
                        ELSE 0
                    END
                ) AS LEAF_COUNT,

                SUM
                (
                    CASE
                        WHEN bt.ITTY_CODE IN ('02', '03')
                             AND
                             (
                                 pd.MAT_ID IS NULL
                                 OR pd.PRICE_STATUS <> 'OK'
                             )
                            THEN 1
                        ELSE 0
                    END
                ) AS MISSING_PRICE_COUNT

            FROM HppBomTree bt
            LEFT JOIN PriceData pd
                ON bt.COMPONENT_ID = pd.MAT_ID

            WHERE bt.ITTY_CODE IN ('02', '03')
            GROUP BY bt.ROOT_PART_ID
        ),
        DirectBom AS
        (
            SELECT
                i.ITEM_ID AS PART_ID,
                i.ITEM_CODE AS PART_CODE,
                i.ITEM_NAME AS PART_NAME,

                m.ITEM_ID AS MAT_ID,
                m.ITEM_CODE AS MAT_CODE,
                m.ITEM_NAME AS MAT_NAME,

                CAST(bd.QTY AS DECIMAL(38, 8)) AS QTY,
                m.ITTY_CODE

            FROM BOM_DEFAULT bd
            INNER JOIN ITEMS m
                ON bd.ITEM_ID = m.ITEM_ID
            INNER JOIN ITEMS i
                ON bd.PART_ID = i.ITEM_ID

            WHERE
                (
                    m.ITTY_CODE IN ('02', '03')
                    OR
                    (
                        i.ITEM_CODE LIKE '02%'
                        AND m.ITTY_CODE = '01'
                    )
                )
              AND i.ITEM_INACTIVE = 0
              AND m.ITEM_INACTIVE = 0
        )

        SELECT
            x.PART_ID,
            x.PART_CODE,
            x.PART_NAME,
            x.MAT_ID,
            x.MAT_CODE,
            x.MAT_NAME,
            x.BOM_QTY,
            x.ITTY_CODE,
            x.SATUAN_HITUNG,
            x.HARGA_PO,
            CONVERT(VARCHAR(10), x.PRICE_DATE_RAW, 23) AS TGL_PO,
            x.SATUAN_PO,
            x.MATA_UANG,
            x.KURS_VRATE,
            x.TOTAL_HARGA,
            x.STATUS_HARGA,
            x.SUMBER_HARGA

        FROM
        (
            SELECT
                b.PART_ID,
                b.PART_CODE,
                b.PART_NAME,
                b.MAT_ID,
                b.MAT_CODE,
                b.MAT_NAME,
                b.QTY AS BOM_QTY,
                b.ITTY_CODE,

                CASE b.ITTY_CODE
                    WHEN '01' THEN 'Pcs x HPP'
                    WHEN '02' THEN 'Kg (/1000)'
                    WHEN '03' THEN 'Pcs'
                    ELSE b.ITTY_CODE
                END AS SATUAN_HITUNG,

                CAST
                (
                    CASE
                        WHEN b.ITTY_CODE = '01'
                            THEN ISNULL(h.HPP_IDR_PER_UNIT, 0)
                        ELSE ISNULL(pd.POD_PRICE, 0)
                    END
                    AS DECIMAL(38, 8)
                ) AS HARGA_PO,

                CASE
                    WHEN b.ITTY_CODE = '01'
                        THEN h.HPP_DATE_RAW
                    ELSE pd.PRICE_DATE_RAW
                END AS PRICE_DATE_RAW,

                CASE
                    WHEN b.ITTY_CODE = '01'
                        THEN 'HPP/Pcs'
                    ELSE pd.POD_UNIT
                END AS SATUAN_PO,

                CASE
                    WHEN b.ITTY_CODE = '01'
                        THEN 'IDR'
                    ELSE ISNULL(pd.PO_CUR, '-')
                END AS MATA_UANG,

                CASE
                    WHEN b.ITTY_CODE = '01'
                        THEN NULL
                    ELSE pd.CURR_VRATE
                END AS KURS_VRATE,

                CAST
                (
                    CASE
                        WHEN b.ITTY_CODE = '01' THEN
                            ROUND
                            (
                                b.QTY * ISNULL(h.HPP_IDR_PER_UNIT, 0),
                                2
                            )

                        WHEN b.ITTY_CODE = '02' THEN
                            ROUND
                            (
                                (b.QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0,
                                2
                            )

                        WHEN b.ITTY_CODE = '03' THEN
                            ROUND
                            (
                                b.QTY * ISNULL(pd.PRICE_IDR, 0),
                                2
                            )

                        ELSE 0
                    END
                    AS DECIMAL(38, 2)
                ) AS TOTAL_HARGA,

                CASE
                    WHEN b.ITTY_CODE = '01'
                         AND
                         (
                             h.ROOT_PART_ID IS NULL
                             OR ISNULL(h.LEAF_COUNT, 0) = 0
                         )
                        THEN 'TANPA_HPP'

                    WHEN b.ITTY_CODE = '01'
                         AND ISNULL(h.MISSING_PRICE_COUNT, 0) > 0
                        THEN 'HPP_TIDAK_LENGKAP'

                    WHEN b.ITTY_CODE = '01'
                        THEN 'OK'

                    WHEN pd.MAT_ID IS NULL
                        THEN 'TANPA_HARGA'

                    ELSE pd.PRICE_STATUS
                END AS STATUS_HARGA,

                CASE
                    WHEN b.ITTY_CODE = '01'
                        THEN 'HPP BOM ITTY 01'
                    ELSE 'PO TERAKHIR'
                END AS SUMBER_HARGA

            FROM DirectBom b
            LEFT JOIN PriceData pd
                ON b.MAT_ID = pd.MAT_ID
            LEFT JOIN Hpp01Summary h
                ON b.MAT_ID = h.ROOT_PART_ID
        ) x

        WHERE 1 = 1
    ";

    $params = array();

    if ($filter_part_id != '') {
        $sql .= " AND x.PART_ID = ?";
        $params[] = $filter_part_id;
    }

    if ($filter_itty_code != '') {
        $sql .= " AND x.ITTY_CODE = ?";
        $params[] = $filter_itty_code;
    }

    if ($filter_periode != '') {
        /*
         * ITTY 01 memakai tanggal PO terbaru dari material daun pembentuk HPP.
         * ITTY 02/03 memakai tanggal PO material langsung.
         */
        $sql .= " AND CONVERT(VARCHAR(10), x.PRICE_DATE_RAW, 23) LIKE ?";
        $params[] = $filter_periode . '%';
    }

    $sql .= "
        ORDER BY
            x.PART_CODE,
            CASE x.ITTY_CODE
                WHEN '01' THEN 1
                WHEN '02' THEN 2
                WHEN '03' THEN 3
                ELSE 9
            END,
            x.MAT_CODE
        OPTION (MAXRECURSION 100)
    ";

    $stmt = q($sql, $params);

    $data = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}

// ============================================
// FUNGSI AMBIL DAFTAR PART INTERNAL
// ============================================
function getDaftarPart() {
    $sql = "
        SELECT DISTINCT
            i.ITEM_ID AS PART_ID,
            i.ITEM_CODE AS PART_CODE,
            i.ITEM_NAME AS PART_NAME

        FROM BOM_DEFAULT bd
        INNER JOIN ITEMS m
            ON bd.ITEM_ID = m.ITEM_ID
        INNER JOIN ITEMS i
            ON bd.PART_ID = i.ITEM_ID

        WHERE
            (
                m.ITTY_CODE IN ('02', '03')
                OR
                (
                    i.ITEM_CODE LIKE '02%'
                    AND m.ITTY_CODE = '01'
                )
            )
          AND i.ITEM_INACTIVE = 0
          AND m.ITEM_INACTIVE = 0

        ORDER BY i.ITEM_CODE
    ";

    $stmt = q($sql);

    $data = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}

// ============================================
// PROSES AJAX REQUEST
// ============================================
if ($action == 'get_data') {
    header('Content-Type: application/json; charset=utf-8');

    $data = getMaterialHarga(
        $filter_part_id,
        $filter_itty_code,
        $filter_periode
    );

    echo json_encode($data);
    exit;
}

// ============================================
// AMBIL DATA AWAL & HITUNG TOTAL
// ============================================
$daftar_part = getDaftarPart();

$data_material = getMaterialHarga(
    $filter_part_id,
    $filter_itty_code,
    $filter_periode
);

$total_harga_idr = 0;
$jml_tanpa_harga = 0;
$jml_tanpa_kurs = 0;
$jml_tanpa_hpp = 0;
$jml_hpp_tidak_lengkap = 0;
$jml_itty01 = 0;
$jml_itty02 = 0;
$jml_itty03 = 0;
$jml_usd = 0;

foreach ($data_material as $row) {
    $total_harga_idr += (float)$row['TOTAL_HARGA'];

    if ($row['STATUS_HARGA'] == 'TANPA_HARGA') $jml_tanpa_harga++;
    if ($row['STATUS_HARGA'] == 'TANPA_KURS') $jml_tanpa_kurs++;
    if ($row['STATUS_HARGA'] == 'TANPA_HPP') $jml_tanpa_hpp++;
    if ($row['STATUS_HARGA'] == 'HPP_TIDAK_LENGKAP') $jml_hpp_tidak_lengkap++;
    if ($row['ITTY_CODE'] == '01') $jml_itty01++;
    if ($row['ITTY_CODE'] == '02') $jml_itty02++;
    if ($row['ITTY_CODE'] == '03') $jml_itty03++;
    if ($row['MATA_UANG'] == 'USD') $jml_usd++;
}

$jml_error_harga = $jml_tanpa_harga
    + $jml_tanpa_kurs
    + $jml_tanpa_hpp
    + $jml_hpp_tidak_lengkap;

$parts_unique = array();

foreach ($data_material as $row) {
    $parts_unique[$row['PART_ID']] = true;
}

$jml_part = count($parts_unique);

// ============================================
// HITUNG HARGA MINIMUM & MAKSIMUM PER PART
// Dasar: subtotal TOTAL_HARGA IDR setiap PART
// Nilai 0 diabaikan.
// ============================================
$subtotal_per_part = array();

foreach ($data_material as $row) {
    $part_key = (string)$row['PART_ID'];

    if (!isset($subtotal_per_part[$part_key])) {
        $subtotal_per_part[$part_key] = array(
            'PART_CODE' => $row['PART_CODE'],
            'PART_NAME' => $row['PART_NAME'],
            'TOTAL'     => 0
        );
    }

    $subtotal_per_part[$part_key]['TOTAL'] += (float)$row['TOTAL_HARGA'];
}

$harga_min_idr = null;
$harga_max_idr = null;

$part_min_code = '-';
$part_min_name = '';

$part_max_code = '-';
$part_max_name = '';

foreach ($subtotal_per_part as $part_summary) {
    $nilai = (float)$part_summary['TOTAL'];

    if ($nilai <= 0) {
        continue;
    }

    if ($harga_min_idr === null || $nilai < $harga_min_idr) {
        $harga_min_idr = $nilai;
        $part_min_code = $part_summary['PART_CODE'];
        $part_min_name = $part_summary['PART_NAME'];
    }

    if ($harga_max_idr === null || $nilai > $harga_max_idr) {
        $harga_max_idr = $nilai;
        $part_max_code = $part_summary['PART_CODE'];
        $part_max_name = $part_summary['PART_NAME'];
    }
}

// ============================================
// INFO PLANT
// ============================================
$current_plant_val = isset($_SESSION['active_plant'])
    ? $_SESSION['active_plant']
    : 'p1';

$plantDisplay = ($current_plant_val == 'p1')
    ? 'Plant 1'
    : 'Plant 2';

$serverIpDisplay = isset($serverName)
    ? $serverName
    : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>List Harga Material + HPP Assembling - PT. IMC TEKNO INDONESIA</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            padding: 20px 30px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 22px;
        }

        .header p {
            opacity: 0.8;
            font-size: 13px;
            margin-top: 3px;
        }

        .header-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .price-range-header {
            display: flex;
            align-items: stretch;
            gap: 8px;
            flex-wrap: wrap;
        }

        .price-range-item {
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            padding: 7px 10px;
            min-width: 150px;
        }

        .price-range-label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.78;
            margin-bottom: 2px;
        }

        .price-range-value {
            display: block;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .price-range-part {
            display: block;
            font-size: 9px;
            opacity: 0.82;
            margin-top: 2px;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .plant-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .filter-box {
            background: white;
            padding: 20px 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .filter-group select,
        .filter-group input {
            padding: 9px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            min-width: 200px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #1a237e;
        }

        .select-server {
            border-color: #f57c00 !important;
            background-color: #fff3e0;
            font-weight: bold;
            color: #e65100;
        }

        .select-server:focus {
            border-color: #e65100 !important;
        }

        .btn {
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #1a237e;
            color: white;
        }

        .btn-primary:hover {
            background: #283593;
        }

        .btn-warning {
            background: #f57c00;
            color: white;
        }

        .btn-warning:hover {
            background: #ef6c00;
        }

        .formula-box {
            background: #fffde7;
            border: 1px solid #fff9c4;
            padding: 12px 20px;
        }

        .formula-box h3 {
            color: #f57f17;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .formula-item {
            display: inline-block;
            margin-right: 25px;
            font-size: 12px;
        }

        .stats-box {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin: 15px 0;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid;
        }

        .stat-card.c1 { border-left-color: #1a237e; }
        .stat-card.c2 { border-left-color: #1565c0; }
        .stat-card.c3 { border-left-color: #2e7d32; }
        .stat-card.c4 { border-left-color: #f57c00; }
        .stat-card.c5 { border-left-color: #6a1b9a; }
        .stat-card.c6 { border-left-color: #c62828; }

        .stat-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            margin-top: 3px;
        }

        .stat-card.c1 .stat-value { color: #1a237e; }
        .stat-card.c2 .stat-value { color: #1565c0; }
        .stat-card.c3 .stat-value { color: #2e7d32; }
        .stat-card.c4 .stat-value { color: #f57c00; }
        .stat-card.c5 .stat-value { color: #6a1b9a; }
        .stat-card.c6 .stat-value { color: #c62828; }

        .table-container {
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        thead {
            background: #37474f;
            color: white;
        }

        thead th {
            padding: 11px 10px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #eee;
        }

        tbody tr:hover {
            background: #f5f5f5;
        }

        tbody td {
            padding: 9px 10px;
            vertical-align: middle;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: 700; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-01 {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-02 {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-03 {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-usd {
            background: #e8eaf6;
            color: #283593;
            font-weight: 700;
        }

        .badge-idr {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .badge-error {
            background: #ffebee;
            color: #c62828;
        }

        .badge-warning {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .part-header {
            background: #e8eaf6 !important;
        }

        .part-header td {
            padding: 10px 12px !important;
            color: #1a237e;
            font-weight: 700;
            font-size: 13px;
        }

        .subtotal-row {
            background: #f5f5f5 !important;
        }

        .subtotal-row td {
            padding: 8px 10px !important;
        }

        .grand-total {
            background: #1a237e !important;
            color: white !important;
            font-weight: 700;
        }

        .grand-total td {
            padding: 14px 10px !important;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            color: #999;
            font-size: 11px;
        }

        @media (max-width: 900px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .header-right {
                width: 100%;
                justify-content: flex-start;
            }

            .price-range-header {
                width: 100%;
            }

            .price-range-item {
                flex: 1;
                min-width: 140px;
            }

            .stats-box {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group select,
            .filter-group input {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>📦 List Harga Material + HPP Assembling (IDR)</h1>
            <p>PT. IMC TEKNO INDONESIA | Di-trace System | BOM + HPP ITTY 01</p>
        </div>

        <div class="header-right">
            <div class="price-range-header">

                <div
                    class="price-range-item"
                    title="Harga minimum berdasarkan subtotal per part dari data yang sedang difilter"
                >
                    <span class="price-range-label">
                        ▼ Harga Minimum / Part
                    </span>

                    <span class="price-range-value">
                        <?php
                        echo ($harga_min_idr !== null)
                            ? 'Rp ' . number_format($harga_min_idr, 2)
                            : '-';
                        ?>
                    </span>

                    <span class="price-range-part">
                        <?php
                        echo ($harga_min_idr !== null)
                            ? htmlspecialchars($part_min_code . ' - ' . $part_min_name)
                            : 'Tidak ada harga valid';
                        ?>
                    </span>
                </div>

                <div
                    class="price-range-item"
                    title="Harga maksimum berdasarkan subtotal per part dari data yang sedang difilter"
                >
                    <span class="price-range-label">
                        ▲ Harga Maksimum / Part
                    </span>

                    <span class="price-range-value">
                        <?php
                        echo ($harga_max_idr !== null)
                            ? 'Rp ' . number_format($harga_max_idr, 2)
                            : '-';
                        ?>
                    </span>

                    <span class="price-range-part">
                        <?php
                        echo ($harga_max_idr !== null)
                            ? htmlspecialchars($part_max_code . ' - ' . $part_max_name)
                            : 'Tidak ada harga valid';
                        ?>
                    </span>
                </div>

            </div>

            <div class="plant-badge">
                🏭 Plant:
                <?php echo $plantDisplay; ?>
                (<?php echo $serverIpDisplay; ?>)
            </div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET" action="">
            <div class="filter-row">

                <div class="filter-group">
                    <label>Pilih Server</label>

                    <select
                        name="plant"
                        id="plant"
                        class="select-server"
                        onchange="this.form.submit()"
                    >
                        <option
                            value="p1"
                            <?php echo ($current_plant_val == 'p1') ? 'selected' : ''; ?>
                        >
                            🏭 Plant 1 (192.168.0.4)
                        </option>

                        <option
                            value="p2"
                            <?php echo ($current_plant_val == 'p2') ? 'selected' : ''; ?>
                        >
                            🏭 Plant 2 (192.168.0.9)
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Pilih Part (Aktif)</label>

                    <select name="part_id" id="part_id">
                        <option value="">
                            -- Semua Part Aktif --
                        </option>

                        <?php foreach ($daftar_part as $part): ?>
                            <option
                                value="<?php echo htmlspecialchars($part['PART_ID']); ?>"
                                <?php
                                echo ($filter_part_id == $part['PART_ID'])
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php
                                echo htmlspecialchars(
                                    $part['PART_CODE'] . ' - ' . $part['PART_NAME']
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Tipe Material</label>

                    <select name="itty_code" id="itty_code">
                        <option value="">
                            -- Semua Tipe --
                        </option>

                        <option
                            value="01"
                            <?php echo ($filter_itty_code == '01') ? 'selected' : ''; ?>
                        >
                            01 - HPP BOM (Assembling 02%)
                        </option>

                        <option
                            value="02"
                            <?php echo ($filter_itty_code == '02') ? 'selected' : ''; ?>
                        >
                            02 - Kg (÷1000)
                        </option>

                        <option
                            value="03"
                            <?php echo ($filter_itty_code == '03') ? 'selected' : ''; ?>
                        >
                            03 - Pcs
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Periode Harga (YYYY-MM)</label>

                    <input
                        type="text"
                        name="periode"
                        id="periode"
                        value="<?php echo htmlspecialchars($filter_periode); ?>"
                        placeholder="2026-07"
                    >
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>

                    <div>
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            🔍 Cari
                        </button>

                        <a
                            href="?"
                            class="btn btn-warning"
                            style="margin-left:5px;"
                        >
                            ✖ Reset
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <div class="formula-box">
        <h3>
            📐 Rumus BOM + HPP Assembling
            (USD Otomatis ke IDR)
        </h3>

        <div class="formula-item">
            <span class="badge badge-01">ITTY 01</span>
            = BOM_QTY × HPP item 01 (HPP dihitung recursive dari BOM 02/03)
        </div>

        <div class="formula-item">
            <span class="badge badge-02">ITTY 02</span>
            = BOM_QTY × (Harga_PO × Kurs) / 1000
        </div>

        <div class="formula-item">
            <span class="badge badge-03">ITTY 03</span>
            = BOM_QTY × (Harga_PO × Kurs)
        </div>

        <div
            class="formula-item"
            style="margin-left:20px; color:#283593; font-weight:600;"
        >
            💱 USD → IDR = Harga × VRATE (dari CURR_RAT)
        </div>
    </div>

    <div class="stats-box">

        <div class="stat-card c1">
            <div class="stat-label">Total Material</div>
            <div class="stat-value"><?php echo count($data_material); ?></div>
        </div>

        <div class="stat-card c2">
            <div class="stat-label">Jumlah Part Aktif</div>
            <div class="stat-value"><?php echo $jml_part; ?></div>
        </div>

        <div class="stat-card c3">
            <div class="stat-label">Grand Total (IDR)</div>
            <div class="stat-value" style="font-size:15px;">
                <?php echo number_format($total_harga_idr, 2); ?>
            </div>
        </div>

        <div class="stat-card c4">
            <div class="stat-label">Material USD</div>
            <div class="stat-value"><?php echo $jml_usd; ?></div>
        </div>

        <div class="stat-card c5">
            <div class="stat-label">ITTY 01 / HPP</div>
            <div class="stat-value"><?php echo $jml_itty01; ?></div>
        </div>

        <div class="stat-card c6">
            <div class="stat-label">⚠ Error Harga/HPP/Kurs</div>
            <div class="stat-value"><?php echo $jml_error_harga; ?></div>
        </div>

    </div>

    <div class="table-container">
        <table id="dataTable">

            <thead>
            <tr>
                <th
                    class="text-center"
                    style="width:35px;"
                >
                    No
                </th>

                <th>Part Code</th>
                <th>Material Code</th>
                <th>Material Name</th>
                <th class="text-center">ITTY</th>
                <th class="text-right">BOM QTY</th>
                <th class="text-right">Harga Dasar</th>
                <th class="text-center">Curr</th>
                <th class="text-right">Kurs (VRATE)</th>
                <th>Tgl Harga/HPP</th>
                <th>Unit</th>
                <th class="text-right">Total Harga (IDR)</th>
                <th class="text-center">Status</th>
            </tr>
            </thead>

            <tbody>
            <?php
            if (count($data_material) > 0):

                $no = 1;
                $current_part = '';
                $subtotal_part = 0;

                foreach ($data_material as $row):
            ?>

                <?php
                if ($current_part != $row['PART_CODE']):

                    if ($current_part != ''):
                ?>
                    <tr class="subtotal-row">
                        <td
                            colspan="11"
                            class="text-right text-bold"
                        >
                            Subtotal
                            <?php echo htmlspecialchars($current_part); ?>
                        </td>

                        <td class="text-right text-bold">
                            <?php echo number_format($subtotal_part, 2); ?>
                        </td>

                        <td></td>
                    </tr>
                <?php
                    endif;

                    $subtotal_part = 0;
                ?>

                    <tr class="part-header">
                        <td colspan="13">
                            📦
                            <?php echo htmlspecialchars($row['PART_CODE']); ?>
                            -
                            <?php echo htmlspecialchars($row['PART_NAME']); ?>
                        </td>
                    </tr>

                <?php
                    $current_part = $row['PART_CODE'];
                    // Reset nomor detail setiap masuk group/part baru
                    $no = 1;
                endif;
                ?>

                <?php
                $subtotal_part += (float)$row['TOTAL_HARGA'];
                ?>

                <tr>
                    <td class="text-center">
                        <?php echo $no++; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['PART_CODE']); ?>
                    </td>

                    <td>
                        <strong>
                            <?php echo htmlspecialchars($row['MAT_CODE']); ?>
                        </strong>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['MAT_NAME']); ?>
                    </td>

                    <td class="text-center">
                        <span
                            class="badge badge-<?php echo htmlspecialchars($row['ITTY_CODE']); ?>"
                        >
                            <?php echo htmlspecialchars($row['ITTY_CODE']); ?>
                        </span>
                    </td>

                    <td class="text-right">
                        <?php echo number_format((float)$row['BOM_QTY'], 4); ?>
                    </td>

                    <td class="text-right">
                        <?php echo number_format((float)$row['HARGA_PO'], 4); ?>
                    </td>

                    <td class="text-center">
                        <?php if ($row['MATA_UANG'] != '-'): ?>
                            <span
                                class="badge badge-<?php echo strtolower(htmlspecialchars($row['MATA_UANG'])); ?>"
                            >
                                <?php echo htmlspecialchars($row['MATA_UANG']); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td class="text-right">
                        <?php
                        if (
                            $row['MATA_UANG'] == 'IDR' ||
                            $row['MATA_UANG'] == '-'
                        ) {
                            echo '-';
                        } else {
                            if ($row['KURS_VRATE']) {
                                echo number_format((float)$row['KURS_VRATE'], 2);
                            } else {
                                echo '<span style="color:red; font-weight:600;">0</span>';
                            }
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $row['TGL_PO']
                            ? htmlspecialchars($row['TGL_PO'])
                            : '-';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $row['SATUAN_PO']
                            ? htmlspecialchars($row['SATUAN_PO'])
                            : '-';
                        ?>
                    </td>

                    <td
                        class="text-right text-bold"
                        style="color:#1a237e;"
                    >
                        <?php
                        echo number_format((float)$row['TOTAL_HARGA'], 2);
                        ?>
                    </td>

                    <td class="text-center">
                        <?php
                        if ($row['STATUS_HARGA'] == 'TANPA_HPP'):
                        ?>
                            <span class="badge badge-error">
                                ⚠ Tanpa HPP
                            </span>

                        <?php
                        elseif ($row['STATUS_HARGA'] == 'HPP_TIDAK_LENGKAP'):
                        ?>
                            <span class="badge badge-warning">
                                ⚠ HPP Tidak Lengkap
                            </span>

                        <?php
                        elseif ($row['STATUS_HARGA'] == 'TANPA_HARGA'):
                        ?>
                            <span class="badge badge-error">
                                ⚠ Tanpa Harga
                            </span>

                        <?php
                        elseif ($row['STATUS_HARGA'] == 'TANPA_KURS'):
                        ?>
                            <span class="badge badge-warning">
                                ⚠ Tanpa Kurs
                            </span>

                        <?php
                        else:
                        ?>
                            <span class="badge badge-success">
                                ✓ OK
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>

                <tr class="subtotal-row">
                    <td
                        colspan="11"
                        class="text-right text-bold"
                    >
                        Subtotal
                        <?php echo htmlspecialchars($current_part); ?>
                    </td>

                    <td class="text-right text-bold">
                        <?php echo number_format($subtotal_part, 2); ?>
                    </td>

                    <td></td>
                </tr>

                <tr class="grand-total">
                    <td
                        colspan="11"
                        class="text-right"
                        style="font-size:14px;"
                    >
                        GRAND TOTAL (IDR)
                    </td>

                    <td
                        class="text-right"
                        style="font-size:16px;"
                    >
                        <?php echo number_format($total_harga_idr, 2); ?>
                    </td>

                    <td></td>
                </tr>

            <?php else: ?>

                <tr>
                    <td
                        colspan="13"
                        class="no-data"
                    >
                        📭 Tidak ada data material konsumsi ditemukan
                    </td>
                </tr>

            <?php endif; ?>
            </tbody>

        </table>
    </div>

    <div class="footer">
        <p>
            © 2026 Di-trace System |
            Database: msdata |
            Plant: <?php echo $plantDisplay; ?>
            (<?php echo $serverIpDisplay; ?>) |
            Assembling ITEM_CODE 02% memakai HPP komponen ITTY 01;
            currency otomatis dikonversi ke IDR menggunakan VRATE
        </p>
    </div>

</div>
</body>
</html>

<?php
if (isset($conn) && $conn) {
    sqlsrv_close($conn);
}
?>
