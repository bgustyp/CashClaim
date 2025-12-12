<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 * @version   2.0.0
 */

// Application Version
define('APP_VERSION', '2.0.0');
define('APP_VERSION_DATE', '2025-12-12');

session_start();

// ============================================
// URL Configuration
// ============================================

/**
 * Clean URL Mode
 * 
 * Set to true untuk URL tanpa .php extension (butuh web server config)
 * Set to false untuk URL dengan .php extension (tanpa web server config)
 * 
 * Contoh:
 * - true:  /dashboard, /users, /report
 * - false: /dashboard.php, /users.php, /report.php
 */
define('CLEAN_URL', true);

/**
 * Helper function untuk generate URL
 * 
 * @param string $page Nama halaman tanpa extension
 * @return string URL yang sudah disesuaikan dengan config
 */
function url($page) {
    if (CLEAN_URL) {
        // Special case: index.php jadi root /
        if ($page === 'index') {
            return '/';
        }
        return '/' . $page;
    } else {
        return $page . '.php';
    }
}

// ============================================
// Database Configuration
// ============================================

/**
 * Database file location
 */
define('DB_FILE', __DIR__ . '/pettycash.db');

// ============================================
// Application Configuration
// ============================================

/**
 * Application name
 */
define('APP_NAME', 'CashClaim');
define('APP_FOOTER', 'CashClaim');

/**
 * Default admin credentials
 */
define('DEFAULT_ADMIN_USER', 'Admin');
define('DEFAULT_ADMIN_PIN', '1234');
