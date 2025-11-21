# Config-Based URL System

## Cara Pakai

Buka file `config.php` dan ubah nilai `CLEAN_URL`:

```php
// Set ke true untuk URL tanpa .php (butuh web server config)
define('CLEAN_URL', true);

// Set ke false untuk URL dengan .php (tanpa web server config)
define('CLEAN_URL', false);
```

## Mode URL

### Mode 1: Clean URL (CLEAN_URL = true)

- URL: `/dashboard`, `/users`, `/report`
- **Butuh konfigurasi web server** (Apache atau Nginx)
- Sudah otomatis jalan di PHP built-in server

### Mode 2: Standard URL (CLEAN_URL = false)

- URL: `/dashboard.php`, `/users.php`, `/report.php`
- **Tidak butuh** konfigurasi web server
- Langsung jalan di semua environment

## Web Server Configuration

### Apache (.htaccess)

Buat file `.htaccess` di root project:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^(.+)$ $1.php [L,QSA]
```

### Nginx

Tambahkan di nginx config:

```nginx
location / {
    try_files $uri $uri.html $uri/ @extensionless-php;
    index index.html index.htm index.php;
}

location @extensionless-php {
    rewrite ^(.+)$ $1.php last;
}
```

## Catatan

- Semua link internal sudah menggunakan fungsi `url()`
- Tinggal toggle `CLEAN_URL` di `config.php`
- Tidak perlu edit file lain
- Helper function `url()` otomatis menambah `.php` kalau `CLEAN_URL = false`
