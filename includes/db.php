<?php
declare(strict_types=1);

require_once __DIR__ . '/crypto.php';

class Database
{
    private PDO $pdo;
    private SecurityCrypto $crypto;
    private string $dbPath;

    public function __construct(?string $customDbPath = null, ?SecurityCrypto $customCrypto = null)
    {
        $this->dbPath = $customDbPath ?? (__DIR__ . '/../data/database.sqlite');
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $this->crypto = $customCrypto ?? new SecurityCrypto();
        $this->pdo = new PDO('sqlite:' . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->pdo->exec('PRAGMA journal_mode = WAL;');

        $this->initializeSchema();
        $this->seedDefaultsIfEmpty();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getCrypto(): SecurityCrypto
    {
        return $this->crypto;
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                encrypted_password TEXT NOT NULL,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT DEFAULT NULL,
                last_login TEXT DEFAULT NULL,
                password_updated_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS site_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL,
                group_name TEXT NOT NULL DEFAULT 'general',
                label TEXT NOT NULL DEFAULT '',
                field_type TEXT NOT NULL DEFAULT 'text',
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'Madu Murni',
                badge TEXT NOT NULL DEFAULT '100% Raw Honey',
                weight TEXT NOT NULL DEFAULT '500gr',
                price INTEGER NOT NULL DEFAULT 95000,
                original_price INTEGER NOT NULL DEFAULT 120000,
                variants_text TEXT NOT NULL DEFAULT '',
                short_desc TEXT NOT NULL,
                full_desc TEXT NOT NULL,
                benefits TEXT NOT NULL DEFAULT '',
                origin TEXT NOT NULL DEFAULT 'Hutan Nusantara',
                image_url TEXT NOT NULL,
                shopee_url TEXT NOT NULL,
                wa_custom_message TEXT NOT NULL DEFAULT '',
                stock_status TEXT NOT NULL DEFAULT 'Tersedia',
                shopee_clicks INTEGER NOT NULL DEFAULT 0,
                wa_clicks INTEGER NOT NULL DEFAULT 0,
                is_featured INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'Edukasi & Panen',
                excerpt TEXT NOT NULL,
                content TEXT NOT NULL,
                image_url TEXT NOT NULL,
                author TEXT NOT NULL DEFAULT 'Tim Ahli Madu Nusantara',
                published_date TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS features (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                icon TEXT NOT NULL DEFAULT 'shield-check',
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                badge_value TEXT NOT NULL DEFAULT '100%',
                sort_order INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS team_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                role TEXT NOT NULL,
                bio TEXT NOT NULL,
                image_url TEXT NOT NULL,
                instagram_url TEXT NOT NULL DEFAULT '#',
                sort_order INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS testimonials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                customer_role TEXT NOT NULL,
                rating INTEGER NOT NULL DEFAULT 5,
                comment TEXT NOT NULL,
                product_name TEXT NOT NULL DEFAULT 'Madu Hutan Liar Sumbawa',
                order_channel TEXT NOT NULL DEFAULT 'Shopee Official Store',
                avatar_url TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS inquiries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                customer_phone TEXT NOT NULL,
                customer_city TEXT NOT NULL DEFAULT '',
                inquiry_type TEXT NOT NULL DEFAULT 'Pesan Non-Marketplace',
                product_id INTEGER DEFAULT NULL,
                product_name TEXT NOT NULL DEFAULT 'Umum / Konsultasi',
                quantity INTEGER NOT NULL DEFAULT 1,
                voucher_code TEXT NOT NULL DEFAULT '',
                discount_amount INTEGER NOT NULL DEFAULT 0,
                total_estimate INTEGER NOT NULL DEFAULT 0,
                message TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'Baru',
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS certificates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                cert_number TEXT NOT NULL,
                issuer TEXT NOT NULL,
                test_summary TEXT NOT NULL,
                image_url TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS faqs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'Keaslian & Konsumsi',
                sort_order INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS vouchers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                discount_type TEXT NOT NULL DEFAULT 'percent',
                discount_value INTEGER NOT NULL DEFAULT 10,
                min_purchase INTEGER NOT NULL DEFAULT 100000,
                description TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1
            );
        ");

        // Safe column migration for existing SQLite databases
        $prodCols = array_column($this->pdo->query("PRAGMA table_info(products)")->fetchAll(), 'name');
        if (!in_array('variants_text', $prodCols, true)) {
            $this->pdo->exec("ALTER TABLE products ADD COLUMN variants_text TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('shopee_clicks', $prodCols, true)) {
            $this->pdo->exec("ALTER TABLE products ADD COLUMN shopee_clicks INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('wa_clicks', $prodCols, true)) {
            $this->pdo->exec("ALTER TABLE products ADD COLUMN wa_clicks INTEGER NOT NULL DEFAULT 0");
        }

        $inqCols = array_column($this->pdo->query("PRAGMA table_info(inquiries)")->fetchAll(), 'name');
        if (!in_array('voucher_code', $inqCols, true)) {
            $this->pdo->exec("ALTER TABLE inquiries ADD COLUMN voucher_code TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('discount_amount', $inqCols, true)) {
            $this->pdo->exec("ALTER TABLE inquiries ADD COLUMN discount_amount INTEGER NOT NULL DEFAULT 0");
        }
    }

    public function seedDefaultsIfEmpty(bool $forceReset = false): void
    {
        if ($forceReset) {
            $this->pdo->exec("
                DELETE FROM site_settings;
                DELETE FROM products;
                DELETE FROM articles;
                DELETE FROM features;
                DELETE FROM team_members;
                DELETE FROM testimonials;
                DELETE FROM certificates;
                DELETE FROM faqs;
                DELETE FROM vouchers;
            ");
        }

        $now = date('Y-m-d H:i:s');

        // 1. Default Admin Account (Username: admin, Password: MaduAdmin#2026)
        $adminCount = (int)$this->pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($adminCount === 0) {
            $encPass = $this->crypto->createEncryptedPasswordHash('MaduAdmin#2026');
            $stmt = $this->pdo->prepare("
                INSERT INTO admins (username, display_name, encrypted_password, password_updated_at, created_at)
                VALUES (:u, :d, :p, :u_at, :c_at)
            ");
            $stmt->execute([
                ':u'    => 'admin',
                ':d'    => 'Administrator Madu Nusantara',
                ':p'    => $encPass,
                ':u_at' => $now,
                ':c_at' => $now,
            ]);
        }

        // 2. Default Site Settings
        $settingsCount = (int)$this->pdo->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
        if ($settingsCount === 0) {
            $defaultSettings = [
                // Branding & Theme
                ['brand_name', 'MADU NUSANTARA', 'branding', 'Nama Brand UMKM', 'text'],
                ['brand_tagline', 'The Sweet Science of Pure Natural Honey', 'branding', 'Tagline Brand', 'text'],
                ['brand_logo_sub', 'PURE RAW HONEY EST. 2019', 'branding', 'Sub-teks Logo', 'text'],
                ['primary_color', '#298A81', 'branding', 'Warna Utama (Teal WebEkspor)', 'color'],
                ['secondary_color', '#C2884F', 'branding', 'Warna Sekunder (Honey Amber Gold)', 'color'],
                ['cream_bg_color', '#FCF6EF', 'branding', 'Warna Latar Krem Hangat', 'color'],

                // Hero Section (Section 1 of template147)
                ['hero_badge', '100% MADU MURNI ALAMI TANPA CAMPURAN', 'hero', 'Badge Hero', 'text'],
                ['hero_title', 'The Sweet Science of Pure Honey', 'hero', 'Judul Utama Hero (H1)', 'text'],
                ['hero_subtitle', 'Menjelajahi Kemurnian & Khasiat Kesehatan Madu Hutan serta Ternak Unggulan Nusantara', 'hero', 'Subjudul Hero (H3)', 'textarea'],
                ['hero_bg_image', 'assets/images/hero-honey.svg', 'hero', 'Gambar Background Hero', 'image'],
                ['hero_btn_primary_text', 'Lihat Semua Produk', 'hero', 'Teks Tombol Utama Hero', 'text'],
                ['hero_btn_shopee_text', 'Official Store Shopee', 'hero', 'Teks Tombol Shopee Hero', 'text'],
                ['hero_btn_cs_text', 'Chat Customer Service', 'hero', 'Teks Tombol CS Hero', 'text'],

                // About Section (Section 2 of template147)
                ['about_title', 'About', 'about', 'Judul Seksi Tentang Kami', 'text'],
                ['about_highlight', 'Madu murni kaya akan enzim diastase aktif, antioksidan flavonoid, dan nutrisi alami yang menjaga imunitas tubuh dari radikal bebas.', 'about', 'Teks Highlight Tebal (H5)', 'textarea'],
                ['about_description', 'Madu telah dikenal selama berabad-abad sebagai cairan emas keajaiban alam. Namun tahukah Anda bahwa madu mentah (raw unprocessed honey) yang dipanen tanpa pemanasan suhu tinggi menyimpan khasiat kesehatan berlipat ganda? Kami menghadirkan kemurnian madu langsung dari peternak lebah lokal dan hutan tropis Indonesia dengan kualitas standar ekspor.', 'about', 'Deskripsi Utama Tentang Kami', 'textarea'],
                ['about_image', 'assets/images/about-honey.svg', 'about', 'Gambar Seksi Tentang Kami', 'image'],
                ['about_cert_badge', 'Lulus Uji Lab SNI & Tersertifikasi Halal Indonesia', 'about', 'Badge Sertifikasi Tentang Kami', 'text'],

                // Product Special Section Header (Section 3 of template147)
                ['products_section_title', 'Product Special', 'products', 'Judul Seksi Produk', 'text'],
                ['products_section_subtitle', 'Pilihan varian madu murni terbaik dengan karakter nektar, rasa, dan manfaat kesehatan spesifik untuk keluarga Anda. Tersedia melalui Shopee maupun pemesanan keranjang langsung via Customer Service.', 'products', 'Subjudul Seksi Produk', 'textarea'],

                // Certificates / Lab Proof Section Header
                ['certs_section_title', 'Bukti Keaslian & Sertifikasi Laboratorium', 'certificates', 'Judul Seksi Sertifikat & Uji Lab', 'text'],
                ['certs_section_subtitle', 'Transparansi kualitas adalah komitmen utama kami. Setiap batch panen Madu Nusantara diuji secara berkala di laboratorium terakreditasi untuk menjamin 100% kemurnian tanpa campuran sirup gula.', 'certificates', 'Subjudul Seksi Sertifikat & Uji Lab', 'textarea'],

                // Sustainability / Purity Highlight Banner (Section 6 of template147)
                ['sustainability_title', 'Praktik Panen Lestari untuk Kemurnian Madu Terbaik', 'sustainability', 'Judul Banner Panen Lestari', 'text'],
                ['sustainability_desc', 'Lebah madu adalah penjaga ekosistem hutan dan pertanian Nusantara. Kami menerapkan metode panen lestari (sustainable beekeeping) pada saat sarang madu telah tertutup lilin sempurna (capped honey), kemudian disaring secara dingin (cold-filtered) agar seluruh enzim, royal jelly, dan bee pollen tetap utuh terjaga.', 'sustainability', 'Deskripsi Banner Panen Lestari', 'textarea'],
                ['sustainability_image', 'assets/images/sustainable-honey.svg', 'sustainability', 'Gambar Banner Panen Lestari', 'image'],

                // Articles / Education Section Header (Section 7-8 of template147)
                ['articles_section_title', 'Edukasi, Proses & Manfaat Kesehatan Madu', 'articles', 'Judul Seksi Artikel & Edukasi', 'text'],
                ['articles_section_subtitle', 'Mengenal lebih dekat perjalanan tetesan madu murni dari nektar bunga pilihan hingga sampai ke meja makan Anda.', 'articles', 'Subjudul Seksi Artikel & Edukasi', 'textarea'],

                // History / Legacy Section (Section 9 of template147)
                ['history_badge', 'Warisan Kebaikan Alam: Jejak Kemurnian Madu Nusantara', 'history', 'Sub-judul Seksi Sejarah (H5)', 'text'],
                ['history_title', 'Madu murni Nusantara memiliki sejarah panjang sebagai eliksir kesehatan keluarga lintas generasi dan budaya.', 'history', 'Judul Utama Seksi Sejarah (H3)', 'textarea'],
                ['history_paragraph_1', 'Sejak zaman kerajaan Nusantara, madu hutan liar dari pedalaman Sumbawa, Sumatera, dan Kalimantan hingga madu klanceng (Trigona) di Jawa telah menjadi ramuan utama penjaga stamina dan pemulihan kesehatan alami. Kami merawat warisan tersebut dengan menghubungkan peternak lebah tradisional langsung ke konsumen modern.', 'history', 'Paragraf 1 Seksi Sejarah', 'textarea'],
                ['history_paragraph_2', 'Setiap batch panen melewati tahap pengecekan kadar air menggunakan refraktometer digital (di bawah 19%–21%) untuk mencegah fermentasi berlebih, serta dikemas secara higienis dalam botol food-grade bersegel rapat yang menghasilkan kejernihan keemasan dan aroma nektar bunga asli yang memikat.', 'history', 'Paragraf 2 Seksi Sejarah', 'textarea'],

                // Team Section Header (Section 11 of template147)
                ['team_section_title', 'Madu Nusantara Solid Team', 'team', 'Judul Seksi Tim Kami', 'text'],
                ['team_section_subtitle', 'Dibalik setiap botol madu murni yang Anda terima, terdapat dedikasi tim ahli perlebahan, pengendali mutu, dan layanan pelanggan kami.', 'team', 'Subjudul Seksi Tim Kami', 'textarea'],

                // Testimonials Section Header
                ['testimonials_section_title', 'Apa Kata Pelanggan Setia Kami', 'testimonials', 'Judul Seksi Testimoni', 'text'],
                ['testimonials_section_subtitle', 'Ribuan keluarga Indonesia telah merasakan manfaat kemurnian Madu Nusantara baik melalui Official Store Shopee maupun pemesanan langsung.', 'testimonials', 'Subjudul Seksi Testimoni', 'textarea'],

                // FAQ Section Header
                ['faq_section_title', 'Tanya Jawab Seputar Keaslian & Konsumsi Madu (FAQ)', 'faq', 'Judul Seksi FAQ', 'text'],
                ['faq_section_subtitle', 'Jawaban lengkap dari tim ahli perlebahan kami atas pertanyaan yang paling sering diajukan calon pembeli.', 'faq', 'Subjudul Seksi FAQ', 'textarea'],

                // Marketplace Shopee & Customer Service Non-Marketplace Settings
                ['shopee_store_url', 'https://shopee.co.id/madunusantara.official', 'marketplace', 'Link Toko Utama Shopee', 'url'],
                ['shopee_store_name', 'Madu Nusantara Official Shop', 'marketplace', 'Nama Toko di Shopee', 'text'],
                ['shopee_rating_badge', '4.9/5.0 (12.4RB+ Penilaian di Shopee)', 'marketplace', 'Badge Rating Shopee', 'text'],
                ['cs_whatsapp_number', '6281234567890', 'marketplace', 'Nomor WhatsApp Customer Service (Format 628...)', 'text'],
                ['cs_name', 'Admin CS Madu Nusantara (Kak Sari)', 'marketplace', 'Nama Customer Service', 'text'],
                ['cs_default_ask_template', 'Halo Customer Service Madu Nusantara, saya ingin bertanya mengenai produk madu murni yang tersedia di website.', 'marketplace', 'Template Pesan Tanya CS Umum', 'textarea'],
                ['cs_default_order_template', 'Halo CS Madu Nusantara, saya ingin memesan produk secara langsung (Non-Marketplace):', 'marketplace', 'Template Awal Pesanan Non-Marketplace', 'textarea'],

                // Contact, Location & Opening Hours (Section 14-16 of template147)
                ['contact_location_label', 'Location', 'contact', 'Label Lokasi', 'text'],
                ['contact_address', 'Jl. Madu Perhutani No. 88, Kawasan Agrowisata, Kota Bogor, Jawa Barat 16128', 'contact', 'Alamat Lengkap UMKM', 'textarea'],
                ['contact_maps_url', 'https://maps.google.com/?q=Kota+Bogor+Jawa+Barat', 'contact', 'Link Google Maps', 'url'],
                ['contact_reservations_label', 'Customer Service & Direct Order', 'contact', 'Label Reservasi / Layanan CS', 'text'],
                ['contact_phone_display', '+62 812-3456-7890', 'contact', 'Nomor Telepon Tampil', 'text'],
                ['contact_email', 'halo@madunusantara.co.id', 'contact', 'Alamat Email Resmi', 'text'],
                ['contact_hours_label', 'Opening Hours', 'contact', 'Label Jam Operasional', 'text'],
                ['contact_hours_weekdays', 'Senin - Jumat: 08:00 - 21:00 WIB', 'contact', 'Jam Operasional Hari Kerja', 'text'],
                ['contact_hours_saturday', 'Sabtu: 08:30 - 22:00 WIB', 'contact', 'Jam Operasional Hari Sabtu', 'text'],
                ['contact_hours_sunday', 'Minggu & Hari Libur: 09:00 - 17:00 WIB', 'contact', 'Jam Operasional Hari Minggu', 'text'],
                ['social_instagram', 'https://instagram.com/madunusantara.id', 'contact', 'Link Instagram', 'url'],
                ['social_facebook', 'https://facebook.com/madunusantara.id', 'contact', 'Link Facebook', 'url'],
                ['social_tiktok', 'https://tiktok.com/@madunusantara.id', 'contact', 'Link TikTok', 'url'],
                ['footer_tagline', 'Menghadirkan kemurnian madu asli Indonesia langsung dari sarang lebah hutan dan peternakan lestari untuk kesehatan keluarga Anda.', 'contact', 'Deskripsi Footer', 'textarea'],
                ['footer_copyright', '© 2026 Madu Nusantara UMKM. Inspired by WebEkspor Design. All Rights Reserved.', 'contact', 'Teks Hak Cipta Footer', 'text'],
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value, group_name, label, field_type, updated_at)
                VALUES (:k, :v, :g, :l, :t, :u)
            ");
            foreach ($defaultSettings as $row) {
                $stmt->execute([
                    ':k' => $row[0],
                    ':v' => $row[1],
                    ':g' => $row[2],
                    ':l' => $row[3],
                    ':t' => $row[4],
                    ':u' => $now,
                ]);
            }
        }

        // Ensure newly added settings exist even if site_settings was already seeded earlier
        $extraSettings = [
            ['certs_section_title', 'Bukti Keaslian & Sertifikasi Laboratorium', 'certificates', 'Judul Seksi Sertifikat & Uji Lab', 'text'],
            ['certs_section_subtitle', 'Transparansi kualitas adalah komitmen utama kami. Setiap batch panen Madu Nusantara diuji secara berkala di laboratorium terakreditasi untuk menjamin 100% kemurnian tanpa campuran sirup gula.', 'certificates', 'Subjudul Seksi Sertifikat & Uji Lab', 'textarea'],
            ['faq_section_title', 'Tanya Jawab Seputar Keaslian & Konsumsi Madu (FAQ)', 'faq', 'Judul Seksi FAQ', 'text'],
            ['faq_section_subtitle', 'Jawaban lengkap dari tim ahli perlebahan kami atas pertanyaan yang paling sering diajukan calon pembeli.', 'faq', 'Subjudul Seksi FAQ', 'textarea'],
        ];
        foreach ($extraSettings as $es) {
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key = ?");
            $chk->execute([$es[0]]);
            if ((int)$chk->fetchColumn() === 0) {
                $this->upsertSetting($es[0], $es[1], $es[2], $es[3], $es[4]);
            }
        }

        // 3. Default 6 Honey Products with Multi-Size Variants & Click Counters
        $prodCount = (int)$this->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        if ($prodCount === 0) {
            $defaultProducts = [
                [
                    'name'              => 'Madu Hutan Liar Sumbawa (Raw Forest Honey)',
                    'slug'              => 'madu-hutan-liar-sumbawa',
                    'category'          => 'Madu Hutan Liar',
                    'badge'             => 'Best Seller',
                    'weight'            => '500gr (Botol Kaca Segel)',
                    'price'             => 115000,
                    'original_price'    => 145000,
                    'variants_text'     => '250gr Travel:65000|500gr Reguler:115000|1Kg Family:210000',
                    'short_desc'        => 'Dipanen langsung dari lebah raksasa Apis dorsata di hutan belantara Sumbawa. Tekstur kental alami dengan aroma nektar multiflora hutan yang kuat.',
                    'full_desc'         => 'Madu Hutan Liar Sumbawa berasal dari nektar ribuan bunga pohon hutan tropis Sumbawa yang dikumpulkan oleh lebah liar Apis dorsata. Dipanen secara higienis dan berkelanjutan tanpa proses pemanasan (100% Raw & Unfiltered), sehingga kandungan enzim diastase, asam amino, mineral, serta antioksidan flavonoid di dalamnya tetap aktif maksimal.',
                    'benefits'          => 'Meningkatkan daya tahan tubuh & imunitas harian|Membantu pemulihan stamina pasca sakit atau kelelahan|Kaya antioksidan penangkal radikal bebas|Meredakan batuk, radang tenggorokan, dan panas dalam',
                    'origin'            => 'Hutan Pegunungan Sumbawa, NTB',
                    'image_url'         => 'assets/images/product-1-sumbawa.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-hutan-sumbawa-500gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya tertarik memesan / bertanya tentang *Madu Hutan Liar Sumbawa*. Apakah stok masih tersedia?',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 142,
                    'wa_clicks'         => 98,
                    'is_featured'       => 1,
                    'sort_order'        => 1,
                ],
                [
                    'name'              => 'Madu Klanceng / Trigona Sp. Murni',
                    'slug'              => 'madu-klanceng-trigona',
                    'category'          => 'Madu Propolis Alami',
                    'badge'             => 'High Propolis',
                    'weight'            => '350gr (Botol Premium)',
                    'price'             => 135000,
                    'original_price'    => 165000,
                    'variants_text'     => '180gr Mini:78000|350gr Premium:135000|700gr Twin Pack:255000',
                    'short_desc'        => 'Dihasilkan oleh lebah tanpa sengat (Trigona sp.) yang menyimpan madu di dalam kantung propolis. Cita rasa manis-asam segar kaya propolis & bee pollen.',
                    'full_desc'         => 'Madu Klanceng (Trigona sp.) dikenal sebagai kasta tertinggi dalam dunia perlebahan karena lebah tanpa sengat ini menyimpan nektar di dalam tempayan propolis alami. Hal ini membuat Madu Klanceng secara alami terinfusi dengan propolis aktif, bee pollen, serta senyawa antibakteri dan anti-inflamasi tingkat tinggi.',
                    'benefits'          => 'Kandungan propolis alami 4x lebih tinggi dari madu biasa|Sangat baik untuk kesehatan lambung (GERD & Maag)|Mempercepat regenerasi sel dan penyembuhan luka|Menjaga kesehatan jantung dan mengontrol kolesterol',
                    'origin'            => 'Peternakan Lestari Lereng Merapi, Jawa Tengah',
                    'image_url'         => 'assets/images/product-2-klanceng.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-klanceng-trigona-350gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya ingin bertanya / memesan *Madu Klanceng / Trigona Sp. Murni* secara langsung.',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 118,
                    'wa_clicks'         => 87,
                    'is_featured'       => 1,
                    'sort_order'        => 2,
                ],
                [
                    'name'              => 'Madu Sarang Murni (Fresh Raw Honeycomb)',
                    'slug'              => 'madu-sarang-murni-honeycomb',
                    'category'          => 'Madu Sarang Asli',
                    'badge'             => 'Fresh Harvest',
                    'weight'            => '400gr (Vacuum Box Food Grade)',
                    'price'             => 98000,
                    'original_price'    => 125000,
                    'variants_text'     => '250gr Compact:65000|400gr Regular Box:98000|800gr Family Box:185000',
                    'short_desc'        => 'Potongan sarang lebah Apis mellifera asli yang masih tertutup segel lilin lebah (capped comb). Sensasi mengunyah sarang lembut dengan lelehan madu segar.',
                    'full_desc'         => 'Rasakan pengalaman mengonsumsi madu paling murni sebagaimana yang ada di alam! Madu Sarang Murni kami dipotong langsung dari bingkai sarang lebah unggulan yang telah matang sempurna (100% tertutup lilin lebah putih/keemasan). Lilin sarang lebah alami mengandung senyawa policosanol yang bermanfaat bagi kesehatan metabolisme.',
                    'benefits'          => 'Jaminan keaslian 100% langsung dari sarang lebah|Lilin sarang alami membantu menyehatkan gusi & pernapasan|Kaya enzim segar yang belum pernah terpapar udara luar|Cocok dikonsumsi langsung atau dipadukan dengan roti & buah',
                    'origin'            => 'Perkebunan Kemitraan Pati & Kediri',
                    'image_url'         => 'assets/images/product-3-sarang.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-sarang-murni-400gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya ingin memesan / bertanya mengenai *Madu Sarang Murni (Fresh Raw Honeycomb)*.',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 94,
                    'wa_clicks'         => 64,
                    'is_featured'       => 1,
                    'sort_order'        => 3,
                ],
                [
                    'name'              => 'Madu Bunga Randu Organik (Kapok Blossom)',
                    'slug'              => 'madu-bunga-randu-organik',
                    'category'          => 'Madu Monoflora',
                    'badge'             => 'Favorit Keluarga',
                    'weight'            => '650gr (Family Size)',
                    'price'             => 105000,
                    'original_price'    => 130000,
                    'variants_text'     => '350gr Reguler:62000|650gr Family:105000|1Kg Jumbo:155000',
                    'short_desc'        => 'Madu nektar bunga pohon randu (kapuk) dengan rasa manis lembut dan sedikit sentuhan segar yang sangat disukai anak-anak maupun dewasa.',
                    'full_desc'         => 'Madu Bunga Randu dihasilkan oleh koloni lebah Apis mellifera yang digembalakan di kawasan hutan pohon randu saat musim berbunga raya. Memiliki warna cokelat keemasan jernih, aroma harum khas bunga randu, dan tekstur lembut yang tidak menyengat di tenggorokan, menjadikannya pilihan favorit untuk konsumsi harian anak dan keluarga.',
                    'benefits'          => 'Meningkatkan nafsu makan anak secara alami|Meredakan demam, sariawan, dan batuk pada anak & dewasa|Menutrisi fungsi otak dan daya konsentrasi|Pengganti gula sehat untuk minuman dan sarapan keluarga',
                    'origin'            => 'Kawasan Hutan Randu Jawa Timur',
                    'image_url'         => 'assets/images/product-4-randu.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-bunga-randu-650gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya ingin memesan / tanya detail *Madu Bunga Randu Organik*.',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 86,
                    'wa_clicks'         => 72,
                    'is_featured'       => 1,
                    'sort_order'        => 4,
                ],
                [
                    'name'              => 'Madu Royal Jelly & Bee Pollen Superfood',
                    'slug'              => 'madu-royal-jelly-bee-pollen',
                    'category'          => 'Madu Superfood',
                    'badge'             => 'Premium Blend',
                    'weight'            => '500gr (Botol Eksklusif)',
                    'price'             => 149000,
                    'original_price'    => 185000,
                    'variants_text'     => '250gr Starter:82000|500gr Eksklusif:149000|1Kg Paket Vitalitas:279000',
                    'short_desc'        => 'Perpaduan madu murni nektar kaliandra dengan susu ratu lebah (Royal Jelly) segar dan tepungsari bunga (Bee Pollen) untuk vitalitas paripurna.',
                    'full_desc'         => 'Kombinasi tiga elemen terbaik dari sarang lebah dalam satu botol: Madu Murni Nektar Kaliandra, Royal Jelly segar (makanan khusus ratu lebah), dan Bee Pollen kaya protein. Dirancang khusus bagi Anda yang memiliki aktivitas padat, membutuhkan pemulihan energi cepat, serta menjaga kesehatan hormon dan keremajaan kulit.',
                    'benefits'          => 'Menunjang vitalitas, stamina, dan kebugaran tubuh ekstra|Mendukung keseimbangan hormon & kesuburan alami|Kaya kolagen alami & antioksidan untuk kesehatan kulit|Mempercepat pemulihan tubuh setelah olahraga atau bekerja berat',
                    'origin'            => 'Dataran Tinggi Jawa Barat & Jawa Tengah',
                    'image_url'         => 'assets/images/product-5-royaljelly.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-royal-jelly-500gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya tertarik dengan *Madu Royal Jelly & Bee Pollen Superfood*. Mohon info pemesanannya.',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 79,
                    'wa_clicks'         => 61,
                    'is_featured'       => 1,
                    'sort_order'        => 5,
                ],
                [
                    'name'              => 'Madu Hitam Pahit Hutan Mahoni & Propolis',
                    'slug'              => 'madu-hitam-pahit-mahoni',
                    'category'          => 'Madu Terapi Kesehatan',
                    'badge'             => 'Low Glycemic',
                    'weight'            => '500gr (Botol Kaca Gelap)',
                    'price'             => 120000,
                    'original_price'    => 150000,
                    'variants_text'     => '250gr Reguler:68000|500gr Terapi:120000|1Kg Paket Rutin:220000',
                    'short_desc'        => 'Madu hutan nektar bunga mahoni dan pelawan dengan karakteristik warna hitam pekat serta rasa manis-pahit alami yang tinggi alkaloid.',
                    'full_desc'         => 'Madu Hitam Pahit berasal dari lebah yang menghisap nektar bunga pohon mahoni dan kaliandra hitam di hutan alami. Warna gelap pekat dan rasa pahit alaminya menandakan tingginya kandungan alkaloid serta senyawa fenolik, dengan indeks glikemik yang lebih rendah dibandingkan madu manis biasa.',
                    'benefits'          => 'Membantu menjaga kestabilan kadar gula darah & kolesterol|Tinggi zat alkaloid alami sebagai anti-infeksi dan detoksifikasi|Membantu meredakan nyeri sendi dan gejala asam urat|Menjaga stamina pria & wanita dewasa',
                    'origin'            => 'Hutan Pedalaman Jambi & Bangka',
                    'image_url'         => 'assets/images/product-6-hitampahit.svg',
                    'shopee_url'        => 'https://shopee.co.id/madunusantara.official?product=madu-hitam-pahit-500gr',
                    'wa_custom_message' => 'Halo CS Madu Nusantara, saya ingin berkonsultasi / memesan *Madu Hitam Pahit Hutan Mahoni*.',
                    'stock_status'      => 'Tersedia',
                    'shopee_clicks'     => 65,
                    'wa_clicks'         => 53,
                    'is_featured'       => 1,
                    'sort_order'        => 6,
                ],
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO products (
                    name, slug, category, badge, weight, price, original_price, variants_text,
                    short_desc, full_desc, benefits, origin, image_url, shopee_url,
                    wa_custom_message, stock_status, shopee_clicks, wa_clicks, is_featured, sort_order, created_at, updated_at
                ) VALUES (
                    :name, :slug, :category, :badge, :weight, :price, :original_price, :variants_text,
                    :short_desc, :full_desc, :benefits, :origin, :image_url, :shopee_url,
                    :wa_custom_message, :stock_status, :shopee_clicks, :wa_clicks, :is_featured, :sort_order, :created_at, :updated_at
                )
            ");

            foreach ($defaultProducts as $p) {
                $p['created_at'] = $now;
                $p['updated_at'] = $now;
                $stmt->execute($p);
            }
        } else {
            // Backfill variants_text & click counts on existing seeded products if empty
            $emptyVarCount = (int)$this->pdo->query("SELECT COUNT(*) FROM products WHERE variants_text = ''")->fetchColumn();
            if ($emptyVarCount > 0) {
                $this->pdo->exec("
                    UPDATE products SET variants_text = '250gr Travel:65000|500gr Reguler:' || price || '|1Kg Hemat:' || (price * 1.85),
                    shopee_clicks = CASE WHEN shopee_clicks = 0 THEN (95 + id * 12) ELSE shopee_clicks END,
                    wa_clicks = CASE WHEN wa_clicks = 0 THEN (65 + id * 9) ELSE wa_clicks END
                    WHERE variants_text = '';
                ");
            }
        }

        // 4. Default 6 Articles
        $artCount = (int)$this->pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
        if ($artCount === 0) {
            $defaultArticles = [
                [
                    'title'          => 'Panen Lestari Hutan Tropis Ramah Lingkungan',
                    'slug'           => 'panen-lestari-hutan-tropis',
                    'category'       => 'Kelestarian Alam',
                    'excerpt'        => 'Metode panen potong pucuk sarang memastikan koloni lebah Apis dorsata tetap lestari dan kembali bersarang setiap musim.',
                    'content'        => 'Dalam menjaga kelestarian ekosistem hutan Indonesia, para pemanen mitra Madu Nusantara hanya memanen bagian kepala sarang yang berisi madu matang dan meninggalkan bagian anakan lebah (brood comb). Dengan cara ini, koloni lebah tidak rusak dan hutan tetap terjaga penyerbukannya.',
                    'image_url'      => 'assets/images/article-1.svg',
                    'author'         => 'Antonius Saharjo',
                    'published_date' => '2026-10-12',
                    'sort_order'     => 1,
                ],
                [
                    'title'          => 'Riset Membuktikan Flavonoid & Enzim Madu Menjaga Imunitas',
                    'slug'           => 'riset-flavonoid-enzim-madu',
                    'category'       => 'Sains & Kesehatan',
                    'excerpt'        => 'Madu mentah (raw honey) mengandung enzim diastase, glukosa oksidase, dan polifenol yang bekerja sinergis melindungi sel tubuh.',
                    'content'        => 'Berbeda dengan pemanis buatan, madu murni yang tidak dipasteurisasi pada suhu tinggi mempertahankan aktivitas enzim diastase (DN > 5) serta hidrogen peroksida alami yang berperan sebagai antibakteri dan imunomodulator alami bagi anak maupun dewasa.',
                    'image_url'      => 'assets/images/article-2.svg',
                    'author'         => 'Jihan Nadira',
                    'published_date' => '2026-10-15',
                    'sort_order'     => 2,
                ],
                [
                    'title'          => 'Mengenal Perbedaan Karakteristik Madu Hutan vs Madu Ternak',
                    'slug'           => 'perbedaan-madu-hutan-vs-madu-ternak',
                    'category'       => 'Edukasi Konsumen',
                    'excerpt'        => 'Baik madu hutan multiflora maupun madu ternak monoflora memiliki keunikan rasa, warna, dan keunggulan nutrisi masing-masing.',
                    'content'        => 'Madu hutan dihasilkan oleh lebah liar Apis dorsata dari ratusan jenis bunga pohon hutan sehingga kaya akan mineral dan bertekstur sedikit bergas alami. Sementara madu ternak (Apis mellifera / Trigona) berasal dari nektar spesifik seperti Bunga Randu atau Kaliandra dengan rasa yang lebih konsisten.',
                    'image_url'      => 'assets/images/article-3.svg',
                    'author'         => 'Andika Suwardi',
                    'published_date' => '2026-10-18',
                    'sort_order'     => 3,
                ],
                [
                    'title'          => 'Proses Penyaringan Dingin (Cold Micro-Filtration)',
                    'slug'           => 'proses-penyaringan-dingin-cold-filtration',
                    'category'       => 'Standar Mutu',
                    'excerpt'        => 'Madu disaring menggunakan mesh food-grade tanpa pemanasan agar butiran halus bee pollen dan royal jelly tidak hilang.',
                    'content'        => 'Banyak produk komersial memanaskan madu hingga di atas 70 derajat Celcius agar tampak bening berkilau, namun proses tersebut merusak enzim hidup di dalamnya. Madu Nusantara menggunakan teknik Cold Micro-Filtration pada suhu ruang untuk memisahkan serpihan lilin kasar tanpa mengurangi nutrisi mikro.',
                    'image_url'      => 'assets/images/article-4.svg',
                    'author'         => 'Jihan Nadira',
                    'published_date' => '2026-10-21',
                    'sort_order'     => 4,
                ],
                [
                    'title'          => 'Pemberdayaan Peternak Lebah dengan Teknik Agroforestri',
                    'slug'           => 'pemberdayaan-peternak-lebah-agroforestri',
                    'category'       => 'Kemitraan UMKM',
                    'excerpt'        => 'Kolaborasi bersama lebih dari 45 kelompok tani hutan dan peternak lebah lokal di Jawa, Sumbawa, dan Sumatera.',
                    'content'        => 'Melalui integrasi perlebahan dan wana tani (agroforestri), petani lokal memperoleh penghasilan berkelanjutan dari hasil panen madu tanpa harus menebang pohon hutan. Setiap botol yang Anda beli turut mendukung kesejahteraan keluarga peternak lebah Nusantara.',
                    'image_url'      => 'assets/images/article-5.svg',
                    'author'         => 'Antonius Saharjo',
                    'published_date' => '2026-10-25',
                    'sort_order'     => 5,
                ],
                [
                    'title'          => 'Pematangan Alami di Dalam Sarang (Capped Honeycomb)',
                    'slug'           => 'pematangan-alami-di-dalam-sarang',
                    'category'       => 'Proses Alami',
                    'excerpt'        => 'Kami hanya memanen sarang yang telah ditutup segel lilin oleh lebah pekerja sebagai tanda kadar air madu telah ideal.',
                    'content'        => 'Lebah pekerja mengepakkan sayapnya ribuan kali di dalam sarang untuk menguapkan kelebihan air dari nektar bunga. Setelah kadar air turun ke titik optimal (18-20%), lebah menutup sel sarang dengan lapisan lilin. Itulah waktu panen terbaik yang menghasilkan madu kental berkualitas tinggi.',
                    'image_url'      => 'assets/images/article-6.svg',
                    'author'         => 'Andika Suwardi',
                    'published_date' => '2026-10-28',
                    'sort_order'     => 6,
                ],
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO articles (title, slug, category, excerpt, content, image_url, author, published_date, sort_order, created_at)
                VALUES (:title, :slug, :category, :excerpt, :content, :image_url, :author, :published_date, :sort_order, :created_at)
            ");
            foreach ($defaultArticles as $a) {
                $a['created_at'] = $now;
                $stmt->execute($a);
            }
        }

        // 5. Default 4 Features
        $featCount = (int)$this->pdo->query("SELECT COUNT(*) FROM features")->fetchColumn();
        if ($featCount === 0) {
            $defaultFeatures = [
                ['droplet', '100% Raw & Unfiltered', 'Madu mentah murni tanpa proses pemanasan suhu tinggi (pasteurisasi) maupun penambahan gula/sirup fruktosa.', '100%', 1],
                ['shield-check', 'Teruji Lab SNI & Halal', 'Telah melewati uji laboratorium kemurnian enzim diastase, HMF, dan bersertifikat Halal resmi.', 'SNI', 2],
                ['leaf', '45+ Mitra Peternak Lokal', 'Dipanen langsung dari kelompok tani hutan Sumbawa, Sumatera, dan peternakan lebah lestari di Jawa.', '45+', 3],
                ['truck', 'Pengiriman Aman Bergaransi', 'Packing berlapis bubble-wrap tebal & box kokoh. Garansi ganti baru 100% bila botol pecah di perjalanan.', '24 Jam', 4],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO features (icon, title, description, badge_value, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($defaultFeatures as $f) {
                $stmt->execute($f);
            }
        }

        // 6. Default 3 Team Members
        $teamCount = (int)$this->pdo->query("SELECT COUNT(*) FROM team_members")->fetchColumn();
        if ($teamCount === 0) {
            $defaultTeam = [
                [
                    'name'          => 'Antonius Saharjo',
                    'role'          => 'Founder & Kepala Kemitraan Hutan',
                    'bio'           => 'Memimpin jaringan kemitraan pemanen madu hutan lestari dan standarisasi kualitas ekspor produk perlebahan Indonesia.',
                    'image_url'     => 'assets/images/team-1.svg',
                    'instagram_url' => 'https://instagram.com/madunusantara.id',
                    'sort_order'    => 1,
                ],
                [
                    'name'          => 'Andika Suwardi',
                    'role'          => 'Ahli Budidaya Apikultur & Panen',
                    'bio'           => 'Mengawasi kesehatan koloni lebah Apis mellifera dan Trigona sp. agar menghasilkan nektar murni berkualitas tinggi.',
                    'image_url'     => 'assets/images/team-2.svg',
                    'instagram_url' => 'https://instagram.com/madunusantara.id',
                    'sort_order'    => 2,
                ],
                [
                    'name'          => 'Jihan Nadira',
                    'role'          => 'Kepala Quality Control & Lab Nutrisi',
                    'bio'           => 'Memastikan setiap batch madu lolos pengujian kadar air, keaktifan enzim diastase, dan standar pengemasan higienis.',
                    'image_url'     => 'assets/images/team-3.svg',
                    'instagram_url' => 'https://instagram.com/madunusantara.id',
                    'sort_order'    => 3,
                ],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO team_members (name, role, bio, image_url, instagram_url, sort_order)
                VALUES (:name, :role, :bio, :image_url, :instagram_url, :sort_order)
            ");
            foreach ($defaultTeam as $t) {
                $stmt->execute($t);
            }
        }

        // 7. Default Testimonials
        $testiCount = (int)$this->pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
        if ($testiCount === 0) {
            $defaultTestimonials = [
                [
                    'customer_name' => 'dr. Rina Kusumawardhani',
                    'customer_role' => 'Pelanggan Setia - Jakarta Selatan',
                    'rating'        => 5,
                    'comment'       => 'Sudah 2 tahun rutin konsumsi Madu Hutan Sumbawa dan Madu Klanceng dari Madu Nusantara untuk keluarga. Rasanya benar-benar murni, aroma bunganya khas, dan tenggorokan langsung nyaman.',
                    'product_name'  => 'Madu Hutan Liar Sumbawa',
                    'order_channel' => 'Pemesanan via WhatsApp CS',
                    'avatar_url'    => 'RK',
                    'sort_order'    => 1,
                ],
                [
                    'customer_name' => 'Bapak Hendra Wijaya',
                    'customer_role' => 'Verified Buyer - Surabaya',
                    'rating'        => 5,
                    'comment'       => 'Beli lewat link Shopee maupun pesan langsung ke CS sama-sama cepat pelayanannya. Packing botol kaca super aman pakai bubble tebal, Madu Sarangnya segar banget anak-anak suka!',
                    'product_name'  => 'Madu Sarang Murni (Honeycomb)',
                    'order_channel' => 'Shopee Official Store',
                    'avatar_url'    => 'HW',
                    'sort_order'    => 2,
                ],
                [
                    'customer_name' => 'Ibu Siti Nurhaliza',
                    'customer_role' => 'Pemilik Kafe Sehat - Bandung',
                    'rating'        => 5,
                    'comment'       => 'Kami pesan partai besar non-marketplace lewat Customer Service untuk kebutuhan menu minuman sehat di kafe kami. Harga bersahabat dan kualitas madu randunya konsisten kental.',
                    'product_name'  => 'Madu Bunga Randu Organik',
                    'order_channel' => 'Order Non-Marketplace (CS)',
                    'avatar_url'    => 'SN',
                    'sort_order'    => 3,
                ],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO testimonials (customer_name, customer_role, rating, comment, product_name, order_channel, avatar_url, sort_order)
                VALUES (:customer_name, :customer_role, :rating, :comment, :product_name, :order_channel, :avatar_url, :sort_order)
            ");
            foreach ($defaultTestimonials as $tm) {
                $stmt->execute($tm);
            }
        }

        // 8. Default Certificates & Lab Proofs
        $certCount = (int)$this->pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
        if ($certCount === 0) {
            $defaultCerts = [
                [
                    'title'        => 'Uji Laboratorium SNI 8664:2018 (Enzim & HMF)',
                    'cert_number'  => 'LAB-AGRO/SNI/2026-0892',
                    'issuer'       => 'Balai Besar Industri Agro (BBIA)',
                    'test_summary' => 'Aktivitas Enzim Diastase: 6.4 DN (Syarat SNI min 3 DN) • Kadar HMF: 11.2 mg/kg (Maks 50 mg/kg) • Kadar Air: 18.6% • Sukrosa: 1.8% (100% Murni Tanpa Gula Tambahan).',
                    'image_url'    => 'assets/images/cert-1-lab.svg',
                    'sort_order'   => 1,
                ],
                [
                    'title'        => 'Sertifikat Halal Resmi BPJPH Republik Indonesia',
                    'cert_number'  => 'ID32110001928370524',
                    'issuer'       => 'BPJPH Kemenag RI & LPPOM MUI',
                    'test_summary' => 'Seluruh proses pemanenan, penyaringan dingin (cold-filtration), hingga pengemasan botol telah diaudit dan terjamin 100% Halal serta Thayyib.',
                    'image_url'    => 'assets/images/cert-2-halal.svg',
                    'sort_order'   => 2,
                ],
                [
                    'title'        => 'Sertifikat Produksi Pangan Industri Rumah Tangga (SPP-IRT)',
                    'cert_number'  => 'P-IRT No. 2093271010888-26',
                    'issuer'       => 'Dinas Kesehatan Kota Bogor',
                    'test_summary' => 'Memenuhi standar sanitasi ruang kemas higienis, penggunaan kemasan botol kaca dan food-grade bersegel induksi aman untuk konsumsi keluarga.',
                    'image_url'    => 'assets/images/cert-3-pirt.svg',
                    'sort_order'   => 3,
                ],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO certificates (title, cert_number, issuer, test_summary, image_url, sort_order)
                VALUES (:title, :cert_number, :issuer, :test_summary, :image_url, :sort_order)
            ");
            foreach ($defaultCerts as $c) {
                $stmt->execute($c);
            }
        }

        // 9. Default FAQs
        $faqCount = (int)$this->pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
        if ($faqCount === 0) {
            $defaultFaqs = [
                [
                    'question'   => 'Apakah madu murni asli bisa dikerubungi semut?',
                    'answer'     => 'Ya, madu murni asli tetap bisa dikerubungi semut terutama varian madu nektar bunga (seperti Madu Bunga Randu atau Kaliandra) yang memiliki aroma nektar harum dan kandungan glukosa/fruktosa alami dari bunga. Mitos bahwa "madu asli tidak disukai semut" tidak benar secara ilmiah; penentu keaslian madu yang akurat adalah uji laboratorium aktivitas Enzim Diastase dan kadar HMF.',
                    'category'   => 'Keaslian Madu',
                    'sort_order' => 1,
                ],
                [
                    'question'   => 'Mengapa Madu Hutan mengeluarkan gas/busa saat tutup botol dibuka?',
                    'answer'     => 'Gas dan buih halus pada Madu Hutan Liar maupun Madu Mentah (Raw Honey) adalah tanda aktifnya enzim alami (diastase dan invertase) serta kandungan bee pollen segar karena madu kami tidak melalui proses pemanasan suhu tinggi (pasteurisasi). Cukup buka tutup botol perlahan dan aduk sebelum dikonsumsi.',
                    'category'   => 'Karakteristik Produk',
                    'sort_order' => 2,
                ],
                [
                    'question'   => 'Bagaimana cara menyimpan madu yang benar? Bolehkah dimasukkan ke dalam kulkas?',
                    'answer'     => 'Sebaiknya simpan madu di suhu ruang (20°C–28°C) di tempat teduh yang terhindar dari sinar matahari langsung dan tutup rapat botol setelah digunakan. Tidak disarankan menyimpan madu di dalam kulkas/freezer karena suhu dingin dapat memicu proses kristalisasi alami (glukosa mengendap menjadi butiran).',
                    'category'   => 'Penyimpanan',
                    'sort_order' => 3,
                ],
                [
                    'question'   => 'Varian madu mana yang paling cocok untuk penderita Maag/GERD dan untuk anak-anak?',
                    'answer'     => 'Untuk penderita Maag/GERD dan gangguan lambung, kami sangat merekomendasikan Madu Klanceng (Trigona) atau Madu Hutan Sumbawa yang diminum 1 sendok makan dicampur air hangat sebelum makan. Sedangkan untuk anak-anak (usia di atas 1 tahun), varian favorit adalah Madu Bunga Randu Organik dan Madu Sarang Murni karena rasanya manis lembut.',
                    'category'   => 'Rekomendasi Konsumsi',
                    'sort_order' => 4,
                ],
                [
                    'question'   => 'Bagaimana cara memesan banyak produk sekaligus tanpa melalui Shopee (Non-Marketplace)?',
                    'answer'     => 'Anda cukup memilih ukuran kemasan pada produk yang diinginkan lalu klik tombol "+ Keranjang". Setelah semua produk masuk ke Keranjang Belanja, Anda dapat memasukkan Kode Voucher Diskon, mengisi alamat, dan menekan tombol Checkout ke WhatsApp CS. Pesanan Anda akan langsung dirinci otomatis ke Customer Service kami.',
                    'category'   => 'Pemesanan & Pengiriman',
                    'sort_order' => 5,
                ],
                [
                    'question'   => 'Apakah ada garansi jika botol madu pecah atau bocor saat pengiriman ekspedisi?',
                    'answer'     => 'Tentu! Seluruh pesanan dikemas dengan standar ekstra aman (segel dalam, bubble wrap berlapis tebal, dan kardus khusus). Jika terjadi kerusakan/pecah selama pengiriman, kami memberikan Garansi Ganti Baru 100% Gratis cukup dengan mengirimkan video unboxing ke Customer Service kami.',
                    'category'   => 'Garansi & Layanan',
                    'sort_order' => 6,
                ],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO faqs (question, answer, category, sort_order)
                VALUES (:question, :answer, :category, :sort_order)
            ");
            foreach ($defaultFaqs as $fq) {
                $stmt->execute($fq);
            }
        }

        // 10. Default Promo Vouchers
        $vouchCount = (int)$this->pdo->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
        if ($vouchCount === 0) {
            $defaultVouchers = [
                ['MADUSEHAT10', 'percent', 10, 100000, 'Diskon 10% untuk pembelian minimal Rp 100.000 via Website CS', 1],
                ['HEMAT25RB', 'fixed', 25000, 200000, 'Potongan langsung Rp 25.000 untuk pembelian minimal Rp 200.000', 1],
                ['KELUARGA15', 'percent', 15, 300000, 'Diskon Spesial Keluarga 15% (Min. belanja Rp 300.000)', 1],
            ];
            $stmt = $this->pdo->prepare("
                INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, description, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($defaultVouchers as $v) {
                $stmt->execute($v);
            }
        }

        // 11. Default Sample Non-Marketplace Inquiries
        $inqCount = (int)$this->pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        if ($inqCount === 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO inquiries (customer_name, customer_phone, customer_city, inquiry_type, product_id, product_name, quantity, voucher_code, discount_amount, total_estimate, message, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Budi Santoso',
                '081298765432',
                'Depok, Jawa Barat',
                'Pesan Non-Marketplace',
                1,
                '2x Madu Hutan Liar Sumbawa (500gr Reguler)',
                2,
                'MADUSEHAT10',
                23000,
                207000,
                'Mohon info ongkir ke Depok untuk 2 botol Madu Hutan Sumbawa 500gr.',
                'Selesai',
                $now,
            ]);
            $stmt->execute([
                'Dewi Kartika',
                '085711223344',
                'Tangerang Selatan',
                'Tanya Customer Service',
                2,
                'Madu Klanceng / Trigona Sp. Murni',
                1,
                '',
                0,
                135000,
                'Kak, apakah Madu Klanceng aman dikonsumsi sebelum sarapan untuk penderita maag?',
                'Baru',
                $now,
            ]);
        }
    }

    // =========================================================================
    // CRUD HELPERS FOR ALL LANDING PAGE INFORMATION
    // =========================================================================

    public function getSettingsMap(): array
    {
        $rows = $this->pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = $r['setting_value'];
        }
        return $map;
    }

    public function getAllSettingsDetailed(): array
    {
        return $this->pdo->query("SELECT * FROM site_settings ORDER BY group_name ASC, setting_key ASC")->fetchAll();
    }

    public function upsertSetting(string $key, string $value, string $group = 'general', string $label = '', string $type = 'text'): bool
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->pdo->prepare("SELECT setting_key, group_name, label, field_type FROM site_settings WHERE setting_key = ?");
        $existing->execute([$key]);
        $row = $existing->fetch();

        if ($row) {
            $stmt = $this->pdo->prepare("
                UPDATE site_settings
                SET setting_value = :v,
                    group_name = :g,
                    label = :l,
                    field_type = :t,
                    updated_at = :u
                WHERE setting_key = :k
            ");
            return $stmt->execute([
                ':v' => $value,
                ':g' => $group !== 'general' ? $group : $row['group_name'],
                ':l' => $label !== '' ? $label : $row['label'],
                ':t' => $type !== 'text' ? $type : $row['field_type'],
                ':u' => $now,
                ':k' => $key,
            ]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, group_name, label, field_type, updated_at)
            VALUES (:k, :v, :g, :l, :t, :u)
        ");
        return $stmt->execute([
            ':k' => $key,
            ':v' => $value,
            ':g' => $group,
            ':l' => $label ?: ucwords(str_replace('_', ' ', $key)),
            ':t' => $type,
            ':u' => $now,
        ]);
    }

    public function deleteSetting(string $key): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM site_settings WHERE setting_key = ?");
        return $stmt->execute([$key]);
    }

    // Products CRUD + Click Tracking
    public function getProducts(): array
    {
        return $this->pdo->query("SELECT * FROM products ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getProductById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function incrementProductClick(int $productId, string $channel): bool
    {
        $col = ($channel === 'shopee') ? 'shopee_clicks' : 'wa_clicks';
        $stmt = $this->pdo->prepare("UPDATE products SET {$col} = {$col} + 1 WHERE id = ?");
        return $stmt->execute([$productId]);
    }

    public function createProduct(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? 'Produk Madu Baru'));
        $slug = $this->slugify((string)($data['slug'] ?? $name));
        $price = (int)($data['price'] ?? 95000);
        $defaultVar = "250gr:65000|500gr:{$price}|1Kg:" . (int)round($price * 1.85);

        $stmt = $this->pdo->prepare("
            INSERT INTO products (
                name, slug, category, badge, weight, price, original_price, variants_text,
                short_desc, full_desc, benefits, origin, image_url, shopee_url,
                wa_custom_message, stock_status, shopee_clicks, wa_clicks, is_featured, sort_order, created_at, updated_at
            ) VALUES (
                :name, :slug, :category, :badge, :weight, :price, :original_price, :variants_text,
                :short_desc, :full_desc, :benefits, :origin, :image_url, :shopee_url,
                :wa_custom_message, :stock_status, :shopee_clicks, :wa_clicks, :is_featured, :sort_order, :created_at, :updated_at
            )
        ");
        $stmt->execute([
            ':name'              => $name,
            ':slug'              => $slug,
            ':category'          => trim((string)($data['category'] ?? 'Madu Murni')),
            ':badge'             => trim((string)($data['badge'] ?? '100% Raw Honey')),
            ':weight'            => trim((string)($data['weight'] ?? '500gr')),
            ':price'             => $price,
            ':original_price'    => (int)($data['original_price'] ?? 120000),
            ':variants_text'     => trim((string)($data['variants_text'] ?? $defaultVar)),
            ':short_desc'        => trim((string)($data['short_desc'] ?? '')),
            ':full_desc'         => trim((string)($data['full_desc'] ?? ($data['short_desc'] ?? ''))),
            ':benefits'          => trim((string)($data['benefits'] ?? '')),
            ':origin'            => trim((string)($data['origin'] ?? 'Hutan Nusantara')),
            ':image_url'         => trim((string)($data['image_url'] ?? 'assets/images/product-1-sumbawa.svg')),
            ':shopee_url'        => trim((string)($data['shopee_url'] ?? 'https://shopee.co.id/madunusantara.official')),
            ':wa_custom_message' => trim((string)($data['wa_custom_message'] ?? "Halo CS, saya tertarik dengan produk {$name}.")),
            ':stock_status'      => trim((string)($data['stock_status'] ?? 'Tersedia')),
            ':shopee_clicks'     => (int)($data['shopee_clicks'] ?? 0),
            ':wa_clicks'         => (int)($data['wa_clicks'] ?? 0),
            ':is_featured'       => isset($data['is_featured']) ? (int)(bool)$data['is_featured'] : 1,
            ':sort_order'        => (int)($data['sort_order'] ?? 10),
            ':created_at'        => $now,
            ':updated_at'        => $now,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateProduct(int $id, array $data): bool
    {
        $existing = $this->getProductById($id);
        if (!$existing) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = $this->slugify((string)($data['slug'] ?? $name));

        $stmt = $this->pdo->prepare("
            UPDATE products SET
                name = :name,
                slug = :slug,
                category = :category,
                badge = :badge,
                weight = :weight,
                price = :price,
                original_price = :original_price,
                variants_text = :variants_text,
                short_desc = :short_desc,
                full_desc = :full_desc,
                benefits = :benefits,
                origin = :origin,
                image_url = :image_url,
                shopee_url = :shopee_url,
                wa_custom_message = :wa_custom_message,
                stock_status = :stock_status,
                is_featured = :is_featured,
                sort_order = :sort_order,
                updated_at = :updated_at
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name'              => $name,
            ':slug'              => $slug,
            ':category'          => trim((string)($data['category'] ?? $existing['category'])),
            ':badge'             => trim((string)($data['badge'] ?? $existing['badge'])),
            ':weight'            => trim((string)($data['weight'] ?? $existing['weight'])),
            ':price'             => isset($data['price']) ? (int)$data['price'] : (int)$existing['price'],
            ':original_price'    => isset($data['original_price']) ? (int)$data['original_price'] : (int)$existing['original_price'],
            ':variants_text'     => isset($data['variants_text']) ? trim((string)$data['variants_text']) : (string)$existing['variants_text'],
            ':short_desc'        => trim((string)($data['short_desc'] ?? $existing['short_desc'])),
            ':full_desc'         => trim((string)($data['full_desc'] ?? $existing['full_desc'])),
            ':benefits'          => trim((string)($data['benefits'] ?? $existing['benefits'])),
            ':origin'            => trim((string)($data['origin'] ?? $existing['origin'])),
            ':image_url'         => trim((string)($data['image_url'] ?? $existing['image_url'])),
            ':shopee_url'        => trim((string)($data['shopee_url'] ?? $existing['shopee_url'])),
            ':wa_custom_message' => trim((string)($data['wa_custom_message'] ?? $existing['wa_custom_message'])),
            ':stock_status'      => trim((string)($data['stock_status'] ?? $existing['stock_status'])),
            ':is_featured'       => isset($data['is_featured']) ? (int)(bool)$data['is_featured'] : (int)$existing['is_featured'],
            ':sort_order'        => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':updated_at'        => $now,
            ':id'                => $id,
        ]);
    }

    public function deleteProduct(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Certificates & Lab Proofs CRUD
    public function getCertificates(): array
    {
        return $this->pdo->query("SELECT * FROM certificates ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getCertificateById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCertificate(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO certificates (title, cert_number, issuer, test_summary, image_url, sort_order)
            VALUES (:title, :cert_number, :issuer, :test_summary, :image_url, :sort_order)
        ");
        $stmt->execute([
            ':title'        => trim((string)($data['title'] ?? 'Sertifikat Mutu Baru')),
            ':cert_number'  => trim((string)($data['cert_number'] ?? '-')),
            ':issuer'       => trim((string)($data['issuer'] ?? 'Laboratorium Resmi')),
            ':test_summary' => trim((string)($data['test_summary'] ?? '')),
            ':image_url'    => trim((string)($data['image_url'] ?? 'assets/images/cert-1-lab.svg')),
            ':sort_order'   => (int)($data['sort_order'] ?? 10),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCertificate(int $id, array $data): bool
    {
        $existing = $this->getCertificateById($id);
        if (!$existing) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE certificates SET
                title = :title,
                cert_number = :cert_number,
                issuer = :issuer,
                test_summary = :test_summary,
                image_url = :image_url,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':title'        => trim((string)($data['title'] ?? $existing['title'])),
            ':cert_number'  => trim((string)($data['cert_number'] ?? $existing['cert_number'])),
            ':issuer'       => trim((string)($data['issuer'] ?? $existing['issuer'])),
            ':test_summary' => trim((string)($data['test_summary'] ?? $existing['test_summary'])),
            ':image_url'    => trim((string)($data['image_url'] ?? $existing['image_url'])),
            ':sort_order'   => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'           => $id,
        ]);
    }

    public function deleteCertificate(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificates WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // FAQs CRUD
    public function getFaqs(): array
    {
        return $this->pdo->query("SELECT * FROM faqs ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getFaqById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM faqs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createFaq(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO faqs (question, answer, category, sort_order)
            VALUES (:question, :answer, :category, :sort_order)
        ");
        $stmt->execute([
            ':question'   => trim((string)($data['question'] ?? 'Pertanyaan Baru')),
            ':answer'     => trim((string)($data['answer'] ?? '')),
            ':category'   => trim((string)($data['category'] ?? 'Keaslian & Konsumsi')),
            ':sort_order' => (int)($data['sort_order'] ?? 10),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateFaq(int $id, array $data): bool
    {
        $existing = $this->getFaqById($id);
        if (!$existing) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE faqs SET
                question = :question,
                answer = :answer,
                category = :category,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':question'   => trim((string)($data['question'] ?? $existing['question'])),
            ':answer'     => trim((string)($data['answer'] ?? $existing['answer'])),
            ':category'   => trim((string)($data['category'] ?? $existing['category'])),
            ':sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'         => $id,
        ]);
    }

    public function deleteFaq(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM faqs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Vouchers CRUD & Validation
    public function getVouchers(): array
    {
        return $this->pdo->query("SELECT * FROM vouchers ORDER BY id ASC")->fetchAll();
    }

    public function getVoucherById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function validateVoucherCode(string $code, int $subtotal): array
    {
        $cleanCode = strtoupper(trim($code));
        if ($cleanCode === '') {
            return ['valid' => false, 'message' => 'Masukkan kode voucher terlebih dahulu.'];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vouchers WHERE UPPER(code) = ? AND is_active = 1");
        $stmt->execute([$cleanCode]);
        $v = $stmt->fetch();
        if (!$v) {
            return ['valid' => false, 'message' => "Kode voucher '{$cleanCode}' tidak ditemukan atau sudah tidak aktif."];
        }
        $minPurchase = (int)$v['min_purchase'];
        if ($subtotal < $minPurchase) {
            $minFmt = 'Rp ' . number_format($minPurchase, 0, ',', '.');
            return ['valid' => false, 'message' => "Minimal belanja untuk voucher {$cleanCode} adalah {$minFmt}."];
        }
        $discount = 0;
        if ($v['discount_type'] === 'percent') {
            $discount = (int)round($subtotal * ((int)$v['discount_value'] / 100));
        } else {
            $discount = (int)$v['discount_value'];
        }
        $discount = min($subtotal, $discount);

        return [
            'valid'           => true,
            'code'            => $v['code'],
            'discount_type'   => $v['discount_type'],
            'discount_value'  => (int)$v['discount_value'],
            'discount_amount' => $discount,
            'final_total'     => max(0, $subtotal - $discount),
            'description'     => $v['description'],
            'message'         => "Voucher {$v['code']} berhasil diterapkan! Hemat Rp " . number_format($discount, 0, ',', '.'),
        ];
    }

    public function createVoucher(array $data): int
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($data['code'] ?? 'PROMO')));
        $stmt = $this->pdo->prepare("
            INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, description, is_active)
            VALUES (:code, :discount_type, :discount_value, :min_purchase, :description, :is_active)
        ");
        $stmt->execute([
            ':code'           => $code ?: 'PROMO' . rand(10, 99),
            ':discount_type'  => ($data['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent',
            ':discount_value' => (int)($data['discount_value'] ?? 10),
            ':min_purchase'   => (int)($data['min_purchase'] ?? 100000),
            ':description'    => trim((string)($data['description'] ?? '')),
            ':is_active'      => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateVoucher(int $id, array $data): bool
    {
        $existing = $this->getVoucherById($id);
        if (!$existing) {
            return false;
        }
        $code = isset($data['code'])
            ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$data['code']))
            : $existing['code'];
        $stmt = $this->pdo->prepare("
            UPDATE vouchers SET
                code = :code,
                discount_type = :discount_type,
                discount_value = :discount_value,
                min_purchase = :min_purchase,
                description = :description,
                is_active = :is_active
            WHERE id = :id
        ");
        return $stmt->execute([
            ':code'           => $code ?: $existing['code'],
            ':discount_type'  => isset($data['discount_type']) ? (($data['discount_type'] === 'fixed') ? 'fixed' : 'percent') : $existing['discount_type'],
            ':discount_value' => isset($data['discount_value']) ? (int)$data['discount_value'] : (int)$existing['discount_value'],
            ':min_purchase'   => isset($data['min_purchase']) ? (int)$data['min_purchase'] : (int)$existing['min_purchase'],
            ':description'    => trim((string)($data['description'] ?? $existing['description'])),
            ':is_active'      => isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)$existing['is_active'],
            ':id'             => $id,
        ]);
    }

    public function deleteVoucher(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM vouchers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Articles CRUD
    public function getArticles(): array
    {
        return $this->pdo->query("SELECT * FROM articles ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getArticleById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createArticle(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $title = trim((string)($data['title'] ?? 'Artikel Baru'));
        $stmt = $this->pdo->prepare("
            INSERT INTO articles (title, slug, category, excerpt, content, image_url, author, published_date, sort_order, created_at)
            VALUES (:title, :slug, :category, :excerpt, :content, :image_url, :author, :published_date, :sort_order, :created_at)
        ");
        $stmt->execute([
            ':title'          => $title,
            ':slug'           => $this->slugify($title),
            ':category'       => trim((string)($data['category'] ?? 'Edukasi & Panen')),
            ':excerpt'        => trim((string)($data['excerpt'] ?? '')),
            ':content'        => trim((string)($data['content'] ?? ($data['excerpt'] ?? ''))),
            ':image_url'      => trim((string)($data['image_url'] ?? 'assets/images/article-1.svg')),
            ':author'         => trim((string)($data['author'] ?? 'Tim Ahli Madu Nusantara')),
            ':published_date' => trim((string)($data['published_date'] ?? date('Y-m-d'))),
            ':sort_order'     => (int)($data['sort_order'] ?? 10),
            ':created_at'     => $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateArticle(int $id, array $data): bool
    {
        $existing = $this->getArticleById($id);
        if (!$existing) {
            return false;
        }
        $title = trim((string)($data['title'] ?? $existing['title']));
        $stmt = $this->pdo->prepare("
            UPDATE articles SET
                title = :title,
                slug = :slug,
                category = :category,
                excerpt = :excerpt,
                content = :content,
                image_url = :image_url,
                author = :author,
                published_date = :published_date,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':title'          => $title,
            ':slug'           => $this->slugify($title),
            ':category'       => trim((string)($data['category'] ?? $existing['category'])),
            ':excerpt'        => trim((string)($data['excerpt'] ?? $existing['excerpt'])),
            ':content'        => trim((string)($data['content'] ?? $existing['content'])),
            ':image_url'      => trim((string)($data['image_url'] ?? $existing['image_url'])),
            ':author'         => trim((string)($data['author'] ?? $existing['author'])),
            ':published_date' => trim((string)($data['published_date'] ?? $existing['published_date'])),
            ':sort_order'     => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'             => $id,
        ]);
    }

    public function deleteArticle(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM articles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Features CRUD
    public function getFeatures(): array
    {
        return $this->pdo->query("SELECT * FROM features ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getFeatureById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM features WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createFeature(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO features (icon, title, description, badge_value, sort_order)
            VALUES (:icon, :title, :description, :badge_value, :sort_order)
        ");
        $stmt->execute([
            ':icon'        => trim((string)($data['icon'] ?? 'shield-check')),
            ':title'       => trim((string)($data['title'] ?? 'Keunggulan Baru')),
            ':description' => trim((string)($data['description'] ?? '')),
            ':badge_value' => trim((string)($data['badge_value'] ?? '100%')),
            ':sort_order'  => (int)($data['sort_order'] ?? 10),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateFeature(int $id, array $data): bool
    {
        $existing = $this->getFeatureById($id);
        if (!$existing) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE features SET
                icon = :icon,
                title = :title,
                description = :description,
                badge_value = :badge_value,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':icon'        => trim((string)($data['icon'] ?? $existing['icon'])),
            ':title'       => trim((string)($data['title'] ?? $existing['title'])),
            ':description' => trim((string)($data['description'] ?? $existing['description'])),
            ':badge_value' => trim((string)($data['badge_value'] ?? $existing['badge_value'])),
            ':sort_order'  => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'          => $id,
        ]);
    }

    public function deleteFeature(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM features WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Team Members CRUD
    public function getTeamMembers(): array
    {
        return $this->pdo->query("SELECT * FROM team_members ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getTeamMemberById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM team_members WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createTeamMember(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO team_members (name, role, bio, image_url, instagram_url, sort_order)
            VALUES (:name, :role, :bio, :image_url, :instagram_url, :sort_order)
        ");
        $stmt->execute([
            ':name'          => trim((string)($data['name'] ?? 'Anggota Tim')),
            ':role'          => trim((string)($data['role'] ?? 'Spesialis Kualitas')),
            ':bio'           => trim((string)($data['bio'] ?? '')),
            ':image_url'     => trim((string)($data['image_url'] ?? 'assets/images/team-1.svg')),
            ':instagram_url' => trim((string)($data['instagram_url'] ?? '#')),
            ':sort_order'    => (int)($data['sort_order'] ?? 10),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTeamMember(int $id, array $data): bool
    {
        $existing = $this->getTeamMemberById($id);
        if (!$existing) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE team_members SET
                name = :name,
                role = :role,
                bio = :bio,
                image_url = :image_url,
                instagram_url = :instagram_url,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'          => trim((string)($data['name'] ?? $existing['name'])),
            ':role'          => trim((string)($data['role'] ?? $existing['role'])),
            ':bio'           => trim((string)($data['bio'] ?? $existing['bio'])),
            ':image_url'     => trim((string)($data['image_url'] ?? $existing['image_url'])),
            ':instagram_url' => trim((string)($data['instagram_url'] ?? $existing['instagram_url'])),
            ':sort_order'    => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'            => $id,
        ]);
    }

    public function deleteTeamMember(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM team_members WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Testimonials CRUD
    public function getTestimonials(): array
    {
        return $this->pdo->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getTestimonialById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createTestimonial(array $data): int
    {
        $name = trim((string)($data['customer_name'] ?? 'Pelanggan'));
        $initials = $this->makeInitials($name);
        $stmt = $this->pdo->prepare("
            INSERT INTO testimonials (customer_name, customer_role, rating, comment, product_name, order_channel, avatar_url, sort_order)
            VALUES (:customer_name, :customer_role, :rating, :comment, :product_name, :order_channel, :avatar_url, :sort_order)
        ");
        $stmt->execute([
            ':customer_name' => $name,
            ':customer_role' => trim((string)($data['customer_role'] ?? 'Verified Buyer')),
            ':rating'        => max(1, min(5, (int)($data['rating'] ?? 5))),
            ':comment'       => trim((string)($data['comment'] ?? '')),
            ':product_name'  => trim((string)($data['product_name'] ?? 'Madu Hutan Liar Sumbawa')),
            ':order_channel' => trim((string)($data['order_channel'] ?? 'Shopee Official Store')),
            ':avatar_url'    => trim((string)($data['avatar_url'] ?? $initials)),
            ':sort_order'    => (int)($data['sort_order'] ?? 10),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTestimonial(int $id, array $data): bool
    {
        $existing = $this->getTestimonialById($id);
        if (!$existing) {
            return false;
        }
        $name = trim((string)($data['customer_name'] ?? $existing['customer_name']));
        $stmt = $this->pdo->prepare("
            UPDATE testimonials SET
                customer_name = :customer_name,
                customer_role = :customer_role,
                rating = :rating,
                comment = :comment,
                product_name = :product_name,
                order_channel = :order_channel,
                avatar_url = :avatar_url,
                sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':customer_name' => $name,
            ':customer_role' => trim((string)($data['customer_role'] ?? $existing['customer_role'])),
            ':rating'        => isset($data['rating']) ? max(1, min(5, (int)$data['rating'])) : (int)$existing['rating'],
            ':comment'       => trim((string)($data['comment'] ?? $existing['comment'])),
            ':product_name'  => trim((string)($data['product_name'] ?? $existing['product_name'])),
            ':order_channel' => trim((string)($data['order_channel'] ?? $existing['order_channel'])),
            ':avatar_url'    => trim((string)($data['avatar_url'] ?? $existing['avatar_url'])),
            ':sort_order'    => isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$existing['sort_order'],
            ':id'            => $id,
        ]);
    }

    public function deleteTestimonial(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM testimonials WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Inquiries / Non-Marketplace Orders CRUD
    public function getInquiries(): array
    {
        return $this->pdo->query("SELECT * FROM inquiries ORDER BY id DESC")->fetchAll();
    }

    public function getInquiryById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createInquiry(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $prodId = !empty($data['product_id']) ? (int)$data['product_id'] : null;
        $prodName = trim((string)($data['product_name'] ?? 'Konsultasi Umum'));
        $qty = max(1, (int)($data['quantity'] ?? 1));
        $voucherCode = strtoupper(trim((string)($data['voucher_code'] ?? '')));
        $discountAmount = (int)($data['discount_amount'] ?? 0);
        $total = (int)($data['total_estimate'] ?? 0);

        if ($prodId && $total === 0) {
            $prod = $this->getProductById($prodId);
            if ($prod) {
                $prodName = $prod['name'];
                $total = (int)$prod['price'] * $qty;
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO inquiries (
                customer_name, customer_phone, customer_city, inquiry_type,
                product_id, product_name, quantity, voucher_code, discount_amount,
                total_estimate, message, status, created_at
            ) VALUES (
                :customer_name, :customer_phone, :customer_city, :inquiry_type,
                :product_id, :product_name, :quantity, :voucher_code, :discount_amount,
                :total_estimate, :message, :status, :created_at
            )
        ");
        $stmt->execute([
            ':customer_name'   => trim((string)($data['customer_name'] ?? 'Pelanggan')),
            ':customer_phone'  => trim((string)($data['customer_phone'] ?? '-')),
            ':customer_city'   => trim((string)($data['customer_city'] ?? '-')),
            ':inquiry_type'    => trim((string)($data['inquiry_type'] ?? 'Pesan Non-Marketplace')),
            ':product_id'      => $prodId,
            ':product_name'    => $prodName,
            ':quantity'        => $qty,
            ':voucher_code'    => $voucherCode,
            ':discount_amount' => $discountAmount,
            ':total_estimate'  => $total,
            ':message'         => trim((string)($data['message'] ?? '')),
            ':status'          => trim((string)($data['status'] ?? 'Baru')),
            ':created_at'      => $now,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateInquiry(int $id, array $data): bool
    {
        $existing = $this->getInquiryById($id);
        if (!$existing) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE inquiries SET
                customer_name = :customer_name,
                customer_phone = :customer_phone,
                customer_city = :customer_city,
                inquiry_type = :inquiry_type,
                product_name = :product_name,
                quantity = :quantity,
                voucher_code = :voucher_code,
                discount_amount = :discount_amount,
                total_estimate = :total_estimate,
                message = :message,
                status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            ':customer_name'   => trim((string)($data['customer_name'] ?? $existing['customer_name'])),
            ':customer_phone'  => trim((string)($data['customer_phone'] ?? $existing['customer_phone'])),
            ':customer_city'   => trim((string)($data['customer_city'] ?? $existing['customer_city'])),
            ':inquiry_type'    => trim((string)($data['inquiry_type'] ?? $existing['inquiry_type'])),
            ':product_name'    => trim((string)($data['product_name'] ?? $existing['product_name'])),
            ':quantity'        => isset($data['quantity']) ? max(1, (int)$data['quantity']) : (int)$existing['quantity'],
            ':voucher_code'    => isset($data['voucher_code']) ? strtoupper(trim((string)$data['voucher_code'])) : (string)($existing['voucher_code'] ?? ''),
            ':discount_amount' => isset($data['discount_amount']) ? (int)$data['discount_amount'] : (int)($existing['discount_amount'] ?? 0),
            ':total_estimate'  => isset($data['total_estimate']) ? (int)$data['total_estimate'] : (int)$existing['total_estimate'],
            ':message'         => trim((string)($data['message'] ?? $existing['message'])),
            ':status'          => trim((string)($data['status'] ?? $existing['status'])),
            ':id'              => $id,
        ]);
    }

    public function deleteInquiry(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM inquiries WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'item';
        return trim($slug, '-') ?: 'item-' . time();
    }

    private function makeInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: ['M'];
        $initials = '';
        foreach ($parts as $p) {
            if ($p !== '' && strlen($initials) < 2) {
                $initials .= strtoupper($p[0]);
            }
        }
        return $initials ?: 'MN';
    }
}
