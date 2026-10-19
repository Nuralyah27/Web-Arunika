<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

$pageTitle = 'Tukar Poin';
$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| HITUNG TOTAL POIN YANG DIPEROLEH
|--------------------------------------------------------------------------
*/
function arunikaTotalPoints(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(
            CASE
                WHEN status = 'approved' THEN 10
                WHEN status = 'pending' THEN 2
                ELSE 0
            END
        ), 0)
        FROM cultures
        WHERE submitted_by = ?"
    );
    $stmt->execute([$userId]);
    $contributionPoints = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(points_awarded), 0)
         FROM mission_completions
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $missionPoints = (int) $stmt->fetchColumn();

    return $contributionPoints + $missionPoints;
}

/*
|--------------------------------------------------------------------------
| HITUNG PENUKARAN POIN LAMA
|--------------------------------------------------------------------------
| Mendukung tabel point_redemptions lama jika points_spent tersedia.
*/
function arunikaLegacySpent(PDO $pdo, int $userId): int
{
    try {
        $table = $pdo->query(
            "SHOW TABLES LIKE 'point_redemptions'"
        );

        if (!$table->fetchColumn()) {
            return 0;
        }

        $column = $pdo->query(
            "SHOW COLUMNS FROM point_redemptions LIKE 'points_spent'"
        );

        if (!$column->fetch(PDO::FETCH_ASSOC)) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(points_spent), 0)
             FROM point_redemptions
             WHERE user_id = ?"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| SIAPKAN DOMPET
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO arunika_wallets (user_id, balance)
     VALUES (?, 0)"
);
$stmt->execute([$userId]);

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $action = $_POST['action'] ?? '';

        $pdo->beginTransaction();

        // Mengunci akun untuk mencegah transaksi bersamaan.
        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE id = ? FOR UPDATE"
        );
        $stmt->execute([$userId]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Akun tidak ditemukan.');
        }

        $stmt = $pdo->prepare(
            "SELECT balance
             FROM arunika_wallets
             WHERE user_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            throw new RuntimeException('Dompet ARUNIKA tidak ditemukan.');
        }

        $balance = (int) $wallet['balance'];

        /*
        |--------------------------------------------------------------------------
        | A. TUKAR POIN MENJADI SALDO
        |--------------------------------------------------------------------------
        */
        if ($action === 'convert') {
            $points = filter_var(
                $_POST['points'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($points === false || $points === null || $points < 1) {
                throw new RuntimeException(
                    'Masukkan jumlah poin yang valid.'
                );
            }

            $totalPoints = arunikaTotalPoints($pdo, $userId);

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(points_used), 0)
                 FROM arunika_wallet_transactions
                 WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
            $convertedPoints = (int) $stmt->fetchColumn();

            $legacySpent = arunikaLegacySpent($pdo, $userId);

            $availablePoints = max(
                0,
                $totalPoints - $convertedPoints - $legacySpent
            );

            if ($points > $availablePoints) {
                throw new RuntimeException(
                    'Poin tidak mencukupi. Tersedia '
                    . number_format($availablePoints, 0, ',', '.')
                    . ' poin.'
                );
            }

            // 1 poin = Rp1.
            $amount = $points;

            $stmt = $pdo->prepare(
                "INSERT INTO arunika_wallet_transactions
                    (user_id, transaction_type, points_used, amount, description)
                 VALUES (?, 'topup_saldo', ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $points,
                $amount,
                'Konversi poin menjadi saldo ARUNIKA'
            ]);

            $stmt = $pdo->prepare(
                "UPDATE arunika_wallets
                 SET balance = balance + ?
                 WHERE user_id = ?"
            );
            $stmt->execute([$amount, $userId]);

            $message = 'Berhasil menukar '
                . number_format($points, 0, ',', '.')
                . ' poin menjadi Rp'
                . number_format($amount, 0, ',', '.')
                . '.';
        }

        /*
        |--------------------------------------------------------------------------
        | B. TUKAR SALDO MENJADI PULSA ATAU VOUCHER
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'redeem') {
            $type = $_POST['reward_type'] ?? '';
            $provider = trim($_POST['provider'] ?? '');
            $destination = trim($_POST['destination'] ?? '');

            $nominal = filter_var(
                $_POST['nominal'] ?? null,
                FILTER_VALIDATE_INT
            );

            $allowedTypes = ['pulsa', 'voucher'];

            $allowedProviders = [
                'pulsa' => [
                    'Telkomsel', 'Indosat', 'XL', 'Tri', 'Smartfren'
                ],
                'voucher' => [
                    'Shopee', 'Tokopedia', 'GoPay', 'OVO', 'DANA'
                ]
            ];

            $allowedNominals = [5000, 10000, 20000, 50000];

            if (!in_array($type, $allowedTypes, true)) {
                throw new RuntimeException('Jenis penukaran tidak valid.');
            }

            if (
                !in_array($provider, $allowedProviders[$type], true)
            ) {
                throw new RuntimeException('Provider tidak valid.');
            }

            if (
                $nominal === false ||
                $nominal === null ||
                !in_array($nominal, $allowedNominals, true)
            ) {
                throw new RuntimeException('Nominal tidak valid.');
            }

            if ($type === 'pulsa') {
                $phone = preg_replace('/[\s-]+/', '', $destination);

                if (!preg_match('/^(?:\+62|62|0)8[0-9]{8,12}$/', $phone)) {
                    throw new RuntimeException(
                        'Masukkan nomor HP Indonesia yang valid.'
                    );
                }

                $destination = $phone;
            } elseif ($destination === '' || mb_strlen($destination) > 100) {
                throw new RuntimeException(
                    'Masukkan nomor akun atau tujuan voucher yang valid.'
                );
            }

            if ($balance < $nominal) {
                throw new RuntimeException(
                    'Saldo tidak mencukupi untuk penukaran ini.'
                );
            }

            // Potong saldo dan catat permintaan dalam transaksi yang sama.
            $stmt = $pdo->prepare(
                "UPDATE arunika_wallets
                 SET balance = balance - ?
                 WHERE user_id = ? AND balance >= ?"
            );
            $stmt->execute([$nominal, $userId, $nominal]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Saldo tidak mencukupi atau transaksi gagal.'
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO arunika_reward_redemptions
                    (user_id, reward_type, provider, nominal, destination, status)
                 VALUES (?, ?, ?, ?, ?, 'Diproses')"
            );
            $stmt->execute([
                $userId,
                $type,
                $provider,
                $nominal,
                $destination
            ]);

            $message = 'Permintaan penukaran '
                . ($type === 'pulsa' ? 'pulsa' : 'voucher')
                . ' berhasil dicatat. Saldo berkurang Rp'
                . number_format($nominal, 0, ',', '.')
                . '. Status: Diproses.';
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Transaksi gagal. Periksa tabel database dan coba lagi.';

        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA TERBARU UNTUK DITAMPILKAN
|--------------------------------------------------------------------------
*/
$totalPoints = arunikaTotalPoints($pdo, $userId);

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(points_used), 0)
     FROM arunika_wallet_transactions
     WHERE user_id = ?"
);
$stmt->execute([$userId]);
$convertedPoints = (int) $stmt->fetchColumn();

