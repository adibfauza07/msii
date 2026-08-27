<?php
$api_key = 'AQ.Ab8RN6KJuSPjYA3UZgan5dK1_3lH2VFuEJ_4uPzrWugPyfJiAQ'; // Masukkan key full Anda

// HAPUS ?key= dari URL
$url = 'https://generativelanguage.googleapis.com/v1beta/models';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

// TAMBAHKAN API Key melalui HTTP Header
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-goog-api-key: ' . $api_key
]);

$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>