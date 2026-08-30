<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'owner'])->get('/__owner_only_probe', fn () => 'ok');
    }

    public function test_blocks_regular_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_owner' => false]);

        $this->actingAs($admin)->get('/__owner_only_probe')->assertForbidden();
    }

    public function test_allows_the_owner(): void
    {
        $owner = User::factory()->create(['is_owner' => true]);

        $this->actingAs($owner)->get('/__owner_only_probe')->assertOk();
    }

    public function test_is_owner_is_not_mass_assignable(): void
    {
        $user = User::factory()->create(['is_owner' => false]);

        $user->update(['is_owner' => true]);

        $this->assertFalse($user->fresh()->is_owner);
    }
}
