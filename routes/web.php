<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderHistoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\OrderDetailController;

/*
|--------------------------------------------------------------------------
| Publik
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'landing'])->name('landing');
Route::get('/beranda', [HomeController::class, 'beranda'])->name('beranda');

Route::get('/katalog', [CatalogController::class, 'index'])->name('katalog.index');
Route::get('/katalog/{product}', [CatalogController::class, 'show'])->name('katalog.show');

/*
|--------------------------------------------------------------------------
| Autentikasi (hanya untuk tamu)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Keranjang (session, boleh untuk tamu)
|--------------------------------------------------------------------------
*/
Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
Route::post('/keranjang', [CartController::class, 'add'])->name('cart.add');
Route::patch('/keranjang/{row}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/keranjang/{row}', [CartController::class, 'remove'])->name('cart.remove');

/*
|--------------------------------------------------------------------------
| Checkout & riwayat (tamu tetap bisa, sesuai FE lama)
|--------------------------------------------------------------------------
*/
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/riwayat-pesanan', [OrderHistoryController::class, 'index'])->name('orders.history');
Route::get('/pesanan/{order}', [OrderDetailController::class, 'show'])->name('orders.show');
Route::get('/pesanan/{order}/struk', [OrderDetailController::class, 'receipt'])->name('orders.receipt');

/*
|--------------------------------------------------------------------------
| Pembayaran (API)
|--------------------------------------------------------------------------
*/
// Halaman Pembayaran Pelanggan
Route::get('/payments/{order}', [PaymentController::class, 'show'])->name('payment.show');
// Membuat token pembayaran (lewat web agar session/CSRF/otorisasi terbaca)
Route::post('/payments/{order}/token', [PaymentController::class, 'generateToken'])->name('payment.token');
// Polling status pembayaran (di web.php agar session/auth terbaca)
Route::get('/payments/{order}/status', [PaymentController::class, 'status'])->name('payment.status');

// Webhook Midtrans (tanpa auth; CSRF dikecualikan di bootstrap/app.php)
Route::post('/payments/midtrans/notification', [PaymentController::class, 'notification']);

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('shipping')->group(function () {
    Route::get('/destinations', [ShippingController::class, 'destinations'])
        ->middleware('throttle:20,1');

    Route::get('/rates', [ShippingController::class, 'rates'])
        ->middleware('throttle:30,1');
});


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:ADMIN'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/produk', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/produk/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/produk', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/produk/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/produk/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/produk/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/stok', [StockController::class, 'index'])->name('stock.index');

        Route::get('/pesanan', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/pesanan/{order}/struk', [AdminOrderController::class, 'receipt'])->name('orders.receipt');
        Route::patch('/pesanan/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('/pesanan/{order}/lunas', [AdminOrderController::class, 'markPaid'])->name('orders.paid');
        Route::get('/laporan-penjualan', [ReportController::class, 'daily'])
            ->name('reports.daily');
        Route::get('/laporan-penjualan/mingguan', [ReportController::class, 'weekly'])
            ->name('reports.weekly');
        Route::get('/laporan-penjualan/bulanan', [ReportController::class, 'monthly'])
            ->name('reports.monthly');
        Route::get('/pengeluaran', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/pengeluaran/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/pengeluaran', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/pengeluaran/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/pengeluaran/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/pengeluaran/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });
