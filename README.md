# PHP YouTube Ara

SQLite tabanlı basit YouTube arama/izleme scripti ve yönetim paneli.

- Kurulum: `php -S localhost:8000` ile çalıştırın (PHP 8+, pdo_sqlite). `data/` klasörü yazılabilir olmalı.
- Yönetim: `/admin.php` — varsayılan şifre `admin` (ilk girişte Ayarlar'dan değiştirin).
- Arama ve izleme geçmişi SQLite'ta tutulur; panelden tek tek/toplu silinebilir, arşivlenebilir, CSV indirilebilir.
- Ayarlar: site başlığı, YouTube API anahtarı, sonuç sayısı, geçmiş kaydını aç/kapa, otomatik silme (arşivlenenler korunur), şifre.
