# CashClaim Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-12-12

### Security (BREAKING CHANGES)

- **Password Hashing**: All passwords now use bcrypt hashing
  - Automatic migration on first page load
  - Existing passwords will be hashed transparently
- **CSRF Protection**: Added CSRF tokens to all forms
- **Input Sanitization**: All user inputs are now sanitized
- **Session Management**: Improved session handling with helper functions

### Added

- **Quick Amount Buttons**: One-click amount selection (10K, 20K, 50K, 100K, 200K)
- **Auto-Clear Forms**: Forms automatically clear after successful submission
- **Project Memory**: System remembers last selected project
- **Loading States**: Visual feedback on button clicks
- **Enhanced Confirmations**: Delete confirmations now show transaction details
- **Clean URLs**: Filter state stored in session instead of URL parameters
- **Database Indexes**: Added indexes for better query performance
  - `idx_expenses_user`
  - `idx_expenses_project`
  - `idx_expenses_date`
  - `idx_reimbursements_user`
  - `idx_reimbursements_status`

### Fixed

- **Duplicate Main Project**: Fixed issue where users could see duplicate Main projects
- **Database Schema**: Fixed duplicate field declarations in expenses table
- **Project Creation**: All new users now automatically get a Main project
- **Transfer Validation**: Added validation to ensure target user exists before transfer
- **Receiver Project**: Transfer recipients now automatically get Main project if missing

### Changed

- Filter forms now use POST instead of GET (cleaner URLs)
- Project selection now uses JavaScript POST submission
- Export and share links updated to work with session-based filters

### Technical

- Added `security.php` helper library with:
  - Password hashing functions
  - CSRF token management
  - Input sanitization helpers
  - Session management functions
  - Validation helpers
- Updated all core files to use security helpers
- Improved code organization and maintainability

### Performance

- Database queries optimized with indexes
- Reduced page load time for filtered views
- Better memory usage with indexed lookups

---

## [1.0.0] - 2025-11-22

### Initial Release

- Basic petty cash management
- User authentication with PIN
- Transaction tracking (income/expense)
- Project-based budgeting
- Reimbursement workflow
- Admin user management
- CSV export
- WhatsApp sharing
- Print reports
- Bootstrap 5 UI
- SQLite database
