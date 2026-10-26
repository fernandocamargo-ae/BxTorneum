<?php

namespace Tests\Feature;

use App\Models\MonthlyChampion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyChampionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_is_public_and_needs_no_login(): void
    {
        MonthlyChampion::create(['month' => 'Agosto 2026', 'champion_nickname' => 'Ricardo', 'image_path' => null]);

        $this->get('/campeones-mensuales')->assertInertia(fn ($page) => $page
            ->component('MonthlyChampions/Index')
            ->has('groups', 1)
            ->where('groups.0.year', 2026)
            ->has('groups.0.champions', 1)
            ->where('groups.0.champions.0.month', 'Agosto 2026')
            ->where('groups.0.champions.0.month_name', 'Agosto')
            ->where('groups.0.champions.0.champion_nickname', 'Ricardo')
        );
    }

    public function test_months_are_grouped_by_year_desc_and_ordered_chronologically(): void
    {
        MonthlyChampion::create(['month' => 'Octubre 2026', 'champion_nickname' => 'ItsMrPanda']);
        MonthlyChampion::create(['month' => 'Enero 2026', 'champion_nickname' => 'Gustavo Santi']);
        MonthlyChampion::create(['month' => 'Enero 2027', 'champion_nickname' => 'Nuevo']);

        $this->get('/campeones-mensuales')->assertInertia(fn ($page) => $page
            ->has('groups', 2)
            // Most recent year first.
            ->where('groups.0.year', 2027)
            ->where('groups.1.year', 2026)
            // Within 2026, Enero comes before Octubre.
            ->where('groups.1.champions.0.month_name', 'Enero')
            ->where('groups.1.champions.1.month_name', 'Octubre')
        );
    }

    public function test_guests_cannot_add_a_monthly_champion(): void
    {
        $this->post('/campeones-mensuales', ['month' => 'Septiembre 2026'])
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_add_a_monthly_champion(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/campeones-mensuales', ['month' => 'Septiembre 2026'])
            ->assertForbidden();
    }

    public function test_admin_can_add_a_monthly_champion_without_a_photo(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/campeones-mensuales', [
                'month' => 'Septiembre 2026',
                'champion_nickname' => 'Ricardo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('monthly_champions', [
            'month' => 'Septiembre 2026',
            'champion_nickname' => 'Ricardo',
            'image_path' => null,
        ]);
    }

    public function test_month_is_required_to_add_a_monthly_champion(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/campeones-mensuales', ['champion_nickname' => 'Ricardo'])
            ->assertSessionHasErrors('month');
    }
}
