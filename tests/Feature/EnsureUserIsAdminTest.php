<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'admin'])->get('/__admin_only_probe', fn () => 'ok');
    }

    public function test_blocks_non_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/__admin_only_probe')->assertForbidden();
    }

    public function test_allows_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/__admin_only_probe')->assertOk();
    }

    public function test_is_admin_can_be_set_via_mass_assignment(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $user->update(['is_admin' => true]);

        $this->assertTrue($user->fresh()->is_admin);
    }
}
