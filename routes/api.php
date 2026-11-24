<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Pembuatan token pembayaran dipindahkan ke routes/web.php agar memakai
| session, CSRF, dan pengecekan kepemilikan order (route: payment.token).
|
*/
