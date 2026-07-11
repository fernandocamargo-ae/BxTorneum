<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function seedTeam(): void
    {
        $team = Team::create(['name' => 'Xplosivos']);
        $captain = $team->members()->create(['role' => 'captain', 'name' => 'Ricardo']);
        $team->members()->create(['role' => 'subcaptain', 'name' => 'Cielo']);
        $team->members()->create(['role' => 'official', 'name' => 'Kristen']);
        $beyblade = $captain->beyblades()->create(['line' => 'bx', 'position' => 1]);
        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);
    }

    public function test_downloads_pdf_with_correct_password(): void
    {
        config(['report.password' => 'secret123']);
        $this->seedTeam();

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/report/pdf', ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_rejects_wrong_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->actingAs(User::factory()->create())
            ->postJson('/report/pdf', ['password' => 'nope'])->assertForbidden();
    }

    public function test_requires_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->actingAs(User::factory()->create())
            ->postJson('/report/pdf', [])->assertStatus(422);
    }

    public function test_rejects_when_no_password_configured(): void
    {
        config(['report.password' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/report/pdf', ['password' => 'anything'])->assertForbidden();
    }

    public function test_guests_are_unauthorized(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/pdf', ['password' => 'secret123'])->assertUnauthorized();
    }
}
