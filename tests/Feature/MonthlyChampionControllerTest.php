<?php

namespace Tests\Feature;

use App\Models\MonthlyChampion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonthlyChampionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_is_public_and_needs_no_login(): void
    {
        MonthlyChampion::create(['month' => 'Agosto 2026', 'champion_nickname' => 'Ricardo', 'image_path' => 'monthly-champions/x.jpg']);

        $this->get('/campeones-mensuales')->assertInertia(fn ($page) => $page
            ->component('MonthlyChampions/Index')
            ->has('champions', 1)
            ->where('champions.0.month', 'Agosto 2026')
            ->where('champions.0.champion_nickname', 'Ricardo')
        );
    }

    public function test_guests_cannot_add_a_monthly_champion(): void
    {
        $this->post('/campeones-mensuales', [
            'month' => 'Septiembre 2026',
            'image' => UploadedFile::fake()->image('champion.jpg'),
        ])->assertRedirect('/login');
    }

    public function test_non_admin_cannot_add_a_monthly_champion(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/campeones-mensuales', [
                'month' => 'Septiembre 2026',
                'image' => UploadedFile::fake()->image('champion.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_add_a_monthly_champion(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/campeones-mensuales', [
                'month' => 'Septiembre 2026',
                'champion_nickname' => 'Ricardo',
                'image' => UploadedFile::fake()->image('champion.jpg'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('monthly_champions', ['month' => 'Septiembre 2026', 'champion_nickname' => 'Ricardo']);

        $champion = MonthlyChampion::first();
        Storage::disk('public')->assertExists($champion->image_path);
    }

    public function test_image_is_required_to_add_a_monthly_champion(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/campeones-mensuales', ['month' => 'Septiembre 2026'])
            ->assertSessionHasErrors('image');
    }
}
