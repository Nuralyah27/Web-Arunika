<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| 1. HITUNG POIN MISI
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(points_awarded), 0)
     FROM mission_completions
     WHERE user_id = ?'
);
$stmt->execute([$userId]);
$totalMissionPoints = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 2. HITUNG JUMLAH KONTRIBUSI BERDASARKAN STATUS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS total
     FROM cultures
     WHERE submitted_by = ?
     GROUP BY status'
);
$stmt->execute([$userId]);

$counts = [
    'pending'  => 0,
    'approved' => 0,
    'revision' => 0,
    'rejected' => 0
];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['status'], $counts)) {
        $counts[$row['status']] = (int) $row['total'];
    }
}

/*
|--------------------------------------------------------------------------
| 3. HITUNG POIN KONTRIBUSI DAN POIN TOTAL
|--------------------------------------------------------------------------
| Kontribusi disetujui = 10 poin
| Kontribusi menunggu  = 2 poin
| Poin misi            = sesuai poin yang tersimpan
| Konversi             = 1 poin sama dengan Rp1
*/
$totalContributionPoints =
    ($counts['approved'] * 10) +
    ($counts['pending'] * 2);

$totalPoints = $totalContributionPoints + $totalMissionPoints;

/*
|--------------------------------------------------------------------------
| 4. HITUNG POIN YANG SUDAH DIGUNAKAN UNTUK PENUKARAN
|--------------------------------------------------------------------------
| Hanya menghitung transaksi yang sudah tercatat.
| Jika tabel penukaran belum dibuat, saldo tetap dihitung
| berdasarkan poin kontribusi dan misi.
*/
$totalPointsSpent = 0;

$tableCheck = $pdo->query(
    "SHOW TABLES LIKE 'point_redemptions'"
);

if ($tableCheck->fetchColumn()) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(points_spent), 0)
         FROM point_redemptions
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $totalPointsSpent = (int) $stmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| 5. HITUNG SALDO AKHIR
|--------------------------------------------------------------------------
*/
$availablePoints = max(0, $totalPoints - $totalPointsSpent);
$saldoRupiah = $availablePoints;

/*
|--------------------------------------------------------------------------
| 6. AMBIL RIWAYAT KONTRIBUSI TERBARU
|--------------------------------------------------------------------------
*/
$items = $pdo->prepare(
    'SELECT id, title, region, category, status,
            review_note, image_path, created_at
     FROM cultures
     WHERE submitted_by = ?
     ORDER BY created_at DESC
     LIMIT 10'
);
$items->execute([$userId]);
$cultures = $items->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<h1>Halo, <?= e($user['name']) ?>!</h1>

<p class="muted">
    Pantau kontribusi dan perjalananmu dalam melestarikan budaya.
</p>

<!-- RINGKASAN KONTRIBUSI -->
<div class="grid">
    <div class="card">
        <p class="muted">Menunggu pemeriksaan</p>
        <h2><?= $counts['pending'] ?></h2>
    </div>

    <div class="card">
        <p class="muted">Disetujui</p>
        <h2><?= $counts['approved'] ?></h2>
    </div>

    <div class="card">
        <p class="muted">Perlu revisi</p>
        <h2><?= $counts['revision'] ?></h2>
    </div>

    <div class="card">
        <p class="muted">Ditolak</p>
        <h2><?= $counts['rejected'] ?></h2>
    </div>
</div>

