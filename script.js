// ==================== FITUR TARGET TABUNGAN ====================

// Buka modal untuk mengatur target goal
function openTargetModal(index) {
    const modal = document.getElementById('target-modal');
    const goalsData = window.goalsData || [];
    
    if (modal && goalsData[index]) {
        document.getElementById('modal-goal-index').value = index;
        const label = document.getElementById('modal-goal-label');
        if (label) {
            label.innerHTML = goalsData[index].icon + ' ' + goalsData[index].nama;
        }
        const targetInput = document.getElementById('modal-target-input');
        if (targetInput) {
            if (goalsData[index].target > 0) {
                targetInput.value = goalsData[index].target.toLocaleString('id-ID');
            } else {
                targetInput.value = '';
            }
        }
        modal.classList.add('open');
    }
}

// Tutup modal target
function closeModal() {
    const modal = document.getElementById('target-modal');
    if (modal) modal.classList.remove('open');
    const targetInput = document.getElementById('modal-target-input');
    if (targetInput) targetInput.value = '';
}

// ==================== FITUR TRANSAKSI ====================

// Toggle tipe transaksi (Pemasukan/Pengeluaran)
function setTipe(tipe) {
    const tipeInput = document.getElementById('tipe-input');
    if (tipeInput) {
        tipeInput.value = tipe;
        const btnM = document.getElementById('btn-masuk');
        const btnK = document.getElementById('btn-keluar');
        if (btnM && btnK) {
            btnM.className = 'tipe-btn' + (tipe === 'pemasukan' ? ' active-masuk' : '');
            btnK.className = 'tipe-btn' + (tipe === 'pengeluaran' ? ' active-keluar' : '');
        }
    }
}

// Format input Rupiah (otomatis tambah titik)
function formatInput(input) {
    let raw = input.value.replace(/\D/g, '');
    if (!raw) {
        input.value = '';
    } else {
        input.value = parseInt(raw).toLocaleString('id-ID');
    }
}

// ==================== NAVIGASI & EXPORT ====================

// Ganti halaman (Dashboard / Tips)
function showPage(page) {
    document.querySelectorAll('.page-view').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById('page-' + page);
    if (targetPage) targetPage.classList.add('active');
    
    document.querySelectorAll('.nav-tab').forEach((t, i) => {
        t.classList.toggle('active', (page === 'dashboard' && i === 0) || (page === 'tips' && i === 1));
    });
}

// Export transaksi ke CSV (Excel)
function exportExcel() {
    const bulan = document.getElementById('export-bulan').value;
    const tahun = document.getElementById('export-tahun').value;
    window.location.href = '?export=excel&bulan=' + bulan + '&tahun=' + tahun;
}

// ==================== AUTH (LOGIN/REGISTER) ====================

// Switch tab login/register
function switchAuthTab(tab) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const tabs = document.querySelectorAll('.auth-tab');
    
    if (tab === 'login') {
        loginForm.classList.add('active');
        registerForm.classList.remove('active');
        tabs[0].classList.add('active');
        tabs[1].classList.remove('active');
    } else {
        loginForm.classList.remove('active');
        registerForm.classList.add('active');
        tabs[0].classList.remove('active');
        tabs[1].classList.add('active');
    }
}

// ==================== EVENT LISTENER GLOBAL ====================

document.addEventListener('DOMContentLoaded', function() {
    // Modal: klik di luar area modal untuk menutup
    const modal = document.getElementById('target-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
    
    // Auto hilangkan toast notification setelah 3 detik
    setTimeout(() => {
        const toast = document.querySelector('.toast');
        if (toast) toast.remove();
    }, 3000);
});