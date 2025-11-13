# MockAPI-PHP Deployment Guide

This guide explains how to deploy MockAPI-PHP on hosted environments (Apache, Nginx) rather than just using PHP's built-in development server.

## Table of Contents

- [Apache Deployment](#apache-deployment)
  - [Subdirectory Installation (Recommended)](#subdirectory-installation-recommended)
  - [Document Root Installation](#document-root-installation)
- [Nginx Deployment](#nginx-deployment)
- [Environment Configuration](#environment-configuration)
- [Troubleshooting](#troubleshooting)

---

## Apache Deployment

### Requirements

- Apache 2.4+
- PHP 8.3+
- `mod_rewrite` enabled
- Composer (for dependencies)

---

### Subdirectory Installation (Recommended)

This is the **standard deployment method** for MockAPI-PHP, where the repository is cloned into a subdirectory and accessed via a URL like `https://example.com/MockAPI-PHP/`.

#### Directory Structure

```
/var/www/html/                    # Document root
├── .htaccess                     # ← Place .htaccess.sample here (renamed)
├── index.html                    # Your existing site files
├── about.html
└── MockAPI-PHP/                  # ← Git cloned directory
    ├── index.php
    ├── .htaccess.sample          # Sample file (not used)
    ├── .env
    ├── responses/
    └── ...
```

#### Installation Steps

1. **Clone the repository into a subdirectory**
   ```bash
   cd /var/www/html
   git clone https://github.com/ka215/MockAPI-PHP.git
   cd MockAPI-PHP
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev
   ```

3. **Configure environment variables**
   ```bash
   cp .env.sample .env
   nano .env
   ```

   Example `.env` configuration:
   ```bash
   PORT=80
   BASE_PATH=/api        # API endpoint prefix
   LOG_DIR=./logs
   TEMP_DIR=./temp
   TIMEZONE=UTC
   ```

4. **Set up `.htaccess` in the PARENT directory**

   **Copy and rename the sample file:**
   ```bash
   cp MockAPI-PHP/.htaccess.sample .htaccess
   ```

   **Edit `.htaccess` to match your directory name:**
   ```apache
   # Change "MockAPI-PHP" to match your actual directory name if different
   RewriteRule ^MockAPI-PHP/(.*)$ MockAPI-PHP/index.php [QSA,L]
   ```

   If you cloned into a different directory name (e.g., `mock-api`):
   ```apache
   RewriteRule ^mock-api/(.*)$ mock-api/index.php [QSA,L]
   ```

5. **Set proper permissions**
   ```bash
   chmod 755 MockAPI-PHP/index.php
   chmod -R 775 MockAPI-PHP/logs/
   chmod -R 775 MockAPI-PHP/temp/
   chmod -R 775 MockAPI-PHP/schema/
   ```

6. **Verify Apache modules**
   ```bash
   # Check if mod_rewrite is enabled
   apache2ctl -M | grep rewrite
   
   # Enable if not active (on Debian/Ubuntu)
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

7. **Test your endpoints**
   ```bash
   # Test basic endpoint
   curl https://example.com/MockAPI-PHP/api/users
   
   # Test version
   curl https://example.com/MockAPI-PHP/api/version
   
   # Test with custom response
   curl "https://example.com/MockAPI-PHP/api/users?mock_response=success"
   ```

#### URL Structure

With subdirectory installation, your API endpoints follow this pattern:

```
https://example.com/[directory-name]/[BASE_PATH]/[endpoint]
```

Examples:
- `https://example.com/MockAPI-PHP/api/users`
- `https://example.com/MockAPI-PHP/api/products`
- `https://example.com/mock-api/api/version` (if cloned as "mock-api")

**Important Notes:**
- The **directory name** (e.g., `MockAPI-PHP`) is part of the URL path
- `BASE_PATH` (default: `/api`) is the API endpoint prefix
- You can change `BASE_PATH` in `.env` to customize the API prefix

---

### Document Root Installation

If you want to install MockAPI-PHP as the **main application** at the document root (e.g., `https://example.com/api/users`), follow these steps:

#### Directory Structure

```
/var/www/html/                    # Document root (MockAPI-PHP files here)
├── .htaccess                     # Create new .htaccess
├── index.php
├── .env
├── responses/
└── ...
```

#### Installation Steps

1. **Clone directly to document root**
   ```bash
   cd /var/www
   git clone https://github.com/ka215/MockAPI-PHP.git html
   cd html
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev
   ```

3. **Configure environment**
   ```bash
   cp .env.sample .env
   nano .env
   ```

4. **Create `.htaccess` in the document root**
   
   Create a new `.htaccess` file with the following content:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /
       
       # Exclude existing files and directories
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       
       # Redirect all requests to index.php
       RewriteRule ^(.*)$ index.php [QSA,L]
   </IfModule>
   
   Options -Indexes
   AddDefaultCharset UTF-8
   ```

5. **Test endpoints**
   ```bash
   curl https://example.com/api/users
   curl https://example.com/api/version
   ```

### Apache Virtual Host Configuration

For dedicated subdomain deployment (e.g., `https://api.example.com`):

```apache
<VirtualHost *:80>
    ServerName api.example.com
    DocumentRoot /var/www/MockAPI-PHP

    <Directory /var/www/MockAPI-PHP>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mockapi_error.log
    CustomLog ${APACHE_LOG_DIR}/mockapi_access.log combined
</VirtualHost>
```

---

## Nginx Deployment

### Requirements

- Nginx 1.18+
- PHP-FPM 8.3+
- Composer (for dependencies)

---

### Subdirectory Installation (Standard Method)

For subdirectory deployment with Nginx (e.g., `https://example.com/MockAPI-PHP/`):

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html;
    index index.php index.html;

    # Main site
    location / {
        try_files $uri $uri/ =404;
    }

    # MockAPI-PHP subdirectory
    # Change "MockAPI-PHP" to match your actual directory name
    location /MockAPI-PHP/ {
        try_files $uri $uri/ /MockAPI-PHP/index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive directories within MockAPI-PHP
    location ~ ^/MockAPI-PHP/(vendor|logs|temp|\.git)/ {
        deny all;
    }
}
```

---

### Document Root Installation

For installing MockAPI-PHP as the main application at document root:

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/MockAPI-PHP;
    index index.php;

    # Logging
    access_log /var/log/nginx/mockapi_access.log;
    error_log /var/log/nginx/mockapi_error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive directories
    location ~ ^/(vendor|logs|temp|\.git)/ {
        deny all;
    }
}
```

---

## Environment Configuration

### `.env` Settings

The `.env` file configuration is the same for both subdirectory and root installations:

```bash
PORT=80                    # Server port (usually 80 for HTTP, 443 for HTTPS)
BASE_PATH=/api             # API endpoint prefix (not the subdirectory path)
LOG_DIR=./logs             # Log directory
TEMP_DIR=./temp            # Temporary files directory
TIMEZONE=UTC               # Timezone for logging

# CORS Configuration
CORS_ALLOW_ORIGIN=*
CORS_ALLOW_METHODS=GET, POST, DELETE, PATCH, PUT, OPTIONS
CORS_ALLOW_HEADERS=Origin, Content-Type, Accept
CORS_ALLOW_CREDENTIALS=true

# Authentication (optional)
API_KEY=your_api_key_here
CREDENTIAL=your_credential_here
```

**Important Notes:**

- `BASE_PATH` is the **API endpoint prefix**, not the subdirectory path
- Example URL structure:
  - Document root: `https://example.com/api/users`
  - Subdirectory: `https://example.com/MockAPI-PHP/api/users`
  
  In both cases, `BASE_PATH=/api` remains the same.

- If you want endpoints without `/api` prefix:
  ```bash
  BASE_PATH=
  ```
  Then endpoints become:
  - Document root: `https://example.com/users`
  - Subdirectory: `https://example.com/MockAPI-PHP/users`

---

## Troubleshooting

### 404 Errors

**Symptom**: All requests return 404 Not Found

**Solutions**:

1. **For subdirectory installation**: Check if `.htaccess` is in the PARENT directory
   ```bash
   # Should exist here
   /var/www/html/.htaccess
   
   # NOT here
   /var/www/html/MockAPI-PHP/.htaccess
   ```

2. **Verify directory name in `.htaccess`**
   ```apache
   # Must match actual directory name
   RewriteRule ^MockAPI-PHP/(.*)$ MockAPI-PHP/index.php [QSA,L]
   ```

3. **Check if `mod_rewrite` is enabled** (Apache)
   ```bash
   apache2ctl -M | grep rewrite
   ```

4. **Test if `.htaccess` is being read**
   ```bash
   # Add this temporarily to .htaccess to test
   # If you see "500 Internal Server Error", .htaccess is working
   InvalidDirective test
   ```

5. **Check `AllowOverride` in Apache config**
   ```apache
   <Directory /var/www/html>
       AllowOverride All  # Must be "All", not "None"
   </Directory>
   ```

### Permission Errors

**Symptom**: Cannot write logs or create files

**Solution**:
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/html/MockAPI-PHP

# Set proper permissions
chmod -R 755 /var/www/html/MockAPI-PHP
chmod -R 775 /var/www/html/MockAPI-PHP/logs
chmod -R 775 /var/www/html/MockAPI-PHP/temp
chmod -R 775 /var/www/html/MockAPI-PHP/schema
```

### CORS Issues

**Symptom**: Browser blocks requests due to CORS

**Solution**:

Configure CORS in `.env`:
```bash
CORS_ALLOW_ORIGIN=https://your-frontend.com
CORS_ALLOW_METHODS=GET, POST, DELETE, PATCH, PUT, OPTIONS
CORS_ALLOW_HEADERS=Origin, Content-Type, Accept
CORS_ALLOW_CREDENTIALS=true
```

### Routing Not Working

**Symptom**: Only `index.php` works directly, but routes don't

**Checklist for subdirectory installation**:

1. ✅ `.htaccess` exists in the PARENT directory (not inside MockAPI-PHP)
2. ✅ `.htaccess` has correct `RewriteRule` with matching directory name
3. ✅ `mod_rewrite` is enabled (Apache)
4. ✅ `AllowOverride All` is set in Apache config
5. ✅ `.env` file has correct `BASE_PATH`

**Checklist for root installation**:

1. ✅ `.htaccess` exists in the document root (same directory as `index.php`)
2. ✅ `RewriteRule` points to `index.php` (not subdirectory/index.php)
3. ✅ `mod_rewrite` is enabled (Apache)
4. ✅ `AllowOverride All` is set in Apache config

### Testing Rewrite Rules

```bash
# Test if rewriting works (subdirectory)
curl -I https://example.com/MockAPI-PHP/api/users

# Test if rewriting works (root)
curl -I https://example.com/api/users

# Should return 200 OK, not 404

# Check Apache error logs
tail -f /var/log/apache2/error.log

# Check PHP error logs
tail -f /var/log/php8.3-fpm.log
```

---

## Security Recommendations

1. **Restrict access to sensitive directories**
   ```apache
   # In .htaccess
   <FilesMatch "^\.env">
       Require all denied
   </FilesMatch>
   
   <DirectoryMatch "^(logs|temp|vendor)">
       Require all denied
   </DirectoryMatch>
   ```

2. **Use HTTPS in production**
   ```bash
   # Install Let's Encrypt SSL
   sudo certbot --apache -d mockapi.example.com
   ```

3. **Enable authentication**
   ```bash
   # In .env
   API_KEY=your_secure_api_key_here
   CREDENTIAL=your_secure_credential_here
   ```

4. **Disable directory listing** (already in `.htaccess`)
   ```apache
   Options -Indexes
   ```

---

## Performance Optimization

1. **Enable OPcache** (PHP)
   ```ini
   ; In php.ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=10000
   ```

2. **Enable HTTP/2** (Apache)
   ```bash
   sudo a2enmod http2
   sudo systemctl restart apache2
   ```

3. **Use production Composer install**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

---

## Support

If you encounter issues not covered in this guide:

1. Check the [GitHub Issues](https://github.com/ka215/MockAPI-PHP/issues)
2. Review logs in `logs/` directory
3. Create a new issue with:
   - Server environment (Apache/Nginx version)
   - PHP version
   - Installation directory structure
   - Error messages from logs

---

**Related Documentation:**
- [README (Japanese)](./README_JP.md)
- [README (English)](./README.md)
- [Developer Summary](./DEVELOPER_SUMMARY_JP.md)
