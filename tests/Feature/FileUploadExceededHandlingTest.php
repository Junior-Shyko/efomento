<?php

namespace Tests\Feature;

use App\Exceptions\Domain\FileUploadExceededException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FileUploadExceededHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/test-upload-web', function () {
            return response()->json(['success' => true]);
        });

        Route::middleware('api')->post('/test-upload-api', function () {
            return response()->json(['success' => true]);
        });

        Route::middleware('web')->post('/test-post-too-large', function () {
            throw new PostTooLargeException;
        });

        Route::middleware('api')->post('/test-post-too-large-api', function () {
            throw new PostTooLargeException;
        });

        Route::middleware('api')->post('/test-custom-upload-exceeded', function () {
            throw FileUploadExceededException::fromIniLimits();
        });
    }

    public function test_post_too_large_exception_returns_json_with_413_on_api_request(): void
    {
        $response = $this->postJson('/test-post-too-large-api');

        $response->assertStatus(413)
            ->assertJsonStructure([
                'message',
                'code',
            ])
            ->assertJson([
                'code' => 'FileUploadExceededException',
            ]);

        $this->assertStringStartsWith('O arquivo enviado excede o limite máximo permitido de', $response->json('message'));
    }

    public function test_post_too_large_exception_redirects_back_with_error_on_web_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/previous-url')
            ->post('/test-post-too-large');

        $response->assertRedirect('/previous-url');
        $response->assertSessionHasErrors(['message']);
    }

    public function test_php_ini_size_error_is_intercepted_by_middleware_on_web(): void
    {
        $user = User::factory()->create();

        $invalidFile = new UploadedFile(
            path: tempnam(sys_get_temp_dir(), 'test'),
            originalName: 'large_file.pdf',
            mimeType: 'application/pdf',
            error: UPLOAD_ERR_INI_SIZE,
            test: true
        );

        $response = $this->actingAs($user)
            ->from('/upload-form')
            ->post('/test-upload-web', [
                'file' => $invalidFile,
            ]);

        $response->assertRedirect('/upload-form');
        $response->assertSessionHasErrors(['message']);
    }

    public function test_custom_file_upload_exceeded_exception_returns_413_on_api(): void
    {
        $response = $this->postJson('/test-custom-upload-exceeded');

        $response->assertStatus(413)
            ->assertJson([
                'code' => 'FileUploadExceededException',
            ]);

        $this->assertStringStartsWith('O arquivo enviado excede o limite máximo permitido de', $response->json('message'));
    }

    public function test_php_ini_size_error_is_intercepted_by_middleware_on_api(): void
    {
        $invalidFile = new UploadedFile(
            path: tempnam(sys_get_temp_dir(), 'test'),
            originalName: 'large_file.pdf',
            mimeType: 'application/pdf',
            error: UPLOAD_ERR_INI_SIZE,
            test: true
        );

        $response = $this->postJson('/test-upload-api', [
            'file' => $invalidFile,
        ]);

        $response->assertStatus(413)
            ->assertJson([
                'code' => 'FileUploadExceededException',
            ]);

        $this->assertStringStartsWith('O arquivo enviado excede o limite máximo permitido de', $response->json('message'));
    }

    public function test_valid_upload_passes_normally(): void
    {
        $user = User::factory()->create();

        $validFile = UploadedFile::fake()->create('valid.pdf', 100);

        $response = $this->actingAs($user)
            ->post('/test-upload-web', [
                'file' => $validFile,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }
}
