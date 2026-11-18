# Mess-Manager

CSE470 Software Engineering project (Section 13, Group 4). A web app for running shared student messes, built with Laravel MVC.

## Tech stack
- PHP 8.3+ (tested on 8.5), Laravel 13, MySQL (XAMPP), Composer
- Views: Blade templates

## Setup
1. `git clone https://github.com/fardinfaiaz/mess-manager.git` and `cd mess-manager`
2. `composer install`
3. `copy .env.example .env` (Windows) or `cp .env.example .env`, then `php artisan key:generate`
4. Start MySQL in XAMPP, create a database named `mess_manager`, and set in `.env`:
   `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=mess_manager`, `DB_USERNAME=root`, `DB_PASSWORD=`
5. `php artisan migrate`
6. `php artisan serve` and open http://127.0.0.1:8000

If PHP reports a missing `zip` extension during `composer install`, enable `extension=zip` in `php.ini`.

## How to try the Sprint 1 features
1. Register user A and click **Create a new mess**. A becomes the manager.
2. As A: **Manage rooms**, add rooms with capacities.
3. Register user B (another browser or private window): **Find a mess**, search by name or area, send a join request.
4. As A: **Join requests**, approve B into a room (or reject with a reason).
5. As A: on **Manage rooms**, move members between rooms and set a room leader.
6. As A: open **Notice board**, publish a notice (whole mess or one room, optional expiry, optional pin).
7. As B: open the dashboard or **Notice board** to read current notices, filter by category, and open **Previous notices** for expired ones.

## Sprint 1 features
| ID | Feature | Owner | Status |
|----|---------|-------|--------|
| F1 | Mess and room join request with manager approval | Fardin Faiaz | Implemented, manually tested |
| F8 | Room leader role assignment and room roster manager | Fardin Faiaz | Implemented, manually tested |
| F20 | Central notice board | Labonno Roy | Implemented, feature tests added |

## MVC structure
- Routes: `routes/web.php`
- Middleware: `app/Http/Middleware/EnsureManager.php` (manager-only pages)
- Controllers: `app/Http/Controllers` (AuthController, DashboardController, MessController, RoomController, JoinRequestController, NoticeController)
- Models: `app/Models` (User, Mess, Room, Membership, JoinRequest, Notice)
- Views: `resources/views` (layouts, auth, dashboard, messes, rooms, join_requests, notices)

Registration, login and logout are supporting functions and are not counted among the 20 project features.