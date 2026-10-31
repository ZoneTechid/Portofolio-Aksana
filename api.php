<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('X-Robots-Tag: noindex, nofollow');

$db = new Database();
$auth = new AuthManager($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawBody = file_get_contents('php://input');
$jsonBody = $rawBody ? json_decode($rawBody, true) : [];
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

$requestData = array_merge($_GET, $_POST, $jsonBody);
$action = trim((string)($requestData['action'] ?? ''));

function jsonResponse(array $payload, int $statusCode = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =========================================================================
// PUBLIC ACTIONS (Accessible from Landing Page)
// =========================================================================

// 1. Track Product Clicks (Shopee vs WhatsApp CS / Cart)
if ($action === 'track_click' && $method === 'POST') {
    $prodId = (int)($requestData['product_id'] ?? 0);
    $channel = trim((string)($requestData['channel'] ?? 'wa'));
    if ($prodId > 0) {
        $db->incrementProductClick($prodId, $channel);
    }
    jsonResponse(['success' => true]);
}

// 2. Validate Promo Voucher Code
if ($action === 'validate_voucher') {
    $code = trim((string)($requestData['code'] ?? ''));
    $subtotal = max(0, (int)($requestData['subtotal'] ?? 0));
    $res = $db->validateVoucherCode($code, $subtotal);
    $res['success'] = (bool)($res['valid'] ?? false);
    jsonResponse($res, $res['valid'] ? 200 : 400);
}

// 3. Submit Non-Marketplace Order (Single Product OR Multi-Item Shopping Cart) or CS Question
if ($action === 'submit_inquiry' && $method === 'POST') {
    $name = trim((string)($requestData['customer_name'] ?? ''));
    $phone = trim((string)($requestData['customer_phone'] ?? ''));
    $city = trim((string)($requestData['customer_city'] ?? ''));
    $type = trim((string)($requestData['inquiry_type'] ?? 'Pesan Non-Marketplace'));
    $productId = !empty($requestData['product_id']) ? (int)$requestData['product_id'] : null;
    $productName = trim((string)($requestData['product_name'] ?? 'Konsultasi Produk Madu'));
    $qty = max(1, (int)($requestData['quantity'] ?? 1));
    $message = trim((string)($requestData['message'] ?? ''));
    $voucherCode = strtoupper(trim((string)($requestData['voucher_code'] ?? '')));
    $cartItems = $requestData['cart_items'] ?? [];

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Nama lengkap wajib diisi.'], 400);
    }

    $subtotal = 0;
    $totalQty = 0;
    $itemLines = [];

    if (is_array($cartItems) && count($cartItems) > 0) {
        $summaryParts = [];
        foreach ($cartItems as $idx => $ci) {
            $cProdId = (int)($ci['product_id'] ?? 0);
            $cName = trim((string)($ci['name'] ?? 'Produk Madu'));
            $cVariant = trim((string)($ci['variant'] ?? '500gr'));
            $cPrice = max(0, (int)($ci['price'] ?? 0));
            $cQty = max(1, (int)($ci['qty'] ?? 1));
            $lineTotal = $cPrice * $cQty;

            $subtotal += $lineTotal;
            $totalQty += $cQty;
            $summaryParts[] = "{$cQty}x {$cName} ({$cVariant})";
            $num = $idx + 1;
            $itemLines[] = "{$num}. *{$cName}* ({$cVariant}) — {$cQty} x Rp " . number_format($cPrice, 0, ',', '.') . " = *Rp " . number_format($lineTotal, 0, ',', '.') . "*";

            if ($cProdId > 0) {
                $db->incrementProductClick($cProdId, 'wa');
                if (!$productId) {
                    $productId = $cProdId;
                }
            }
        }
        $productName = implode(', ', $summaryParts);
        $qty = $totalQty;
    } else {
        $unitPrice = (int)($requestData['unit_price'] ?? 0);
        if ($productId) {
            $prod = $db->getProductById($productId);
            if ($prod) {
                if ($productName === '' || $productName === 'Konsultasi Produk Madu') {
                    $productName = (string)$prod['name'];
                }
                if ($unitPrice <= 0) {
                    $unitPrice = (int)$prod['price'];
                }
                $db->incrementProductClick($productId, 'wa');
            }
        }
        $subtotal = $unitPrice * $qty;
    }

    // Apply voucher if provided
    $discountAmount = 0;
    if ($voucherCode !== '' && $subtotal > 0) {
        $vCheck = $db->validateVoucherCode($voucherCode, $subtotal);
        if ($vCheck['valid']) {
            $discountAmount = (int)$vCheck['discount_amount'];
        } else {
            $voucherCode = '';
        }
    }

    $totalEstimate = max(0, $subtotal - $discountAmount);

    $inquiryId = $db->createInquiry([
        'customer_name'   => $name,
        'customer_phone'  => $phone ?: '-',
        'customer_city'   => $city ?: '-',
        'inquiry_type'    => $type,
        'product_id'      => $productId,
        'product_name'    => $productName,
        'quantity'        => $qty,
        'voucher_code'    => $voucherCode,
        'discount_amount' => $discountAmount,
        'total_estimate'  => $totalEstimate,
        'message'         => $message,
        'status'          => 'Baru',
    ]);

    $settings = $db->getSettingsMap();
    $waNumber = preg_replace('/[^0-9]/', '', (string)($settings['cs_whatsapp_number'] ?? '6281234567890'));
    if (str_starts_with((string)$waNumber, '0')) {
        $waNumber = '62' . substr((string)$waNumber, 1);
    }

    $formattedSubtotal = 'Rp ' . number_format($subtotal, 0, ',', '.');
    $formattedDiscount = 'Rp ' . number_format($discountAmount, 0, ',', '.');
    $formattedTotal = $totalEstimate > 0 ? 'Rp ' . number_format($totalEstimate, 0, ',', '.') : 'Menunggu Konfirmasi CS';

    if ($type === 'Tanya Customer Service') {
        $waText = "Halo CS *{$settings['brand_name']}*,\n\n"
            . "Saya ingin bertanya mengenai produk:\n"
            . "• *Nama:* {$name}\n"
            . ($city ? "• *Kota:* {$city}\n" : "")
            . "• *Produk:* {$productName}\n"
            . "• *Pertanyaan:* {$message}\n\n"
            . "Mohon informasinya, terima kasih!";
    } else {
        $itemsSection = !empty($itemLines)
            ? "*Rincian Keranjang Belanja:*\n" . implode("\n", $itemLines) . "\n\n"
            : "• *Produk:* {$productName}\n• *Jumlah:* {$qty} pcs\n";

        $voucherLine = ($voucherCode !== '' && $discountAmount > 0)
            ? "• *Subtotal:* {$formattedSubtotal}\n• *Voucher ({$voucherCode}):* -{$formattedDiscount}\n"
            : "";

        $waText = "{$settings['cs_default_order_template']}\n\n"
            . "• *ID Pesanan Web:* #ORD-{$inquiryId}\n"
            . "• *Nama Pemesan:* {$name}\n"
            . ($phone ? "• *No. HP/WA:* {$phone}\n" : "")
            . ($city ? "• *Kota/Alamat:* {$city}\n\n" : "\n")
            . $itemsSection
            . $voucherLine
            . "• *Total Estimasi Produk:* {$formattedTotal}\n"
            . ($message ? "• *Catatan:* {$message}\n" : "")
            . "\nMohon info total beserta ongkos kirimnya Kak. Terima kasih!";
    }

    $waUrl = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waText);

    jsonResponse([
        'success'         => true,
        'message'         => 'Data pesanan berhasil dicatat! Mengalihkan ke WhatsApp Customer Service...',
        'inquiry_id'      => $inquiryId,
        'subtotal'        => $subtotal,
        'discount_amount' => $discountAmount,
        'total_estimate'  => $totalEstimate,
        'wa_url'          => $waUrl,
        'wa_text'         => $waText,
    ]);
}

