<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AvatarPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarPresetTest extends TestCase
{
    use RefreshDatabase;

    private const PRESET = 'metal_char_gingka-hagane';

    private function choose(User $user, mixed $preset)
    {
        return $this->actingAs($user)->post('/profile/avatar/preset', ['preset' => $preset]);
    }

    public function test_a_user_can_choose_a_ready_made_avatar(): void
    {
        $user = User::factory()->create();

        $this->choose($user, self::PRESET)->assertRedirect()->assertSessionHas('success', 'Avatar actualizado.');

        $this->assertSame('/images/avatars/metal_char_gingka-hagane.webp', $user->fresh()->avatar_url);
        $this->get('/profile')->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar_url', '/images/avatars/metal_char_gingka-hagane.webp'));
    }

    public function test_only_avatars_from_the_list_are_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['no-existe', '../../.env', 'preset:'.self::PRESET, '', null, ['x']] as $bad) {
            $this->choose($user, $bad)->assertSessionHasErrors('preset');
        }

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_choosing_one_replaces_the_uploaded_photo_and_deletes_its_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('yo.jpg')]);
        $photo = $user->fresh()->avatar_path;

        $this->choose($user, self::PRESET);

        Storage::disk('public')->assertMissing($photo);
        $this->assertSame('/images/avatars/metal_char_gingka-hagane.webp', $user->fresh()->avatar_url);
    }

    public function test_uploading_a_photo_afterwards_replaces_the_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->choose($user, self::PRESET);

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('yo.jpg')])->assertRedirect();

        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
        $this->assertStringContainsString('/storage/avatars/', $user->fresh()->avatar_url);
    }

    public function test_it_can_be_removed_like_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->choose($user, self::PRESET);

        $this->actingAs($user)->delete('/profile/avatar')->assertRedirect();

        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_other_players_see_it_in_the_player_list(): void
    {
        $user = User::factory()->create(['nickname' => 'Pipo']);
        $this->choose($user, self::PRESET);

        $this->get("/players/{$user->id}")->assertInertia(fn ($page) => $page
            ->where('player.avatar_url', '/images/avatars/metal_char_gingka-hagane.webp'));
    }

    public function test_guests_must_log_in(): void
    {
        $this->post('/profile/avatar/preset', ['preset' => self::PRESET])->assertRedirect('/login');
    }

    public function test_every_generation_has_characters_and_beys_without_repeated_ids(): void
    {
        $all = collect(AvatarPresets::all());

        $this->assertSame($all->count(), $all->pluck('id')->unique()->count());
        foreach (['bakuten', 'metal', 'burst', 'x'] as $generation) {
            foreach (['char', 'bey'] as $kind) {
                $this->assertGreaterThan(30, $all->where('gen', $generation)->where('kind', $kind)->count(), "$generation $kind");
            }
        }
        $this->assertSame([], $all->reject(fn ($avatar) => preg_match('/^(bakuten|metal|burst|x)_(char|bey)_[a-z0-9-]+$/', $avatar['id']))->all());
    }
}
