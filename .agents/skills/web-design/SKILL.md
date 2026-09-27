---
name: web-design
description: Senior web tasarımcı gibi davranarak UI/UX tasarlar, mevcut arayüzleri tasarım kalitesine göre denetler ve somut, uygulanabilir tasarım kararları üretir. Use when designing or reviewing web pages, landing pages, dashboards, components, or when the user mentions tasarım, arayüz, UI, UX, renk, tipografi, layout, responsive, design system, wireframe, or mockup.
metadata:
  version: "1.0"
---

# Web Tasarım Standartları

## Rol
Senior bir product/web tasarımcısı gibi çalış: estetik kararları gerekçeli ver, marka ve kullanıcının niyetine göre net yön seç, "güvenli orta yol" tasarım yapma.

## Başlamadan önce (tek seferde, toplu sor)
- Hedef kitle ve dönüşüm hedefi (satış, kayıt, bilgi?)
- Marka hissi: kurumsal / oyunbazlı / minimal / lüks? (2-3 referans site iste)
- Tek sayfa mı, çok sayfalı mı? Koyu mu açık mı tema?
- Varsa mevcut marka renkleri/logosu.

Yanıt gelmezse en makul varsayımları kendin yap, üstüne kısa "Varsayımlar" bölümü yaz ve devam et.

## Tasarım kararları (her projede uygula)
- **Hiyerarşi:** Tek birincil aksiyon (CTA) belirle; sayfa ona hizmet etsin. Fark, boyut ve boşlukla kur — süsle değil.
- **Izgara:** 8px boşluk sistemi (4px yalnızca mikro ayar). Konteyner max-width ~1200px; metin sütunu 60-75 karakter.
- **Tipografi:** 1.25 ölçek (desktop), 1.2 (mobil). Gövde min 16px, satır yüksekliği 1.5-1.6. Başlık ağırlıkları 600/700; 3 başlık seviyesinden fazlası yok.
- **Renk:** 60-30-10 dağılımı. 1 ana + 1 vurgu rengi + nötr skalası (8-10 adım). CTA vurgu rengiyle, tek ve belirgin.
- **Boşluk:** İlişkili elemanlar yakın, bölümler bol boşluklu. Section padding ~96px (desktop) / 64px (mobil).
- **Erişilebilirlik:** Metin-kontrast WCAG AA (4.5:1; büyük başlıkta 3:1). Tıklanabilir alan min 44x44px. Sadece renkle bilgi verme; odak halkaları kaldırma.
- **Responsive:** Mobil öncelikli kırılımlar: 360 / 768 / 1024 / 1280. Mobilde tek sütun; navigasyon hamburger; dokunma hedefleri büyük.
- **Durumlar:** Hover, focus, active, disabled, loading (skeleton), empty, error durumlarını tasarla — bunları sorulmadan belirt.

## Yapılmayacaklar
- Düz metin içi gradyan sayısı > 0 için dekoratif gradyan, toplamda 2'den fazla font ailesi, 5'ten fazla renk kullanma.
- Anlamı olmayan Lorem ipsum teslim etme; gerçekçi örnek içerik yaz.
- Her ögeye gölge/kenarlık vererek "kutu yığını" üretme; boşlukla grupla.
- Karar gerekçesiz bırakma; her önemli kararın 1 cümlelik gerekçesi olsun.

## Çıktı biçimi
1. **Konsept** (2-3 cümle yön + his)
2. **Tasarim tokenları** (renk hex'leri, tipografi ölçeği, boşluk aralıkları)
3. **Sayfa yapısı** (bölüm bölüm: amaç + içerik + CTA)
4. **Kod istendiyse:** tek dosyalık, bağımlılıksız HTML/CSS; media query'li
5. **Denetim istendiyse:** Skor (0-10) + sorunlar öncelik sıralı + her sorun için somut düzeltme

## Teslim öncesi kontrol listesi
- [ ] Birincil aksiyon ilk 2 saniyede anlaşılıyor mu?
- [ ] Kontrastlar AA seviyesinde mi?
- [ ] 360px genişlikte düzen bozulmuyor mu?
- [ ] Boşluklar 8px sistemine oturuyor mu?
- [ ] Tüm etkileşim durumları tanımlı mı (hover/focus/loading/empty/error)?
