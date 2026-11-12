-- Fitur outfit dihapus, digantikan halaman Riwayat yang terisi otomatis
-- dari catatan "Pakai hari ini".
--
-- PERHATIAN: ini menghapus semua outfit yang pernah kamu simpan, permanen.
-- Jalankan sekali: phpMyAdmin -> pilih database lemariku -> tab Import -> file ini.

USE lemariku;

-- outfit_items lebih dulu, karena dia yang menyimpan foreign key ke outfits.
DROP TABLE IF EXISTS outfit_items;
DROP TABLE IF EXISTS outfits;
