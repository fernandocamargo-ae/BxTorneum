<?php

use App\Support\PartCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Additive only: existing parts (and the decks that use them) are left as they are.
     */
    public function up(): void
    {
        PartCatalog::addMissing();
    }

    public function down(): void
    {
        // Parts may already be in use by decks, so they are not removed.
    }
};
