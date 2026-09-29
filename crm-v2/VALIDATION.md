# Alpha.22 proje ve görüşme yayını — 30 Eylül 2026

Test edilen uygulama kodu: f466e882ac6a36e7d60adb0904855b72b39cd1f5.
CI: https://github.com/NiateksHouse/niateks-crm/actions/runs/36637155816

- MySQL: 37 test, 216 kontrol başarılı. Başka temsilcinin proje/görüşmesine doğrudan erişim reddi, ortak firma akışı filtrelemesi, korunan revizyon ve çakışan güncelleme kontrolü dahil.
- Gerçek HTTP: giriş/çıkış, davet etkinleştirme, CSRF, firma yazımı, güvenlik başlıkları ve eski oturum reddi başarılı.
- Pint, PHP sözdizimi, Blade derleme, Composer doğrulaması/güvenlik taraması ve paket üretimi başarılı.
- Test sunucusu güncellemesi 2026-09-29T22:05:06Z tarihinde başarılı: 21 dosya ve dört ek tablo. Giriş görünümü, hesaplar, APP_KEY ve .env korunur.
- Uygulanan paket: koza-crm-v2.0.0-alpha.22-update-v1.1.0.zip. İlk update.zip uygulanmadı.
- Değişen önceki dosyalar yalnız sunucuda storage/app/private/alpha22-previous altında korundu; hosting yedeği bilgisayara indirilmedi.
- Tarayıcıda Nigar oturumunda açıkça DEMO olarak işaretli firma, apron projesi ve gelen e-posta oluşturuldu. Maili oku açılımı, özet düzeltmesi, eski revizyonun korunması ve aynı kaydın firma akışına yansıması doğrulandı.
- Masaüstü firma/proje/form ekranları incelendi. Bu sürümün telefon görünümü ayrıca doğrulanmadı. Tunç etkinleştirmesi ayrıca doğrulanmadı.
- Tek seferlik güncelleme görevi sonuç dosyasıyla tekrar çalışmaya karşı korumalıdır; Cron kaydını kaldırmak için kullanıcı yanıtı bekleniyor. Mevcut saatlik rapor görevi korunur.

## Kapsam ve sınırlar

Proje Açıldı, Görüşülüyor ve Bilgi Bekleniyor aşamaları çalışır. Teklif aşamaları, sürükle/bırak, ürün/set/renk fiyatlama, numune, PDF teklif, üretim, tahsilat, AI, müzik ve mola modülleri henüz bu backend sürümüne bağlı değildir. E-posta özeti elle girilir. Proje sahibi ataması oluşturana yapılır; kullanıcılar kendi çalışmalarını, yöneticiler tümünü görür. Mevcut firma arşivi ilişkili işleri gizler.

Kullanım kılavuzu v0.3.0 değiştirilmedi; proje/görüşme bölümleri için ayrıca kullanıcı onayı gerekir. Canlı ve diğer siteler değiştirilmedi.
