<?php
session_start();
require_once 'baglan.php';

$hata = "";
$mesaj = "";

// 1. KAYIT OLMA MEKANİZMASI
if (isset($_POST['kayit_ol'])) {
    $kullanici_adi = trim($_POST['kullanici_adi']);
    $sifre = $_POST['sifre'];
    $avatar = $_POST['avatar'];

    if (!empty($kullanici_adi) && !empty($sifre)) {
        // Şifreyi veri tabanına ham metin olarak değil, geri döndürülemez şekilde hash'leyerek kaydediyoruz (Tam Gizlilik)
        $guvenli_sifre = password_hash($sifre, PASSWORD_BCRYPT);
        
        try {
            $sorgu = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, avatar) VALUES (?, ?, ?)");
            $sorgu->execute([$kullanici_adi, $guvenli_sifre, $avatar]);
            $mesaj = "Profiliniz başarıyla mühürlendi! Şimdi giriş yapabilirsiniz.";
        } catch (PDOException $e) {
            $hata = "Bu kullanıcı adı efsanelerde zaten mevcut, başka bir rumuz seçin.";
        }
    } else {
        $hata = "Alanları boş bırakamazsınız.";
    }
}

// 2. GİRİŞ YAPMA MEKANİZMASI
if (isset($_POST['giris_yap'])) {
    $kullanici_adi = trim($_POST['kullanici_adi']);
    $sifre = $_POST['sifre'];

    if (!empty($kullanici_adi) && !empty($sifre)) {
        // Özel Durum: Veri tabanındaki ham admin hesabı kontrolü
        if ($kullanici_adi === 'admin' && $sifre === '123456') {
            $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
            $sorgu->execute([$kullanici_adi]);
            $user = $sorgu->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['kullanici_adi'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['user_avatar'] = $user['avatar'];
            
            header("Location: panel.php");
            exit;
        }

        // Normal Kullanıcı Giriş Kontrolü
        $sorgu = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
        $sorgu->execute([$kullanici_adi]);
        $user = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($sifre, $user['sifre'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['kullanici_adi'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['user_avatar'] = $user['avatar'];
            
            header("Location: panel.php");
            exit;
        } else {
            $hata = "Kullanıcı adı veya şifre yanlış.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Triskel: Döngüye Giriş</title>
    <style>
        body { background-color: #161412; color: #d9c5b2; font-family: 'Georgia', serif; display: flex; min-height: 100vh; margin: 0; align-items: center; justify-content: center; }
        .auth-container { background: #1f1c18; border: 1px solid #2e2924; border-radius: 16px; padding: 40px; width: 360px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; }
        h1 { color: #c9a074; margin: 0 0 10px 0; font-weight: normal; letter-spacing: 2px; }
        .tab-menu { display: flex; gap: 10px; margin-bottom: 25px; justify-content: center; }
        .tab-btn { background: none; border: none; color: #d9c5b2; opacity: 0.5; cursor: pointer; font-size: 14px; font-family: sans-serif; padding-bottom: 4px; }
        .tab-btn.active { opacity: 1; color: #c9a074; border-bottom: 2px solid #c9a074; }
        .form-section { display: none; flex-direction: column; gap: 15px; }
        .form-section.active { display: flex; }
        input, select { background: #161412; border: 1px solid #2e2924; color: #d9c5b2; padding: 10px; border-radius: 6px; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #c9a074; }
        .btn-submit { background: #2e2924; color: #d9c5b2; border: 1px solid #2e2924; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .btn-submit:hover { background: #3d3730; border-color: #c9a074; }
        .alert { font-size: 13px; font-style: italic; margin-bottom: 15px; padding: 8px; border-radius: 4px; }
        .alert-danger { background: #3a1e1a; color: #b56951; }
        .alert-success { background: #222b1e; color: #8f9779; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Triskel</h1>
        <p style="font-style: italic; opacity: 0.6; font-size: 13px; margin-top: 0; margin-bottom: 20px;">Döngüsel Yaşam Kitaplığı</p>
        <?php if(!empty($hata)): ?> <div class="alert alert-danger"><?= $hata ?></div> <?php endif; ?>
        <?php if(!empty($mesaj)): ?> <div class="alert alert-success"><?= $mesaj ?></div> <?php endif; ?>
        <div class="tab-menu">
            <button class="tab-btn active" onclick="switchForm('login', this)">Giriş Yap</button>
            <button class="tab-btn" onclick="switchForm('register', this)">Mühürlen (Kayıt)</button>
        </div>
        <form method="POST" id="form-login" class="form-section active">
            <input type="text" name="kullanici_adi" placeholder="Kullanıcı Adı" required>
            <input type="password" name="sifre" placeholder="Şifre" required>
            <button type="submit" name="giris_yap" class="btn-submit">Döngüyü Başlat</button>
        </form>
        <form method="POST" id="form-register" class="form-section">
            <input type="text" name="kullanici_adi" placeholder="Yeni Kullanıcı Adı" required>
            <input type="password" name="sifre" placeholder="Güçlü Bir Şifre" required>
            <select name="avatar">
                <option value="🌱">🌱 Toprak Elementi</option>
                <option value="🌀">🌀 Hava Elementi</option>
                <option value="🌊">🌊 Su Elementi</option>
                <option value="🔥">🔥 Ateş Elementi</option>
                <option value="☀️">☀️ Güneş Ritmi</option>
            </select>
            <button type="submit" name="kayit_ol" class="btn-submit">Profili Mühürle</button>
        </form>
    </div>
    <script>
        function switchForm(type, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('form-' + type).classList.add('active');
        }
    </script>
</body>
</html>