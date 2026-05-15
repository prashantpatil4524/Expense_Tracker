# FinTrack — Expense Tracker Setup Guide

## Files in this project:
- `db_setup.sql`  — Run this FIRST to create the database
- `config.php`    — Database credentials & admin key
- `login.php`     — Login + Registration page
- `index.php`     — Main dashboard
- `records.php`   — All records with filters
- `admin.php`     — Admin panel (admin only)
- `logout.php`    — Session logout

## Setup Steps (XAMPP):

### 1. Start XAMPP
- Start Apache and MySQL in XAMPP Control Panel

### 2. Place files
- Copy all PHP files to: `C:\xampp\htdocs\expense_t\`

### 3. Create the database
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click the **SQL** tab
- Paste the contents of `db_setup.sql` and click **Go**

### 4. Configure DB credentials (if needed)
- Open `config.php`
- Update `DB_USER` and `DB_PASS` if your MySQL credentials differ from root/''

### 5. Run the app
- Open: http://localhost/expense_t/login.php

## Default Login:
- Username: `admin`
- Password: `admin123`

## Admin Registration Key:
- Key: `ADMIN2026SECRET`
- Enter this key during registration to get admin access
- Change in `config.php` → `ADMIN_SECRET_KEY`

## Features:
✅ MySQL-backed multi-user system
✅ User registration (regular + admin via key)
✅ Monthly expense/income dashboard
✅ Category breakdown with progress bars
✅ 6-month trend chart
✅ All records page with search & filters
✅ Admin panel: manage users, change roles, reset passwords
✅ Notes field for each entry
✅ Dark theme, modern design
