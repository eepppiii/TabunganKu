<?php
require_once 'config.php';

function isLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true 
        && isset($_SESSION['current_user_id']);
}

function getCurrentUser() {
    return isset($_SESSION['current_user_id']) ? $_SESSION['current_user_id'] : null;
}

function getCurrentUsername() {
    return isset($_SESSION['current_username']) ? $_SESSION['current_username'] : '';
}

// 🔧 FUNGSI BARU: Pastikan user memiliki goals (buat default jika belum)
function ensureUserGoals($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() == 0) {
        $defaultGoals = [
            ['nama' => 'Biaya Kuliah', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Biaya Kost', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Kebutuhan Sehari-hari', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Buku & Alat Tulis', 'target' => 0, 'terkumpul' => 0]
        ];
        $stmtGoal = $pdo->prepare("INSERT INTO goals (user_id, nama, target, terkumpul) VALUES (?, ?, ?, ?)");
        foreach ($defaultGoals as $goal) {
            $stmtGoal->execute([$user_id, $goal['nama'], $goal['target'], $goal['terkumpul']]);
        }
    }
}

function processRegister($username, $password, $confirm_password) {
    global $pdo;
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username dan password harus diisi!'];
    }
    if ($password !== $confirm_password) {
        return ['success' => false, 'message' => 'Konfirmasi password tidak cocok!'];
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username sudah terdaftar!'];
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    if ($stmt->execute([$username, $hashed])) {
        $user_id = $pdo->lastInsertId();
        $defaultGoals = [
            ['nama' => 'Biaya Kuliah', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Biaya Kost', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Kebutuhan Sehari-hari', 'target' => 0, 'terkumpul' => 0],
            ['nama' => 'Buku & Alat Tulis', 'target' => 0, 'terkumpul' => 0]
        ];
        $stmtGoal = $pdo->prepare("INSERT INTO goals (user_id, nama, target, terkumpul) VALUES (?, ?, ?, ?)");
        foreach ($defaultGoals as $goal) {
            $stmtGoal->execute([$user_id, $goal['nama'], $goal['target'], $goal['terkumpul']]);
        }
        return ['success' => true, 'message' => 'Registrasi berhasil! Silakan login.'];
    }
    return ['success' => false, 'message' => 'Registrasi gagal.'];
}

function processLogin($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['current_user_id'] = $user['id'];
        $_SESSION['current_username'] = $user['username'];
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Username atau password salah!'];
}

// 🔧 MODIFIKASI: Pastikan goals ada sebelum mengambil
function getUserGoals($user_id) {
    global $pdo;
    ensureUserGoals($user_id); // <-- Kunci perbaikan!
    $stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY id");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $icons = ['🎓', '🏠', '🍱', '📚'];
    $colors = ['#E8A87C', '#7CB9E8', '#A8D8A8', '#D8A8D8'];
    foreach ($goals as $i => &$goal) {
        $goal['icon'] = $icons[$i] ?? '🎯';
        $goal['color'] = $colors[$i] ?? '#C4862A';
    }
    return $goals;
}

function getUserTransactions($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, g.nama as goal_name 
        FROM transaksi t 
        LEFT JOIN goals g ON t.goal_id = g.id 
        WHERE t.user_id = ? 
        ORDER BY t.tanggal DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSaldo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END) as pemasukan,
        SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END) as pengeluaran
        FROM transaksi WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['pemasukan'] ?? 0) - ($result['pengeluaran'] ?? 0);
}

function getTotals($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END) as total_pemasukan,
        SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END) as total_pengeluaran
        FROM transaksi WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return [$result['total_pemasukan'] ?? 0, $result['total_pengeluaran'] ?? 0];
}

