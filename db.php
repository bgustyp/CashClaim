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

    // Create projects table
    $queryProjects = "CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($queryProjects);

    // Migrations
    $columns = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('type', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN type TEXT DEFAULT 'expense'");
    }
    if (!in_array('user', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN user TEXT DEFAULT 'Admin'");
    }
    if (!in_array('project_id', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN project_id INTEGER DEFAULT 1");
    }

    // Migration: Add access_code to users if missing
    $userColumns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('access_code', $userColumns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN access_code TEXT DEFAULT '1234'");
    }

    // ============================================
    // Performance: Add Database Indexes
    // ============================================
    
    // Check if indexes exist before creating
    $indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('idx_expenses_user', $indexes)) {
        $pdo->exec("CREATE INDEX idx_expenses_user ON expenses(user)");
    }
    if (!in_array('idx_expenses_project', $indexes)) {
        $pdo->exec("CREATE INDEX idx_expenses_project ON expenses(project_id)");
    }
    if (!in_array('idx_expenses_date', $indexes)) {
        $pdo->exec("CREATE INDEX idx_expenses_date ON expenses(date)");
    }
    if (!in_array('idx_reimbursements_user', $indexes)) {
        $pdo->exec("CREATE INDEX idx_reimbursements_user ON reimbursements(user)");
    }
    if (!in_array('idx_reimbursements_status', $indexes)) {
        $pdo->exec("CREATE INDEX idx_reimbursements_status ON reimbursements(status)");
    }

    // ============================================
    // Security: Migrate Plain Text Passwords to Hashed
    // ============================================
    
    // Check if we need to migrate passwords (if any password doesn't start with $2y$ which is bcrypt)
    $needsMigration = $pdo->query("SELECT COUNT(*) FROM users WHERE access_code NOT LIKE '$2y$%'")->fetchColumn();
    
    if ($needsMigration > 0) {
        // Get all users with plain text passwords
        $usersToMigrate = $pdo->query("SELECT id, access_code FROM users WHERE access_code NOT LIKE '$2y$%'")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($usersToMigrate as $user) {
            // Hash the existing password
            $hashedPassword = password_hash($user['access_code'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET access_code = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);
        }
    }

    // ============================================
    // Ensure Default Projects Exist
    // ============================================
    
    // Ensure Admin exists
    $checkAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE name = 'Admin'")->fetchColumn();
    if ($checkAdmin == 0) {
        $hashedAdminPin = password_hash('1234', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, access_code) VALUES ('Admin', '$hashedAdminPin')");
    }

    // Ensure Main project exists for Admin
    $checkAdminProject = $pdo->query("SELECT COUNT(*) FROM projects WHERE user_id = 'Admin' AND name = 'Main'")->fetchColumn();
    if ($checkAdminProject == 0) {
        $pdo->exec("INSERT INTO projects (user_id, name, description) VALUES ('Admin', 'Main', 'Default Project')");
    }

    // Ensure all users have a Main project
$stmtCheckProjects = $pdo->query("SELECT DISTINCT name FROM users"); // Changed user_id to name as users table has 'name'
$allUsers = $stmtCheckProjects->fetchAll(PDO::FETCH_COLUMN);

foreach ($allUsers as $userName) {
    // Check if user already has a Main project
    $checkMain = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND name = 'Main'");
    $checkMain->execute([$userName]);
    $hasMain = $checkMain->fetchColumn();
    
    if ($hasMain == 0) {
        // Create Main project only if it doesn't exist
        $stmtCreateMain = $pdo->prepare("INSERT INTO projects (user_id, name, description) VALUES (?, 'Main', 'Default Project')");
        $stmtCreateMain->execute([$userName]);
    }
}

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
