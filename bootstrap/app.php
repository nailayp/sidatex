<?php

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            $maxUpload = ini_get('upload_max_filesize') ?: '50M';
            $maxPost = ini_get('post_max_size') ?: '50M';
            $pesan = "Ukuran payload file yang diunggah melebihi batas server PHP (post_max_size: {$maxPost}, upload_max_filesize: {$maxUpload}). Pastikan ukuran file Excel di bawah 50 MB.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $pesan,
                ], 413);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $pesan);
        });
    })->create();
