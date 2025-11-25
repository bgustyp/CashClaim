<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */
session_start();
require_once __DIR__ . '/config.php';
require 'db.php';

// Strict Access Control
if (!isset($_SESSION['user'])) {
    header("Location: " . url('index'));
    exit;
}

$currentUser = $_SESSION['user'];
$isAdmin = ($currentUser === 'Admin');

$currentMonth = date('Y-m');
$filterMonth = $_GET['filter_month'] ?? $currentMonth;
$filterUser = $_GET['filter_user'] ?? ''; // Only for Admin

// Handle Messages
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Build Query Conditions
$whereClauses = [];
$params = [];

// Month Filter (Always applied)
$whereClauses[] = "strftime('%Y-%m', date) = ?";
$params[] = $filterMonth;

// User Filter Logic
if ($isAdmin) {
    // Admin can see all, or filter by specific user
    if (!empty($filterUser)) {
        $whereClauses[] = "user = ?";
        $params[] = $filterUser;
    }
    // If Admin and no filterUser, we show ALL users (Global View)
} else {
    // Normal user ONLY sees their own data
    $whereClauses[] = "user = ?";
    $params[] = $currentUser;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// 1. Fetch Stats (Based on Filters)
$stmtStats = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM expenses 
    $whereSql
");
$stmtStats->execute($params);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Calculate Balance (Global or Filtered)
// Note: Balance usually needs to be calculated from ALL TIME, not just this month.
// So we need a separate query for Balance if we want "Current Balance" vs "Monthly Flow".
// For simplicity, let's calculate "Current Balance" based on the same User Filter but ignoring Month.

$balanceWhereClauses = [];
$balanceParams = [];
if ($isAdmin) {
    if (!empty($filterUser)) {
        $balanceWhereClauses[] = "user = ?";
        $balanceParams[] = $filterUser;
    }
} else {
    $balanceWhereClauses[] = "user = ?";
    $balanceParams[] = $currentUser;
}
$balanceWhereSql = !empty($balanceWhereClauses) ? "WHERE " . implode(" AND ", $balanceWhereClauses) : "";

$stmtBalance = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM expenses 
    $balanceWhereSql
");
$stmtBalance->execute($balanceParams);
$balanceStats = $stmtBalance->fetch(PDO::FETCH_ASSOC);
$balance = ($balanceStats['total_income'] ?? 0) - ($balanceStats['total_expense'] ?? 0);


// 2. Fetch Transactions
$stmtList = $pdo->prepare("
    SELECT * FROM expenses 
    $whereSql
    ORDER BY date DESC, id DESC
");
$stmtList->execute($params);
$expenses = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Fetch Users for Admin Filter
// Fetch Users (Needed for Admin Filter AND Transfer Feature)
$usersList = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $filename = 'petty_cash_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, ['Tanggal', 'Keterangan', 'Kategori', 'Tipe', 'Jumlah', 'User']);
    
    // Data
    foreach ($expenses as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['date'])),
            $row['description'],
            $row['category'],
            $row['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
            $row['amount'],
            $row['user']
        ]);
    }
    
    fclose($output);
    exit;
}

// Generate WhatsApp Share Link
$waText = "LAPORAN PETTY CASH%0A";
$waText .= "Periode: " . date('F Y', strtotime($filterMonth)) . "%0A";
if ($isAdmin && $filterUser) {
    $waText .= "User: " . $filterUser . "%0A";
} elseif (!$isAdmin) {
    $waText .= "User: " . $currentUser . "%0A";
}
$waText .= "================================%0A";
$waText .= "Saldo: Rp " . number_format($balance, 0, ',', '.') . "%0A";
$waText .= "Pemasukan: Rp " . number_format($stats['total_income'] ?? 0, 0, ',', '.') . "%0A";
$waText .= "Pengeluaran: Rp " . number_format($stats['total_expense'] ?? 0, 0, ',', '.') . "%0A";
$waText .= "================================%0A%0A";

// Add transaction details
$waText .= "DETAIL TRANSAKSI:%0A";
if (!empty($expenses)) {
    foreach ($expenses as $row) {
        $date = date('d/m/Y', strtotime($row['date']));
        $type = $row['type'] === 'income' ? 'MASUK' : 'KELUAR';
        $amount = number_format($row['amount'], 0, ',', '.');
        $waText .= "{$date} - {$type}%0A";
        $waText .= "{$row['description']} ({$row['category']})%0A";
        $waText .= "Rp {$amount}%0A";
        $waText .= "----%0A";
    }
} else {
    $waText .= "Tidak ada transaksi%0A";
}

