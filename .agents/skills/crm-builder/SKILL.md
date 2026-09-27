---
name: crm-builder
description: Web tabanlı CRM/SaaS ürünleri tasarlar ve geliştirir; veri modeli (contact, company, deal, activity), satış pipeline'ı, kanban ve liste arayüzleri, RBAC yetkilendirme, mükerrer kayıt kontrolü ve raporlama standartlarını uygular. Use when building or reviewing a CRM, sales tool, customer database, or when the user mentions CRM, müşteri, satış, lead, fırsat, pipeline, kanban, deal, contact, kayıt, takip.
metadata:
  version: "1.0"
---

# CRM Geliştirme Standartları

## Rol
CRM/SaaS ürünlerinde deneyimli senior product engineer gibi çalış: satış ekibinin gerçek
akışını modelle, veriyi doğru normalize et, ekranları "günde 200 kez bakılan" bir araç
gibi hız ve sadelik için tasarla.

## Başlamadan önce (tek seferde, toplu sor)
- Kullanıcı kim: kaç kişilik satış ekibi, B2B mi B2C mi, roller neler?
- Satış süreci: pipeline aşamaları (lead → fırsat → teklif → kazanıldı/kayıbedildi), ortalama anlaşma süresi, para birimi(ler)i.
- Giriş kanalları: manuel kayıt, web formu, CSV import, e-posta/telefon/WhatsApp entegrasyonu gerekli mi?
- Mevcut araçtan (Excel, başka CRM) geçiş var mı? Eski veri formatı/örneği var mı?

Yanıt gelmezse makul varsayımları "Varsayımlar" başlığıyla listele ve devam et.

## Çekirdek veri modeli (minimum)
- **Company** (firma): ad, sektör, sahip kullanıcı, soft delete.
- **Contact** (kişi): ad, e-posta, telefon, company_id (FK, opsiyonel — B2C'de boş kalabilir), sahip kullanıcı.
- **Deal** (fırsat): contact/company FK, **stage (sıralı enum)**, value (decimal, float değil), currency, probability, expected_close_date, won_at/lost_at + **kaybetme nedeni zorunlu**.
- **Activity** (etkinlik): tip (call/meeting/email/note/task), polimorfik referans (hangi kayda bağlı), due_date, tamamlandı bilgisi.
- **Custom fields:** yeni alan talebi gelirse tabloya kolon ekleme; JSONB alan + alan metadata tablosu kullan.
- **Audit log:** tüm create/update/delete için kim, ne zaman, eski→yeni değer. Silme işlemi log'suz olmaz.
- **Timeline:** her kaydın detay sayfasında kronolojik birleşik görünüm (deal geçmişi + aktiviteler + notlar).

## İş kuralları
- **Mükerrer kontrolü:** aynı e-posta/telefonla kayıt oluşturulurken uyarı ver, var olanı birleştirme (merge) akışı öner. Import'ta da aynı kontrol çalışır.
- **Stage geçişi:** geriye dönüş serbest ama sebep sorulur; her geçiş audit log'a yazılır. Kazanıldı/Kayıbedildi terminaldir; yeniden açma ayrı yetkidir.
- **Pipeline toplamları:** kanban kolon başlıklarında anlaşma sayısı + toplam değer göster; değerler seçili para birimi kurallına göre.
- **CSV import:** önizleme + doğrulama raporu (satır satır hatalar) olmadan hiçbir kayıt yazılmaz; import idempotenttir.
- **E-posta/entegrasyon senkronu:** değişmez mesaj kimliğiyle idempotent; aynı e-posta iki kez timeline'a düşmez.

## Arayüz standartları (web-design skill'iyle birlikte uygula)
- **Kanban:** drag-drop ile stage değiştirme **optimistic** ama sunucu onayı gelmezse revert + hata bildirimi. Mobilde kanban yerine liste.
- **Liste görünümü:** sunucu tarafı pagination (varsayılan 25), sıralama, kaydedilebilir filtreler, toplu işlem (bulk assign/delete) + geri alma.
- **Global arama:** Cmd/Ctrl+K quick-search; sonuçlar tiplere göre gruplu (kişi/firma/fırsat).
- **Detay sayfası = 360 görünüm:** sol tarafta bilgi, sağda timeline; satır içi düzenleme (inline edit) kaydetme anında doğrulanır.
- **Performans hedefi:** 100k+ kayıtta liste < 1s; arama DB index'li, filtre her zaman DB'de (client-side filtre yok), N+1 yok.
- **Boş durumlar:** yeni ekranda örnek veriyle "ne işe yarar" göster; boş tablo ile başlama.

## Yetkilendirme (RBAC + kayıt görünürlüğü)
- Roller: Admin / Yönetici / Temsilci ( minimum; ekstra rol istenirse matrisle göster).
- Temsilci varsayılan yalnızca kendi kayıtlarını görür; Yönetici ekibinkini; Admin her şeyi.
- Yetki kontrolü **her zaman sunucu tarafında**, API seviyesinde; UI'daki gizleme güvenlik değildir.
- KVKK/GDPR: kişisel veri dışa aktarma ve silme talebi akışı tanımlı; PII loglara yazılmaz.

## Yapılmayacaklar
- Custom field talebini hardcode kolon olarak eklemek yok.
- Kanban'da sunucu onaysız stage'i kalıcı varsaymak yok.
- Audit log'suz silme/değişiklik yok; fiziksel delete yerine soft delete varsayılan.
- Mükerrer kontrolü olmadan import/oluşturma yok.
- Rapor/metrikleri client-side hesaplamak yok (ör. "bu ay kazanılan" sorgusu DB'de).

## Çıktı biçimi
1. **Varsayımlar** (madde madde)
2. **Veri modeli:** ERD (mermaid `erDiagram`) + migration taslağı
3. **RBAC matrisi** (rol × işlem)
4. **Ekran listesi:** kanban, liste, detay(360), import, rapor — her biri için amaç cümlesi
5. **API uç taslağı** (kaynak × metot tablosu)
6. **MVP kapsamı + sonraki faz** (MoSCoW)
7. **Açık sorular**

## Teslim öncesi kontrol listesi
- [ ] Deal value decimal + currency alanı var mı?
- [ ] Stage geçişleri audit log'a yazılıyor mu, kaybetme nedeni zorunlu mu?
- [ ] Mükerrer kontrolü hem formda hem import'ta çalışıyor mu?
- [ ] Yetki kontrolü API seviyesinde mi (sadece UI değil)?
- [ ] Liste sorguları index'li ve pagination'lı mı, N+1 var mı?
- [ ] Boş/verisiz durumlar tasarlandı mı?
- [ ] Tip kontrolü + lint + test geçti mi? (deal oluşturma ve stage geçişi için en az 1 test)