<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Foto de perfil')
            ->assertSee('Seguranca da conta')
            ->assertSee('Conta administrada pela equipe')
            ->assertDontSee('Laravel Starter Kit');
    }

    public function test_appearance_route_redirects_to_profile_anchor(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('appearance.edit'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_profile_page_hides_admin_managed_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Aparencia')
            ->assertDontSee('Excluir conta')
            ->assertDontSee('wire:model="name"', false)
            ->assertDontSee('wire:model="email"', false);
    }

    public function test_profile_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('photo', $this->fakePng('avatar.png'))
            ->call('saveProfilePhoto');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_previous_profile_photo_is_removed_when_replaced(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldPhotoPath = "profile-photos/{$user->id}/old-avatar.png";

        Storage::disk('public')->put($oldPhotoPath, 'old-photo');
        $user->forceFill(['profile_photo_path' => $oldPhotoPath])->save();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('photo', $this->fakePng('new-avatar.png'))
            ->call('saveProfilePhoto');

        $response->assertHasNoErrors();

        $user->refresh();

        Storage::disk('public')->assertMissing($oldPhotoPath);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_profile_photo_can_be_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $photoPath = "profile-photos/{$user->id}/avatar.png";

        Storage::disk('public')->put($photoPath, 'avatar-content');
        $user->forceFill(['profile_photo_path' => $photoPath])->save();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->call('removeProfilePhoto');

        $response->assertHasNoErrors();

        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_password_can_be_updated_from_profile_page(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5Wf6kAAAAASUVORK5CYII='),
        );
    }
}
