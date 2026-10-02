# Alpha.27 — mevcut Alpha.26 test kurulumuna Dokümanlar güncellemesi

Bu belge kurulum prosedürüdür; tamamlanmış yayın kanıtı VALIDATION.md dosyasına kaydedilir. Eski kurulum ve davet işlemleri yeniden çalıştırılmaz.

1. Yalnız /home/niatekscom/koza-crm-test-alpha20 uygulamasını ve test.koza.niateks.com adresini hedefle. APP_ENV=staging, APP_DEBUG=false, mevcut test DB niatekscom_koza_test ve HTTPS kontrol edilir. APP_KEY, parola, hesaplar ve .env korunur.
2. PHP CLI 8.4, intl/Collator, zip/ZipArchive, fileinfo, getimagesize, mbstring ve pdo_mysql gereklidir. Mevcut dosyalar Alpha.26 SHA256 değerleriyle eşleşmezse yayın durur. Yeni dosya yolu zaten varsa üzerine yazılmaz.
3. 25 dosyalık güncelleme önce web kökü dışındaki updates/alpha27/payload alanına konur. Değişen önceki kaynaklar yalnız sunucuda storage/app/private/alpha27-previous alanında saklanır. Hosting veya DB yedeği yerel SSD'ye indirilmez.
4. Kısa bakım penceresinde doğrulanmış dosyalar atomik adlandırmayla yerleştirilir. Yalnız database/migrations/2026_09_30_000006_create_documents.php migration'ı çalıştırılır. document_categories, documents, document_versions, document_events eklenir; mevcut iş tablolarının satır sayıları korunur. migrate:fresh, refresh ve rollback hostingde çalıştırılmaz.
5. storage/app/private/documents dizini 0700; dosyalar 0600. public altında bağlantı veya storage symlink oluşturma. Dosya alanı DB'den ayrıdır; ileride sunucu yedekleme politikasında birlikte ele alınmalıdır. Bu güncelleme yedek geri yükleme tatbikatı değildir.
6. Görünüm/rota/ayar önbellekleri temizlenir, dosya hash'leri yeniden doğrulanır ve bakım modu kapatılır. Özel result.json sonucu sır içermez. Geçici Cron işi sonuçtan sonra yeniden işlem yapmaz; kullanıcı onayıyla kaldırılır. Mevcut saatlik rapor görevi korunur.
7. Başarısızlıkta önceki kaynak dosyaları geri yerleştirilir. Eklenen tablolar veya dosyalar otomatik silinmez; başarısız adım özel sonuç kaydında gösterilir. Kısmi DDL durumunda otomatik tekrar yapılmaz; güvenli ileri düzeltme hazırlanır.
8. HTTPS oturumunda liste, kategori, yeni belge, sürüm geçmişi, önizleme, izin ve arşiv davranışını sentetik dosyayla kontrol et. Gerçek şirket evrakı yükleyerek test yapma. Oturum yoksa kullanıcıdan giriş yapmasını iste; kimlik doğrulama atlatılmaz.

Varsayılan rol yetkileri config/documents.php dosyasındadır. Admin tüm belge işlemi yetkilerine sahip olsa da belge ACL'si ayrıca gerekir. Representative yalnız view/download/view_private yetkileriyle erişimi açık belgeleri okur. Özel belge sahibinin de görüntüleme ve özel belge yetkisi gerekir. Yeni finance/management rolü bu sürümde uydurulmaz.
