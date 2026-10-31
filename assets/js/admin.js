/**
 * MADU NUSANTARA UMKM - ADMIN CRUD CONTROLLER
 * Covers: Products (with Multi-Size Variants & Click Stats), Certificates,
 * FAQs, Vouchers, Inquiries (+ CSV Export), Articles, Features, Team, Testimonials,
 * Landing Page Settings, & Encrypted Password Management.
 */
(function () {
    'use strict';

    const state = window.ADMIN_STATE || {
        csrfToken: '',
        waNumber: '6281234567890',
        products: [],
        certificates: [],
        faqs: [],
        vouchers: [],
        articles: [],
        features: [],
        team: [],
        testimonials: [],
        inquiries: []
    };

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function formatRupiah(num) {
        const val = parseInt(num, 10) || 0;
        return 'Rp ' + val.toLocaleString('id-ID');
    }

    function showToast(msg, isError) {
        const toast = document.getElementById('adminToast');
        if (!toast) return;
        toast.textContent = msg;
        toast.style.borderLeftColor = isError ? '#DC2626' : '#16A34A';
        toast.classList.add('show');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(function () {
            toast.classList.remove('show');
        }, 3800);
    }

    function apiCall(action, payload) {
        const body = Object.assign({}, payload || {}, {
            action: action,
            csrf_token: state.csrfToken
        });
        return fetch('api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': state.csrfToken
            },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    // 1. Sidebar & Tab Switching
    const tabButtons = document.querySelectorAll('.side-nav-btn');
    const tabPanes = document.querySelectorAll('.admin-tab-pane');
    const activeTabTitle = document.getElementById('activeTabTitle');
    const sidebar = document.getElementById('adminSidebar');
    const mobileSidebarBtn = document.getElementById('mobileSidebarBtn');

    if (mobileSidebarBtn && sidebar) {
        mobileSidebarBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    window.switchAdminTab = function (tabId) {
        tabButtons.forEach(function (btn) {
            const isTarget = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('active', isTarget);
            if (isTarget && activeTabTitle) {
                activeTabTitle.textContent = btn.textContent.replace(/[0-9]+$/, '').trim();
            }
        });
        tabPanes.forEach(function (pane) {
            pane.classList.toggle('active', pane.id === tabId);
        });
        if (sidebar) sidebar.classList.remove('open');
    };

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.switchAdminTab(btn.getAttribute('data-tab'));
        });
    });

    function updateCounters() {
        const setEl = function (id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        setEl('countProducts', state.products.length);
        setEl('statProducts', state.products.length + ' Varian');
        setEl('countCertificates', state.certificates.length);
        setEl('countFaqs', state.faqs.length);
        setEl('countVouchers', state.vouchers.length);
        setEl('statVouchers', state.vouchers.length + ' Promo');
        setEl('countFeatures', state.features.length);
        setEl('countArticles', state.articles.length);
        setEl('countTeam', state.team.length);
        setEl('countTestimonials', state.testimonials.length);
        setEl('countInquiries', state.inquiries.length);
        setEl('statInquiries', state.inquiries.length + ' Pesanan Masuk →');
    }

    // 2. Universal Modal & Image Upload Helper
    const crudModal = document.getElementById('admCrudModal');
    const crudForm = document.getElementById('admCrudForm');
    const crudTitle = document.getElementById('admModalTitle');
    let currentFormSubmitHandler = null;

    window.closeAdmModal = function () {
        if (crudModal) {
            crudModal.classList.remove('open');
            crudModal.setAttribute('aria-hidden', 'true');
        }
    };

    function openAdmModal(title, htmlFields, onSubmit) {
        if (!crudModal || !crudForm || !crudTitle) return;
        crudTitle.textContent = title;
        crudForm.innerHTML = htmlFields +
            '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:14px;border-top:1px solid #E5E7EB;">' +
                '<button type="button" class="btn-adm btn-adm-outline" onclick="closeAdmModal()">Batal</button>' +
                '<button type="submit" class="btn-adm btn-adm-primary">💾 Simpan Data</button>' +
            '</div>';
        currentFormSubmitHandler = onSubmit;
        crudModal.classList.add('open');
        crudModal.setAttribute('aria-hidden', 'false');
    }

    if (crudForm) {
        crudForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (typeof currentFormSubmitHandler === 'function') {
                const fd = new FormData(crudForm);
                const obj = {};
                fd.forEach(function (val, key) { obj[key] = val; });
                currentFormSubmitHandler(obj);
            }
        });
    }

    window.uploadImageToInput = function (fileInput, targetInputId) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('csrf_token', state.csrfToken);
        fd.append('image', fileInput.files[0]);

        showToast('Mengunggah gambar...', false);
        fetch('api.php?action=upload_image', {
            method: 'POST',
            headers: { 'X-CSRF-Token': state.csrfToken },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success && res.image_url) {
                const target = document.getElementById(targetInputId);
                if (target) target.value = res.image_url;
                showToast('Gambar berhasil diunggah!', false);
            } else {
                showToast(res.message || 'Gagal mengunggah gambar.', true);
            }
        })
        .catch(function () {
            showToast('Kesalahan jaringan saat mengunggah gambar.', true);
        });
    };

    // 3. PRODUCTS CRUD (with Multi-Size Variants & Click Stats)
    function renderProductsTable() {
        const tbody = document.getElementById('productsTableBody');
        if (!tbody) return;
        if (state.products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;">Belum ada produk. Klik "+ Tambah Produk Baru".</td></tr>';
            return;
        }
        tbody.innerHTML = state.products.map(function (p) {
            return '<tr>' +
                '<td><strong>#' + esc(p.sort_order) + '</strong></td>' +
                '<td><img src="' + esc(p.image_url) + '" alt="' + esc(p.name) + '" class="table-thumb"></td>' +
                '<td>' +
                    '<strong>' + esc(p.name) + '</strong><br>' +
                    '<small style="color:#298A81;font-weight:700;">' + esc(p.category) + ' • ' + esc(p.badge) + '</small><br>' +
                    '<small style="color:#6B7280;">Varian: ' + esc(p.variants_text || p.weight) + '</small>' +
                '</td>' +
                '<td>' +
                    '<strong>' + formatRupiah(p.price) + '</strong><br>' +
                    '<small style="text-decoration:line-through;color:#9CA3AF;">' + formatRupiah(p.original_price) + '</small>' +
                '</td>' +
                '<td>' +
                    '<span style="color:#EE4D2D;font-weight:700;font-size:0.78rem;">Shopee: ' + (parseInt(p.shopee_clicks, 10) || 0) + 'x</span><br>' +
                    '<span style="color:#16A34A;font-weight:700;font-size:0.78rem;">WA/Cart: ' + (parseInt(p.wa_clicks, 10) || 0) + 'x</span>' +
                '</td>' +
                '<td>' +
                    '<a href="' + esc(p.shopee_url) + '" target="_blank" style="color:#EE4D2D;font-weight:700;font-size:0.78rem;">Link Shopee ↗</a><br>' +
                    '<span style="font-size:0.75rem;color:#166534;font-weight:700;">' + esc(p.stock_status) + '</span>' +
                '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openProductModal(' + parseInt(p.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteProductItem(' + parseInt(p.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openProductModal = function (id) {
        const prod = id ? state.products.find(function (p) { return parseInt(p.id, 10) === parseInt(id, 10); }) : null;
        const p = prod || {
            name: '',
            category: 'Madu Hutan Liar',
            badge: '100% Raw Honey',
            weight: '500gr (Botol Kaca)',
            price: 115000,
            original_price: 145000,
            variants_text: '250gr Travel:65000|500gr Reguler:115000|1Kg Family:210000',
            origin: 'Hutan Sumbawa, NTB',
            stock_status: 'Tersedia',
            sort_order: state.products.length + 1,
            image_url: 'assets/images/product-1-sumbawa.svg',
            shopee_url: 'https://shopee.co.id/madunusantara.official',
            short_desc: '',
            full_desc: '',
            benefits: 'Meningkatkan imunitas harian|Kaya antioksidan flavonoid alami|Meredakan batuk & radang tenggorokan',
            wa_custom_message: ''
        };

        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Nama Produk Madu *</label>' +
                    '<input type="text" name="name" value="' + esc(p.name) + '" required placeholder="Contoh: Madu Hutan Liar Sumbawa">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Kategori Produk</label>' +
                    '<input type="text" name="category" value="' + esc(p.category) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Label Badge Unggulan</label>' +
                    '<input type="text" name="badge" value="' + esc(p.badge) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Berat / Ukuran Utama</label>' +
                    '<input type="text" name="weight" value="' + esc(p.weight) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Asal Panen (Origin)</label>' +
                    '<input type="text" name="origin" value="' + esc(p.origin) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Harga Utama (Rp) *</label>' +
                    '<input type="number" name="price" value="' + esc(p.price) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Harga Coret / Normal (Rp)</label>' +
                    '<input type="number" name="original_price" value="' + esc(p.original_price) + '">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Daftar Varian Ukuran &amp; Harga (Format: Ukuran:Harga dipisah tanda | )</label>' +
                    '<input type="text" name="variants_text" value="' + esc(p.variants_text) + '" placeholder="250gr Travel:65000|500gr Reguler:115000|1Kg Family:210000">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Status Stok</label>' +
                    '<select name="stock_status">' +
                        '<option value="Tersedia"' + (p.stock_status === 'Tersedia' ? ' selected' : '') + '>Tersedia</option>' +
                        '<option value="Stok Terbatas"' + (p.stock_status === 'Stok Terbatas' ? ' selected' : '') + '>Stok Terbatas</option>' +
                        '<option value="Pre-Order Panen"' + (p.stock_status === 'Pre-Order Panen' ? ' selected' : '') + '>Pre-Order Panen</option>' +
                        '<option value="Habis"' + (p.stock_status === 'Habis' ? ' selected' : '') + '>Habis</option>' +
                    '</select>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(p.sort_order) + '">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Link Produk di Marketplace Shopee *</label>' +
                    '<input type="url" name="shopee_url" value="' + esc(p.shopee_url) + '" required placeholder="https://shopee.co.id/...">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Gambar Produk (URL atau Unggah File)</label>' +
                    '<div class="upload-input-row">' +
                        '<input type="text" id="modalProdImg" name="image_url" value="' + esc(p.image_url) + '" required>' +
                        '<label class="btn-upload-inline">Unggah Gambar<input type="file" accept="image/*" onchange="uploadImageToInput(this, \'modalProdImg\')" hidden></label>' +
                    '</div>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Deskripsi Singkat (Tampil di Kartu Produk) *</label>' +
                    '<textarea name="short_desc" rows="2" required>' + esc(p.short_desc) + '</textarea>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Deskripsi Lengkap &amp; Karakteristik Madu</label>' +
                    '<textarea name="full_desc" rows="3">' + esc(p.full_desc) + '</textarea>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Daftar Manfaat Kesehatan (Pisahkan dengan tanda pipa | )</label>' +
                    '<input type="text" name="benefits" value="' + esc(p.benefits) + '" placeholder="Manfaat 1|Manfaat 2|Manfaat 3">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Pesan Kustom WhatsApp CS untuk Produk Ini</label>' +
                    '<input type="text" name="wa_custom_message" value="' + esc(p.wa_custom_message) + '" placeholder="Halo CS, saya tertarik memesan produk ini...">' +
                '</div>' +
            '</div>';

        openAdmModal(prod ? 'Edit Produk Madu: ' + prod.name : 'Tambah Produk Madu Baru', html, function (formData) {
            const action = prod ? 'update_product' : 'create_product';
            if (prod) formData.id = prod.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (prod) {
                        state.products = state.products.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(prod.id, 10) ? res.product : item;
                        });
                    } else {
                        state.products.push(res.product);
                    }
                    renderProductsTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                } else {
                    showToast(res.message || 'Gagal menyimpan produk.', true);
                }
            });
        });
    };

    window.deleteProductItem = function (id) {
        if (!confirm('Apakah Anda yakin ingin menghapus produk ini dari Landing Page?')) return;
        apiCall('delete_product', { id: id }).then(function (res) {
            if (res.success) {
                state.products = state.products.filter(function (p) { return parseInt(p.id, 10) !== parseInt(id, 10); });
                renderProductsTable();
                showToast(res.message, false);
            } else {
                showToast(res.message || 'Gagal menghapus produk.', true);
            }
        });
    };

    // 4. VOUCHERS CRUD
    function renderVouchersTable() {
        const tbody = document.getElementById('vouchersTableBody');
        if (!tbody) return;
        if (state.vouchers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">Belum ada voucher promo.</td></tr>';
            return;
        }
        tbody.innerHTML = state.vouchers.map(function (v) {
            const discVal = v.discount_type === 'percent' ? (v.discount_value + '% OFF') : ('Potongan ' + formatRupiah(v.discount_value));
            return '<tr>' +
                '<td><code style="background:#FEF3C7;color:#92400E;padding:4px 8px;border-radius:6px;font-weight:700;">' + esc(v.code) + '</code></td>' +
                '<td><strong>' + esc(discVal) + '</strong></td>' +
                '<td>' + formatRupiah(v.min_purchase) + '</td>' +
                '<td>' + esc(v.description) + '</td>' +
                '<td>' + (parseInt(v.is_active, 10) === 1 ? '<span class="enc-status-chip">Aktif</span>' : '<span style="color:#9CA3AF;">Nonaktif</span>') + '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openVoucherModal(' + parseInt(v.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteVoucherItem(' + parseInt(v.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openVoucherModal = function (id) {
        const vouch = id ? state.vouchers.find(function (v) { return parseInt(v.id, 10) === parseInt(id, 10); }) : null;
        const v = vouch || { code: '', discount_type: 'percent', discount_value: 10, min_purchase: 100000, description: '', is_active: 1 };
        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Kode Voucher (Huruf Kapital &amp; Angka) *</label>' +
                    '<input type="text" name="code" value="' + esc(v.code) + '" required placeholder="Contoh: MADUSEHAT10" style="text-transform:uppercase;">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Tipe Potongan Diskon</label>' +
                    '<select name="discount_type">' +
                        '<option value="percent"' + (v.discount_type === 'percent' ? ' selected' : '') + '>Persentase (%)</option>' +
                        '<option value="fixed"' + (v.discount_type === 'fixed' ? ' selected' : '') + '>Potongan Rupiah Tetap (Rp)</option>' +
                    '</select>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Nilai Diskon (% atau Rp) *</label>' +
                    '<input type="number" name="discount_value" value="' + esc(v.discount_value) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Minimal Belanja (Rp)</label>' +
                    '<input type="number" name="min_purchase" value="' + esc(v.min_purchase) + '" required>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Deskripsi Promo</label>' +
                    '<input type="text" name="description" value="' + esc(v.description) + '" placeholder="Diskon 10% untuk pembelian minimal Rp 100.000">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Status Voucher</label>' +
                    '<select name="is_active">' +
                        '<option value="1"' + (parseInt(v.is_active, 10) === 1 ? ' selected' : '') + '>Aktif</option>' +
                        '<option value="0"' + (parseInt(v.is_active, 10) === 0 ? ' selected' : '') + '>Nonaktif</option>' +
                    '</select>' +
                '</div>' +
            '</div>';

        openAdmModal(vouch ? 'Edit Kode Voucher: ' + vouch.code : 'Buat Kode Voucher Baru', html, function (formData) {
            const action = vouch ? 'update_voucher' : 'create_voucher';
            if (vouch) formData.id = vouch.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (vouch) {
                        state.vouchers = state.vouchers.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(vouch.id, 10) ? res.voucher : item;
                        });
                    } else {
                        state.vouchers.push(res.voucher);
                    }
                    renderVouchersTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                } else {
                    showToast(res.message || 'Gagal menyimpan voucher.', true);
                }
            });
        });
    };

    window.deleteVoucherItem = function (id) {
        if (!confirm('Hapus kode voucher promo ini?')) return;
        apiCall('delete_voucher', { id: id }).then(function (res) {
            if (res.success) {
                state.vouchers = state.vouchers.filter(function (v) { return parseInt(v.id, 10) !== parseInt(id, 10); });
                renderVouchersTable();
                showToast(res.message, false);
            }
        });
    };

    // 5. CERTIFICATES & LAB PROOFS CRUD
    function renderCertificatesTable() {
        const tbody = document.getElementById('certificatesTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.certificates.map(function (c) {
            return '<tr>' +
                '<td>#' + esc(c.sort_order) + '</td>' +
                '<td><img src="' + esc(c.image_url) + '" alt="" class="table-thumb"></td>' +
                '<td><strong>' + esc(c.title) + '</strong><br><small style="color:#298A81;font-weight:700;">' + esc(c.cert_number) + '</small></td>' +
                '<td>' + esc(c.issuer) + '</td>' +
                '<td>' + esc(c.test_summary) + '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openCertificateModal(' + parseInt(c.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteCertificateItem(' + parseInt(c.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openCertificateModal = function (id) {
        const cert = id ? state.certificates.find(function (c) { return parseInt(c.id, 10) === parseInt(id, 10); }) : null;
        const c = cert || {
            title: '',
            cert_number: 'SNI 8664:2018',
            issuer: 'Laboratorium Pengujian Mutu',
            test_summary: '',
            image_url: 'assets/images/cert-1-lab.svg',
            sort_order: state.certificates.length + 1
        };

        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Nama Sertifikat / Pengujian Lab *</label>' +
                    '<input type="text" name="title" value="' + esc(c.title) + '" required placeholder="Contoh: Uji Laboratorium SNI 8664:2018">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Nomor Sertifikat / Dokumen</label>' +
                    '<input type="text" name="cert_number" value="' + esc(c.cert_number) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Lembaga Penerbit *</label>' +
                    '<input type="text" name="issuer" value="' + esc(c.issuer) + '" required>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Gambar Sertifikat / Laporan Uji Lab</label>' +
                    '<div class="upload-input-row">' +
                        '<input type="text" id="modalCertImg" name="image_url" value="' + esc(c.image_url) + '" required>' +
                        '<label class="btn-upload-inline">Unggah<input type="file" accept="image/*" onchange="uploadImageToInput(this, \'modalCertImg\')" hidden></label>' +
                    '</div>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Ringkasan Hasil Uji / Parameter Kemurnian *</label>' +
                    '<textarea name="test_summary" rows="3" required>' + esc(c.test_summary) + '</textarea>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(c.sort_order) + '">' +
                '</div>' +
            '</div>';

        openAdmModal(cert ? 'Edit Sertifikat & Uji Lab' : 'Tambah Sertifikat / Hasil Lab Baru', html, function (formData) {
            const action = cert ? 'update_certificate' : 'create_certificate';
            if (cert) formData.id = cert.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (cert) {
                        state.certificates = state.certificates.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(cert.id, 10) ? res.certificate : item;
                        });
                    } else {
                        state.certificates.push(res.certificate);
                    }
                    renderCertificatesTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteCertificateItem = function (id) {
        if (!confirm('Hapus bukti sertifikat ini?')) return;
        apiCall('delete_certificate', { id: id }).then(function (res) {
            if (res.success) {
                state.certificates = state.certificates.filter(function (c) { return parseInt(c.id, 10) !== parseInt(id, 10); });
                renderCertificatesTable();
                showToast(res.message, false);
            }
        });
    };

    // 6. FAQS CRUD
    function renderFaqsTable() {
        const tbody = document.getElementById('faqsTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.faqs.map(function (f) {
            return '<tr>' +
                '<td>#' + esc(f.sort_order) + '</td>' +
                '<td><span class="enc-status-chip">' + esc(f.category) + '</span></td>' +
                '<td><strong>' + esc(f.question) + '</strong></td>' +
                '<td>' + esc(f.answer) + '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openFaqModal(' + parseInt(f.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteFaqItem(' + parseInt(f.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openFaqModal = function (id) {
        const faq = id ? state.faqs.find(function (f) { return parseInt(f.id, 10) === parseInt(id, 10); }) : null;
        const f = faq || { question: '', answer: '', category: 'Keaslian Madu', sort_order: state.faqs.length + 1 };
        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Kategori Pertanyaan</label>' +
                    '<input type="text" name="category" value="' + esc(f.category) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(f.sort_order) + '">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Pertanyaan (Question) *</label>' +
                    '<input type="text" name="question" value="' + esc(f.question) + '" required>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Jawaban Lengkap (Answer) *</label>' +
                    '<textarea name="answer" rows="4" required>' + esc(f.answer) + '</textarea>' +
                '</div>' +
            '</div>';

        openAdmModal(faq ? 'Edit Pertanyaan FAQ' : 'Tambah Pertanyaan FAQ Baru', html, function (formData) {
            const action = faq ? 'update_faq' : 'create_faq';
            if (faq) formData.id = faq.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (faq) {
                        state.faqs = state.faqs.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(faq.id, 10) ? res.faq : item;
                        });
                    } else {
                        state.faqs.push(res.faq);
                    }
                    renderFaqsTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteFaqItem = function (id) {
        if (!confirm('Hapus pertanyaan FAQ ini?')) return;
        apiCall('delete_faq', { id: id }).then(function (res) {
            if (res.success) {
                state.faqs = state.faqs.filter(function (f) { return parseInt(f.id, 10) !== parseInt(id, 10); });
                renderFaqsTable();
                showToast(res.message, false);
            }
        });
    };

    // 7. LANDING PAGE SETTINGS & MARKETPLACE/CONTACT FORMS
    function bindSettingsForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(form);
            const settingsMap = {};
            fd.forEach(function (val, key) {
                const match = key.match(/^settings\[(.+)\]$/);
                if (match) {
                    settingsMap[match[1]] = val;
                }
            });
            apiCall('save_settings', { settings: settingsMap }).then(function (res) {
                if (res.success) {
                    showToast(res.message, false);
                } else {
                    showToast(res.message || 'Gagal menyimpan pengaturan.', true);
                }
            });
        });
    }

    bindSettingsForm('landingContentForm');
    bindSettingsForm('marketplaceContactForm');

    window.resetDefaultSettings = function () {
        if (!confirm('Kembalikan seluruh teks dan konten Landing Page ke pengaturan awal (default)?')) return;
        apiCall('reset_defaults', {}).then(function (res) {
            if (res.success) {
                showToast(res.message, false);
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                showToast(res.message || 'Gagal mereset data.', true);
            }
        });
    };

    // 8. FEATURES CRUD
    function renderFeaturesTable() {
        const tbody = document.getElementById('featuresTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.features.map(function (f) {
            return '<tr>' +
                '<td>#' + esc(f.sort_order) + '</td>' +
                '<td><span class="enc-status-chip">' + esc(f.badge_value) + '</span></td>' +
                '<td><strong>' + esc(f.title) + '</strong></td>' +
                '<td>' + esc(f.description) + '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openFeatureModal(' + parseInt(f.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteFeatureItem(' + parseInt(f.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openFeatureModal = function (id) {
        const feat = id ? state.features.find(function (f) { return parseInt(f.id, 10) === parseInt(id, 10); }) : null;
        const f = feat || { title: '', badge_value: '100%', description: '', sort_order: state.features.length + 1 };
        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Judul Keunggulan *</label>' +
                    '<input type="text" name="title" value="' + esc(f.title) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Label / Angka Badge</label>' +
                    '<input type="text" name="badge_value" value="' + esc(f.badge_value) + '" required>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Deskripsi Keunggulan *</label>' +
                    '<textarea name="description" rows="3" required>' + esc(f.description) + '</textarea>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(f.sort_order) + '">' +
                '</div>' +
            '</div>';

        openAdmModal(feat ? 'Edit Keunggulan' : 'Tambah Keunggulan Baru', html, function (formData) {
            const action = feat ? 'update_feature' : 'create_feature';
            if (feat) formData.id = feat.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (feat) {
                        state.features = state.features.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(feat.id, 10) ? res.feature : item;
                        });
                    } else {
                        state.features.push(res.feature);
                    }
                    renderFeaturesTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteFeatureItem = function (id) {
        if (!confirm('Hapus poin keunggulan ini?')) return;
        apiCall('delete_feature', { id: id }).then(function (res) {
            if (res.success) {
                state.features = state.features.filter(function (f) { return parseInt(f.id, 10) !== parseInt(id, 10); });
                renderFeaturesTable();
                showToast(res.message, false);
            }
        });
    };

    // 9. ARTICLES CRUD
    function renderArticlesTable() {
        const tbody = document.getElementById('articlesTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.articles.map(function (a) {
            return '<tr>' +
                '<td>#' + esc(a.sort_order) + '</td>' +
                '<td><img src="' + esc(a.image_url) + '" alt="" class="table-thumb"></td>' +
                '<td><strong>' + esc(a.title) + '</strong><br><small style="color:#298A81;font-weight:700;">' + esc(a.category) + '</small></td>' +
                '<td>' + esc(a.excerpt) + '</td>' +
                '<td>' + esc(a.author) + '<br><small>' + esc(a.published_date) + '</small></td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openArticleModalAdmin(' + parseInt(a.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteArticleItem(' + parseInt(a.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openArticleModalAdmin = function (id) {
        const art = id ? state.articles.find(function (a) { return parseInt(a.id, 10) === parseInt(id, 10); }) : null;
        const a = art || {
            title: '',
            category: 'Sains & Kesehatan',
            author: 'Tim Ahli Madu Nusantara',
            published_date: new Date().toISOString().slice(0, 10),
            image_url: 'assets/images/article-1.svg',
            excerpt: '',
            content: '',
            sort_order: state.articles.length + 1
        };

        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Judul Artikel / Edukasi *</label>' +
                    '<input type="text" name="title" value="' + esc(a.title) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Kategori</label>' +
                    '<input type="text" name="category" value="' + esc(a.category) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Penulis</label>' +
                    '<input type="text" name="author" value="' + esc(a.author) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Tanggal Publikasi</label>' +
                    '<input type="date" name="published_date" value="' + esc(a.published_date) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(a.sort_order) + '">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Gambar Artikel</label>' +
                    '<div class="upload-input-row">' +
                        '<input type="text" id="modalArtImg" name="image_url" value="' + esc(a.image_url) + '">' +
                        '<label class="btn-upload-inline">Unggah<input type="file" accept="image/*" onchange="uploadImageToInput(this, \'modalArtImg\')" hidden></label>' +
                    '</div>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Ringkasan Singkat *</label>' +
                    '<textarea name="excerpt" rows="2" required>' + esc(a.excerpt) + '</textarea>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Isi Lengkap Artikel</label>' +
                    '<textarea name="content" rows="4">' + esc(a.content) + '</textarea>' +
                '</div>' +
            '</div>';

        openAdmModal(art ? 'Edit Artikel' : 'Tambah Artikel Baru', html, function (formData) {
            const action = art ? 'update_article' : 'create_article';
            if (art) formData.id = art.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (art) {
                        state.articles = state.articles.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(art.id, 10) ? res.article : item;
                        });
                    } else {
                        state.articles.push(res.article);
                    }
                    renderArticlesTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteArticleItem = function (id) {
        if (!confirm('Hapus artikel ini?')) return;
        apiCall('delete_article', { id: id }).then(function (res) {
            if (res.success) {
                state.articles = state.articles.filter(function (a) { return parseInt(a.id, 10) !== parseInt(id, 10); });
                renderArticlesTable();
                showToast(res.message, false);
            }
        });
    };

    // 10. TEAM MEMBERS CRUD
    function renderTeamTable() {
        const tbody = document.getElementById('teamTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.team.map(function (t) {
            return '<tr>' +
                '<td>#' + esc(t.sort_order) + '</td>' +
                '<td><img src="' + esc(t.image_url) + '" alt="" class="table-thumb"></td>' +
                '<td><strong>' + esc(t.name) + '</strong></td>' +
                '<td>' + esc(t.role) + '</td>' +
                '<td>' + esc(t.bio) + '</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openTeamModal(' + parseInt(t.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteTeamItem(' + parseInt(t.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openTeamModal = function (id) {
        const member = id ? state.team.find(function (t) { return parseInt(t.id, 10) === parseInt(id, 10); }) : null;
        const m = member || { name: '', role: '', bio: '', image_url: 'assets/images/team-1.svg', sort_order: state.team.length + 1 };
        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Nama Lengkap *</label>' +
                    '<input type="text" name="name" value="' + esc(m.name) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Jabatan / Keahlian *</label>' +
                    '<input type="text" name="role" value="' + esc(m.role) + '" required>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Foto Profil</label>' +
                    '<div class="upload-input-row">' +
                        '<input type="text" id="modalTeamImg" name="image_url" value="' + esc(m.image_url) + '">' +
                        '<label class="btn-upload-inline">Unggah<input type="file" accept="image/*" onchange="uploadImageToInput(this, \'modalTeamImg\')" hidden></label>' +
                    '</div>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Deskripsi / Kutipan Singkat</label>' +
                    '<textarea name="bio" rows="3">' + esc(m.bio) + '</textarea>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(m.sort_order) + '">' +
                '</div>' +
            '</div>';

        openAdmModal(member ? 'Edit Anggota Tim' : 'Tambah Anggota Tim', html, function (formData) {
            const action = member ? 'update_team' : 'create_team';
            if (member) formData.id = member.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (member) {
                        state.team = state.team.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(member.id, 10) ? res.member : item;
                        });
                    } else {
                        state.team.push(res.member);
                    }
                    renderTeamTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteTeamItem = function (id) {
        if (!confirm('Hapus anggota tim ini?')) return;
        apiCall('delete_team', { id: id }).then(function (res) {
            if (res.success) {
                state.team = state.team.filter(function (t) { return parseInt(t.id, 10) !== parseInt(id, 10); });
                renderTeamTable();
                showToast(res.message, false);
            }
        });
    };

    // 11. TESTIMONIALS CRUD
    function renderTestimonialsTable() {
        const tbody = document.getElementById('testimonialsTableBody');
        if (!tbody) return;
        tbody.innerHTML = state.testimonials.map(function (t) {
            return '<tr>' +
                '<td>#' + esc(t.sort_order) + '</td>' +
                '<td><strong>' + esc(t.customer_name) + '</strong><br><small>' + esc(t.customer_role) + ' (' + esc(t.order_channel) + ')</small></td>' +
                '<td>' + esc(t.product_name) + '</td>' +
                '<td style="color:#F59E0B;font-weight:700;">' + '★'.repeat(parseInt(t.rating, 10) || 5) + '</td>' +
                '<td>“' + esc(t.comment) + '”</td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openTestimonialModal(' + parseInt(t.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteTestimonialItem(' + parseInt(t.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openTestimonialModal = function (id) {
        const testi = id ? state.testimonials.find(function (t) { return parseInt(t.id, 10) === parseInt(id, 10); }) : null;
        const t = testi || {
            customer_name: '',
            customer_role: 'Pelanggan Setia',
            rating: 5,
            product_name: 'Madu Hutan Liar Sumbawa',
            order_channel: 'Shopee Official Store',
            comment: '',
            sort_order: state.testimonials.length + 1
        };

        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Nama Pelanggan *</label>' +
                    '<input type="text" name="customer_name" value="' + esc(t.customer_name) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Keterangan / Kota Pelanggan</label>' +
                    '<input type="text" name="customer_role" value="' + esc(t.customer_role) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Produk yang Dibeli</label>' +
                    '<input type="text" name="product_name" value="' + esc(t.product_name) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Kanal Pembelian</label>' +
                    '<select name="order_channel">' +
                        '<option value="Shopee Official Store"' + (t.order_channel === 'Shopee Official Store' ? ' selected' : '') + '>Shopee Official Store</option>' +
                        '<option value="Pemesanan via WhatsApp CS"' + (t.order_channel === 'Pemesanan via WhatsApp CS' ? ' selected' : '') + '>Pemesanan via WhatsApp CS</option>' +
                        '<option value="Order Non-Marketplace (CS)"' + (t.order_channel === 'Order Non-Marketplace (CS)' ? ' selected' : '') + '>Order Non-Marketplace (CS)</option>' +
                    '</select>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Rating Bintang (1 - 5)</label>' +
                    '<input type="number" name="rating" min="1" max="5" value="' + esc(t.rating) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Urutan Tampil</label>' +
                    '<input type="number" name="sort_order" value="' + esc(t.sort_order) + '">' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Isi Komentar / Testimoni *</label>' +
                    '<textarea name="comment" rows="3" required>' + esc(t.comment) + '</textarea>' +
                '</div>' +
            '</div>';

        openAdmModal(testi ? 'Edit Testimoni' : 'Tambah Testimoni Baru', html, function (formData) {
            const action = testi ? 'update_testimonial' : 'create_testimonial';
            if (testi) formData.id = testi.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (testi) {
                        state.testimonials = state.testimonials.map(function (item) {
                            return parseInt(item.id, 10) === parseInt(testi.id, 10) ? res.testimonial : item;
                        });
                    } else {
                        state.testimonials.push(res.testimonial);
                    }
                    renderTestimonialsTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteTestimonialItem = function (id) {
        if (!confirm('Hapus testimoni pelanggan ini?')) return;
        apiCall('delete_testimonial', { id: id }).then(function (res) {
            if (res.success) {
                state.testimonials = state.testimonials.filter(function (t) { return parseInt(t.id, 10) !== parseInt(id, 10); });
                renderTestimonialsTable();
                showToast(res.message, false);
            }
        });
    };

    // 12. INQUIRIES / NON-MARKETPLACE ORDERS CRUD
    function renderInquiriesTable() {
        const tbody = document.getElementById('inquiriesTableBody');
        if (!tbody) return;
        if (state.inquiries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;">Belum ada pesanan non-marketplace.</td></tr>';
            return;
        }
        tbody.innerHTML = state.inquiries.map(function (inq) {
            const cleanPhone = String(inq.customer_phone || '').replace(/[^0-9]/g, '').replace(/^0/, '62');
            const waReplyUrl = cleanPhone
                ? 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent('Halo Kak ' + inq.customer_name + ', kami dari CS Madu Nusantara menindaklanjuti ' + inq.inquiry_type + ' (#ORD-' + inq.id + ') untuk produk ' + inq.product_name + '.')
                : '#';

            const voucherBadge = inq.voucher_code
                ? '<br><small style="color:#92400E;background:#FEF3C7;padding:2px 6px;border-radius:4px;font-weight:700;">Voucher: ' + esc(inq.voucher_code) + ' (-' + formatRupiah(inq.discount_amount) + ')</small>'
                : '';

            return '<tr>' +
                '<td><strong>#ORD-' + esc(inq.id) + '</strong><br><small>' + esc(inq.created_at) + '</small></td>' +
                '<td><strong>' + esc(inq.customer_name) + '</strong><br><small>' + esc(inq.customer_phone) + ' • ' + esc(inq.customer_city) + '</small></td>' +
                '<td>' + esc(inq.inquiry_type) + '</td>' +
                '<td><strong>' + esc(inq.product_name) + '</strong> (' + esc(inq.quantity) + ' pcs)' + voucherBadge + '<br><small style="color:#6B7280;">' + esc(inq.message) + '</small></td>' +
                '<td><strong>' + formatRupiah(inq.total_estimate) + '</strong></td>' +
                '<td><span class="enc-status-chip">' + esc(inq.status) + '</span></td>' +
                '<td>' +
                    '<div style="display:flex;gap:6px;flex-wrap:wrap;">' +
                        (cleanPhone ? '<a href="' + esc(waReplyUrl) + '" target="_blank" class="btn-adm btn-adm-sm btn-wa-sm">Balas WA</a>' : '') +
                        '<button type="button" class="btn-adm btn-adm-sm btn-edit" onclick="openInquiryModal(' + parseInt(inq.id, 10) + ')">Edit</button>' +
                        '<button type="button" class="btn-adm btn-adm-sm btn-delete" onclick="deleteInquiryItem(' + parseInt(inq.id, 10) + ')">Hapus</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        updateCounters();
    }

    window.openInquiryModal = function (id) {
        const inq = id ? state.inquiries.find(function (i) { return parseInt(i.id, 10) === parseInt(id, 10); }) : null;
        const item = inq || {
            customer_name: '',
            customer_phone: '',
            customer_city: '',
            inquiry_type: 'Pesan Non-Marketplace',
            product_name: 'Madu Hutan Liar Sumbawa (500gr)',
            quantity: 1,
            voucher_code: '',
            discount_amount: 0,
            total_estimate: 115000,
            status: 'Baru',
            message: ''
        };

        const html =
            '<div class="adm-form-grid-2">' +
                '<div class="adm-field">' +
                    '<label>Nama Pelanggan *</label>' +
                    '<input type="text" name="customer_name" value="' + esc(item.customer_name) + '" required>' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Nomor HP / WhatsApp</label>' +
                    '<input type="text" name="customer_phone" value="' + esc(item.customer_phone) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Kota Pengiriman</label>' +
                    '<input type="text" name="customer_city" value="' + esc(item.customer_city) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Tipe Layanan</label>' +
                    '<select name="inquiry_type">' +
                        '<option value="Pesan Non-Marketplace"' + (item.inquiry_type === 'Pesan Non-Marketplace' ? ' selected' : '') + '>Pesan Non-Marketplace</option>' +
                        '<option value="Tanya Customer Service"' + (item.inquiry_type === 'Tanya Customer Service' ? ' selected' : '') + '>Tanya Customer Service</option>' +
                    '</select>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Rincian Produk</label>' +
                    '<input type="text" name="product_name" value="' + esc(item.product_name) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Jumlah Total (Qty)</label>' +
                    '<input type="number" name="quantity" value="' + esc(item.quantity) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Total Estimasi (Rp)</label>' +
                    '<input type="number" name="total_estimate" value="' + esc(item.total_estimate) + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Kode Voucher</label>' +
                    '<input type="text" name="voucher_code" value="' + esc(item.voucher_code || '') + '">' +
                '</div>' +
                '<div class="adm-field">' +
                    '<label>Status Pesanan</label>' +
                    '<select name="status">' +
                        '<option value="Baru"' + (item.status === 'Baru' ? ' selected' : '') + '>Baru</option>' +
                        '<option value="Diproses"' + (item.status === 'Diproses' ? ' selected' : '') + '>Diproses</option>' +
                        '<option value="Selesai"' + (item.status === 'Selesai' ? ' selected' : '') + '>Selesai</option>' +
                        '<option value="Dibatalkan"' + (item.status === 'Dibatalkan' ? ' selected' : '') + '>Dibatalkan</option>' +
                    '</select>' +
                '</div>' +
                '<div class="adm-field adm-col-full">' +
                    '<label>Pesan / Catatan</label>' +
                    '<textarea name="message" rows="3">' + esc(item.message) + '</textarea>' +
                '</div>' +
            '</div>';

        openAdmModal(inq ? 'Edit Pesanan #ORD-' + inq.id : 'Catat Pesanan Non-Marketplace Baru', html, function (formData) {
            const action = inq ? 'update_inquiry' : 'create_inquiry_admin';
            if (inq) formData.id = inq.id;
            apiCall(action, formData).then(function (res) {
                if (res.success) {
                    if (inq) {
                        state.inquiries = state.inquiries.map(function (x) {
                            return parseInt(x.id, 10) === parseInt(inq.id, 10) ? res.inquiry : x;
                        });
                    } else {
                        state.inquiries.unshift(res.inquiry);
                    }
                    renderInquiriesTable();
                    window.closeAdmModal();
                    showToast(res.message, false);
                }
            });
        });
    };

    window.deleteInquiryItem = function (id) {
        if (!confirm('Hapus data pesanan/pertanyaan ini?')) return;
        apiCall('delete_inquiry', { id: id }).then(function (res) {
            if (res.success) {
                state.inquiries = state.inquiries.filter(function (i) { return parseInt(i.id, 10) !== parseInt(id, 10); });
                renderInquiriesTable();
                showToast(res.message, false);
            }
        });
    };

    // 13. SECURITY & ENCRYPTED PASSWORD MANAGEMENT
    const newPassInput = document.getElementById('newPassInput');
    const strengthFill = document.getElementById('passStrengthFill');
    const strengthText = document.getElementById('passStrengthText');

    if (newPassInput && strengthFill && strengthText) {
        newPassInput.addEventListener('input', function () {
            const val = newPassInput.value;
            let score = 0;
            if (val.length >= 8) score += 25;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
            if (/[0-9]/.test(val)) score += 25;
            if (/[^A-Za-z0-9]/.test(val)) score += 25;

            strengthFill.style.width = score + '%';
            if (score <= 25) {
                strengthFill.style.background = '#DC2626';
                strengthText.textContent = 'Kekuatan Password: Lemah (Gunakan minimal 8 karakter + simbol)';
            } else if (score <= 75) {
                strengthFill.style.background = '#F59E0B';
                strengthText.textContent = 'Kekuatan Password: Sedang';
            } else {
                strengthFill.style.background = '#16A34A';
                strengthText.textContent = 'Kekuatan Password: Sangat Kuat (Siap Dienkripsi AES-256-GCM)';
            }
        });
    }

    const secForm = document.getElementById('securityUpdateForm');
    if (secForm) {
        secForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(secForm);
            const payload = {};
            fd.forEach(function (v, k) { payload[k] = v; });

            apiCall('update_credentials', payload).then(function (res) {
                if (res.success) {
                    showToast(res.message, false);
                    secForm.querySelector('input[name="current_password"]').value = '';
                    secForm.querySelector('input[name="new_password"]').value = '';
                    secForm.querySelector('input[name="confirm_password"]').value = '';
                    if (strengthFill) strengthFill.style.width = '0%';
                } else {
                    showToast(res.message || 'Gagal memperbarui password.', true);
                }
            });
        });
    }

    // Initial Render
    renderProductsTable();
    renderVouchersTable();
    renderCertificatesTable();
    renderFaqsTable();
    renderFeaturesTable();
    renderArticlesTable();
    renderTeamTable();
    renderTestimonialsTable();
    renderInquiriesTable();
})();
