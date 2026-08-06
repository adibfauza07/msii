<?php
// File: test_bom_xml.php
// Script untuk melihat RAW XML langsung dari Tally

$tally_ip = 'serplan1'; // Sesuaikan jika beda
$tally_port = '9002';    // Sesuaikan jika beda

$part_name = isset($_POST['part_name']) ? trim($_POST['part_name']) : '';
$xml_response = '';

if ($part_name !== '') {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>EXPORT</TALLYREQUEST>
    <TYPE>OBJECT</TYPE>
    <SUBTYPE>Stock Item</SUBTYPE>
    <ID TYPE="Name">' . htmlspecialchars($part_name, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
      </STATICVARIABLES>
      <FETCHLIST>
        <FETCH>Name</FETCH>
        <FETCH>BaseUnits</FETCH>
        <FETCH>ComponentList</FETCH>
        <FETCH>MultiComponentList</FETCH>
        <FETCH>MultiComponentList.*</FETCH>
        <FETCH>MultiComponentList.*.*</FETCH>
        <FETCH>*.*</FETCH>
      </FETCHLIST>
    </DESC>
  </BODY>
</ENVELOPE>';

    $ch = curl_init("http://$tally_ip:$tally_port");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=utf-8'));
    $xml_response = curl_exec($ch);
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html>
<body style="font-family: Arial; padding: 20px;">
    <h3>Test Tarik RAW XML BOM dari Tally</h3>
    <form method="post">
        <label>Masukkan TEPAT 1 Part Name yang PASTI ADA BOM-nya di Tally:</label><br>
        <input type="text" name="part_name" style="width: 300px; padding: 5px;" value="<?php echo htmlspecialchars($part_name); ?>">
        <button type="submit" style="padding: 5px 15px;">Tarik XML</button>
    </form>
    
    <?php if ($xml_response !== ''): ?>
        <hr>
        <h4>Hasil RAW XML dari Tally Server:</h4>
        <p><i>Mohon copy SEMUA teks di dalam kotak hitam ini dan kirimkan ke saya.</i></p>
        <textarea style="width: 100%; height: 500px; background: #222; color: #0f0; font-family: monospace; padding: 10px;"><?php echo htmlspecialchars($xml_response); ?></textarea>
    <?php endif; ?>
</body>
</html>