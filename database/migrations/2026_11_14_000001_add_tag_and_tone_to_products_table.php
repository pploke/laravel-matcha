<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori (tag) dan warna ilustrasi cadangan (tone) untuk tiap menu.
     * Dipakai di kartu produk dan filter kategori di halaman admin.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tag')->nullable()->after('description');
            $table->string('tone')->nullable()->after('tag');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tag', 'tone']);
        });
    }
};
