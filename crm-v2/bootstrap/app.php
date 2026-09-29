<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias(['active' => ActiveUser::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/companies');
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
