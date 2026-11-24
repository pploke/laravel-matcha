<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(
        string $completedAt,
        int $total,
        int $quantity,
        string $status = Order::STATUS_COMPLETED
    ): Order {
        $order = Order::factory()->create([
            'order_status' => $status,
            'payment_status' => 'PAID',
            'subtotal' => $total,
            'shipping_fee' => 0,
            'total_amount' => $total,
            'completed_at' => $completedAt,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    private function seedNovember(): void
    {
        // Masuk rekap November 2026
        $this->makeOrder('2026-11-03 10:00:00', 50000, 2);
        $this->makeOrder('2026-11-03 15:30:00', 30000, 1);
        $this->makeOrder('2026-11-20 09:00:00', 40000, 3);

        // Tidak boleh ikut: di luar bulan, atau bukan COMPLETED
        $this->makeOrder('2026-10-31 23:59:59', 99000, 9);
        $this->makeOrder('2026-12-01 00:00:00', 99000, 9);
        $this->makeOrder('2026-11-10 10:00:00', 99000, 9, Order::STATUS_PROCESSING);
    }

    public function test_monthly_total_equals_sum_of_daily_rows(): void
    {
        $this->seedNovember();

        $report = app(SalesReportService::class)->monthly(2026, 11);

        $this->assertEquals(120000, $report['total_revenue']);
        $this->assertSame(3, $report['total_transactions']);
        $this->assertSame(6, $report['total_items_sold']);
        $this->assertEquals(40000, $report['average_transaction']);
        $this->assertCount(30, $report['days']);
        $this->assertEquals($report['total_revenue'], $report['days']->sum('total_revenue'));
        $this->assertEquals(
            80000,
            $report['days']->firstWhere('date', '2026-11-03')['total_revenue']
        );
    }

    public function test_weekly_report_covers_seven_days(): void
    {
        $this->seedNovember();

        // Minggu ISO ke-45 2026 = 2 - 8 November
        $report = app(SalesReportService::class)->weekly(2026, 45);

        $this->assertCount(7, $report['days']);
        $this->assertEquals(80000, $report['total_revenue']);
        $this->assertSame(2, $report['total_transactions']);
    }

    public function test_monthly_page_is_admin_only(): void
    {
        $this->get(route('admin.reports.monthly'))
            ->assertRedirect(route('login'));

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.reports.monthly'))
            ->assertForbidden();
    }

    public function test_admin_can_view_monthly_and_weekly_pages(): void
    {
        $this->withoutVite();
        $this->seedNovember();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->get(route('admin.reports.monthly', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Rekap Penjualan Bulanan')
            ->assertSee('November 2026')
            ->assertSee('Rp 120.000');

        $this->actingAs($admin)
            ->get(route('admin.reports.weekly', ['week' => '2026-W45']))
            ->assertOk()
            ->assertSee('Rekap Penjualan Mingguan')
            ->assertSee('Rp 80.000');
    }

    public function test_invalid_period_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->get(route('admin.reports.monthly', ['month' => 'bukan-bulan']))
            ->assertSessionHasErrors('month');
    }
}