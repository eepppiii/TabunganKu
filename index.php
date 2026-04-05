<?php
require_once 'functions.php';

$pesan = '';
$pesan_type = '';

$is_logged_in = isLoggedIn();
$current_user_id = getCurrentUser();
$current_username = $_SESSION['current_username'] ?? '';

// Proses Registrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $result = processRegister($_POST['reg_username'] ?? '', $_POST['reg_password'] ?? '', $_POST['confirm_password'] ?? '');
    $pesan = $result['message'];
    $pesan_type = $result['success'] ? 'success' : 'error';
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $result = processLogin($_POST['login_username'] ?? '', $_POST['login_password'] ?? '');
    if ($result['success']) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $pesan = $result['message'];
        $pesan_type = 'error';
    }
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Jika login, ambil data dari database
$goals = [];
$transaksi = [];
$saldo = 0;
$total_pemasukan = 0;
$total_pengeluaran = 0;

if ($is_logged_in && $current_user_id) {
    $goals = getUserGoals($current_user_id);
    $transaksi = getUserTransactions($current_user_id);
    $saldo = getSaldo($current_user_id);
    list($total_pemasukan, $total_pengeluaran) = getTotals($current_user_id);
}

// Handle form submissions untuk transaksi
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $result = null;
    
    if ($action === 'tambah_transaksi') {
        $result = processAddTransaction($current_user_id, $_POST);
    } elseif ($action === 'set_target') {
        $result = processSetTarget($current_user_id, $_POST);
    } elseif ($action === 'hapus_transaksi') {
        $result = processDeleteTransaction($current_user_id, $_POST['id'] ?? '');
    } elseif ($action === 'reset') {
        $result = processResetData($current_user_id);
    }
    
    if ($result) {
        $pesan = $result['message'];
        $pesan_type = $result['success'] ? 'success' : 'error';
        // Refresh data
        $goals = getUserGoals($current_user_id);
        $transaksi = getUserTransactions($current_user_id);
        $saldo = getSaldo($current_user_id);
        list($total_pemasukan, $total_pengeluaran) = getTotals($current_user_id);
    }
}

// Export ke Excel
if ($is_logged_in && isset($_GET['export']) && $_GET['export'] === 'excel') {
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
    exportToCSV($current_user_id, $bulan, $tahun);
}

$bulan_ini = date('n');
$tahun_ini = date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>TabunganKu — Hemat Cerdas untuk Masa Depan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if ($pesan): ?>
<div class="toast <?= $pesan_type ?>">
    <?= $pesan_type === 'success' ? '✓' : '✗' ?> <?= htmlspecialchars($pesan) ?>
</div>
<?php endif; ?>

