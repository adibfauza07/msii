<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();
}

/* =========================
   SESSION LOGIN
========================= */
$login_user_id    = isset($_SESSION['user_id'])
    ? $_SESSION['user_id']
    : 0;

$login_username   = isset($_SESSION['username'])
    ? $_SESSION['username']
    : '';

$login_role       = isset($_SESSION['role'])
    ? $_SESSION['role']
    : 'user';

$login_department = isset($_SESSION['department'])
    ? trim($_SESSION['department'])
    : '';

/* =========================
   FILTER DEPARTMENT
========================= */
function departmentFilter(&$sql, &$params)
{
    global $login_role, $login_department;

    if ($login_role != 'admin') {

        $sql .= " AND department = ?";
        $params[] = $login_department;
    }
}

/* =========================
   VALIDASI PROJECT ACCESS
========================= */
function validateProjectAccess($conn, $project_id)
{
    global $login_role, $login_department;

    /* ADMIN bebas akses */
    if ($login_role == 'admin') {
        return true;
    }

    $sql = "
    SELECT id
    FROM dbo.it_projects
    WHERE id = ?
    AND department = ?
    ";

    $params = array(
        $project_id,
        $login_department
    );

    $q = sqlsrv_query($conn, $sql, $params);

    if ($q === false) {

        die(print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array(
        $q,
        SQLSRV_FETCH_ASSOC
    );

    if (!$row) {

        die("
        <div style='
            padding:30px;
            font-family:Arial;
            text-align:center;
        '>

            <h2 style='color:red'>
                ACCESS DENIED
            </h2>

            <p>
                Anda tidak memiliki akses
                ke project department lain.
            </p>

        </div>
        ");
    }

    return true;
}
?>