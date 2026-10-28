<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('format', ['individual', 'teams'])->default('individual')->after('type');
        });

        // Everything that exists today is an individual tournament.
        DB::table('tournaments')->update(['format' => 'individual']);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
