<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Controllers\ExportRunDownloadController;
use App\Http\Controllers\ExportFeedController;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

Route::middleware(['web', FilamentAuthenticate::class])
    ->prefix('admin/devlogs')                 // ← 'admin/logs' yerine
    ->name('admin.devlogs.')                  // ← isim de değişti
    ->group(function () {
        Route::get('/', function () {
            $dir = storage_path('logs');
            $files = collect(glob($dir.'/*.log'))
                ->map(fn ($p) => basename($p))
                ->values();

            return response()->json($files);
        })->name('index');

        Route::get('/{file}', function (string $file) {
            abort_unless(preg_match('/^[A-Za-z0-9._-]+\.log$/', $file), 404);
            $path = storage_path('logs/'.$file);
            abort_unless(file_exists($path), 404, 'Log dosyası yok');

            return response()->download($path, $file, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        })->name('download');

        Route::get('/{file}/tail', function (string $file) {
            abort_unless(preg_match('/^[A-Za-z0-9._-]+\.log$/', $file), 404);
            $path = storage_path('logs/'.$file);
            abort_unless(file_exists($path), 404, 'Log dosyası yok');

            $lines = max(1, min((int) request('lines', 200), 2000));
            $content = implode(PHP_EOL, array_slice(file($path, FILE_IGNORE_NEW_LINES), -$lines));

            return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        })->name('tail');
    });

Route::get('/admin/logs/laravel', function () {
    abort_unless(auth()->check(), 403);
    $path = storage_path('logs/laravel.log');
    abort_unless(file_exists($path), 404, 'Log dosyası yok');
    return response()->download($path, 'laravel.log', ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('admin.logs.download');

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