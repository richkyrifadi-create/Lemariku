# Lemariku

Aplikasi lemari digital pribadi: catat pakaian, tandai pemakaian harian, dan lihat riwayatnya per tanggal.

Dibangun dengan PHP native (PDO) dan MySQL, tanpa framework.

## Fitur

- Daftar dan masuk (kata sandi di-hash dengan `password_hash`)
- CRUD pakaian dengan foto (validasi tipe dan ukuran file)
- Catat "dipakai hari ini" (satu catatan per pakaian per hari)
- Tandai perlu/sudah dicuci (otomatis perlu dicuci setelah dipakai)
- Lemari terbagi per kategori, tiap kategori punya section dan jumlah itemnya sendiri
- Statistik: jumlah item, perlu dicuci, jumlah yang jarang dipakai (90 hari), total nilai, dan cost per wear
- Pencarian dan filter kategori
- Riwayat pemakaian dikelompokkan per tanggal, terisi otomatis dari "Pakai hari ini", dan tiap catatan bisa dihapus kalau salah pencet

## Menjalankan dengan XAMPP

1. Salin folder ini ke `C:\xampp\htdocs\`
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel
3. Buka `http://localhost/phpmyadmin`, masuk tab **Import**, pilih `schema.sql`, klik **Import**
4. Buka `http://localhost/lemariku/public/`, lalu daftar akun baru

Pengaturan bawaan sudah cocok dengan XAMPP (user `root`, tanpa password).

## Menjalankan tanpa XAMPP

Butuh PHP 8.1+ dengan ekstensi `pdo_mysql` dan `fileinfo`, serta MySQL 8 atau MariaDB.

```bash
mysql -u root -p < schema.sql
export DB_HOST=127.0.0.1 DB_NAME=lemariku DB_USER=root DB_PASS=
php -S localhost:8000 -t public
```

## Struktur

```
lemariku/
├── schema.sql              # Definisi database dan data kategori awal
├── migrations/             # Perubahan skema untuk database yang sudah jalan
├── src/
│   ├── bootstrap.php       # Koneksi DB, CSRF, login, upload foto, helper
│   └── layout.php          # Header dan footer HTML
└── public/                 # Root web server
    ├── index.php           # Daftar lemari + statistik + filter
    ├── item_form.php       # Tambah dan edit pakaian
    ├── item_delete.php     # Hapus pakaian
    ├── wear.php            # Catat dipakai / tandai dicuci
    ├── riwayat.php         # Riwayat pemakaian per tanggal
    ├── masukan.php         # Form masukan dan pengaduan
    ├── login.php, register.php, logout.php
    ├── uploads/            # Foto pakaian (PHP dinonaktifkan lewat .htaccess)
    └── assets/
        ├── style.css       # Tema editorial monokrom
        └── app.js          # Reveal halus saat scroll
```

## Skema database

```
users 1───* items *───1 categories
  │          │
  │          └───* wear_logs      (item_id, worn_on) unik per hari
  │
  └───* feedback  (tabel berdiri sendiri, tidak terikat user)
```

## Keamanan yang sudah diterapkan

- Query memakai prepared statement (PDO)
- Output di-escape dengan `htmlspecialchars`
- Token CSRF pada semua form yang mengubah data
- Setiap query dibatasi `user_id` pemilik data
- Validasi tipe file dengan `finfo` (bukan dari ekstensi nama file), nama file diacak
- Folder `uploads/` tidak bisa mengeksekusi PHP

## Rencana berikutnya

- Statistik per kategori dan grafik pemakaian
- Tes otomatis untuk fungsi perhitungan cost per wear
