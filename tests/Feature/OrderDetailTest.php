<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function makeOrder(?User $owner = null, array $override = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'user_id' => $owner?->id,
            'customer_name' => 'Budi Test',
            'delivery_type' => 'antar',
            'shipping_fee' => 10000,
            'subtotal' => 50000,
            'total_amount' => 60000,
        ], $override));

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => Product::factory()->create(['name' => 'Matcha Uji'])->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'sweetness' => '50%',
            'note' => 'Es sedikit',
        ]);

        return $order;
    }

    public function test_admin_can_view_order_detail(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Matcha Uji')
            ->assertSee('50%')
            ->assertSee('Es sedikit')
            ->assertSee('Rp 60.000');
    }

    public function test_customer_cannot_open_admin_order_detail(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $order = $this->makeOrder($customer);

        $this->actingAs($customer)->get(route('admin.orders.show', $order))->assertForbidden();
    }

    public function test_owner_can_view_own_order_detail(): void
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $order = $this->makeOrder($owner);

        $this->actingAs($owner)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Matcha Uji');
    }

    public function test_other_user_gets_403_on_order_detail_and_receipt(): void
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $other = User::factory()->create(['role' => 'CUSTOMER']);
        $order = $this->makeOrder($owner);

        $this->actingAs($other)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($other)->get(route('orders.receipt', $order))->assertForbidden();
    }

    public function test_guest_can_view_only_orders_in_their_session(): void
    {
        $order = $this->makeOrder();
        $foreign = $this->makeOrder();

        $this->withSession(['guest_orders' => [$order->id]])
            ->get(route('orders.show', $order))->assertOk();

        $this->withSession(['guest_orders' => [$order->id]])
            ->get(route('orders.show', $foreign))->assertForbidden();

        // Tanpa session tamu (browser lain) -> ditolak.
        $this->flushSession();
        $this->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_receipt_shows_items_total_and_paid_stamp(): void
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $order = $this->makeOrder($owner, ['payment_status' => 'PAID', 'paid_at' => now()]);

        $this->actingAs($owner)
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Matcha Uji')
            ->assertSee('Rp 60.000')
            ->assertSee('LUNAS');
    }

    public function test_receipt_shows_free_shipping_label_for_pickup(): void
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $order = $this->makeOrder($owner, [
            'delivery_type' => 'ambil',
            'shipping_fee' => 0,
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);

        $this->actingAs($owner)
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('Gratis')
            ->assertSee('BELUM LUNAS');
    }

    public function test_admin_can_open_receipt(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->makeOrder();

        $this->actingAs($admin)->get(route('admin.orders.receipt', $order))->assertOk()->assertSee($order->code);
    }
}