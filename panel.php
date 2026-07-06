<?php
session_start();
require_once 'baglan.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$user_avatar = $_SESSION['user_avatar'];

// ---- GÜVENLİK FİLTRESİ ----
function guvenli_yaz($metin) {
    return htmlspecialchars($metin, ENT_QUOTES, 'UTF-8');
}

// ---- ADMİN ÖZEL PANELİ ----
if ($user_role === 'admin') {
    $toplam_user_sorgu = $db->query("SELECT COUNT(*) as toplam FROM kullanicilar WHERE rol = 'user'");
    $toplam_user = $toplam_user_sorgu->fetch(PDO::FETCH_ASSOC)['toplam'];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <title>Triskel: Yönetici Paneli</title>
        <style>
            body { background-color: #161412; color: #d9c5b2; font-family: 'Georgia', serif; display: flex; min-height: 100vh; margin:0; justify-content: center; align-items: center; }
            .admin-card { background: #1f1c18; border: 1px solid #2e2924; border-radius: 16px; padding: 40px; text-align: center; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            h1 { color: #c9a074; font-weight: normal; }
            .stat-box { font-size: 48px; color: #8f9779; font-family: sans-serif; margin: 20px 0; }
            .btn-logout { background: #b56951; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;}
        </style>
    </head>
    <body>
        <div class="admin-card">
            <h1>Mistik Admin Gözü ☀️</h1>
            <p>Sistemde Kayıtlı Toplam Kullanıcı Sayısı:</p>
            <div class="stat-box"><?= $toplam_user ?></div>
            <a href="cikis.php" class="btn-logout">Oturumu Kapat</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---- KULLANICI PANELİ ----
$bugun_str = date('Y-m-d');
$secilen_tarih = isset($_GET['tarih']) ? $_GET['tarih'] : $bugun_str;

$aylik_yil = isset($_GET['ay_yil']) ? (int)$_GET['ay_yil'] : (int)date('Y', strtotime($secilen_tarih));
$aylik_ay  = isset($_GET['ay_ay']) ? (int)$_GET['ay_ay'] : (int)date('m', strtotime($secilen_tarih));

// Sarmal İsimleri
$u_sorgu = $db->prepare("SELECT dal0_isim, dal1_isim, dal2_isim FROM kullanicilar WHERE id = ?");
$u_sorgu->execute([$user_id]);
$kullanici_veri = $u_sorgu->fetch(PDO::FETCH_ASSOC);

$dal_isimleri = [
    $kullanici_veri['dal0_isim'] ?? "Akademik Çalışmalar",
    $kullanici_veri['dal1_isim'] ?? "Kişisel Gelişim",
    $kullanici_veri['dal2_isim'] ?? "Günlük Rutinler"
];

if (isset($_POST['guncelle_dal_isim'])) {
    $idx = (int)$_POST['dal_idx'];
    $yeni_isim = trim($_POST['yeni_isim']);
    if (!empty($yeni_isim)) {
        if ($idx === 0) $sql = "UPDATE kullanicilar SET dal0_isim = ? WHERE id = ?";
        if ($idx === 1) $sql = "UPDATE kullanicilar SET dal1_isim = ? WHERE id = ?";
        if ($idx === 2) $sql = "UPDATE kullanicilar SET dal2_isim = ? WHERE id = ?";
        $up = $db->prepare($sql);
        $up->execute([$yeni_isim, $user_id]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// Görev Ekleme
if (isset($_POST['gorev_ekle'])) {
    $metin = trim($_POST['gorev_metni']);
    $tur = $_POST['tur'];
    $dal = (int)$_POST['dal_index'];
    if (!empty($metin)) {
        $sorgu = $db->prepare("INSERT INTO gorevler (kullanici_id, gorev_metni, tur, dal_index, tarih_str) VALUES (?, ?, ?, ?, ?)");
        $sorgu->execute([$user_id, $metin, $tur, $dal, $secilen_tarih]);
    }
    header("Location: panel.php?tarih=" . $secilen_tarih);
    exit;
}

// Görev Silme
if (isset($_GET['sil_gorev'])) {
    $g_id = (int)$_GET['sil_gorev'];
    $sorgu = $db->prepare("DELETE FROM gorevler WHERE id = ? AND kullanici_id = ?");
    $sorgu->execute([$g_id, $user_id]);
    header("Location: panel.php?tarih=" . $secilen_tarih);
    exit;
}

// Görev Tikleme
if (isset($_GET['toggle_gorev'])) {
    $g_id = (int)$_GET['toggle_gorev'];
    $sorgu = $db->prepare("SELECT * FROM gorevler WHERE id = ? AND kullanici_id = ?");
    $sorgu->execute([$g_id, $user_id]);
    $gorev = $sorgu->fetch(PDO::FETCH_ASSOC);
    
    if ($gorev) {
        $tamamlananlar = $gorev['tamamlandi_tarihleri'] ? explode(',', $gorev['tamamlandi_tarihleri']) : [];
        if (in_array($secilen_tarih, $tamamlananlar)) {
            $tamamlananlar = array_diff($tamamlananlar, [$secilen_tarih]);
        } else {
            $tamamlananlar[] = $secilen_tarih;
        }
        $yeni_str = implode(',', $tamamlananlar);
        $u_sorgu = $db->prepare("UPDATE gorevler SET tamamlandi_tarihleri = ? WHERE id = ?");
        $u_sorgu->execute([$yeni_str, $g_id]);
    }
    header("Location: panel.php?tarih=" . $secilen_tarih);
    exit;
}

// Kültür Ekleme
if (isset($_POST['kultur_ekle'])) {
    $eser = trim($_POST['eser_adi']);
    $k_tur = $_POST['kultur_turu'];
    $puan = (int)$_POST['puan'];
    $yorum = trim($_POST['yorum']);
    $k_tarih = !empty($_POST['kultur_tarihi']) ? $_POST['kultur_tarihi'] : $bugun_str;
    
    if (!empty($eser)) {
        $sorgu = $db->prepare("INSERT INTO kultur_havuzu (kullanici_id, eser_adi, tur, puan, yorum, tarih) VALUES (?, ?, ?, ?, ?, ?)");
        $sorgu->execute([$user_id, $eser, $k_tur, $puan, $yorum, $k_tarih]);
    }
    header("Location: panel.php?tarih=" . $secilen_tarih . "&view=culture");
    exit;
}

// Kültür Silme
if (isset($_GET['sil_kultur'])) {
    $k_id = (int)$_GET['sil_kultur'];
    $sorgu = $db->prepare("DELETE FROM kultur_havuzu WHERE id = ? AND kullanici_id = ?");
    $sorgu->execute([$k_id, $user_id]);
    header("Location: panel.php?tarih=" . $secilen_tarih . "&view=culture");
    exit;
}

// Tüm Görevleri Çekme
$g_sorgu = $db->prepare("SELECT * FROM gorevler WHERE kullanici_id = ?");
$g_sorgu->execute([$user_id]);
$tum_gorevler = $g_sorgu->fetchAll(PDO::FETCH_ASSOC);

$dallar = [[], [], []];
foreach ($tum_gorevler as $g) {
    $goster = false;
    if ($g['tur'] === 'daily' && $g['tarih_str'] === $secilen_tarih) $goster = true;
    elseif ($g['tur'] === 'routine' && $g['tarih_str'] <= $secilen_tarih) $goster = true;
    
    if ($goster) {
        $t_list = $g['tamamlandi_tarihleri'] ? explode(',', $g['tamamlandi_tarihleri']) : [];
        $g['is_done'] = in_array($secilen_tarih, $t_list);
        $dallar[$g['dal_index']][] = $g;
    }
}

$k_sorgu = $db->prepare("SELECT * FROM kultur_havuzu WHERE kullanici_id = ? ORDER BY tarih DESC, id DESC");
$k_sorgu->execute([$user_id]);
$kultur_havuzu = $k_sorgu->fetchAll(PDO::FETCH_ASSOC);

// ---- AY SARMAL VERİSİ ----
$gun_sayisi = cal_days_in_month(CAL_GREGORIAN, $aylik_ay, $aylik_yil);

$aylik_triskel_veri = [];
for ($d = 1; $d <= $gun_sayisi; $d++) {
    $t_str = sprintf("%04d-%02d-%02d", $aylik_yil, $aylik_ay, $d);
    
    for ($dal = 0; $dal < 3; $dal++) {
        $g_toplam = 0; $g_ok = 0;
        foreach ($tum_gorevler as $g) {
            if ((int)$g['dal_index'] === $dal) {
                if (($g['tur'] === 'daily' && $g['tarih_str'] === $t_str) || ($g['tur'] === 'routine' && $g['tarih_str'] <= $t_str)) {
                    $g_toplam++;
                    $t_list = $g['tamamlandi_tarihleri'] ? explode(',', $g['tamamlandi_tarihleri']) : [];
                    if (in_array($t_str, $t_list)) $g_ok++;
                }
            }
        }
        $aylik_triskel_veri[$dal][$d] = [
            'ratio' => $g_toplam > 0 ? ($g_ok / $g_toplam) : 0,
            'has_tasks' => $g_toplam > 0
        ];
    }
}

// ---- YILLIK MATRİS ORANLARI ----
$yillik_aylik_oranlar = array_fill(0, 12, [0, 0, 0]);
$aktif_yil = date('Y', strtotime($secilen_tarih));

for ($m = 0; $m < 12; $m++) {
    $gercek_ay = $m + 1;
    $m_gun_sayisi = cal_days_in_month(CAL_GREGORIAN, $gercek_ay, $aktif_yil);
    
    for ($dal = 0; $dal < 3; $dal++) {
        $toplam_is = 0; $tamamlanan_is = 0;
        
        for ($d = 1; $d <= $m_gun_sayisi; $d++) {
            $t_str = sprintf("%04d-%02d-%02d", $aktif_yil, $gercek_ay, $d);
            foreach ($tum_gorevler as $g) {
                if ((int)$g['dal_index'] === $dal) {
                    if (($g['tur'] === 'daily' && $g['tarih_str'] === $t_str) || ($g['tur'] === 'routine' && $g['tarih_str'] <= $t_str)) {
                        $toplam_is++;
                        $t_list = $g['tamamlandi_tarihleri'] ? explode(',', $g['tamamlandi_tarihleri']) : [];
                        if (in_array($t_str, $t_list)) $tamamlanan_is++;
                    }
                }
            }
        }
        $yillik_aylik_oranlar[$m][$dal] = $toplam_is > 0 ? ($tamamlanan_is / $toplam_is) : 0;
    }
}

$aktif_sekme = isset($_GET['view']) ? $_GET['view'] : 'today';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Triskel: Yaşam Döngüsü</title>
    <style>
        :root {
            --bg-color: #161412;
            --card-bg: #1f1c18;
            --border-color: #2e2924;
            --text-main: #d9c5b2;
            --text-accent: #c9a074;
            --color-1: #8f9779;
            --color-2: #708090;
            --color-3: #b56951;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Georgia', serif; margin: 0; display: flex; min-height: 100vh; }
        
        .sidebar { width: 70px; background-color: var(--card-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; padding: 25px 0; gap: 20px; position: fixed; height: 100vh; box-sizing: border-box; z-index: 10; }
        .nav-item { width: 45px; height: 45px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { border-color: var(--text-accent); color: var(--text-accent); transform: translateY(-2px); }
        .btn-logout-sidebar { margin-top: auto; font-size: 20px; text-decoration: none; }

        .main-stage { flex: 1; margin-left: 70px; padding: 40px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; box-sizing: border-box; }
        .view-section { display: none; width: 100%; max-width: 1400px; animation: fadeIn 0.4s ease forwards; }
        .view-section.active { display: flex; flex-direction: column; align-items: center; }
        
        h1 { color: var(--text-accent); margin: 0; font-weight: normal; letter-spacing: 2px; }
        .subtitle { color: var(--text-main); opacity: 0.6; margin: 5px 0 25px 0; font-style: italic; }

        .triskel-horizontal-layout { display: flex; gap: 40px; width: 100%; align-items: flex-start; justify-content: center; margin-top: 20px; }
        .canvas-container-side { text-align: center; background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        .cards-grid-side { display: flex; gap: 20px; flex: 1; justify-content: flex-start; }
        
        .branch-card { flex: 1; min-width: 260px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; height: fit-content; }
        .branch-card.b-0 { border-top: 4px solid var(--color-1); }
        .branch-card.b-1 { border-top: 4px solid var(--color-2); }
        .branch-card.b-2 { border-top: 4px solid var(--color-3); }
        
        .card-title-clickable { color: var(--text-accent); cursor: pointer; border-bottom: 1px dashed var(--border-color); padding-bottom: 2px; display: inline-block; font-size: 15px; }
        .card-title-clickable:hover { color: var(--text-main); }

        .tasks-wrapper { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; max-height: 300px; overflow-y: auto; }
        .task-node { display: flex; align-items: center; gap: 8px; background: #191714; padding: 8px; border-radius: 6px; font-family: sans-serif; font-size: 13px; }
        .task-node.done span { text-decoration: line-through; opacity: 0.4; }
        .btn-del { background: transparent; border: none; color: #b56951; cursor: pointer; margin-left: auto; }

        input[type="text"], input[type="date"], select, textarea { background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-main); padding: 6px; border-radius: 6px; }
        .btn-action { background: #2e2924; color: var(--text-main); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: 6px; cursor: pointer; }

        .year-matrix-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; width: 100%; max-width: 1100px; margin-top: 20px; margin-bottom: 40px; }
        .month-circle-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .month-circle-title { font-size: 16px; color: var(--text-accent); margin-bottom: 12px; font-weight: normal; }

        .stat-box-clickable { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px 20px; text-align: center; flex: 1; max-width: 240px; cursor: pointer; transition: all 0.3s; }
        .stat-box-clickable:hover, .stat-box-clickable.active-filter { border-color: var(--text-accent); background: #27231e; transform: translateY(-2px); }

        .culture-workspace { width: 100%; display: flex; flex-direction: column; gap: 25px; }
        .culture-input-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
        
        .archive-scroll-container { max-height: 550px; overflow-y: auto; padding-right: 10px; margin-top: 10px; }
        .archive-scroll-container::-webkit-scrollbar { width: 6px; }
        .archive-scroll-container::-webkit-scrollbar-track { background: var(--bg-color); border-radius: 10px; }
        .archive-scroll-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
        .archive-scroll-container::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        .archive-grid-display { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; width: 100%; }

        .culture-list-item { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; position: relative; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; flex-direction: column; gap: 6px; }
        .culture-list-item:hover { transform: translateY(-2px); border-color: #3e3730; box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        
        .culture-list-item[data-tur="Kitap"] { border-left: 4px solid var(--color-1); }
        .culture-list-item[data-tur="Film"] { border-left: 4px solid var(--color-2); }
        .culture-list-item[data-tur="Dizi"] { border-left: 4px solid var(--color-3); }

        .item-meta-row { display: flex; justify-content: space-between; align-items: center; font-size: 11px; opacity: 0.5; font-family: sans-serif; margin-top: auto; padding-top: 8px; border-top: 1px dashed #282420; }
        
        .month-navigator { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 15px; background: var(--card-bg); padding: 10px; border-radius: 10px; border: 1px solid var(--border-color); }
        .nav-month-btn { background: #2e2924; color: var(--text-accent); border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .nav-month-btn:hover { background: var(--text-accent); color: #161412; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="nav-item <?= $aktif_sekme === 'today' ? 'active' : '' ?>" id="btn-today" onclick="switchView('today', this)" title="Günlük Döngü">☀️</div>
        <div class="nav-item <?= $aktif_sekme === 'month' ? 'active' : '' ?>" id="btn-month" onclick="switchView('month', this)" title="Aylık Labirent">🌀</div>
        <div class="nav-item <?= $aktif_sekme === 'year' ? 'active' : '' ?>" id="btn-year" onclick="switchView('year', this)" title="Yıllık Matris">🌳</div>
        <div class="nav-item <?= $aktif_sekme === 'culture' ? 'active' : '' ?>" id="btn-culture" onclick="switchView('culture', this)" title="Entelektüel Havuz">📚</div>
        <a href="cikis.php" class="btn-logout-sidebar" title="Güvenli Çıkış">🚪</a>
    </div>

    <div class="main-stage">
        
        <!-- 1. GÜNLÜK DÖNGÜ -->
        <div id="view-today" class="view-section <?= $aktif_sekme === 'today' ? 'active' : '' ?>">
            <h1>Triskel Sarmalı</h1>
            <p class="subtitle">Mühürlü Ruh: <strong><?= guvenli_yaz($user_name) ?></strong> <?= $user_avatar ?></p>
            
            <div class="triskel-horizontal-layout">
                <div class="canvas-container-side">
                    <canvas id="todayCanvas" width="320" height="320"></canvas>
                    <div style="margin-top:15px;">
                        <input type="date" value="<?= $secilen_tarih ?>" onchange="location='panel.php?tarih='+this.value">
                    </div>
                </div>

                <div class="cards-grid-side">
                    <?php for($i=0; $i<3; $i++): ?>
                        <div class="branch-card b-<?= $i ?>">
                            <div class="card-header">
                                <span class="card-title-clickable" onclick="guncelleSarmalIsmi(<?= $i ?>, '<?= guvenli_yaz($dal_isimleri[$i]) ?>')">
                                    <?= guvenli_yaz($dal_isimleri[$i]) ?> ⚙️
                                </span>
                            </div>
                            <form method="POST" style="display:flex; gap:5px; margin-top:10px;">
                                <input type="hidden" name="dal_index" value="<?= $i ?>">
                                <input type="text" name="gorev_metni" style="flex:1; min-width:80px;" placeholder="Görev..." required>
                                <select name="tur" style="padding:2px;">
                                    <option value="daily">G</option>
                                    <option value="routine">R</option>
                                </select>
                                <button type="submit" name="gorev_ekle" class="btn-action">+</button>
                            </form>
                            
                            <div class="tasks-wrapper">
                                <?php foreach($dallar[$i] as $task): ?>
                                    <div class="task-node <?= $task['is_done'] ? 'done' : '' ?>">
                                        <input type="checkbox" <?= $task['is_done'] ? 'checked' : '' ?> onclick="location='panel.php?tarih=<?= $secilen_tarih ?>&toggle_gorev=<?= $task['id'] ?>'">
                                        <span><?= guvenli_yaz($task['gorev_metni']) ?></span>
                                        <a href="panel.php?tarih=<?= $secilen_tarih ?>&sil_gorev=<?= $task['id'] ?>" class="btn-del" onclick="return confirm('Silinsin mi?')">✕</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- 2. AYLIK KISIM -->
        <div id="view-month" class="view-section <?= $aktif_sekme === 'month' ? 'active' : '' ?>">
            <h1>Aylık Triskelion Labirenti</h1>
            <p class="subtitle">Kozmik zaman akışındaki sarmal döngünüzü inceleyin.</p>
            
            <div class="month-navigator">
                <?php
                $onceki_ay_ts = strtotime("-1 month", strtotime("$aylik_yil-$aylik_ay-01"));
                $sonraki_ay_ts = strtotime("+1 month", strtotime("$aylik_yil-$aylik_ay-01"));
                $aylar_isim = ["", "Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
                ?>
                <a href="panel.php?tarih=<?= $secilen_tarih ?>&view=month&ay_yil=<?= date('Y', $onceki_ay_ts) ?>&ay_ay=<?= date('m', $onceki_ay_ts) ?>" class="nav-month-btn">◀ Önceki Ay</a>
                <span style="font-size:18px; font-weight:bold; color:var(--text-accent);"><?= $aylar_isim[$aylik_ay] . " " . $aylik_yil ?></span>
                <a href="panel.php?tarih=<?= $secilen_tarih ?>&view=month&ay_yil=<?= date('Y', $sonraki_ay_ts) ?>&ay_ay=<?= date('m', $sonraki_ay_ts) ?>" class="nav-month-btn">Sonraki Ay ▶</a>
            </div>

            <div class="canvas-container-side" style="background: var(--card-bg); padding:30px;">
                <canvas id="monthTriskelCanvas" width="550" height="550"></canvas>
            </div>
        </div>

        <!-- 3. YILLIK DÖNGÜ MATRİSİ -->
        <div id="view-year" class="view-section <?= $aktif_sekme === 'year' ? 'active' : '' ?>">
            <h1>Makro Yıllık Ritmler</h1>
            <p class="subtitle"><?= $aktif_yil ?> Yılına Ait 12 Aylık Entelektüel ve Eylemsel Gelişim Döngünüz</p>
            
            <div class="year-matrix-grid">
                <?php 
                $aylar_liste = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
                for($m=0; $m<12; $m++): 
                ?>
                    <div class="month-circle-card">
                        <div class="month-circle-title"><?= $aylar_liste[$m] ?></div>
                        <canvas id="miniTriskel_<?= $m ?>" width="140" height="140"></canvas>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 4. ENTELEKTÜEL HAVUZ (DÜZELTİLMİŞ VE ARINDIRILMIŞ MODEL) -->
        <div id="view-culture" class="view-section <?= $aktif_sekme === 'culture' ? 'active' : '' ?>">
            <h1>Entelektüel Havuz</h1>
            <p class="subtitle">Zihninizde derin izler bırakan tüm eserler tek bir bütünsel akışta.</p>
            
            <?php
            // ---- AKILLI VE HATASIZ VERİ SAYIM MOTORU ----
            $t_kitap = 0; $t_film = 0; $t_dizi = 0;
            foreach($kultur_havuzu as $k) {
                if($k['tur'] === 'Kitap') $t_kitap++;
                elseif($k['tur'] === 'Film') $t_film++;
                elseif($k['tur'] === 'Dizi') $t_dizi++;
            }
            $t_toplam = $t_kitap + $t_film + $t_dizi;
            ?>

            <div style="display: flex; gap: 20px; width: 100%; margin-bottom: 25px; justify-content: center;">
                <!-- TÜMÜ KUTUSU: DÖKÜM LOGOLARI ARTIK BURADA -->
                <div class="stat-box-clickable active-filter" onclick="canliFiltrele('Hepsi', this)">
                    <div style="font-size: 11px; color: #708090; text-transform: uppercase; letter-spacing: 1px;">Kozmik Toplam</div>
                    <div style="font-size: 24px; color: var(--text-accent); font-family: sans-serif; margin: 4px 0; font-weight: bold;"><?= $t_toplam ?> Eser</div>
                    <div style="font-size: 11px; opacity: 0.8; font-family: sans-serif; color: #708090;">📖 <?= $t_kitap ?> | 🎬 <?= $t_film ?> | 📺 <?= $t_dizi ?></div>
                </div>
                <!-- KATEGORİ KUTULARI: SADECE KENDİ TOPLAMLARINI YAZIYOR -->
                <div class="stat-box-clickable" onclick="canliFiltrele('Kitap', this)" style="border-top: 2px solid var(--color-1);">
                    <div style="font-size: 11px; color: var(--color-1); text-transform: uppercase; letter-spacing: 1px;">Kitap Kitaplığı</div>
                    <div style="font-size: 24px; color: var(--color-1); font-family: sans-serif; margin: 4px 0; font-weight: bold;">📖 Kitaplar</div>
                    <div style="font-size: 11px; opacity: 0.7; font-family: sans-serif;">Toplam: <?= $t_kitap ?> Kitap</div>
                </div>
                <div class="stat-box-clickable" onclick="canliFiltrele('Film', this)" style="border-top: 2px solid var(--color-2);">
                    <div style="font-size: 11px; color: var(--color-2); text-transform: uppercase; letter-spacing: 1px;">Sinema Havuzu</div>
                    <div style="font-size: 24px; color: var(--color-2); font-family: sans-serif; margin: 4px 0; font-weight: bold;">🎬 Filmler</div>
                    <div style="font-size: 11px; opacity: 0.7; font-family: sans-serif;">Toplam: <?= $t_film ?> Film</div>
                </div>
                <div class="stat-box-clickable" onclick="canliFiltrele('Dizi', this)" style="border-top: 2px solid var(--color-3);">
                    <div style="font-size: 11px; color: var(--color-3); text-transform: uppercase; letter-spacing: 1px;">Dizi Kulvarı</div>
                    <div style="font-size: 24px; color: var(--color-3); font-family: sans-serif; margin: 4px 0; font-weight: bold;">📺 Diziler</div>
                    <div style="font-size: 11px; opacity: 0.7; font-family: sans-serif;">Toplam: <?= $t_dizi ?> Dizi</div>
                </div>
            </div>

            <div class="culture-workspace">
                <form method="POST" class="culture-input-panel">
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items: center;">
                        <select name="kultur_turu" style="height:35px;">
                            <option value="Kitap">📖 Kitap</option>
                            <option value="Film">🎬 Film</option>
                            <option value="Dizi">📺 Dizi</option>
                        </select>
                        <input type="text" name="eser_adi" style="flex:2; height:21px;" placeholder="Eserin adı..." required>
                        <input type="date" name="kultur_tarihi" value="<?= $bugun_str ?>" title="Tüketim Tarihi" style="height:21px;">
                        <select name="puan" style="height:35px;">
                            <option value="5">⭐⭐⭐⭐⭐</option>
                            <option value="4">⭐⭐⭐⭐</option>
                            <option value="3">⭐⭐⭐</option>
                            <option value="2">⭐⭐</option>
                            <option value="1">⭐</option>
                        </select>
                    </div>
                    <textarea name="yorum" rows="2" placeholder="Zihninizde kalan mistik veya felsefi izler..."></textarea>
                    <button type="submit" name="kultur_ekle" class="btn-action" style="align-self: flex-end;">Havuza Mühürle</button>
                </form>
                
                <div class="archive-scroll-container">
                    <div class="archive-grid-display">
                        <?php foreach($kultur_havuzu as $k): 
                            $ikon = "📖";
                            if($k['tur'] === 'Film') $ikon = "🎬";
                            if($k['tur'] === 'Dizi') $ikon = "📺";
                        ?>
                            <div class="culture-list-item" data-tur="<?= guvenli_yaz($k['tur']) ?>">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <span style="font-size:16px; font-weight:bold; color:var(--text-main);">
                                        <?= $ikon ?> <?= guvenli_yaz($k['eser_adi']) ?>
                                    </span>
                                    <span style="color:var(--text-accent); font-family:sans-serif; font-size:13px;">
                                        <?= str_repeat('★', $k['puan']) ?>
                                    </span>
                                </div>
                                
                                <p style="font-style:italic; margin:8px 0; color:#b09e8f; font-size:14px; line-height:1.4;">
                                    "<?= guvenli_yaz($k['yorum']) ?>"
                                </p>
                                
                                <div class="item-meta-row">
                                    <span>Tarih: <?= date('d.m.Y', strtotime($k['tarih'])) ?></span>
                                    <span>[<?= guvenli_yaz($k['tur']) ?>]</span>
                                </div>
                                
                                <a href="panel.php?sil_kultur=<?= $k['id'] ?>" class="btn-del" style="position:absolute; bottom:15px; right:15px; display:none;" onclick="return confirm('Silinsin mi?')">✕</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>
        </div>

    </div>

    <script>
        const colors = [
            getComputedStyle(document.documentElement).getPropertyValue('--color-1').trim() || '#8f9779',
            getComputedStyle(document.documentElement).getPropertyValue('--color-2').trim() || '#708090',
            getComputedStyle(document.documentElement).getPropertyValue('--color-3').trim() || '#b56951'
        ];

        document.querySelectorAll('.culture-list-item').forEach(item => {
            item.addEventListener('mouseenter', () => { item.querySelector('.btn-del').style.display = 'block'; });
            item.addEventListener('mouseleave', () => { item.querySelector('.btn-del').style.display = 'none'; });
        });

        function switchView(viewId, btn) {
            document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.getElementById('view-' + viewId).classList.add('active');
            btn.classList.add('active');
            
            if(viewId === 'month') drawAylikTriskel();
            if(viewId === 'year') drawYillikMatrisTriskels();
        }

        function guncelleSarmalIsmi(idx, eskiIsim) {
            let yeniIsim = prompt("Bu sarmal dalına yeni bir unvan verin:", eskiIsim);
            if(yeniIsim && yeniIsim.trim() !== "") {
                let formData = new FormData();
                formData.append('guncelle_dal_isim', '1');
                formData.append('dal_idx', idx);
                formData.append('yeni_isim', yeniIsim.trim());
                
                fetch('panel.php', { method: 'POST', body: formData })
                .then(() => location.reload());
            }
        }

        function canliFiltrele(tur, secilenKutu) {
            document.querySelectorAll('.stat-box-clickable').forEach(box => box.classList.remove('active-filter'));
            secilenKutu.classList.add('active-filter');

            document.querySelectorAll('.culture-list-item').forEach(item => {
                let itemTur = item.getAttribute('data-tur');
                if(tur === 'Hepsi') {
                    item.style.display = 'flex';
                } else if(itemTur === tur) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // --- GÜNLÜK SARMAL ÇİZİM MOTORU ---
        const cToday = document.getElementById('todayCanvas');
        if(cToday) {
            const ctx = cToday.getContext('2d');
            const cx = cToday.width/2, cy = cToday.height/2;
            
            for (let i = 0; i < 3; i++) {
                ctx.beginPath(); ctx.lineWidth = 2; ctx.strokeStyle = '#2e2924';
                let offset = (i * 2 * Math.PI) / 3;
                for (let t = 0; t <= Math.PI * 2.3; t += 0.05) {
                    ctx.lineTo(cx + (15 + t * 13) * Math.cos(offset + t), cy + (15 + t * 13) * Math.sin(offset + t));
                }
                ctx.stroke();
            }
            
            const ratios = [
                <?= count($dallar[0]) > 0 ? (count(array_filter($dallar[0], function($t){return $t['is_done'];})) / count($dallar[0])) : 0 ?>,
                <?= count($dallar[1]) > 0 ? (count(array_filter($dallar[1], function($t){return $t['is_done'];})) / count($dallar[1])) : 0 ?>,
                <?= count($dallar[2]) > 0 ? (count(array_filter($dallar[2], function($t){return $t['is_done'];})) / count($dallar[2])) : 0 ?>
            ];
            
            for (let i = 0; i < 3; i++) {
                if(ratios[i] === 0) continue;
                ctx.beginPath(); ctx.lineWidth = 6; ctx.lineCap = 'round'; ctx.strokeStyle = colors[i];
                let offset = (i * 2 * Math.PI) / 3;
                let maxT = ratios[i] * (Math.PI * 2.3);
                for (let t = 0; t <= maxT; t += 0.04) {
                    ctx.lineTo(cx + (15 + t * 13) * Math.cos(offset + t), cy + (15 + t * 13) * Math.sin(offset + t));
                }
                ctx.stroke();
            }
        }

        // --- AYLIK DİNAMİK TRISKELION MOTORU ---
        const aylikTriskelVerisi = <?= json_encode($aylik_triskel_veri) ?>;
        function drawAylikTriskel() {
            const c = document.getElementById('monthTriskelCanvas'); const ctx = c.getContext('2d');
            const cx = c.width/2, cy = c.height/2; ctx.clearRect(0,0,c.width,c.height);
            const totalDays = Object.keys(aylikTriskelVerisi[0]).length;

            for (let i = 0; i < 3; i++) {
                let offset = (i * 2 * Math.PI) / 3;
                
                ctx.beginPath(); ctx.lineWidth = 1.5; ctx.strokeStyle = '#2d2722';
                for (let t = 0; t <= Math.PI * 2.5; t += 0.05) {
                    ctx.lineTo(cx + (20 + t * 24) * Math.cos(offset + t), cy + (20 + t * 24) * Math.sin(offset + t));
                }
                ctx.stroke();

                for (let d = 1; d <= totalDays; d++) {
                    let progress = (d / totalDays);
                    let t = progress * (Math.PI * 2.5);
                    let r = 20 + t * 24;
                    let x = cx + r * Math.cos(offset + t);
                    let y = cy + r * Math.sin(offset + t);

                    let gunVerisi = aylikTriskelVerisi[i][d];

                    if (gunVerisi.has_tasks) {
                        if (gunVerisi.ratio > 0) {
                            ctx.beginPath(); ctx.arc(x, y, 7, 0, 2*Math.PI);
                            ctx.fillStyle = colors[i]; ctx.fill();
                        } else {
                            ctx.beginPath(); ctx.arc(x, y, 3, 0, 2*Math.PI);
                            ctx.fillStyle = 'rgba(181, 105, 81, 0.25)'; ctx.fill();
                        }
                    } else {
                        ctx.beginPath(); ctx.arc(x, y, 1.5, 0, 2*Math.PI);
                        ctx.fillStyle = '#3a332d'; ctx.fill();
                    }

                    if (i === 0 && d % 2 !== 0) {
                        ctx.fillStyle = '#c9a074'; ctx.font = '9px sans-serif';
                        ctx.fillText(d, x + 6, y - 6);
                    }
                }
            }
        }

        // --- YILLIK MOTOR ÇİZİMİ ---
        const yillikAyOranlari = <?= json_encode($yillik_aylik_oranlar) ?>;
        function drawYillikMatrisTriskels() {
            let m = 0;
            while (m < 12) {
                const c = document.getElementById('miniTriskel_' + m);
                if(c) {
                    const ctx = c.getContext('2d');
                    const cx = c.width/2, cy = c.height/2;
                    ctx.clearRect(0,0,c.width,c.height);
                    
                    for (let i = 0; i < 3; i++) {
                        ctx.beginPath(); ctx.lineWidth = 1; ctx.strokeStyle = '#2e2924';
                        let offset = (i * 2 * Math.PI) / 3;
                        for (let t = 0; t <= Math.PI * 1.8; t += 0.1) {
                            ctx.lineTo(cx + (8 + t * 6) * Math.cos(offset + t), cy + (8 + t * 6) * Math.sin(offset + t));
                        }
                        ctx.stroke();
                    }
                    
                    let oranlar = yillikAyOranlari[m];
                    for (let i = 0; i < 3; i++) {
                        let r = oranlar[i];
                        if(r !== 0) {
                            ctx.beginPath(); ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.strokeStyle = colors[i];
                            let offset = (i * 2 * Math.PI) / 3;
                            let maxT = r * (Math.PI * 1.8);
                            for (let t = 0; t <= maxT; t += 0.08) {
                                ctx.lineTo(cx + (8 + t * 6) * Math.cos(offset + t), cy + (8 + t * 6) * Math.sin(offset + t));
                            }
                            ctx.stroke();
                        }
                    }
                }
                m = m + 1;
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const activeMode = '<?= $aktif_sekme ?>';
            if(activeMode === 'month') drawAylikTriskel();
            if(activeMode === 'year') drawYillikMatrisTriskels();
        });
    </script>
</body>
</html>