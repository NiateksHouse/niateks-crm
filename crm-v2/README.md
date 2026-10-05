# KOZA CRM 2.0.0-alpha.27

Laravel / PHP 8.4 / MySQL 8 CRM. Onaylı v1.6.0 çalışma alanında firma, kişi, özel proje/görüşme ve kontrollü firma eşleştirmesine Dokümanlar modülü eklenir. Bu sürümün doğrulama ve yayın durumu VALIDATION.md dosyasındadır.

Dokümanlar: parent/child kategori ağacı, Türkçe alfabetik sıralama, yetki filtreli arama, özel depolama, izinli önizleme/indirme, değişmez dosya sürümleri, bilgi revizyonları, audit ve geri alınabilir arşiv. Admin rolü özel belge erişimini aşmaz. Tüm yeni belgeler Özel seçili başlar. Erişim hem rol yetkisi hem belge ACL'si ile denetlenir; önceki sürümler de aynı ACL'yi kullanır.

Uygulama limiti 10 MB, PHP limiti daha düşükse etkili sınır arayüzde gösterilir. PDF ve görseller tarayıcıda; Office dosyaları güvenli indirme ile açılır. Dış önizleme servisine belge gönderilmez. Dosya MIME/yapı doğrulaması antivirüs değildir. Arşivlenen dosyalar özel depoda süresiz saklanır; otomatik fiziksel silme bulunmaz.

Ürün, iş takibi, numune, teklif, sipariş/üretim ve finans ekranları hâlâ hazırlık aşamasındadır. Yardım Merkezi ayrı sonraki iştir. Kılavuz v0.3.0 değiştirilmedi; güncellemesi kullanıcı onayı gerektirir.

Test sitesi: https://test.koza.niateks.com . Canlı site ve diğer hosting uygulamaları bu sürümün kapsamı dışındadır. Sırlar, gerçek kullanıcı dosyaları ve veritabanı yedekleri kaynak paketine girmez.
