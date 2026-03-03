<?php
session_start();
session_unset();
session_destroy();
// Redirect ke Login QC
header("Location: login.php");
exit;
?>