# TP Djerbi — MySQL Database Manager (Laravel) 🗄️

A lightweight, developer-friendly **database management** web app built with **Laravel** that demonstrates how to **connect to MySQL**, browse schemas/tables, inspect columns & indexes, run queries safely, and perform CRUD with pagination and search.

<!-- Badges (optional). Update owner/repo if you enable CI -->
![php](https://img.shields.io/badge/PHP-8.2%2B-informational)
![laravel](https://img.shields.io/badge/Laravel-10%2F11-red)
![mysql](https://img.shields.io/badge/MySQL-8-blue)
[![CI](https://github.com/YOUR_GITHUB_USERNAME/tp_djerbi/actions/workflows/laravel-ci.yml/badge.svg)](https://github.com/YOUR_GITHUB_USERNAME/tp_djerbi/actions)

---

## ✨ What it does

**Implemented**
- Connect to a MySQL instance via `.env` settings
- List **databases → tables → columns**
- View **rows** with pagination & basic filtering
- Basic **CRUD** (create/update/delete) on a selected table
- Execute **SQL queries** in a sandbox route (read/write scope configurable)
- **Auth scaffolding** (Laravel) to protect the UI

**Planned / Nice to have**
- Per-table **role-based access** (admin/user)
- **Query history** and saved snippets
- CSV **export/import**
- **Indexes** & foreign key visualization
- Dark mode

> This repository is intentionally scoped as a **demo** of MySQL integration and web tooling in Laravel. The roadmap shows how to grow it toward a more complete DB manager.

---

## 🧱 Tech stack

- **Laravel** (PHP 8.2+) with Vite & Blade
- **MySQL 8** (local or Docker)
- Front-end: Blade + vanilla JS (or Alpine) + Tailwind (optional)
- **Pest/PHPUnit** for tests
- **GitHub Actions** for CI (optional)
- **Docker Compose** for reproducible local setup (optional)

---

## 🚀 Quick start

### 0) Requirements
- PHP 8.2+, Composer
- Node 18+ & npm
- MySQL 8 (local) **or** Docker

### 1) Clone & install
```bash
git clone https://github.com/YOUR_GITHUB_USERNAME/tp_djerbi.git
cd tp_djerbi

composer install
npm install
