# Alpha.25 — güvenli test migration geri alma düzeltmesi
30 Eylül 2026

## Sonuç
GitHub Actions mysql-tests başarılı: https://github.com/NiateksHouse/niateks-crm/actions/runs/36642756465
Doğrulanan kod: a227578770d176fc2dc84461a450d2952598c210.
PHP 8.4.26 / MySQL 8.0.46 / PHPUnit 12.5.37.

- Tek regresyon: php vendor/bin/phpunit --filter=test_upgrade_keeps_existing_users_and_companies → 1 test, 8 assertion geçti.
- composer test → 53 test, 354 assertion; hata/başarısızlık yok.
- Strict Pint, PHP sözdizimi, Composer audit, Blade, gerçek HTTP oturum/CSRF/davet ve paketleme kontrolleri geçti.
- Beş yeni test: şema ve ilgisiz kayıtların korunması; kalıcı ortamda koruma; eksik/yanlış geçici DB tanımında ret; eski unique kurallarıyla çelişen mükerrer veride işlem öncesi ret; geçici DB migrate:refresh döngüsü.
- Önceki kullanıcı/firma korunma testi atlanmadı veya silinmedi; Laravel rollback/migrate üzerinden çalışıyor. İlk dört koruma assertion'ı korunup rollback sonrasında ek doğrulamalar eklendi.
- Yerel MySQL yok; gerçek MySQL testleri CI servisinde çalıştırıldı. Yerelde değişen altı PHP dosyası syntax kontrolü geçti. SQLite kullanılmadı.
- Projede Collision/artisan test sarmalayıcısı yok; eşdeğer doğrudan PHPUnit komutu ayrı zorunlu CI adımı olarak çalıştırıldı.

## Kök neden ve davranış
Eski 46-test çalıştırmasında migrate:rollback en yeni matching migration'ın down() metodunu çağırıyordu. Bu metot koşulsuz RuntimeException üretiyordu. Bağlantı problemi veya Node uyarısı değildi.

Önce: tüm ortamlarda down() engelleniyordu.
Sonra: production/staging/local/development/ci_http yine engellenir. Yalnız testing + CLI + önbelleksiz ayar + MySQL + _ci adı + DB_DISPOSABLE_TEST_DATABASE açık tanımı ve gerçek bağlantı uyuşması halinde temizleme yapılır. Aynı kontrol PHPUnit migrate:fresh öncesinde de uygulanır.

Altı yeni tablo bağımlılık sırasıyla kaldırılır; firma tablosunun üç eski unique indeksi geri gelir ve yalnız yeni website/tax_number alanları kaldırılır. users/companies satırları ve ilgisiz tablolar korunur. Çelişen mükerrer firma varsa herhangi bir DDL'den önce ret verilir; veri silerek benzersizlik sağlanmaz. up() değişmedi. Kalıcı veritabanında geri alma/temizleme çalıştırılmadı.

## GitHub Actions
Checkout ve upload-artifact resmi v7.0.1 / Node24 SHA'larına sabitlendi; setup-php zaten Node24'tü. Başarılı iş günlüğünde Node.js20 kullanımdan kalkma uyarısı yok. SHA'lar ve tam şema/ortam incelemesi MIGRATION-SAFETY.md içinde.

## Teslim ve sınırlar
Kaynak sürümü 2.0.0-alpha.25. Önceki paketler korundu. Bu CI/kaynak düzeltmesi hosting'e uygulanmadı; test CRM hâlâ Alpha.24, canlı değiştirilmedi. PR taslak olarak kaldı.
Laravel migrate:fresh doğrudan down() çağırmadan şema siler; yalnız bu migration'ın exception'ı bütün yönetici komutlarını korumaz. Test giriş noktası korundu; kalıcı ortamlarda ayrı DB yetkisi ve yedek/geri yükleme prosedürü gerekir. Yedek geri yükleme tatbikatı yapılmış sayılmaz.
Kullanım kılavuzuna değişiklik yapılmadı.
