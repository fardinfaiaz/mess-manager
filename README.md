# Mess-Manager

CSE470 Software Engineering project (Section 13). A web app for running shared student messes, built with Laravel MVC.

## Tech stack
- PHP 8.5, Laravel 13, MySQL (XAMPP)
- Views: Blade templates

## Setup
1. `git clone https://github.com/fardinfaiaz/mess-manager.git` and `cd mess-manager`
2. `composer install`
3. `copy .env.example .env` then `php artisan key:generate`
4. Create a MySQL database named `mess_manager` and set in `.env`:
   `DB_CONNECTION=mysql`, `DB_DATABASE=mess_manager`, `DB_USERNAME=root`, `DB_PASSWORD=`
5. `php artisan migrate`
6. `php artisan serve` and open http://127.0.0.1:8000

## MVC structure
- Routes: `routes/web.php`
- Controllers: `app/Http/Controllers` (AuthController, DashboardController)
- Models: `app/Models` (User)
- Views: `resources/views` (layouts/app, auth/login, auth/register, dashboard)

Registration, login and logout are supporting functions and are not counted among the 20 project features.