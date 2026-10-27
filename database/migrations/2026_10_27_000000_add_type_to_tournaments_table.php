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
            $table->enum('type', ['official', 'clandestine'])->default('official')->after('name');
        });

        // Every tournament that existed until now was official — mark them so.
        DB::table('tournaments')->update(['type' => 'official']);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
