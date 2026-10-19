<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Beranda';
$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$region = trim((string)($_GET['region'] ?? ''));
$sql = "SELECT c.id, c.title, c.region, c.category, c.description, c.image_path, c.created_at, u.name AS contributor
        FROM cultures c JOIN users u ON u.id = c.submitted_by
        WHERE c.status = 'approved'";
$params = [];
if ($q !== '') { $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.source_info LIKE ?)"; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($category !== '') { $sql .= " AND c.category = ?"; $params[]=$category; }
if ($region !== '') { $sql .= " AND c.region LIKE ?"; $params[]='%'.$region.'%'; }
$sql .= " ORDER BY c.reviewed_at DESC, c.created_at DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $cultures = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <p><strong>Warisan budaya, cerita kita bersama.</strong></p>
    <h1>Kenali, rekam, dan lestarikan budaya Nusantara.</h1>
    <p>ARUNIKA adalah rancangan arsip digital kolaboratif untuk mendokumentasikan budaya lokal dengan menghormati sumber, konteks, dan komunitas pemiliknya.</p>
    <div class="actions">
        <a class="btn" href="#jelajah">Jelajahi budaya</a>
        <?php if (is_logged_in()): ?>
            <a class="btn secondary" href="kontribusi.php">Bagikan budaya</a>
        <?php else: ?>
            <a class="btn secondary" href="register.php">Ikut berkontribusi</a>
        <?php endif; ?>
    </div>
</section>

<section id="jelajah">
    <h2>Jelajah Budaya</h2>
    <p class="muted">Cari cerita rakyat, tradisi lisan, seni, kuliner, kerajinan, permainan, upacara adat, dan pengetahuan lokal.</p>
    <form method="get" action="index.php#jelajah" class="card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end">
      <div><label for="q">Kata kunci</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Nama budaya atau isi arsip"></div>
      <div><label for="category">Kategori</label><select id="category" name="category"><option value="">Semua kategori</option><?php foreach(['Cerita rakyat','Tradisi lisan','Seni dan pertunjukan','Kuliner','Kerajinan','Permainan tradisional','Upacara adat','Pengetahuan lokal','Lainnya'] as $cat): ?><option value="<?=e($cat)?>" <?=$category===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach; ?></select></div>
      <div><label for="region">Provinsi/daerah</label><input id="region" name="region" value="<?=e($region)?>" placeholder="Misalnya Berau"></div>
      <div class="actions"><button type="submit">Cari budaya</button><a class="btn secondary" href="index.php#jelajah">Reset</a></div>
    </form>
    <h2>Arsip budaya terverifikasi</h2>
    <p class="muted">Hanya kontribusi yang telah disetujui yang ditampilkan di katalog publik.</p>
    <?php if (!$cultures): ?>
        <div class="empty">
            <h3>Tidak ada arsip yang cocok</h3>
            <p>Coba ubah kata kunci, kategori, atau daerah. Arsip publik hanya menampilkan kontribusi yang telah diverifikasi.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($cultures as $culture): ?>
                <article class="card">
                    <?php if (!empty($culture['image_path'])): ?><img class="culture-image" src="<?= e($culture['image_path']) ?>" alt="Foto <?= e($culture['title']) ?>" loading="lazy"><?php endif; ?>
                    <span class="badge"><?= e($culture['category']) ?></span>
                    <h3><a href="detail_budaya.php?id=<?= (int)$culture['id'] ?>"><?= e($culture['title']) ?></a></h3>
                    <p><strong>Asal:</strong> <?= e($culture['region']) ?></p>
                    <p><?= nl2br(e(mb_strimwidth($culture['description'], 0, 220, '…', 'UTF-8'))) ?></p>
                    <p class="muted">Kontributor: <?= e($culture['contributor']) ?></p>
                    <a class="btn secondary small" href="detail_budaya.php?id=<?= (int)$culture['id'] ?>">Baca detail</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
