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

// Admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'Admin') {
    header("Location: " . url('dashboard'));
    exit;
}

// Get filter
$filterMonth = $_GET['filter_month'] ?? date('Y-m');

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for each user
$reportData = [];
$grandTotalIncome = 0;
$grandTotalExpense = 0;
$grandBalance = 0;

foreach ($users as $user) {
    $userName = $user['name'];
    
    // Get monthly stats
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
        FROM expenses 
        WHERE user = ? AND strftime('%Y-%m', date) = ?
    ");
    $stmt->execute([$userName, $filterMonth]);
    $monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get overall balance (all time)
    $stmtBalance = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
        FROM expenses 
        WHERE user = ?
    ");
    $stmtBalance->execute([$userName]);
    $balanceStats = $stmtBalance->fetch(PDO::FETCH_ASSOC);
    $balance = ($balanceStats['total_income'] ?? 0) - ($balanceStats['total_expense'] ?? 0);
    
    // Get transactions for the month
    $stmtTrans = $pdo->prepare("
        SELECT * FROM expenses 
        WHERE user = ? AND strftime('%Y-%m', date) = ?
        ORDER BY date DESC, id DESC
    ");
    $stmtTrans->execute([$userName, $filterMonth]);
    $transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);
    
    $reportData[] = [
        'user' => $userName,
        'monthly_income' => $monthlyStats['total_income'] ?? 0,
        'monthly_expense' => $monthlyStats['total_expense'] ?? 0,
        'balance' => $balance,
        'transactions' => $transactions
    ];
    
    $grandTotalIncome += ($monthlyStats['total_income'] ?? 0);
    $grandTotalExpense += ($monthlyStats['total_expense'] ?? 0);
    $grandBalance += $balance;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Petty Cash - <?= date('F Y', strtotime($filterMonth)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page-break { page-break-after: always; }
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
        }
        .report-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .report-header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .report-title {
            font-size: 20px;
            font-weight: bold;
            margin: 0;
        }
        .report-subtitle {
            font-size: 14px;
            margin: 5px 0;
        }
        .user-section {
            margin-bottom: 30px;
            border: 1px solid #000;
            padding: 15px;
        }
        .user-header {
            font-weight: bold;
            font-size: 14px;
            background: #000;
            color: #fff;
            padding: 8px;
            margin: -15px -15px 15px -15px;
        }
        .summary-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .summary-table td {
            padding: 3px 0;
        }
        .summary-table td:first-child {
            width: 200px;
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .transaction-table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-weight: bold;
        }
        .transaction-table td {
            border-bottom: 1px dotted #ccc;
            padding: 5px;
        }
        .text-right { text-align: right; }
        .grand-total {
            border-top: 3px double #000;
            margin-top: 20px;
            padding-top: 15px;
            font-weight: bold;
            font-size: 14px;
        }
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

<!-- Print & Back Buttons -->
<div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer"></i> Print
    </button>
    <a href="<?= url('dashboard') ?>" class="btn btn-secondary">Kembali</a>
</div>

<div class="report-container">
    <!-- Report Header -->
    <div class="report-header">
        <p class="report-title">LAPORAN PETTY CASH MANAGEMENT</p>
        <p class="report-subtitle">Periode: <?= date('F Y', strtotime($filterMonth)) ?></p>
        <p class="report-subtitle">Tanggal Cetak: <?= date('d F Y, H:i') ?> WIB</p>
    </div>

    <!-- User Sections -->
    <?php foreach ($reportData as $data): ?>
        <div class="user-section">
            <div class="user-header">USER: <?= strtoupper($data['user']) ?></div>
            
            <!-- Summary -->
            <table class="summary-table">
                <tr>
                    <td>Saldo Saat Ini</td>
                    <td>: Rp <?= number_format($data['balance'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Pemasukan Bulan Ini</td>
                    <td>: Rp <?= number_format($data['monthly_income'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Pengeluaran Bulan Ini</td>
                    <td>: Rp <?= number_format($data['monthly_expense'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Total Transaksi</td>
                    <td>: <?= count($data['transactions']) ?> item</td>
                </tr>
            </table>

            <!-- Transactions -->
            <?php if (!empty($data['transactions'])): ?>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Tanggal</th>
                            <th style="width: 100px;">Kategori</th>
                            <th>Keterangan</th>
                            <th style="width: 60px;">Tipe</th>
                            <th class="text-right" style="width: 120px;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['transactions'] as $trx): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($trx['date'])) ?></td>
                                <td><?= $trx['category'] ?></td>
                                <td><?= $trx['description'] ?></td>
                                <td><?= $trx['type'] === 'income' ? 'MASUK' : 'KELUAR' ?></td>
                                <td class="text-right">Rp <?= number_format($trx['amount'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin: 10px 0;">- Tidak ada transaksi bulan ini -</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Grand Total -->
    <div class="grand-total">
        <table class="summary-table">
            <tr>
                <td>TOTAL SALDO SEMUA USER</td>
                <td>: Rp <?= number_format($grandBalance, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>TOTAL PEMASUKAN BULAN INI</td>
                <td>: Rp <?= number_format($grandTotalIncome, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>TOTAL PENGELUARAN BULAN INI</td>
                <td>: Rp <?= number_format($grandTotalExpense, 0, ',', '.') ?></td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #666;">
        <p>--- Laporan ini dicetak otomatis oleh sistem Petty Cash Management ---</p>
        <p>Copyleft <?= date('Y') ?> - Made with love in Indonesia</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
