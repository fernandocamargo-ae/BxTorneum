<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Amateur = may create/manage clandestine tournaments. Assigned only by the owner.
            // Everyone defaults to plain "jugador" (false); admins already outrank amateurs.
            $table->boolean('is_amateur')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_amateur');
        });
    }
};
