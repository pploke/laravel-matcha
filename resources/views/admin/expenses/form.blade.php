@php $isEdit = $expense->exists; @endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    :title="$isEdit ? 'Ubah Pengeluaran' : 'Catat Pengeluaran'"
    subtitle="Isi detail pengeluaran operasional">
    <div class="mx-auto w-full max-w-2xl">
        <a href="{{ route('admin.expenses.index') }}"
            class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-matcha-700 no-underline transition hover:text-matcha-900">
            <i class="bi bi-arrow-left"></i> Kembali ke daftar pengeluaran
        </a>

        <form method="POST"
            action="{{ $isEdit ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}"
            class="card-soft p-5 sm:p-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label-matcha" for="title">Judul <span class="text-red-500">*</span></label>
                    <input id="title" name="title" required maxlength="255"
                        value="{{ old('title', $expense->title) }}" class="input-matcha"
                        placeholder="Contoh: Beli bubuk matcha 1 kg">
                </div>

                <div>
                    <label class="label-matcha" for="category">Kategori <span class="text-red-500">*</span></label>
                    <select id="category" name="category" required class="select-matcha w-full">
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $item)
                            <option value="{{ $item }}" @selected(old('category', $expense->category) === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label-matcha" for="amount">Nominal (Rp) <span class="text-red-500">*</span></label>
                    <input id="amount" name="amount" type="number" min="1" step="1" required
                        value="{{ old('amount', $expense->amount) }}" class="input-matcha" placeholder="150000">
                </div>

                <div>
                    <label class="label-matcha" for="expense_date">Tanggal <span class="text-red-500">*</span></label>
                    <input id="expense_date" name="expense_date" type="date" required max="{{ today()->toDateString() }}"
                        value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" class="input-matcha">
                </div>

                <div class="sm:col-span-2">
                    <label class="label-matcha" for="notes">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" class="input-matcha"
                        placeholder="Opsional">{{ old('notes', $expense->notes) }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn-matcha">
                    <i class="bi bi-check2"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengeluaran' }}
                </button>
                <a href="{{ route('admin.expenses.index') }}" class="btn-outline-matcha no-underline">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.dashboard>