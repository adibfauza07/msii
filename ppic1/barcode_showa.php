<?php
// =======================================
// 1. DATA SHOWA (ARRAY)
// =======================================
$data = [
    ["code"=>"011544-0","part_no"=>"HKVY1-200-BO-IN","name"=>"Valve A","pb"=>100,"bx"=>9600,"warna"=>"PUTIH"],
    ["code"=>"011545-0","part_no"=>"HKVY1-201-BO-IN","name"=>"Valve B","pb"=>100,"bx"=>9600,"warna"=>"PUTIH"],
    ["code"=>"012281-0","part_no"=>"G5E51-200-00","name"=>"Valve","pb"=>100,"bx"=>1200,"warna"=>"PUTIH"],
    ["code"=>"012282-0","part_no"=>"S3381-250-00","name"=>"RING PISTON","pb"=>100,"bx"=>1200,"warna"=>"PUTIH"],
    ["code"=>"012270-0","part_no"=>"HHR62-400-0A-IN","name"=>"GUIDE SPRING","pb"=>15,"bx"=>60,"warna"=>"PUTIH"],
    ["code"=>"012356-0","part_no"=>"HT3W1-J45-00-IN","name"=>"REBOUND COLLAR","pb"=>60,"bx"=>960,"warna"=>"HIJAU"],
    ["code"=>"012358-0","part_no"=>"HTAA1-J45-00-IN","name"=>"REBOUND COLLAR","pb"=>200,"bx"=>1000,"warna"=>"PUTIH"],
    ["code"=>"012438-0","part_no"=>"35809-250-00-RT","name"=>"RING PISTON","pb"=>100,"bx"=>1008,"warna"=>"PUTIH"],
    ["code"=>"012363-0","part_no"=>"74009-388-20","name"=>"REBOUND RUBBER","pb"=>100,"bx"=>1000,"warna"=>"PUTIH"],
    ["code"=>"012424-0","part_no"=>"HK201-252-00-RT","name"=>"RING PISTON B","pb"=>100,"bx"=>1008,"warna"=>"PUTIH"],
    ["code"=>"012421-0","part_no"=>"HK201-250-00-RT","name"=>"RING PISTON","pb"=>"?","bx"=>1260,"warna"=>"HIJAU"],
    ["code"=>"012508-0","part_no"=>"HKVY1-200-BO-IN","name"=>"Valve A TL4","pb"=>100,"bx"=>10800,"warna"=>"PUTIH"],
    ["code"=>"012509-0","part_no"=>"HKVY1-201-BO-IN","name"=>"Valve B TL4","pb"=>100,"bx"=>10800,"warna"=>"PUTIH"],
    ["code"=>"012548-0","part_no"=>"C7881-J45-00-IN","name"=>"REBOUND COLLAR","pb"=>60,"bx"=>960,"warna"=>"PUTIH"],
    ["code"=>"012561-0","part_no"=>"25009-250-10-IN","name"=>"PISTON RING","pb"=>200,"bx"=>2400,"warna"=>"PUTIH"],
    ["code"=>"012562-0","part_no"=>"HKEJ1-200-91","name"=>"VALVE","pb"=>6000,"bx"=>300,"warna"=>"PUTIH"],
    ["code"=>"012754-0","part_no"=>"HKVY1-200-BB-IN","name"=>"Valve A TL5","pb"=>100,"bx"=>10800,"warna"=>"PUTIH"],
    ["code"=>"012660-0","part_no"=>"X6141-388-OA-IN","name"=>"REBOUND RUBBER","pb"=>240,"bx"=>1000,"warna"=>"PUTIH"],
    ["code"=>"012660-0","part_no"=>"X6141-388-OA-IN","name"=>"REBOUND RUBBER","pb"=>120,"bx"=>2400,"warna"=>"PUTIH"],
    ["code"=>"012713-0","part_no"=>"D5002-J45-OA-IN","name"=>"REBOUND COLLAR","pb"=>160,"bx"=>1200,"warna"=>"PUTIH"],
    ["code"=>"013114-0","part_no"=>"HKW31-200-OA-IN","name"=>"VALVE K236","pb"=>200,"bx"=>4000,"warna"=>"PUTIH"],
    ["code"=>"013261-0","part_no"=>"HKW32-294-OA-IN","name"=>"FREE PISTON","pb"=>60,"bx"=>900,"warna"=>"PUTIH"],
    ["code"=>"014514-0","part_no"=>"HKW32-703-OA-IN","name"=>"FREE PISTON","pb"=>60,"bx"=>480,"warna"=>"PUTIH"],
    ["code"=>"013501-0","part_no"=>"C7551-J45-00-IN","name"=>"REBOUND COLAR (AFTER ANELING)","pb"=>"?","bx"=>480,"warna"=>"PUTIH"],
    ["code"=>"013501-0","part_no"=>"C7551-J45-0A-IN","name"=>"REBOUND COLAR","pb"=>"?","bx"=>480,"warna"=>"PUTIH"],
    ["code"=>"013730-0","part_no"=>"K2391-250-0B-IN","name"=>"PISTON RING","pb"=>"?","bx"=>1000,"warna"=>"PUTIH"],
    ["code"=>"013739-0","part_no"=>"HK641-405-00-IN","name"=>"SPRING JOINT","pb"=>100,"bx"=>1000,"warna"=>"PUTIH"],
    ["code"=>"013752-0","part_no"=>"S00012-250-01","name"=>"PISTON RING","pb"=>"?","bx"=>4000,"warna"=>"HIJAU"],
    ["code"=>"013672-0","part_no"=>"S38021-250-0N1","name"=>"PISTON RING 7500","pb"=>100,"bx"=>7500,"warna"=>"PUTIH"],
    ["code"=>"013740-0","part_no"=>"HK201-250-00-RT","name"=>"RING PISTON TL2","pb"=>"?","bx"=>1260,"warna"=>"PUTIH"],
    ["code"=>"013741-0","part_no"=>"HK201-258-00-RT","name"=>"RING PISTON B","pb"=>"?","bx"=>1008,"warna"=>"PUTIH"],
    ["code"=>"014535-0","part_no"=>"K2391-252-0B-IN","name"=>"RING PISTON B","pb"=>"?","bx"=>400,"warna"=>"PUTIH"],
    ["code"=>"014977-0","part_no"=>"HKVY1-200-B0-IN","name"=>"Valve A TL8","pb"=>100,"bx"=>10800,"warna"=>"PUTIH"],
    ["code"=>"014258-0","part_no"=>"HKSP2-250-0B-IN1","name"=>"PISTON RING","pb"=>100,"bx"=>1300,"warna"=>"PUTIH"],
    ["code"=>"014252-0","part_no"=>"25009-250-10 IN TL2","name"=>"PISTON RING","pb"=>200,"bx"=>2400,"warna"=>"PUTIH"],
    ["code"=>"014447-0","part_no"=>"01359-250-00-0B","name"=>"PISTON RING","pb"=>"?","bx"=>480,"warna"=>"PUTIH"],
    ["code"=>"014469-0","part_no"=>"HK02-393-91-IN","name"=>"SPRING SEAT B","pb"=>20,"bx"=>200,"warna"=>"PUTIH"],
    ["code"=>"014450-0","part_no"=>"HSY21-388-0A-IN","name"=>"REBOUND RUBBER","pb"=>60,"bx"=>720,"warna"=>"PUTIH"]
];
?>

<?php
// =======================================
// 2. TABEL BERGARIS + LINK PDF
// =======================================

$base = "file://///serplan3/Application/APP NEW/4.PLANT 2/NEW PROGRAM/2.PPIC/2.Barcode WO/report Beda/pdf_showa/";
?>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>PART CODE</th>
        <th>PART NO</th>
        <th>PART NAME</th>
        <th>POLIBAG</th>
        <th>BOX</th>
        <th>WARNA</th>
        <th>PDF POLIBAG</th>
        <th>PDF BOX</th>
    </tr>

    <?php foreach($data as $r){ ?>
    <tr>
        <td><?= $r['code'] ?></td>
        <td><?= $r['part_no'] ?></td>
        <td><?= $r['name'] ?></td>
        <td><?= $r['pb'] ?></td>
        <td><?= $r['bx'] ?></td>
        <td><?= $r['warna'] ?></td>

        <td><a href="<?= $base.$r['pb'] ?>.pdf" target="_blank">PDF <?= $r['pb'] ?></a></td>
        <td><a href="<?= $base.$r['bx'] ?>.pdf" target="_blank">PDF <?= $r['bx'] ?></a></td>
    </tr>
    <?php } ?>
</table>
