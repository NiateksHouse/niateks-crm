# Proje & Teklif Yönetimi — Modül Dokümantasyonu

> V1.11.0 ile gelen modül: 7 aşamalı proje pipeline'ı, revizyonlu teklif üretimi,
> teklif PDF çıktısı, kanban görünümü ve ürüne bağlı görseller.
> Bu doküman **nasıl çalıştığını** anlatır; sürüm geçmişi için CHANGELOG.md'ye bak.

---

## 1. Büyük resim

Proje (numune/sipariş işi) artık kendi **aşama akışıyla** takip edilir ve aşamalar
satış fırsatlarındaki (deals) aşamalardan **tamamen bağımsızdır** — ikisi ayrı işler:

```
SATIŞ FIRSATI (deals)          PROJE (projects)
İhtiyaç → ... → Sipariş onayı  Proje Açıldı → Görüşülüyor → Teklif... → Kabul/Red
   (müşteri kazanma)              (işin içi: ürün, teklif, numune)
```

Tek akış özeti:

1. Firmanın görüşme ekranından **"Proje Aç"** (isteğe bağlı birlikte fırsat açar).
2. Proje detayında **ürün kartları** açılır, her ürün bilgileri tamamlanınca **"Teklife hazır"** olur.
3. **Teklif üret** → ürün satırlarından revizyon oluşur (NX-...-R1), PDF önizle, yazdır.
4. Müşteri dönüşü yapar: indirimli **Rev.02** üret (eski revizyon arşivde kalır).
5. Sonuç: **Kabul** (referans kaydedilir) veya **Red** (nedeni zorunlu) → proje arşive düşer.

---

## 2. 7 aşama ve kuralları

Aşamalar: `Proje Açıldı → Görüşülüyor → Teklif Hazırlandı → Teklif Gönderildi → Bilgi Bekleniyor → Teklif Kabul Edildi | Teklif Reddedildi`

| Kural | Açıklama |
|---|---|
| Geçiş | Proje detayındaki **aşama şeridinde** bir adıma tıkla (veya kanbanda kartı sürükle). |
| "Teklif Hazırlandı" / "Teklif Gönderildi" | En az **1 ürün "Teklife hazır"** olmalı; yoksa API reddeder (400). |
| "Teklif Reddedildi" | **Red nedeni zorunlu** (listeden): Fiyat yüksek · Teslim süresi uzun · Rakip firma tercihi · Numune onaylanmadı · Teknik yetersizlik · Bütçe iptali. Açıklama serbest metin (opsiyonel). |
| "Teklif Kabul Edildi" | Kazanım tarihi otomatik; **Sipariş/onay referansı** yazılmazsa otomatik `PO-<1000+proje id>` üretilir. |
| Kapanmış proje (Kabul/Red) | **Arşiv modu:** aşama, ürün, teklif değiştirilemez; yeni revizyon üretilemez. Ekranda "Arşiv · kapanmış proje" rozeti. |
| Kaybedileni yeniden açma | Reddedilen proje **yalnız "Görüşülüyor"** aşamasına alınarak açılır; red nedeni temizlenir. "Kabul"a geri çevrilemez. |
| Kazanılan proje | Hiçbir aşamaya dönüştürülemez (arşivde kalır). |
| İz bırakma | Her aşama değişimi: firmanın görüşme akışına **Sistem kaydı** + denetim kaydı (log) + son hareketler. |

---

## 3. Teklifler (revizyonlu)

- **Teklif no:** `NX-<yıl>-<proje id son 4 hane>.R<revizyon>` → ör. `NX-2026-0001.R2`
- **Satırlar:** yalnızca **"teklife hazır"** ürünler girer. Her satır: ürün adı, tip, spec
  (ölçü·renk; boşsa ilk ek alan), adet dağılımı özeti, ambalaj (+adet/koli), incoterm, fiyat, tutar.
