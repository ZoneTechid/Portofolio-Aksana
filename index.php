<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$db = new Database();
$auth = new AuthManager($db);

$settings     = $db->getSettingsMap();
$products     = $db->getProducts();
$certificates = $db->getCertificates();
$articles     = $db->getArticles();
$features     = $db->getFeatures();
$teamMembers  = $db->getTeamMembers();
$testimonials = $db->getTestimonials();
$faqs         = $db->getFaqs();
$vouchers     = $db->getVouchers();
$isAdmin      = $auth->isLoggedIn();

function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function formatRupiah(int|string $amount): string
{
    return 'Rp ' . number_format((int)$amount, 0, ',', '.');
}

function buildWaLink(string $rawNumber, string $message): string
{
    $clean = preg_replace('/[^0-9]/', '', $rawNumber) ?: '6281234567890';
    if (str_starts_with($clean, '0')) {
        $clean = '62' . substr((string)$clean, 1);
    }
    return 'https://wa.me/' . $clean . '?text=' . rawurlencode($message);
}

/**
 * Parses variants_text like "250gr Travel:65000|500gr Reguler:115000|1Kg Family:210000"
 */
function parseProductVariants(array $prod): array
{
    $raw = trim((string)($prod['variants_text'] ?? ''));
    $list = [];
    if ($raw !== '') {
        foreach (explode('|', $raw) as $part) {
            $pieces = explode(':', trim($part));
            if (count($pieces) === 2 && trim($pieces[0]) !== '' && is_numeric(trim($pieces[1]))) {
                $list[] = [
                    'label' => trim($pieces[0]),
                    'price' => (int)trim($pieces[1]),
                ];
            }
        }
    }
    if (empty($list)) {
        $list[] = [
            'label' => (string)($prod['weight'] ?? '500gr'),
            'price' => (int)($prod['price'] ?? 95000),
        ];
    }
    return $list;
}

$waNumber = (string)($settings['cs_whatsapp_number'] ?? '6281234567890');
$defaultAskWaLink = buildWaLink($waNumber, (string)($settings['cs_default_ask_template'] ?? 'Halo CS Madu Nusantara, saya ingin bertanya tentang produk madu.'));
$primaryColor = e($settings['primary_color'] ?? '#298A81');
$secondaryColor = e($settings['secondary_color'] ?? '#C2884F');
$creamBgColor = e($settings['cream_bg_color'] ?? '#FCF6EF');

$categories = [];
foreach ($products as $p) {
    $cat = trim((string)$p['category']);
    if ($cat !== '' && !in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}

$activeVouchers = array_values(array_filter($vouchers, fn($v) => (int)($v['is_active'] ?? 0) === 1));

// Build Schema.org JSON-LD for LocalBusiness + Products + FAQPage
$schemaProducts = [];
foreach ($products as $idx => $p) {
    $schemaProducts[] = [
        '@type'    => 'ListItem',
        'position' => $idx + 1,
        'item'     => [
            '@type'       => 'Product',
            'name'        => $p['name'],
            'description' => $p['short_desc'],
            'image'       => $p['image_url'],
            'brand'       => ['@type' => 'Brand', 'name' => $settings['brand_name'] ?? 'Madu Nusantara'],
            'offers'      => [
                '@type'         => 'Offer',
                'priceCurrency' => 'IDR',
                'price'         => (string)$p['price'],
                'availability'  => 'https://schema.org/InStock',
                'url'           => $p['shopee_url'],
            ],
        ],
    ];
}

$schemaFaqs = [];
foreach ($faqs as $f) {
    $schemaFaqs[] = [
        '@type'          => 'Question',
        'name'           => $f['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text'  => $f['answer'],
        ],
    ];
}

$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'       => 'LocalBusiness',
            'name'        => $settings['brand_name'] ?? 'Madu Nusantara',
            'description' => $settings['hero_subtitle'] ?? '',
            'telephone'   => $settings['contact_phone_display'] ?? '',
            'email'       => $settings['contact_email'] ?? '',
            'address'     => [
                '@type'         => 'PostalAddress',
                'streetAddress' => $settings['contact_address'] ?? '',
                'addressCountry'=> 'ID',
            ],
        ],
        [
            '@type'           => 'ItemList',
            'name'            => 'Katalog Produk ' . ($settings['brand_name'] ?? 'Madu Nusantara'),
            'itemListElement' => $schemaProducts,
        ],
        [
            '@type'      => 'FAQPage',
            'mainEntity' => $schemaFaqs,
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($settings['brand_name'] ?? 'Madu Nusantara') ?> - <?= e($settings['hero_subtitle'] ?? '') ?>">
    <!-- Open Graph / Social Media Share Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="<?= e($settings['brand_name'] ?? 'Madu Nusantara') ?>">
    <meta property="og:title" content="<?= e($settings['brand_name'] ?? 'Madu Nusantara') ?> — <?= e($settings['hero_title'] ?? '100% Pure Raw Honey') ?>">
    <meta property="og:description" content="<?= e($settings['hero_subtitle'] ?? '') ?>. Tersedia di Shopee Official & Pemesanan Langsung via CS.">
    <meta property="og:image" content="<?= e($settings['about_image'] ?? 'assets/images/about-honey.svg') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($settings['brand_name'] ?? 'Madu Nusantara') ?>">
    <meta name="twitter:description" content="<?= e($settings['hero_subtitle'] ?? '') ?>">
    <title><?= e($settings['brand_name'] ?? 'Madu Nusantara') ?> - <?= e($settings['brand_tagline'] ?? 'The Sweet Science of Pure Honey') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Slab:wght@400;600;700&family=Roboto:wght@400;500;700&family=Yeseva+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --secondary: <?= $secondaryColor ?>;
            --cream-bg: <?= $creamBgColor ?>;
        }
    </style>
</head>
<body>

