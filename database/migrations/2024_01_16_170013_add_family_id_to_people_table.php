<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('family_id')->after('name')->nullable()->constrained('families')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            //
        });
    }
};
