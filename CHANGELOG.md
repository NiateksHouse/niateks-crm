# Değişiklik Günlüğü

Sürüm şeması: **SemVer** — `VMAJOR.MINOR.PATCH`
- PATCH (1.9.**1**): hata düzeltmeleri → `bash surumle.sh "not"` (varsayılan)
- MINOR (1.**10**.0): yeni özellikler → `bash surumle.sh "not" minor`
- MAJOR (**2**.0.0): kırıcı değişiklikler → `bash surumle.sh "not" major`
- Elle sürüm: `bash surumle.sh "not" V1.9.2`

Not: 1.9.0 öncesi sürümler iki haneli şemayla (V1.0–V1.9) çıktı; SemVer'a geçişle üç haneye çevrildi (V1.0→1.0.0 … V1.9→1.9.0).






## [1.11.0] - 2026-09-28
- Proje & Teklif Yonetimi modulu: 7 asamali proje pipeline (tiklanabilir asama seridi, red nedeni diyalogu, kazan Referans alani, kapanmis proje arsiv modu), revizyonlu teklif uretimi (NX-YYYY-NNNN.Rn, indirim + not, eski revizyonlar arsivde), teklif PDF onizleme ve A4 Yazdir/PDF cikti, Projeler ekranina 7 sutunlu kanban + surukle-birak + acik/kapanmis filtresi + liste gecisi, urune bagli gorsel (project_images.product_id; 0=proje geneli) ve cekmecede Gorseller bolumu; KRITIK DÜZELTME: bos vals JSON [] dondugunden ilk alan doldurmasi kayboluyordu (object cast + istemci normalizasyonu), canli Toplam adet hesabi, teklif panelinin urun hazir olunca tazelenmesi; api: /api/projects/stage, /api/quotes GET/POST, projects stage/lost_*/won_* kolonlari + project_quotes tablosu + migration, 47 API testi
- Paket: Versiyon/Niateks_CRM_v1.11.0.zip
## [1.10.1] - 2026-09-28
- Giris ekrani ince ayar: kart seffaflastirildi ve bulaniklik azaltildi (fotograf net gorunur), yazı okunabilirliği için gölgeler güçlendirildi, sitede acilis animasyonu eklendi (kart kapı gibi acilir, motion azaltma tercihinde devre disi), captcha satiri yeniden dizayn edildi (esit yükseklikli kutu + yenile + giriş alanı)
- Paket: Versiyon/Niateks_CRM_v1.10.1.zip
## [1.10.0] - 2026-09-27
- Giris ekrani atolye fotografli yeni tasarıma gecti: tam ekran fotograf + koyu yari saydam kart, koza markasi, zeytin yesili buton; tum auth modlari (giris, kurulum, sifremi unuttum, sifre degisimi, sunucu yok) yeniden stillendi; captcha ve tum giris mantigi korundu; img/login-bg.jpg pakete eklendi (surumle.sh guncellendi)
- Paket: Versiyon/Niateks_CRM_v1.10.0.zip
## [1.9.2] - 2026-09-27
- Sürüm raporu otomasyonu: her sürümde surumle.sh artik tunc@niateks.com'a otomatik rapor maili gonderiyor (bu sürümde yapılanlar + paket ekleme/cikarma/degisim raporu + son sürümler; surum_mail.php, SMTP mail_config.php'den)
- Paket: Versiyon/Niateks_CRM_v1.9.2.zip
## [1.9.1] - 2026-09-27
- Sürüm şeması SemVer'a geçirildi (VMAJOR.MINOR.PATCH; sürümün tek kaynağı index.html APP_VERSION), CHANGELOG.md başlatıldı ve geçmiş tüm sürümler (V1.0–V1.9) taşındı, surumle.sh patch/minor/major desteği + otomatik changelog yazımı kazandı
- Sürüm şeması SemVer'a geçirildi (VMAJOR.MINOR.PATCH; sürümün tek kaynağı index.html APP_VERSION), CHANGELOG.md başlatıldı ve geçmiş tüm sürümler (V1.0–V1.9) taşındı, surumle.sh patch/minor/major desteği + otomatik changelog yazımı kazandı
- Paket: Versiyon/Niateks_CRM_v1.9.1.zip

