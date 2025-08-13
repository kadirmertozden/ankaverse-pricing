<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExportFeedController;
use App\Http\Controllers\ExportRunDownloadController; // (kullanıyorsan dursun)
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

// --- Debug ping (isteğe bağlı) ---
Route::get('/__ping', fn () => response('ok', 200));

// --- Debug: storage üzerinden direkt okuma (GEÇİCİ, iş bitince sil) ---
Route::get('/__debug/exports/{folder}/{any}', function ($folder, $any) {
    $path = "1/{$any}"; // DB'yi 1/... formatına normalize ettik
    if (Storage::disk('exports')->exists($path)) {
        return response(Storage::disk('exports')->get($path), 200)
            ->header('Content-Type', 'application/xml');
    }
    return response("missing: {$path}", 404);
})->where('any', '.*');

// =====================
//  Exports: PUBLIC API
// =====================

// 1) Token ile güvenli yayın (önerilir)
Route::get('/exports/t/{token}', [ExportController::class, 'showByToken'])
    ->name('exports.show');

// 2) Yol bazlı (nested) erişim
//    Ör: /exports/1/20250812_161733.xml
//        /exports/1/manual/manual-20250812-170641.xml
Route::get('/exports/{folder}/{any}', [ExportController::class, 'showByPath'])
    ->where('any', '.*');

// --- Eski/çakışan closure route KALDIRILDI ---
// Route::get('/exports/{folder}/{filename}', function (...) { ... });

// =====================
//  Admin log viewer (Filament ile korumalı)
// =====================
Route::middleware(['web', FilamentAuthenticate::class])
    ->prefix('admin/devlogs')
    ->name('admin.devlogs.')
    ->group(function () {
        Route::get('/', function () {
            $dir = storage_path('logs');
            $files = collect(glob($dir . '/*.log'))
                ->map(fn ($p) => basename($p))
                ->values();
            return response()->json($files);
        })->name('index');

        Route::get('/{file}', function (string $file) {
            abort_unless(preg_match('/^[A-Za-z0-9._-]+\.log$/', $file), 404);
            $path = storage_path('logs/' . $file);
            abort_unless(file_exists($path), 404, 'Log dosyası yok');
            return response()->download($path, $file, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        })->name('download');

        Route::get('/{file}/tail', function (string $file) {
            abort_unless(preg_match('/^[A-Za-z0-9._-]+\.log$/', $file), 404);
            $path = storage_path('logs/' . $file);
            abort_unless(file_exists($path), 404, 'Log dosyası yok');
            $lines = max(1, min((int) request('lines', 200), 2000));
            $content = implode(PHP_EOL, array_slice(file($path, FILE_IGNORE_NEW_LINES), -$lines));
            return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        })->name('tail');
    });

// Tekil log indirme (auth kontrolü var)
Route::get('/admin/logs/laravel', function () {
    abort_unless(auth()->check(), 403);
    $path = storage_path('logs/laravel.log');
    abort_unless(file_exists($path), 404, 'Log dosyası yok');
    return response()->download($path, 'laravel.log', ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('admin.logs.download');

// Feeds
Route::get('/feeds/{token}.xml', [ExportFeedController::class, 'show'])
    ->name('feeds.show');

Route::get('/admin/exports/{exportRun}/download', [ExportFeedController::class, 'download'])
    ->name('admin.exports.download')
    ->middleware(['auth']);

// Debug yardımcıları (gerekirse)
Route::get('/debug/users-list', function () {
    return User::select('id', 'name', 'email', 'is_admin')->orderBy('id')->limit(10)->get();
});

Route::get('/debug/create-admin', function () {
    $u = User::updateOrCreate(
        ['email' => 'kadirmertozden@ankaverse.com.tr'],
        ['name' => 'Kadir Mert Özden', 'password' => bcrypt('Discovery96.'), 'is_admin' => true]
    );
    return $u ? 'ok: ' . $u->id : 'failed';
});

Route::get('/debug/make-admin/{id}', function ($id) {
    $u = User::find($id);
    if (!$u) return 'user not found';
    $u->is_admin = true;
    $u->save();
    return 'ok';
});

Route::get('/debug/reset-pass/{id}', function ($id) {
    $u = User::find($id);
    if (!$u) return 'user not found';
    $u->password = bcrypt('Discovery96.');
    $u->save();
    return 'ok';
});

Route::get('/debug/users-count', fn () => DB::table('users')->count());

// Genel
Route::get('/', fn () => view('welcome'));
Route::get('/health', fn () => response()->json([
    'ok' => true,
    'app' => config('app.name'),
    'env' => config('app.env'),
    'time' => now()->toISOString(),
]));

// Örnek feed (varsa)
use App\Http\Controllers\FeedController;
Route::get('/feed/satis.xml', [FeedController::class, 'feed']);

Route::get('/admin/ping', fn () => 'pong');
Route::get('/debug/reset-admin', function () {
    $u = User::where('email', 'kadirmertozden@ankaverse.com.tr')->first();
    if (!$u) return 'user not found';
    $u->password = bcrypt('Discovery96.');
    $u->is_admin = true;
    $u->save();
    return 'ok';
});


