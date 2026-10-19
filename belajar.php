<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

$pointsPerMission = 20;
$missionMessage = '';
$messageType = 'notice';
$selectedMission = $_GET['misi'] ?? '';

$missions = [
    'foto_budaya' => [
        'title' => 'Berburu Foto Budaya Daerah',
        'level' => 'Mudah',
        'duration' => '10–20 menit',
        'description' => 'Temukan dan dokumentasikan budaya yang ada di daerahmu.',
        'steps' => [
            'Pilih objek budaya seperti rumah adat, pakaian tradisional, kerajinan, atau makanan khas.',
            'Ambil foto sendiri bersama objek budaya tersebut atau foto objeknya.',
            'Tuliskan nama objek budaya dan daerah asalnya.',
            'Jelaskan secara singkat mengapa objek tersebut menarik atau penting.'
        ],
        'evidence' => 'Foto asli hasil kegiatanmu.'
    ],
    'video_budaya' => [
        'title' => 'Video Cerita Budaya',
        'level' => 'Menengah',
        'duration' => '15–30 menit',
        'description' => 'Buat video singkat yang memperkenalkan budaya lokal.',
        'steps' => [
            'Pilih tradisi, kesenian, tempat bersejarah, atau budaya lokal.',
            'Rekam video singkat untuk memperkenalkan budaya tersebut.',
            'Jelaskan nama dan asal budaya serta fakta menariknya.',
            'Pastikan kamu memiliki izin jika merekam orang lain.'
        ],
        'evidence' => 'Video hasil kegiatanmu.'
    ],
    'cerita_budaya' => [
        'title' => 'Penulis Cerita Budaya',
        'level' => 'Mudah',
        'duration' => '15–25 menit',
        'description' => 'Dokumentasikan cerita atau fakta budaya berdasarkan sumber yang jelas.',
        'steps' => [
            'Pilih satu cerita rakyat, tradisi, kuliner, atau kesenian daerah.',
            'Cari informasi dari buku, sumber tepercaya, atau narasumber.',
            'Tuliskan minimal tiga fakta tentang budaya tersebut.',
            'Cantumkan sumber informasi dan jangan mengubah cerita menjadi fakta jika belum terverifikasi.'
        ],
        'evidence' => 'Dokumen PDF, DOCX, TXT, atau gambar tulisan.'
    ],
    'jelajah_budaya' => [
        'title' => 'Jelajah Budaya Sekitar',
        'level' => 'Menengah',
        'duration' => '20–40 menit',
        'description' => 'Jelajahi dan dokumentasikan kegiatan atau objek budaya di sekitarmu.',
        'steps' => [
            'Pilih objek, tempat, kegiatan, atau kerajinan budaya yang bisa kamu kunjungi.',
            'Dokumentasikan kegiatan atau objek tersebut.',
            'Tuliskan lokasi dan nama budaya yang ditemukan.',
            'Jelaskan hal baru yang kamu pelajari dari kegiatan ini.'
        ],
        'evidence' => 'Foto, video, atau dokumen pendukung.'
    ]
];

$uploadDir = __DIR__ . '/uploads/missions/';
$uploadUrl = 'uploads/missions/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $missionKey = $_POST['mission_key'] ?? '';
    $description = trim($_POST['proof_description'] ?? '');

    if (!isset($missions[$missionKey])) {
        $missionMessage = 'Misi tidak valid.';
    } elseif ($description === '') {
        $missionMessage = 'Deskripsi pengerjaan wajib diisi.';
    } elseif (
        !isset($_FILES['proof']) ||
        $_FILES['proof']['error'] !== UPLOAD_ERR_OK
    ) {
        $missionMessage = 'Bukti wajib diunggah. Silakan pilih file kembali.';
    } else {
        $file = $_FILES['proof'];
        $maxSize = 50 * 1024 * 1024;

        $allowed = [
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'gif'  => ['image/gif'],
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
            'mov'  => ['video/quicktime'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'doc'  => ['application/msword'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'mp3'  => ['audio/mpeg', 'audio/mp3'],
            'wav'  => ['audio/wav', 'audio/x-wav'],
            'm4a'  => ['audio/mp4', 'audio/x-m4a']
        ];

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        if ($file['size'] > $maxSize) {
            $missionMessage = 'Ukuran file maksimal 50 MB.';
        } elseif (!isset($allowed[$extension])) {
            $missionMessage =
                'Format tidak didukung. Gunakan gambar, video, PDF, dokumen, atau audio yang didukung.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            if (!in_array($mime, $allowed[$extension], true)) {
                $missionMessage =
                    'Jenis file tidak sesuai dengan ekstensi file.';
            } else {
                try {
                    // Cegah pengiriman misi yang sama lebih dari sekali.
                    $check = $pdo->prepare(
                        'SELECT id FROM mission_completions
                         WHERE user_id = ? AND mission_key = ?'
                    );
                    $check->execute([$userId, $missionKey]);

                    if ($check->fetch()) {
                        $missionMessage =
                            'Misi ini sudah pernah diselesaikan. Poin tidak diberikan dua kali.';
                    } else {
                        if (
                            !is_dir($uploadDir) &&
                            !mkdir($uploadDir, 0755, true) &&
                            !is_dir($uploadDir)
                        ) {
                            throw new RuntimeException(
                                'Folder upload tidak dapat dibuat.'
                            );
                        }

                        // Nama file acak untuk menghindari benturan nama.
                        $filename = bin2hex(random_bytes(16))
                            . '.' . $extension;

                        $destination = $uploadDir . $filename;

                        if (!move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )) {
                            throw new RuntimeException(
                                'File bukti gagal disimpan.'
                            );
                        }

                        try {
                            $stmt = $pdo->prepare(
                                'INSERT INTO mission_completions
                                (user_id, mission_key, points_awarded,
                                 proof_path, proof_type, proof_description)
                                VALUES (?, ?, ?, ?, ?, ?)'
                            );

                            $stmt->execute([
                                $userId,
                                $missionKey,
                                $pointsPerMission,
                                $uploadUrl . $filename,
                                $mime,
                                $description
                            ]);

                            $missionMessage =
                                'Misi berhasil diselesaikan! Kamu mendapatkan '
                                . $pointsPerMission . ' poin.';
                            $messageType = 'notice success';
                            $selectedMission = $missionKey;
                        } catch (Throwable $e) {
                            if (is_file($destination)) {
                                unlink($destination);
                            }
                            throw $e;
                        }
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $missionMessage =
                            'Misi ini sudah pernah diselesaikan. Poin tidak diberikan dua kali.';
                    } else {
                        error_log($e->getMessage());
                        $missionMessage =
                            'Data misi gagal disimpan. Periksa database.';
                    }
                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    $missionMessage =
                        'Bukti gagal disimpan. Periksa izin folder uploads/missions.';
                }
            }
        }
    }
}

