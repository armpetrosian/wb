<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// mb_split alternative for systems without mbstring extension
if (!function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1) {
        // Use preg_split as alternative
        $result = preg_split($pattern, $string, $limit);
        return $result !== false ? $result : str_split($string);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
