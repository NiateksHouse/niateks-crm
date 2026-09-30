# Alpha.27 — Dokümanlar modülü
30 Eylül 2026

## Doğrulama
- Kod: 1b3262d54b6b1970fbe3a7a79e9be2982dce37a4.
- GitHub Actions: https://github.com/NiateksHouse/niateks-crm/actions/runs/36647695375
- MySQL 8.0.46 / PHP 8.4.26 / PHPUnit 12.5.37: 65 test, 584 assertion başarılı. Ayrı upgrade regresyonu 1 test / 8 assertion başarılı.
- Pint, Composer audit, PHP sözdizimi, Blade derleme, kaynak/runtime paket bütünlüğü başarılı.
- Gerçek HTTP: oturum/CSRF, giriş/çıkış, oturum tekrar kullanımı, davet, multipart belge yükleme, özel dosya önizleme güvenlik başlıkları, başka aktif kullanıcının ve misafirin dosyaya erişiminin engellenmesi başarılı.
- Yeni testler: admin özel belge ACL atlayamaz; aramada ad/açıklama/dosya/kategori/yükleyen/tarih sızıntısı yok; kullanıcı/rol/internal izinleri; yetenek+ACL birleşimi; eski dosyalarda ACL geri alma; değişmez sürümler; stale revision 409 ve sahipsiz dosya temizliği; çapraz belge sürüm IDOR; arşiv/restore ve saklama; sahte MIME, boyut, Office makro reddi; Office indirme fallback; Türkçe DB/Collator sırası; yetkisiz kategori oluşturma; metadata alan whitelist; önceki belge bilgileri ve audit; kalıcı ortam migration geri alma koruması.
- Yerel PHP sözdizimi ve Blade önbelleğe derleme başarılı. Yerel MySQL yok; SQLite kullanılmadı.
- İlk CI kod biçimi nedeniyle durdu; düzeltildi. İkinci CI'de yalnız yeni migration koruma testinin hata mesajı beklentisi uyuşmadı; koruma zaten doğru çalışıyordu. Assertion gerçek koruma mesajına göre düzeltildi. Yukarıdaki tam tekrar başarılıdır.

## Davranış ve sınırlar
Onaylı v1.6 tasarımı korunur. Dokümanlar menüsü, kategori ağacı, Türkçe alfabetik liste, ACL filtreli arama, form, detay, değişmez dosya sürümleri, özel önizleme/indirme ve arşiv/restore eklendi. Yeni dokümanlar Özel seçili başlar. Metadata geçmişi ve ACL değişimleri özel audit kaydında saklanır; ekran son 30 işlemi gösterir, veri tabanındaki daha eski kayıtlar silinmez. Dosya geçmişi sayfalanır.

Admin rolü tek başına özel belgeyi açmaz. Belge sahibi de gerekli görüntüleme yetkilerine sahip olmalıdır. Mevcut admin/representative rolleri kullanılır; temsilci varsayılan olarak erişimi verilen belgeleri okuyup indirebilir. İç genel internete açık değildir. Önceki sürümler güncel ana belge ACL'sini miras alır.

Azami uygulama limiti 10 MB; düşük PHP sınırı uygulanır ve gösterilir. MIME/yapı denetimi antivirüs değildir. Office belgeleri harici bir servise gönderilmez; indirilir. PDF ve görseller nosniff/no-store/sandbox başlıklarıyla yetki denetimli endpoint'ten açılır. Arşivlenen belgeler, dosyalar ve audit süresiz saklanır; otomatik fiziksel silme/purge yoktur. Dosya yedeği DB yedeğinden ayrı planlanmalıdır; geri yükleme tatbikatı yapılmış sayılmaz.

Yardım Merkezi bu kapsamda geliştirilmedi. Kullanım kılavuzu v0.3.0 değiştirilmedi; kullanıcı onayıyla sonraki sürümde güncellenecek. Önceki tamamlanmamış teklif/sipariş/üretim/finans modülleri bu sürümle tamamlanmış sayılmaz.

## Test sunucusu
- 30 Eylül 2026 00:02:03 UTC: result.json success; 25 dosya, dört yeni belge tablosu. users/companies/projects/activities satır sayıları değişmedi. Dosya depolama web kökü dışında.
- Chrome, Nigar oturumu: Dokümanlar menüsü ve onaylı çalışma alanı; Türkçe kategori ağacı; yeni belge formunda Özel varsayılanı ve hostingde etkili 10,0 MB sınırı doğrulandı.
- Gerçek şirket verisi içermeyen “DEMO — Doküman ve sürüm kontrolü” kaydı /documents/1 açıldı. Sentetik PNG v1 ve v2 yüklendi; listede tek kayıt/güncel v2, Geçmiş altında v1 görüldü. Özel önizlemede doğru v2 görseli açıldı.
- Arşivleme, arşiv listesinde dosya aksiyonlarının kapanması, geri getirme, Türkçe arama ve v2'nin korunması doğrulandı. Tarayıcı konsolunda hata/uyarı yok. DEMO belge kullanıcı incelemesi için Özel durumda bırakıldı; başka kullanıcıya paylaşım yapılmadı.
- Kullanıcının açık onayıyla yalnız updates/alpha27/deploy.php geçici Cron kaldırıldı. Mevcut saatlik niateks_app/cron_report.php görevi korundu. Sonuç/manifest/önceki kaynaklar sunucuda kaldı.
- Yerel SSD'ye hosting/DB yedeği indirilmedi. Canlı koza.niateks.com ve diğer uygulamalar değiştirilmedi.
