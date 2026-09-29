# Alpha.26 — onaylı v1.6.0 çalışma alanı tasarımı
30 Eylül 2026

Tasarım referansı: outputs/versiyonlar/koza-crm-interactive-demo-v1.6.0.html. Demo dosyası değiştirilmedi.

## Doğrulama
- Kod: e64d896438fde2fbd3f736c75d9b20f94fcfc7c1.
- GitHub Actions: https://github.com/NiateksHouse/niateks-crm/actions/runs/36644499325
- MySQL 8.0.46 / PHP 8.4: 56 test, 402 assertion başarılı. Ayrı upgrade regresyonu 1 test / 8 assertion başarılı.
- Pint, PHP sözdizimi, Composer audit, Blade derleme, gerçek HTTP oturum/CSRF/davet ve paketleme başarılı.
- Yeni kontroller: başlangıç ve genel görüşmelerde özel kayıtların kullanıcı bazlı görünürlüğü; arşivli firma kayıtlarının görünmemesi; aktif hesap gerekliliği; hazırlık ekranlarının açık etiketlenmesi.
- Yerel JavaScript sözdizimi ve 100 benzersiz mola mesajı / üç tam karıştırılmış turda ardışık tekrar olmaması / günlük sayaç sıfırlama kontrolü geçti.
- İlk CI denemesi eski giriş yönlendirmesini bekleyen ProvisioningTest assertion'ında durdu. Yeni Başlangıç yönlendirmesine göre güncellendi; yukarıdaki tam tekrar başarılı.

## Davranış
Onaylı tasarımın renkleri, sol logo/menü, ikonlar, başlangıç düzeni, müzik ve mola bileşenleri sunucu tarafındaki oturumlu CRM'ye taşındı. Girişten sonra /home açılır. Firma tablosu ve proje sütunları gerçek yetkili kayıtları gösterir. Mevcut görüşmeler ayrı ana menüden erişilir; firma sözlüğü ve kişiler Firma araçları altındadır.

Müzik kullanıcı başlatmadan çalmaz; Jazz, Relax ve Akustik özgün WebAudio önizlemeleridir, canlı yayın değildir. Sayfa değişiminde durur. Mola sayacı aynı tarayıcıdaki kullanıcı kimliğine göre tutulur, etkin CRM süresi ve boşta kalma kullanılır; yazılan içerik kaydedilmez. Otomatik mesajlar günde en fazla dört, yaklaşık 50 dakikalık etkin kullanım sonrası gösterilir. Manuel Mola mesajı bu otomatik kotadan ayrıdır. Farklı cihazlarda ortak sayaç yoktur.

Ürün, iş takibi, numune, teklif, sipariş/üretim ve finans işlemleri henüz sunucuya bağlı değildir; sayfalarında Hazırlanıyor / Henüz kayıt alınmıyor yazısı vardır. Bu sürüm bunları tamamlandı saymaz. Sipariş kabulü kutlaması, yedi aşamalı teklif kanbanı ve sürükleyerek durum değiştirme henüz etkin değildir. Dokümanlar ve Yardım Merkezi son aşama planında kalır. Kılavuz v0.3.0 değiştirilmedi; güncellemesi ayrıca onay gerektirir.

## Yayınlama
Alpha.24 üstüne 18 dosyalık kontrollü güncelleme. Migration çalıştırılmaz; veritabanı yeniden kurulmaz. Alpha.25 güvenli test geri alma kaynakları dahil edilir, kalıcı ortam koruması sürer. Eski dosya hash'leri doğrulanır, değişen dosyalar yalnız sunucuda storage/app/private/alpha26-previous altında saklanır. Yerel SSD'ye hosting/veritabanı yedeği indirilmez. Canlı koza.niateks.com ve diğer siteler kapsam dışıdır.

## Hosting ve tarayıcı sonucu
- 29 Eylül 2026 23:22:01 UTC: kurulum sonucu success, 18 dosya, unchanged_no_migration_run.
- Chrome oturumunda yeni Başlangıç, firma kartı, özel proje sütunları, genel görüşmeler ve üretim hazırlık ekranı açıldı; mevcut DEMO firma/proje ve görüşme Revizyon 2 korundu.
- Müzik Çal → Sessize al → Durdur, manuel mola aç/kapat doğrulandı; konsolda hata/uyarı görülmedi.
- Dar görünüm kontrolünde belge genişliği ekran genişliğine uydu (tarayıcı etkin CSS genişliği yaklaşık 487px); geçici viewport ayarı geri alındı.
- Giriş ekranı, parola göster düğmesi ve Alpha.26 etiketi doğrulandı. Oturum son kontrolde sona erdi; kullanıcı yeniden giriş yapmalıdır.
- Sürüm yazısı için ilk cPanel editör düzenlemesi hatalı metin üretti. Tam, doğrulanmış kaynak şablonu koza-crm-v2.0.0-alpha.26-label-fix.zip üzerinden geri yerleştirildi; hatalı kaydedilmemiş editör sekmesi kapatıldı. Son fark yalnız iki sürüm metninin Alpha.24 → Alpha.26 değişimidir; davranış değişikliği yok, giriş sayfası tekrar doğrulandı.
- Kullanıcının açık onayıyla yalnız updates/alpha26/deploy.php geçici Cron kaldırıldı. Mevcut saatlik niateks_app/cron_report.php görevi listede korundu.
