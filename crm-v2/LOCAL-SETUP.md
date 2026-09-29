# Yerel kurulum — 2026-09-29

Kullanıcı PHP/Composer kurulumunu açıkça istedi. Resmî https://herd.laravel.com üzerinden Herd 1.30.1 (120,5 MB DMG) indirildi ve Applications klasörüne kuruldu. İlk kurulum tamamlandı; NVM ve Herd Pro atlandı, başlangıçta otomatik açılma işaretlenmedi.

- PHP CLI: 8.4.25; Composer: 2.10.2. Sürümler çalıştırılarak doğrulandı.
- Araçlar: /Users/organikinsan/Library/Application Support/Herd/bin
- Varsayılan yerel site klasörü ~/Herd; CRM bu klasöre taşınmadı veya yayınlanmadı.
- 25 PHP/artisan dosyası syntax kontrolünden geçti; composer.json strict doğrulaması geçti.
- composer install ağ/DNS hatasıyla durdu; composer.lock/vendor oluşturulmuş sayılmaz. Paketleri indirmek için yetkili ağ erişimi olan bir komut ortamı gereklidir.
- MySQL kurulmadı; 11 gerçek MySQL feature testi hâlâ çalıştırılmadı.
- Hosting, e-posta ve sunucu veritabanlarında bu işlem sırasında değişiklik yok.

## GitHub bağlantısı
GitHub eklentisi kullanıcı onayıyla kuruldu. Kimliği doğrulanmış profil NiateksHouse ve özel CRM deposu https://github.com/NiateksHouse/niateks-crm doğrulandı; erişim push/pull/admin içeriyor. Website ve test-niateks depoları da var; CRM hedefi niateks-crm. Bu adımda depoya yazılmadı, iş akışı çalıştırılmadı. Sohbetteki parola dosyalara kaydedilmedi ve giriş için kullanılmadı; bağlayıcının mevcut yetkilendirmesi kullanıldı.

## Alpha.25 — güncel test güvenliği
Önceki bölümler tarihsel kurulum kaydıdır. Tam MySQL testleri GitHub Actions üzerinde çalışır. Yerelde MySQL kurulu değildir. Yerel test çalıştırmak için yalnız test için ayrılmış MySQL hesabı/veritabanı ve `DB_DATABASE=<ad>_ci`, `DB_DISPOSABLE_TEST_DATABASE=<ad>_ci` gerekir. `phpunit.xml` testing ortamını zorlar; ayrıca bu açık veri tabanı tanımı olmadan `migrate:fresh` başlatılmaz. `config:cache` kullanılmamalıdır. Hosting staging veritabanını bu amaçla kullanmayın. Ayrıntılar MIGRATION-SAFETY.md içindedir.
