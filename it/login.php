<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['db_user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['btnMasuk'])) {

    $temp_username = trim($_POST['username']);
    $temp_password = $_POST['password'];
    $selected_plant = $_POST['plant'];

    $serverName = ($selected_plant == 'p1')
        ? "192.168.0.4"
        : "192.168.0.9";

    $connectionString = "sqlsrv:Server=$serverName;Database=msData";

    try {

        $conn = new PDO(
            $connectionString,
            $temp_username,
            $temp_password
        );

        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = $selected_plant;
        $_SESSION['module'] = "IT";

        header("Location: dashboard.php");
        exit();

    } catch (PDOException $e) {

        $error = "Username / Password Salah";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login IT Project</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        body{
            background:#eef2f7;
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:Arial;
        }

        .login-box{
            width:100%;
            max-width:420px;
            background:white;
            padding:40px;
            border-radius:20px;
            box-shadow:0 10px 30px rgba(0,0,0,0.1);
        }

        .title{
            text-align:center;
            margin-bottom:30px;
        }

        .title h2{
            font-weight:bold;
            color:#2563eb;
        }

        .title p{
            color:#64748b;
            font-size:14px;
        }

        .btn-login{
            background:#2563eb;
            border:none;
            padding:12px;
            font-weight:bold;
        }

        .btn-login:hover{
            background:#1d4ed8;
        }

    </style>

</head>
<body>

<div class="login-box">

    <div class="title">
        <h2>IT PROJECT</h2>
        <p>Monitoring Software Internal</p>
    </div>

    <?php if($error != ""){ ?>

        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>

    <?php } ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label">Plant</label>

            <select name="plant" class="form-select" required>
    <option value="p1">PLANT 1</option>
    <option value="p2" selected>PLANT 2</option>
</select>
        </div>

        <div class="mb-3">

            <label class="form-label">Username</label>

            <div class="input-group">

                <span class="input-group-text">
                    <i class="bi bi-person"></i>
                </span>

                <input type="text"
                name="username"
                class="form-control"
                required>

            </div>

        </div>

        <div class="mb-4">

            <label class="form-label">Password</label>

            <div class="input-group">

                <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                </span>

                <input type="password"
                name="password"
                class="form-control"
                required>

            </div>

        </div>

        <div class="d-grid">

            <button type="submit"
            name="btnMasuk"
            class="btn btn-primary btn-login">

                LOGIN
                <i class="bi bi-box-arrow-in-right"></i>

            </button>

        </div>

        <div class="d-grid mt-2">

            <a href="../index.php"
            class="btn btn-outline-secondary">

                Kembali Portal

            </a>

        </div>

    </form>

</div>

</body>
</html>