<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamLogoTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeam(User $captain): Team
    {
        $team = Team::create(['name' => 'Dragones', 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);

        return $team;
    }

    public function test_any_member_can_upload_the_team_logo(): void
    {
        Storage::fake('public');
        $team = $this->makeTeam(User::factory()->create());
        $subcaptain = User::factory()->create();
        $team->members()->create(['user_id' => $subcaptain->id, 'role' => 'subcaptain']);

        $this->actingAs($subcaptain)
            ->post("/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertRedirect();

        $team->refresh();
        $this->assertNotNull($team->logo_path);
        Storage::disk('public')->assertExists($team->logo_path);
    }

    public function test_a_non_member_cannot_upload_the_team_logo(): void
    {
        Storage::fake('public');
        $team = $this->makeTeam(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post("/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('x.png')])
            ->assertForbidden();

        $this->assertNull($team->fresh()->logo_path);
    }

    public function test_the_logo_must_be_an_image(): void
    {
        Storage::fake('public');
        $captain = User::factory()->create();
        $team = $this->makeTeam($captain);

        $this->actingAs($captain)
            ->post("/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('logo');
    }

    public function test_a_member_can_remove_the_team_logo(): void
    {
        Storage::fake('public');
        $captain = User::factory()->create();
        $team = $this->makeTeam($captain);
        $team->update(['logo_path' => UploadedFile::fake()->image('l.png')->store('team-logos', 'public')]);
        $path = $team->logo_path;

        $this->actingAs($captain)->delete("/teams/{$team->id}/logo")->assertRedirect();

        $this->assertNull($team->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
