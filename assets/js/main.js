/**
 * MADU NUSANTARA UMKM - LANDING PAGE INTERACTIVITY
 * Features: Multi-Size Variants, Multi-Item Shopping Cart, Promo Vouchers,
 * Lab Certificate Preview, FAQ Accordion, Click Tracking, & Secret Admin Access
 */
(function () {
    'use strict';

    const data = window.MADU_DATA || {
        products: [],
        certificates: [],
        articles: [],
        vouchers: [],
        waNumber: '6281234567890'
    };

    let cart = [];
    let appliedVoucher = null;

    try {
        const saved = localStorage.getItem('madu_cart_v1');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed)) cart = parsed;
        }
    } catch (err) {
        cart = [];
    }

    function saveCart() {
        try {
            localStorage.setItem('madu_cart_v1', JSON.stringify(cart));
        } catch (err) {}
        updateCartBadges();
    }

    function formatIdr(num) {
        const val = parseInt(num, 10) || 0;
        return 'Rp ' + val.toLocaleString('id-ID');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    // 1. Click Analytics Tracking
    window.trackProductClick = function (productId, channel) {
        if (!productId) return;
        const payload = JSON.stringify({
            action: 'track_click',
            product_id: parseInt(productId, 10),
            channel: channel || 'wa'
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('api.php?action=track_click', payload);
        } else {
            fetch('api.php?action=track_click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            }).catch(function () {});
        }
    };

    // 2. Multi-Size Variant Selection on Product Cards
    window.selectProductVariant = function (productId, variantLabel, price, btnEl) {
        const card = document.getElementById('product-card-' + productId);
        if (card) {
            card.setAttribute('data-selected-variant', variantLabel);
            card.setAttribute('data-selected-price', String(price));
            card.querySelectorAll('.variant-pill').forEach(function (pill) {
                pill.classList.remove('active');
            });
            if (btnEl) btnEl.classList.add('active');
        }
        const priceEl = document.getElementById('price-display-' + productId);
        if (priceEl) priceEl.textContent = formatIdr(price);
        const weightEl = document.getElementById('weight-label-' + productId);
        if (weightEl) weightEl.textContent = variantLabel;
    };

    // 3. Multi-Item Shopping Cart & Voucher System
    function updateCartBadges() {
        const totalQty = cart.reduce(function (acc, item) { return acc + (parseInt(item.qty, 10) || 1); }, 0);
        const headerBadge = document.getElementById('headerCartCount');
        const floatBtn = document.getElementById('floatingCartBtn');
        const floatCount = document.getElementById('floatingCartCount');

        if (headerBadge) headerBadge.textContent = String(totalQty);
        if (floatCount) floatCount.textContent = String(totalQty);
        if (floatBtn) {
            floatBtn.style.display = totalQty > 0 ? 'flex' : 'none';
        }
    }

    window.addToCart = function (productId, variantLabel, price, qty) {
        const prod = (data.products || []).find(function (p) { return parseInt(p.id, 10) === parseInt(productId, 10); });
        if (!prod) return;

        const chosenVariant = variantLabel || prod.weight || '500gr';
        const chosenPrice = parseInt(price, 10) || parseInt(prod.price, 10) || 95000;
        const addQty = Math.max(1, parseInt(qty, 10) || 1);

        const existingIndex = cart.findIndex(function (c) {
            return parseInt(c.product_id, 10) === parseInt(prod.id, 10) && c.variant === chosenVariant;
        });

        if (existingIndex !== -1) {
            cart[existingIndex].qty += addQty;
        } else {
            cart.push({
                product_id: parseInt(prod.id, 10),
                name: prod.name,
                variant: chosenVariant,
                price: chosenPrice,
                qty: addQty
            });
        }

        window.trackProductClick(prod.id, 'wa');
        saveCart();
        window.openCartModal();
    };

    window.addProductToCartFromCard = function (productId) {
        const card = document.getElementById('product-card-' + productId);
        const prod = (data.products || []).find(function (p) { return parseInt(p.id, 10) === parseInt(productId, 10); });
        if (!prod) return;

        const variant = (card && card.getAttribute('data-selected-variant')) || prod.weight || '500gr';
        const price = (card && parseInt(card.getAttribute('data-selected-price'), 10)) || parseInt(prod.price, 10);
        window.addToCart(productId, variant, price, 1);
    };

    window.changeCartQty = function (idx, delta) {
        if (!cart[idx]) return;
        cart[idx].qty += delta;
        if (cart[idx].qty <= 0) {
            cart.splice(idx, 1);
        }
        saveCart();
        renderCartModalContents();
    };

    window.removeCartItem = function (idx) {
        cart.splice(idx, 1);
        saveCart();
        renderCartModalContents();
    };

    function getCartSubtotal() {
        return cart.reduce(function (sum, item) {
            return sum + ((parseInt(item.price, 10) || 0) * (parseInt(item.qty, 10) || 1));
        }, 0);
    }

    function renderCartModalContents() {
        const container = document.getElementById('cartItemsContainer');
        const subText = document.getElementById('cartSubtotalText');
        const discRow = document.getElementById('cartDiscountRow');
        const discLabel = document.getElementById('cartVoucherLabel');
        const discText = document.getElementById('cartDiscountText');
        const finalText = document.getElementById('cartFinalTotalText');
        if (!container) return;

        if (cart.length === 0) {
            const quickAddHtml = (data.products || []).slice(0, 3).map(function (p) {
                return '<button type="button" class="voucher-chip" style="padding:6px 10px;font-weight:600;" onclick="addToCart(' + parseInt(p.id, 10) + ', ' + JSON.stringify(p.weight || '500gr') + ', ' + parseInt(p.price, 10) + ', 1)">+ ' + escapeHtml(p.name) + ' (' + formatIdr(p.price) + ')</button>';
            }).join(' ');

            container.innerHTML =
                '<div style="text-align:center;padding:18px 12px;color:#4B5563;">' +
                    '<p style="margin-bottom:8px;font-weight:600;color:#111827;">Keranjang belanja Anda masih kosong.</p>' +
                    '<p style="font-size:0.8rem;margin-bottom:12px;color:#6B7280;">Klik salah satu produk di bawah untuk langsung menambahkannya ke keranjang, atau pilih ukuran di katalog:</p>' +
                    '<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-bottom:14px;">' + quickAddHtml + '</div>' +
                    '<button type="button" class="btn-apply-voucher" onclick="closeModal(\'cartCheckoutModal\'); document.getElementById(\'products\').scrollIntoView({behavior:\'smooth\'});">Lihat Semua Katalog Produk →</button>' +
                '</div>';
        } else {
            container.innerHTML = cart.map(function (item, idx) {
                const lineTotal = (parseInt(item.price, 10) || 0) * (parseInt(item.qty, 10) || 1);
                return '<div class="cart-item-row">' +
                    '<div class="cart-item-info">' +
                        '<strong>' + escapeHtml(item.name) + '</strong>' +
                        '<small>Kemasan: ' + escapeHtml(item.variant) + ' • ' + formatIdr(item.price) + ' / pcs</small>' +
                    '</div>' +
                    '<div class="cart-qty-controls">' +
                        '<button type="button" class="btn-qty-step" onclick="changeCartQty(' + idx + ', -1)">−</button>' +
                        '<strong>' + item.qty + '</strong>' +
                        '<button type="button" class="btn-qty-step" onclick="changeCartQty(' + idx + ', 1)">+</button>' +
                        '<span style="min-width:85px;text-align:right;font-weight:700;font-size:0.84rem;">' + formatIdr(lineTotal) + '</span>' +
                        '<button type="button" class="btn-cart-remove" onclick="removeCartItem(' + idx + ')">×</button>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        const subtotal = getCartSubtotal();
        let discountAmt = 0;

        if (appliedVoucher && subtotal >= (parseInt(appliedVoucher.min_purchase, 10) || 0)) {
            if (appliedVoucher.discount_type === 'percent') {
                discountAmt = Math.round(subtotal * ((parseInt(appliedVoucher.discount_value, 10) || 0) / 100));
            } else {
                discountAmt = parseInt(appliedVoucher.discount_value, 10) || 0;
            }
            discountAmt = Math.min(subtotal, discountAmt);
        } else if (appliedVoucher && subtotal < (parseInt(appliedVoucher.min_purchase, 10) || 0)) {
            appliedVoucher = null;
            const vf = document.getElementById('voucherFeedback');
            if (vf) vf.style.display = 'none';
        }

        const finalTotal = Math.max(0, subtotal - discountAmt);

        if (subText) subText.textContent = formatIdr(subtotal);
        if (discRow && discLabel && discText) {
            if (discountAmt > 0 && appliedVoucher) {
                discRow.style.display = 'flex';
                discLabel.textContent = appliedVoucher.code;
                discText.textContent = '-' + formatIdr(discountAmt);
            } else {
                discRow.style.display = 'none';
            }
        }
        if (finalText) finalText.textContent = formatIdr(finalTotal);
    }

    window.openCartModal = function () {
        const modal = document.getElementById('cartCheckoutModal');
        if (!modal) return;
        renderCartModalContents();
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    window.quickFillVoucher = function (code) {
        const input = document.getElementById('voucherCodeInput');
        if (input) {
            input.value = code;
            window.applyCartVoucher();
        }
    };

    window.applyCartVoucher = function () {
        const input = document.getElementById('voucherCodeInput');
        const feedback = document.getElementById('voucherFeedback');
        if (!input || !feedback) return;

        const code = input.value.trim().toUpperCase();
        const subtotal = getCartSubtotal();

        if (subtotal <= 0) {
            feedback.style.display = 'block';
            feedback.style.color = '#DC2626';
            feedback.textContent = 'Tambahkan produk ke keranjang terlebih dahulu sebelum memakai voucher.';
            return;
        }

        fetch('api.php?action=validate_voucher&code=' + encodeURIComponent(code) + '&subtotal=' + subtotal)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                feedback.style.display = 'block';
                if (res.valid) {
                    appliedVoucher = {
                        code: res.code,
                        discount_type: res.discount_type,
                        discount_value: res.discount_value,
                        min_purchase: 0
                    };
                    feedback.style.color = '#16A34A';
                    feedback.textContent = '✓ ' + res.message;
                } else {
                    appliedVoucher = null;
                    feedback.style.color = '#DC2626';
                    feedback.textContent = '✕ ' + (res.message || 'Voucher tidak valid.');
                }
                renderCartModalContents();
            })
            .catch(function () {
                feedback.style.display = 'block';
                feedback.style.color = '#DC2626';
                feedback.textContent = 'Gagal memeriksa kode voucher.';
            });
    };

    const cartCheckoutForm = document.getElementById('cartCheckoutForm');
    if (cartCheckoutForm) {
        cartCheckoutForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (cart.length === 0) {
                alert('Keranjang belanja Anda masih kosong. Silakan pilih produk terlebih dahulu.');
                return;
            }
            const submitBtn = document.getElementById('cartSubmitBtn');
            const feedback = document.getElementById('cartFormFeedback');
            const fd = new FormData(cartCheckoutForm);

            const payload = {
                action: 'submit_inquiry',
                inquiry_type: 'Pesan Non-Marketplace',
                customer_name: fd.get('customer_name'),
                customer_phone: fd.get('customer_phone'),
                customer_city: fd.get('customer_city'),
                message: fd.get('message'),
                voucher_code: appliedVoucher ? appliedVoucher.code : '',
                cart_items: cart
            };

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses Checkout ke WhatsApp CS...';
            }

            fetch('api.php?action=submit_inquiry', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Checkout & Kirim Rincian Pesanan ke WhatsApp CS →';
                }
                if (res.success && res.wa_url) {
                    cart = [];
                    appliedVoucher = null;
                    saveCart();
                    renderCartModalContents();
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.innerHTML = '✓ ' + escapeHtml(res.message) + ' <a href="' + escapeHtml(res.wa_url) + '" target="_blank" rel="noopener" style="text-decoration:underline;font-weight:700;">Klik di sini untuk membuka WhatsApp CS</a>.';
                    }
                    const win = window.open(res.wa_url, '_blank', 'noopener,noreferrer');
                    if (!win) {
                        window.location.href = res.wa_url;
                    }
                } else {
                    alert(res.message || 'Gagal memproses checkout.');
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Checkout & Kirim Rincian Pesanan ke WhatsApp CS →';
                }
                alert('Terjadi kesalahan jaringan.');
            });
        });
    }

    // 5. Category Filter & Product Search
    const catPills = document.querySelectorAll('.cat-pill');
    const searchInput = document.getElementById('productSearchInput');
    const productCards = document.querySelectorAll('.product-card');
    let activeCategory = 'all';

    function filterProducts() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        productCards.forEach(function (card) {
            const cat = card.getAttribute('data-category') || '';
            const searchStr = card.getAttribute('data-search') || '';
            const matchCat = activeCategory === 'all' || cat === activeCategory;
            const matchSearch = query === '' || searchStr.indexOf(query) !== -1;
            card.style.display = (matchCat && matchSearch) ? '' : 'none';
        });
    }

    catPills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            catPills.forEach(function (p) { p.classList.remove('active'); });
            pill.classList.add('active');
            activeCategory = pill.getAttribute('data-category') || 'all';
            filterProducts();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
    }

    // 6. Modal Helpers & Secret Admin Triggers
    window.closeModal = function (modalId) {
        const m = document.getElementById(modalId);
        if (m) {
            m.classList.remove('open');
            m.setAttribute('aria-hidden', 'true');
        }
    };

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                backdrop.classList.remove('open');
                backdrop.setAttribute('aria-hidden', 'true');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.open').forEach(function (m) {
                m.classList.remove('open');
            });
        }
        // Secret Admin Keyboard Shortcut: Ctrl + Shift + A (or Ctrl + Shift + L)
        if (e.ctrlKey && e.shiftKey && (e.key === 'A' || e.key === 'a' || e.key === 'L' || e.key === 'l')) {
            e.preventDefault();
            window.location.href = 'admin-login.php';
        }
    });

    const secretTrigger = document.getElementById('secretAdminTrigger');
    let clickCount = 0;
    let clickTimer = null;
    if (secretTrigger) {
        secretTrigger.addEventListener('click', function () {
            clickCount++;
            clearTimeout(clickTimer);
            if (clickCount >= 5) {
                clickCount = 0;
                window.location.href = 'admin-login.php';
            } else {
                clickTimer = setTimeout(function () { clickCount = 0; }, 1800);
            }
        });
    }

    // 7. FAQ Accordion
    window.toggleFaqItem = function (btnEl) {
        const item = btnEl ? btnEl.closest('.faq-item') : null;
        if (!item) return;
        const isOpen = item.classList.contains('open');
        item.classList.toggle('open', !isOpen);
        const icon = item.querySelector('.faq-icon');
        if (icon) icon.textContent = !isOpen ? '−' : '+';
    };

    // 8. Product Detail Modal
    window.openProductDetailModal = function (productId) {
        const prod = (data.products || []).find(function (p) { return parseInt(p.id, 10) === parseInt(productId, 10); });
        if (!prod) return;

        const container = document.getElementById('productDetailContent');
        const modal = document.getElementById('productDetailModal');
        if (!container || !modal) return;

        const benefits = String(prod.benefits || '').split('|').map(function (b) { return b.trim(); }).filter(Boolean);
        const benefitsHtml = benefits.length > 0
            ? '<div class="detail-benefits-box"><h4>Manfaat &amp; Khasiat Utama:</h4><ul>' +
              benefits.map(function (b) { return '<li>✓ ' + escapeHtml(b) + '</li>'; }).join('') +
              '</ul></div>'
            : '';

        const origHtml = parseInt(prod.original_price, 10) > parseInt(prod.price, 10)
            ? '<span class="price-original">' + formatIdr(prod.original_price) + '</span>'
            : '';

        container.innerHTML =
            '<div class="product-detail-grid">' +
                '<div class="product-detail-img">' +
                    '<img src="' + escapeHtml(prod.image_url) + '" alt="' + escapeHtml(prod.name) + '">' +
                '</div>' +
                '<div class="product-detail-info">' +
                    '<div class="product-detail-badges">' +
                        '<span class="cat-pill active">' + escapeHtml(prod.category) + '</span>' +
                        '<span class="cat-pill">' + escapeHtml(prod.badge) + '</span>' +
                        '<span class="cat-pill">' + escapeHtml(prod.weight) + '</span>' +
                    '</div>' +
                    '<h2 class="detail-title" id="detailModalTitle">' + escapeHtml(prod.name) + '</h2>' +
                    '<p class="detail-origin">Asal Panen: <strong>' + escapeHtml(prod.origin) + '</strong> • Status: <strong>' + escapeHtml(prod.stock_status) + '</strong></p>' +
                    '<div class="detail-price-box">' +
                        '<span class="price-current">' + formatIdr(prod.price) + '</span>' +
                        origHtml +
                    '</div>' +
                    '<div class="detail-desc"><p>' + escapeHtml(prod.full_desc || prod.short_desc).replace(/\n/g, '<br>') + '</p></div>' +
                    benefitsHtml +
                    '<div class="product-actions-3">' +
                        '<button type="button" class="btn btn-prod-cart" onclick="closeModal(\'productDetailModal\'); addProductToCartFromCard(' + parseInt(prod.id, 10) + ')">' +
                            '🛒 + Keranjang' +
                        '</button>' +
                        '<a href="' + escapeHtml(prod.shopee_url) + '" target="_blank" rel="noopener noreferrer" onclick="trackProductClick(' + parseInt(prod.id, 10) + ', \'shopee\')" class="btn btn-shopee">' +
                            'Beli di Shopee ↗' +
                        '</a>' +
                        '<button type="button" class="btn btn-cs-hero" onclick="closeModal(\'productDetailModal\'); openCsOrderModal(' + parseInt(prod.id, 10) + ', \'Pesan Non-Marketplace\')">' +
                            'Pesan Cepat CS' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    // 9. Certificate Preview Modal
    window.openCertificatePreviewModal = function (certId) {
        const cert = (data.certificates || []).find(function (c) { return parseInt(c.id, 10) === parseInt(certId, 10); });
        if (!cert) return;

        const container = document.getElementById('articleDetailContent');
        const modal = document.getElementById('articleDetailModal');
        if (!container || !modal) return;

        container.innerHTML =
            '<div style="border-radius:12px;overflow:hidden;margin-bottom:18px;border:1px solid #E5E7EB;">' +
                '<img src="' + escapeHtml(cert.image_url) + '" alt="' + escapeHtml(cert.title) + '" style="width:100%;object-fit:contain;">' +
            '</div>' +
            '<span class="section-eyebrow">NOMOR DOKUMEN: ' + escapeHtml(cert.cert_number) + '</span>' +
            '<h2 id="articleModalTitle" style="font-family:var(--font-heading);font-size:1.45rem;margin-bottom:6px;">' + escapeHtml(cert.title) + '</h2>' +
            '<p style="font-size:0.86rem;color:var(--secondary);font-weight:700;margin-bottom:14px;">Lembaga Penerbit: ' + escapeHtml(cert.issuer) + '</p>' +
            '<div style="background:#F0FDFA;border:1px solid rgba(41,138,129,0.25);padding:14px;border-radius:8px;font-size:0.92rem;margin-bottom:20px;">' +
                '<strong>Ringkasan Hasil Pengujian &amp; Standar Mutu:</strong><br>' +
                escapeHtml(cert.test_summary) +
            '</div>' +
            '<button type="button" class="btn btn-primary" onclick="closeModal(\'articleDetailModal\')">Tutup Pratinjau</button>';

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    // 10. Article Detail Modal
    window.openArticleModal = function (articleId) {
        const art = (data.articles || []).find(function (a) { return parseInt(a.id, 10) === parseInt(articleId, 10); });
        if (!art) return;

        const container = document.getElementById('articleDetailContent');
        const modal = document.getElementById('articleDetailModal');
        if (!container || !modal) return;

        container.innerHTML =
            '<div style="border-radius:12px;overflow:hidden;margin-bottom:18px;max-height:280px;">' +
                '<img src="' + escapeHtml(art.image_url) + '" alt="' + escapeHtml(art.title) + '" style="width:100%;object-fit:cover;">' +
            '</div>' +
            '<span class="section-eyebrow">' + escapeHtml(art.category) + ' • ' + escapeHtml(art.published_date) + '</span>' +
            '<h2 id="articleModalTitle" style="font-family:var(--font-heading);font-size:1.55rem;margin-bottom:10px;">' + escapeHtml(art.title) + '</h2>' +
            '<p style="font-size:0.84rem;color:var(--text-muted);margin-bottom:18px;">Oleh: <strong>' + escapeHtml(art.author) + '</strong></p>' +
            '<div style="font-size:0.96rem;line-height:1.75;color:var(--text-dark);margin-bottom:24px;">' +
                escapeHtml(art.content || art.excerpt).replace(/\n/g, '<br><br>') +
            '</div>' +
            '<div style="display:flex;gap:12px;flex-wrap:wrap;">' +
                '<button type="button" class="btn btn-primary" onclick="closeModal(\'articleDetailModal\')">Kembali ke Halaman</button>' +
                '<button type="button" class="btn btn-cs-hero" onclick="closeModal(\'articleDetailModal\'); openCsOrderModal(null, \'Tanya Customer Service\')">Konsultasi dengan CS</button>' +
            '</div>';

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    // 11. Non-Marketplace Customer Service Quick Order & Chat Modal
    window.toggleCsFormMode = function (mode) {
        const tabOrder = document.getElementById('tabOrderMode');
        const tabAsk = document.getElementById('tabAskMode');
        const qtyGroup = document.getElementById('csQuantityGroup');
        const estBanner = document.getElementById('csEstimateBanner');
        const msgLabel = document.getElementById('csMessageLabel');

        if (mode === 'Tanya Customer Service') {
            if (tabOrder) tabOrder.classList.remove('active');
            if (tabAsk) tabAsk.classList.add('active');
            if (qtyGroup) qtyGroup.style.opacity = '0.5';
            if (estBanner) estBanner.style.display = 'none';
            if (msgLabel) msgLabel.textContent = 'Pertanyaan Anda untuk Customer Service *';
        } else {
            if (tabOrder) tabOrder.classList.add('active');
            if (tabAsk) tabAsk.classList.remove('active');
            if (qtyGroup) qtyGroup.style.opacity = '1';
            if (estBanner) estBanner.style.display = 'flex';
            if (msgLabel) msgLabel.textContent = 'Catatan Pesanan / Alamat Lengkap';
        }
        window.updateOrderEstimate();
    };

    window.updateOrderEstimate = function () {
        const select = document.getElementById('csProductSelect');
        const qtyInput = document.getElementById('csQuantity');
        const estVal = document.getElementById('csEstimateValue');
        if (!select || !qtyInput || !estVal) return;

        const selectedOpt = select.options[select.selectedIndex];
        const price = selectedOpt ? (parseInt(selectedOpt.getAttribute('data-price'), 10) || 0) : 0;
        const qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
        estVal.textContent = price > 0 ? formatIdr(price * qty) : 'Menunggu Konfirmasi CS';
    };

    window.openCsOrderModal = function (productId, mode) {
        const modal = document.getElementById('csOrderModal');
        const select = document.getElementById('csProductSelect');
        const feedback = document.getElementById('csFormFeedback');
        const directWaBtn = document.getElementById('csQuickDirectWaLink');
        if (!modal) return;

        if (feedback) feedback.style.display = 'none';
        if (select) {
            select.value = productId ? String(productId) : '';
            if (productId) {
                const card = document.getElementById('product-card-' + productId);
                const selectedOpt = select.options[select.selectedIndex];
                if (card && selectedOpt) {
                    const chosenVariant = card.getAttribute('data-selected-variant');
                    const chosenPrice = parseInt(card.getAttribute('data-selected-price'), 10);
                    const prod = (data.products || []).find(function (p) { return parseInt(p.id, 10) === parseInt(productId, 10); });
                    if (prod && chosenVariant && chosenPrice > 0) {
                        selectedOpt.setAttribute('data-price', String(chosenPrice));
                        selectedOpt.setAttribute('data-name', prod.name + ' (' + chosenVariant + ')');
                        selectedOpt.textContent = prod.name + ' (' + chosenVariant + ') - ' + formatIdr(chosenPrice);
                    }
                }
            }
        }

        if (directWaBtn && data.waNumber) {
            const cleanWa = String(data.waNumber).replace(/[^0-9]/g, '');
            const selectedOpt = select && select.options[select.selectedIndex];
            const prodLabel = (productId && selectedOpt) ? selectedOpt.getAttribute('data-name') : '';
            const waText = prodLabel
                ? 'Halo CS ' + (data.brandName || 'Madu Nusantara') + ', saya ingin memesan / bertanya tentang produk ' + prodLabel + '.'
                : 'Halo CS ' + (data.brandName || 'Madu Nusantara') + ', saya ingin bertanya dan berkonsultasi mengenai produk madu murni.';
            directWaBtn.href = 'https://wa.me/' + cleanWa + '?text=' + encodeURIComponent(waText);
        }

        const targetMode = mode || (productId ? 'Pesan Non-Marketplace' : 'Tanya Customer Service');
        const radios = document.querySelectorAll('input[name="inquiry_type"]');
        radios.forEach(function (r) {
            r.checked = (r.value === targetMode);
        });
        window.toggleCsFormMode(targetMode);

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const csOrderForm = document.getElementById('csOrderForm');
    if (csOrderForm) {
        csOrderForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = document.getElementById('csSubmitBtn');
            const feedback = document.getElementById('csFormFeedback');
            const select = document.getElementById('csProductSelect');
            const selectedOpt = select && select.options[select.selectedIndex];

            const formData = new FormData(csOrderForm);
            const payload = {
                action: 'submit_inquiry',
                customer_name: formData.get('customer_name'),
                customer_phone: formData.get('customer_phone'),
                customer_city: formData.get('customer_city'),
                inquiry_type: formData.get('inquiry_type'),
                product_id: formData.get('product_id'),
                product_name: selectedOpt ? selectedOpt.getAttribute('data-name') : 'Umum',
                quantity: formData.get('quantity'),
                message: formData.get('message')
            };

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses & Menghubungkan ke WhatsApp CS...';
            }

            fetch('api.php?action=submit_inquiry', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Kirim & Lanjutkan Chat ke WhatsApp CS →';
                }
                if (res.success && res.wa_url) {
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.innerHTML = '✓ ' + escapeHtml(res.message) + ' <a href="' + escapeHtml(res.wa_url) + '" target="_blank" rel="noopener" style="text-decoration:underline;font-weight:700;">Klik di sini jika WhatsApp belum terbuka</a>.';
                    }
                    const win = window.open(res.wa_url, '_blank', 'noopener,noreferrer');
                    if (!win) {
                        window.location.href = res.wa_url;
                    }
                } else {
                    alert(res.message || 'Gagal mengirim pesan.');
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Kirim & Lanjutkan Chat ke WhatsApp CS →';
                }
                alert('Terjadi gangguan jaringan. Silakan coba kembali.');
            });
        });
    }

    // 12. Header Navigation Gliding Capsule Animation, Smooth Scroll & ScrollSpy
    const siteHeader = document.getElementById('site-header');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mainNav = document.getElementById('mainNav');
    const navPillTrack = document.getElementById('navPillTrack');
    const navGlider = document.getElementById('navGlider');
    const navHoverGlider = document.getElementById('navHoverGlider');
    const navLinks = Array.from(document.querySelectorAll('.main-nav .nav-link'));
    let isClickScrolling = false;
    let clickScrollTimer = null;

    function updateNavGlider(activeLink) {
        const target = activeLink || document.querySelector('.main-nav .nav-link.active') || navLinks[0];
        if (!target || !navGlider || !navPillTrack) return;

        // Calculate offset relative to navPillTrack
        const trackRect = navPillTrack.getBoundingClientRect();
        const linkRect = target.getBoundingClientRect();
        if (trackRect.width === 0 || linkRect.width === 0) return;

        const offsetLeft = linkRect.left - trackRect.left;
        navPillTrack.classList.add('glider-ready');
        navGlider.style.width = linkRect.width + 'px';
        navGlider.style.transform = 'translate3d(' + offsetLeft + 'px, 0, 0)';
    }

    function updateHoverGlider(hoverLink) {
        if (!navHoverGlider || !navPillTrack) return;
        if (!hoverLink || hoverLink.classList.contains('active')) {
            navHoverGlider.style.opacity = '0';
            return;
        }
        const trackRect = navPillTrack.getBoundingClientRect();
        const linkRect = hoverLink.getBoundingClientRect();
        if (trackRect.width === 0 || linkRect.width === 0) return;

        const offsetLeft = linkRect.left - trackRect.left;
        navHoverGlider.style.width = linkRect.width + 'px';
        navHoverGlider.style.transform = 'translate3d(' + offsetLeft + 'px, 0, 0)';
        navHoverGlider.style.opacity = '1';
    }

    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', function () {
            const isOpen = mainNav.classList.toggle('open');
            mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    navLinks.forEach(function (link) {
        link.addEventListener('mouseenter', function () {
            updateHoverGlider(link);
        });
        link.addEventListener('mouseleave', function () {
            updateHoverGlider(null);
        });

        link.addEventListener('click', function (e) {
            const href = link.getAttribute('href') || '';
            if (!href.startsWith('#')) return;

            const targetId = href.slice(1);
            const targetSection = document.getElementById(targetId);

            // Trigger immediate active state & gliding animation from previous item to clicked item
            navLinks.forEach(function (item) {
                item.classList.remove('active', 'nav-clicked-anim');
            });
            void link.offsetWidth;
            link.classList.add('active', 'nav-clicked-anim');
            updateHoverGlider(null);
            updateNavGlider(link);

            // Close mobile dropdown if open
            if (mainNav && mainNav.classList.contains('open')) {
                mainNav.classList.remove('open');
                if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', 'false');
            }

            if (targetSection) {
                e.preventDefault();
                isClickScrolling = true;
                if (clickScrollTimer) clearTimeout(clickScrollTimer);

                const headerHeight = siteHeader ? siteHeader.offsetHeight : 76;
                const targetTop = Math.max(0, targetSection.getBoundingClientRect().top + window.pageYOffset - headerHeight + 4);

                window.scrollTo({
                    top: targetTop,
                    behavior: 'smooth'
                });

                if (history.replaceState) {
                    history.replaceState(null, '', href);
                }

                clickScrollTimer = setTimeout(function () {
                    isClickScrolling = false;
                }, 900);
            }
        });
    });

    // Position the glider on initial load and window resize
    window.addEventListener('load', function () {
        updateNavGlider();
    });
    window.addEventListener('resize', function () {
        updateNavGlider();
    });
    setTimeout(updateNavGlider, 60);

    // ScrollSpy to glide active pill when user scrolls manually
    const trackedSections = navLinks
        .map(function (link) {
            const href = link.getAttribute('href') || '';
            const id = href.startsWith('#') ? href.slice(1) : '';
            const el = id ? document.getElementById(id) : null;
            return el ? { link: link, section: el } : null;
        })
        .filter(Boolean);

    window.addEventListener('scroll', function () {
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;
        if (siteHeader) {
            siteHeader.classList.toggle('scrolled', scrollY > 20);
        }

        if (isClickScrolling || trackedSections.length === 0) return;

        const checkOffset = scrollY + 140;
        let currentItem = trackedSections[0];

        trackedSections.forEach(function (item) {
            if (item.section.offsetTop <= checkOffset) {
                currentItem = item;
            }
        });

        if (currentItem && !currentItem.link.classList.contains('active')) {
            navLinks.forEach(function (l) {
                l.classList.remove('active', 'nav-clicked-anim');
            });
            currentItem.link.classList.add('active');
            updateNavGlider(currentItem.link);
        }
    }, { passive: true });

    // 13. Secret Admin Login Access (Ctrl+Shift+L or 5x Quick Clicks on Brand Logo)
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.shiftKey && (e.key === 'L' || e.key === 'l')) {
            e.preventDefault();
            window.location.href = 'admin-login.php';
        }
    });

    let logoClicks = 0;
    let logoTimer = null;
    ['brandLogoSecret', 'secretLogoTrigger'].forEach(function (triggerId) {
        const el = document.getElementById(triggerId);
        if (!el) return;
        el.addEventListener('click', function () {
            logoClicks++;
            if (logoTimer) clearTimeout(logoTimer);
            if (logoClicks >= 5) {
                logoClicks = 0;
                window.location.href = 'admin-login.php';
            } else {
                logoTimer = setTimeout(function () {
                    logoClicks = 0;
                }, 1800);
            }
        });
    });

    updateCartBadges();
})();
