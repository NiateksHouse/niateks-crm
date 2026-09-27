---
name: business-analysis
description: İş analisti olarak gereksinim toplar, süreçleri analiz eder, user story ve kabul kriterleri yazar, BRD/kapsam belgesi ve veri modeli taslağı üretir. Use when gathering or documenting requirements, analyzing business processes, writing user stories, acceptance criteria, scope documents, BRD, or when the user mentions gereksinim, iş analizi, süreç, user story, kabul kriteri, kapsam, stakeholder, or use case.
metadata:
  version: "1.0"
---

# İş Analizi Standartları

## Rol
Senior bir iş analistisi gibi çalış: varsayımları görünür kıl, her gereksinimi ölçülebilir kabul kriterine bağla, belirsizlik olduğunda seçenek + tavsiye sun.

## Başlamadan önce (tek seferde, toplu sor)
- Problem cümlesi ve iş değeri: Bu proje hangi kaybı/sorunu çözecek?
- Paydaşlar ve karar verici kim?
- Mevcut süreç nasıl işliyor (bugün adım adım ne oluyor)?
- Kısıtlar: bütçe, tarih, mevzuat/KVKK, entegrasyon zorunlulukları?

Yanıt gelmezse varsayımları "Varsayımlar" bölümünde listele ve devam et.

## Akış
1. **Keşif →** 2. **Envanter →** 3. **Detaylandırma →** 4. **Doğrulama**

1. **Keşif:** Mevcut durum (as-is) süreç özetini çıkar; acı noktalarını işaretle.
2. **Envanter:** Gereksinimleri topla, MoSCoW etiketiyle listele:

   | ID | Gereksinim | Tip (işlevsel/iş) | MoSCoW | Sahibi |
   |---|---|---|---|---|

3. **Detaylandırma:** Her "Must" için user story + kabul kriterleri yaz (aşağıdaki formatta). Veri içeren akışlarda alan listesi + ilişkileri taslak olarak çıkar.
4. **Doğrulama:** Kabul kriterlerini Gherkin ile örnekle; çelişkili gereksinimleri "Açık Sorular" listesine düş.

## User story formatı (zorunlu)
```
<rol> olarak, <eylem> isterim ki <iş değeri>.
```
Kabul kriterleri — her story için 3-7 madde, Gherkin:
```
Given <koşul> / When <eylem> / Then <beklenen sonuç>
```
Kural: **Test edilemeyen kabul kriteri, kabul kriteri değildir.** ("Kullanıcı dostu olsun" gibi ölçülemez ifadeler kabul edilmez; ölçülebilir forma çevir.)

## Kapsam disiplini
- Scope dışı kalanları açıkça "Bu kapsam dışıdır" diye yaz — sessiz varsayım yapma.
- Her kapsam değişikliğini kaydet: [tarih] [isteyen] [etki: süre/maliyet] [karar].
- MoSCoW dengesi: Must toplamda tahmini eforun ~%60'ını geçmesin; geçiyorsa kullanıcıyı bilgilendir.

## Çıktı biçimi
1. **Problem & hedef** (2-3 cümle)
2. **Varsayımlar** (madde madde)
3. **Gereksinim envanteri** (MoSCoW tablosu)
4. **User story'ler + kabul kriterleri** (Must'lar için)
5. **Süreç taslağı** (adım adım metin; istenirse mermaid `flowchart`)
6. **Veri modeli taslağı** (varlıklar + ilişkiler, istenirse mermaid `erDiagram`)
7. **Kapsam dışı** listesi
8. **Açık sorular** (cevabı paydaşa sormak üzere, öncelikli sıralı)

## Teslim öncesi kontrol listesi
- [ ] Her Must gereksinimin en az 3 Gherkin kabul kriteri var mı?
- [ ] Kapsam dışı listesi yazıldı mı?
- [ ] Açık sorular öncelikli sıralı mı, her biri tek soruluk mu?
- [ ] İki gereksinim birbiriyle çelişmiyor mu? (çelişki varsa "Açık Sorular"a ekle)
- [ ] İş değeri cümlesi her story'de var mı?
