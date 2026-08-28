<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /** @var array<string, array<int, string>> part type => names */
    private const PARTS = [
        'blade' => [
            'Dran Sword', 'Dran Buster', 'Dran Dagger', 'Wizard Arrow', 'Knight Shield',
            'Hells Scythe', 'Shark Edge', 'Leon Claw', 'Wyvern Gale', 'Rhino Horn',
            'Phoenix Wing', 'Whale Wave', 'Cobalt Dragoon', 'Viper Tail',
            'Aero Pegasus', 'Bear Scratch', 'Crimson Garuda', 'Dragoon Storm', 'Draciel Shield',
        ],
        'ratchet' => [
            '3-60', '3-70', '3-80', '3-85', '4-50', '4-55', '4-60', '4-70', '4-80',
            '5-50', '5-60', '5-70', '5-80', '6-60', '6-70', '6-80', '7-55', '7-60',
            '7-70', '7-80', '8-70', '8-80', '9-60', '9-65', '9-70', '9-80',
        ],
        'bit' => [
            'Flat', 'Point', 'Ball', 'Free Ball', 'Needle', 'Orb', 'Taper', 'Rush',
            'Low Flat', 'High Needle', 'Gear Flat', 'Gear Ball', 'Gear Point',
            'Metal Needle', 'Accel', 'Level',
        ],
        'lock_chip' => [
            'Dran', 'Wizard', 'Knight', 'Shark', 'Leon', 'Phoenix', 'Cobalt', 'Viper',
        ],
        'main_blade' => [
            'Sword', 'Arrow', 'Shield', 'Scythe', 'Edge', 'Claw', 'Gale', 'Horn',
            'Wing', 'Wave', 'Dragoon', 'Buster', 'Dagger', 'Tail',
        ],
        'assist_blade' => [
            'Level', 'Jaggy', 'Wedge', 'Metsu', 'Gear', 'Vertical', 'Point', 'Taper',
        ],
        'over_blade' => [
            'Dran Sword', 'Wizard Arrow', 'Knight Shield', 'Cobalt Dragoon', 'Phoenix Wing',
        ],
        'metal_blade' => [
            'Dran', 'Wizard', 'Knight', 'Cobalt', 'Phoenix', 'Shark',
        ],
    ];

    public function run(): void
    {
        foreach (self::PARTS as $type => $names) {
            foreach ($names as $name) {
                Part::firstOrCreate(['type' => $type, 'name' => $name]);
            }
        }
    }
}
