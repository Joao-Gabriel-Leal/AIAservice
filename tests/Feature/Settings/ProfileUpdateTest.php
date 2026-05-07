<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
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
            ->assertSee('Patrimonios vinculados')
            ->assertSee('Seguranca da conta')
            ->assertSee('Conta administrada pela equipe')
            ->assertDontSee('Laravel Starter Kit');
    }

    public function test_appearance_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('Tema do sistema')
            ->assertSee('Modo claro')
            ->assertSee('Modo escuro');
    }

    public function test_user_can_update_theme_preference_from_appearance_page(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'light',
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.appearance')
            ->set('themePreference', 'dark')
            ->assertSet('themePreference', 'dark');

        $this->assertSame('dark', $user->refresh()->theme_preference);
    }

    public function test_profile_page_hides_admin_managed_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Excluir conta')
            ->assertDontSee('wire:model="name"', false)
            ->assertDontSee('wire:model="email"', false);
    }

    public function test_profile_photo_can_be_uploaded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('photo', $this->fakePng('avatar.png'))
            ->call('saveProfilePhoto');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        $this->assertNotNull($user->profile_photo_content);
        $this->assertNotNull($user->profile_photo_mime_type);
        $this->assertGreaterThan(0, $user->profile_photo_size);
    }

    public function test_previous_profile_photo_is_removed_when_replaced(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'profile_photo_path' => "profile-photos/{$user->id}/old-avatar.png",
            'profile_photo_original_name' => 'old-avatar.png',
            'profile_photo_mime_type' => 'image/png',
            'profile_photo_size' => 8,
            'profile_photo_content' => 'old-photo',
        ])->save();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('photo', $this->fakePng('new-avatar.png'))
            ->call('saveProfilePhoto');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertNotSame('old-photo', $user->profile_photo_content);
        $this->assertStringContainsString("profile-photos/{$user->id}/", (string) $user->profile_photo_path);
    }

    public function test_profile_photo_can_be_removed(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'profile_photo_path' => "profile-photos/{$user->id}/avatar.png",
            'profile_photo_original_name' => 'avatar.png',
            'profile_photo_mime_type' => 'image/png',
            'profile_photo_size' => 14,
            'profile_photo_content' => 'avatar-content',
        ])->save();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->call('removeProfilePhoto');

        $response->assertHasNoErrors();

        $this->assertNull($user->refresh()->profile_photo_path);
        $this->assertNull($user->profile_photo_content);
        $this->assertNull($user->profile_photo_mime_type);
        $this->assertNull($user->profile_photo_size);
    }

    public function test_password_can_be_updated_from_profile_page(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('current_password', 'password')
            ->set('password', 'NovaSenha@123')
            ->set('password_confirmation', 'NovaSenha@123')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenha@123', $user->refresh()->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_pending_password_change_can_be_completed_without_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('password', 'NovaSenha@123')
            ->set('password_confirmation', 'NovaSenha@123')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenha@123', $user->refresh()->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_pending_password_change_form_hides_current_password_field(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->assertSee('Defina uma nova senha para liberar o acesso ao restante do portal.')
            ->assertDontSee('Senha atual')
            ->assertDontSee('wire:model="current_password"', false);
    }

    public function test_password_minimum_validation_message_is_translated(): void
    {
        app()->setLocale('pt_BR');

        $validator = Validator::make(
            [
                'password' => 'curta',
                'password_confirmation' => 'curta',
            ],
            [
                'password' => ['required', 'string', Password::min(8), 'confirmed'],
            ],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('O campo nova senha deve ter pelo menos 8 caracteres.', $validator->errors()->first('password'));
    }

    public function test_current_password_is_required_when_password_change_is_not_pending(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('password', 'NovaSenha@123')
            ->set('password_confirmation', 'NovaSenha@123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
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
