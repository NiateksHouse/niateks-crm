# Dokümanlar — Alpha.27 uygulama planı

Kapsam: güvenli özel depolama; kategoriler/alt kategoriler; Türkçe sıralama ve yetki filtreli arama; belge seviyesinde erişim; değişmez dosya sürümleri; önizleme/indirme; audit; arşivleme/geri alma.

Kararlar: mevcut admin / representative rolleri korunur. Adminler belge oluşturup kategori yönetebilir. Yazma/yetki/sürüm yönetimi hem rol yetkisi hem belge erişimi ister. Hiçbir admin tüm private belgelere örtük erişemez. Belge sahibi ve açıkça seçilen kullanıcı/roller görebilir. Tüm yeni belgeler güvenli başlangıç olarak Private seçilir. Internal yalnız aktif CRM kullanıcılarıdır. Eski sürümler güncel ana belge izinlerini miras alır.

Azami uygulama dosya limiti 10 MB; hosting PHP sınırı daha düşükse arayüz daha düşük etkili limiti gösterir. Office belgeleri indirilir, harici önizleme servisine gönderilmez. PDF/görseller yetkili controller üzerinden açılır. MIME ve gerçek dosya yapısı kontrol edilir; bunun antivirüs taraması olmadığı açıkça belirtilir.

Silme arşivlemedir. Dosyalar private storage'da süresiz korunur; fiziksel otomatik silme yoktur. Retention/purge ayrı onaylı politika gerektirir. Erişim ve değişiklik audit kayıtları silinmez. Dosyalar veritabanı yedeğinden ayrı yedeklenmelidir; yerel SSD'ye hosting yedeği indirilmeyecek.

Adımlar:
1. Veri modeli, ACL/policy ve güvenli dosya kabulü.
2. Liste/ağaç/arama, detay, form, sürüm, arşiv ekranları; onaylı v1.6 tasarımı.
3. Gerçek MySQL yetki, IDOR, sürüm, yükleme, sıralama, audit testleri.
4. Test yayını, doğrulama, sürüm paketi ve sonuç kaydı.

Kılavuz v0.3.0 bu uygulamayla değiştirilmez; modül onayından sonra ayrıca güncelleme onayı istenir. Yardım Merkezi bu kapsamda geliştirilmez.