$legacySpent = arunikaLegacySpent($pdo, $userId);

$availablePoints = max(
    0,
    $totalPoints - $convertedPoints - $legacySpent
);

$stmt = $pdo->prepare(
    "SELECT balance FROM arunika_wallets WHERE user_id = ?"
);
$stmt->execute([$userId]);
$balance = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT points_used, amount, description, created_at
     FROM arunika_wallet_transactions
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 20"
);
$stmt->execute([$userId]);
$conversionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT reward_type, provider, nominal, destination, status, created_at
     FROM arunika_reward_redemptions
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 20"
);
$stmt->execute([$userId]);
$rewardHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<h1>Tukar Poin ARUNIKA</h1>

<p class="muted">
    Tukarkan poin kontribusi dan misi menjadi saldo, lalu gunakan
    saldo untuk mengajukan penukaran pulsa atau voucher.
</p>

<?php if ($message !== ''): ?>
    <div class="notice <?= e($messageType) ?>">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<!-- RINGKASAN SALDO -->
<div class="grid">
    <div class="card">
        <p class="muted">Saldo ARUNIKA</p>
        <h2 style="font-size:30px;color:#823a27;">
            Rp<?= number_format($balance, 0, ',', '.') ?>
        </h2>
    </div>

    <div class="card">
        <p class="muted">Poin tersedia</p>
        <h2>
            <?= number_format($availablePoints, 0, ',', '.') ?>
        </h2>
        <p class="muted">1 poin = Rp1</p>
    </div>
</div>

