# SpaceMatch

SpaceMatch is a Laravel 12 rental marketplace for renters and property owners.

## Local development setup

Requirements: PHP 8.2 or newer with the PDO SQLite extension, Composer, and Node.js/npm if you need to rebuild frontend assets.

1. Clone the repository and install PHP dependencies:

   ```powershell
   composer install
   ```

2. Create your private local environment file and SQLite database:

   ```powershell
   Copy-Item .env.example .env
   New-Item -ItemType File database\database.sqlite -Force
   ```

   Do not commit `.env` or `database\database.sqlite`.

3. Generate your local application key, apply migrations, and optionally load synthetic development records:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   ```

   The development seeder runs only in the local environment. It creates synthetic renter, owner, property, space, booking, and pending-payment records. It is safe to rerun and does not create an administrator account. Do not use `migrate:fresh` on a database containing data you want to keep.

4. Start the application:

   ```powershell
   php artisan serve
   ```

   Visit <http://127.0.0.1:8000>.

## Team database workflow

Each developer should use their own local database. The migration files in `database/migrations` are the source of truth for the schema; commit and push new migrations with the code changes that require them. After pulling, each developer runs:

```powershell
composer install
php artisan migrate
```

To create the same synthetic sample records locally, run `php artisan db:seed`. No real users, passwords, OTPs, or local database contents are shared through GitHub.

Migrations synchronize database structure, not live records. Seeders are for safe, repeatable development fixtures, not for copying account or rental data between developers. If a coworker needs actual records, create and privately share a reviewed, sanitized export instead; never put production data or database credentials in Git.

### Optional: use a local MySQL server

The default setup uses SQLite and does not require a MySQL server. To use your own local MySQL or XAMPP installation:

1. Create an empty database named `spacematch_db` in MySQL/phpMyAdmin.
2. Ensure the PHP `pdo_mysql` extension is enabled.
3. Set these values in your private `.env`, using the credentials configured on your machine:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=spacematch_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. Apply the project schema and optional synthetic data:

   ```powershell
   php artisan config:clear
   php artisan migrate --seed
   php artisan migrate:status
   ```

This still creates an independent database for each developer. Sharing live records requires a separately provisioned shared database server and is not provided by GitHub migrations.

## Tests

Run the application test suite with:

```powershell
php artisan test
```
