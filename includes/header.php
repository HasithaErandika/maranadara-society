<?php
define('APP_START', true);
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maranadara Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#">Maranadara Society</a>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/login.php?logout=1">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
<div class="container mt-4">