<!-- FORM KONVERSI POIN KE SALDO -->
<div class="card">
    <h2>1. Tukar Poin ke Saldo</h2>

    <p>
        Total poin diperoleh:
        <strong>
            <?= number_format($totalPoints, 0, ',', '.') ?> poin
        </strong>
    </p>

    <form method="post" action="tukar_poin.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >
        <input type="hidden" name="action" value="convert">

        <label for="points">Jumlah poin yang ingin ditukar</label>

        <input
            type="number"
            id="points"
            name="points"
            min="1"
            max="<?= $availablePoints ?>"
            step="1"
            required
            <?= $availablePoints < 1 ? 'disabled' : '' ?>
            placeholder="Contoh: 1000"
        >

        <p class="muted">
            Saldo yang diterima sama dengan jumlah poin yang ditukar.
        </p>

        <button
            type="submit"
            class="btn"
            <?= $availablePoints < 1 ? 'disabled' : '' ?>
        >
            Tukar ke Saldo
        </button>
    </form>
</div>

<!-- FORM PULSA/VOUCHER -->
<div class="card">
    <h2>2. Tukar Saldo ke Pulsa atau Voucher</h2>

    <p>
        Saldo saat ini:
        <strong>
            Rp<?= number_format($balance, 0, ',', '.') ?>
        </strong>
    </p>

    <form method="post" action="tukar_poin.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >
        <input type="hidden" name="action" value="redeem">

        <label for="reward_type">Jenis penukaran</label>
        <select
            name="reward_type"
            id="reward_type"
            required
            onchange="updateRewardOptions()"
        >
            <option value="pulsa">Pulsa</option>
            <option value="voucher">Voucher</option>
        </select>

        <label for="provider">Provider</label>
        <select name="provider" id="provider" required>
            <option value="Telkomsel">Telkomsel</option>
            <option value="Indosat">Indosat</option>
            <option value="XL">XL</option>
            <option value="Tri">Tri</option>
            <option value="Smartfren">Smartfren</option>
        </select>

        <label for="nominal">Nominal penukaran</label>
        <select name="nominal" id="nominal" required>
            <option value="5000">Rp5.000</option>
            <option value="10000">Rp10.000</option>
            <option value="20000">Rp20.000</option>
            <option value="50000">Rp50.000</option>
        </select>

        <label for="destination" id="destination_label">Nomor HP tujuan</label>
        <input
            type="text"
            id="destination"
            name="destination"
            maxlength="100"
            required
            placeholder="Contoh: 08xxxxxxxxxx"
        >

        <button
            type="submit"
            class="btn"
            <?= $balance < 5000 ? 'disabled' : '' ?>
        >
            Ajukan Penukaran
        </button>

        <p class="muted">
            Penukaran pulsa atau voucher dicatat dengan status Diproses.
            Pengiriman belum otomatis karena belum terhubung ke API penyedia.
        </p>
    </form>
</div>

<!-- RIWAYAT KONVERSI -->
<div class="card">
    <h2>Riwayat Tukar Poin ke Saldo</h2>

    <?php if (!$conversionHistory): ?>
        <p>Belum ada konversi poin ke saldo.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Poin Ditukar</th>
                        <th>Saldo Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversionHistory as $item): ?>
                        <tr>
                            <td><?= e($item['created_at']) ?></td>
                            <td>
                                <?= number_format(
                                    (int) $item['points_used'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> poin
                            </td>
                            <td>
                                Rp<?= number_format(
                                    (int) $item['amount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- RIWAYAT PULSA/VOUCHER -->
<div class="card">
    <h2>Riwayat Pulsa dan Voucher</h2>

    <?php if (!$rewardHistory): ?>
        <p>Belum ada pengajuan penukaran pulsa atau voucher.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Provider</th>
                        <th>Nominal</th>
                        <th>Tujuan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rewardHistory as $item): ?>
                        <tr>
                            <td><?= e($item['created_at']) ?></td>
                            <td><?= e(ucfirst($item['reward_type'])) ?></td>
                            <td><?= e($item['provider']) ?></td>
                            <td>
                                Rp<?= number_format(
                                    (int) $item['nominal'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                            <td><?= e($item['destination']) ?></td>
                            <td>
                                <span class="badge">
                                    <?= e($item['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function updateRewardOptions() {
    const type = document.getElementById('reward_type').value;
    const provider = document.getElementById('provider');
    const label = document.getElementById('destination_label');
    const destination = document.getElementById('destination');

    const options = type === 'pulsa'
        ? ['Telkomsel', 'Indosat', 'XL', 'Tri', 'Smartfren']
        : ['Shopee', 'Tokopedia', 'GoPay', 'OVO', 'DANA'];

    provider.replaceChildren();

    options.forEach(function (name) {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        provider.appendChild(option);
    });

    if (type === 'pulsa') {
        label.textContent = 'Nomor HP tujuan';
        destination.placeholder = 'Contoh: 08xxxxxxxxxx';
    } else {
        label.textContent = 'Nomor akun atau tujuan voucher';
        destination.placeholder = 'Masukkan nomor akun tujuan';
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>