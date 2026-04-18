# Skaie API (Laravel 11 + Passport)

This is the backend API for the Skaie project, built with Laravel 11 and OAuth2 Authentication via Passport.

## 🛠 Prerequisites

Ensure you have the following installed:
- **PHP 8.3 or 8.4**
- **Composer**
- **Node.js & NPM**
- **MySQL** (XAMPP, WAMP, or standalone)

### ⚠️ Windows PHP Configuration
For this project to run on Windows, you **must** enable specific extensions in your `php.ini` file.

1. Locate your `php.ini` (run `php --ini` to find it).
2. Set your extension directory to an absolute path:
   `extension_dir = "C:\your-php-folder\ext"`
3. Enable (remove the `;`) the following extensions:
   - `extension=curl`
   - `extension=openssl`
   - `extension=mbstring`
   - `extension=pdo_mysql`
   - `extension=sodium` (Required for Passport/JWT)
   - `extension=fileinfo`

## 🚀 Installation & Setup

Follow these steps to get your local environment running:

### 1. Clone the repository
```bash
git clone <your-repo-url>
cd skaie

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

cp .env.example .env
```

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=skaie
DB_USERNAME=root
DB_PASSWORD=

``` bash
# Create application key
php artisan key:generate

# Run migrations
php artisan migrate

# Install Passport keys and clients
# When asked for the user provider, choose '0' (users)
php artisan passport:install

composer run dev
```