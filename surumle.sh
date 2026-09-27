#!/bin/bash
# Niateks House CRM — Versiyonlu dağıtım paketi oluşturucu (SemVer)
# Sürüm şeması: VMAJOR.MINOR.PATCH  (ör. V1.9.0 → V1.9.1 → V1.10.0 → V2.0.0)
#   PATCH (V1.9.1) : hata düzeltmeleri          → bash surumle.sh "not"            (varsayılan)
#   MINOR (V1.10.0): yeni özellikler            → bash surumle.sh "not" minor
#   MAJOR (V2.0.0) : kırıcı değişiklikler       → bash surumle.sh "not" major
#   Elle sürüm                                  → bash surumle.sh "not" V1.9.2
# Sürümün tek kaynağı: index.html içindeki APP_VERSION. CHANGELOG.md otomatik güncellenir.
# Not: 1.9.0 öncesi paketler iki haneliydi (V1.0–V1.9); script bunları üç haneye (x.y.0) normalize eder.
set -e
cd "$(dirname "$0")"

VERSIYON_KLASORU="Versiyon"
CHANGELOG="CHANGELOG.md"
NOT="$1"
TIP="$2"
[ -z "$NOT" ] && { echo 'Kullanim: bash surumle.sh "degisiklik notu" [patch|minor|major|VX.Y.Z]'; exit 1; }

# ---- mevcut surumu oku (tek kaynak: index.html APP_VERSION) ----
CUR=$(grep -oE "const APP_VERSION='V[0-9.]+'" index.html | grep -oE '[0-9.]+' | sed 's/\.$//')
[ -z "$CUR" ] && { echo 'HATA: index.html icinde APP_VERSION bulunamadi.'; exit 1; }
# iki haneli eski formatı (x.y) uç haneye tamamla
PARTS=$(echo "$CUR" | awk -F. '{print NF}')
[ "$PARTS" -eq 2 ] && CUR="$CUR.0"
MAJOR=$(echo "$CUR" | cut -d. -f1); MINOR=$(echo "$CUR" | cut -d. -f2); PATCH=$(echo "$CUR" | cut -d. -f3)

# ---- yeni surumu belirle ----
case "$TIP" in
  ""|patch) NEXT="$MAJOR.$MINOR.$((PATCH+1))" ;;
  minor)    NEXT="$MAJOR.$((MINOR+1)).0" ;;
  major)    NEXT="$((MAJOR+1)).0.0" ;;
  V*|v*)    NEXT="$(echo "$TIP" | sed 's/^[vV]//')"
            echo "$NEXT" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$' || { echo "HATA: surum formati VX.Y.Z olmali (ornek: V1.9.2)"; exit 1; } ;;
  *)        echo "HATA: gecersiz tip: $TIP (patch|minor|major|VX.Y.Z)"; exit 1 ;;
esac

STAMP=$(date +%Y-%m-%d_%H%M)
TARIH=$(date +%Y-%m-%d)
OUT="$VERSIYON_KLASORU/Niateks_CRM_v${NEXT}.zip"
WORK="/tmp/nx_deploy_$$"
rm -rf "$WORK" && mkdir -p "$WORK/nx_data"

# ---- index.html icindeki APP_VERSION'i guncelle ----
sed -i "s/const APP_VERSION='[^']*'/const APP_VERSION='V${NEXT}'/" index.html
cp index.html api.php norm.php intro.js .htaccess KURULUM.md "$WORK/"
[ -f "$CHANGELOG" ] && cp "$CHANGELOG" "$WORK/"
printf 'NIA-2026-KX94-MT37\n' > "$WORK/nx_data/setup_code.txt"
printf 'Niateks House CRM\nSurum: V%s\nTarih: %s\nDegisiklik: %s\nIcerik: index.html, api.php, norm.php, intro.js, .htaccess, KURULUM.md, CHANGELOG.md, VERSION.txt, nx_data/setup_code.txt\nGeri donus: onceki Niateks_CRM_v*.zip dosyasini acip sunucuya yukleyin.\n' "$NEXT" "$STAMP" "$NOT" > "$WORK/VERSION.txt"

# ---- CHANGELOG.md'ye yeni surum bolumunu ekle (baslik/blog kismini en ustte tutar) ----
# Ayni surum tekrar paketlenirse (yeniden yayin) changelog'a ikinci kez yazmaz.
if [ -f "$CHANGELOG" ]; then
  if ! grep -q "^## \[$NEXT\] - " "$CHANGELOG"; then
    LINE=$(grep -n '^## \[' "$CHANGELOG" | head -1 | cut -d: -f1)
    TMP="/tmp/nx_changelog_$$"
    if [ -n "$LINE" ]; then
      { head -n $((LINE-1)) "$CHANGELOG"
        printf '\n## [%s] - %s\n- %s\n- Paket: %s\n' "$NEXT" "$TARIH" "$NOT" "$OUT"
        tail -n +$LINE "$CHANGELOG"; } > "$TMP"
    else
      { cat "$CHANGELOG"
        printf '\n## [%s] - %s\n- %s\n- Paket: %s\n' "$NEXT" "$TARIH" "$NOT" "$OUT"; } > "$TMP"
    fi
    mv "$TMP" "$CHANGELOG"
  fi
else
  printf '# Değişiklik Günlüğü\n\n## [%s] - %s\n- %s\n- Paket: %s\n' "$NEXT" "$TARIH" "$NOT" "$OUT" > "$CHANGELOG"
fi

cd "$WORK"
powershell.exe -NoProfile -Command "Compress-Archive -Path 'C:\\Users\\TS\\AppData\\Local\\Temp\\nx_deploy_$$\\*' -DestinationPath 'C:\\Users\\TS\\Desktop\\Projects\\Niateks_CRM\\${OUT}' -Force"
cd - >/dev/null && rm -rf "$WORK"

# ---- otomatik surum raporu maili (tunc@niateks.com): bu surumde yapilanlar + paket ekleme/cikarma/degisim raporu ----
# surum_mail.php pakete girmez (lokal arac); mail gonderilemezse paketleme etkilenmez.
if [ -f surum_mail.php ]; then
  # onceki paketi bul (ayni surumun yeniden yayini ise mail diff'i yine olusturur)
  PREVZIP=$(ls -t "$VERSIYON_KLASORU"/Niateks_CRM_v*.zip 2>/dev/null | grep -v "v${NEXT}.zip" | head -1)
  NX_RELEASE_VER="V$NEXT" NX_RELEASE_PREV="V$CUR" NX_RELEASE_ZIP="$PWD/$OUT" \
  NX_RELEASE_PREVZIP="${PREVZIP:+$PWD/$PREVZIP}" NX_RELEASE_NOTE="$NOT" \
  "C:/xampp/php/php.exe" surum_mail.php || echo "(surum maili gonderilemedi - paket etkilenmedi)"
fi

echo "== Olusturuldu: $OUT (surum V$CUR -> V$NEXT, arayuzde APP_VERSION=V$NEXT)"
echo "== CHANGELOG.md guncellendi."
echo "== HATIRLATMA: Mail gonderimi icin sunucuda koza/nx_data/mail_config.php bulunmali (SMTP kimlik bilgileri - GIZLI, pakete girmez)."
echo "   Dosya yoksa sistem PHP mail() yedegini kullanir. Ayrinti: api.php basindaki 'posta hesabi' yorumu."
echo "== Versiyon klasoru icerigi:"
ls -la "$VERSIYON_KLASORU"
