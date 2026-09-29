# Alpha.18 test kurulumu — yayın öncesi kontrol

Bu paket ilk firma kartı altyapısıdır; tam CRM veya onaylı görsel demo değildir. PHP 8.4 ve MySQL 8 gerekir. Canlı ortamda kullanım onayı verilmiş sayılmaz.

1. Test alanının ve niatekscom_koza_test veritabanının ayrı olduğunu doğrula. Mevcut dosyaları/DB durumunu sunucuda yedekle. Yerel büyük yedek indirme.
2. Paketi /home/niatekscom altında ayrı bir sürüm klasörüne çıkar. Yalnız public klasörünü test alanının web kökü yap. .env, vendor, storage ve database internetten erişilemez olmalı. Dosya kökünü doğrulamadan trafik yönlendirme.
3. .env.example dosyasından .env oluştur; DB parolası ve uygulama anahtarını yalnız sunucuda güvenli girişle ayarla. APP_ENV=staging, APP_DEBUG=false, HTTPS APP_URL, SESSION_SECURE_COOKIE=true, test ortamına özel oturum adı. Mail gönderimi kapalı kalır.
4. PHP çalıştırıcısını ve gerekli uzantıları sunucuda doğrula. Terminal olmayan hostingde sağlayıcının desteklediği güvenli komut çalıştırma yolu netleşmeden migration/provisioning yapma. Web üzerinden açık kurulum veya komut çalıştırma dosyası oluşturma.
5. Yetkili komut ortamında sırayla `php artisan key:generate --force` (yalnız ilk kurulumda), `php artisan migrate --force`, `php artisan config:cache`, `php artisan view:cache` çalıştır. Var olan anahtarı sonraki dağıtımlarda değiştirme. migrate:fresh hostingde çalıştırılmaz.
6. Etkileşimli ve yetkili bir komut ortamında `php artisan koza:create-user KULLANICI_ADI EPOSTA --name="AD SOYAD"` komutuyla yeni kullanıcı oluşturulur. Yönetici için `--admin`, ayrıca genel finans için `--all-finance` açıkça seçilir. Parola gizli iki kez sorulur; argüman olarak verilmez. En az 14 karakter, büyük/küçük harf, sayı, simge; en çok 72 bayt. Var olan hesap veya parola değiştirilmez. Nigar genel finans yetkili yönetici; Tunç yönetici ancak genel finans yetkisiz olacak şekilde planlanmıştır. Gerçek hesaplar bu paket tarafından otomatik oluşturulmaz.
7. Mevcut cPanel'de Cron var, etkileşimli Terminal/SSH yok. Bu komut Cron üzerinden çalıştırılamaz; otomatik girişsiz kullanım bilinçli olarak reddedilir. Sağlayıcının desteklediği etkileşimli kurulum veya ayrıca test edilmiş tek kullanımlık hesap davet akışı gerekli. Açık web kurulum dosyası veya parolayı komut satırına yazma çözümü kullanılmaz.
8. storage ve bootstrap/cache için yalnız gereken yazma iznini ver; 777 kullanma. HTTPS, güvenli çerezler, dizin listelemenin kapalı olması, özel dosyalara erişilememesi ve giriş/çıkış/firma işlemlerini test alanında doğrula.
9. Geri dönüşte önce önceki uygulama sürümüne dön. Migration down ile bilgi geçmişini silme; veritabanı geri yüklemesi gerekiyorsa yeni verileri koruma planıyla ayrıca değerlendir.

Bu liste işletim hazırlığıdır, kullanıcı kılavuzunun yeni sürümü değildir. Kılavuz v0.3.0 değiştirilmedi.
