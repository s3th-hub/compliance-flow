<?php
// includes/header.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Compliance Platform' ?> | BizComply</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<div class="d-flex" id="wrapper">
    <nav id="sidebar" class="bg-dark text-white d-flex flex-column p-3" style="min-height:100vh;min-width:240px;">
        <a href="<?= BASE_URL ?>/index.php" class="d-flex align-items-center mb-4 text-white text-decoration-none">
            <i class="bi bi-shield-check fs-4 me-2 text-success"></i>
            <span class="fs-5 fw-bold">BizComply</span>
        </a>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="<?= BASE_URL ?>/index.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/admin/businesses.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'businesses.php') ? 'active' : '' ?>"><i class="bi bi-building me-2"></i>Businesses</a></li>
            <li><a href="<?= BASE_URL ?>/compliance/records.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'records.php') ? 'active' : '' ?>"><i class="bi bi-clipboard2-check me-2"></i>Compliance</a></li>
            <li><a href="<?= BASE_URL ?>/documents/index.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'],'documents') !== false) ? 'active' : '' ?>"><i class="bi bi-folder2-open me-2"></i>Documents</a></li>
            <li><a href="<?= BASE_URL ?>/reports/index.php" class="nav-link text-white <?= (strpos($_SERVER['PHP_SELF'],'reports') !== false) ? 'active' : '' ?>"><i class="bi bi-bar-chart-line me-2"></i>Reports</a></li>
            <?php if ($user['role'] === 'admin'): ?>
            <li class="mt-2"><hr class="text-secondary"></li>
            <li><a href="<?= BASE_URL ?>/admin/users.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'users.php') ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Users</a></li>
            <li><a href="<?= BASE_URL ?>/admin/categories.php" class="nav-link text-white <?= (basename($_SERVER['PHP_SELF']) === 'categories.php') ? 'active' : '' ?>"><i class="bi bi-tags me-2"></i>Categories</a></li>
            <?php endif; ?>
        </ul>
        <hr class="text-secondary">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-circle fs-5"></i>
            <div>
                <div class="small fw-semibold"><?= clean($user['name']) ?></div>
                <div class="text-secondary" style="font-size:.75rem;"><?= ucfirst($user['role']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="ms-auto text-danger" title="Logout"><i class="bi bi-box-arrow-right fs-5"></i></a>
        </div>
    </nav>

    <!-- Page Content -->
    <div id="page-content" class="flex-grow-1 bg-light">
        <div class="container-fluid p-4">
