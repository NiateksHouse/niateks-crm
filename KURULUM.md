<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proje Bazlı CRM İş Akışı Simülasyonu</title>
    <style>
        :root {
            --primary: #2563eb;
            --success: #16a34a;
            --gray-100: #f3f4f6;
            --gray-300: #d1d5db;
            --gray-700: #374151;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
            color: var(--gray-700);
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        /* Adım Göstergeleri (Wizard Progress) */
        .steps-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .steps-container::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gray-300);
            z-index: 1;
        }
        .step {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 10px;
            text-align: center;
            flex: 1;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-300);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .step.active .step-number {
            background: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
        }
        .step.completed .step-number {
            background: var(--success);
        }
        .step-title {
            font-size: 13px;
            font-weight: 600;
        }

        /* Ekranlar */
        .crm-screen {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .crm-screen.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Elemanları */
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #15803d; }

        /* İki Bölümlü Son Ekran Düzeni */
        .final-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .summary-card {
            background: var(--gray-100);
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid var(--primary);
        }

        /* Kronolojik Zaman Tüneli (Timeline) */
        .timeline {
            position: relative;
            padding-left: 30px;
            border-left: 2px solid var(--gray-300);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -37px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
        }
        .timeline-item.email::before { background: #f59e0b; }
        .timeline-item.call::before { background: #06b6d4; }
        .timeline-item.system::before { background: var(--success); }
        
        .timeline-date {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }
        .timeline-content {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--gray-300);
            margin-top: 5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .timeline-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Adım Göstergeleri -->
    <div class="steps-container">
        <div class="step active" id="st-1">
            <div class="step-number">1</div>
            <div class="step-title">Firma Tanımlama</div>
        </div>
        <div class="step" id="st-2">
            <div class="step-number">2</div>
            <div class="step-title">Lead & İlk Temas</div>
        </div>
        <div class="step" id="st-3">
            <div class="step-number">3</div>
            <div class="step-title">Proje & Ürün Ağacı</div>
        </div>
        <div class="step" id="st-4">
            <div class="step-number">4</div>
            <div class="step-title">Teklif Oluşturma</div>
        </div>
        <div class="step" id="st-5">
            <div class="step-number">5</div>
            <div class="step-title">Kronolojik Takip</div>
        </div>
    </div>

    <!-- EKRAN 1: FİRMA TANIMLAMA -->
    <div class="crm-screen active" id="screen-1">
        <h2>🏢 Adım 1: Firma Tanımlama</h2>
        <p>Yeni tanışılan firmanın temel kurumsal kart bilgilerini giriniz.</p>
        <div class="form-group">
            <label>Firma Adı</label>
            <input type="text" id="inp-firma" value="ABC Teknoloji A.Ş.">
        </div>
        <div class="form-group">
            <label>Yetkili Kişi (Ad Soyad)</label>
            <input type="text" id="inp-yetkili" value="Ahmet Yılmaz">
        </div>
        <div class="form-group">
            <label>Sektör</label>
            <select>
                <option>Yazılım / Bilişim</option>
                <option>İmalat / Sanayi</option>
                <option>Sağlık</option>
            </select>
        </div>
        <button class="btn" onclick="nextStep(2)">Kaydet ve Devam Et ➔</button>
    </div>

    <!-- EKRAN 2: LEAD VE İLK TEMAS -->
    <div class="crm-screen" id="screen-2">
        <h2>🎯 Adım 2: Lead Yönetimi & İlk Temas</h2>
        <p>Müşteri adayı ile yapılan ilk etkileşimleri ve toplantı notlarını bu ekrandan loglayın.</p>
        <div class="form-group">
            <label>İlk İletişim Türü</label>
            <select id="inp-temas-turu">
                <option>Fuar Tanışma Maili Atıldı</option>
                <option>Keşif Telefon Araması Yapıldı</option>
            </select>
        </div>
        <div class="form-group">
            <label>Görüşme / E-posta İçeriği Notu</label>
            <textarea id="inp-gorusme-notu" rows="4">Fuarda tanışılan Ahmet Bey ile ilk keşif mailleşmesi tamamlandı. Şirketlerinin büyüdüğünü ve yeni bir otomasyon projesine ihtiyaç duyduklarını belirttiler. Ürün grubu detayları talep edildi.</textarea>
        </div>
        <button class="btn" onclick="nextStep(3)">Proje Aç / Dönüştür ➔</button>
    </div>

    <!-- EKRAN 3: PROJE VE ÜRÜN AĞACI -->
    <div class="crm-screen" id="screen-3">
        <h2>⚙️ Adım 3: Proje Yapılandırma & Ürün Ağacı</h2>
        <p>Lead onaylandı ve projeye dönüştü. Müşterinin spesifik ihtiyaçlarına göre ürün konfigürasyonunu seçin.</p>
        <div class="form-group">
            <label>Proje Adı</label>
            <input type="text" id="inp-proje-adi" value="ABC Teknoloji - Altyapı Yenileme Projesi">
        </div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid var(--gray-300); margin-bottom: 20px;">
            <h4>🌲 Ürün Ağacı (BOM) Spesifikasyonları</h4>
            <div class="form-group">
                <label>Ana Modül / Donanım</label>
                <select id="inp-urun">
                    <option>Endüstriyel Veri Sunucusu X100</option>
                    <option>Bulut Entegrasyon Lisans Paketi</option>
                </select>
            </div>
            <div class="form-group">
                <label>Özellik / Bileşen Seçimi</label>
                <input type="checkbox" checked disabled> PLC Kontrol Ünitesi (Siemens Entegre)<br>
                <input type="checkbox" checked id="inp-ozellik"> Paslanmaz Çelik Şasi Tasarımı (+%10 Opsiyon)<br>
                <input type="checkbox" checked> 1 Yıl Yerinde Periyodik Bakım Garantisi
            </div>
        </div>
        <button class="btn" onclick="nextStep(4)">Teklif Oluştur ➔</button>
    </div>

    <!-- EKRAN 4: TEKLİF OLUŞTURMA -->
    <div class="crm-screen" id="screen-4">
        <h2>💰 Adım 4: Fiyat Teklifi (Quotation)</h2>
        <p>Sistem, ürün ağacında girdiğiniz özelliklere göre otomatik teklif matrisini hazırladı.</p>
        <table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background: var(--gray-100); text-align: left;">
                    <th style="padding:10px;">Kalem/Özellik</th>
                    <th style="padding:10px;">Miktar</th>
