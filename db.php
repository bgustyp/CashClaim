<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */
$dbFile = __DIR__ . '/pettycash.db';
$pdo = null;

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create expenses table
    $query = "CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT NOT NULL,
        amount REAL NOT NULL,
        type TEXT DEFAULT 'expense',
        user TEXT DEFAULT 'Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query);

    // Create users table
    $queryUsers = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        access_code TEXT DEFAULT '1234'
    )";
    $pdo->exec($queryUsers);

    // Create reimbursements table
    $queryReimbursements = "CREATE TABLE IF NOT EXISTS reimbursements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user TEXT NOT NULL,
        date TEXT NOT NULL,
        category TEXT NOT NULL,
        description TEXT NOT NULL,
        amount INTEGER NOT NULL,
        status TEXT DEFAULT 'pending',
        notes TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME,
        processed_by TEXT
    )";
    $pdo->exec($queryReimbursements);

    // Migrations
    $columns = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('type', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN type TEXT DEFAULT 'expense'");
    }
    if (!in_array('user', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN user TEXT DEFAULT 'Admin'");
    }

    // Migration: Add access_code to users if missing
    $userColumns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('access_code', $userColumns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN access_code TEXT DEFAULT '1234'");
    }

    // Ensure Admin exists
    $checkAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE name = 'Admin'")->fetchColumn();
    if ($checkAdmin == 0) {
        $pdo->exec("INSERT INTO users (name, access_code) VALUES ('Admin', '1234')");
    }

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
