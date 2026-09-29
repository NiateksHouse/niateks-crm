# Alpha.23 — Girişte şifre göster/gizle
30 Eylül 2026

- Girişte 44px göz düğmesi: Şifreyi göster / Şifreyi gizle, klavyeyle kullanılabilir ve ekran okuyucu etiketi değişir.
- İlk yüklemede gizli. Form gönderiminde, sayfadan ayrılmada ve sekme gizlendiğinde yeniden gizlenir. JavaScript yüklenmezse düğme gizli; normal giriş çalışır.
- Yeni bağımlılık yok; harici aynı kaynak JS/CSS mevcut CSP ile uyumlu. Şifre okunmaz, kaydedilmez veya yeni bir uç noktaya gönderilmez.
- Değişenler: login.blade.php göz kontrolü ve varlık bağlantıları; layout.blade.php sürüm etiketi; iki statik dosya; build_release.py sürüm adı.
- JS sözdizimi kontrolü geçti. CI 36638223864 başarılı, uygulama commit 1425a979000683633ed449f8c63cf43bd91ab39b. Mevcut MySQL/HTTP güvenlik, lint, Blade ve paket kontrolleri geçti.
- HTTPS test sitesinde örnek 14 karakterlik metinle göster/gizle, Enter ile gizleme, başlangıçta gizlilik doğrulandı. Test metni temizlendi, giriş gönderilmedi. Sekme gizlenmesi davranışı kodda mevcut; ayrıca tarayıcı testi yapılmadı.
- Uygulanan paket: koza-crm-v2.0.0-alpha.23-login-update.zip. Yalnız dört UI dosyası yayımlandı. Veritabanı ve kimlik doğrulama kuralları değişmedi.
- Önceki şablonlar sunucuda resources/views-before-alpha23.zip içinde. Geri dönüş: resources altında açılır; yeni statik dosyalar kullanılmadan kalabilir.
- Kullanım kılavuzu v0.3.0 onay beklediği için değiştirilmedi. Alpha22 geçici Cron kaldırma onayı hâlâ bekliyor.