if ($action === 'get_public_data') {
    jsonResponse([
        'success'      => true,
        'settings'     => $db->getSettingsMap(),
        'products'     => $db->getProducts(),
        'certificates' => $db->getCertificates(),
        'articles'     => $db->getArticles(),
        'features'     => $db->getFeatures(),
        'team'         => $db->getTeamMembers(),
        'testimonials' => $db->getTestimonials(),
        'faqs'         => $db->getFaqs(),
        'vouchers'     => $db->getVouchers(),
    ]);
}

// =========================================================================
// ADMIN AUTHENTICATION CHECK FOR ALL CRUD OPERATIONS
// =========================================================================
if (!$auth->isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'message' => 'Akses ditolak! Silakan login ke Dashboard Admin terlebih dahulu.',
    ], 401);
}

// Export CSV (GET allowed when logged in)
if ($action === 'export_inquiries_csv') {
    $rows = $db->getInquiries();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Laporan-Pesanan-Madu-Nusantara-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID Pesanan', 'Tanggal', 'Nama Pelanggan', 'No HP / WA', 'Kota Tujuan', 'Tipe Layanan', 'Rincian Produk', 'Jumlah Qty', 'Kode Voucher', 'Diskon (Rp)', 'Total Estimasi (Rp)', 'Status', 'Catatan Pesan']);
    foreach ($rows as $r) {
        fputcsv($out, [
            '#ORD-' . $r['id'],
            $r['created_at'],
            $r['customer_name'],
            $r['customer_phone'],
            $r['customer_city'],
            $r['inquiry_type'],
            $r['product_name'],
            $r['quantity'],
            $r['voucher_code'] ?? '',
            $r['discount_amount'] ?? 0,
            $r['total_estimate'],
            $r['status'],
            $r['message'],
        ]);
    }
    fclose($out);
    exit;
}

