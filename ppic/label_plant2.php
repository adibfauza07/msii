<?php
// /msii/ppic/label_plant2.php
// PHP 5.4 + SQL Server
// AUTO FIT + EDIT PER WO + UPDATE MASTER STD_PACK
// MATERIAL DROPDOWN DARI BOM_DEFAULT PER WO
// MAKER AUTOCOMPLETE DARI Mat_Maker
//
// Relasi material:
// BOM_DEFAULT.PART_ID = WO.ITEM_ID
// BOM_DEFAULT.ITEM_ID = ITEMS.ITEM_ID
//
// Simpan:
// 1. LABEL_WO_SETTING berdasarkan WO_NUMBER + ITEM_CODE
// 2. STD_PACK berdasarkan ITEM_CODE unik
// 3. Mat_Maker berdasarkan MAT_CODE



require_once "../config/db_plant2.php";

function h_label($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmt_date_label($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v === null || $v === '') {
        return '';
    }

    return (string)$v;
}

function int_post_label($v) {
    $v = str_replace(',', '', (string)$v);
    $v = trim($v);

    if ($v === '') {
        return 0;
    }

    return (int)$v;
}

function get_label_types_label_page() {
    return array(
        'STD_PACK' => array('nama' => 'STANDAR - PACK', 'mode' => 'BAG'),
        'STD_BOX'  => array('nama' => 'STANDAR - BOX',  'mode' => 'BOX'),

        'STD_INITIAL_LH_PACK' => array('nama' => 'STANDAR INITIAL/LH/RL/CAB - PACK', 'mode' => 'BAG'),
        'STD_INITIAL_LH_BOX'  => array('nama' => 'STANDAR INITIAL/LH/RL/CAB - BOX',  'mode' => 'BOX'),

        'STD_PAKAI_UL_PACK' => array('nama' => 'STANDAR PAKAI UL - PACK', 'mode' => 'BAG'),
        'STD_PAKAI_UL_BOX'  => array('nama' => 'STANDAR PAKAI UL - BOX',  'mode' => 'BOX'),

       

        'SUZUKI_PACK' => array('nama' => 'SUZUKI - PACK', 'mode' => 'BAG'),
        'SUZUKI_BOX'  => array('nama' => 'SUZUKI - BOX',  'mode' => 'BOX'),

        'AUTOTECH_PACK' => array('nama' => 'AUTOTECH - BOX', 'mode' => 'BOX'),

        'KOITO_PACK' => array('nama' => 'KOITO - PACK', 'mode' => 'BAG'),
        'KOITO_BOX'  => array('nama' => 'KOITO - BOX',  'mode' => 'BOX'),

        'AUTOLIV_PACK' => array('nama' => 'AUTOLIV - PACK', 'mode' => 'BAG'),

       
        'SANKEIKID_PACK' => array('nama' => 'SANKEIKID - PACK', 'mode' => 'BAG'),
        'SANKEIKID_BOX'  => array('nama' => 'SANKEIKID - BOX',  'mode' => 'BOX'),

        'SANKEIKID_SIM_PACK' => array('nama' => 'SANKEIKID SIM - PACK', 'mode' => 'BAG'),
        'SANKEIKID_SIM_BOX'  => array('nama' => 'SANKEIKID SIM - BOX',  'mode' => 'BOX'),

        'STANLEY_PACK' => array('nama' => 'STANLEY - PACK', 'mode' => 'BAG'),
        'STANLEY_BOX'  => array('nama' => 'STANLEY - BOX',  'mode' => 'BOX'),

        'STANLEY_800_PACK' => array('nama' => 'STANLEY 800 - PACK', 'mode' => 'BAG'),
        'STANLEY_800_BOX'  => array('nama' => 'STANLEY 800 - BOX', 'mode' => 'BOX'),

        'KATSUYAMA_BOX' => array('nama' => 'KATSUYAMA - BOX', 'mode' => 'BOX'),

        'SIIX_PACK' => array('nama' => 'SIIX - PACK', 'mode' => 'BAG'),
        'SIIX_BOX'  => array('nama' => 'SIIX - BOX',  'mode' => 'BOX'),

        'HIROSE_PACK' => array('nama' => 'HIROSE - PACK', 'mode' => 'BAG'),
        'HIROSE_BOX'  => array('nama' => 'HIROSE - BOX',  'mode' => 'BOX'),

        'YAZAKI_BOX' => array('nama' => 'YAZAKI - BOX', 'mode' => 'BOX')
    );
}

$labelTypes = get_label_types_label_page();

$bulan = isset($_REQUEST['bulan']) ? (int)$_REQUEST['bulan'] : (int)date('m');
$tahun = isset($_REQUEST['tahun']) ? (int)$_REQUEST['tahun'] : (int)date('Y');
$cari  = isset($_REQUEST['cari']) ? trim($_REQUEST['cari']) : '';

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

