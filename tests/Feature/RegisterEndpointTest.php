<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_register_api_is_disabled(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Andi Pratama',
            'company_name' => 'Ruang Karya Digital',
            'email' => 'andi@ruangkarya.example',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'terms' => true,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');

        $this->assertDatabaseMissing('users', [
            'email' => 'andi@ruangkarya.example',
        ]);
    }

    public function test_public_register_web_get_redirects_to_login(): void
    {
        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');
    }

    public function test_public_register_web_post_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'Riko Saputra',
            'company_name' => 'Ruang Karya Digital',
            'email' => 'riko@ruangkarya.example',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'terms' => '1',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'riko@ruangkarya.example',
        ]);
    }
}
