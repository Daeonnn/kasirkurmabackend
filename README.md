# Kasir Kurma - Backend API

RESTful API untuk sistem kasir toko kurma yang dibangun menggunakan Laravel. API ini menyediakan endpoint untuk autentikasi, manajemen produk, transaksi penjualan, dan pelaporan.

## 📋 Daftar Isi

- [Fitur](#-fitur)
- [Teknologi](#-teknologi)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#-konfigurasi)
- [API Documentation](#-api-documentation)
- [Database Schema](#-database-schema)
- [Testing](#-testing)
- [Deployment](#-deployment)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Fitur

- 🔐 **Autentikasi JWT** - Secure login dengan token-based authentication
- 👥 **Multi-Role Access** - Admin dan Kasir dengan hak akses berbeda
- 📦 **Manajemen Produk** - CRUD produk kurma dengan upload foto
- 📊 **Master Data** - Pengelolaan distributor, jenis produk, dan satuan
- 👨‍💼 **Manajemen Pegawai** - CRUD data pegawai dan pengaturan role
- 💰 **Transaksi Penjualan** - API untuk proses transaksi dengan keranjang
- 📈 **Laporan** - Endpoint untuk laporan penjualan dengan filtering
- 🏷️ **Diskon Fleksibel** - Support diskon persentase dan nominal
- 📱 **RESTful API** - Standar REST untuk integrasi mudah
- 🔄 **Real-time Stock** - Update stok otomatis saat transaksi

## 🛠 Teknologi

- **Framework:** Laravel 10.x
- **Database:** MySQL 8.0
- **Authentication:** Laravel Sanctum / JWT
- **PHP Version:** 8.1+
- **API Documentation:** Postman Collection
- **Image Storage:** Laravel Storage
- **PDF Generation:** DomPDF / Laravel PDF

## 📌 Persyaratan Sistem

Sebelum instalasi, pastikan sistem memenuhi persyaratan berikut:

- PHP >= 8.1
- Composer >= 2.0
- MySQL >= 8.0 atau MariaDB >= 10.3
- Extensions PHP yang diperlukan:
  - BCMath PHP Extension
  - Ctype PHP Extension
  - JSON PHP Extension
  - Mbstring PHP Extension
  - OpenSSL PHP Extension
  - PDO PHP Extension
  - Tokenizer PHP Extension
  - XML PHP Extension
  - GD PHP Extension (untuk image processing)

## 📦 Instalasi

```bash
# Clone Repository
git clone https://github.com/Daeonnn/kasirkurmabackend.git
cd kasirkurmabackend

# Install Dependencies
composer install

# Setup Environment
cp .env.example .env

# Generate Application Key
php artisan key:generate

# Konfigurasi Database di .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=kasir_kurma_db
# DB_USERNAME=root
# DB_PASSWORD=your_password

# Jalankan Migration dan Seeder
php artisan migrate --seed

# Link Storage untuk Upload Foto
php artisan storage:link

# Jalankan Server
php artisan serve


konvigu
