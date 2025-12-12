<?php
/**
 * CashClaim - Security Helper Functions
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */

// ============================================
// Password Hashing Functions
// ============================================

/**
 * Hash a password using bcrypt
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================
// CSRF Protection Functions
// ============================================

/**
 * Generate CSRF token and store in session
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 * 
 * @return string|null CSRF token or null if not set
 */
function getCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Validate CSRF token from POST request
 * 
 * @param string|null $token Token to validate (defaults to $_POST['csrf_token'])
 * @return bool True if token is valid
 */
function validateCSRFToken($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
}

/**
 * Output hidden CSRF token input field
 * 
 * @return void
 */
function csrfField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// ============================================
// Input Sanitization Functions
// ============================================

/**
 * Sanitize string input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeString($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize integer input
 * 
 * @param mixed $input Input to sanitize
 * @return int Sanitized integer
 */
function sanitizeInt($input) {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize amount (remove dots, keep only numbers)
 * 
 * @param string $input Amount string with dots
 * @return int Amount as integer
 */
function sanitizeAmount($input) {
    return (int) str_replace('.', '', $input);
}

// ============================================
// Session Management
// ============================================

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user']);
}

/**
 * Get current logged in user
 * 
 * @return string|null Username or null if not logged in
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user'] ?? null;
}

/**
 * Check if current user is admin
 * 
 * @return bool True if user is admin
 */
function isAdmin() {
    return getCurrentUser() === 'Admin';
}

/**
 * Require login - redirect to index if not logged in
 * 
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . url('index'));
        exit;
    }
}

/**
 * Require admin - redirect to dashboard if not admin
 * 
 * @return void
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header("Location: " . url('dashboard'));
        exit;
    }
}

// ============================================
// Error Handling
// ============================================

/**
 * Set success message in session
 * 
 * @param string $message Success message
 * @return void
 */
function setSuccessMessage($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">' 
        . htmlspecialchars($message) 
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Set error message in session
 * 
 * @param string $message Error message
 * @return void
 */
function setErrorMessage($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['message'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' 
        . htmlspecialchars($message) 
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Set warning message in session
 * 
 * @param string $message Warning message
 * @return void
 */
function setWarningMessage($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['message'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">' 
        . htmlspecialchars($message) 
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Get and clear message from session
 * 
 * @return string Message HTML or empty string
 */
function getMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $message = $_SESSION['message'] ?? '';
    unset($_SESSION['message']);
    
    return $message;
}

// ============================================
// Validation Functions
// ============================================

/**
 * Validate date format (YYYY-MM-DD)
 * 
 * @param string $date Date string
 * @return bool True if valid
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate required fields
 * 
 * @param array $fields Array of field names to check in $_POST
 * @return bool True if all fields are present and not empty
 */
function validateRequired($fields) {
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            return false;
        }
    }
    return true;
}

?>
