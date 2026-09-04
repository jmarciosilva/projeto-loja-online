<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'api' => 'v1',
]))->name('api.health');

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum');
