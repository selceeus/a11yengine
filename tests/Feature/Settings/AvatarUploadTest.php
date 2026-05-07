<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

test('authenticated user can upload an avatar', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $file])
        ->assertRedirect(route('profile.edit'));

    $this->user->refresh();

    expect($this->user->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->user->avatar_path);
});

test('uploading a new avatar deletes the previous one', function (): void {
    $first = UploadedFile::fake()->image('first.jpg', 100, 100);
    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $first]);

    $this->user->refresh();
    $oldPath = $this->user->avatar_path;

    $second = UploadedFile::fake()->image('second.png', 100, 100);
    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $second]);

    Storage::disk('public')->assertMissing($oldPath);
    $this->user->refresh();
    Storage::disk('public')->assertExists($this->user->avatar_path);
});

test('invalid mime type is rejected', function (): void {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $file])
        ->assertSessionHasErrors('avatar');

    expect($this->user->refresh()->avatar_path)->toBeNull();
});

test('file exceeding 2MB is rejected', function (): void {
    $file = UploadedFile::fake()->image('big.jpg')->size(3000);

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $file])
        ->assertSessionHasErrors('avatar');
});

test('authenticated user can remove their avatar', function (): void {
    $file = UploadedFile::fake()->image('photo.png', 100, 100);
    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => $file]);

    $this->user->refresh();
    $path = $this->user->avatar_path;

    $this->actingAs($this->user)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    Storage::disk('public')->assertMissing($path);
    expect($this->user->refresh()->avatar_path)->toBeNull();
});

test('unauthenticated user cannot upload an avatar', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $this->post(route('profile.avatar.store'), ['avatar' => $file])
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot remove an avatar', function (): void {
    $this->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('login'));
});
