ARUNIKA - Arsip Budaya Nusantara Kita
=====================================

FITUR YANG TERSEDIA
- Beranda yang memperkenalkan tujuan ARUNIKA.
- Jelajah Budaya: pencarian kata kunci dan filter kategori/daerah.
- Detail Budaya: deskripsi, sumber informasi, kontributor, foto, dan catatan penggunaan bila tersedia.
- Kontribusi Budaya: formulir pengiriman, persetujuan pengirim, unggah foto, dan status moderasi.
- Verifikasi Budaya: halaman admin untuk menyetujui, menolak, atau meminta revisi kontribusi.
- Belajar Budaya dan Misi: kuis edukasi dan misi sederhana.
- Dashboard Pengguna: profil, riwayat kontribusi, status verifikasi, poin indikatif, dan lencana.
- Registrasi, login, logout, dan pembatasan akses pengguna/admin.

KEBUTUHAN
- XAMPP dengan PHP 8.1+ dan MySQL/MariaDB
- Browser modern

INSTALASI
1. Ekstrak folder arunika ke C:\xampp\htdocs\arunika.
2. Buka XAMPP Control Panel, jalankan Apache dan MySQL.
3. Buka http://localhost/phpmyadmin dan buat database bernama arunika_db.
4. Buat tabel users dan cultures sesuai struktur dasar yang digunakan versi sebelumnya. Jika database lama sudah ada, pertahankan tabel tersebut.
5. Periksa config/database.php. Pengaturan default: 127.0.0.1, database arunika_db, user root, password kosong.
6. Jika kolom image_path belum ada, jalankan migration_foto_budaya.sql. Untuk catatan penggunaan detail, jalankan kolom usage_notes dari migration_fitur_arunika.sql jika belum ada. Jangan menjalankan ALTER yang sama dua kali.
7. Buka http://localhost/arunika/ dan daftarkan akun melalui tombol Daftar.
8. Untuk menjadikan akun sebagai admin, gunakan phpMyAdmin > arunika_db > SQL:
   UPDATE users SET role = 'admin' WHERE username = 'USERNAME_KAMU';
   Ganti USERNAME_KAMU dengan username akun yang didaftarkan.
9. Keluar, lalu login kembali. Akun admin akan dapat membuka menu Admin.

CATATAN
- Katalog publik hanya menampilkan kontribusi berstatus approved.
- Foto JPG/PNG/WEBP maksimal 5 MB.
- Poin dan lencana pada dashboard saat ini dihitung secara sederhana dari jumlah kontribusi; kuis belum disimpan ke profil.
- Filter daerah berupa pencarian teks, bukan daftar provinsi baku.
- Sebelum dipakai di server publik, gunakan HTTPS, kata sandi database kuat, validasi keamanan tambahan, backup, dan kebijakan persetujuan komunitas budaya.
- Hormati izin komunitas, terutama untuk dokumentasi sakral atau pengetahuan terbatas.
