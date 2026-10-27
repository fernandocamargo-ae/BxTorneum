<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_no_avatar_url_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->avatar_url);
    }

    public function test_a_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 200, 200)])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString($user->avatar_path, $user->avatar_url);
    }

    public function test_uploading_a_new_avatar_deletes_the_previous_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')]);
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('b.jpg')]);
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_user_can_remove_their_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')]);
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)->delete('/profile/avatar')->assertRedirect();

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_avatar_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_guests_cannot_upload_an_avatar(): void
    {
        $this->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
            ->assertRedirect('/login');
    }
}
