<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role uniqueness per team is enforced in the app (join uses the first open role,
        // the captain swaps roles). Dropping the DB unique lets a role swap run as two plain
        // updates — no fragile temporary value that could get stuck mid-swap.
        // The team_id FK leans on the composite unique index for coverage; give it its
        // own index first so MySQL will let the unique be dropped.
        Schema::table('members', function (Blueprint $table) {
            $table->index('team_id');
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unique(['team_id', 'role']);
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
        });
    }
};
