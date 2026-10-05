# Alpha.27 — Dokümanlar

Tamamlanan geliştirme ve test yayını VALIDATION.md içinde. Kullanıcı incelemesi için https://test.koza.niateks.com/documents ekranı açık. Doküman 1 yalnız sentetik v1/v2 dosyaları içerir, Özel durumdadır. Geçici kurulum Cron işi açık kullanıcı onayıyla kaldırıldı.

Sonraki adım: kullanıcı modülü inceleyip onayladığında kılavuz güncellemesini ayrıca onaya sun. HELP son geliştirme aşamasında. Diğer hazırlık modülleri henüz uygulama işlevi değildir.

## Önceki geliştirme notu (Alpha.22)

# Alpha22 — Firma bağlantılı proje ve görüşme akışı

Onaylı alpha21 giriş ve menü düzeni korunur. Firma ortak; projeler ve görüşmeler yalnız sorumlu kullanıcıya, ayrıca iki yöneticiye görünür. Projeye eklenen tek görüşme hem firma hem proje akışında gösterilir. Metinler kaçışlı, e-postalar kapalı ayrıntı altında; AI analizi ve gönderim yoktur. Yeni bilgi revizyon ekler; önceki sürümler yetki kontrollü geçmiş ekranındadır.

Bu dilim: proje açma, ad/açıklama revizyonu, ilk üç çalışma durumu, firma/proje görüşmeleri ve revizyonları. Teklif/sipariş aşamaları ile kanban sürükleme, ürün/numune, finans ve AI daha sonraki dilimde; tamamlanmış gibi gösterilmez. Kılavuz değişmedi.

Kabul: başka temsilci URL ile özel işe erişemez; yönetici tümünü görür; aynı görüşme iki akışta tek kaynaktır; geçmiş metin silinmez; eski sürümle yazım 409 döner; e-posta HTML çalıştırmaz; firma arşivlenince bağlı işler görünmez. Mevcut test veritabanı genişletilir, sıfırlanmaz.