<?php if ($isAdmin): ?>
<!-- Floating Top Bar Only Visible When Admin Is Logged In -->
<div class="admin-logged-banner">
    <div class="container admin-banner-inner">
        <span><strong>Mode Administrator Aktif:</strong> Anda sedang melihat tampilan publik Landing Page.</span>
        <div class="admin-banner-actions">
            <a href="admin.php" class="btn-admin-go">Buka Dashboard Admin (CRUD)</a>
            <a href="admin-login.php?logout=1" class="btn-admin-logout">Logout</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =======================================================================
     HEADER / STICKY NAVIGATION (Clean 3-Zone Layout + Animated Active State)
     ======================================================================= -->
<header class="site-header" id="site-header">
    <div class="container header-container">
        <div class="header-brand-group">
            <button class="mobile-menu-toggle" id="mobileMenuBtn" aria-label="Buka Menu Navigasi" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <a href="#home" class="brand-logo" id="brandLogoSecret" title="<?= e($settings['brand_name'] ?? 'MADU NUSANTARA') ?>">
                <div class="brand-logo-icon">
                    <svg viewBox="0 0 36 36" fill="none">
                        <polygon points="18,2 33,10.5 33,25.5 18,34 3,25.5 3,10.5" fill="var(--secondary)" stroke="#FFF" stroke-width="2"/>
                        <path d="M18,9 C22,15 24,18 24,22 C24,25.3 21.3,28 18,28 C14.7,28 12,25.3 12,22 C12,18 14,15 18,9 Z" fill="#FFFBEB"/>
                    </svg>
                </div>
                <div class="brand-logo-text">
                    <span class="brand-title"><?= e($settings['brand_name'] ?? 'MADU NUSANTARA') ?></span>
                    <span class="brand-sub"><?= e($settings['brand_logo_sub'] ?? 'PURE RAW HONEY') ?></span>
                </div>
            </a>
        </div>

        <nav class="main-nav" id="mainNav" aria-label="Navigasi Utama">
            <div class="nav-pill-track" id="navPillTrack">
                <span class="nav-hover-glider" id="navHoverGlider" aria-hidden="true"></span>
                <span class="nav-glider" id="navGlider" aria-hidden="true"></span>
                <ul class="nav-list" id="navList">
                    <li><a href="#home" class="nav-link active" data-target="home">Home</a></li>
                    <li><a href="#about-us" class="nav-link" data-target="about-us">About</a></li>
                    <li><a href="#products" class="nav-link" data-target="products">Products</a></li>
                    <li><a href="#certificates" class="nav-link" data-target="certificates">Uji Lab</a></li>
                    <li><a href="#news" class="nav-link" data-target="news">Edukasi</a></li>
                    <li><a href="#faq" class="nav-link" data-target="faq">FAQ</a></li>
                    <li><a href="#contact-us" class="nav-link" data-target="contact-us">Contact</a></li>
                </ul>
            </div>
        </nav>

        <div class="header-right">
            <button type="button" class="header-cart-pill" onclick="openCartModal()" title="Keranjang Belanja Non-Marketplace">
                <span>🛒 Keranjang</span>
                <span class="cart-count-badge" id="headerCartCount">0</span>
            </button>

            <a href="<?= e($settings['shopee_store_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="header-shopee-pill" title="Kunjungi Toko Resmi Shopee">
                <svg class="icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm0 10c-2.76 0-5-2.24-5-5h2c0 1.66 1.34 3 3 3s3-1.34 3-3h2c0 2.76-2.24 5-5 5z"/></svg>
                <span>Shopee</span>
            </a>
        </div>
    </div>
</header>

