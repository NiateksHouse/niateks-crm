# KOZA CRM 2.0.0-alpha.16 — ilk backend kaynak taslağı

**Yayınlanmadı. PHP testleri çalıştırılmadı. Kurulabilir sürüm olarak onaylı değildir.**

Bu klasör Laravel 13/PHP 8.4 için ilk uygulama diliminin kaynağıdır. Composer bağımlılıkları ve kilit dosyası henüz oluşturulmadı. Onaylı görsel demo v1.6.0 korunmuştur; bu Blade görünümleri işlevsel başlangıçtır, nihai tasarım değildir. Logo/giriş görseli sonraki görsel entegrasyon adımındadır.

## Bu dilim
- Framework oturum/giriş altyapısı, giriş denemesi sınırlama, pasif hesabın oturumunu kesme; dışarıya açık kayıt yok.
- Ortak firma kartı liste/arama/oluşturma/düzenleme/arşivleme, 25 kayıt/sayfa.
- Sunucu yetki politikası, isim+ülke/e-posta/telefon mükerrer kontrolü, transaction içinde audit, eşzamanlı güncelleme çakışması.
- Nigar/Tunç için admin rolü tanımlanabilir; otomatik kullanıcı/parola oluşturulmaz. Finans izni ayrı alandır; finans modülü yok.

## Onay bekleyen ayrıntılar
Temsilci firma oluşturabilir, kendi oluşturduğu ortak kartı düzenleyebilir; başka temsilcinin kartını yalnız görür. Yönetici tümünü düzenler/arşivler. Kullanıcı ayrıntılı yetkileri daha sonra belirleyeceğini söyledi; bu sınır taslak olup onaylı nihai kural değildir.
Mükerrer e-posta/telefon şu anda yeni kartı engeller. Aynı grup telefonunu kullanan ayrı şirketler için yönetici incelemesi/birleştirme akışı eklenmeden bu kural nihai sayılmaz.
Audit şu anda aktör/işlem/değişen alan isimlerini tutar; tam eski-yeni iş geçmişi henüz yok. Kişisel iletişim değerleri uygulama günlüğüne yazılmaz.

## Henüz yok
Proje/kişiye özel iş görünürlüğü, görüşme akışı, ürün/set, numune, teklif/PDF, üretim, finans, bildirim, AI, kullanıcı yönetimi ve hesap kurtarma. Bu alanlar sahte çalışır düğmelerle sunulmaz. Yönetici hesabı ilk oluşturma yolu henüz tamamlanmadı.

## Doğrulama ve güvenli yayın kapısı
1. PHP 8.4, Composer 2 ve ayrı MySQL 8 test çalışma ortamı gerekir. Kullanıcının izniyle Laravel Herd üzerinden PHP 8.4.25 ve Composer 2.10.2 kuruldu. Komut ortamında paket sunucusu DNS erişimi hâlâ başarısız. Hosting yedeği yerelde tutulmaz.
2. Bağımlılıklar resmi Composer deposundan kurulmalı; composer.lock oluşturulup saklanmalı. `composer validate --strict`, `composer audit`, tüm PHP dosyalarına `php -l`, `composer lint`, `composer test` çalıştırılmalı.
3. Test DB adı `_ci` ile bitmelidir. Testler migrate:fresh kullanır; hostingteki koza_test veya canlı DB asla kullanılmaz. Tests/TestCase.php bunun için başlangıç koruması içerir.
4. Tarayıcıdan CSRF, oturum cookie bayrakları/yenilenmesi, çıkış sonrası erişim ve farklı hesap yetkileri ayrıca denenmeli. PHPUnit varsayılan test ortamında CSRF middleware devre dışı olduğundan feature testleri CSRF doğrulaması değildir.
5. Başarılı testten sonra aynı lock ile `composer install --no-dev --classmap-authoritative` ile temiz yayın paketi hazırlanmalı. Test kodu, .env, özel dosyalar ve yedekler public içine girmemeli.
6. Hosting belge kökü yalnız public olmalı; .env ve tüm uygulama kaynakları dışında kalmalı. Public .htaccess dizin listesini kapatır. Gerçek HTTPS/PHP/MySQL bağlantısı test edilmeli.
7. Migration geri dönüşü veri siler; gerçek veri oluşunca otomatik rollback uygulanmaz. Yedek + ileri düzeltme değerlendirilir.

Kullanım kılavuzu değiştirilmedi. Bu belge geliştirme durum kaydıdır.
