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
            ->assertSee('Informacoes pessoais')
            ->assertSee('Status do trabalho')
            ->assertSee('Preferencias')
            ->assertSee('Seguranca da conta')
            ->assertSee('Historico da sessao')
            ->assertSee('Patrimonios vinculados')
            ->assertDontSee('Laravel Starter Kit');
    }

    public function test_appearance_page_redirects_to_profile_preferences(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('appearance.edit'))
            ->assertRedirect(route('profile.edit').'#preferencias');
    }

    public function test_user_can_update_theme_preference_from_profile_page(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'light',
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('themePreference', 'dark')
            ->call('saveThemePreference')
            ->assertHasNoErrors()
            ->assertSet('themePreference', 'dark');

        $this->assertSame('dark', $user->refresh()->theme_preference);
    }

    public function test_user_can_update_safe_personal_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Nome Antigo',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Nome Atualizado')
            ->set('job_title', 'Analista de suporte')
            ->set('phone', '11 3000-0000')
            ->set('mobile_phone', '11 99999-0000')
            ->set('location', 'Sao Paulo')
            ->set('birth_date', '1995-05-10')
            ->set('work_anniversary', '2024-01-15')
            ->call('savePersonalInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Nome Atualizado', $user->name);
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame('Analista de suporte', $user->job_title);
        $this->assertSame('11 3000-0000', $user->phone);
        $this->assertSame('11 99999-0000', $user->mobile_phone);
        $this->assertSame('Sao Paulo', $user->location);
        $this->assertSame('1995-05-10', $user->birth_date->format('Y-m-d'));
        $this->assertSame('2024-01-15', $user->work_anniversary->format('Y-m-d'));
    }

    public function test_user_can_update_work_status(): void
    {
        $user = User::factory()->create([
            'work_status' => 'office',
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('work_status', 'home')
            ->call('saveWorkStatus');

        $response->assertHasNoErrors();

        $this->assertSame('home', $user->refresh()->work_status);
    }

    public function test_profile_page_keeps_admin_managed_fields_readonly(): void
    {
        $user = User::factory()->create([
            'email' => 'readonly@example.com',
        ]);

        $this->actingAs($user);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('readonly@example.com')
            ->assertDontSee('Excluir conta')
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