/* ======================================================
   SAVE / UPDATE PER WO + MASTER STD_PACK
   ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'save_wo_setting') {
    $woNumber = isset($_POST['wo_number']) ? trim($_POST['wo_number']) : '';
    $itemCode = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
    $itemId   = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    $stdPack  = isset($_POST['std_pack']) ? int_post_label($_POST['std_pack']) : 0;
    $stdBox   = isset($_POST['std_box']) ? int_post_label($_POST['std_box']) : 0;
    $typeLot  = isset($_POST['type_lot']) ? trim($_POST['type_lot']) : '';
    $spesial  = isset($_POST['spesial']) ? trim($_POST['spesial']) : '';
    $spesial2 = isset($_POST['spesial2']) ? trim($_POST['spesial2']) : '';
    $spesial3 = isset($_POST['spesial3']) ? trim($_POST['spesial3']) : '';
    $matCode  = isset($_POST['mat_code']) ? trim($_POST['mat_code']) : '';
    $matName  = isset($_POST['mat_name']) ? trim($_POST['mat_name']) : '';
    $maker    = isset($_POST['maker']) ? trim($_POST['maker']) : '';

    $bulanPost = isset($_POST['bulan']) ? (int)$_POST['bulan'] : $bulan;
    $tahunPost = isset($_POST['tahun']) ? (int)$_POST['tahun'] : $tahun;
    $cariPost  = isset($_POST['cari']) ? trim($_POST['cari']) : $cari;

    if ($woNumber === '' || $itemCode === '') {
        $redir = "label_plant2.php?bulan=" . urlencode($bulanPost) .
                 "&tahun=" . urlencode($tahunPost) .
                 "&cari=" . urlencode($cariPost) .
                 "&err=WO Number / Item Code kosong";
        header("Location: " . $redir);
        exit;
    }

    $sqlSave = "
        IF EXISTS (
            SELECT 1
            FROM dbo.LABEL_WO_SETTING
            WHERE WO_NUMBER = ? AND ITEM_CODE = ?
        )
        BEGIN
            UPDATE dbo.LABEL_WO_SETTING
            SET
                STD_PACK     = ?,
                STD_PACK_BOX = ?,
                [TYPE]       = ?,
                SPESIAL      = ?,
                SPESIAL2     = ?,
                SPESIAL3     = ?,
                MAT_CODE     = ?,
                MAT_NAME     = ?,
                MAKER        = ?,
                UPDATED_AT   = GETDATE()
            WHERE WO_NUMBER = ? AND ITEM_CODE = ?
        END
        ELSE
        BEGIN
            INSERT INTO dbo.LABEL_WO_SETTING
            (
                WO_NUMBER,
                ITEM_CODE,
                STD_PACK,
                STD_PACK_BOX,
                [TYPE],
                SPESIAL,
                SPESIAL2,
                SPESIAL3,
                MAT_CODE,
                MAT_NAME,
                MAKER
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        END
    ";

    $paramsSave = array(
        $woNumber,
        $itemCode,

        $stdPack,
        $stdBox,
        $typeLot,
        $spesial,
        $spesial2,
        $spesial3,
        $matCode,
        $matName,
        $maker,
        $woNumber,
        $itemCode,

        $woNumber,
        $itemCode,
        $stdPack,
        $stdBox,
        $typeLot,
        $spesial,
        $spesial2,
        $spesial3,
        $matCode,
        $matName,
        $maker
    );

    $stmtSave = sqlsrv_query($conn, $sqlSave, $paramsSave);

    if ($stmtSave === false) {
        echo "<h3 style='color:red;'>Gagal simpan setting label per WO</h3>";
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    /* ======================================================
       UPDATE MASTER STD_PACK
       ITEM_CODE unik.
       ====================================================== */
    $sqlUpdateStdPack = "
        IF EXISTS (
            SELECT 1
            FROM dbo.STD_PACK
            WHERE ITEM_CODE = ?
        )
        BEGIN
            UPDATE dbo.STD_PACK
            SET
                STD_PACK     = ?,
                STD_PACK_BOX = ?,
                [TYPE]       = ?,
                SPESIAL      = ?,
                SPESIAL2     = ?,
                SPESIAL3     = ?,
                MAT_CODE     = ?,
                MAT_NAME     = ?
            WHERE ITEM_CODE = ?
        END
        ELSE
        BEGIN
            INSERT INTO dbo.STD_PACK
            (
                ITEM_ID,
                ITEM_CODE,
                STD_PACK,
                STD_PACK_BOX,
                [TYPE],
                SPESIAL,
                SPESIAL2,
                SPESIAL3,
                MAT_CODE,
                MAT_NAME
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        END
    ";

    $paramsUpdateStdPack = array(
        $itemCode,

        $stdPack,
        $stdBox,
        $typeLot,
        $spesial,
        $spesial2,
        $spesial3,
        $matCode,
        $matName,
        $itemCode,

        $itemId,
        $itemCode,
        $stdPack,
        $stdBox,
        $typeLot,
        $spesial,
        $spesial2,
        $spesial3,
        $matCode,
        $matName
    );

    $stmtStdPack = sqlsrv_query($conn, $sqlUpdateStdPack, $paramsUpdateStdPack);

    if ($stmtStdPack === false) {
        echo "<h3 style='color:red;'>Setting WO tersimpan, tapi gagal update master STD_PACK</h3>";
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    /* ======================================================
       UPDATE / INSERT Mat_Maker
       ====================================================== */
    if ($matCode !== '') {
        $sqlMaker = "
            IF EXISTS (
                SELECT 1
                FROM dbo.Mat_Maker
                WHERE MAT_CODE = ?
            )
            BEGIN
                UPDATE dbo.Mat_Maker
                SET
                    MAT_NAME = ?,
                    MAKER    = ?
                WHERE MAT_CODE = ?
            END
            ELSE
            BEGIN
                INSERT INTO dbo.Mat_Maker
                (
                    MAT_CODE,
                    MAT_NAME,
                    MAKER
                )
                VALUES (?, ?, ?)
            END
        ";

        $paramsMaker = array(
            $matCode,
            $matName,
            $maker,
            $matCode,

            $matCode,
            $matName,
            $maker
        );

        $stmtMaker = sqlsrv_query($conn, $sqlMaker, $paramsMaker);

        if ($stmtMaker === false) {
            echo "<h3 style='color:red;'>Setting tersimpan, tapi gagal update Mat_Maker</h3>";
            echo "<pre>";
            print_r(sqlsrv_errors());
            echo "</pre>";
            exit;
        }
    }

    $redir = "label_plant2.php?bulan=" . urlencode($bulanPost) .
             "&tahun=" . urlencode($tahunPost) .
             "&cari=" . urlencode($cariPost) .
             "&saved=1";
    header("Location: " . $redir);
    exit;
}

/* ======================================================
   MATERIAL DROPDOWN DARI BOM_DEFAULT PER WO
   ====================================================== */
$sqlMat = "
SELECT DISTINCT
    BD.PART_ID,
    BD.ITEM_ID AS BOM_ITEM_ID,
    BD.QTY,
    BD.UNIT,
    WO.WO_NUMBER,
    ISNULL(MAT.ITEM_CODE, '') AS MAT_CODE,
    ISNULL(MAT.ITEM_NAME, '') AS MAT_NAME,
    ISNULL(MM.MAKER, '') AS MAKER
FROM dbo.BOM_DEFAULT BD
INNER JOIN dbo.ITEMS MAT
    ON BD.ITEM_ID = MAT.ITEM_ID
INNER JOIN dbo.WO WO
    ON BD.PART_ID = WO.ITEM_ID
LEFT JOIN dbo.Mat_Maker MM
    ON MM.MAT_CODE = MAT.ITEM_CODE
WHERE
    MONTH(WO.WO_MMYY) = ?
    AND YEAR(WO.WO_MMYY) = ?
ORDER BY
    WO.WO_NUMBER,
    MAT_NAME,
    MAT_CODE
";

$stmtMat = sqlsrv_query($conn, $sqlMat, array($bulan, $tahun));
$bomMaterialMap = array();

if ($stmtMat !== false) {
    while ($mr = sqlsrv_fetch_array($stmtMat, SQLSRV_FETCH_ASSOC)) {
        $woKey = isset($mr['WO_NUMBER']) ? trim($mr['WO_NUMBER']) : '';

        if ($woKey === '') {
            continue;
        }

        if (!isset($bomMaterialMap[$woKey])) {
            $bomMaterialMap[$woKey] = array();
        }

        $bomMaterialMap[$woKey][] = $mr;
    }
}

/* ======================================================
   MAKER AUTOCOMPLETE DARI Mat_Maker
   ====================================================== */
$sqlMakerList = "
SELECT
    ID,
    ISNULL(MAT_CODE, '') AS MAT_CODE,
    ISNULL(MAT_NAME, '') AS MAT_NAME,
    ISNULL(MAKER, '') AS MAKER
FROM dbo.Mat_Maker
WHERE ISNULL(MAKER, '') <> ''
ORDER BY MAKER, MAT_CODE
";

$stmtMakerList = sqlsrv_query($conn, $sqlMakerList);
$makerList = array();

if ($stmtMakerList !== false) {
    while ($mk = sqlsrv_fetch_array($stmtMakerList, SQLSRV_FETCH_ASSOC)) {
        $makerList[] = $mk;
    }
}

/* ======================================================
   AUTOCOMPLETE WO / ITEM
   ====================================================== */
$sqlAuto = "
SELECT TOP 500
    WO.WO_NUMBER,
    I.ITEM_CODE,
    I.ITEM_NO,
    I.ITEM_NAME
FROM dbo.WO WO
INNER JOIN dbo.ITEMS I
    ON I.ITEM_ID = WO.ITEM_ID
WHERE 
    MONTH(WO.WO_MMYY) = ?
    AND YEAR(WO.WO_MMYY) = ?
ORDER BY WO.WO_MMYY DESC, WO.WO_NUMBER DESC
";

$stmtAuto = sqlsrv_query($conn, $sqlAuto, array($bulan, $tahun));
$autoList = array();

if ($stmtAuto !== false) {
    while ($ar = sqlsrv_fetch_array($stmtAuto, SQLSRV_FETCH_ASSOC)) {
        if (isset($ar['WO_NUMBER']) && trim($ar['WO_NUMBER']) !== '') {
            $autoList[] = trim($ar['WO_NUMBER']);
        }

        if (isset($ar['ITEM_CODE']) && trim($ar['ITEM_CODE']) !== '') {
            $autoList[] = trim($ar['ITEM_CODE']);
        }

        if (isset($ar['ITEM_NO']) && trim($ar['ITEM_NO']) !== '') {
            $autoList[] = trim($ar['ITEM_NO']);
        }

        if (isset($ar['ITEM_NAME']) && trim($ar['ITEM_NAME']) !== '') {
            $autoList[] = trim($ar['ITEM_NAME']);
        }
    }
}

$autoList = array_values(array_unique($autoList));

/* ======================================================
   QUERY LIST WO
   ====================================================== */
$params = array($bulan, $tahun);
$whereCari = "";

if ($cari !== '') {
    $whereCari = "
        AND (
            WO.WO_NUMBER LIKE ?
            OR I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR I.ITEM_NO LIKE ?
            OR C.CUST_COMP LIKE ?
            OR C.CUST_ALIAS LIKE ?
            OR ISNULL(LWS.SPESIAL, SP.SPESIAL) LIKE ?
            OR ISNULL(LWS.SPESIAL2, SP.SPESIAL2) LIKE ?
            OR ISNULL(LWS.SPESIAL3, SP.SPESIAL3) LIKE ?
            OR ISNULL(LWS.MAT_NAME, SP.MAT_NAME) LIKE ?
            OR ISNULL(LWS.MAKER, MM.MAKER) LIKE ?
        )
    ";

    $like = "%" . $cari . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
SELECT TOP 500
    WO.WO_NUMBER,
    WO.WO_MMYY,
    WO.WO_QTY,
    WO.WO_ID,
    WO.ITEM_ID,
    WO.TOT_BARCODE,
    I.ITEM_CODE,
    I.ITEM_NAME,
    I.ITEM_NO,
    ISNULL(C.CUST_COMP, '') AS CUST_COMP,
    ISNULL(C.CUST_ALIAS, '') AS CUST_ALIAS,

    ISNULL(LWS.ID, 0) AS LWS_ID,
    ISNULL(SP.PACK_ID, 0) AS PACK_ID,

    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.STD_PACK, 0) ELSE ISNULL(LWS.STD_PACK, 0) END AS STD_PACK,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.STD_PACK_BOX, 0) ELSE ISNULL(LWS.STD_PACK_BOX, 0) END AS STD_PACK_BOX,

    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.MAT_CODE, '') ELSE ISNULL(LWS.MAT_CODE, '') END AS MAT_CODE,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.MAT_NAME, '') ELSE ISNULL(LWS.MAT_NAME, '') END AS MAT_NAME,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.WARNA_LABEL, '') ELSE ISNULL(SP.WARNA_LABEL, '') END AS WARNA_LABEL,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.[TYPE], '') ELSE ISNULL(LWS.[TYPE], '') END AS [TYPE],
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.SPESIAL, '') ELSE ISNULL(LWS.SPESIAL, '') END AS SPESIAL,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.SPESIAL2, '') ELSE ISNULL(LWS.SPESIAL2, '') END AS SPESIAL2,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(SP.SPESIAL3, '') ELSE ISNULL(LWS.SPESIAL3, '') END AS SPESIAL3,
    CASE WHEN LWS.ID IS NULL THEN ISNULL(MM.MAKER, '') ELSE ISNULL(LWS.MAKER, '') END AS MAKER

