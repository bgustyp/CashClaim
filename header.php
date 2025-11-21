<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="googlebot" content="noindex">
    <title>CashClaim</title>
    <link rel="icon" type="image/png" href="assets/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg" />
    <link rel="shortcut icon" href="assets/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png" />
    <link rel="manifest" href="assets/site.webmanifest" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= time() ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light mb-4 fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?= url('index') ?>">
            <i class="bi bi-wallet2 me-2 text-primary"></i> CashClaim
        </a>
        <?php if(isset($_SESSION['user'])): ?>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">Halo, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></span>
                <a href="<?= url('index') ?>?logout=1" class="btn btn-sm btn-outline-danger">Ganti User</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

