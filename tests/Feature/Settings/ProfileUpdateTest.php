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
            ->assertSee('Dados pessoais')
            ->assertSee('Senha de acesso');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('photo', UploadedFile::fake()->image('avatar.png'))
            ->call('updateProfileInformation');

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
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('photo', UploadedFile::fake()->image('new-avatar.png'))
            ->call('updateProfileInformation');

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
}