<main id="content">
    <!-- ===================================================================
         SECTION 1: HERO BANNER (Modeled after template147 Section 1)
         =================================================================== -->
    <section class="hero-section" id="home" style="background-image: url('<?= e($settings['hero_bg_image'] ?? 'assets/images/hero-honey.svg') ?>');">
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <span class="hero-badge"><?= e($settings['hero_badge'] ?? '100% MADU MURNI ALAMI TANPA CAMPURAN') ?></span>
            <h1 class="hero-title"><?= e($settings['hero_title'] ?? 'The Sweet Science of Pure Honey') ?></h1>
            <h3 class="hero-subtitle"><?= e($settings['hero_subtitle'] ?? 'Exploring the Health Benefits of Raw Natural Honey') ?></h3>

            <div class="hero-cta-group">
                <a href="#products" class="btn btn-primary-hero">
                    <?= e($settings['hero_btn_primary_text'] ?? 'All Products') ?>
                </a>
                <a href="<?= e($settings['shopee_store_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-shopee-hero">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3z"/></svg>
                    <?= e($settings['hero_btn_shopee_text'] ?? 'Official Store Shopee') ?>
                </a>
                <button type="button" class="btn btn-cs-hero" onclick="openCsOrderModal(null, 'Tanya Customer Service')">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
                    <?= e($settings['hero_btn_cs_text'] ?? 'Chat Customer Service') ?>
                </button>
            </div>

            <div class="hero-trust-strip">
                <div class="trust-pill">
                    <span class="trust-dot"></span>
                    <span><?= e($settings['shopee_rating_badge'] ?? '4.9/5.0 di Shopee Official') ?></span>
                </div>
                <div class="trust-pill">
                    <span class="trust-dot"></span>
                    <span><?= e($settings['about_cert_badge'] ?? 'Lulus Uji Lab SNI & Halal') ?></span>
                </div>
                <?php if (!empty($activeVouchers)): ?>
                <div class="trust-pill promo-pill-highlight" onclick="openCartModal()" style="cursor:pointer;">
                    <span>🎁 Gunakan Kode Promo: <strong><?= e($activeVouchers[0]['code']) ?></strong> (<?= e($activeVouchers[0]['description']) ?>)</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 2: OVERLAPPING ABOUT SECTION (Modeled after template147 Sec 2)
         =================================================================== -->
    <section class="about-section" id="about-us">
        <div class="container">
            <div class="about-overlap-card">
                <div class="about-text-col">
                    <span class="section-eyebrow-light"><?= e($settings['about_cert_badge'] ?? '100% Pure Indonesian Honey') ?></span>
                    <h2 class="about-heading"><?= e($settings['about_title'] ?? 'About') ?></h2>
                    <h5 class="about-highlight"><?= e($settings['about_highlight'] ?? '') ?></h5>
                    <p class="about-desc"><?= nl2br(e($settings['about_description'] ?? '')) ?></p>
                    <div class="about-actions">
                        <a href="#products" class="btn btn-secondary">Jelajahi Varian Madu</a>
                        <a href="#certificates" class="btn btn-outline-light">Lihat Bukti Uji Lab &amp; Halal</a>
                    </div>
                </div>
                <div class="about-image-col">
                    <div class="about-image-frame">
                        <img src="<?= e($settings['about_image'] ?? 'assets/images/about-honey.svg') ?>" alt="<?= e($settings['about_title'] ?? 'Tentang Madu Nusantara') ?>" loading="lazy">
                    </div>
                </div>
            </div>

            <!-- 4 Purity & Quality Guarantee Badges -->
            <?php if (!empty($features)): ?>
            <div class="features-grid">
                <?php foreach ($features as $feat): ?>
                <div class="feature-card">
                    <div class="feature-badge-Top"><?= e($feat['badge_value']) ?></div>
                    <h3 class="feature-title"><?= e($feat['title']) ?></h3>
                    <p class="feature-desc"><?= e($feat['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 3: PRODUCT SPECIAL (Modeled after template147 Section 3-5)
         With Multi-Size Variants, Add-to-Cart, Shopee Link & CS Order
         =================================================================== -->
    <section class="products-section" id="products">
        <div class="container">
            <div class="section-header-split">
                <div>
                    <span class="section-eyebrow">KATALOG PRODUK UNGGULAN</span>
                    <h2 class="section-title"><?= e($settings['products_section_title'] ?? 'Product Special') ?></h2>
                </div>
                <p class="section-subtitle-right"><?= e($settings['products_section_subtitle'] ?? '') ?></p>
            </div>

            <!-- Category Filter & Search Controls -->
            <div class="product-filter-bar">
                <div class="category-pills" role="tablist" aria-label="Filter Kategori Produk">
                    <button type="button" class="cat-pill active" data-category="all">Semua Produk (<?= count($products) ?>)</button>
                    <?php foreach ($categories as $cat): ?>
                    <button type="button" class="cat-pill" data-category="<?= e($cat) ?>"><?= e($cat) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="product-search-wrap">
                    <input type="search" id="productSearchInput" placeholder="Cari varian madu atau manfaat..." aria-label="Cari Produk Madu">
                </div>
            </div>

            <!-- Products Grid (3 Columns like template147) -->
            <div class="products-grid" id="productsGrid">
                <?php foreach ($products as $prod):
                    $prodId = (int)$prod['id'];
                    $price = (int)$prod['price'];
                    $origPrice = (int)$prod['original_price'];
                    $discountPct = ($origPrice > $price && $origPrice > 0)
                        ? round((($origPrice - $price) / $origPrice) * 100)
                        : 0;
                    $benefitsList = array_filter(array_map('trim', explode('|', (string)$prod['benefits'])));
                    $variants = parseProductVariants($prod);
                    $directWaUrl = buildWaLink(
                        $waNumber,
                        (string)($prod['wa_custom_message'] ?: "Halo CS, saya tertarik dengan produk {$prod['name']} ({$prod['weight']}).")
                    );
                ?>
                <article class="product-card"
                         id="product-card-<?= $prodId ?>"
                         data-id="<?= $prodId ?>"
                         data-category="<?= e($prod['category']) ?>"
                         data-selected-variant="<?= e($variants[1]['label'] ?? $variants[0]['label']) ?>"
                         data-selected-price="<?= (int)($variants[1]['price'] ?? $variants[0]['price']) ?>"
                         data-search="<?= e(strtolower($prod['name'] . ' ' . $prod['short_desc'] . ' ' . $prod['benefits'] . ' ' . $prod['category'])) ?>">
                    <div class="product-img-wrap" onclick="openProductDetailModal(<?= $prodId ?>)">
                        <img src="<?= e($prod['image_url']) ?>" alt="<?= e($prod['name']) ?>" loading="lazy">
                        <?php if (!empty($prod['badge'])): ?>
                            <span class="product-badge"><?= e($prod['badge']) ?></span>
                        <?php endif; ?>
                        <?php if ($discountPct > 0): ?>
                            <span class="product-discount-tag">Hemat <?= $discountPct ?>%</span>
                        <?php endif; ?>
                        <span class="product-stock-pill <?= $prod['stock_status'] === 'Tersedia' ? 'in-stock' : 'low-stock' ?>">
                            <?= e($prod['stock_status']) ?>
                        </span>
                    </div>

                    <div class="product-body">
                        <div class="product-meta-row">
                            <span class="product-category"><?= e($prod['category']) ?></span>
                            <span class="product-weight" id="weight-label-<?= $prodId ?>"><?= e($prod['weight']) ?></span>
                        </div>

                        <h3 class="product-title">
                            <a href="javascript:void(0)" onclick="openProductDetailModal(<?= $prodId ?>)">
                                <?= e($prod['name']) ?>
                            </a>
                        </h3>

                        <p class="product-short-desc"><?= e($prod['short_desc']) ?></p>

                        <?php if (!empty($benefitsList)): ?>
                        <ul class="product-benefits-preview">
                            <?php foreach (array_slice($benefitsList, 0, 2) as $ben): ?>
                            <li>✓ <?= e($ben) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <!-- Multi-Size Variant Selector Pills -->
                        <?php if (count($variants) > 1): ?>
                        <div class="variant-selector-box">
                            <span class="variant-label-small">Pilih Kemasan:</span>
                            <div class="variant-pills-row">
                                <?php foreach ($variants as $vIdx => $vItem):
                                    $isDefaultVar = ($vItem['price'] === $price) || ($vIdx === 1 && $price !== $variants[0]['price']);
                                ?>
                                <button type="button"
                                        class="variant-pill <?= $isDefaultVar ? 'active' : '' ?>"
                                        onclick="selectProductVariant(<?= $prodId ?>, '<?= e($vItem['label']) ?>', <?= (int)$vItem['price'] ?>, this)">
                                    <?= e($vItem['label']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="product-price-block">
                            <div class="price-current" id="price-display-<?= $prodId ?>"><?= formatRupiah($price) ?></div>
                            <?php if ($origPrice > $price): ?>
                                <div class="price-original"><?= formatRupiah($origPrice) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons: Add to Cart + Shopee Link + Non-Marketplace CS Chat/Order -->
                        <div class="product-actions-3">
                            <button type="button"
                                    class="btn-prod btn-prod-cart"
                                    onclick="addProductToCartFromCard(<?= $prodId ?>)"
                                    title="Masukkan ke Keranjang Non-Marketplace">
                                🛒 + Keranjang
                            </button>

                            <a href="<?= e($prod['shopee_url']) ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               onclick="trackProductClick(<?= $prodId ?>, 'shopee')"
                               class="btn-prod btn-prod-shopee"
                               title="Beli <?= e($prod['name']) ?> di Marketplace Shopee">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3z"/></svg>
                                <span>Beli di Shopee</span>
                            </a>

                            <button type="button"
                                    class="btn-prod btn-prod-cs"
                                    onclick="openCsOrderModal(<?= $prodId ?>, 'Pesan Non-Marketplace')"
                                    title="Pesan atau Tanya Customer Service secara Non-Marketplace">
                                💬 Pesan / Chat CS
                            </button>
                        </div>

                        <div class="product-sub-actions">
                            <button type="button" class="btn-link-detail" onclick="openProductDetailModal(<?= $prodId ?>)">
                                Lihat Deskripsi Lengkap &amp; Khasiat →
                            </button>
                            <a href="<?= e($directWaUrl) ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               onclick="trackProductClick(<?= $prodId ?>, 'wa')"
                               class="btn-link-wa-direct"
                               title="Langsung buka WhatsApp CS">
                                WA Langsung
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 3B: BUKTI KEASLIAN & SERTIFIKASI LABORATORIUM
         =================================================================== -->
    <?php if (!empty($certificates)): ?>
    <section class="certificates-section" id="certificates">
        <div class="container">
            <div class="section-header-center">
                <span class="section-eyebrow">GARANSI 100% MADU ASLI MURNI</span>
                <h2 class="section-title"><?= e($settings['certs_section_title'] ?? 'Bukti Keaslian & Sertifikasi Laboratorium') ?></h2>
                <p class="section-subtitle"><?= e($settings['certs_section_subtitle'] ?? '') ?></p>
            </div>

            <!-- Lab Parameter Metrics Bar -->
            <div class="lab-metrics-strip">
                <div class="lab-metric-item">
                    <strong>DN &gt; 5.8</strong>
                    <span>Enzim Diastase Aktif (Standar SNI &ge; 3)</span>
                </div>
                <div class="lab-metric-item">
                    <strong>&lt; 19.0%</strong>
                    <span>Kadar Air Panen Matang (Capped Honey)</span>
                </div>
                <div class="lab-metric-item">
                    <strong>&lt; 15 mg/kg</strong>
                    <span>Kadar HMF Rendah (Tanpa Pemanasan)</span>
                </div>
                <div class="lab-metric-item">
                    <strong>0% Sirup</strong>
                    <span>Bebas Gula Rafinasi &amp; Pemanis Buatan</span>
                </div>
            </div>

            <div class="certificates-grid">
                <?php foreach ($certificates as $cert): ?>
                <div class="certificate-card" onclick="openCertificatePreviewModal(<?= (int)$cert['id'] ?>)">
                    <div class="cert-img-wrap">
                        <img src="<?= e($cert['image_url']) ?>" alt="<?= e($cert['title']) ?>" loading="lazy">
                        <span class="cert-zoom-badge">🔍 Klik Perbesar</span>
                    </div>
                    <div class="cert-body">
                        <span class="cert-number-pill"><?= e($cert['cert_number']) ?></span>
                        <h3 class="cert-title"><?= e($cert['title']) ?></h3>
                        <p class="cert-issuer">Diterbitkan oleh: <strong><?= e($cert['issuer']) ?></strong></p>
                        <p class="cert-summary"><?= e($cert['test_summary']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===================================================================
         SECTION 4: SUSTAINABILITY / PURITY BANNER (Modeled after template147 Sec 6)
         =================================================================== -->
    <section class="sustainability-section" id="sustainability">
        <div class="container sustainability-grid">
            <div class="sustainability-visual">
                <div class="sustainability-accent-box"></div>
                <img src="<?= e($settings['sustainability_image'] ?? 'assets/images/sustainable-honey.svg') ?>"
                     alt="<?= e($settings['sustainability_title'] ?? 'Panen Lestari') ?>"
                     class="sustainability-img"
                     loading="lazy">
            </div>
            <div class="sustainability-content">
                <span class="section-eyebrow">STANDAR MUTU &amp; EKOSISTEM</span>
                <h2 class="sustainability-title"><?= e($settings['sustainability_title'] ?? "Sustainable Practices for a Beekeeper's Delight") ?></h2>
                <p class="sustainability-desc"><?= nl2br(e($settings['sustainability_desc'] ?? '')) ?></p>
                <div class="sustainability-cta">
                    <a href="<?= e($settings['shopee_store_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-shopee">
                        Kunjungi Toko Shopee Kami
                    </a>
                    <button type="button" class="btn btn-primary" onclick="openCartModal()">
                        🛒 Buka Keranjang &amp; Promo
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 5: ARTICLES / HEALTH BENEFITS GRID (Modeled after template147 Sec 7-8)
         =================================================================== -->
    <section class="articles-section" id="news">
        <div class="container">
            <div class="section-header-center">
                <span class="section-eyebrow">WAWASAN &amp; MANFAAT KESEHATAN</span>
                <h2 class="section-title"><?= e($settings['articles_section_title'] ?? 'Edukasi, Proses & Manfaat Kesehatan Madu') ?></h2>
                <p class="section-subtitle"><?= e($settings['articles_section_subtitle'] ?? '') ?></p>
            </div>

            <div class="articles-grid">
                <?php foreach ($articles as $art): ?>
                <article class="article-card" onclick="openArticleModal(<?= (int)$art['id'] ?>)">
                    <div class="article-img-wrap">
                        <img src="<?= e($art['image_url']) ?>" alt="<?= e($art['title']) ?>" loading="lazy">
                        <span class="article-cat-tag"><?= e($art['category']) ?></span>
                    </div>
                    <div class="article-body">
                        <h3 class="article-title"><?= e($art['title']) ?></h3>
                        <p class="article-excerpt"><?= e($art['excerpt']) ?></p>
                        <div class="article-footer">
                            <span class="article-meta"><?= e($art['author']) ?> • <?= e($art['published_date']) ?></span>
                            <span class="article-readmore">Baca Artikel →</span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 6: HISTORY & LEGACY (Modeled after template147 Section 9-10)
         =================================================================== -->
    <section class="history-section" id="history">
        <div class="container history-grid">
            <div class="history-left">
                <h5 class="history-eyebrow"><?= e($settings['history_badge'] ?? 'Warisan Kebaikan Alam') ?></h5>
                <h3 class="history-heading"><?= e($settings['history_title'] ?? '') ?></h3>
            </div>
            <div class="history-right">
                <p><?= nl2br(e($settings['history_paragraph_1'] ?? '')) ?></p>
                <p><?= nl2br(e($settings['history_paragraph_2'] ?? '')) ?></p>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 7: SOLID TEAM (Modeled after template147 Section 11-13)
         =================================================================== -->
    <section class="team-section" id="team">
        <div class="container">
            <div class="section-header-center">
                <span class="section-eyebrow">DEDIKASI &amp; KEAHLIAN</span>
                <h2 class="section-title"><?= e($settings['team_section_title'] ?? 'Madu Nusantara Solid Team') ?></h2>
                <p class="section-subtitle"><?= e($settings['team_section_subtitle'] ?? '') ?></p>
            </div>

            <div class="team-grid">
                <?php foreach ($teamMembers as $tm): ?>
                <div class="team-card">
                    <div class="team-img-wrap">
                        <img src="<?= e($tm['image_url']) ?>" alt="<?= e($tm['name']) ?>" loading="lazy">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name"><?= e($tm['name']) ?></h3>
                        <span class="team-role"><?= e($tm['role']) ?></span>
                        <p class="team-bio"><?= e($tm['bio']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 8: CUSTOMER TESTIMONIALS (Shopee & Non-Marketplace Buyers)
         =================================================================== -->
    <?php if (!empty($testimonials)): ?>
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <div class="section-header-center">
                <span class="section-eyebrow">ULASAN PELANGGAN ASLI</span>
                <h2 class="section-title"><?= e($settings['testimonials_section_title'] ?? 'Apa Kata Pelanggan Setia Kami') ?></h2>
                <p class="section-subtitle"><?= e($settings['testimonials_section_subtitle'] ?? '') ?></p>
            </div>

            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testi): ?>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <?= str_repeat('★', max(1, min(5, (int)$testi['rating']))) ?>
                    </div>
                    <p class="testimonial-comment">“<?= e($testi['comment']) ?>”</p>
                    <div class="testimonial-product-pill">Produk: <?= e($testi['product_name']) ?></div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><?= e($testi['avatar_url'] ?: 'MN') ?></div>
                        <div>
                            <h4 class="testimonial-name"><?= e($testi['customer_name']) ?></h4>
                            <span class="testimonial-role"><?= e($testi['customer_role']) ?> • <strong><?= e($testi['order_channel']) ?></strong></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===================================================================
         SECTION 8B: FAQ INTERAKTIF (Tanya Jawab Seputar Madu Asli)
         =================================================================== -->
    <?php if (!empty($faqs)): ?>
    <section class="faq-section" id="faq">
        <div class="container">
            <div class="section-header-center">
                <span class="section-eyebrow">PUSAT EDUKASI &amp; BANTUAN</span>
                <h2 class="section-title"><?= e($settings['faq_section_title'] ?? 'Tanya Jawab Seputar Keaslian & Konsumsi Madu (FAQ)') ?></h2>
                <p class="section-subtitle"><?= e($settings['faq_section_subtitle'] ?? '') ?></p>
            </div>

            <div class="faq-accordion-list">
                <?php foreach ($faqs as $idx => $faq): ?>
                <div class="faq-item <?= $idx === 0 ? 'open' : '' ?>">
                    <button type="button" class="faq-question-btn" onclick="toggleFaqItem(this)">
                        <div>
                            <span class="faq-cat-pill"><?= e($faq['category']) ?></span>
                            <span class="faq-q-text"><?= e($faq['question']) ?></span>
                        </div>
                        <span class="faq-icon"><?= $idx === 0 ? '−' : '+' ?></span>
                    </button>
                    <div class="faq-answer-body">
                        <p><?= nl2br(e($faq['answer'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===================================================================
         SECTION 9: DUAL ORDER CHANNEL BANNER (Shopee vs Non-Marketplace CS)
         =================================================================== -->
    <section class="order-channels-section">
        <div class="container">
            <div class="channels-wrapper">
                <!-- Channel 1: Shopee Marketplace -->
                <div class="channel-box channel-shopee">
                    <span class="channel-tag">MARKETPLACE RESMI</span>
                    <h3>Belanja Mudah via Shopee Official</h3>
                    <p>Nikmati kemudahan klaim Voucher Gratis Ongkir, Cashback Koin Shopee, dan pembayaran COD di toko resmi <strong><?= e($settings['shopee_store_name'] ?? 'Madu Nusantara Official') ?></strong>.</p>
                    <a href="<?= e($settings['shopee_store_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-shopee-large">
                        Buka Toko Shopee Kami →
                    </a>
                </div>

                <!-- Channel 2: Non-Marketplace Customer Service -->
                <div class="channel-box channel-direct">
                    <span class="channel-tag">PEMESANAN NON-MARKETPLACE</span>
                    <h3>Keranjang Multi-Produk &amp; Chat Customer Service</h3>
                    <p>Ingin pesan beberapa varian sekaligus dengan <strong>Kode Voucher Diskon</strong>, konsultasi manfaat madu, atau beli grosir/reseller? Hubungi CS kami<strong> (<?= e($settings['cs_name'] ?? 'Admin CS') ?>)</strong>.</p>
                    <div class="channel-direct-btns">
                        <button type="button" class="btn btn-cs-large" onclick="openCartModal()">
                            🛒 Buka Keranjang Belanja
                        </button>
                        <button type="button" class="btn btn-outline-light" onclick="openCsOrderModal(null, 'Tanya Customer Service')">
                            💬 Konsultasi CS Langsung
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         SECTION 10: LOCATION, RESERVATIONS/CS & OPENING HOURS
         (Modeled directly after template147 Section 14)
         =================================================================== -->
    <section class="contact-info-section" id="contact-us">
        <div class="container contact-info-grid">
            <div class="contact-col">
                <h6 class="contact-eyebrow"><?= e($settings['contact_location_label'] ?? 'Location') ?></h6>
                <h3 class="contact-main-text"><?= e($settings['contact_address'] ?? '') ?></h3>
                <a href="<?= e($settings['contact_maps_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="contact-map-link">
                    View on Google Maps →
                </a>
            </div>

            <div class="contact-col">
                <h6 class="contact-eyebrow"><?= e($settings['contact_reservations_label'] ?? 'Customer Service & Direct Order') ?></h6>
                <h3 class="contact-main-text"><?= e($settings['contact_phone_display'] ?? '+62 812-3456-7890') ?></h3>
                <h3 class="contact-sub-email">
                    <a href="mailto:<?= e($settings['contact_email'] ?? 'halo@madunusantara.co.id') ?>">
                        <?= e($settings['contact_email'] ?? 'halo@madunusantara.co.id') ?>
                    </a>
                </h3>
                <button type="button" class="btn-inline-cs" onclick="openCsOrderModal(null, 'Tanya Customer Service')">
                    Hubungi Customer Service Sekarang
                </button>
            </div>

            <div class="contact-col">
                <h6 class="contact-eyebrow"><?= e($settings['contact_hours_label'] ?? 'Opening Hours') ?></h6>
                <ul class="opening-hours-list">
                    <li><?= e($settings['contact_hours_weekdays'] ?? 'Senin - Jumat: 08:00 - 21:00 WIB') ?></li>
                    <li><?= e($settings['contact_hours_saturday'] ?? 'Sabtu: 08:30 - 22:00 WIB') ?></li>
                    <li><?= e($settings['contact_hours_sunday'] ?? 'Minggu: 09:00 - 17:00 WIB') ?></li>
                </ul>
            </div>
        </div>
    </section>
</main>

<!-- =======================================================================
     FOOTER (Modeled after template147 Section 15 & 16)
     ======================================================================= -->
<footer class="site-footer">
    <div class="container footer-top-grid">
        <div class="footer-brand-col">
            <div class="brand-logo footer-logo">
                <div class="brand-logo-icon">
                    <svg viewBox="0 0 36 36" fill="none">
                        <polygon points="18,2 33,10.5 33,25.5 18,34 3,25.5 3,10.5" fill="var(--secondary)" stroke="#FFF" stroke-width="2"/>
                        <path d="M18,9 C22,15 24,18 24,22 C24,25.3 21.3,28 18,28 C14.7,28 12,25.3 12,22 C12,18 14,15 18,9 Z" fill="#FFFBEB"/>
                    </svg>
                </div>
                <div class="brand-logo-text">
                    <span class="brand-title"><?= e($settings['brand_name'] ?? 'MADU NUSANTARA') ?></span>
                    <span class="brand-sub"><?= e($settings['brand_logo_sub'] ?? 'PURE RAW HONEY') ?></span>
                </div>
            </div>
            <p class="footer-tagline"><?= e($settings['footer_tagline'] ?? '') ?></p>
        </div>

        <div class="footer-links-col">
            <h4>Navigation</h4>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#about-us">About Us</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#certificates">Bukti Uji Lab</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-links-col">
            <h4>Social &amp; Marketplace</h4>
            <ul>
                <li><a href="<?= e($settings['shopee_store_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">Shopee Official Store</a></li>
                <li><a href="<?= e($settings['social_instagram'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">Instagram</a></li>
                <li><a href="<?= e($settings['social_facebook'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">Facebook</a></li>
                <li><a href="<?= e($settings['social_tiktok'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">Tiktok</a></li>
            </ul>
        </div>

        <div class="footer-links-col">
            <h4>Customer Service</h4>
            <ul>
                <li><a href="<?= e($defaultAskWaLink) ?>" target="_blank" rel="noopener noreferrer">WhatsApp: <?= e($settings['contact_phone_display'] ?? '') ?></a></li>
                <li><a href="mailto:<?= e($settings['contact_email'] ?? '') ?>"><?= e($settings['contact_email'] ?? '') ?></a></li>
                <li><a href="javascript:void(0)" onclick="openCartModal()">Keranjang Non-Marketplace</a></li>
            </ul>
        </div>
    </div>

    <!-- Bottom Teal Bar (#298A81) like template147 Section 16 -->
    <div class="footer-bottom-bar">
        <div class="container">
            <p id="secretAdminTrigger" title="Hak Cipta"><?= e($settings['footer_copyright'] ?? '© 2026 Madu Nusantara UMKM.') ?></p>
        </div>
    </div>
</footer>

<!-- =======================================================================
     FLOATING WHATSAPP CS & MINI CART WIDGETS
     ======================================================================= -->
<div class="floating-cs-wrapper">
    <button type="button" class="floating-cart-btn" id="floatingCartBtn" onclick="openCartModal()" style="display:none;" aria-label="Buka Keranjang Belanja">
        <span>🛒 Keranjang (<strong id="floatingCartCount">0</strong>)</span>
    </button>

    <button type="button" class="floating-cs-btn" onclick="openCsOrderModal(null, 'Tanya Customer Service')" aria-label="Chat Customer Service & Pesan Non-Marketplace">
        <svg class="wa-icon" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2ZM12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 15 3.8 13.47 3.8 11.91C3.81 7.37 7.5 3.67 12.05 3.67Z"/>
        </svg>
        <div class="floating-cs-label">
            <span class="cs-status-dot"></span>
            <span>Chat CS / Order</span>
        </div>
    </button>
</div>

<!-- =======================================================================
     MODAL 1: PRODUCT DETAIL MODAL (Modeled after template147/product/powder/)
     ======================================================================= -->
<div class="modal-backdrop" id="productDetailModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle">
        <button type="button" class="modal-close-btn" onclick="closeModal('productDetailModal')" aria-label="Tutup">&times;</button>
        <div class="product-detail-layout" id="productDetailContent"></div>
    </div>
</div>

<!-- =======================================================================
     MODAL 2: MULTI-PRODUCT SHOPPING CART & VOUCHER CHECKOUT MODAL
     ======================================================================= -->
<div class="modal-backdrop" id="cartCheckoutModal" aria-hidden="true">
    <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="cartModalTitle">
        <button type="button" class="modal-close-btn" onclick="closeModal('cartCheckoutModal')" aria-label="Tutup">&times;</button>
        <div class="cs-modal-header">
            <span class="cs-online-badge">🛒 Keranjang Belanja Non-Marketplace</span>
            <h3 id="cartModalTitle">Ringkasan Keranjang &amp; Checkout WhatsApp CS</h3>
            <p>Pilih beberapa varian madu sekaligus, gunakan kode voucher diskon, dan kirim pesanan langsung ke Customer Service kami.</p>
        </div>

        <div id="cartItemsContainer" class="cart-items-container"></div>

        <!-- Promo Voucher Box -->
        <div class="voucher-box" id="cartVoucherBox">
            <label for="voucherCodeInput">🎁 Punya Kode Voucher Promo?</label>
            <div class="voucher-input-row">
                <input type="text" id="voucherCodeInput" placeholder="Masukkan kode (cth: MADUSEHAT10)" style="text-transform:uppercase;">
                <button type="button" class="btn-apply-voucher" onclick="applyCartVoucher()">Pakai Voucher</button>
            </div>
            <?php if (!empty($activeVouchers)): ?>
            <div class="available-vouchers-chips">
                <span>Klik untuk pakai:</span>
                <?php foreach ($activeVouchers as $av): ?>
                <button type="button" class="voucher-chip" onclick="quickFillVoucher('<?= e($av['code']) ?>')">
                    <strong><?= e($av['code']) ?></strong>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div id="voucherFeedback" class="voucher-feedback" style="display:none;"></div>
        </div>

        <!-- Price Summary -->
        <div class="cart-summary-box">
            <div class="cart-sum-row">
                <span>Subtotal Produk:</span>
                <strong id="cartSubtotalText">Rp 0</strong>
            </div>
            <div class="cart-sum-row discount-row" id="cartDiscountRow" style="display:none;">
                <span>Potongan Voucher (<span id="cartVoucherLabel"></span>):</span>
                <strong id="cartDiscountText">-Rp 0</strong>
            </div>
            <div class="cart-sum-row total-row">
                <span>Total Estimasi (Belum Ongkir):</span>
                <strong id="cartFinalTotalText">Rp 0</strong>
            </div>
        </div>

        <form id="cartCheckoutForm" class="cs-order-form">
            <div class="form-row-2">
                <div class="form-group">
                    <label for="cartCustName">Nama Lengkap Penerima *</label>
                    <input type="text" id="cartCustName" name="customer_name" required placeholder="Contoh: Ibu Rina">
                </div>
                <div class="form-group">
                    <label for="cartCustPhone">Nomor WhatsApp / HP *</label>
                    <input type="tel" id="cartCustPhone" name="customer_phone" required placeholder="Contoh: 081234567890">
                </div>
            </div>
            <div class="form-group">
                <label for="cartCustCity">Alamat Lengkap / Kota Kecamatan *</label>
                <input type="text" id="cartCustCity" name="customer_city" required placeholder="Contoh: Jl. Melati No. 10, Kec. Coblong, Kota Bandung">
            </div>
            <div class="form-group">
                <label for="cartCustNote">Catatan Tambahan (Opsional)</label>
                <input type="text" id="cartCustNote" name="message" placeholder="Contoh: Tolong packing kayu / kirim via ekspedisi kargo">
            </div>

            <div id="cartFormFeedback" class="cs-form-feedback" style="display:none;"></div>

            <button type="submit" class="btn btn-cs-submit" id="cartSubmitBtn">
                Checkout &amp; Kirim Rincian Pesanan ke WhatsApp CS →
            </button>
        </form>
    </div>
</div>

<!-- =======================================================================
     MODAL 3: NON-MARKETPLACE CUSTOMER SERVICE CHAT & QUICK ORDER MODAL
     ======================================================================= -->
<div class="modal-backdrop" id="csOrderModal" aria-hidden="true">
    <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="csModalTitle">
        <button type="button" class="modal-close-btn" onclick="closeModal('csOrderModal')" aria-label="Tutup">&times;</button>
        <div class="cs-modal-header">
            <span class="cs-online-badge">● Customer Service Online (<?= e($settings['cs_name'] ?? 'Admin CS') ?>)</span>
            <h3 id="csModalTitle">Layanan Tanya CS &amp; Pemesanan Cepat</h3>
            <p>Silakan pilih jenis layanan di bawah ini. Pesanan atau pertanyaan Anda akan tercatat dan langsung terhubung ke WhatsApp resmi kami.</p>
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= e($defaultAskWaLink) ?>" id="csQuickDirectWaLink" target="_blank" rel="noopener noreferrer" class="btn-link-wa-direct" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:#DCFCE7;color:#166534;font-weight:700;font-size:0.78rem;border:1px solid #86EFAC;">
                    💬 Chat WhatsApp CS Langsung (<?= e($settings['contact_phone_display'] ?? '+62 812-3456-7890') ?>) ↗
                </a>
                <button type="button" onclick="closeModal('csOrderModal'); openCartModal();" style="background:#F0FDFA;color:#134641;border:1px solid rgba(41,138,129,0.3);padding:6px 12px;border-radius:99px;font-size:0.78rem;font-weight:700;cursor:pointer;">
                    🛒 Pindah ke Keranjang Multi-Produk
                </button>
            </div>
        </div>

        <form id="csOrderForm" class="cs-order-form">
            <div class="cs-mode-tabs">
                <label class="cs-mode-tab active" id="tabOrderMode">
                    <input type="radio" name="inquiry_type" value="Pesan Non-Marketplace" checked onchange="toggleCsFormMode(this.value)">
                    <span>🛒 Pesan Cepat (1 Produk)</span>
                </label>
                <label class="cs-mode-tab" id="tabAskMode">
                    <input type="radio" name="inquiry_type" value="Tanya Customer Service" onchange="toggleCsFormMode(this.value)">
                    <span>💬 Tanya / Konsultasi Produk</span>
                </label>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="csCustomerName">Nama Lengkap *</label>
                    <input type="text" id="csCustomerName" name="customer_name" required placeholder="Contoh: Budi Santoso">
                </div>
                <div class="form-group">
                    <label for="csCustomerPhone">Nomor WhatsApp / HP</label>
                    <input type="tel" id="csCustomerPhone" name="customer_phone" placeholder="Contoh: 081234567890">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="csProductSelect">Pilih Produk Madu</label>
                    <select id="csProductSelect" name="product_id" onchange="updateOrderEstimate()">
                        <option value="" data-price="0" data-name="Konsultasi Umum / Semua Varian">-- Semua Varian / Konsultasi Umum --</option>
                        <?php foreach ($products as $prod): ?>
                        <option value="<?= (int)$prod['id'] ?>"
                                data-price="<?= (int)$prod['price'] ?>"
                                data-name="<?= e($prod['name']) ?>">
                            <?= e($prod['name']) ?> (<?= e($prod['weight']) ?>) - <?= formatRupiah((int)$prod['price']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="csQuantityGroup">
                    <label for="csQuantity">Jumlah Botol/Pack</label>
                    <input type="number" id="csQuantity" name="quantity" value="1" min="1" max="500" onchange="updateOrderEstimate()" oninput="updateOrderEstimate()">
                </div>
            </div>

            <div class="form-group">
                <label for="csCustomerCity">Kota / Kecamatan Tujuan Pengiriman</label>
                <input type="text" id="csCustomerCity" name="customer_city" placeholder="Contoh: Kebayoran Baru, Jakarta Selatan">
            </div>

            <div class="cs-estimate-banner" id="csEstimateBanner">
                <span>Estimasi Subtotal Produk:</span>
                <strong id="csEstimateValue">Rp 0</strong>
            </div>

            <div class="form-group">
                <label for="csMessage" id="csMessageLabel">Catatan Pesanan / Pertanyaan untuk CS</label>
                <textarea id="csMessage" name="message" rows="3" placeholder="Tulis pertanyaan atau catatan pengiriman Anda..."></textarea>
            </div>

            <div id="csFormFeedback" class="cs-form-feedback" style="display:none;"></div>

            <div class="cs-form-actions">
                <button type="submit" class="btn btn-cs-submit" id="csSubmitBtn">
                    Kirim &amp; Lanjutkan Chat ke WhatsApp CS →
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =======================================================================
     MODAL 4: ARTICLE & CERTIFICATE READER MODAL
     ======================================================================= -->
<div class="modal-backdrop" id="articleDetailModal" aria-hidden="true">
    <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="articleModalTitle">
        <button type="button" class="modal-close-btn" onclick="closeModal('articleDetailModal')" aria-label="Tutup">&times;</button>
        <div id="articleDetailContent" class="article-detail-content"></div>
    </div>
</div>

<script>
    window.MADU_DATA = {
        waNumber: <?= json_encode($waNumber) ?>,
        shopeeStoreUrl: <?= json_encode($settings['shopee_store_url'] ?? '#') ?>,
        brandName: <?= json_encode($settings['brand_name'] ?? 'Madu Nusantara') ?>,
        products: <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        certificates: <?= json_encode($certificates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        articles: <?= json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        vouchers: <?= json_encode($activeVouchers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