function processAddTransaction($user_id, $post) {
    global $pdo;
    $jumlah = (int)str_replace(['Rp', '.', ',', ' '], '', $post['jumlah'] ?? 0);
    $kategori = trim($post['kategori'] ?? '');
    $catatan = trim($post['catatan'] ?? '');
    $tipe = $post['tipe'] ?? 'pemasukan';
    $goal_index = isset($post['goal_index']) ? (int)$post['goal_index'] : -1;

    if ($jumlah <= 0 || !$kategori) {
        return ['success' => false, 'message' => 'Jumlah dan kategori tidak boleh kosong.'];
    }

    $goal_id = null;
    if ($goal_index >= 0) {
        $goals = getUserGoals($user_id);
        if (isset($goals[$goal_index])) {
            $goal_id = $goals[$goal_index]['id'];
            if ($tipe === 'pengeluaran' && $goals[$goal_index]['terkumpul'] < $jumlah) {
                return ['success' => false, 'message' => 'Pengeluaran melebihi jumlah yang terkumpul di goal "' . htmlspecialchars($goals[$goal_index]['nama']) . '".'];
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO transaksi (user_id, jumlah, kategori, tipe, catatan, tanggal, goal_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    if (!$stmt->execute([$user_id, $jumlah, $kategori, $tipe, $catatan, $goal_id])) {
        return ['success' => false, 'message' => 'Gagal menyimpan transaksi.'];
    }

    if ($goal_id) {
        if ($tipe === 'pemasukan') {
            $pdo->prepare("UPDATE goals SET terkumpul = terkumpul + ? WHERE id = ?")->execute([$jumlah, $goal_id]);
        } else {
            $pdo->prepare("UPDATE goals SET terkumpul = GREATEST(0, terkumpul - ?) WHERE id = ?")->execute([$jumlah, $goal_id]);
        }
    }

    return ['success' => true, 'message' => 'Transaksi berhasil ditambahkan!'];
}

function processSetTarget($user_id, $post) {
    global $pdo;
    $goal_index = (int)($post['goal_index'] ?? 0);
    $target = (int)str_replace(['Rp', '.', ',', ' '], '', $post['target'] ?? 0);
    if ($target <= 0) {
        return ['success' => false, 'message' => 'Target harus lebih dari 0.'];
    }
    $goals = getUserGoals($user_id);
    if (isset($goals[$goal_index])) {
        $goal_id = $goals[$goal_index]['id'];
        $stmt = $pdo->prepare("UPDATE goals SET target = ? WHERE id = ?");
        if ($stmt->execute([$target, $goal_id])) {
            return ['success' => true, 'message' => 'Target berhasil disimpan!'];
        }
    }
    return ['success' => false, 'message' => 'Gagal menyimpan target.'];
}

function processDeleteTransaction($user_id, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT jumlah, tipe, goal_id FROM transaksi WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $trans = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trans) {
        return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
    }

    if ($trans['goal_id']) {
        if ($trans['tipe'] === 'pemasukan') {
            $pdo->prepare("UPDATE goals SET terkumpul = GREATEST(0, terkumpul - ?) WHERE id = ?")->execute([$trans['jumlah'], $trans['goal_id']]);
        } else {
            $pdo->prepare("UPDATE goals SET terkumpul = terkumpul + ? WHERE id = ?")->execute([$trans['jumlah'], $trans['goal_id']]);
        }
    }

    $pdo->prepare("DELETE FROM transaksi WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    return ['success' => true, 'message' => 'Transaksi berhasil dihapus.'];
}

function processResetData($user_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM transaksi WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE goals SET target = 0, terkumpul = 0 WHERE user_id = ?")->execute([$user_id]);
        $pdo->commit();
        return ['success' => true, 'message' => 'Semua data berhasil direset!'];
    } catch(Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Gagal reset data.'];
    }
}

function exportToCSV($user_id, $bulan, $tahun) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.tanggal, t.tipe, t.kategori, t.jumlah, t.catatan, g.nama as goal_name
        FROM transaksi t
        LEFT JOIN goals g ON t.goal_id = g.id
        WHERE t.user_id = ? AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
        ORDER BY t.tanggal ASC
    ");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transaksi_' . $tahun . '_' . $bulan . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Tanggal', 'Tipe', 'Kategori', 'Jumlah', 'Catatan', 'Alokasi Goal']);
    foreach ($transaksi as $t) {
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($t['tanggal'])),
            $t['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran',
            $t['kategori'],
            $t['jumlah'],
            $t['catatan'],
            $t['goal_name'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}
?>