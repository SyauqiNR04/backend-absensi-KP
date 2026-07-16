<?php
/*
|==================================================================
| FITUR: Bootstrap Middleware
| Meregistrasi SecurityHeaders secara global dan alias middleware keamanan (device.integrity, attestation).
|==================================================================
*/

use App\Http\Middleware\AttestationGuard;
use App\Http\Middleware\DeviceIntegrity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers pada SEMUA response API (global).
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        // Alias middleware terarah untuk route sensitif.
        // Sanctum tidak mendaftarkan 'ability'/'abilities' sendiri, jadi
        // keduanya harus didaftarkan di sini agar route ability:* bekerja.
        $middleware->alias([
            'device.integrity' => DeviceIntegrity::class,
            'attestation'      => AttestationGuard::class,
            'abilities'        => CheckAbilities::class,
            'ability'          => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
