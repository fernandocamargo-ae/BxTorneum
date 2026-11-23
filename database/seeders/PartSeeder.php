<?php

namespace Database\Seeders;

use App\Support\PartCatalog;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    public function run(): void
    {
        PartCatalog::addMissing();
    }
}