<?php if (!$is_logged_in): ?>
    <!-- HALAMAN LOGIN/REGISTER -->
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-tabs">
                    <button class="auth-tab active" onclick="switchAuthTab('login')">Masuk</button>
                    <button class="auth-tab" onclick="switchAuthTab('register')">Daftar</button>
                </div>
                
                <div id="login-form" class="auth-form active">
                    <h3 style="font-family:'Playfair Display';">Selamat Datang Kembali</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="login_username" placeholder="Masukkan username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="login_password" placeholder="Masukkan password" required>
                        </div>
                        <button type="submit" name="login" class="btn-auth">Masuk</button>
                    </form>
                </div>
                
                <div id="register-form" class="auth-form">
                    <h3 style="font-family:'Playfair Display';">Buat Akun Baru</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="reg_username" placeholder="Pilih username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="reg_password" placeholder="Buat password" required>
                        </div>
                        <div class="form-group">
                            <label>Konfirmasi Password</label>
                            <input type="password" name="confirm_password" placeholder="Ulangi password" required>
                        </div>
                        <button type="submit" name="register" class="btn-auth">Daftar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- TAMPILAN DASHBOARD -->
    <header>
        <div class="header-inner">
            <div class="logo">Tabungan<span>Ku</span></div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <nav class="nav-tabs">
                    <button class="nav-tab active" onclick="showPage('dashboard')">Dashboard</button>
                    <button class="nav-tab" onclick="showPage('tips')">Tips</button>
                </nav>
                <div class="user-info">
                    <span class="username-badge">👤 <?= htmlspecialchars($current_username) ?></span>
                    <a href="?logout=1" class="logout-btn">Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div id="page-dashboard" class="page-view active">
            <!-- SUMMARY -->
            <div class="summary-grid">
                <div class="card card-main">
                    <div class="label">Total Saldo</div>
                    <div class="amount"><?= formatRupiah($saldo) ?></div>
                </div>
                <div class="card">
                    <div class="card-icon">📥</div>
                    <div class="card-label">Pemasukan</div>
                    <div class="card-amount green"><?= formatRupiah($total_pemasukan) ?></div>
                </div>
                <div class="card">
                    <div class="card-icon">📤</div>
                    <div class="card-label">Pengeluaran</div>
                    <div class="card-amount red"><?= formatRupiah($total_pengeluaran) ?></div>
                </div>
            </div>

            <!-- GOALS -->
            <div class="section-title">Target Tabungan</div>
            <div class="goals-grid">
                <?php foreach ($goals as $i => $goal):
                    $pct = $goal['target'] > 0 ? min(100, round($goal['terkumpul'] / $goal['target'] * 100)) : 0;
                ?>
                <div class="goal-card">
                    <div class="goal-header">
                        <div class="goal-icon" style="background: <?= $goal['color'] ?>22;"><?= $goal['icon'] ?></div>
                        <div>
                            <div class="goal-name"><?= htmlspecialchars($goal['nama']) ?></div>
                            <div class="goal-sub"><?= $pct ?>% tercapai</div>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $pct ?>%; background:<?= $goal['color'] ?>;"></div>
                    </div>
                    <div class="goal-amounts">
                        <span>Terkumpul: <strong><?= formatRupiah($goal['terkumpul']) ?></strong></span>
                        <span>Target: <strong><?= $goal['target'] > 0 ? formatRupiah($goal['target']) : '—' ?></strong></span>
                    </div>
                    <button class="set-target-btn" onclick="openTargetModal(<?= $i ?>)">
                        <?= $goal['target'] > 0 ? '✏️ Ubah Target' : '+ Set Target' ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">
                <div class="card">
                    <div class="form-title">Catat Transaksi</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="tambah_transaksi">
                        <input type="hidden" name="tipe" id="tipe-input" value="pemasukan">
                        <div class="tipe-selector">
                            <button type="button" class="tipe-btn active-masuk" id="btn-masuk" onclick="setTipe('pemasukan')">↑ Pemasukan</button>
                            <button type="button" class="tipe-btn" id="btn-keluar" onclick="setTipe('pengeluaran')">↓ Pengeluaran</button>
                        </div>
                        <div class="form-group">
                            <label>Jumlah (Rp)</label>
                            <input type="text" name="jumlah" id="jumlah-input" placeholder="0" required oninput="formatInput(this)">
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori">
                                <optgroup label="Pemasukan">
                                    <option value="Uang Saku">Uang Saku</option>
                                    <option value="Transfer Orang Tua">Transfer Orang Tua</option>
                                    <option value="Beasiswa">Beasiswa</option>
                                    <option value="Kerja Partime">Kerja Part-time</option>
                                    <option value="Freelance">Freelance</option>
                                </optgroup>
                                <optgroup label="Pengeluaran">
                                    <option value="Makan & Minum">Makan & Minum</option>
                                    <option value="Kost / Kontrakan">Kost / Kontrakan</option>
                                    <option value="Transportasi">Transportasi</option>
                                    <option value="Buku & ATK">Buku & ATK</option>
                                    <option value="Pulsa / Internet">Pulsa / Internet</option>
                                    <option value="Hiburan">Hiburan</option>
                                    <option value="Biaya Kuliah">Biaya Kuliah</option>
                                    <option value="Kesehatan">Kesehatan</option>
                                    <option value="Laundry">Laundry</option>
                                    <option value="Lainnya">Lainnya</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Alokasi ke Goal (opsional)</label>
                            <select name="goal_index">
                                <option value="-1">— Tidak dialokasikan —</option>
                                <?php foreach ($goals as $i => $g): ?>
                                <option value="<?= $i ?>"><?= $g['icon'] ?> <?= htmlspecialchars($g['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Catatan</label>
                            <input type="text" name="catatan" placeholder="Opsional...">
                        </div>
                        <button type="submit" class="btn-primary">Simpan Transaksi</button>
                    </form>
                </div>

                <div class="card">
                    <div style="display:flex; justify-content:space-between; margin-bottom:1rem;">
                        <div class="form-title" style="margin:0;">Riwayat Transaksi</div>
                        <span style="font-size:0.7rem;"><?= count($transaksi) ?> transaksi</span>
                    </div>

                    <?php if (empty($transaksi)): ?>
                        <div class="empty-state">💸 Belum ada transaksi.</div>
                    <?php else: ?>
                        <div class="txn-list">
                            <?php foreach ($transaksi as $t): ?>
                            <div class="txn-item">
                                <div class="txn-dot <?= $t['tipe'] === 'pemasukan' ? 'in' : 'out' ?>"><?= $t['tipe'] === 'pemasukan' ? '↑' : '↓' ?></div>
                                <div class="txn-info">
                                    <div class="txn-name"><?= htmlspecialchars($t['kategori']) ?><?= $t['catatan'] ? ' — ' . htmlspecialchars($t['catatan']) : '' ?></div>
                                    <div class="txn-date"><?= date('d M Y, H:i', strtotime($t['tanggal'])) ?></div>
                                </div>
                                <div class="txn-amount <?= $t['tipe'] === 'pemasukan' ? 'in' : 'out' ?>"><?= $t['tipe'] === 'pemasukan' ? '+' : '−' ?><?= formatRupiah($t['jumlah']) ?></div>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="hapus_transaksi">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="txn-del" title="Hapus">✕</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- EXPORT -->
                    <div class="export-section">
                        <div class="export-title">📎 Export Data Bulanan</div>
                        <div class="export-controls">
                            <select id="export-bulan">
                                <?php foreach ($list_bulan as $num => $nama): ?>
                                    <option value="<?= $num ?>" <?= $num == $bulan_ini ? 'selected' : '' ?>><?= $nama ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="export-tahun">
                                <?php for ($y = $tahun_ini - 2; $y <= $tahun_ini + 1; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $tahun_ini ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <button onclick="exportExcel()">📎 Download Excel</button>
                        </div>
                        <div class="export-note">Download transaksi per bulan (CSV).</div>
                    </div>

                    <?php if (!empty($transaksi)): ?>
                        <div class="divider"></div>
                        <form method="POST" onsubmit="return confirm('Reset semua data?')">
                            <input type="hidden" name="action" value="reset">
                            <button type="submit" style="background:none; border:none; color:var(--text-muted); font-size:0.75rem; cursor:pointer;">🗑 Reset semua data</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="page-tips" class="page-view">
            <div class="section-title">💡 Tips Hemat Mahasiswa</div>
            <div class="tips-grid">
                <div class="tip-card"><div class="tip-icon">🍳</div><div class="tip-title">Masak Sendiri</div><div class="tip-desc">Hemat 50–70% dari makan di luar.</div></div>
                <div class="tip-card"><div class="tip-icon">📋</div><div class="tip-title">Buat Anggaran</div><div class="tip-desc">50% kebutuhan, 30% keinginan, 20% tabungan.</div></div>
                <div class="tip-card"><div class="tip-icon">🛒</div><div class="tip-title">Belanja Mingguan</div><div class="tip-desc">Lebih hemat dari beli harian.</div></div>
                <div class="tip-card"><div class="tip-icon">🚶</div><div class="tip-title">Jalan Kaki</div><div class="tip-desc">Kurangi ojek online, sehat & hemat.</div></div>
                <div class="tip-card"><div class="tip-icon">📱</div><div class="tip-title">Paket Internet Cerdas</div><div class="tip-desc">Manfaatkan WiFi kampus.</div></div>
                <div class="tip-card"><div class="tip-icon">🎯</div><div class="tip-title">Tetapkan Target</div><div class="tip-desc">Sisihkan tabungan di awal bulan.</div></div>
            </div>
        </div>
    </div>

    <!-- MODAL TARGET -->
    <div class="modal-overlay" id="target-modal">
        <div class="modal">
            <div class="modal-title">Set Target Tabungan</div>
            <form method="POST">
                <input type="hidden" name="action" value="set_target">
                <input type="hidden" name="goal_index" id="modal-goal-index">
                <div class="form-group">
                    <label id="modal-goal-label">Target</label>
                    <input type="text" name="target" id="modal-target-input" placeholder="Rp 0" required oninput="formatInput(this)">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.goalsData = <?= json_encode($goals) ?>;
    </script>
<?php endif; ?>

<script src="script.js"></script>
</body>
</html>