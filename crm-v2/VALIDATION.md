# Alpha.24 — Kontrollü firma sözlüğü ve açıklamalı eşleşme
30 Eylül 2026

- Kullanıcının açık onayından öğrenilen isim varyasyonları, özgün marka yazımı, firma/kişi için 0–100 puan ve nedenleri, yönetilebilir ağırlık/eşikler, aynı/farklı karar geçmişi ve yönetici geri alma desteği.
- Hiçbir puan otomatik birleştirme veya otomatik sözlük kaydı üretmez. “Aynı” mevcut kaydı kullanır; kayıt birleştirme işlemi bu sürümde yoktur.
- Altı yeni tablo; firma web sitesi/vergi numarası alanları; bilinçli ayrı kart oluşturabilmek için eski benzersiz firma indeksleri normal indekse çevrildi. Mevcut kayıtlar korunarak eşleşme anahtarları üretildi.
- MySQL 8 / PHP 8.4 CI: 48 test, 294 assertion başarılı. Strict Pint, PHP syntax, Blade, Composer audit, gerçek HTTP oturum/CSRF/davet kontrolleri geçti. Son kod commit: e2d284be5ae7c61e7c04620b1e7726fe8666ce88.
- Doğrulama: https://github.com/NiateksHouse/niateks-crm/actions/runs/36641212670
- Kapsanan kritik durumlar: boş alanlar, ülkeye göre vergi numarası, Unicode isimler, puan sınırları, yetki/kişi gizliliği, süresi dolmuş veya değiştirilmiş karar taslakları, tekrar gönderim, farklı kararının ters yönü, kimlik değişince yeniden değerlendirme, yönetici geri alma, mevcut verilerle yükseltme.
- Yazma işlemleri işlem kilidiyle sıralanır. Gerçek eşzamanlı yük testi yapılmadı; büyük hacim performans garantisi verilmez.
- Test yayını: 2026-09-29 22:49:03 UTC (30 Eylül 01:49 İstanbul), 19 dosya ve migration 000005 başarıyla uygulandı. Paket: koza-crm-v2.0.0-alpha.24-update-v1.1.0.zip. İlk update.zip uygulanmadı.
- HTTPS tarayıcı kontrolü: alpha.24 menüsü, boş/onaysız sözlük, açık onay kutusu ve yönetici ayarları; DEMO firma için %100 isim benzerliğinin 20/100 puan ve açıklamayla gösterilmesi; geri dönüşte taslak ad/ülkenin korunması; kişiler ekranı; mevcut proje/görüşme akışının korunması doğrulandı. Sunucuda sahte onay/alias veya yeni firma oluşturulmadı.
- Geçici Alpha24 Cron görevi kullanıcı onayıyla kaldırıldı; mevcut saatlik rapor görevi korundu.
- Önceki dosyalar ve firma tablosu yedeği yalnız sunucuda storage/app/private/alpha24-previous içinde. Yerel SSD’ye hosting/veritabanı yedeği indirilmedi. Canlı site ve kimlik bilgileri değiştirilmedi.
- Migration ileri yönlüdür; öğrenilmiş kayıtları silen otomatik down yoktur. Gerektiğinde önceki uygulama dosyaları döndürülür; şema geri dönüşü ayrıca değerlendirilir.
- Sınırlar: Kişiler oluşturma/liste/detay kapsamındadır; kişi revizyon/arşivleme henüz yok. İsim adayları indekslenmiş alanlar ve sözcük başlangıçlarıyla daraltılır; tüm kelime başlangıçları değişmiş yazımlarda eşleşme kaçabilir. Doğrulanmış isim elle eklenebilir.
- Önceki demo v1.6.0 tasarımının tüm menü/modüllerinin aktarımı hâlâ tamamlanmadı. Bu sürüm o işin tamamlandığı anlamına gelmez.
- Kullanım kılavuzu v0.3.0 ayrı güncelleme onayı beklediği için korundu; yeni modülü henüz kapsamıyor.