// Verify CSRF token for state-mutating requests
if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    $csrfToken = (string)($requestData['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$auth->verifyCsrfToken($csrfToken)) {
        jsonResponse([
            'success' => false,
            'message' => 'Token keamanan CSRF tidak valid atau kadaluarsa. Silakan muat ulang halaman.',
        ], 403);
    }
}

try {
    switch ($action) {
        // -----------------------------------------------------------------
        // 1. SITE SETTINGS / LANDING PAGE CONTENT (CRUD)
        // -----------------------------------------------------------------
        case 'get_settings':
            jsonResponse([
                'success'  => true,
                'settings' => $db->getSettingsMap(),
                'detailed' => $db->getAllSettingsDetailed(),
            ]);

        case 'save_settings':
            $settingsPayload = $requestData['settings'] ?? [];
            if (!is_array($settingsPayload)) {
                jsonResponse(['success' => false, 'message' => 'Format data pengaturan tidak valid.'], 400);
            }
            foreach ($settingsPayload as $key => $val) {
                $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
                if ($cleanKey !== '') {
                    $db->upsertSetting($cleanKey, (string)$val);
                }
            }
            jsonResponse([
                'success'  => true,
                'message'  => 'Seluruh informasi Landing Page berhasil disimpan!',
                'settings' => $db->getSettingsMap(),
            ]);

        case 'create_setting':
            $key = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim((string)($requestData['setting_key'] ?? ''))));
            $val = (string)($requestData['setting_value'] ?? '');
            $group = trim((string)($requestData['group_name'] ?? 'general'));
            $label = trim((string)($requestData['label'] ?? $key));
            $type = trim((string)($requestData['field_type'] ?? 'text'));
            if ($key === '') {
                jsonResponse(['success' => false, 'message' => 'Kunci pengaturan (setting_key) wajib diisi.'], 400);
            }
            $db->upsertSetting($key, $val, $group, $label, $type);
            jsonResponse([
                'success' => true,
                'message' => "Informasi '{$label}' berhasil ditambahkan!",
            ]);

        case 'delete_setting':
            $key = trim((string)($requestData['setting_key'] ?? ''));
            if ($key === '') {
                jsonResponse(['success' => false, 'message' => 'Kunci pengaturan tidak ditemukan.'], 400);
            }
            $db->deleteSetting($key);
            jsonResponse([
                'success' => true,
                'message' => "Pengaturan '{$key}' berhasil dihapus.",
            ]);

        case 'reset_defaults':
            $db->seedDefaultsIfEmpty(true);
            jsonResponse([
                'success' => true,
                'message' => 'Seluruh konten Landing Page berhasil dikembalikan ke pengaturan awal (default)!',
            ]);

        // -----------------------------------------------------------------
        // 2. PRODUCTS CRUD (Create, Read, Update, Delete)
        // -----------------------------------------------------------------
        case 'get_products':
            jsonResponse(['success' => true, 'products' => $db->getProducts()]);

        case 'get_product':
            $id = (int)($requestData['id'] ?? 0);
            $prod = $db->getProductById($id);
            if (!$prod) {
                jsonResponse(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'product' => $prod]);

        case 'create_product':
            if (empty(trim((string)($requestData['name'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Nama produk wajib diisi.'], 400);
            }
            $newId = $db->createProduct($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Produk baru berhasil ditambahkan ke Landing Page!',
                'id'      => $newId,
                'product' => $db->getProductById($newId),
            ]);

        case 'update_product':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateProduct($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui produk. ID tidak valid.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Informasi produk berhasil diperbarui!',
                'product' => $db->getProductById($id),
            ]);

        case 'delete_product':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteProduct($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus produk.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Produk berhasil dihapus dari Landing Page!',
            ]);

        // -----------------------------------------------------------------
        // 3. CERTIFICATES & LAB PROOFS CRUD
        // -----------------------------------------------------------------
        case 'get_certificates':
            jsonResponse(['success' => true, 'certificates' => $db->getCertificates()]);

        case 'create_certificate':
            if (empty(trim((string)($requestData['title'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Nama sertifikat/uji lab wajib diisi.'], 400);
            }
            $newId = $db->createCertificate($requestData);
            jsonResponse([
                'success'     => true,
                'message'     => 'Bukti sertifikat & uji lab berhasil ditambahkan!',
                'id'          => $newId,
                'certificate' => $db->getCertificateById($newId),
            ]);

        case 'update_certificate':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateCertificate($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui sertifikat.'], 400);
            }
            jsonResponse([
                'success'     => true,
                'message'     => 'Data sertifikat berhasil diperbarui!',
                'certificate' => $db->getCertificateById($id),
            ]);

        case 'delete_certificate':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteCertificate($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus sertifikat.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Sertifikat berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 4. FAQS CRUD
        // -----------------------------------------------------------------
        case 'get_faqs':
            jsonResponse(['success' => true, 'faqs' => $db->getFaqs()]);

        case 'create_faq':
            if (empty(trim((string)($requestData['question'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Pertanyaan FAQ wajib diisi.'], 400);
            }
            $newId = $db->createFaq($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Pertanyaan FAQ baru berhasil ditambahkan!',
                'id'      => $newId,
                'faq'     => $db->getFaqById($newId),
            ]);

        case 'update_faq':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateFaq($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui FAQ.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'FAQ berhasil diperbarui!',
                'faq'     => $db->getFaqById($id),
            ]);

        case 'delete_faq':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteFaq($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus FAQ.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'FAQ berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 5. PROMO VOUCHERS CRUD
        // -----------------------------------------------------------------
        case 'get_vouchers':
            jsonResponse(['success' => true, 'vouchers' => $db->getVouchers()]);

        case 'create_voucher':
            if (empty(trim((string)($requestData['code'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Kode voucher wajib diisi.'], 400);
            }
            $newId = $db->createVoucher($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Kode voucher promo berhasil ditambahkan!',
                'id'      => $newId,
                'voucher' => $db->getVoucherById($newId),
            ]);

        case 'update_voucher':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateVoucher($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui voucher.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Voucher berhasil diperbarui!',
                'voucher' => $db->getVoucherById($id),
            ]);

        case 'delete_voucher':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteVoucher($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus voucher.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Voucher berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 6. ARTICLES / EDUCATION CARDS CRUD
        // -----------------------------------------------------------------
        case 'get_articles':
            jsonResponse(['success' => true, 'articles' => $db->getArticles()]);

        case 'get_article':
            $id = (int)($requestData['id'] ?? 0);
            $art = $db->getArticleById($id);
            if (!$art) {
                jsonResponse(['success' => false, 'message' => 'Artikel tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'article' => $art]);

        case 'create_article':
            if (empty(trim((string)($requestData['title'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Judul artikel wajib diisi.'], 400);
            }
            $newId = $db->createArticle($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Artikel & edukasi baru berhasil ditambahkan!',
                'id'      => $newId,
                'article' => $db->getArticleById($newId),
            ]);

        case 'update_article':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateArticle($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui artikel.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Artikel berhasil diperbarui!',
                'article' => $db->getArticleById($id),
            ]);

        case 'delete_article':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteArticle($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus artikel.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Artikel berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 7. FEATURES / HIGHLIGHTS CRUD
        // -----------------------------------------------------------------
        case 'get_features':
            jsonResponse(['success' => true, 'features' => $db->getFeatures()]);

        case 'get_feature':
            $id = (int)($requestData['id'] ?? 0);
            $feat = $db->getFeatureById($id);
            if (!$feat) {
                jsonResponse(['success' => false, 'message' => 'Item keunggulan tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'feature' => $feat]);

        case 'create_feature':
            if (empty(trim((string)($requestData['title'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Judul keunggulan wajib diisi.'], 400);
            }
            $newId = $db->createFeature($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Poin keunggulan berhasil ditambahkan!',
                'id'      => $newId,
                'feature' => $db->getFeatureById($newId),
            ]);

        case 'update_feature':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateFeature($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui poin keunggulan.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Poin keunggulan berhasil diperbarui!',
                'feature' => $db->getFeatureById($id),
            ]);

        case 'delete_feature':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteFeature($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus poin keunggulan.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Poin keunggulan berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 8. TEAM MEMBERS CRUD
        // -----------------------------------------------------------------
        case 'get_team':
            jsonResponse(['success' => true, 'team' => $db->getTeamMembers()]);

        case 'get_team_member':
            $id = (int)($requestData['id'] ?? 0);
            $member = $db->getTeamMemberById($id);
            if (!$member) {
                jsonResponse(['success' => false, 'message' => 'Anggota tim tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'member' => $member]);

        case 'create_team':
            if (empty(trim((string)($requestData['name'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Nama anggota tim wajib diisi.'], 400);
            }
            $newId = $db->createTeamMember($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Anggota tim baru berhasil ditambahkan!',
                'id'      => $newId,
                'member'  => $db->getTeamMemberById($newId),
            ]);

        case 'update_team':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateTeamMember($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui anggota tim.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Data anggota tim berhasil diperbarui!',
                'member'  => $db->getTeamMemberById($id),
            ]);

        case 'delete_team':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteTeamMember($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus anggota tim.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Anggota tim berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 9. TESTIMONIALS CRUD
        // -----------------------------------------------------------------
        case 'get_testimonials':
            jsonResponse(['success' => true, 'testimonials' => $db->getTestimonials()]);

        case 'get_testimonial':
            $id = (int)($requestData['id'] ?? 0);
            $testi = $db->getTestimonialById($id);
            if (!$testi) {
                jsonResponse(['success' => false, 'message' => 'Testimoni tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'testimonial' => $testi]);

        case 'create_testimonial':
            if (empty(trim((string)($requestData['customer_name'] ?? '')))) {
                jsonResponse(['success' => false, 'message' => 'Nama pelanggan wajib diisi.'], 400);
            }
            $newId = $db->createTestimonial($requestData);
            jsonResponse([
                'success'     => true,
                'message'     => 'Testimoni pelanggan berhasil ditambahkan!',
                'id'          => $newId,
                'testimonial' => $db->getTestimonialById($newId),
            ]);

        case 'update_testimonial':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateTestimonial($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui testimoni.'], 400);
            }
            jsonResponse([
                'success'     => true,
                'message'     => 'Testimoni berhasil diperbarui!',
                'testimonial' => $db->getTestimonialById($id),
            ]);

        case 'delete_testimonial':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteTestimonial($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus testimoni.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Testimoni berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 10. INQUIRIES / NON-MARKETPLACE ORDERS CRUD
        // -----------------------------------------------------------------
        case 'get_inquiries':
            jsonResponse(['success' => true, 'inquiries' => $db->getInquiries()]);

        case 'get_inquiry':
            $id = (int)($requestData['id'] ?? 0);
            $inq = $db->getInquiryById($id);
            if (!$inq) {
                jsonResponse(['success' => false, 'message' => 'Data pesanan/pertanyaan tidak ditemukan.'], 404);
            }
            jsonResponse(['success' => true, 'inquiry' => $inq]);

        case 'create_inquiry_admin':
            $newId = $db->createInquiry($requestData);
            jsonResponse([
                'success' => true,
                'message' => 'Catatan pesanan non-marketplace berhasil ditambahkan!',
                'id'      => $newId,
                'inquiry' => $db->getInquiryById($newId),
            ]);

        case 'update_inquiry':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->updateInquiry($id, $requestData)) {
                jsonResponse(['success' => false, 'message' => 'Gagal memperbarui status pesanan.'], 400);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Data pesanan/pertanyaan berhasil diperbarui!',
                'inquiry' => $db->getInquiryById($id),
            ]);

        case 'delete_inquiry':
            $id = (int)($requestData['id'] ?? 0);
            if ($id <= 0 || !$db->deleteInquiry($id)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menghapus data pesanan.'], 400);
            }
            jsonResponse(['success' => true, 'message' => 'Data pesanan/pertanyaan berhasil dihapus!']);

        // -----------------------------------------------------------------
        // 11. IMAGE UPLOAD HANDLER
        // -----------------------------------------------------------------
        case 'upload_image':
            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'message' => 'Tidak ada file gambar valid yang diunggah.'], 400);
            }
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
            if (!in_array($ext, $allowedExts, true)) {
                jsonResponse(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, GIF, atau SVG.'], 400);
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                jsonResponse(['success' => false, 'message' => 'Ukuran file maksimal 5MB.'], 400);
            }

            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $safeName = 'madu-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file gambar.'], 500);
            }

            jsonResponse([
                'success'   => true,
                'message'   => 'Gambar berhasil diunggah!',
                'image_url' => 'uploads/' . $safeName,
            ]);

        // -----------------------------------------------------------------
        // 12. ADMIN CREDENTIALS & ENCRYPTED PASSWORD UPDATE
        // -----------------------------------------------------------------
        case 'update_credentials':
            $admin = $auth->getCurrentAdmin();
            if (!$admin) {
                jsonResponse(['success' => false, 'message' => 'Sesi admin tidak ditemukan.'], 401);
            }
            $currentPass = (string)($requestData['current_password'] ?? '');
            $newUsername = trim((string)($requestData['username'] ?? $admin['username']));
            $newDisplay  = trim((string)($requestData['display_name'] ?? $admin['display_name']));
            $newPass     = (string)($requestData['new_password'] ?? '');
            $confirmPass = (string)($requestData['confirm_password'] ?? '');

            if ($newPass !== '' && $newPass !== $confirmPass) {
                jsonResponse(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok!'], 400);
            }

            $res = $auth->updateAdminCredentials(
                (int)$admin['id'],
                $currentPass,
                $newUsername,
                $newDisplay,
                $newPass !== '' ? $newPass : null
            );

            jsonResponse($res, $res['success'] ? 200 : 400);

        default:
            jsonResponse(['success' => false, 'message' => "Aksi API '{$action}' tidak dikenali."], 400);
    }
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
    ], 500);
}
