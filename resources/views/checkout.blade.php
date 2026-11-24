<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Checkout" subtitle="Pilih diantar atau ambil sendiri">
    @php
        $initialType = old('type', 'antar');
        $initialShipping = $initialType === 'antar' ? $ongkir : 0;
        $initialTotal = $subtotal + $initialShipping;
        $initialMethod = old('payment_method', \App\Models\Order::PAYMENT_METHOD_CASH);
        $paymentMethods = [
            [
                'key' => \App\Models\Order::PAYMENT_METHOD_CASH,
                'icon' => 'bi-cash-coin',
                'title' => 'Tunai',
                'desc' => 'Bayar di gerai atau saat pesanan diantar (COD)',
            ],
            [
                'key' => \App\Models\Order::PAYMENT_METHOD_QRIS,
                'icon' => 'bi-qr-code',
                'title' => 'QRIS',
                'desc' => 'Scan QRIS melalui Midtrans',
            ],
            [
                'key' => \App\Models\Order::PAYMENT_METHOD_BANK_TRANSFER,
                'icon' => 'bi-bank',
                'title' => 'Transfer Bank',
                'desc' => 'Virtual Account melalui Midtrans',
            ],
        ];
    @endphp

    @if (empty($rows))
        <div class="card-soft mx-auto max-w-lg p-10 text-center">
            <p class="text-sm text-stone-500">Belum ada menu di keranjang.</p>
            <a href="{{ route('katalog.index') }}" class="btn-matcha mt-4 no-underline">Pilih Menu</a>
        </div>
    @else
        <form method="POST" action="{{ route('checkout.store') }}" class="w-full" id="checkout-form"
            data-subtotal="{{ (int) $subtotal }}" data-ongkir="{{ (int) $ongkir }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="card-soft p-5 sm:p-6">
                        <h2 class="text-base font-bold text-matcha-900">Konfirmasi Pesanan</h2>
                        <p class="text-xs text-stone-500">Pilih diantar atau ambil sendiri, lalu lengkapi datanya.</p>

                        <h3 class="mt-5 text-sm font-bold text-matcha-900">1. Jenis Pesanan</h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['key' => 'antar', 'icon' => 'bi-bicycle', 'title' => 'Diantar', 'desc' => 'Pesanan diantar ke alamatmu'],
                                ['key' => 'ambil', 'icon' => 'bi-bag-check', 'title' => 'Ambil sendiri', 'desc' => 'Ambil langsung di gerai'],
                            ] as $option)
                                <label class="group/opt cursor-pointer">
                                    <input type="radio" name="type" value="{{ $option['key'] }}" data-type
                                        @checked($initialType === $option['key']) class="peer sr-only">
                                    <span class="flex items-center gap-3 rounded-xl border border-matcha-200 bg-white p-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:border-matcha-500 hover:shadow-soft peer-checked:border-matcha-700 peer-checked:bg-matcha-50 peer-checked:ring-1 peer-checked:ring-matcha-700">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-matcha-100 text-lg text-matcha-700 transition-colors duration-300 group-has-[:checked]/opt:bg-matcha-700 group-has-[:checked]/opt:text-white">
                                            <i class="bi {{ $option['icon'] }}"></i>
                                        </span>
                                        <span>
                                            <span class="block text-sm font-semibold text-matcha-900">{{ $option['title'] }}</span>
                                            <span class="block text-xs text-stone-500">{{ $option['desc'] }}</span>
                                            <span class="block text-xs font-semibold text-matcha-700">
                                                {{ $option['key'] === 'antar' ? 'Ongkir sesuai tujuan & kurir' : 'Gratis ongkir' }}
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <h3 class="mt-6 text-sm font-bold text-matcha-900">
                            2. <span data-label-antar>Alamat Pengiriman</span><span data-label-ambil hidden>Data Pengambilan</span>
                        </h3>

                        <div id="checkout-fields" class="mt-3 grid animate-fade-up gap-3 md:grid-cols-2">
                            <div>
                                <label class="label-matcha !text-xs" for="name">
                                    <span data-label-antar>Nama penerima</span><span data-label-ambil hidden>Nama pengambil</span>
                                </label>
                                <input id="name" name="name" required value="{{ old('name', auth()->user()->name ?? '') }}"
                                    class="input-matcha" placeholder="Nama lengkap">
                            </div>
                            <div>
                                <label class="label-matcha !text-xs" for="phone">Nomor telepon</label>
                                <input id="phone" name="phone" type="tel" required value="{{ old('phone') }}"
                                    class="input-matcha" placeholder="08xxxxxxxxxx">
                            </div>

                            <div class="md:col-span-2" data-antar>
                                <label class="label-matcha !text-xs" for="address">Alamat lengkap</label>
                                <input id="address" name="address" value="{{ old('address') }}"
                                    class="input-matcha" placeholder="Jalan, nomor rumah, kota, kode pos">
                            </div>

                            <div class="md:col-span-2 space-y-3" data-antar data-shipping-panel>
                                @if ($originLabel)
                                    <p class="flex items-start gap-2 rounded-xl bg-matcha-50 px-3 py-2 text-[11px] text-stone-600">
                                        <i class="bi bi-geo-alt-fill mt-0.5 shrink-0 text-matcha-700"></i>
                                        <span>Dikirim dari: <span class="font-semibold text-matcha-900">{{ $originLabel }}</span></span>
                                    </p>
                                @endif

                                <div>
                                    <label class="label-matcha !text-xs" for="destination-search">Cari tujuan pengiriman</label>
                                    <div class="relative">
                                        <input id="destination-search" type="search" autocomplete="off"
                                            class="input-matcha" placeholder="Minimal 3 karakter, mis. Arjosari"
                                            data-destination-search>
                                        <div id="destination-results" data-destination-results hidden
                                            class="absolute inset-x-0 top-full z-20 mt-1 max-h-60 overflow-y-auto rounded-xl border border-matcha-200 bg-white p-1 shadow-soft"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-stone-400">Cari berdasarkan nama kecamatan, kota, atau kode pos.</p>
                                    @error('shipping_destination_id')
                                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="destination-selected" data-destination-selected hidden
                                    class="flex items-center justify-between gap-3 rounded-xl border border-matcha-300 bg-matcha-50 px-3 py-2.5 text-sm">
                                    <span class="flex min-w-0 items-start gap-2 text-matcha-900">
                                        <i class="bi bi-check-circle-fill mt-0.5 shrink-0 text-matcha-700"></i>
                                        <span class="truncate font-semibold" data-destination-selected-label></span>
                                    </span>
                                    <button type="button" data-destination-clear
                                        class="shrink-0 text-xs font-semibold text-matcha-700 underline">Ubah</button>
                                </div>

                                <input type="hidden" name="shipping_destination_id" data-shipping-destination-id
                                    value="{{ old('shipping_destination_id') }}">
                                <input type="hidden" name="shipping_destination_label" data-shipping-destination-label
                                    value="{{ old('shipping_destination_label') }}">
                                <input type="hidden" name="shipping_postal_code" data-shipping-postal-code
                                    value="{{ old('shipping_postal_code') }}">
                                <input type="hidden" name="shipping_courier" data-shipping-courier
                                    value="{{ old('shipping_courier') }}">

                                <div data-rates-panel>
                                    <div class="flex items-center justify-between">
                                        <label class="label-matcha !mb-0 !text-xs">Pilih kurir & layanan</label>
                                        <span class="text-[11px] text-stone-400" data-rates-hint>Pilih tujuan terlebih dahulu.</span>
                                    </div>

                                    <div data-rates-loading hidden class="mt-2 flex items-center gap-2 rounded-xl bg-stone-50 px-3 py-3 text-xs text-stone-500">
                                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-matcha-200 border-t-matcha-700"></span>
                                        Mencari tarif pengiriman...
                                    </div>

                                    <div data-rates-empty hidden class="mt-2 rounded-xl border border-dashed border-matcha-200 px-3 py-3 text-xs text-stone-500">
                                        Tidak ada layanan pengiriman untuk tujuan ini. Coba tujuan lain.
                                    </div>

                                    <div data-rates-error hidden class="mt-2 flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs text-rose-700">
                                        <span data-rates-error-message>Gagal memuat tarif.</span>
                                        <button type="button" data-rates-retry class="shrink-0 font-bold underline">Coba lagi</button>
                                    </div>

                                    <div data-rates-list class="mt-2 grid gap-2"></div>

                                    @error('shipping_service')
                                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <input type="hidden" name="shipping_service" data-shipping-service
                                        value="{{ old('shipping_service') }}">
                                </div>
                            </div>

                            <div data-ambil hidden>
                                <label class="label-matcha !text-xs" for="pickup">Waktu ambil</label>
                                <select id="pickup" name="pickup" class="input-matcha">
                                    @foreach ($pickupOptions as $option)
                                        <option @selected(old('pickup') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div data-ambil hidden>
                                <div class="label-matcha !text-xs">Ambil di</div>
                                <div class="flex items-center gap-2 rounded-xl border border-matcha-200 bg-matcha-50/60 px-3.5 py-2.5 text-sm font-semibold text-matcha-900">
                                    <i class="bi bi-geo-alt-fill text-matcha-700"></i>Gerai Telang · Surabaya
                                </div>
                            </div>
                        </div>

                        <h3 class="mt-6 text-sm font-bold text-matcha-900">3. Metode Pembayaran</h3>
                        <p class="text-xs text-stone-500">Metode yang dipilih tidak dapat diubah setelah pesanan dibuat.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            @foreach ($paymentMethods as $method)
                                <label class="group/pay cursor-pointer">
                                    <input type="radio" name="payment_method" value="{{ $method['key'] }}" data-payment-method
                                        @checked($initialMethod === $method['key']) class="peer sr-only">
                                    <span class="flex h-full items-start gap-3 rounded-xl border border-matcha-200 bg-white p-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:border-matcha-500 hover:shadow-soft peer-checked:border-matcha-700 peer-checked:bg-matcha-50 peer-checked:ring-1 peer-checked:ring-matcha-700">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-matcha-100 text-base text-matcha-700 transition-colors duration-300 group-has-[:checked]/pay:bg-matcha-700 group-has-[:checked]/pay:text-white">
                                            <i class="bi {{ $method['icon'] }}"></i>
                                        </span>
                                        <span>
                                            <span class="block text-sm font-semibold text-matcha-900">{{ $method['title'] }}</span>
                                            <span class="block text-xs text-stone-500">{{ $method['desc'] }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="lg:col-span-4">
                    @guest
                        <div class="mb-3 rounded-2xl border border-matcha-200 bg-matcha-50 p-4 text-xs text-stone-600">
                            <div class="mb-1 text-sm font-bold text-matcha-900"><i class="bi bi-stars mr-1 text-matcha-700"></i>Simpan riwayat di akunmu</div>
                            <a href="{{ route('login') }}" class="font-semibold text-matcha-700">Masuk</a> atau
                            <a href="{{ route('register') }}" class="font-semibold text-matcha-700">daftar</a>
                            agar pesanan tersimpan permanen. Kamu juga bisa lanjut tanpa akun, riwayat tersimpan di browser ini.
                        </div>
                    @endguest

                    <div class="card-soft p-5">
                        <h3 class="text-sm font-bold text-matcha-900">Ringkasan</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-stone-500">{{ collect($rows)->sum('qty') }} item pesanan</dt>
                                <dd class="font-semibold">Rp {{ number_format($subtotal, 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">Ongkir</dt>
                                <dd class="font-semibold" data-shipping-label>Rp {{ number_format($initialShipping, 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">Jenis pesanan</dt>
                                <dd class="font-semibold" data-type-label>
                                    {{ $initialType === 'antar' ? 'Diantar' : 'Ambil sendiri' }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">Metode pembayaran</dt>
                                <dd class="font-semibold" data-payment-method-label>
                                    {{ \App\Models\Order::PAYMENT_METHOD_LABELS[$initialMethod] ?? 'Tunai' }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex items-center justify-between border-t border-matcha-100 pt-4">
                            <span class="text-sm font-semibold text-matcha-900">Total pembayaran</span>
                            <span class="text-lg font-extrabold text-matcha-700" data-total>Rp {{ number_format($initialTotal, 0, ',', '.') }}</span>
                        </div>

                        <ul class="mt-4 space-y-1.5 border-t border-matcha-100 pt-4 text-xs text-stone-500">
                            @foreach ($rows as $row)
                                <li class="flex justify-between gap-2">
                                    <span class="truncate">{{ $row['qty'] }}× {{ $row['name'] }}</span>
                                    <span class="shrink-0">Rp {{ number_format((int) $row['subtotal'], 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <button type="submit" class="btn-matcha mt-4 w-full">
                            <i class="bi bi-bag-check"></i> <span data-submit-label>Buat Pesanan</span>
                        </button>
                        <p data-shipping-submit-error hidden class="mt-2 text-center text-xs font-semibold text-rose-600"></p>
                        <p class="mt-2 text-center text-[11px] text-stone-400" data-payment-note>
                            Pembayaran tunai dilakukan langsung di gerai / saat pesanan diantar.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    @endif
</x-layouts.dashboard>
