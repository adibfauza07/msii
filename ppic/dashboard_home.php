<?php
if (session_id() == "") {
    session_start();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PPIC Dashboard</title>
    <style>
        body {
            margin: 0;
            padding: 25px;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
        }

        .box {
            background: #ffffff;
            border: 1px solid #888888;
            padding: 20px;
            max-width: 900px;
        }

        h2 {
            margin-top: 0;
            color: #000080;
        }

        .info {
            line-height: 22px;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>PPIC SYSTEM - PLANT 2</h2>

    <div class="info">
        Selamat datang, <b><?php echo h($dbUser); ?></b><br>
        Silakan pilih menu di sebelah kiri.
    </div>
</div>

</body>
</html>