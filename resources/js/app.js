import './bootstrap';

/* ==========================================================================
   Interaksi halaman (pengganti js/ui.js, js/shell.js, dan event handler FE).
   Semua memakai atribut data-*, jadi Blade tinggal menaruh atributnya.
   ========================================================================== */

// Elemen .reveal muncul halus saat masuk layar
function revealAll() {
    const els = document.querySelectorAll('.reveal:not(.is-visible)');
    if (typeof IntersectionObserver === 'undefined') {
        els.forEach((el) => el.classList.add('is-visible'));
        return;
    }
    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 },
    );
    els.forEach((el) => io.observe(el));
}

document.addEventListener('DOMContentLoaded', revealAll);

document.addEventListener('click', (e) => {
    // Drawer sidebar mobile
    if (e.target.closest('[data-drawer-open]')) document.getElementById('drawer')?.classList.remove('hidden');
    if (e.target.closest('[data-drawer-close]')) document.getElementById('drawer')?.classList.add('hidden');

    // Grup menu sidebar (dropdown)
    const toggle = e.target.closest('[data-group-toggle]');
    if (toggle) {
        const key = toggle.dataset.groupToggle;
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        document.querySelectorAll(`[data-group="${key}"]`).forEach((g) => {
            g.querySelector('[data-group-toggle]').setAttribute('aria-expanded', open);
            g.querySelector('[data-chevron]').classList.toggle('rotate-180', open);
            const panel = g.querySelector('[data-panel]');
            panel.classList.toggle('grid-rows-[1fr]', open);
            panel.classList.toggle('opacity-100', open);
            panel.classList.toggle('grid-rows-[0fr]', !open);
            panel.classList.toggle('opacity-0', !open);
        });
    }

    // Slider produk di landing
    const slide = e.target.closest('[data-slider]');
    if (slide) {
        document.getElementById('produk-slider')?.scrollBy({ left: Number(slide.dataset.slider) * 280, behavior: 'smooth' });
    }

    // Scroll halus ke bagian di landing
    const scroll = e.target.closest('[data-scroll-to]');
    if (scroll) {
        e.preventDefault();
        document.getElementById(scroll.dataset.scrollTo)?.scrollIntoView({ behavior: 'smooth' });
    }

    // Tampil/sembunyi sandi
    const pw = e.target.closest('[data-toggle-password]');
    if (pw) {
        const input = document.getElementById(pw.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        pw.setAttribute('aria-label', show ? 'Sembunyikan sandi' : 'Tampilkan sandi');
        pw.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
    }

    // Stepper jumlah di keranjang (kirim form PATCH)
    const step = e.target.closest('[data-qty-step]');
    if (step) {
        const form = step.closest('form');
        const input = form.querySelector('input[name="qty"]');
        const max = Number(step.dataset.qtyMax || 99);
        const next = Math.min(max, Math.max(1, Number(input.value) + Number(step.dataset.qtyStep)));
        if (next !== Number(input.value)) {
            input.value = next;
            form.submit();
        }
    }

    // Modal <dialog>
    const open = e.target.closest('[data-open-dialog]');
    if (open) document.getElementById(open.dataset.openDialog)?.showModal();
    const close = e.target.closest('[data-close-dialog]');
    if (close) close.closest('dialog')?.close();
    if (e.target instanceof HTMLDialogElement && e.target.classList.contains('modal-matcha')) e.target.close();
});

// Konfirmasi sebelum submit (data-confirm="pesan")
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset?.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
});

// Katalog: pilih produk, atur varian, hitung harga
function initKatalog() {
    const root = document.getElementById('katalog');
    if (!root) return;
    const money = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const cards = root.querySelectorAll('[data-select-product]');
    const panels = root.querySelectorAll('[data-product-panel]');

    const select = (id) => {
        cards.forEach((c) => c.classList.toggle('ring-2', c.dataset.selectProduct === String(id)));
        cards.forEach((c) => c.classList.toggle('ring-matcha-600', c.dataset.selectProduct === String(id)));
        panels.forEach((p) => {
            const on = p.dataset.productPanel === String(id);
            p.hidden = !on;
            if (on) recalc(p);
        });
    };

    const recalc = (panel) => {
        const base = Number(panel.dataset.base);
        const checked = panel.querySelector('input[name="product_variant_id"]:checked');
        const add = checked ? Number(checked.dataset.add) : 0;
        panel.querySelector('[data-total]').textContent = money(base + add);
    };

    cards.forEach((c) =>
        c.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            select(c.dataset.selectProduct);
        }),
    );
    panels.forEach((p) => p.addEventListener('change', () => recalc(p)));

    const wanted = new URLSearchParams(location.search).get('produk');
    const first = root.querySelector('[data-select-product]');
    select(wanted && root.querySelector(`[data-select-product="${wanted}"]`) ? wanted : first?.dataset.selectProduct);
}

