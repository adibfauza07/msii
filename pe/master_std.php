<?php
require_once 'MiddleWare/Auth.php'; 
require_once 'MiddleWare/RoleCheck.php';
require_once __DIR__ . '/../config/database_p1.php';
include 'includes/header.php';
?>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    <div class="p-4 w-100" style="margin-left: 250px;">
        <h3>Master Standard Trial Part</h3>
        <hr>
        <table class="table table-sm table-striped table-bordered mt-3">
            <thead class="table-dark small text-center">
                <tr>
                    <th>ITEM CODE</th>
                    <th>WEIGHT PART (STD)</th>
                    <th>RUNNER (STD)</th>
                    <th>CYCLE TIME (STD)</th>
                    <th>CAVITY (STD)</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $q = sqlsrv_query($conn, "SELECT * FROM TRIAL_PE_STD ORDER BY ITEM_CODE");
                while($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)){
                    echo "<tr>
                        <td>{$r['ITEM_CODE']}</td>
                        <td class='text-end'>{$r['WEIGHT_PART_STD']} g</td>
                        <td class='text-end'>{$r['WEIGHT_RUNNER_STD']} g</td>
                        <td class='text-center'>{$r['CYCLE_TIME_STD']} s</td>
                        <td class='text-center'>{$r['CAVITY_STD']}</td>
                        <td class='text-center'><button class='btn btn-xs btn-warning'>Edit</button></td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>