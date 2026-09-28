# Niateks Projeleri — Çalışma Rehberi

> Bu rehber sana (Tunc) özel: projeyi **gerçek bir yazılım projesi gibi** yönetmen için
> adım adım ne yapacağını anlatır. Kaybolursa GitHub'da da durur (niateks-crm repo'sunda).

---

## 1. Büyük resim: 3 katman

```
[1] YEREL BİLGİSAYAR          [2] GITHUB               [3] CPANEL SUNUCU
    Projects/ klasörleri          NiateksHouse/*           srvc43.trwww.com
    kodun ASIL evi                kodun güvenli kopyası    ziyaretçilerin gördüğü site
    (değişiklik burada)           + tüm geçmişi            (koza.niateks.com vb.)
```

**Altın kural:** Değişiklik hep soldan sağa akar: yerelde yap → GitHub'a işle → canlıya yükle.
Canlıda elle hiçbir şeyi düzeltme (o düzeltme kaybolur — buna "teknik borç" denir).
Canlıya acil müdahale gerekiirse: önce yerelde düzelt, sonra yükle.

---

## 2. Bir istek geldiğinde günlük akış (en sık yapacağın şey)

1. **İsteği bana yaz** — "login ekranına şunu ekle" gibi. Ben yerelde kodu değiştirir,
   local sunucuda (`http://127.0.0.1:8091`) test eder, sana rapor veririm.
2. **Onay ver** — rapordan sonra "tamam" demen yeterli.
3. **Sürüm paketle** — proje klasöründe:
   ```bash
   bash surumle.sh "Bu sürümde ne değişti"
   ```
   Bu TEK komut otomatik olarak şunları yapar:
   - sürüm numarasını yükseltir (V1.9.2 → V1.9.3)
   - zip paketi oluşturur (`Versiyon/Niateks_CRM_vX.Y.Z.zip`)
   - CHANGELOG.md'ye notu yazar
   - **tunc@niateks.com'a rapor maili** gönderir (yapılanlar + eklenen/çıkarılan/değişen dosyalar)
   - git commit + tag (`v1.9.3`) + **GitHub'a push**
4. **Canlıya yükle** — rapor mailindeki **"Değişen dosyalar"** listesindeki dosyaları
   cPanel → File Manager → `public_html/koza/` içine sürükle (üzerine yaz = Evet).
   (Ya da bana "yükle" de — ben komutla atarım.)
5. **Doğrula** — siteyi aç, giriş ekranında sürüm numarasını gör; veya:
   ```
   https://koza.niateks.com/  → sağ üstte/köşede Vx.y.z
   ```

Senden sürekli istenen tek emek **4. adım** (dosya yükleme). Gerisi otomatik ya da bende.

---

## 3. Sürümlendirme mantığı (SemVer — 2 dakikada öğren)

| Değişiklik | Komut | Sürüm |
|---|---|---|
| Hata düzeltme | `bash surumle.sh "not"` | V1.9.2 → **V1.9.3** |
| Yeni özellik | `bash surumle.sh "not" minor` | V1.9.3 → **V1.10.0** |
| Büyük/kırıcı değişiklik | `bash surumle.sh "not" major` | V1.x → **V2.0.0** |

Kural: ne kadar sık sürüm çıkarırsan o kadar güvenli olursun. Küçük adımlar > büyük sıçramalar.

---

## 4. GitHub'da ne var, nasıl bakılır?

Hesabın: **github.com/NiateksHouse** (Private depolar: niateks-crm, niateks-website, niateks-admin)

- **Geçmiş:** repoya gir → "Commits" → her satır bir "fotoğraf" (commit). Tıkla, ne değiştiğini renkli gör (yeşil=eklenen, kırmızı=silinen satır).
- **Etiketler (tag):** "Tags" → `v1.9.2` = o anki kodun donmuş hali. Zip'teki sürümle aynı isim — **tag = zip** eşleşmesi.
- **"Bu dosya kim ne zaman neden değiştirdi?"** → dosyayı aç → "History".
- **Eski bir sürümün kodu lazım?** → Tags → v1.9.2 → "Browse files".

---

## 5. Acil durum: geri dönüş

1. **En hızlı yol (2 dk):** `Versiyon/` klasöründen önceki zip'i sunucuya yükle (Fileman, overwrite). Sürümler zip'lerde duruyor — bu yüzden zip arşivi hâlâ değerli.
2. **Kalıcı yol:** bana "v1.9.1'e geri dön" de — git'ten o tag'in dosyalarını çıkarıp yeni bir sürüm (V1.9.4 "geri dönüş") olarak paketleriz. Böylece geçmiş bozulmaz.
3. **Veri etkilenmez:** veritabanı (`nx_data/`) sürümlerden bağımsız; sürüm düşürmek müşteri verilerini silmez.
4. **Ama:** yedekleme otomasyonu henüz YOK — sunucudaki tek veri kopyası orada duruyor. Bu, yapılacaklar listesinde 1 numara.