// Admin produk: filter kategori
function initFilterKategori() {
    const sel = document.getElementById('filter-kategori');
    if (!sel) return;
    sel.addEventListener('change', () => {
        document.querySelectorAll('[data-kategori]').forEach((tr) => {
            tr.hidden = sel.value !== '' && tr.dataset.kategori !== sel.value;
        });
    });
}

// Checkout: antar/ambil, cari tujuan, ambil tarif kurir, lalu hitung ulang total
function initCheckout() {
    const form = document.getElementById('checkout-form');
    if (!form) return;
    const subtotal = Number(form.dataset.subtotal);
    const money = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const fields = document.getElementById('checkout-fields');

    const el = {
        destinationSearch: form.querySelector('[data-destination-search]'),
        destinationResults: form.querySelector('[data-destination-results]'),
        destinationSelected: form.querySelector('[data-destination-selected]'),
        destinationSelectedLabel: form.querySelector('[data-destination-selected-label]'),
        destinationClear: form.querySelector('[data-destination-clear]'),
        destinationId: form.querySelector('[data-shipping-destination-id]'),
        destinationLabel: form.querySelector('[data-shipping-destination-label]'),
        postalCode: form.querySelector('[data-shipping-postal-code]'),
        courier: form.querySelector('[data-shipping-courier]'),
        service: form.querySelector('[data-shipping-service]'),
        ratesPanel: form.querySelector('[data-rates-panel]'),
        ratesHint: form.querySelector('[data-rates-hint]'),
        ratesLoading: form.querySelector('[data-rates-loading]'),
        ratesEmpty: form.querySelector('[data-rates-empty]'),
        ratesError: form.querySelector('[data-rates-error]'),
        ratesErrorMessage: form.querySelector('[data-rates-error-message]'),
        ratesRetry: form.querySelector('[data-rates-retry]'),
        ratesList: form.querySelector('[data-rates-list]'),
        shippingLabel: form.querySelector('[data-shipping-label]'),
        submitError: form.querySelector('[data-shipping-submit-error]'),
        submitButton: form.querySelector('button[type="submit"]'),
    };

    const paymentLabels = {
        CASH: 'Tunai',
        QRIS: 'QRIS',
        BANK_TRANSFER: 'Transfer Bank',
    };
    const paymentNotes = {
        CASH: 'Pembayaran tunai dilakukan langsung di gerai / saat pesanan diantar.',
        QRIS: 'Kamu akan diarahkan ke Midtrans untuk scan QRIS setelah pesanan dibuat.',
        BANK_TRANSFER: 'Kamu akan diarahkan ke Midtrans untuk pembayaran Virtual Account setelah pesanan dibuat.',
    };

    let searchTimer = null;
    let searchController = null;
    let ratesController = null;
    let selectedDestination = null;
    let selectedRate = null;
    let lastResults = [];
    let submitLabel = 'Buat Pesanan';

    const hide = (node) => { if (node) node.hidden = true; };
    const show = (node) => { if (node) node.hidden = false; };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[ch]));
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const isAntar = () => (form.querySelector('[data-type]:checked')?.value || 'antar') === 'antar';

    const setButtonLoading = (loading) => {
        if (!el.submitButton) return;
        el.submitButton.disabled = loading;
        const label = el.submitButton.querySelector('[data-submit-label]');
        if (label) label.textContent = loading ? 'Memproses...' : submitLabel;
    };

    const showSubmitError = (message) => {
        if (!el.submitError) return;
        el.submitError.textContent = message;
        show(el.submitError);
    };

    const sync = (animate) => {
        const antar = isAntar();
        const method = form.querySelector('[data-payment-method]:checked')?.value || 'CASH';
        const isCash = method === 'CASH';
        const shipping = antar && selectedRate ? Number(selectedRate.cost) : 0;

        form.querySelectorAll('[data-antar]').forEach((node) => (node.hidden = !antar));
        form.querySelectorAll('[data-ambil]').forEach((node) => (node.hidden = antar));
        form.querySelectorAll('[data-label-antar]').forEach((node) => (node.hidden = !antar));
        form.querySelectorAll('[data-label-ambil]').forEach((node) => (node.hidden = antar));
        document.getElementById('address').required = antar;

        if (el.shippingLabel) {
            el.shippingLabel.textContent = !antar ? 'Gratis' : (selectedRate ? money(shipping) : 'Belum dipilih');
        }
        form.querySelector('[data-type-label]').textContent = antar ? 'Diantar' : 'Ambil sendiri';
        form.querySelector('[data-total]').textContent = money(subtotal + shipping);
        form.querySelector('[data-payment-method-label]').textContent = paymentLabels[method] || 'Tunai';
        form.querySelector('[data-payment-note]').textContent = paymentNotes[method] || paymentNotes.CASH;

        submitLabel = isCash ? 'Buat Pesanan' : 'Buat Pesanan & Bayar';
        if (form.dataset.submitting !== '1') setButtonLoading(false);

        if (animate && fields) {
            fields.classList.remove('animate-fade-up');
            void fields.offsetWidth;
            fields.classList.add('animate-fade-up');
        }
    };

    const resetShipping = () => {
        if (searchController) { searchController.abort(); searchController = null; }
        if (ratesController) { ratesController.abort(); ratesController = null; }
        clearTimeout(searchTimer);
        selectedDestination = null;
        selectedRate = null;
        lastResults = [];
        [el.destinationId, el.destinationLabel, el.postalCode, el.courier, el.service].forEach((node) => {
            if (node) node.value = '';
        });
        if (el.ratesList) el.ratesList.innerHTML = '';
        [el.destinationResults, el.destinationSelected, el.ratesLoading, el.ratesEmpty, el.ratesError, el.submitError]
            .forEach(hide);
        if (el.ratesHint) el.ratesHint.textContent = 'Pilih tujuan terlebih dahulu.';
    };

    const setRatesState = (state, message) => {
        [el.ratesLoading, el.ratesEmpty, el.ratesError].forEach(hide);
        if (state === 'loading') {
            show(el.ratesLoading);
            if (el.ratesHint) el.ratesHint.textContent = 'Menghubungi kurir...';
        } else if (state === 'empty') {
            show(el.ratesEmpty);
            if (el.ratesHint) el.ratesHint.textContent = 'Tidak ada layanan';
        } else if (state === 'error') {
            if (el.ratesErrorMessage) el.ratesErrorMessage.textContent = message || 'Gagal memuat tarif.';
            show(el.ratesError);
            if (el.ratesHint) el.ratesHint.textContent = 'Gagal memuat';
        }
    };

    const renderRates = (rates, oldCourier, oldService) => {
        if (!el.ratesList) return;
        el.ratesList.innerHTML = '';

        rates.forEach((rate, index) => {
            const courier = String(rate.courier || '');
            const service = String(rate.service || '');
            const cost = Number(rate.cost || 0);
            const etdText = String(rate.etd || '').replace(/\s*days?\s*$/i, '').trim();
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-matcha-200 bg-white px-3 py-2.5 transition hover:border-matcha-500 hover:bg-matcha-50/50';
            label.innerHTML = `
                <span class="flex min-w-0 items-center gap-2.5">
                    <input type="radio" name="shipping_option" class="accent-matcha-700" data-rate-radio
                        data-courier="${escapeHtml(courier)}" data-service="${escapeHtml(service)}"
                        data-cost="${cost}" data-etd="${escapeHtml(rate.etd || '')}">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-matcha-900">${escapeHtml(rate.courier_name || courier.toUpperCase())} &middot; ${escapeHtml(rate.service_name || service)}</span>
                        <span class="block text-[11px] text-stone-500">Estimasi ${escapeHtml(etdText || '-')} hari</span>
                    </span>
                </span>
                <span class="shrink-0 text-sm font-bold text-matcha-700">${money(cost)}</span>`;
            el.ratesList.appendChild(label);
        });

        const restore = [...el.ratesList.querySelectorAll('[data-rate-radio]')]
            .find((radio) => radio.dataset.courier === oldCourier && radio.dataset.service === oldService);
        if (restore) {
            restore.checked = true;
            restore.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const loadRates = async () => {
        if (!selectedDestination || !el.ratesList) return;
        if (ratesController) ratesController.abort();
        ratesController = new AbortController();
        const controller = ratesController;
        const oldCourier = el.courier?.value || '';
        const oldService = el.service?.value || '';
        selectedRate = null;
        if (el.courier) el.courier.value = '';
        if (el.service) el.service.value = '';
        el.ratesList.innerHTML = '';
        hide(el.submitError);
        setRatesState('loading');
        sync(false);

        try {
            const params = new URLSearchParams({ type: 'antar', destination_id: selectedDestination.id });
            const response = await fetch(`/shipping/rates?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Gagal memuat tarif pengiriman.');

            const rates = Array.isArray(payload.rates) ? payload.rates : [];
            if (!rates.length) {
                setRatesState('empty');
                sync(false);
                return;
            }

            renderRates(rates, oldCourier, oldService);
            [el.ratesLoading, el.ratesEmpty, el.ratesError].forEach(hide);
            if (el.ratesHint) el.ratesHint.textContent = `${rates.length} layanan tersedia`;
            sync(false);
        } catch (error) {
            if (error.name === 'AbortError') return;
            setRatesState('error', error.message);
            sync(false);
        }
    };

    const renderDestinations = (items) => {
        if (!el.destinationResults) return;
        el.destinationResults.innerHTML = '';
        lastResults = items;
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-2 text-xs text-stone-500';
            empty.textContent = 'Tujuan tidak ditemukan. Coba kata kunci lain.';
            el.destinationResults.appendChild(empty);
            show(el.destinationResults);
            return;
        }
        items.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full flex-col items-start rounded-lg px-3 py-2 text-left text-xs transition hover:bg-matcha-50';
            button.dataset.destinationIndex = String(index);
            button.innerHTML = `<span class="font-semibold text-matcha-900">${escapeHtml(item.label)}</span>
                <span class="text-stone-500">${escapeHtml(item.zip_code || '')}</span>`;
            el.destinationResults.appendChild(button);
        });
        show(el.destinationResults);
    };

    el.destinationSearch?.addEventListener('input', (event) => {
        const query = event.target.value.trim();
        clearTimeout(searchTimer);
        if (query.length < 3) {
            if (searchController) { searchController.abort(); searchController = null; }
            hide(el.destinationResults);
            return;
        }
        searchTimer = setTimeout(async () => {
            if (searchController) searchController.abort();
            searchController = new AbortController();
            const controller = searchController;
            try {
                const response = await fetch(`/shipping/destinations?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    signal: controller.signal,
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Gagal mencari tujuan.');
                renderDestinations(Array.isArray(payload.data) ? payload.data : []);
            } catch (error) {
                if (error.name === 'AbortError') return;
                hide(el.destinationResults);
                showSubmitError('Gagal mencari tujuan pengiriman.');
            }
        }, 500);
    });

    el.destinationResults?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-destination-index]');
        if (!button) return;
        const item = lastResults[Number(button.dataset.destinationIndex)];
        if (!item) return;
        selectedDestination = {
            id: String(item.id || ''),
            label: String(item.label || ''),
            zip_code: String(item.zip_code || ''),
        };
        if (el.destinationId) el.destinationId.value = selectedDestination.id;
        if (el.destinationLabel) el.destinationLabel.value = selectedDestination.label;
        if (el.postalCode) el.postalCode.value = selectedDestination.zip_code;
        if (el.destinationSelectedLabel) el.destinationSelectedLabel.textContent = selectedDestination.label;
        show(el.destinationSelected);
        hide(el.destinationResults);
        if (el.destinationSearch) el.destinationSearch.value = '';
        hide(el.submitError);
        loadRates();
    });

    el.destinationClear?.addEventListener('click', () => {
        resetShipping();
        if (el.destinationSearch) { el.destinationSearch.value = ''; el.destinationSearch.focus(); }
        sync(false);
    });

    el.ratesList?.addEventListener('change', (event) => {
        const radio = event.target.closest('[data-rate-radio]');
        if (!radio) return;
        selectedRate = {
            courier: radio.dataset.courier,
            service: radio.dataset.service,
            cost: Number(radio.dataset.cost),
            etd: radio.dataset.etd || '',
        };
        if (el.courier) el.courier.value = selectedRate.courier;
        if (el.service) el.service.value = selectedRate.service;
        hide(el.submitError);
        sync(false);
    });

    el.ratesRetry?.addEventListener('click', loadRates);

    document.addEventListener('click', (event) => {
        if (!el.destinationResults || el.destinationResults.hidden) return;
        if (event.target.closest('[data-destination-search]') || event.target.closest('[data-destination-results]')) return;
        hide(el.destinationResults);
    });

    // Guard submit: antar wajib punya tujuan + layanan. Idempotensi tetap dijaga server.
    form.addEventListener('submit', (event) => {
        if (isAntar() && (!el.destinationId?.value || !el.courier?.value || !el.service?.value)) {
            event.preventDefault();
            showSubmitError('Pilih tujuan pengiriman dan layanan kurir terlebih dahulu.');
            el.ratesPanel?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        setButtonLoading(true);
    });

    form.querySelectorAll('[data-type]').forEach((radio) => radio.addEventListener('change', () => {
        if (radio.checked && radio.value === 'ambil') resetShipping();
        sync(true);
    }));
    form.querySelectorAll('[data-payment-method]').forEach((radio) => radio.addEventListener('change', () => sync(false)));

    // Pulihkan pilihan saat validasi server mengembalikan halaman checkout.
    const oldDestinationId = el.destinationId?.value || '';
    if (oldDestinationId && isAntar()) {
        selectedDestination = {
            id: oldDestinationId,
            label: el.destinationLabel?.value || oldDestinationId,
            zip_code: el.postalCode?.value || '',
        };
        if (el.destinationSelectedLabel) el.destinationSelectedLabel.textContent = selectedDestination.label;
        show(el.destinationSelected);
        loadRates();
    }

    sync(false);
}

document.addEventListener('DOMContentLoaded', () => {
    initCheckout();
    initKatalog();
    initFilterKategori();
});
