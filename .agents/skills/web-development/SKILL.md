---
name: web-development
description: Full-stack web geliştirme standartlarını uygular; mimari, API ve veri modelleme, güvenlik ve test kurallarıyla üretim kalitesinde kod yazar ve mevcut kodu inceler. Use when writing, reviewing, or debugging web application code, APIs, databases, or when the user mentions kod, geliştirme, backend, frontend, API, veritabanı, refactor, test, bug, or deployment.
metadata:
  version: "1.0"
---

# Web Geliştirme Standartları

## Rol
Senior bir full-stack geliştirici gibi çalış: önce mevcut projenin **kendi konvansiyonlarını oku ve onlara uy**, kararlarında basitliği koru, doğrulanmamış kod teslim etme.

## Başlamadan önce
1. Projeyi tara: paket yöneticisi, framework sürümü, klasör yapısı, lint/format ayarları, mevcut test altyapısı. Eksikse proje konvansiyonuna uy, proje boşsa aşağıdaki varsayılanları kullan.
2. Talebi netleştir: hangi davranış, hangi kısıtlar, tanımına göre "bitti" ne demek?
3. Büyük işleri parçala; çok adımlı işlerde todo listesi tut.

## Mimari ilkeler
- **Proje konvansiyonu > bu skill.** Çelişki olursa projedeki düzen kazanır; farkı kullanıcıya not et.
- Katmanlar: route/controller → service/iş mantığı → veri erişimi. İş mantığını framework dosyalarına gömme.
- Tekrar 3 olmadan soyutlama yapma; YAGNI — bugünün ihtiyacını en basit çözümle karşıla.
- Bağımlılıklar: yeni paket eklemeden önce projede zaten var mı bak; ekliyorsan gerekçesini söyle.
- Konfigürasyon ortam değişkenlerinden; sırlar asla koda girmez.

## API kuralları
- REST: çoğul kaynak isimleri (`/users/{id}`), fiil yok. Durum kodları doğru: 400 doğrulama, 401 kimliksiz, 403 yetkisiz, 404 bulunamadı, 409 çakışma.
- Tüm girdileri doğrula (schema tabanlı — projede zaten varsa onu kullan); hata yanıtları tek format: `{ "error": { "code", "message", "details" } }`.
- Liste uçları paginated; N+1 sorgu üretme.
- Kırılgan/tekrar eden işlemler (migration, deploy) için: tam komutu belgele, sırayı değiştirme.

## Veri kuralları
- Migration ile ilerle; asla geriye dönüş gerektiren kırıcı değişiklik yapma (önce genişlet, sonra daralt).
- Tutarlı adlandırma: tablo çoğul snake_case, alanlar snake_case, timestamp'ler `created_at`/`updated_at`.
- Kullanıcı üretimi veri modellerinde `deleted_at` soft delete düşünülür.
- Parasal değerler float değil decimal; kimlik/tutar gibi alanlarda veritabanı kısıtı (unique, not null, FK) tanımla.

## Frontend kuralları
- Bileşenler tek sorumluluklu; 150 satırı geçen bileşeni böl.
- Sunucu verisi için projedeki mevcut pattern (React Query/SWR/store) neyse onu kullan, yeni pattern icat etme.
- Form durumları, yükleniyor/hata/boş durumları her görünümde ele al.
- Erişilebilirlik: semantik HTML, form alanlarına label, tıklanabilir alanlar ≥ 44px, odak görünürlüğü.

## Güvenlik minimumları (istisnasız)
- SQL her zaman parametreli; string birleştirme ile sorgu yok.
- XSS: kullanıcı verisi HTML'e ham geçmez; framework'ün kaçış mekanizması dışına çıkma.
- Kimlik denetimi her korumalı uçta sunucu tarafında yapılır; client-side kontrol güvenlik sayılmaz.
- Secret'lar env'de; loglara token/parola/kişisel veri yazma.

## Bitirme döngüsü (her değişiklikte, atlanamaz)
1. Değişen dosyaları listele, her dosyanın neden değiştiğini 1 cümleyle söyle.
2. Tip kontrolü çalıştır (ör. `tsc --noEmit`) — hatasız geçmeli.
3. Lint çalıştır (proje varsa) — uyarıları gider.
4. İlgili testleri çalıştır; kritik akışlar için test yoksa min 1 test ekle.
5. Döngü başarısızsa düzelt ve 2-4'ü tekrar çalıştır; hepsi geçmeden "bitti" deme.

## Yapılmayacaklar
- Çalışan davranışı sessizce değiştirme; kırıcı değişiklik öncesi kullanıcıya sor.
- İstek dışı büyük refactor açma; fark edersen öneri olarak not düş.
- `console.log`/debug kodu ve yorumlanmış kod bloğu bırakma.
- Doğrulanmamış ("çalışması gerek") kod teslim etme.

## Teslim öncesi kontrol listesi
- [ ] Proje konvansiyonlarına uyuldu mu?
- [ ] Yeni bağımlılık gerçekten gerekli mi, gerekçesi söylendi mi?
- [ ] Girdi doğrulama ve hata formatı standart mı?
- [ ] Secret/kişisel veri koda veya loga sızmıyor mu?
- [ ] Tip kontrolü + lint + test çalıştırıldı ve geçti mi?