---

## 6. Güvenlik kuralları (ezberle)

1. **`nx_data/` asla GitHub'a girmez** — içinde veritabanı, oturumlar ve mail şifresi var. `.gitignore` zaten koruyor; elle eklemeye kalkma.
2. **Şifreleri chat'te/mesajda düz metin paylaşma.** Mümkünse "şifre değişti, mail_config.php'yi ben güncelleyeceğim" de; ben dosyayı kodla günceller, içeriği ekrana basmam. → **Öneri:** SMTP şifresi bu sohbette geçtiği için yakın zamanda cPanel'den değiştir, bana "değişti" demen yeterli.
3. **Test edilmemiş kodu canlıya yükleme** — ben her şeyi local'de test ederim; raporda "testler" bölümü olmayan sürümü yükleme.
4. **Reposlar Private kalsın.** Birine erişim vereceksen GitHub → Settings → Collaborators ile, sadece güvendiğin kişilere.
5. **cPanel token'ı** (deploy'da kullandığımız uzun anahtar) sadece bu bilgisayarda durur; kimseyle paylaşma.

---

## 7. Öğrenme planı (haftada ~15 dakika)

- **Hafta 1 — Yükleme pratiği:** Rapor mailindeki listeden TEK dosyayı Fileman'la kendin yükle + sürümü doğrula. (5 dk)
- **Hafta 2 — GitHub gezinmesi:** niateks-crm reposunda Commits'i gez, son commit'inin diff'ini oku, Tags sekmesine bak. (10 dk)
- **Hafta 3 — Uçtan uca sen yap:** Basit bir yazı değişikliği (ör. sidebar'daki cümle) — bana "ben yapacağım, yol göster" de; sürüm paketle, yükle, doğrula. Ben sadece izlerim.
- **Hafta 4 — Geri dönüş tatbikatı:** Önceki zip'i yükleyip sonra güncel sürüme dön. Acil durumda panik yok, kas hafızası olur.

---

## 8. Kim ne yapıyor? (bölüştürme)

| İş | Kim |
|---|---|
| Ne yapılacağına karar vermek | **Sen** |
| Kod yazmak, local test, paketleme komutu, push, rapor | **Ben (Codebuff)** |
| zip + CHANGELOG + rapor maili + git commit/tag/push | **Otomatik** (surumle.sh) |
| Canlıya dosya yükleme + doğrulama | **Sen** (veya bana yaptır) |
| Şifre/cPanel yönetimi | **Sen** (bana asla şifre yazdırmayı zorunlu tutma) |

---

## 9. Modül dokümantasyonları (neden, nasıl bakılır?)

- **MODUL-PROJE-TEKLIF.md** — V1.11.0 Proje & Teklif Yönetimi: 7 aşama kuralları, revizyonlu
  teklif (`NX-YYYY-NNNN.Rn`), PDF çıktısı, kanban, ürüne bağlı görseller, API ve test.
  CRM'de bir aşama/teklif davranışını anlamadığında önce ona bak.
- **CHANGELOG.md** — hangi sürümde ne değişti (otomatik yazılır; elle ekleme yapma).

> **Bilinen durum:** KURULUM.md eski bir HTML mockup'tan kalmadır (gerçek kurulum adımları
> içermez; içindeki "Ürün Ağacı (BOM)" ifadesi marka/mockup dokümanına aittir, gerçek CRM'de
> BOM tablosu yoktur). Kurulum aslında: zip'i sunucuda aç + siteyi aç + ekrandaki kurulum
> sihirbazını izle. İstenirse bu dosya gerçek bir kurulum dokümanıyla değiştirilir.

## 10. Sözlük (mini)

- **commit:** projenin o andaki fotoğrafı + açıklaması
- **push:** fotoğrafları GitHub'a göndermek
- **tag:** bir commit'e isim vermek (v1.9.2 gibi)
- **repo (depository):** projenin GitHub'daki evi
- **deploy:** canlıya yükleme
- **diff:** iki sürüm arasındaki satır satır fark
- **migration:** veritabanı yapısı değişikliği (otomatik uygulanır, veri kaybı olmadan)
- **rollback:** önceki sürüme geri dönme

---

*Son güncelleme: 2026-09-27 · Niateks House CRM V1.9.2 · Bu rehber surumle.sh akışına dahildir.*
