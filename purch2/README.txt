PURCHASE REPORT - PETUNJUK PEMASANGAN
===============================================

Letakkan file berikut dalam folder yang sama:

1. purchase.php
2. search_item.php
3. search_receive.php

File koneksi yang dipakai:

../config/global.php
../config/database_ordering.php

database_ordering.php WAJIB menghasilkan variabel:

$conn

Contoh minimal:

<?php
$serverName = "SERVER_SQL";
$connectionInfo = array(
    "Database" => "msData",
    "UID" => "user_database",
    "PWD" => "password_database",
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true
);
$conn = sqlsrv_connect($serverName, $connectionInfo);

LANGKAH PENTING
---------------

1. Jalankan RPT_PURCHASE_YEAR2_fix.sql di SQL Server.
2. Salin ketiga file PHP ke folder report.
3. Buka purchase.php.
4. Pilih periode yang memang mempunyai data.
5. Klik Tampilkan.

PENGUJIAN AUTOCOMPLETE
----------------------

Buka Developer Tools > Network, lalu ketik pada Item Code atau Receive No.
Harus terlihat request POST ke:

search_item.php
search_receive.php

Respons harus berupa JSON.

CATATAN KOLOM RECEIVE
---------------------

Autocomplete memakai:

dbo.RECEIVE.RCV_NOMOR

Stored procedure asli menampilkan:

R.RCV_NO AS RCV_NOMOR
R.RCV_NOMOR AS REC

Struktur ini dipertahankan agar tidak mengubah nama kolom report lama.
