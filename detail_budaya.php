<?php
require_once __DIR__ . '/includes/auth.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Arsip budaya tidak ditemukan.'); }
$stmt=$pdo->prepare("SELECT c.*, u.name AS contributor FROM cultures c JOIN users u ON u.id=c.submitted_by WHERE c.id=? AND (c.status='approved' OR c.submitted_by=?) LIMIT 1");
$stmt->execute([$id, is_logged_in() ? current_user()['id'] : 0]); $culture=$stmt->fetch();
if(!$culture){http_response_code(404);exit('Arsip budaya tidak ditemukan atau belum dipublikasikan.');}
$pageTitle='Detail Budaya'; require __DIR__.'/includes/header.php';
?>
<article class="card">
<?php if(!empty($culture['image_path'])):?><img class="culture-image" style="height:auto;max-height:520px;object-fit:contain" src="<?=e($culture['image_path'])?>" alt="Foto <?=e($culture['title'])?>"><?php endif;?>
<span class="badge"><?=e($culture['category'])?></span> <span class="badge approved">Terverifikasi</span>
<h1><?=e($culture['title'])?></h1><p><strong>Daerah asal:</strong> <?=e($culture['region'])?></p>
<h2>Deskripsi budaya</h2><p><?=nl2br(e($culture['description']))?></p>
<h2>Sumber informasi</h2><p><?=nl2br(e($culture['source_info'] ?: 'Sumber belum dicantumkan secara publik.'))?></p>
<p class="muted">Kontributor: <?=e($culture['contributor'])?> · Diperbarui: <?=e($culture['reviewed_at'] ?: $culture['created_at'])?></p>
<?php if(!empty($culture['usage_notes'])):?><h2>Izin dan batasan penggunaan</h2><p><?=nl2br(e($culture['usage_notes']))?></p><?php else:?><p class="muted">Gunakan informasi ini secara bertanggung jawab. Hormati komunitas pemilik budaya dan mintalah izin sebelum menggunakan dokumentasi untuk publikasi atau kepentingan komersial.</p><?php endif;?>
<a class="btn secondary" href="index.php#jelajah">Kembali ke katalog</a>
</article>
<?php require __DIR__.'/includes/footer.php'; ?>
