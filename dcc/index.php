<?php
// ==========================================
// KONFIGURASI API
// ==========================================
$api_key = 'AQ.Ab8RN6IFCEgNQACesASZLcHka1WI9RTvFyU1hxZ-y-W5F-RDiw'; 

$hasil_analisis = '';
$input_kasus = '';

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['kasus'])) {
    $input_kasus = htmlspecialchars($_POST['kasus']);
    
    // Endpoint sesuai dengan cURL dari Google AI Studio (tanpa ?key= di URL)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
    
    // Instruksi Sistem (Persona Senior Auditor IMS)
    $system_instruction = "You are an expert Senior Auditor and Integrated Management System (IMS) Consultant (ISO 9001:2015, IATF 16949:2016, and ISO 14001:2015). 
    Tugas Anda adalah menganalisis kasus audit yang diberikan. Petakan klausul, nilai risiko, dan buat tindakan perbaikan (8D, Poka-Yoke).
    PENTING: Output Anda HARUS strictly dalam format HTML menggunakan tag <h2>, <h3>, <p>, <ul>, <li>, dan <table>. Gunakan class CSS dasar jika perlu. Jangan gunakan tag Markdown (seperti ** atau ##). Gunakan Bahasa Indonesia.";

    // Data yang dikirim ke Gemini
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $system_instruction . "\n\nBERIKUT ADALAH KASUS AUDIT DARI USER:\n" . $input_kasus]
                ]
            ]
        ]
    ];

    // Eksekusi cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    // Menambahkan header X-goog-api-key sesuai perintah cURL Google
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $api_key
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        $hasil_analisis = "<div class='alert-error'>Error koneksi API: " . $error . "</div>";
    } else {
        $response_data = json_decode($response, true);
        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            $hasil_analisis = $response_data['candidates'][0]['content']['parts'][0]['text'];
            
            // Membersihkan tag markdown blockquote bawaan API jika ada
            $hasil_analisis = preg_replace('/```html\s*/', '', $hasil_analisis);$hasil_analisis = preg_replace('/```/', '', $hasil_analisis);
        } else {
            $hasil_analisis = "<div class='alert-error'>
                <strong>Gagal memproses respons dari AI.</strong><br>
                Detail Error dari Server Google:<br>
                <code>" . htmlspecialchars($response) . "</code>
            </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS AI Assistant - Analisis Temuan</title>
    <link rel="stylesheet" href="../assets/bootstrap/bootstrap.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .chat-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .header { background: #1a5276; color: white; padding: 20px; text-align: center; }
        .header h2 { margin: 0; font-size: 1.5rem; }
        .content-area { padding: 30px; }
        .input-box textarea { width: 100%; border-radius: 8px; border: 1px solid #ced4da; padding: 15px; resize: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.075); }
        .input-box textarea:focus { border-color: #1a5276; outline: 0; box-shadow: 0 0 0 0.2rem rgba(26, 82, 118, 0.25); }
        .btn-ask { background-color: #1a5276; color: white; border: none; padding: 10px 25px; border-radius: 20px; font-weight: bold; margin-top: 15px; transition: 0.3s; }
        .btn-ask:hover { background-color: #123c57; }
        .result-box { margin-top: 40px; border-top: 2px dashed #eee; padding-top: 30px; }
        
        .result-box h2 { color: #1a5276; font-size: 1.3rem; border-bottom: 2px solid #1a5276; padding-bottom: 5px; margin-top: 20px; }
        .result-box h3 { color: #34495e; font-size: 1.1rem; margin-top: 15px; }
        .result-box table { width: 100%; margin-top: 10px; margin-bottom: 20px; border-collapse: collapse; }
        .result-box th, .result-box td { border: 1px solid #bdc3c7; padding: 10px; }
        .result-box th { background-color: #ecf0f1; }
        .alert-error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="header">
        <h2>✨ Konsultan Audit IMS (AI Assistant)</h2>
        <small>Powered by Gemini API</small>
    </div>
    
    <div class="content-area">
        <div class="input-box">
            <form method="POST" action="">
                <label for="kasus" class="form-label" style="font-weight: bold; color: #2c3e50;">Ceritakan temuan audit atau masalah operasional di sini:</label>
                <textarea name="kasus" id="kasus" rows="4" placeholder="Ketik deskripsi masalah di sini... (Contoh: Ditemukan operator membuang cairan sisa produksi ke drainase umum tanpa proses filtrasi...)" required><?= $input_kasus ?></textarea>
                <div class="text-end">
                    <button type="submit" class="btn-ask">Analisis Temuan 🚀</button>
                </div>
            </form>
        </div>

        <?php if (!empty($hasil_analisis)): ?>
            <div class="result-box">
                <?= $hasil_analisis ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>