- **Snapshot ilkesi:** Revizyon üretildiği andaki satırlar **JSON olarak saklanır**. Sonradan
  ürün değişse bile eski revizyon **değişmez** — müşteriye gönderdiğin şey neyse o durur.
- **İndirim:** 0–40% (dışına çıkamaz). Not yazılmazsa otomatik üretilir ("İlk teklif." / "Revizyon: indirim %5").
- **Para birimi:** proje düzeyinde tek (USD/EUR/TL); kartta/teklifte değiştirilemez.
- **Aşama etkisi:** teklif üretilince proje (kapalı aşamada değilse) otomatik **"Teklif Hazırlandı"** olur.
- **PDF:** her revizyonun popup önizlemesi + **A4 "Yazdır / PDF olarak kaydet"** (tarayıcı yazdırma
  ile — başlık, alıcı, satırlar, brüt/indirim/net, geçerlilik +30 gün, teslim/ödeme şartları).
- Eski revizyonlar listede "arşiv (silinmez)" olarak durur; her biri ayrı PDF'lenebilir.

---

## 4. Görseller: proje geneli vs. ürüne bağlı

İki ayrı yer vardır, karışmaması bilinçli bir tasarımdır:

| | **Proje geneli görseller** | **Ürüne bağlı görseller** |
|---|---|---|
| Nerede | Proje detayındaki panel | Ürün çekmecesindeki "Görseller" bölümü |
| Bağ | `product_id = 0` (proje) | `product_id = <ürün id>` |
| Ne zaman | Görüşme/numune aşaması fotoğrafları, kumaş/renk örnekleri | O ürüne özel çizim, etiket, baskı görseli |
| Görüntüleme | Proje galerisi yalnız `product_id=0` gösterir | Çekmece galerisi yalnız o ürünü gösterir |

> **Arka plan:** İlk sürümde tek yükleme alanı vardı ve başlığı "Ürün görselleri"ydi; aslında
> projeye bağlıydı (numune/renk fotoğrafları ürün kartları bölünmeden geldiği için). V1.11.0
> ile `project_images.product_id` eklendi; eski kayıtlar otomatik "proje geneli" sayılır (0).

---

## 5. Projeler ekranı: kanban + liste

- **Pipeline görünümü:** 7 sütun, kart sürükle-bırak = aşama değiştir. Red/Kabul sütununa
  bırakınca diyalog açılır (neden/referans sorulur) — arşiv kartları sürüklenemez.
- **Filtre:** "Sadece açık" / "Kapanmış / arşiv". Kapalı projeler yalnız iki sütunda görünür.
- **Liste görünümü:** aşama, ürün hazırlık durumu, güncel revizyon, net tutar, sorumlu kolonları.
- Sütun başlıkları o aşamadaki teklif tutarlarını toplar (para birimi gruplu).

---

## 6. Veri modeli (V1.11.0'da değişenler)

```
projects (yeni kolonlar)
  stage        TEXT  DEFAULT 'Proje Açıldı'   -- deals.stage'den bağımsız
  lost_reason  TEXT  -- red nedeni (listeden)
  lost_note    TEXT  -- red açıklaması (opsiyonel)
  won_date     TEXT  -- kabulde otomatik bugün
  won_ref      TEXT  -- sipariş/onay referansı (boşsa PO-<n> otomatik)
  INDEX projects_stage(stage)

project_quotes (yeni tablo — teklif revizyon arşivi)
  id, project_id, rev, quote_date, discount, note, currency,
  lines (JSON snapshot), total_gross, total_net, created_at, created_by
  INDEX pquotes_p(project_id)

project_images (yeni kolon)
  product_id   INTEGER DEFAULT 0   -- 0 = proje geneli
  INDEX pimgs_prod(product_id)
```

**Migration:** eski veritabanlarında ilk açılışta otomatik çalışır (PRAGMA kontrollü ALTER
TABLE + index). Veri kaybı yok; geriye dönük tam uyumlu.

---

