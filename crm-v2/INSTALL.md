# Alpha.20 — test ortamı kurulum sırası

Bu paket tam CRM değildir. Yalnız giriş, ilk hesap daveti ve firma altyapısıdır. Kılavuz v0.3.0 değiştirilmedi.

1. Sunucuda test DB ve test alanını doğrula; ayrı sürüm klasörüne paketi çıkar. Yalnız public klasörü web kökü olmalı. .env, vendor, storage, kurulum dosyaları ve loglar internetten erişilemez olmalı. Dizin listelemeyi kapat ve HTTPS'yi doğrula.
2. .env yalnız sunucuda: test DB parolası, benzersiz APP_KEY, APP_ENV=staging, APP_DEBUG=false, HTTPS APP_URL, güvenli çerez ve ayrı test oturumu. E-posta gönderimi kapalı.
3. Cron'un PHP CLI sürümünün 8.4 ve gerekli uzantıların etkin olduğunu çalıştırarak doğrula; web PHP seçimi bunu tek başına kanıtlamaz. Mevcut cron işlerini değiştirme.
4. Yetkili özel kurulum işiyle ilk kurulumda key:generate --force, migrate --force çalıştır; hostingde migrate:fresh kullanma. Sonraki dağıtımda mevcut APP_KEY korunur. Görev çıktısı yalnız özel dosyaya, e-postasız yazılmalı. Tek seferlik kurulumun tekrar çalışmasını engelle ve tamamlanınca geçici işi kaldır.
5. storage/app/private/bootstrap-users.json dosyasını yalnız sunucuda oluştur (0600). Bu dosya en fazla 10 kayıt içerir: username, email, name, role (admin/representative), can_view_all_finance (boolean). En az bir yönetici şart. Dosyada parola yoktur. Onaylı gerçek kullanıcı listesi Git/paket yerine yerel özel kurulum kaydındadır.
6. `php artisan koza:bootstrap-invitations --no-interaction` komutunu özel kurulum işinden çalıştır. İlk batch yalnız boş kullanıcı/davet tablolarında oluşturulur; komut tekrarları davetleri yenilemez. Çıktı kodları storage/app/private/bootstrap-invitations.json dosyasında 0600 izniyle bulunur; konsola veya e-postaya yazılmaz. Bu dosyayı web köküne koyma, Git'e ekleme veya log olarak paylaşma.
7. Kodları yetkili kullanıcıya güvenli şekilde teslim et; otomatik e-posta yok. Kullanıcı HTTPS /activate ekranında kodunu ve kendi parolasını girer. Kod 24 saat sonra biter ve tek kullanımlıktır. Nigar için genel finans, Tunç için genel finans olmadan yönetici planı uygulanır. Parola belirleme işlemini kullanıcının kendisi tamamlamalıdır.
8. Süresi dolmuş/iptal edilmiş davet için herkese açık yenileme veya parola sıfırlama yoktur. Bu sürümde yenileme ekranı bulunmaz; ilk kurulum kodları kullanıcı hazırken üretilmeli. İlk batch dosyasını silip komutu tekrar çalıştırmak yeni kod üretmez. Kontrollü davet yenileme sonraki idari işlevdir.
9. Giriş/çıkış, firma revizyonu, rol ve özel dosya erişim testlerini HTTPS üzerinde yap. Kullanılmış kod yeniden hesap açamamalı. Hata/erişim günlüklerine gövde veya gizli kod yazılmadığını doğrula.
10. Geri dönüş uygulama sürümünü değiştirerek yapılır; migration down ile kullanıcı/davet/geçmiş tabloları silinmez.

İlk davet oluşturma yolu Cron ile uyumludur; gerçek hosting kurulumu ve CLI sürümü henüz doğrulanmış değildir. Bu belge uygulanmış kurulum kaydı değildir.
