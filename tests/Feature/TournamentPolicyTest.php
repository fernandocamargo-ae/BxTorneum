<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TournamentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    public function test_admin_manages_both_types(): void
    {
        $admin = $this->user(['is_admin' => true, 'is_guest' => false]);

        $this->assertTrue(Gate::forUser($admin)->allows('manage', new Tournament(['type' => 'official'])));
        $this->assertTrue(Gate::forUser($admin)->allows('manage', new Tournament(['type' => 'clandestine'])));
    }

    public function test_non_admin_manages_clandestine_but_not_official(): void
    {
        $amateur = $this->user(['is_admin' => false, 'is_guest' => false]);

        $this->assertTrue(Gate::forUser($amateur)->allows('manage', new Tournament(['type' => 'clandestine'])));
        $this->assertFalse(Gate::forUser($amateur)->allows('manage', new Tournament(['type' => 'official'])));
    }

    public function test_guest_manages_nothing_and_creates_nothing(): void
    {
        $guest = $this->user(['is_admin' => false, 'is_guest' => true]);

        $this->assertFalse(Gate::forUser($guest)->allows('manage', new Tournament(['type' => 'clandestine'])));
        $this->assertFalse(Gate::forUser($guest)->allows('createClandestine', Tournament::class));
        $this->assertFalse(Gate::forUser($guest)->allows('createOfficial', Tournament::class));
    }

    public function test_create_permissions(): void
    {
        $admin = $this->user(['is_admin' => true, 'is_guest' => false]);
        $amateur = $this->user(['is_admin' => false, 'is_guest' => false]);

        $this->assertTrue(Gate::forUser($admin)->allows('createOfficial', Tournament::class));
        $this->assertFalse(Gate::forUser($amateur)->allows('createOfficial', Tournament::class));

        $this->assertTrue(Gate::forUser($admin)->allows('createClandestine', Tournament::class));
        $this->assertTrue(Gate::forUser($amateur)->allows('createClandestine', Tournament::class));
    }
}