$waText .= "%0ATotal: " . count($expenses) . " transaksi";
$waLink = "https://wa.me/?text=" . $waText;

require 'header.php';
?>

<div class="container pb-5 mb-5">

<?= $message ?>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-4" id="mainTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pettycash-tab" data-bs-toggle="pill" data-bs-target="#pettycash" type="button" role="tab">
            <i class="bi bi-wallet2"></i> Petty Cash
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="reimbursement-tab" data-bs-toggle="pill" data-bs-target="#reimbursement" type="button" role="tab">
            <i class="bi bi-receipt"></i> Reimbursement
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="mainTabsContent">
    <!-- Petty Cash Tab -->
    <div class="tab-pane fade show active" id="pettycash" role="tabpanel">


<!-- Admin Filter Bar -->
<?php if ($isAdmin): ?>
<div class="card mb-4 bg-light border-0">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="badge bg-dark">ADMIN MODE</span>
            </div>
            <div class="col-auto">
                <label class="fw-bold small">Lihat Data User:</label>
            </div>
            <div class="col-auto flex-grow-1">
                <select name="filter_user" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Semua User (Global) --</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?= htmlspecialchars($u['name']) ?>" <?= $filterUser === $u['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#topupModal">
                    <i class="bi bi-cash-coin"></i> Top Up User
                </button>
            </div>
            <div class="col-auto">
                <a href="<?= url('report') ?>?filter_month=<?= $filterMonth ?>" class="btn btn-sm btn-info text-white" target="_blank">
                    <i class="bi bi-file-text"></i> Print Report
                </a>
            </div>
            <div class="col-auto">
                <a href="<?= url('users') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-people"></i> Kelola User
                </a>
            </div>
            <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filterMonth) ?>">
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Dashboard Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-2">
        <div class="card stat-card bg-blue border-0">
            <div class="stat-label">
                Sisa Saldo 
                <?= $isAdmin ? ($filterUser ? "($filterUser)" : "(Global)") : "Anda" ?>
            </div>
            <div class="stat-value">Rp <?= number_format($balance, 0, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card stat-card bg-green border-0">
            <div class="stat-label">Pemasukan (<?= date('M Y', strtotime($filterMonth)) ?>)</div>
            <div class="stat-value">Rp <?= number_format($stats['total_income'] ?? 0, 0, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card stat-card bg-red border-0">
            <div class="stat-label">Pengeluaran (<?= date('M Y', strtotime($filterMonth)) ?>)</div>
            <div class="stat-value">Rp <?= number_format($stats['total_expense'] ?? 0, 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Input Form (Only show if NOT Admin OR Admin selected a specific user) -->
    <!-- Actually, Admin should be able to add transaction for themselves too. -->
    <!-- Let's allow Admin to add transaction only if they select a user (to be safe) or for themselves if filter is empty? -->
    <!-- Simplest: Admin adds for themselves if no filter, or for the filtered user if selected. -->
    
    <?php 
        $targetUser = $isAdmin ? ($filterUser ?: 'Admin') : $currentUser;
    ?>

    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-plus-circle me-2"></i> Catat Transaksi</span>
                <?php if(!$isAdmin || ($isAdmin && $filterUser)): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
                        <i class="bi bi-arrow-left-right"></i> Transfer
                    </button>
                <?php endif; ?>
                <?php if($isAdmin): ?>
                    <br><small class="text-muted">Untuk: <strong><?= htmlspecialchars($targetUser) ?></strong></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form action="<?= url('process') ?>" method="POST">
                    <input type="hidden" name="action" value="add_transaction">
                    <!-- If Admin, we might need to pass the target user -->
                    <?php if($isAdmin): ?>
                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($targetUser) ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Jenis</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type" id="type-expense" value="expense" checked>
                            <label class="btn btn-outline-danger" for="type-expense">Pengeluaran</label>

                            <input type="radio" class="btn-check" name="type" id="type-income" value="income">
                            <label class="btn btn-outline-success" for="type-income">Pemasukan</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="Operasional">Operasional</option>
                            <option value="Konsumsi">Konsumsi</option>
                            <option value="Transport">Transport</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Keterangan</label>
                        <input type="text" name="description" class="form-control" placeholder="Contoh: Makan Siang" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Jumlah (Rp)</label>
                        <input type="text" name="amount" class="form-control form-control-lg fw-bold" placeholder="0" onkeyup="formatRupiah(this)" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">Simpan Transaksi</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Transaction List -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-clock-history me-2"></i> Riwayat Transaksi</span>
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Export & Share Buttons -->
                    <a href="?action=export&filter_month=<?= $filterMonth ?><?= $isAdmin && $filterUser ? '&filter_user=' . urlencode($filterUser) : '' ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </a>
                    <a href="<?= $waLink ?>" target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-whatsapp"></i> Share
                    </a>
                    
                    <!-- Month Filter -->
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <?php if($isAdmin && $filterUser): ?>
                            <input type="hidden" name="filter_user" value="<?= htmlspecialchars($filterUser) ?>">
                        <?php endif; ?>
                        <input type="month" name="filter_month" class="form-control form-control-sm" value="<?= $filterMonth ?>" onchange="this.form.submit()">
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-3 opacity-50"></i>
                                    Belum ada transaksi bulan ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $row): 
                                $isIncome = $row['type'] === 'income';
                                $colorClass = $isIncome ? 'text-success' : 'text-danger';
                                $sign = $isIncome ? '+' : '-';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?= date('d M Y', strtotime($row['date'])) ?></div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($row['category']) ?>
                                            <?php if($isAdmin && !$filterUser): ?>
                                                &bull; <i class="bi bi-person"></i> <?= htmlspecialchars($row['user']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td class="text-end fw-bold <?= $colorClass ?>">
                                        <?= $sign ?> Rp <?= number_format($row['amount'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= url('process') ?>?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('Hapus transaksi ini?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Top Up Modal (Admin Only) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Top Up Saldo User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('process') ?>" method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" name="type" value="income">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih User</label>
                        <select name="target_user" class="form-select" required>
                            <option value="">-- Pilih User --</option>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <input type="text" name="description" class="form-control" placeholder="Contoh: Top Up Saldo Awal" value="Top Up Saldo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah (Rp)</label>
                        <input type="text" name="amount" class="form-control form-control-lg text-center fw-bold" placeholder="0" onkeyup="formatRupiah(this)" required>
                    </div>
                    <input type="hidden" name="category" value="Top Up">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-cash-coin"></i> Top Up Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

    </div> <!-- End Petty Cash Tab -->

    <!-- Reimbursement Tab -->
    <div class="tab-pane fade" id="reimbursement" role="tabpanel">
        <?php
        // Fetch Reimbursement Data
        if ($isAdmin) {
            // Admin sees all
            $filterUserReimb = $_GET['filter_user_reimb'] ?? '';
            if ($filterUserReimb) {
                $stmtReimb = $pdo->prepare("SELECT * FROM reimbursements WHERE user = ? ORDER BY submitted_at DESC");
                $stmtReimb->execute([$filterUserReimb]);
            } else {
                $stmtReimb = $pdo->query("SELECT * FROM reimbursements ORDER BY submitted_at DESC");
            }
            $reimbursements = $stmtReimb->fetchAll(PDO::FETCH_ASSOC);
            
            // Stats
            $statsPending = $pdo->query("SELECT COUNT(*) as count, SUM(amount) as total FROM reimbursements WHERE status = 'pending'")->fetch();
            $statsApproved = $pdo->query("SELECT COUNT(*) as count, SUM(amount) as total FROM reimbursements WHERE status = 'approved'")->fetch();
            $statsRejected = $pdo->query("SELECT COUNT(*) as count FROM reimbursements WHERE status = 'rejected'")->fetch();
        } else {
            // User sees only their own
            $stmtReimb = $pdo->prepare("SELECT * FROM reimbursements WHERE user = ? ORDER BY submitted_at DESC");
            $stmtReimb->execute([$currentUser]);
            $reimbursements = $stmtReimb->fetchAll(PDO::FETCH_ASSOC);
            
            // Stats
            $statsPending = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM reimbursements WHERE user = ? AND status = 'pending'");
            $statsPending->execute([$currentUser]);
            $statsPending = $statsPending->fetch();
            
            $statsApproved = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM reimbursements WHERE user = ? AND status = 'approved'");
            $statsApproved->execute([$currentUser]);
            $statsApproved = $statsApproved->fetch();
        }
        ?>

        <!-- Admin Filter (Reimbursement) -->
        <?php if ($isAdmin): ?>
        <div class="card mb-4 bg-light border-0">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <span class="badge bg-dark">ADMIN MODE</span>
                    </div>
                    <div class="col-auto">
                        <label class="fw-bold small">Filter User:</label>
                    </div>
                    <div class="col-auto flex-grow-1">
                        <select name="filter_user_reimb" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Semua User --</option>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= htmlspecialchars($u['name']) ?>" <?= ($filterUserReimb ?? '') === $u['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="tab" value="reimbursement">
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-2">
                <div class="card stat-card bg-blue border-0">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= $statsPending['count'] ?? 0 ?> item</div>
                    <small>Rp <?= number_format($statsPending['total'] ?? 0, 0, ',', '.') ?></small>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="card stat-card bg-green border-0">
                    <div class="stat-label">Approved</div>
                    <div class="stat-value"><?= $statsApproved['count'] ?? 0 ?> item</div>
                    <small>Rp <?= number_format($statsApproved['total'] ?? 0, 0, ',', '.') ?></small>
                </div>
            </div>
            <?php if ($isAdmin): ?>
            <div class="col-md-4 mb-2">
                <div class="card stat-card bg-red border-0">
                    <div class="stat-label">Rejected</div>
                    <div class="stat-value"><?= $statsRejected['count'] ?? 0 ?> item</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Submit Form (Users) -->
            <?php if (!$isAdmin): ?>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-plus-circle me-2"></i> Ajukan Reimbursement
                    </div>
                    <div class="card-body">
                        <form action="<?= url('reimbursement') ?>" method="POST">
                            <input type="hidden" name="action" value="submit_reimbursement">
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Tanggal</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Kategori</label>
                                <select name="category" class="form-select">
                                    <option value="Operasional">Operasional</option>
                                    <option value="Konsumsi">Konsumsi</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Keterangan</label>
                                <input type="text" name="description" class="form-control" placeholder="Contoh: Bensin perjalanan dinas" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Jumlah (Rp)</label>
                                <input type="text" name="amount" class="form-control form-control-lg fw-bold" placeholder="0" onkeyup="formatRupiah(this)" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">Ajukan Reimbursement</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reimbursement List -->
            <div class="<?= $isAdmin ? 'col-12' : 'col-lg-8' ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul me-2"></i> <?= $isAdmin ? 'Semua Reimbursement' : 'Riwayat Reimbursement' ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <?php if ($isAdmin): ?><th>User</th><?php endif; ?>
                                    <th>Keterangan</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-center">Status</th>
                                    <?php if ($isAdmin): ?><th class="text-center">Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reimbursements)): ?>
                                    <tr>
                                        <td colspan="<?= $isAdmin ? 6 : 5 ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-6 d-block mb-3 opacity-50"></i>
                                            Belum ada reimbursement.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reimbursements as $reimb): 
                                        $statusBadge = [
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'paid' => 'bg-info'
                                        ];
                                        $statusText = [
                                            'pending' => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            'paid' => 'Paid'
                                        ];
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= date('d M Y', strtotime($reimb['date'])) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($reimb['category']) ?></div>
                                            </td>
                                            <?php if ($isAdmin): ?>
                                                <td><?= htmlspecialchars($reimb['user']) ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <?= htmlspecialchars($reimb['description']) ?>
                                                <?php if ($reimb['notes']): ?>
                                                    <br><small class="text-muted"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($reimb['notes']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-bold">Rp <?= number_format($reimb['amount'], 0, ',', '.') ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $statusBadge[$reimb['status']] ?>"><?= $statusText[$reimb['status']] ?></span>
                                            </td>
                                            <?php if ($isAdmin && $reimb['status'] === 'pending'): ?>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $reimb['id'] ?>">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $reimb['id'] ?>">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </td>
                                            <?php elseif ($isAdmin && $reimb['status'] === 'approved'): ?>
                                                <td class="text-center">
                                                    <form action="reimbursement" method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="mark_paid">
                                                        <input type="hidden" name="reimbursement_id" value="<?= $reimb['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-info text-white" onclick="return confirm('Tandai sebagai dibayar?')">
                                                            <i class="bi bi-cash"></i> Paid
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php elseif ($isAdmin): ?>
                                                <td class="text-center">-</td>
                                            <?php endif; ?>
                                        </tr>

                                        <!-- Approve Modal -->
                                        <?php if ($isAdmin && $reimb['status'] === 'pending'): ?>
                                        <div class="modal fade" id="approveModal<?= $reimb['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Reimbursement</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="<?= url('reimbursement') ?>" method="POST">
                                                        <input type="hidden" name="action" value="approve_reimbursement">
                                                        <input type="hidden" name="reimbursement_id" value="<?= $reimb['id'] ?>">
                                                        <div class="modal-body">
                                                            <p><strong>User:</strong> <?= htmlspecialchars($reimb['user']) ?></p>
                                                            <p><strong>Jumlah:</strong> Rp <?= number_format($reimb['amount'], 0, ',', '.') ?></p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Catatan (opsional)</label>
                                                                <textarea name="notes" class="form-control" rows="2"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-success w-100">Approve</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?= $reimb['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Reimbursement</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="<?= url('reimbursement') ?>" method="POST">
                                                        <input type="hidden" name="action" value="reject_reimbursement">
                                                        <input type="hidden" name="reimbursement_id" value="<?= $reimb['id'] ?>">
                                                        <div class="modal-body">
                                                            <p><strong>User:</strong> <?= htmlspecialchars($reimb['user']) ?></p>
                                                            <p><strong>Jumlah:</strong> Rp <?= number_format($reimb['amount'], 0, ',', '.') ?></p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Alasan Penolakan</label>
                                                                <textarea name="notes" class="form-control" rows="2" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-danger w-100">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- End Reimbursement Tab -->

</div> <!-- End Tab Content -->

<!-- Top Up Modal (Admin Only) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Top Up Saldo User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('process') ?>" method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" name="type" value="income">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih User</label>
                        <select name="target_user" class="form-select" required>
                            <option value="">-- Pilih User --</option>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <input type="text" name="description" class="form-control" placeholder="Contoh: Top Up Saldo Awal" value="Top Up Saldo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah (Rp)</label>
                        <input type="text" name="amount" class="form-control form-control-lg text-center fw-bold" placeholder="0" onkeyup="formatRupiah(this)" required>
                    </div>
                    <input type="hidden" name="category" value="Top Up">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-cash-coin"></i> Top Up Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</div> <!-- End Container -->

<?php require 'footer.php'; ?>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Saldo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('process') ?>" method="POST">
                <input type="hidden" name="action" value="transfer_balance">
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        Saldo Anda saat ini: <strong>Rp <?= number_format($balance, 0, ',', '.') ?></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Transfer Ke</label>
                        <select name="target_user" class="form-select" required>
                            <option value="">-- Pilih Penerima --</option>
                            <?php foreach ($usersList as $u): ?>
                                <?php if ($u['name'] !== $currentUser): ?>
                                    <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah (Rp)</label>
                        <div class="input-group">
                            <input type="text" name="amount" id="transferAmount" class="form-control form-control-lg fw-bold" placeholder="0" onkeyup="formatRupiah(this)" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="setMaxBalance()">Max</button>
                        </div>
                        <div class="form-text">Klik Max untuk transfer semua saldo.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <input type="text" name="description" class="form-control" placeholder="Opsional (Default: Transfer)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send"></i> Kirim Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setMaxBalance() {
    // Get current balance from PHP variable (passed as data attribute or global var would be cleaner, but parsing the alert text is hacky)
    // Let's use the raw number from PHP
    let maxBalance = <?= $balance ?>;
    if (maxBalance < 0) maxBalance = 0;
    
    // Format to rupiah for display
    let formatted = formatRupiahString(maxBalance.toString());
    document.getElementById('transferAmount').value = formatted;
}

// Helper to format string directly (reusing existing formatRupiah logic concept)
function formatRupiahString(angka) {
    var number_string = angka.replace(/[^,\d]/g, '').toString(),
    split   = number_string.split(','),
    sisa    = split[0].length % 3,
    rupiah  = split[0].substr(0, sisa),
    ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah;
}
</script>
