<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('X-Robots-Tag: noindex, nofollow');

$db = new Database();
$auth = new AuthManager($db);

// Redirect unauthenticated users to the hidden Login Landing Page
if (!$auth->isLoggedIn()) {
    header('Location: admin-login.php');
    exit;
}

$admin        = $auth->getCurrentAdmin();
$csrfToken    = $auth->getCsrfToken();
$settings     = $db->getSettingsMap();
$products     = $db->getProducts();
$certificates = $db->getCertificates();
$articles     = $db->getArticles();
$features     = $db->getFeatures();
$teamMembers  = $db->getTeamMembers();
$testimonials = $db->getTestimonials();
$faqs         = $db->getFaqs();
$vouchers     = $db->getVouchers();
$inquiries    = $db->getInquiries();
$encInfo      = $db->getCrypto()->inspectEnvelope((string)($admin['encrypted_password'] ?? ''));

$totalShopeeClicks = array_sum(array_map(fn($p) => (int)($p['shopee_clicks'] ?? 0), $products));
$totalWaClicks     = array_sum(array_map(fn($p) => (int)($p['wa_clicks'] ?? 0), $products));

function eAdm(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard Admin CRUD - <?= eAdm($settings['brand_name'] ?? 'Madu Nusantara') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Slab:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-dashboard-body">

<div class="admin-layout">
    <!-- ===================================================================
         SIDEBAR NAVIGATION
         =================================================================== -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo-icon">🍯</div>
            <div>
                <h2><?= eAdm($settings['brand_name'] ?? 'MADU NUSANTARA') ?></h2>
                <span>Admin Control Center</span>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Menu Dashboard Admin">
            <button type="button" class="side-nav-btn active" data-tab="tab-overview">
                <span>📊</span> Ringkasan &amp; Analitik
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-products">
                <span>🍯</span> CRUD Produk &amp; Varian <em class="nav-count" id="countProducts"><?= count($products) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-inquiries">
                <span>💬</span> Pesanan &amp; Export CSV <em class="nav-count badge-warn" id="countInquiries"><?= count($inquiries) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-vouchers">
                <span>🎁</span> CRUD Voucher Promo <em class="nav-count" id="countVouchers"><?= count($vouchers) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-certificates">
                <span>🔬</span> CRUD Bukti Uji Lab <em class="nav-count" id="countCertificates"><?= count($certificates) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-faqs">
                <span>❓</span> CRUD Tanya Jawab FAQ <em class="nav-count" id="countFaqs"><?= count($faqs) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-landing">
                <span>🏠</span> Konten Landing Page
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-marketplace">
                <span>🛍️</span> Link Shopee &amp; CS WA
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-features">
                <span>🛡️</span> CRUD Keunggulan <em class="nav-count" id="countFeatures"><?= count($features) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-articles">
                <span>📰</span> CRUD Artikel Edukasi <em class="nav-count" id="countArticles"><?= count($articles) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-team">
                <span>👥</span> CRUD Tim Solid <em class="nav-count" id="countTeam"><?= count($teamMembers) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-testimonials">
                <span>⭐</span> CRUD Testimoni <em class="nav-count" id="countTestimonials"><?= count($testimonials) ?></em>
            </button>
            <button type="button" class="side-nav-btn" data-tab="tab-security">
                <span>🔒</span> Enkripsi &amp; Password
            </button>
        </nav>

        <div class="sidebar-footer">
            <a href="index.php" target="_blank" class="btn-view-site">
                🌐 Lihat Landing Page Publik ↗
            </a>
            <a href="admin-login.php?logout=1" class="btn-logout-sidebar">
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- ===================================================================
         MAIN WORKSPACE AREA
         =================================================================== -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-sidebar-btn" id="mobileSidebarBtn">☰ Menu</button>
                <div>
                    <h1 id="activeTabTitle">Ringkasan &amp; Analitik Klik</h1>
                    <p class="topbar-sub">Kelola seluruh informasi Landing Page (Create, Read, Update, Delete) secara real-time.</p>
                </div>
            </div>
            <div class="topbar-right">
                <span class="enc-status-chip" title="Password Admin dilindungi dengan AES-256-GCM + Bcrypt Cost 12">
                    🔐 <?= eAdm($encInfo['cipher_algorithm'] ?? 'AES-256-GCM') ?> Aktif
                </span>
                <div class="admin-profile-pill">
                    <strong><?= eAdm($admin['display_name'] ?? 'Admin') ?></strong>
                    <span>@<?= eAdm($admin['username'] ?? 'admin') ?></span>
                </div>
            </div>
        </header>

        <!-- Toast Notification Container -->
        <div id="adminToast" class="admin-toast" role="status" aria-live="polite"></div>

        <main class="admin-content-area">

            <!-- ===========================================================
                 TAB 1: OVERVIEW / RINGKASAN & ANALITIK KLIK
                 =========================================================== -->
            <section class="admin-tab-pane active" id="tab-overview">
                <div class="stats-cards-grid">
                    <div class="stat-card" onclick="switchAdminTab('tab-products')">
                        <div class="stat-icon">🍯</div>
                        <div>
                            <span class="stat-label">Total Produk Madu</span>
                            <h3 class="stat-value" id="statProducts"><?= count($products) ?> Varian</h3>
                            <span class="stat-link">Multi-Ukuran &amp; Harga →</span>
                        </div>
                    </div>
                    <div class="stat-card" onclick="switchAdminTab('tab-products')">
                        <div class="stat-icon">🛍️</div>
                        <div>
                            <span class="stat-label">Total Klik ke Shopee</span>
                            <h3 class="stat-value"><?= number_format($totalShopeeClicks, 0, ',', '.') ?> Klik</h3>
                            <span class="stat-link">Traffic Marketplace →</span>
                        </div>
                    </div>
                    <div class="stat-card" onclick="switchAdminTab('tab-inquiries')">
                        <div class="stat-icon">💬</div>
                        <div>
                            <span class="stat-label">Klik WA CS &amp; Keranjang</span>
                            <h3 class="stat-value"><?= number_format($totalWaClicks, 0, ',', '.') ?> Klik</h3>
                            <span class="stat-link" id="statInquiries"><?= count($inquiries) ?> Pesanan Masuk →</span>
                        </div>
                    </div>
                    <div class="stat-card" onclick="switchAdminTab('tab-vouchers')">
                        <div class="stat-icon">🎁</div>
                        <div>
                            <span class="stat-label">Voucher &amp; Uji Lab</span>
                            <h3 class="stat-value" id="statVouchers"><?= count($vouchers) ?> Promo</h3>
                            <span class="stat-link"><?= count($certificates) ?> Sertifikat Lab &amp; <?= count($faqs) ?> FAQ →</span>
                        </div>
                    </div>
                </div>

                <div class="overview-panels-grid">
                    <div class="admin-panel">
                        <div class="panel-header">
                            <h3>🚀 Aksi Cepat Pengelolaan Website</h3>
                        </div>
                        <div class="quick-actions-grid">
                            <button type="button" class="quick-btn" onclick="switchAdminTab('tab-products'); openProductModal();">
                                <strong>+ Tambah Produk &amp; Varian Ukuran</strong>
                                <span>Atur harga 250gr, 500gr, 1Kg, link Shopee &amp; WA CS</span>
                            </button>
                            <button type="button" class="quick-btn" onclick="switchAdminTab('tab-certificates'); openCertificateModal();">
                                <strong>🔬 Kelola Bukti Uji Lab &amp; Halal</strong>
                                <span>Tambah/ubah sertifikat SNI, Halal BPJPH &amp; P-IRT</span>
                            </button>
                            <button type="button" class="quick-btn" onclick="switchAdminTab('tab-vouchers'); openVoucherModal();">
                                <strong>🎁 Buat Kode Voucher Diskon</strong>
                                <span>Kelola kode promo untuk Keranjang Non-Marketplace</span>
                            </button>
                            <button type="button" class="quick-btn" onclick="switchAdminTab('tab-faqs'); openFaqModal();">
                                <strong>❓ Kelola Pertanyaan FAQ Madu</strong>
                                <span>Edukasi pembeli seputar keaslian &amp; penyimpanan madu</span>
                            </button>
                        </div>
                    </div>

                    <div class="admin-panel">
                        <div class="panel-header">
                            <h3>📈 Statistik Klik Produk Terpopuler (Shopee vs WA CS)</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="adm-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Klik Shopee</th>
                                        <th>Klik WA / Cart</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($products, 0, 4) as $p): ?>
                                    <tr>
                                        <td><strong><?= eAdm($p['name']) ?></strong></td>
                                        <td><span style="color:#EE4D2D;font-weight:700;"><?= (int)$p['shopee_clicks'] ?> klik</span></td>
                                        <td><span style="color:#16A34A;font-weight:700;"><?= (int)$p['wa_clicks'] ?> klik</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 2: CRUD PRODUK MADU & MULTI-VARIAN UKURAN
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-products">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🍯 Manajemen Katalog Produk Madu &amp; Varian Ukuran (CRUD)</h3>
                            <p>Tambah, ubah, atau hapus produk beserta pilihan ukuran kemasan (250gr, 500gr, 1Kg) dan pantau statistik klik.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openProductModal()">
                            + Tambah Produk Baru
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Gambar</th>
                                    <th>Nama Produk &amp; Varian Ukuran</th>
                                    <th>Harga Utama</th>
                                    <th>Statistik Klik</th>
                                    <th>Link Shopee &amp; Stok</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 3: CRUD PESANAN NON-MARKETPLACE & EXPORT CSV
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-inquiries">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>💬 Daftar Pesanan Keranjang &amp; Pertanyaan Non-Marketplace (CRUD)</h3>
                            <p>Setiap pelanggan yang melakukan Checkout Keranjang atau mengisi form Tanya CS tercatat otomatis di sini.</p>
                        </div>
                        <div class="header-btn-group">
                            <a href="api.php?action=export_inquiries_csv" class="btn-adm btn-adm-outline" style="text-decoration:none;">
                                📥 Export Laporan ke Excel (CSV)
                            </a>
                            <button type="button" class="btn-adm btn-adm-primary" onclick="openInquiryModal()">
                                + Catat Pesanan Manual
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>ID &amp; Waktu</th>
                                    <th>Pelanggan &amp; Kota</th>
                                    <th>Tipe Layanan</th>
                                    <th>Rincian Produk &amp; Voucher</th>
                                    <th>Total Estimasi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="inquiriesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 4: CRUD VOUCHER PROMO DISKON
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-vouchers">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🎁 Manajemen Kode Voucher Promo &amp; Diskon Keranjang (CRUD)</h3>
                            <p>Kelola kode kupon potongan harga untuk meningkatkan konversi pemesanan Non-Marketplace.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openVoucherModal()">
                            + Buat Kode Voucher Baru
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Kode Voucher</th>
                                    <th>Tipe &amp; Nilai Diskon</th>
                                    <th>Minimal Belanja</th>
                                    <th>Keterangan</th>
                                    <th>Status</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="vouchersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 5: CRUD BUKTI KEASLIAN & SERTIFIKAT UJI LAB
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-certificates">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🔬 Manajemen Bukti Keaslian &amp; Sertifikasi Laboratorium (CRUD)</h3>
                            <p>Kelola dokumen hasil uji laboratorium SNI, Sertifikat Halal BPJPH, dan Izin P-IRT.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openCertificateModal()">
                            + Tambah Sertifikat / Hasil Lab
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Gambar</th>
                                    <th>Nama Sertifikat &amp; Nomor</th>
                                    <th>Penerbit</th>
                                    <th>Ringkasan Hasil Uji</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="certificatesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 6: CRUD TANYA JAWAB / FAQ
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-faqs">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>❓ Manajemen Tanya Jawab Seputar Madu Asli / FAQ (CRUD)</h3>
                            <p>Kelola daftar pertanyaan dan jawaban edukasi yang tampil pada akordeon FAQ di Landing Page.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openFaqModal()">
                            + Tambah Pertanyaan FAQ
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Kategori</th>
                                    <th>Pertanyaan</th>
                                    <th>Jawaban</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="faqsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 7: KONTEN LANDING PAGE (Hero, About, Sustainability, History)
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-landing">
                <form id="landingContentForm" class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🏠 Edit Seluruh Konten Seksi Landing Page</h3>
                            <p>Ubah teks, judul, warna tema, dan gambar untuk seksi Branding, Hero, About, Sertifikat, Panen Lestari, Sejarah, dan FAQ.</p>
                        </div>
                        <div class="header-btn-group">
                            <button type="button" class="btn-adm btn-adm-outline" onclick="resetDefaultSettings()">
                                ↺ Reset ke Default
                            </button>
                            <button type="submit" class="btn-adm btn-adm-primary">
                                💾 Simpan Perubahan Konten
                            </button>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>1. Identitas Brand &amp; Palet Warna (Gaya Template147)</h4>
                        <div class="adm-form-grid-3">
                            <div class="adm-field">
                                <label>Nama Brand Utama</label>
                                <input type="text" name="settings[brand_name]" value="<?= eAdm($settings['brand_name'] ?? '') ?>" required>
                            </div>
                            <div class="adm-field">
                                <label>Tagline Brand</label>
                                <input type="text" name="settings[brand_tagline]" value="<?= eAdm($settings['brand_tagline'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Sub-Teks Logo</label>
                                <input type="text" name="settings[brand_logo_sub]" value="<?= eAdm($settings['brand_logo_sub'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Warna Utama (Teal #298A81)</label>
                                <input type="color" name="settings[primary_color]" value="<?= eAdm($settings['primary_color'] ?? '#298A81') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Warna Sekunder (Amber #C2884F)</label>
                                <input type="color" name="settings[secondary_color]" value="<?= eAdm($settings['secondary_color'] ?? '#C2884F') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Warna Latar Krem (#FCF6EF)</label>
                                <input type="color" name="settings[cream_bg_color]" value="<?= eAdm($settings['cream_bg_color'] ?? '#FCF6EF') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>2. Seksi Hero Utama (Atas Halaman)</h4>
                        <div class="adm-form-grid-2">
                            <div class="adm-field">
                                <label>Badge Atas Hero</label>
                                <input type="text" name="settings[hero_badge]" value="<?= eAdm($settings['hero_badge'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Judul Utama Hero (H1)</label>
                                <input type="text" name="settings[hero_title]" value="<?= eAdm($settings['hero_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Subjudul Hero (H3)</label>
                                <textarea name="settings[hero_subtitle]" rows="2"><?= eAdm($settings['hero_subtitle'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field">
                                <label>URL Gambar Background Hero</label>
                                <div class="upload-input-row">
                                    <input type="text" id="heroBgImageInput" name="settings[hero_bg_image]" value="<?= eAdm($settings['hero_bg_image'] ?? '') ?>">
                                    <label class="btn-upload-inline">
                                        Unggah File
                                        <input type="file" accept="image/*" onchange="uploadImageToInput(this, 'heroBgImageInput')" hidden>
                                    </label>
                                </div>
                            </div>
                            <div class="adm-field">
                                <label>Teks Tombol Utama Hero</label>
                                <input type="text" name="settings[hero_btn_primary_text]" value="<?= eAdm($settings['hero_btn_primary_text'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Teks Tombol Shopee Hero</label>
                                <input type="text" name="settings[hero_btn_shopee_text]" value="<?= eAdm($settings['hero_btn_shopee_text'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Teks Tombol Chat CS Hero</label>
                                <input type="text" name="settings[hero_btn_cs_text]" value="<?= eAdm($settings['hero_btn_cs_text'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>3. Seksi Tentang Kami (About - Overlapping Card)</h4>
                        <div class="adm-form-grid-2">
                            <div class="adm-field">
                                <label>Judul Seksi About</label>
                                <input type="text" name="settings[about_title]" value="<?= eAdm($settings['about_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Badge Sertifikasi / Lab</label>
                                <input type="text" name="settings[about_cert_badge]" value="<?= eAdm($settings['about_cert_badge'] ?? '') ?>">
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Kalimat Highlight Tebal (H5 - Antioksidan &amp; Kemurnian)</label>
                                <textarea name="settings[about_highlight]" rows="2"><?= eAdm($settings['about_highlight'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Deskripsi Paragraf Tentang Kami</label>
                                <textarea name="settings[about_description]" rows="3"><?= eAdm($settings['about_description'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Gambar Seksi About</label>
                                <div class="upload-input-row">
                                    <input type="text" id="aboutImageInput" name="settings[about_image]" value="<?= eAdm($settings['about_image'] ?? '') ?>">
                                    <label class="btn-upload-inline">
                                        Unggah File
                                        <input type="file" accept="image/*" onchange="uploadImageToInput(this, 'aboutImageInput')" hidden>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>4. Seksi Judul Katalog, Bukti Uji Lab, Panen Lestari &amp; FAQ</h4>
                        <div class="adm-form-grid-2">
                            <div class="adm-field">
                                <label>Judul Seksi Katalog Produk</label>
                                <input type="text" name="settings[products_section_title]" value="<?= eAdm($settings['products_section_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Subjudul Seksi Katalog Produk</label>
                                <input type="text" name="settings[products_section_subtitle]" value="<?= eAdm($settings['products_section_subtitle'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Judul Seksi Sertifikat &amp; Uji Lab</label>
                                <input type="text" name="settings[certs_section_title]" value="<?= eAdm($settings['certs_section_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Subjudul Seksi Sertifikat &amp; Uji Lab</label>
                                <input type="text" name="settings[certs_section_subtitle]" value="<?= eAdm($settings['certs_section_subtitle'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Judul Banner Panen Lestari</label>
                                <input type="text" name="settings[sustainability_title]" value="<?= eAdm($settings['sustainability_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Gambar Banner Panen Lestari</label>
                                <div class="upload-input-row">
                                    <input type="text" id="sustainImageInput" name="settings[sustainability_image]" value="<?= eAdm($settings['sustainability_image'] ?? '') ?>">
                                    <label class="btn-upload-inline">
                                        Unggah File
                                        <input type="file" accept="image/*" onchange="uploadImageToInput(this, 'sustainImageInput')" hidden>
                                    </label>
                                </div>
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Deskripsi Banner Panen Lestari</label>
                                <textarea name="settings[sustainability_desc]" rows="3"><?= eAdm($settings['sustainability_desc'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field">
                                <label>Judul Seksi FAQ</label>
                                <input type="text" name="settings[faq_section_title]" value="<?= eAdm($settings['faq_section_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Subjudul Seksi FAQ</label>
                                <input type="text" name="settings[faq_section_subtitle]" value="<?= eAdm($settings['faq_section_subtitle'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>5. Seksi Sejarah &amp; Warisan Madu Nusantara (History)</h4>
                        <div class="adm-form-grid-2">
                            <div class="adm-field">
                                <label>Sub-Judul Sejarah (H5)</label>
                                <input type="text" name="settings[history_badge]" value="<?= eAdm($settings['history_badge'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Judul Utama Sejarah (H3)</label>
                                <input type="text" name="settings[history_title]" value="<?= eAdm($settings['history_title'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Paragraf 1 Sejarah</label>
                                <textarea name="settings[history_paragraph_1]" rows="4"><?= eAdm($settings['history_paragraph_1'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field">
                                <label>Paragraf 2 Sejarah</label>
                                <textarea name="settings[history_paragraph_2]" rows="4"><?= eAdm($settings['history_paragraph_2'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="panel-footer-bar">
                        <button type="submit" class="btn-adm btn-adm-primary">💾 Simpan Seluruh Perubahan Konten</button>
                    </div>
                </form>
            </section>

            <!-- ===========================================================
                 TAB 8: MARKETPLACE SHOPEE, WHATSAPP CS & KONTAK LOKASI
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-marketplace">
                <form id="marketplaceContactForm" class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🛍️ Pengaturan Link Shopee, Customer Service WhatsApp &amp; Kontak</h3>
                            <p>Atur tujuan tombol Marketplace Shopee, nomor WhatsApp CS untuk order non-marketplace, alamat, dan jam buka.</p>
                        </div>
                        <button type="submit" class="btn-adm btn-adm-primary">
                            💾 Simpan Pengaturan Shopee &amp; CS
                        </button>
                    </div>

                    <div class="settings-section-box">
                        <h4>1. Integrasi Marketplace Shopee Official</h4>
                        <div class="adm-form-grid-3">
                            <div class="adm-field">
                                <label>Link Utama Toko Shopee</label>
                                <input type="url" name="settings[shopee_store_url]" value="<?= eAdm($settings['shopee_store_url'] ?? '') ?>" required>
                            </div>
                            <div class="adm-field">
                                <label>Nama Toko di Shopee</label>
                                <input type="text" name="settings[shopee_store_name]" value="<?= eAdm($settings['shopee_store_name'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Badge Rating / Kepercayaan Shopee</label>
                                <input type="text" name="settings[shopee_rating_badge]" value="<?= eAdm($settings['shopee_rating_badge'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>2. Layanan Customer Service &amp; Order Non-Marketplace (WhatsApp)</h4>
                        <div class="adm-form-grid-2">
                            <div class="adm-field">
                                <label>Nomor WhatsApp CS (Awali dengan 628...)</label>
                                <input type="text" name="settings[cs_whatsapp_number]" value="<?= eAdm($settings['cs_whatsapp_number'] ?? '') ?>" required>
                            </div>
                            <div class="adm-field">
                                <label>Nama Tampil Customer Service</label>
                                <input type="text" name="settings[cs_name]" value="<?= eAdm($settings['cs_name'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Template Pesan Tanya CS Umum</label>
                                <textarea name="settings[cs_default_ask_template]" rows="2"><?= eAdm($settings['cs_default_ask_template'] ?? '') ?></textarea>
                            </div>
                            <div class="adm-field">
                                <label>Template Pembuka Pesanan Non-Marketplace</label>
                                <textarea name="settings[cs_default_order_template]" rows="2"><?= eAdm($settings['cs_default_order_template'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-box">
                        <h4>3. Lokasi, Reservasi/Kontak, Jam Operasional &amp; Sosial Media</h4>
                        <div class="adm-form-grid-3">
                            <div class="adm-field adm-col-full">
                                <label>Alamat Lengkap UMKM</label>
                                <input type="text" name="settings[contact_address]" value="<?= eAdm($settings['contact_address'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Link Google Maps</label>
                                <input type="url" name="settings[contact_maps_url]" value="<?= eAdm($settings['contact_maps_url'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Nomor Telepon Tampil</label>
                                <input type="text" name="settings[contact_phone_display]" value="<?= eAdm($settings['contact_phone_display'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Email Resmi</label>
                                <input type="email" name="settings[contact_email]" value="<?= eAdm($settings['contact_email'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Jam Buka Senin - Jumat</label>
                                <input type="text" name="settings[contact_hours_weekdays]" value="<?= eAdm($settings['contact_hours_weekdays'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Jam Buka Hari Sabtu</label>
                                <input type="text" name="settings[contact_hours_saturday]" value="<?= eAdm($settings['contact_hours_saturday'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Jam Buka Hari Minggu</label>
                                <input type="text" name="settings[contact_hours_sunday]" value="<?= eAdm($settings['contact_hours_sunday'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Link Instagram</label>
                                <input type="text" name="settings[social_instagram]" value="<?= eAdm($settings['social_instagram'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Link Facebook</label>
                                <input type="text" name="settings[social_facebook]" value="<?= eAdm($settings['social_facebook'] ?? '') ?>">
                            </div>
                            <div class="adm-field">
                                <label>Link TikTok</label>
                                <input type="text" name="settings[social_tiktok]" value="<?= eAdm($settings['social_tiktok'] ?? '') ?>">
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Deskripsi Footer</label>
                                <input type="text" name="settings[footer_tagline]" value="<?= eAdm($settings['footer_tagline'] ?? '') ?>">
                            </div>
                            <div class="adm-field adm-col-full">
                                <label>Teks Copyright Footer</label>
                                <input type="text" name="settings[footer_copyright]" value="<?= eAdm($settings['footer_copyright'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="panel-footer-bar">
                        <button type="submit" class="btn-adm btn-adm-primary">💾 Simpan Pengaturan Shopee, CS &amp; Kontak</button>
                    </div>
                </form>
            </section>

            <!-- ===========================================================
                 TAB 9: CRUD KEUNGGULAN / FITUR (Features)
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-features">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>🛡️ Manajemen Poin Keunggulan &amp; Jaminan Mutu (CRUD)</h3>
                            <p>Kelola kartu keunggulan yang tampil di bawah seksi About.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openFeatureModal()">
                            + Tambah Keunggulan Baru
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Label / Angka Badge</th>
                                    <th>Judul Keunggulan</th>
                                    <th>Deskripsi</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="featuresTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 10: CRUD ARTIKEL & EDUKASI KESEHATAN (Articles)
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-articles">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>📰 Manajemen Artikel, Proses &amp; Manfaat Kesehatan (CRUD)</h3>
                            <p>Kelola kartu artikel edukasi dan proses panen yang tampil di Landing Page.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openArticleModalAdmin()">
                            + Tambah Artikel Baru
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Gambar</th>
                                    <th>Judul &amp; Kategori</th>
                                    <th>Ringkasan</th>
                                    <th>Penulis &amp; Tanggal</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="articlesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 11: CRUD TIM SOLID (Team Members)
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-team">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>👥 Manajemen Anggota Tim Solid (CRUD)</h3>
                            <p>Kelola profil tim perlebahan dan pengendali mutu yang tampil di seksi "Solid Team".</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openTeamModal()">
                            + Tambah Anggota Tim
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Foto</th>
                                    <th>Nama Lengkap</th>
                                    <th>Jabatan / Peran</th>
                                    <th>Deskripsi Singkat</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="teamTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 12: CRUD TESTIMONI PELANGGAN (Testimonials)
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-testimonials">
                <div class="admin-panel">
                    <div class="panel-header-flex">
                        <div>
                            <h3>⭐ Manajemen Testimoni Pelanggan (CRUD)</h3>
                            <p>Kelola ulasan pembeli dari Shopee Official Store maupun pemesanan langsung via WhatsApp CS.</p>
                        </div>
                        <button type="button" class="btn-adm btn-adm-primary" onclick="openTestimonialModal()">
                            + Tambah Testimoni
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Pelanggan &amp; Kanal</th>
                                    <th>Produk Dibeli</th>
                                    <th>Rating</th>
                                    <th>Isi Ulasan</th>
                                    <th>Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody id="testimonialsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 TAB 13: KEAMANAN & ENKRIPSI PASSWORD ADMIN
                 =========================================================== -->
            <section class="admin-tab-pane" id="tab-security">
                <div class="overview-panels-grid">
                    <div class="admin-panel">
                        <div class="panel-header">
                            <h3>🔑 Ubah Kredensial &amp; Password Admin</h3>
                            <p>Password baru Anda akan di-hash dengan Bcrypt (Cost 12) + HMAC-SHA256 lalu dienkripsi dengan AES-256-GCM.</p>
                        </div>

                        <form id="securityUpdateForm">
                            <div class="adm-field">
                                <label>Username Login Admin</label>
                                <input type="text" name="username" value="<?= eAdm($admin['username'] ?? 'admin') ?>" required>
                            </div>
                            <div class="adm-field">
                                <label>Nama Tampilan Admin</label>
                                <input type="text" name="display_name" value="<?= eAdm($admin['display_name'] ?? '') ?>" required>
                            </div>
                            <div class="adm-field">
                                <label>Password Saat Ini (Wajib untuk Verifikasi) *</label>
                                <input type="password" name="current_password" required placeholder="Masukkan password saat ini">
                            </div>
                            <div class="adm-field">
                                <label>Password Baru (Kosongkan jika tidak ingin mengubah password)</label>
                                <input type="password" id="newPassInput" name="new_password" placeholder="Minimal 8 karakter">
                                <div class="pass-strength-bar"><div id="passStrengthFill"></div></div>
                                <small id="passStrengthText">Masukkan kombinasi huruf besar, kecil, angka &amp; simbol.</small>
                            </div>
                            <div class="adm-field">
                                <label>Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" placeholder="Ulangi password baru">
                            </div>
                            <button type="submit" class="btn-adm btn-adm-primary">
                                🔒 Enkripsi &amp; Simpan Kredensial Baru
                            </button>
                        </form>
                    </div>

                    <div class="admin-panel">
                        <div class="panel-header">
                            <h3>🛡️ Inspektor Enkripsi Password Aktif</h3>
                            <p>Metadata kriptografi dari envelope password yang tersimpan di database saat ini:</p>
                        </div>

                        <div class="crypto-inspector-box" id="cryptoInspectorBox">
                            <div class="crypto-row">
                                <span>Status Envelope:</span>
                                <strong class="badge-ok">TERENKRIPSI VALID</strong>
                            </div>
                            <div class="crypto-row">
                                <span>Algoritma Cipher:</span>
                                <code><?= eAdm((string)($encInfo['cipher_algorithm'] ?? 'AES-256-GCM')) ?></code>
                            </div>
                            <div class="crypto-row">
                                <span>Key Derivation (KDF):</span>
                                <code><?= eAdm((string)($encInfo['kdf_algorithm'] ?? 'PBKDF2-HMAC-SHA256')) ?> (<?= (int)($encInfo['kdf_iterations'] ?? 100000) ?> iterasi)</code>
                            </div>
                            <div class="crypto-row">
                                <span>Adaptive Password Hash:</span>
                                <code><?= eAdm((string)($encInfo['hash_algorithm'] ?? 'Bcrypt Cost 12 + HMAC-SHA256')) ?></code>
                            </div>
                            <div class="crypto-row">
                                <span>Preview IV (96-bit Nonce):</span>
                                <code><?= eAdm((string)($encInfo['iv_Preview'] ?? '')) ?></code>
                            </div>
                            <div class="crypto-row">
                                <span>Preview GCM Auth Tag:</span>
                                <code><?= eAdm((string)($encInfo['tag_Preview'] ?? '')) ?></code>
                            </div>
                            <div class="crypto-row">
                                <span>Preview Ciphertext:</span>
                                <code><?= eAdm((string)($encInfo['ciphertext_Preview'] ?? '')) ?></code>
                            </div>
                            <div class="crypto-row">
                                <span>Terakhir Diperbarui:</span>
                                <strong><?= eAdm((string)($admin['password_updated_at'] ?? '-')) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<div class="adm-modal-backdrop" id="admCrudModal" aria-hidden="true">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <h3 id="admModalTitle">Form Data</h3>
            <button type="button" class="adm-modal-close" onclick="closeAdmModal()">&times;</button>
        </div>
        <form id="admCrudForm" class="adm-modal-body"></form>
    </div>
</div>

<script>
    window.ADMIN_STATE = {
        csrfToken: <?= json_encode($csrfToken) ?>,
        waNumber: <?= json_encode($settings['cs_whatsapp_number'] ?? '6281234567890') ?>,
        products: <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        certificates: <?= json_encode($certificates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        faqs: <?= json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        vouchers: <?= json_encode($vouchers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        articles: <?= json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        features: <?= json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        team: <?= json_encode($teamMembers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        testimonials: <?= json_encode($testimonials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        inquiries: <?= json_encode($inquiries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