// Ambil daftar misi yang telah selesai.
$stmt = $pdo->prepare(
    'SELECT mission_key, points_awarded, proof_path,
            proof_type, proof_description, completed_at
     FROM mission_completions
     WHERE user_id = ?'
);
$stmt->execute([$userId]);

$completedMissions = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $completedMissions[$row['mission_key']] = $row;
}

$totalMissionPoints = array_sum(
    array_map(
        static fn($row) => (int) $row['points_awarded'],
        $completedMissions
    )
);

$totalCompleted = count($completedMissions);

$pageTitle = 'Belajar Budaya dan Misi';
require __DIR__ . '/includes/header.php';
?>

<h1>Belajar Budaya dan Misi</h1>
<p class="muted">
    Ikuti tantangan budaya, kerjakan misinya, lalu unggah bukti
    untuk mendapatkan poin ARUNIKA.
</p>

<?php if ($missionMessage !== ''): ?>
    <div class="<?= e($messageType) ?>">
        <?= e($missionMessage) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Pencapaian Misi</h2>
    <p>
        <strong>Total poin misi:</strong>
        <?= (int) $totalMissionPoints ?> poin
    </p>
    <p>
        <strong>Misi selesai:</strong>
        <?= (int) $totalCompleted ?> / <?= count($missions) ?>
    </p>
    <p class="muted">
        Setiap misi memberikan 20 poin setelah bukti berhasil
        diunggah dan data penyelesaian tersimpan.
    </p>
</div>

<h2>Pilih Tantangan Budaya</h2>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
    <?php foreach ($missions as $key => $mission): ?>
        <div class="card">
            <span class="badge"><?= e($mission['level']) ?></span>
            <p class="muted"><?= e($mission['duration']) ?></p>
            <h3><?= e($mission['title']) ?></h3>
            <p><?= e($mission['description']) ?></p>
            <p><strong>Hadiah: 20 poin</strong></p>

            <?php if (isset($completedMissions[$key])): ?>
                <div class="notice success">
                    Selesai (+<?= (int) $completedMissions[$key]['points_awarded'] ?> poin)
                </div>
                <p class="muted">Bukti telah tersimpan.</p>
            <?php else: ?>
                <a class="btn secondary"
                   href="belajar.php?misi=<?= e($key) ?>#detail-misi">
                    Kerjakan Misi
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if (isset($missions[$selectedMission])): ?>
    <?php
    $mission = $missions[$selectedMission];
    $alreadyCompleted = isset($completedMissions[$selectedMission]);
    ?>

    <section id="detail-misi" class="card" style="margin-top:24px">
        <h2><?= e($mission['title']) ?></h2>
        <p><?= e($mission['description']) ?></p>

        <h3>Langkah Pengerjaan</h3>
        <ol>
            <?php foreach ($mission['steps'] as $step): ?>
                <li style="margin-bottom:8px"><?= e($step) ?></li>
            <?php endforeach; ?>
        </ol>

        <?php if ($alreadyCompleted): ?>
            <div class="notice success">
                Misi ini sudah selesai dan poinnya telah diberikan.
            </div>
            <p>
                <strong>Bukti:</strong>
                <a href="<?= e($completedMissions[$selectedMission]['proof_path']) ?>"
                   target="_blank" rel="noopener">
                    Lihat bukti yang diunggah
                </a>
            </p>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="card">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="mission_key"
                       value="<?= e($selectedMission) ?>">

                <label for="proof_description">
                    Ceritakan hasil pengerjaan
                </label>
                <textarea id="proof_description"
                          name="proof_description"
                          rows="4"
                          required
                          maxlength="5000"
                          placeholder="Ceritakan apa yang kalian lakukan, nama budaya, dan lokasi atau sumbernya."></textarea>

                <label for="proof">Unggah bukti pengerjaan (wajib)</label>
                <p class="muted">
                    Bukti yang diterima: <?= e($mission['evidence']) ?>
                    Format lainnya yang didukung meliputi foto, video,
                    PDF, dokumen, dan audio. Maksimal 50 MB.
                </p>

                <input id="proof"
                       type="file"
                       name="proof"
                       accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.pdf,.txt,.doc,.docx,.mp3,.wav,.m4a"
                       required>

                <p class="muted">
                    Pastikan bukti tidak melanggar privasi orang lain
                    atau memuat informasi budaya yang dibatasi komunitas.
                </p>

                <button type="submit">
                    Kirim Bukti dan Klaim 20 Poin
                </button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>