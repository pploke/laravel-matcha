<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', Rule::in(Expense::CATEGORIES)],
        ]);

        $month = Carbon::createFromFormat(
            'Y-m-d',
            ($validated['month'] ?? today()->format('Y-m')) . '-01'
        )->startOfDay();

        $category = $validated['category'] ?? null;

        $monthQuery = fn () => Expense::query()
            ->whereBetween('expense_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);

        // Daftar (filter bulan + kategori), terbaru di atas
        $expenses = $monthQuery()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->with('user')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Rekap per kategori (selalu seluruh bulan, tidak ikut filter kategori)
        $byCategory = $monthQuery()
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $monthTotal = (int) $byCategory->sum('total');
        $topCategory = $byCategory->first();

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'byCategory' => $byCategory,
            'monthTotal' => $monthTotal,
            'topCategory' => $topCategory,
            'monthValue' => $month->format('Y-m'),
            'monthLabel' => $month->copy()->locale('id')->translatedFormat('F Y'),
            'category' => $category,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function create()
    {
        return view('admin.expenses.form', [
            'expense' => new Expense(['expense_date' => today()]),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;

        Expense::create($data);

        return redirect()
            ->route('admin.expenses.index', ['month' => substr($data['expense_date'], 0, 7)])
            ->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.form', [
            'expense' => $expense,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);

        $expense->update($data);

        return redirect()
            ->route('admin.expenses.index', ['month' => substr($data['expense_date'], 0, 7)])
            ->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        $month = $expense->expense_date->format('Y-m');
        $expense->delete();

        return redirect()
            ->route('admin.expenses.index', ['month' => $month])
            ->with('success', 'Pengeluaran berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'title.required' => 'Judul pengeluaran wajib diisi.',
            'category.required' => 'Kategori wajib dipilih.',
            'category.in' => 'Kategori tidak valid.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.integer' => 'Nominal harus berupa bilangan bulat (tanpa titik/koma).',
            'amount.min' => 'Nominal harus lebih dari 0.',
            'expense_date.required' => 'Tanggal wajib diisi.',
            'expense_date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
        ]);
    }
}