## 7. API uçları

Tüm POST isteklerinde header zorunlu: `X-Requested-With: niateks`. Kimlik: oturum çerezi.

| Uç | Metot | İş |
|---|---|---|
| `/api/projects/stage` | POST | Aşama değiştir. Gövde: `{id, stage, lost_reason?, lost_note?, won_ref?}`. Kurallar §2'deki gibidir (geçersiz aşama/eksik neden/hazır ürün yok → 400). |
| `/api/quotes?project_id=` | GET | `{quotes (no dahil), lines (güncel hazır ürünler), drafts (taslak ürün adları), currency, next_rev}` |
| `/api/quotes` | POST | Revizyon üret. Gövde: `{project_id, discount, note?}` → yeni revizyon + sistem notu + aşama ilerlemesi. |
| `/api/projects/images` | POST | Multipart: `project_id`, `files[]`, opsiyonel `product_id` (0=proje geneli; ürün o projeye ait değilse 400). |
| `/api/projects/images?project_id=&product_id=` | GET | `product_id` verilmezse tümü; `0` = proje geneli; `>0` = o ürün. |
| `/api/projects` | GET | Her projede artık: `stage, lost_reason, lost_note, won_date, won_ref, quote_count, quote_rev, quote_net, quote_currency, quote_date, products_ready, products_total, created_at`. |

Örnek (local):

```bash
# aşama değiştir
curl -H "X-Requested-With: niateks" -H "Cookie: $C" -H "Content-Type: application/json" \
  -d '{"id":1,"stage":"Teklif Reddedildi","lost_reason":"Fiyat yüksek","lost_note":"Rakip %15 düşük"}' \
  http://127.0.0.1:8090/api/projects/stage

# revizyon üret
curl -H "X-Requested-With: niateks" -H "Cookie: $C" -H "Content-Type: application/json" \
  -d '{"project_id":1,"discount":5}' \
  http://127.0.0.1:8090/api/quotes
```

---

## 8. Yetkiler

| İşlem | Kim yapabilir |
|---|---|
| Aşama değiştirme, teklif üretme | Yönetici **veya** projenin sorumlusu/oluşturanı (`can_edit_project`) |
| Ürün düzenleme | Yönetici, proje sorumlusu/oluşturanı, veya firmanın sorumlusu/oluşturanı |
| Görsel ekleme/silme | Yönetici ya da proje sorumlusu/oluşturanı |
| Görüntüleme | Mevcut proje görünürlük kuralları değişmedi (satıcı kendi firmalarını görür) |

---

## 9. Test

`nx_quotes_test.js` — 47 testlik API regresyon seti (captcha+login, aşama kuralları, teklif
revizyonları/snapshot, red nedeni, arşiv, görsel bağı, vals kodlaması, log kayıtları):

```bash
node nx_quotes_test.js http://127.0.0.1:8090     # veya 8091
# Çıktı: "SONUÇ: 47 geçti, 0 kaldı"
```

Not: Betik `tunc` kullanıcısıyla local veritabanında çalışır; her çalıştırmada test
verisi üretir (canlıya bağlanmaz). Sürüm çıkmadan önce koşması önerilir.

---

## 10. Bilinen sınırlar / sıradaki fikirler

- PDF tarayıcı yazdırmasıyla üretilir (sunucuda dosya oluşmaz); "indir" istenirse tarayıcıdaki
  "PDF olarak kaydet" kullanılır. Sunucu tarafı PDF üretimi (ileride) ek bağımlılık gerektirir — istenirse konuşulur.
- Teklif e-postayla gönderim henüz yok (SMTP altyapı hazır; istenirse eklenebilir).
- Ürün görselleri teklif PDF'ine girmez (yalnız satırlar). İstenirse "numune fotoğraflı" ek
  revizyon şablonu düşünülebilir.
- Kanbanda alt/üst aşama atlama serbesttir (kural kontrolleri API'de uygulanır).
