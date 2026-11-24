<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'ADMIN']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Beli bubuk matcha',
            'category' => 'Bahan Baku',
            'amount' => 150000,
            'expense_date' => today()->toDateString(),
            'notes' => 'Supplier A',
        ], $override);
    }

    public function test_customer_cannot_access_expenses(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->get(route('admin.expenses.index'))->assertForbidden();
    }

    public function test_admin_can_create_expense(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.expenses.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'title' => 'Beli bubuk matcha',
            'amount' => 150000,
            'user_id' => $admin->id,
        ]);
    }

    public function test_amount_must_be_positive_and_date_not_in_future(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.expenses.store'), $this->payload(['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->admin())
            ->post(route('admin.expenses.store'), $this->payload(['expense_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('expense_date');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_admin_can_update_and_delete_expense(): void
    {
        $admin = $this->admin();
        $expense = Expense::create($this->payload() + ['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->put(route('admin.expenses.update', $expense), $this->payload(['amount' => 200000]))
            ->assertRedirect();
        $this->assertSame(200000, $expense->fresh()->amount);

        $this->actingAs($admin)
            ->delete(route('admin.expenses.destroy', $expense))
            ->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_category_summary_equals_sum_of_list(): void
    {
        $admin = $this->admin();

        Expense::create($this->payload(['amount' => 100000, 'expense_date' => '2026-11-03']));
        Expense::create($this->payload(['amount' => 50000, 'expense_date' => '2026-11-10']));
        Expense::create($this->payload(['category' => 'Sewa', 'amount' => 300000, 'expense_date' => '2026-11-05']));
        Expense::create($this->payload(['amount' => 999000, 'expense_date' => '2026-10-31'])); // bulan lain

        $response = $this->actingAs($admin)
            ->get(route('admin.expenses.index', ['month' => '2026-11']))
            ->assertOk();

        $this->assertSame(450000, $response->viewData('monthTotal'));
        $this->assertSame('Sewa', $response->viewData('topCategory')->category);
        $this->assertEquals(
            $response->viewData('monthTotal'),
            $response->viewData('byCategory')->sum('total')
        );
    }

    public function test_category_filter_only_filters_list(): void
    {
        $admin = $this->admin();

        Expense::create($this->payload(['amount' => 100000, 'expense_date' => '2026-11-03']));
        Expense::create($this->payload(['category' => 'Sewa', 'amount' => 300000, 'expense_date' => '2026-11-05']));

        $response = $this->actingAs($admin)
            ->get(route('admin.expenses.index', ['month' => '2026-11', 'category' => 'Sewa']))
            ->assertOk();

        $this->assertCount(1, $response->viewData('expenses'));
        $this->assertSame(400000, $response->viewData('monthTotal'));
    }

    public function test_low_stock_notification_is_marked_once_and_reset(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Reguler',
            'additional_price' => 0,
            'stock' => 3,
        ]);
        $alert = LowStockAlert::create(['product_id' => $product->id, 'min_threshold' => 10, 'is_notified' => false]);

        $service = app(StockAlertService::class);

        $this->assertCount(1, $service->sync());   // pertama kali: alert baru
        $this->assertTrue($alert->fresh()->is_notified);
        $this->assertCount(0, $service->sync());   // reload: tidak diulang

        $variant->update(['stock' => 50]);         // stok naik
        $this->assertCount(0, $service->sync());
        $this->assertFalse($alert->fresh()->is_notified); // reset
    }
}