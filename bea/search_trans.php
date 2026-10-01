<?php
@ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
if (session_id() == "") session_start();

require_once __DIR__ . '/config/database.php';

if ($conn === false) {
    echo json_encode(array("success" => false, "rows" => array()));
    exit;
}

$qAjax = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$likeAjax = "%" . strtoupper($qAjax) . "%";

$sql = "SELECT TOP 10 TRAN_ID, TRAN_DOC, TRAN_DATE FROM TRANS WHERE UPPER(TRAN_DOC) LIKE ? ORDER BY TRAN_DATE DESC, TRAN_DOC DESC";
$stmtSearch = sqlsrv_query($conn, $sql, array($likeAjax));

$out = array();
if ($stmtSearch !== false) {
    while ($r = sqlsrv_fetch_array($stmtSearch, SQLSRV_FETCH_ASSOC)) {
        $tgl = ($r['TRAN_DATE'] instanceof DateTime) ? $r['TRAN_DATE']->format('d-M-Y') : $r['TRAN_DATE'];
        $out[] = array(
            "TRAN_ID" => intval($r["TRAN_ID"]),
            "TRAN_DOC" => trim((string)$r["TRAN_DOC"]),
            "TRAN_DATE" => $tgl
        );
    }
}

echo json_encode(array("success" => true, "rows" => $out));
exit;
?>
```[cite: 2]

---

### 2. Perbarui Fungsi JavaScript di `trans.php`
Buka file `trans.php` Anda, lalu cari fungsi **`showTransSearchAC`** (di bagian bawah script), kemudian ubah baris `xhr.open(...)` agar langsung memanggil file `search_trans.php` yang baru saja dibuat:

Ganti kode fungsi `showTransSearchAC` yang lama dengan ini:

```javascript
function showTransSearchAC(input) {
    initAC(); 
    var key = (input.value || "").trim();
    acMode = "trans_search"; 
    acItems = []; 
    acIndex = -1;
    
    if(key.length < 1) { hideAC(); return; }

    var xhr = new XMLHttpRequest();
    // Mengarahkan langsung ke file pemroses JSON terpisah
    xhr.open("GET", "search_trans.php?q=" + enc(key), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if(res.success) {
                    acItems = res.rows || [];
                    positionAC(input);
                    renderAC(function(r) { 
                        return "<b>" + htmlEncode(r.TRAN_DOC) + "</b><div class='autocomplete-sub'>Tanggal: " + htmlEncode(r.TRAN_DATE) + "</div>"; 
                    }, pickTransSearch);
                } else hideAC();
            } catch(e) { 
                console.log("Error parsing JSON:", e, xhr.responseText);
                hideAC(); 
            }
        }
    };
    xhr.send(null);
}
```[cite: 2]

Dengan memisahkan proses AJAX ke dalam file `search_trans.php`, respons JSON dijamin bersih dari gangguan layout HTML halaman utama, dan fitur pencarian dokumen transaksi Anda akan langsung berfungsi normal.