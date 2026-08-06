# IT Inventory Bea Cukai – SQL Server Plant 1

Dashboard IT Inventory Kawasan Berikat berbasis **PHP 5.4**, **Bootstrap 3**, dan **Microsoft SQL Server 2008**.

## Struktur penempatan

Letakkan folder aplikasi di dalam folder `msii`, sejajar dengan folder `config`:

```text
/msii/
  config/
    db_plant1.php
  it-inventory-bc/
    index.php
    config/database.php
```

`config/database.php` pada dashboard otomatis mencari:

1. `/msii/config/db_plant1.php`
2. `/msii/config/db_plan1.php` sebagai fallback

Jadi username dan password database tetap mengikuti session login yang sudah dipakai aplikasi MSII.

## Persyaratan

- PHP 5.4
- Microsoft SQL Server 2008
- Microsoft Drivers for PHP for SQL Server yang sesuai dengan versi PHP
- Ekstensi `sqlsrv` aktif
- Web server yang menjalankan folder `/msii`

## Instalasi

1. Ekstrak folder `it-inventory-bc` ke `W:\msii\it-inventory-bc` atau folder web yang setara.
2. Pastikan file koneksi Plant 1 berada di `W:\msii\config\db_plant1.php`.
3. Buka `http://server/msii/it-inventory-bc/`.
4. Bila tabel dashboard belum ada, jalankan `database/schema_sqlserver.sql` pada database `msdata`.

## Tabel yang digunakan

Kode contoh membaca:

- `dbo.dokumen_bc`
- `dbo.aset_it`

Jika database produksi memakai nama tabel atau kolom yang berbeda, sesuaikan seluruh query hanya di:

```text
inc/repository.php
```

## Catatan konfigurasi yang diberikan

Pada file koneksi Plant 1, komentar awal masih menuliskan `db_plant2.php` dan “Plant 2”. Itu tidak memengaruhi koneksi, tetapi sebaiknya diganti menjadi `db_plant1.php` dan “Plant 1” agar tidak membingungkan.

Koneksi memakai variabel `$conn` dari `sqlsrv_connect()`. Dashboard tidak lagi memakai PDO MySQL.

## Login

File koneksi yang diberikan mengarahkan user yang belum login ke:

```text
/msii/marketing/login.php
```

Pastikan halaman login menyimpan nilai berikut setelah autentikasi berhasil:

```php
$_SESSION['db_user'] = $username;
$_SESSION['db_pass'] = $password;
```

## Pengujian koneksi

Setelah login, buka dashboard. Bila koneksi berhasil tetapi tabel belum ada, halaman tetap terbuka dan data dashboard tertentu menggunakan data contoh. Untuk laporan aktual, buat tabel contoh atau petakan query ke tabel produksi.
