# KOZA tasarım, dil ve ana işleyiş kabul listesi

Kaynak sırası: görsel tasarım için 2 Ekim onaylı PNG; etkileşim örnekleri için functional prototype V1; veri/karar kuralları için Nihai Brief v3. Dil yalnız TR ve ENG. Giriş sayfası değişmez.

| ID | Kabul ölçütü | Uygulama / doğrulama |
|---|---|---|
| D01 | Dil menüsü ve profil yalnız TR/ENG sunar | İki seçenek; eski en-GB/en-US kayıtları ENG olarak okunur. Saat dilimi korunur; backend regresyon testi. |
| D02 | Giriş sayfası ve görselleri değişmez | Mevcut login-baseline SHA denetimi. |
| D03 | Onaylı logo, kumaş, Always Smile ve alt marka kullanılır | Orijinal PNG değiştirilmeden dekoratif bölgeleri CSS ile gösterilir. Ekranın kendisi canlı HTML'dir; sahte müşteri/profil bilgisi görüntüye gömülmez. |
| D04 | Ana ekran üç karar kartı, House Pulse, öneri, takvim, haftalık sonuç, faaliyet, Ask Koza düzenindedir | Desktop üç sütun; dar ekran iki, mobil tek sütun. Boş veri de aynı bölümleri korur. |
| D05 | Bugün ile My Focus ayrıdır | Bugün: açık hizmet konusu, doğrulanmış ticari karar, ödeme incelemesi. My Focus: kişisel Şimdi/Sonra/Bekleyen. |
| D06 | Öncelik kayda, sahibe ve varsa tarihe bağlanır | Dashboard API yalnız erişilebilir kayıtları döndürür; kayda açılan düğmeler. Ticari karar alanı yetkili kişilere açılır. |
| D07 | Haftalık sonuçlar toplam kayıt sayısı değildir | Haftanın olay zamanları: tekil ilerleyen fırsat, Won reorder müşterisi, son tarihe kadar tamamlanan görev, çözülen vaka. Yinelenen geçiş, eski olay, demo ve özel kayıt testleri. |
| D08 | Veri yokluğu sağlıklı/başarılı diye sunulmaz | Boş ölçümler çizgiyle; ticari sağlık politika ayarıyla eşitlenmez. Açık vaka sayısı genel sağlık skoru gibi gösterilmez. |
| D09 | Önerinin dayanağı görünür | Teslim edilmiş siparişin takip tarihi ve açık vaka bulunmaması; stok/ihtiyaç/ekonomi yeniden doğrulanır. Bağlı olmayan AI için üretilmiş analiz iddiası yok. |
| D10 | Son aktiviteler gerçek olayları gösterir | Kayıtların son düzenlenme listesinin yerine izinli olay geçmişi; snapshot/maliyet payload'ı dışarı çıkarılmaz. |
| D11 | Örnek ve gerçek veriler ayrıdır | Önizleme fixture verisi yalnız yerelde. Gerçek ortama örnek müşteri veya ticari eşik eklenmez. Demo kayıtları dashboard ölçümlerinden hariçtir. |
| D12 | TR/ENG ve mobil kullanılabilirlik doğrulanır | Dil kalıcılığı, menü/düğmeler, yatay taşma ve klavye odağı incelenir. |
| D13 | Yayın geri alınabilir | Yeni özel yedek, checksum, mevcut veri sayıları ve login korunması; GitHub CI geçmeden yayın yapılmaz. |

## Bu düzeltmenin kapsam sınırı

Bu liste tasarım, dil ve ana dashboard davranışının düzeltmesidir. Tüm ticari/operasyon kapsamının tamamlandığı anlamına gelmez. Minimum katkı, numune/kargo bütçesi ve veri geçerlilik süreleri kullanıcı tarafından ertelenmiştir; gerçek onay kapıları kapalı kalır. AI, e-posta ve stok/maliyet entegrasyonlarının tamamlanması ayrı kabul gerektirir. Kullanıcı fotoğrafı sağlanmadığından mevcut hesaba ait ad ve baş harf gösterilir; referanstaki örnek kişi fotoğrafı gerçek kullanıcı yerine kullanılmaz. Takvim mevcut görevlerin tarihlerini gösterir; kayıtta bulunmayan saat uydurulmaz.