FROM dbo.WO WO

INNER JOIN dbo.ITEMS I
    ON I.ITEM_ID = WO.ITEM_ID

OUTER APPLY (
    SELECT TOP 1 *
    FROM dbo.PRICE PR
    WHERE PR.PART_ID = WO.ITEM_ID
    ORDER BY PR.PRICE_ID DESC
) PR

LEFT JOIN dbo.CUST C
    ON C.CUST_ID = PR.CUST_ID

OUTER APPLY (
    SELECT TOP 1 *
    FROM dbo.STD_PACK SP2
    WHERE SP2.ITEM_CODE = I.ITEM_CODE
    ORDER BY SP2.PACK_ID DESC
) SP

LEFT JOIN dbo.Mat_Maker MM
    ON MM.MAT_CODE = SP.MAT_CODE

LEFT JOIN dbo.LABEL_WO_SETTING LWS
    ON LWS.WO_NUMBER = WO.WO_NUMBER
   AND LWS.ITEM_CODE = I.ITEM_CODE

WHERE 
    MONTH(WO.WO_MMYY) = ?
    AND YEAR(WO.WO_MMYY) = ?
    $whereCari

ORDER BY WO.WO_MMYY DESC, WO.WO_NUMBER DESC
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Label Plant 2</title>

    <link rel="stylesheet" href="../assets/bootstrap.min.css">

    <style>
        body {
            background-color: #f8f9fa;
            padding: 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .container-fluid {
            width: 100%;
            padding-left: 0;
            padding-right: 0;
        }

        .main-container {
            width: 100%;
            max-width: none;
            margin: 0 auto;
            background: #fff;
            padding: 14px;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            box-sizing: border-box;
            overflow: hidden;
        }

        .header-section {
            border-bottom: 2px solid #eee;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }

        .header-section h3 {
            margin: 0;
            font-weight: 700;
            color: #333;
            font-size: 20px;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 10px;
        }

        .filter-box {
            background: #f7f7f7;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .filter-grid {
            display: table;
            width: 100%;
        }

        .filter-cell {
            display: table-cell;
            vertical-align: bottom;
            padding-right: 8px;
            white-space: nowrap;
        }

        .filter-cell label {
            display: block;
            font-size: 11px;
            margin-bottom: 4px;
            color: #333;
        }

        .filter-bulan {
            width: 68px;
        }

        .filter-tahun {
            width: 82px;
        }

        .filter-cari {
            width: 260px;
        }

        .filter-info {
            margin-top: 8px;
            color: #777;
            font-size: 11px;
        }

        .alert-mini {
            padding: 8px 12px;
            margin-bottom: 12px;
            border-radius: 6px;
            font-size: 12px;
        }

        .alert-ok {
            background: #e9f8ee;
            border: 1px solid #bde5c8;
            color: #2b7a3d;
        }

        .alert-error {
            background: #fff1f1;
            border: 1px solid #f1bcbc;
            color: #a94442;
        }

        .table-responsive {
            width: 100%;
            overflow-x: hidden;
            overflow-y: visible;
            border: 1px solid #ddd;
        }

        .table-label {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            font-size: 9px;
            background: #fff;
            margin-bottom: 0;
        }

        .table-label th {
            background: #34495e;
            color: #fff;
            vertical-align: middle !important;
            text-align: center;
            padding: 4px 2px !important;
            line-height: 1.08;
            white-space: normal;
            word-break: break-word;
        }

        .table-label td {
            vertical-align: middle !important;
            white-space: normal;
            word-break: break-word;
            padding: 3px 2px !important;
            line-height: 1.12;
        }

        .table-label th:nth-child(1),
        .table-label td:nth-child(1) {
            width: 2.3%;
            text-align: center;
        }

        .table-label th:nth-child(2),
        .table-label td:nth-child(2) {
            width: 6.1%;
        }

        .table-label th:nth-child(3),
        .table-label td:nth-child(3) {
            width: 5.1%;
        }

        .table-label th:nth-child(4),
        .table-label td:nth-child(4) {
            width: 5.2%;
        }

        .table-label th:nth-child(5),
        .table-label td:nth-child(5) {
            width: 7.2%;
        }

        .table-label th:nth-child(6),
        .table-label td:nth-child(6) {
            width: 10.2%;
        }

        .table-label th:nth-child(7),
        .table-label td:nth-child(7) {
            width: 8.0%;
        }

        .table-label th:nth-child(8),
        .table-label td:nth-child(8) {
            width: 4.4%;
            text-align: right;
        }

        .table-label th:nth-child(9),
        .table-label td:nth-child(9),
        .table-label th:nth-child(10),
        .table-label td:nth-child(10) {
            width: 4.6%;
        }

        .table-label th:nth-child(11),
        .table-label td:nth-child(11) {
            width: 5.0%;
        }

        .table-label th:nth-child(12),
        .table-label td:nth-child(12),
        .table-label th:nth-child(13),
        .table-label td:nth-child(13),
        .table-label th:nth-child(14),
        .table-label td:nth-child(14) {
            width: 6.3%;
        }

        .table-label th:nth-child(15),
        .table-label td:nth-child(15) {
            width: 12.0%;
        }

        .table-label th:nth-child(16),
        .table-label td:nth-child(16) {
            width: 7.2%;
        }

        .table-label th:nth-child(17),
        .table-label td:nth-child(17) {
            width: 14.5%;
        }

        .input-edit {
            height: 22px;
            padding: 2px 3px;
            font-size: 9px;
            border: 1px solid #bbb;
            border-radius: 3px;
            background: #fff;
            width: 100%;
            box-sizing: border-box;
        }

        .input-edit:focus {
            border-color: #428bca;
            box-shadow: 0 0 4px rgba(66,139,202,0.45);
            outline: none;
        }

        .material-select {
            height: 22px;
            padding: 1px 2px;
            font-size: 9px;
            border: 1px solid #bbb;
            border-radius: 3px;
            background: #fff;
            width: 100%;
            box-sizing: border-box;
            cursor: pointer;
        }

        .material-select:focus {
            border-color: #428bca;
            box-shadow: 0 0 4px rgba(66,139,202,0.45);
            outline: none;
        }

        .btn-xs {
            padding: 2px 5px;
            font-size: 9px;
            line-height: 1.25;
            border-radius: 3px;
        }

        .btn-save-row {
            margin-bottom: 3px;
            display: inline-block;
            width: auto;
        }

        .print-form {
            margin: 0;
            white-space: nowrap;
        }

        .label-select {
            width: 92px;
            display: inline-block;
            height: 23px;
            padding: 1px 2px;
            font-size: 8px;
            vertical-align: middle;
        }

        .edit-note {
            color: #888;
            font-size: 8px;
            line-height: 1.1;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wo-setting-note {
            color: #2b7a3d;
            font-size: 8px;
            font-weight: bold;
            line-height: 1.1;
        }

        .text-right {
            text-align: right;
        }

        .btn-cetak-mini {
            padding: 2px 5px;
            font-size: 8px;
            line-height: 1.2;
        }

        @media screen and (max-width: 1200px) {
            body {
                overflow-x: auto;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .table-label {
                min-width: 1250px;
            }
        }

        @media screen and (max-width: 900px) {
            .filter-cell {
                display: block;
                margin-bottom: 8px;
            }

            .filter-cari {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">

    <a href="dashboard_home.php" class="btn btn-secondary btn-sm btn-back shadow-sm">
        ← Kembali ke Menu
    </a>

    <div class="main-container">

        <div class="header-section">
            <h3>Cetak Label Plant 2</h3>
            <small class="text-muted">
                Simpan akan update setting WO, master STD_PACK, dan Mat_Maker.
            </small>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert-mini alert-ok">
                Setting label berhasil disimpan.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
            <div class="alert-mini alert-error">
                <?php echo h_label($_GET['err']); ?>
            </div>
        <?php endif; ?>

        <form method="get" id="formFilter" class="filter-box">
            <div class="filter-grid">

                <div class="filter-cell">
                    <label>Bulan</label>
                    <select name="bulan" id="bulan" class="form-control input-sm filter-bulan">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($bulan == $i) ? 'selected' : ''; ?>>
                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-cell">
                    <label>Tahun</label>
                    <input type="text"
                           name="tahun"
                           id="tahun"
                           value="<?php echo h_label($tahun); ?>"
                           class="form-control input-sm filter-tahun">
                </div>

                <div class="filter-cell">
                    <label>Cari WO / Item</label>
                    <input type="text"
                           name="cari"
                           id="cari"
                           value="<?php echo h_label($cari); ?>"
                           list="listWoItem"
                           autocomplete="off"
                           class="form-control input-sm filter-cari"
                           placeholder="Ketik WO, Item Code, Item No, Item Name">
                </div>

                <div class="filter-cell">
                    <label>&nbsp;</label>
                    <a href="label_plant2.php" class="btn btn-default btn-sm">
                        Reset
                    </a>
                </div>

            </div>

            <div class="filter-info">
                Material dropdown dari BOM per WO. Maker bisa autocomplete dari tabel Mat_Maker.
            </div>

            <datalist id="listWoItem">
                <?php foreach ($autoList as $auto): ?>
                    <option value="<?php echo h_label($auto); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </form>

        <datalist id="listMaker">
            <?php foreach ($makerList as $mk): ?>
                <?php
                    $mkMaker = isset($mk['MAKER']) ? trim($mk['MAKER']) : '';
                    $mkCode  = isset($mk['MAT_CODE']) ? trim($mk['MAT_CODE']) : '';
                    $mkName  = isset($mk['MAT_NAME']) ? trim($mk['MAT_NAME']) : '';
                ?>
                <?php if ($mkMaker !== ''): ?>
                    <option value="<?php echo h_label($mkMaker); ?>">
                        <?php echo h_label($mkCode . " | " . $mkName); ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </datalist>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-condensed table-label">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>WO Number</th>
                        <th>WO Date</th>
                        <th>Item Code</th>
                        <th>Item No</th>
                        <th>Item Name</th>
                        <th>Customer</th>
                        <th>WO Qty</th>
                        <th>STD Pack</th>
                        <th>STD Box</th>
                        <th>Type/Lot</th>
                        <th>Spesial</th>
                        <th>Spesial2</th>
                        <th>Spesial3</th>
                        <th>Material</th>
                        <th>Maker</th>
                        <th>Cetak / Simpan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $no = 0;

                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                        $no++;

                        $wo = trim($row['WO_NUMBER']);
                        $stdPack = (int)$row['STD_PACK'];
                        $stdBox  = (int)$row['STD_PACK_BOX'];
                        $lwsId   = (int)$row['LWS_ID'];

                        $formId = "saveWoSetting" . $no;

                        $woKey   = trim($row['WO_NUMBER']);
                        $bomList = isset($bomMaterialMap[$woKey]) ? $bomMaterialMap[$woKey] : array();

                        $currentMatCode = isset($row['MAT_CODE']) ? trim($row['MAT_CODE']) : '';
                        $currentMatName = isset($row['MAT_NAME']) ? trim($row['MAT_NAME']) : '';
                        $currentMaker   = isset($row['MAKER']) ? trim($row['MAKER']) : '';

                        $currentInBom = false;
                    ?>
                    <tr>
                        <td>
                            <?php echo $no; ?>

                            <?php if ($lwsId > 0): ?>
                                <div class="wo-setting-note">WO</div>
                            <?php endif; ?>

                            <form method="post" id="<?php echo h_label($formId); ?>" action="label_plant2.php"></form>

                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="act" value="save_wo_setting">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="wo_number" value="<?php echo h_label($wo); ?>">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="item_code" value="<?php echo h_label($row['ITEM_CODE']); ?>">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="item_id" value="<?php echo h_label($row['ITEM_ID']); ?>">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="bulan" value="<?php echo h_label($bulan); ?>">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="tahun" value="<?php echo h_label($tahun); ?>">
                            <input form="<?php echo h_label($formId); ?>" type="hidden" name="cari" value="<?php echo h_label($cari); ?>">
                        </td>

                        <td><b><?php echo h_label($wo); ?></b></td>
                        <td><?php echo h_label(fmt_date_label($row['WO_MMYY'])); ?></td>
                        <td><?php echo h_label($row['ITEM_CODE']); ?></td>
                        <td><?php echo h_label($row['ITEM_NO']); ?></td>
                        <td><?php echo h_label($row['ITEM_NAME']); ?></td>
                        <td><?php echo h_label($row['CUST_COMP']); ?></td>
                        <td class="text-right"><?php echo number_format((float)$row['WO_QTY']); ?></td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="std_pack"
                                   value="<?php echo h_label($stdPack); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="std_box"
                                   value="<?php echo h_label($stdBox); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="type_lot"
                                   value="<?php echo h_label($row['TYPE']); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="spesial"
                                   value="<?php echo h_label($row['SPESIAL']); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="spesial2"
                                   value="<?php echo h_label($row['SPESIAL2']); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="spesial3"
                                   value="<?php echo h_label($row['SPESIAL3']); ?>"
                                   class="input-edit">
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="hidden"
                                   name="mat_code"
                                   id="mat_code_<?php echo $no; ?>"
                                   value="<?php echo h_label($currentMatCode); ?>">

                            <select id="mat_select_<?php echo $no; ?>"
                                    class="material-select mat-select"
                                    data-row="<?php echo $no; ?>">
                                <option value=""
                                        data-code=""
                                        data-name=""
                                        data-maker="">
                                    -- Pilih Material --
                                </option>

                                <?php foreach ($bomList as $mat): ?>
                                    <?php
                                        $matCodeOpt = isset($mat['MAT_CODE']) ? trim($mat['MAT_CODE']) : '';
                                        $matNameOpt = isset($mat['MAT_NAME']) ? trim($mat['MAT_NAME']) : '';
                                        $makerOpt   = isset($mat['MAKER']) ? trim($mat['MAKER']) : '';

                                        $selected = '';
                                        if ($currentMatCode !== '' && $currentMatCode === $matCodeOpt) {
                                            $selected = 'selected';
                                            $currentInBom = true;
                                        } elseif ($currentMatCode === '' && $currentMatName !== '' && $currentMatName === $matNameOpt) {
                                            $selected = 'selected';
                                            $currentInBom = true;
                                        }
                                    ?>
                                    <option value="<?php echo h_label($matCodeOpt); ?>"
                                            data-code="<?php echo h_label($matCodeOpt); ?>"
                                            data-name="<?php echo h_label($matNameOpt); ?>"
                                            data-maker="<?php echo h_label($makerOpt); ?>"
                                            <?php echo $selected; ?>>
                                        <?php echo h_label($matNameOpt . " | " . $matCodeOpt); ?>
                                    </option>
                                <?php endforeach; ?>

                                <?php if ($currentMatName !== '' && !$currentInBom): ?>
                                    <option value="<?php echo h_label($currentMatCode); ?>"
                                            data-code="<?php echo h_label($currentMatCode); ?>"
                                            data-name="<?php echo h_label($currentMatName); ?>"
                                            data-maker="<?php echo h_label($currentMaker); ?>"
                                            selected>
                                        <?php echo h_label($currentMatName . " | " . $currentMatCode); ?>
                                    </option>
                                <?php endif; ?>
                            </select>

                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="mat_name"
                                   id="mat_name_<?php echo $no; ?>"
                                   value="<?php echo h_label($currentMatName); ?>"
                                   class="input-edit"
                                   style="margin-top:2px;"
                                   placeholder="Material Name bisa diedit">

                            <div class="edit-note">
                                Code: <span id="mat_code_show_<?php echo $no; ?>"><?php echo h_label($currentMatCode); ?></span>
                            </div>
                        </td>

                        <td>
                            <input form="<?php echo h_label($formId); ?>"
                                   type="text"
                                   name="maker"
                                   id="maker_<?php echo $no; ?>"
                                   value="<?php echo h_label($currentMaker); ?>"
                                   list="listMaker"
                                   autocomplete="off"
                                   class="input-edit"
                                   placeholder="Maker">
                        </td>

                        <td>
                            <button form="<?php echo h_label($formId); ?>"
                                    type="submit"
                                    class="btn btn-success btn-xs btn-save-row">
                                Simpan
                            </button>

                            <form method="get" action="label_print.php" target="_blank" class="print-form">
                                <input type="hidden" name="wo" value="<?php echo h_label($wo); ?>">

                                <select name="jenis" class="form-control input-sm label-select">
                                    <?php foreach ($labelTypes as $kode => $cfg): ?>
                                        <?php
                                            $mode = isset($cfg['mode']) ? $cfg['mode'] : 'BAG';
                                            $disabled = '';

                                            if ($mode == 'BAG' && $stdPack <= 0) {
                                                $disabled = 'disabled';
                                            }

                                            if ($mode == 'BOX' && $stdBox <= 0) {
                                                $disabled = 'disabled';
                                            }
                                        ?>
                                        <option value="<?php echo h_label($kode); ?>" <?php echo $disabled; ?>>
                                            <?php echo h_label($cfg['nama']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" class="btn btn-primary btn-xs btn-cetak-mini">
                                    Cetak
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if ($no == 0): ?>
                    <tr>
                        <td colspan="17" class="text-center">
                            Data WO tidak ditemukan untuk bulan <?php echo h_label($bulan); ?>/<?php echo h_label($tahun); ?>.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="../assets/jquery.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

<script>
(function() {
    var form  = document.getElementById('formFilter');
    var bulan = document.getElementById('bulan');
    var tahun = document.getElementById('tahun');
    var cari  = document.getElementById('cari');

    var timerCari = null;

    function submitForm() {
        if (form) {
            form.submit();
        }
    }

    function submitBulanTahun() {
        if (cari) {
            cari.value = '';
        }
        submitForm();
    }

    function autoCari() {
        if (timerCari) {
            clearTimeout(timerCari);
        }

        timerCari = setTimeout(function() {
            submitForm();
        }, 500);
    }

    if (bulan) {
        bulan.onchange = function() {
            submitBulanTahun();
        };
    }

    if (tahun) {
        tahun.onchange = function() {
            submitBulanTahun();
        };

        tahun.onkeyup = function(e) {
            e = e || window.event;

            if (e.keyCode == 13) {
                submitBulanTahun();
            }
        };
    }

    if (cari) {
        cari.onkeyup = function(e) {
            e = e || window.event;

            if (e.keyCode == 13) {
                submitForm();
                return;
            }

            if (cari.value.length >= 2) {
                autoCari();
            }

            if (cari.value.length == 0) {
                autoCari();
            }
        };

        cari.onchange = function() {
            submitForm();
        };
    }

    function applyMaterial(rowNo) {
        var matSelect = document.getElementById('mat_select_' + rowNo);
        var matName   = document.getElementById('mat_name_' + rowNo);
        var matCode   = document.getElementById('mat_code_' + rowNo);
        var matShow   = document.getElementById('mat_code_show_' + rowNo);
        var maker     = document.getElementById('maker_' + rowNo);

        if (!matSelect || !matName || !matCode || !maker) {
            return;
        }

        var opt = matSelect.options[matSelect.selectedIndex];

        if (!opt) {
            return;
        }

        var code = opt.getAttribute('data-code') || '';
        var name = opt.getAttribute('data-name') || '';
        var mk   = opt.getAttribute('data-maker') || '';

        matCode.value = code;
        matName.value = name;

        if (mk !== '') {
            maker.value = mk;
        }

        if (matShow) {
            matShow.innerHTML = code;
        }
    }

    var selects = document.getElementsByClassName('mat-select');

    for (var i = 0; i < selects.length; i++) {
        selects[i].onchange = function() {
            applyMaterial(this.getAttribute('data-row'));
        };
    }
})();
</script>

</body>
</html>