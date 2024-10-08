<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->renameColumn('user_id', 'family_id');
            $table->dropColumn('featured_users');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->renameColumn('family_id', 'user_id');
            $table->json('featured_users')->nullable();
        });
    }
};
