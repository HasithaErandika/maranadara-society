<?php
define('APP_START', true);
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maranadara Society</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>