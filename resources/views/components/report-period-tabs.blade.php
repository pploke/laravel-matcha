@props(['active' => 'monthly', 'value' => ''])

@php
    $tabs = [
        'daily' => [
            'label' => 'Harian', 'route' => 'admin.reports.daily',
            'param' => 'date', 'type' => 'date',
            'caption' => 'Pilih Tanggal Penjualan', 'now' => 'Hari Ini',
        ],
        'weekly' => [
            'label' => 'Mingguan', 'route' => 'admin.reports.weekly',
            'param' => 'week', 'type' => 'week',
            'caption' => 'Pilih Minggu', 'now' => 'Minggu Ini',
        ],
        'monthly' => [
            'label' => 'Bulanan', 'route' => 'admin.reports.monthly',
            'param' => 'month', 'type' => 'month',
            'caption' => 'Pilih Bulan', 'now' => 'Bulan Ini',
        ],
    ];
    $current = $tabs[$active] ?? $tabs['monthly'];
@endphp

<div class="card-soft space-y-4 p-5">
    <div class="inline-flex rounded-xl border border-matcha-200 bg-white p-1">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route']) }}"
                class="rounded-lg px-4 py-1.5 text-sm font-semibold no-underline transition {{ $key === $active ? 'bg-matcha-700 text-white' : 'text-matcha-800 hover:bg-matcha-100' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    <form action="{{ route($current['route']) }}" method="GET"
        class="flex flex-wrap items-end gap-3">
        <div>
            <label for="period-input" class="mb-1 block text-sm font-semibold text-matcha-800">
                {{ $current['caption'] }}
            </label>
            <input type="{{ $current['type'] }}" id="period-input"
                name="{{ $current['param'] }}" value="{{ $value }}"
                class="rounded-xl border border-matcha-200 px-3 py-2 text-sm">
        </div>

        <button type="submit" class="btn-matcha !py-2">
            <i class="bi bi-search"></i> Tampilkan
        </button>

        <a href="{{ route($current['route']) }}"
            class="rounded-xl border border-matcha-200 px-4 py-2 text-sm font-semibold text-matcha-800 no-underline">
            {{ $current['now'] }}
        </a>
    </form>
</div>