<!-- PROFIL DAN PENCAPAIAN -->
<div class="card">
    <h2>Profil dan Pencapaian</h2>

    <p>
        <strong>Nama:</strong>
        <?= e($user['name']) ?>
    </p>

    <p>
        <strong>Peran:</strong>
        <?= e(
            ($user['role'] ?? 'user') === 'admin'
                ? 'Admin / Verifikator'
                : 'Pengguna terdaftar'
        ) ?>
    </p>

    <!-- SALDO ARUNIKA -->
    <div style="
        padding: 20px;
        margin: 20px 0;
        border-radius: 14px;
        background: #f8f1e8;
        border: 1px solid #ead9c5;
    ">
        <p class="muted" style="margin: 0 0 6px;">
            Saldo ARUNIKA
        </p>

        <h2 style="
            font-size: 34px;
            color: #823a27;
            margin: 4px 0 8px;
        ">
            Rp<?= number_format($saldoRupiah, 0, ',', '.') ?>
        </h2>

        <p class="muted" style="margin: 0 0 16px;">
            <?= number_format($availablePoints, 0, ',', '.') ?>
            poin tersedia · 1 poin = Rp1
        </p>

        <a class="btn" href="tukar_poin.php">
            Tukar Saldo
        </a>
    </div>

    <!-- RINCIAN POIN -->
    <h3>Rincian Poin</h3>

    <p>
        <strong>Poin kontribusi:</strong>
        <?= number_format($totalContributionPoints, 0, ',', '.') ?> poin
    </p>

    <p>
        <strong>Poin misi:</strong>
        <?= number_format($totalMissionPoints, 0, ',', '.') ?> poin
    </p>

    <p>
        <strong>Poin yang sudah digunakan:</strong>
        <?= number_format($totalPointsSpent, 0, ',', '.') ?> poin
    </p>

    <p>
        <strong>Total poin yang diperoleh:</strong>
        <?= number_format($totalPoints, 0, ',', '.') ?> poin
    </p>

    <!-- LENCANA -->
    <p><strong>Lencana:</strong></p>

    <?php if ($counts['approved'] >= 5): ?>
        <span class="badge approved">Pengarsip Aktif</span>
    <?php elseif ($counts['approved'] >= 1): ?>
        <span class="badge approved">Penjaga Budaya</span>
    <?php else: ?>
        <span class="badge">Kontributor Pemula</span>
    <?php endif; ?>

    <p style="margin-top: 20px;">
        <a class="btn secondary" href="belajar.php">
            Belajar Budaya dan Misi
        </a>
    </p>
</div>

<!-- AJAKAN KONTRIBUSI -->
<div class="card">
    <h2>Kontribusiku</h2>

    <p>
        Bagikan cerita, tradisi, seni, kuliner, atau pengetahuan
        lokal yang sumbernya dapat dijelaskan.
    </p>

    <a class="btn" href="kontribusi.php">
        + Tambah kontribusi budaya
    </a>
</div>

<!-- RIWAYAT KONTRIBUSI -->
<div class="card">
    <h2>Riwayat Kontribusi</h2>

    <?php if (!$cultures): ?>

        <p>
            Belum ada kontribusi. Kontribusi pertamamu bisa dimulai sekarang.
        </p>

    <?php else: ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Daerah</th>
                        <th>Status</th>
                        <th>Catatan Admin</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($cultures as $c): ?>
                        <tr>
                            <td>
                                <?php if (!empty($c['image_path'])): ?>
                                    <img
                                        src="<?= e($c['image_path']) ?>"
                                        alt="Foto budaya"
                                        style="
                                            width:90px;
                                            height:65px;
                                            object-fit:cover;
                                            border-radius:8px;
                                            display:block;
                                            margin-bottom:6px;
                                        "
                                        loading="lazy"
                                    >
                                <?php endif; ?>

                                <?= e($c['title']) ?>

                                <br>

                                <small class="muted">
                                    <?= e($c['category']) ?>
                                </small>
                            </td>

                            <td>
                                <?= e($c['region']) ?>
                            </td>

                            <td>
                                <span class="badge <?= e($c['status']) ?>">
                                    <?= e(
                                        match ($c['status']) {
                                            'pending'  => 'Menunggu',
                                            'approved' => 'Disetujui',
                                            'revision' => 'Perlu revisi',
                                            'rejected' => 'Ditolak',
                                            default    => $c['status']
                                        }
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($c['review_note'] ?: '—') ?>
                            </td>

                            <td>
                                <?= e($c['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>