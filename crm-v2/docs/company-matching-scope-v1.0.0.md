# Firma sözlüğü ve açıklanabilir mükerrer kontrolü — v1.0.0

30 Eylül 2026 · Kullanıcı talebi · Alpha.24 geliştirme kapsamı

## Amaç ve kararlar
Aynı firmanın farklı adlarla tekrar açılmasını kullanıcıya açıklayarak azaltmak. Sözlük yalnız açık onayla öğrenir. Puan hiçbir koşulda otomatik birleştirme veya otomatik öğrenme yetkisi değildir. Canonical kayıt şirket kartıdır; görüntülenen marka adı aynen korunur. Normalize edilmiş arama anahtarı yalnız eşleştirme içindir.

Firma bilgileri ortak, kişiler yetkiye göre görünür (temsilci kendi kişileri; admin tümü). Aktif kullanıcı aynı/farklı onayı ve manuel varyasyon ekleyebilir. Yalnız admin karar geri alabilir ve puan ayarlarını değiştirebilir. Düşük puanlı bir varyasyon da ancak açık insan doğrulamasıyla eklenebilir; düşük puan otomatik öğrenilmez.

## Gereksinimler ve kabul ölçütleri

### M1 — Kullanıcı olarak yeni kaydı karşılaştırmak isterim; mevcut kaydı yanlışlıkla çoğaltmayayım.
- Given yeni firma/kişi / When eşleşmeler inceleme eşiğine ulaşır / Then kayıt oluşturulmadan gerekçeli inceleme açılır.
- Given yalnız düşük puanlı adaylar / When normal kaydet kullanılır / Then normal akış sürer ve sözlük öğrenmez; “Önce eşleşmeleri incele” ayrıca kullanılabilir.
- Given 100 puan / When kayıt incelenir / Then mevcut kaydı açma veya bilinçli ayrı kayıt seçenekleri vardır; otomatik merge yoktur.
- Given boş alanlar / When puan hesaplanır / Then boş=boş puan kazandırmaz. Vergi numarası yalnız aynı ülke içinde eşleşir.

### M2 — Kullanıcı olarak doğruladığım ticari adları öğretmek isterim; daha sonra doğru firma önerilsin.
- Given “aynı” açık onayı ve açıklama / When kaydedilir / Then varyasyon aynı yazımla canonical firma kimliğine bağlanır, firma adı değişmez.
- Given manuel varyasyon / When doğrulama kutusu işaretli değil / Then öğrenme reddedilir.
- Given aynı varyasyon birden fazla farklı firmaya onaylanmış / When aranır / Then adaylar ayrı sunulur; biri kendiliğinden seçilmez.

### M3 — Kullanıcı olarak “farklı” kararımı hatırlatmak isterim; aynı uyarıyı tekrar görmeyeyim.
- Given iki mevcut kayıt / When açık farklı onayı verilirse / Then yalnız bu çift için, her iki yönde uyarı bastırılır.
- Given çiftin eşleşme bilgileri değişirse / When kontrol edilir / Then eski karar yeni bilgileri bastırmaz.
- Given normal “yeni oluştur” / When farklı kutusu işaretlenmemişse / Then sistem tüm adayları farklı kabul etmez.

### M4 — Yönetici olarak kararları denetlemek ve geri almak isterim; yanlış öğrenmeyi düzelteyim.
- Given admin / When gerekçeyle geri alır / Then karar silinmez, kim/ne zaman/neden bilgisi eklenir, etkisi kaldırılır.
- Given temsilci / When geri alma veya ayar değiştirme isteği gönderir / Then sunucu 403 verir.
- Given ayar değişikliği / When kaydedilir / Then önceki ve yeni değerler aktörle denetim olayına yazılır; eşzamanlı eski sürüm değişikliği 409 alır.

## Varsayılan ayarlar
Vergi 50; site alan adı 40; e-posta 40; telefon 35; isim benzerliği 20; aynı kişi adı+firma 20; şehir+ülke 5; onaylı varyasyon 70. Toplam 100 ile sınırlı. İnceleme 70, güçlü uyarı 95, isim benzerliği sınırı %85. Hepsi DB ayarından okunur ve admin ekranından düzenlenir. Puan istatistiksel olasılık değildir.

Normalize: isimde Unicode harf/rakam ve boşluk; I/İ/ı için ortak arama şekli; özgün görüntü yazımı değişmez. Telefon yalnız ülke kodu verilmiş değerlerle (+ ve 00 eşit) karşılaştırılır; yerel telefon ülke kodu tahmin edilmez. Domain verilen web sitesinin hostudur; www ve büyük/küçük harf normalize edilir, ağ isteği yapılmaz. E-posta sağlayıcısından firma alan adı türetilmez. Alan adının aynı olması aynı tüzel kişi demek değildir.

## Veri modeli
- self_learning_company_dictionary → company + onay kararı; özgün alias ve arama anahtarı.
- matching_decisions → kaynak/hedef kimliği, aynı/farklı, açıklama, aktör, zaman, kanıt ve o anki ayarlar, geri alma bilgileri.
- matching_events → onay, yeniden doğrulama, ayrı oluşturma, geri alma ve ayar değişikliğinin eklenen olayları.
- matching_keys → tür+alan+normalize hash indeksleri; yalnız aday bulma için. Hash kişisel veriyi anonimleştirme iddiası değildir.
- matching_settings → DB ayarları + sürüm; admin değişiklikleri denetlenir.
- contacts → firma bağlantısı, ad/e-posta/telefon, oluşturan; ilk kayıt ve görünürlük ekranı.
- companies → web sitesi ve ülkeye bağlı VAT/vergi bilgisi; mevcut revizyon geçmişine dahil.

İndeksli aday bulma kesin alan anahtarı veya en az üç harfli isim parçalarının ilk üç karakterini kullanır. Bu nedenle bütün harfleri farklı bir yazımı bulacağı garanti edilmez; doğrulanmış varyasyon elle eklenebilir. İsim benzerliği Unicode karakter bazlı düzenleme uzaklığıdır; AI servisi kullanılmaz. Kayıt çiftlerinin alan izi, değişmiş bilgilerde eski kararın yanlış uygulanmasını engeller.

## Veri tabanı geçişi ve sınırlar
Önceki mutlak ad/e-posta/telefon tekillikleri, bilinçli ayrı kayıt akışı için normal indekslere çevrilir. Kayıt veya revizyon silinmez. Geri dönüş migration'ı geçmişi silmez; kontrollü yedek/ileri düzeltme gerekir. Mevcut firmalar indekslenir. Test ve canlı ayrı kalır.

Bu kapsam otomatik/bilinçli fiziksel merge, kişi düzenleme/arşivleme, AI isim çıkarımı, otomatik ithalat veya tüm önceki demo ekranlarının aktarımı değildir. Kişi alanlarının revizyonlu düzenlemesi sonraki iş; bu sürüm yeni kişi oluşturma ve görüntüleme sağlar. Canonical müşteri/tedarikçi tek firma kartıdır. Önceki onaylı tasarımın bütün menülerini aktarma işi ayrıca korunur.

Kullanım kılavuzu mevcut v0.3.0 sürümü üzerinde değiştirilmez; fonksiyon inceleme/onayı sonrasında ayrıca yeni kılavuz sürümü için onay alınır.
