<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Controllers\ExportRunDownloadController;
use App\Http\Controllers\ExportFeedController;

Route::get('/feeds/{token}.xml', [ExportFeedController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('feeds.public');

Route::middleware(['web','auth'])->group(function () {
    Route::get('/admin/exports/{exportRun}/download', [ExportRunDownloadController::class, 'download'])
        ->name('admin.exports.download');
});

Route::get('/debug/users-list', function () {
    return User::select('id','name','email','is_admin')->orderBy('id')->limit(10)->get();
});

// 1) İstediğin e‑posta ile admin oluştur / güncelle
Route::get('/debug/create-admin', function () {
    $u = User::updateOrCreate(
        ['email' => 'kadirmertozden@ankaverse.com.tr'],
        [
            'name' => 'Kadir Mert Özden',
            'password' => bcrypt('Discovery96.'), // giriş şifren
            'is_admin' => true,
        ]
    );
    return $u ? 'ok: '.$u->id : 'failed';
});

// 2) Var olan kullanıcıyı admin yap (ID: 2 mesela)
Route::get('/debug/make-admin/{id}', function ($id) {
    $u = User::find($id);
    if (! $u) return 'user not found';
    $u->is_admin = true;
    $u->save();
    return 'ok';
});

// 3) (Opsiyonel) Var olan kullanıcının şifresini sıfırla
Route::get('/debug/reset-pass/{id}', function ($id) {
    $u = User::find($id);
    if (! $u) return 'user not found';
    $u->password = bcrypt('Discovery96.');
    $u->save();
    return 'ok';
});

Route::get('/debug/users-count', function () {
    return DB::table('users')->count();
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'app' => config('app.name'),
        'env' => config('app.env'),
        'time' => now()->toISOString(),
    ]);
});

use App\Http\Controllers\FeedController;

Route::get('/feed/satis.xml', [FeedController::class, 'feed']);
Route::get('/admin/ping', fn() => 'pong');
Route::get('/debug/reset-admin', function () {
    $u = App\Models\User::where('email','kadirmertozden@ankaverse.com.tr')->first();
    if (!$u) return 'user not found';
    $u->password = bcrypt('Discovery96.');
    $u->is_admin = true;
    $u->save();
    return 'ok';
});