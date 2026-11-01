<?php

use App\Domain\RuleViolation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/contracts');

        // Konteynerga faqat TLS proxy orqali kiriladi: havola va assetlar https bo'lib qolsin.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(RuleViolation::class);

        // Biznes qoidasi buzilsa foydalanuvchi aniq xabar bilan formaga qaytadi.
        $exceptions->render(fn (RuleViolation $e, Request $request) => back()
            ->withInput()
            ->withErrors([$e->field => $e->getMessage()]));
    })->create();
