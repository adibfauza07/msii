<?php
// /msii/ppic/label_plant2.php

require_once __DIR__ . "/../config/database_ppic.php";

function h_label($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmt_date_label($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }
    if ($v == '' || $v === null) {
        return '';
    }
    return (string)$v;
}

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cari  = isset($_GET['cari']) ? trim($_GET['cari']) : '';

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

$params = array($bulan, $tahun);
$whereCari = "";

if ($cari !== '') {
    $whereCari = "
        AND (
            WO.WO_NUMBER LIKE ?
            OR I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR C.CUST_COMP LIKE ?
            OR C.CUST_ALIAS LIKE ?
        )
    ";

    $like = "%" . $cari . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
SELECT TOP 300
    WO.WO_NUMBER,
    WO.WO_MMYY,
    WO.WO_QTY,
    I.ITEM_CODE,
    I.ITEM_NAME,
    I.ITEM_NO,
    C.CUST_COMP,
    C.CUST_ALIAS,
    ISNULL(SP.STD_PACK, 0) AS STD_PACK,
    ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX,
    SP.MAT_CODE,
    SP.MAT_NAME,
    MM.MAKER
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
WHERE 
    MONTH(WO.WO_MMYY) = ?
    AND YEAR(WO.WO_MMYY) = ?
    $whereCari
ORDER BY WO.WO_MMYY DESC, WO.WO_NUMBER DESC
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "<div class='alert alert-danger'>";
    echo "<b>Query error.</b><br><pre>";
    print_r(sqlsrv_errors());
    echo "</pre></div>";
    return;
}
?>

<style>
    .box-title {
        background: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .table-label {
        background: #fff;
        font-size: 12px;
    }

    .table-label th {
        background: #34495e;
        color: #fff;
        white-space: nowrap;
        vertical-align: middle;
    }

    .table-label td {
        vertical-align: middle !important;
    }

    .btn-xs {
        padding: 3px 8px;
        font-size: 11px;
        line-height: 1.4;
        border-radius: 3px;
    }

    .filter-row {
        margin-top: 12px;
    }

    .muted-small {
        color: #777;
        font-size: 12px;
    }
</style>

<div class="box-title">
    <h2>Cetak Label Plant 2</h2>
    <div class="header-line"></div>

    <form method="get" class="form-inline filter-row">
        <input type="hidden" name="page" value="label_p2">

        <div class="form-group">
            <label>Bulan</label>
            <select name="bulan" class="form-control input-sm">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($bulan == $i) ? 'selected' : ''; ?>>
                        <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group" style="margin-left:8px;">
            <label>Tahun</label>
            <input type="text" name="tahun" value="<?php echo h_label($tahun); ?>" class="form-control input-sm" style="width:80px;">
        </div>

        <div class="form-group" style="margin-left:8px;">
            <label>Cari WO / Item</label>
            <input type="text" name="cari" value="<?php echo h_label($cari); ?>" class="form-control input-sm" style="width:220px;">
        </div>

        <button type="submit" class="btn btn-primary btn-sm" style="margin-left:8px;">Cari</button>
        <a href="?page=label_p2" class="btn btn-default btn-sm">Reset</a>
    </form>

    <div class="muted-small" style="margin-top:8px;">
        BAG memakai STD_PACK. BOX memakai STD_PACK_BOX.
    </div>
</div>

<table class="table table-bordered table-striped table-condensed table-label">
    <thead>
        <tr>
            <th>No</th>
            <th>WO Number</th>
            <th>WO Date</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>Customer</th>
            <th class="text-right">WO Qty</th>
            <th class="text-right">STD Pack</th>
            <th class="text-right">STD Box</th>
            <th>Material</th>
            <th>Maker</th>
            <th>Cetak</th>
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
        ?>
        <tr>
            <td><?php echo $no; ?></td>
            <td><b><?php echo h_label($wo); ?></b></td>
            <td><?php echo h_label(fmt_date_label($row['WO_MMYY'])); ?></td>
            <td><?php echo h_label($row['ITEM_CODE']); ?></td>
            <td><?php echo h_label($row['ITEM_NAME']); ?></td>
            <td><?php echo h_label($row['CUST_COMP']); ?></td>
            <td class="text-right"><?php echo number_format((float)$row['WO_QTY']); ?></td>
            <td class="text-right"><?php echo number_format($stdPack); ?></td>
            <td class="text-right"><?php echo number_format($stdBox); ?></td>
            <td><?php echo h_label($row['MAT_NAME']); ?></td>
            <td><?php echo h_label($row['MAKER']); ?></td>
            <td>
                <?php if ($stdPack > 0): ?>
                    <a target="_blank" class="btn btn-success btn-xs"
                       href="label_print.php?jenis=BAG&wo=<?php echo urlencode($wo); ?>">
                        BAG
                    </a>
                <?php else: ?>
                    <button class="btn btn-default btn-xs" disabled>BAG</button>
                <?php endif; ?>

                <?php if ($stdBox > 0): ?>
                    <a target="_blank" class="btn btn-info btn-xs"
                       href="label_print.php?jenis=BOX&wo=<?php echo urlencode($wo); ?>">
                        BOX
                    </a>
                <?php else: ?>
                    <button class="btn btn-default btn-xs" disabled>BOX</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($no == 0): ?>
        <tr>
            <td colspan="12" class="text-center">Data WO tidak ditemukan.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>