## [1.9.0] - 2026-09-27
- Otomatik şifre + mail ile kullanıcı ekleme ("şifreyi otomatik üret ve e-postayla gönder")
- İlk girişte zorunlu şifre değiştirme ekranı (users.must_change)
- "Şifremi unuttum" akışı: e-posta → gönderildi + 20 sn geri sayım → tekrar gönder → girişe dön
- Login CAPTCHA: sunucu üretimli SVG güvenlik kodu, karışan karakterler yok (O/0/I/1/L)
- SMTP mail: mail.niateks.com:465 (TLS), web@niateks.com; kimlik bilgileri nx_data/mail_config.php'de (paketlere girmez), mail() yedeği
- Canlıda sıcak düzeltmeler: intro.js sunucuya yüklendi, otomatik şifre formunun submit engeli giderildi
- Paket: Versiyon/Niateks_CRM_v1.9.zip

## [1.8.0] - 2026-09-27
- Koza kimliği: açılış animasyonu (koza zıplar, iplik "Niateks" krem "House" turuncu çizer), koza logosu + Fraunces fontu, kozahouse paleti (krem/zeytin/terra), saatli selamlama
- Logout GET→POST düzeltmesi; ürün modülü arayüzü
- Paket: Versiyon/Niateks_CRM_v1.8.zip

## [1.7.0] - 2026-09-27
- Ürün modülü (dinamik ürün kartları): proje içi Ürünler paneli, drawer, kopyala/sil, teklife hazır akışı
- Türkçe yazım düzeni çekirdeği; DB geçici hata kurtarması; IP gizliliği (işlem kayıtları)
- Paket: Versiyon/Niateks_CRM_v1.7.zip

## [1.6.0] - 2026-09-27
- Yöneticilerin özel alanı YÖNET grubu altında toplandı: Kullanıcılar, Kayıtlar, Giriş kayıtları ayrı menü öğesi
- Paket: Versiyon/Niateks_CRM_v1.6.zip

## [1.5.0] - 2026-09-27
- Giriş kayıtları: cihaz bilgisi, IP (yalnız kurucu hesapta görünür), Kayıtlar altına "İşlem/Giriş" sekmeleri
- Paket: Versiyon/Niateks_CRM_v1.5.zip

## [1.4.0] - 2026-09-27
- Proje görselleri: sürükle-bırak yükleme, galeri kutucukları, lightbox (zoom +/-, ok gezinme, Esc), güvenli sunum, silme; proje silinince görseller de temizlenir
- Paket: Versiyon/Niateks_CRM_v1.4.zip

## [1.3.0] - 2026-09-26
- Audit log: alan bazında eski→yeni değişim izleme, Kayıtlar ekranında kullanıcı/tip/serbest metin filtreleri, müşteri kartından log'a git
- Paket: Versiyon/Niateks_CRM_v1.3.zip

## [1.2.0] - 2026-09-26
- Firma yetkilileri çok kişi: isim+unvan+e-posta+telefon, birincil seçimi; eski tek kişi otomatik taşınır
- Paket: Versiyon/Niateks_CRM_v1.2.zip

## [1.1.0] - 2026-09-26
- Adres bölümü genişletildi: Semt/İlçe, Posta Kodu, Web sitesi, Vergi/VKN alanları; detay kartında gösterim
- Paket: Versiyon/Niateks_CRM_v1.1.zip

## [1.0.0] - 2026-09-26
- Sürüm numarası arayüze eklendi (sidebar + giriş ekranı); sürüm şeması yenilendi
- Paket: Versiyon/Niateks_CRM_v1.0.zip
