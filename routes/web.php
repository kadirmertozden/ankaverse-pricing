<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Controllers\ExportRunDownloadController;
use App\Http\Controllers\ExportFeedController;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ExportController;



Route::get('/__ping', fn() => response('ok',200));

Route::get('/__debug/exports/{folder}/{any}', function($folder,$any){
    $path = "1/$any"; // DB normalize ettiğimiz format
    if (Storage::disk('exports')->exists($path)) {
        return response(Storage::disk('exports')->get($path),200)->header('Content-Type','application/xml');
    }
    return response("missing: $path",404);
})->where('any','.*');


Route::get('/exports/{folder}/{filename}', function ($folder, $filename) {
    $path = "exports/{$folder}/{$filename}";

    if (!Storage::exists($path)) {
        abort(404, 'XML dosyası bulunamadı.');
    }

    return response(Storage::get($path), 200)
        ->header('Content-Type', 'application/xml');
});
// Token ile güvenli yayın (önerilir)
Route::get('/exports/t/{token}', [ExportController::class, 'showByToken'])
    ->name('exports.show');

// /exports/1/20250812_161733.xml
// /exports/1/manual/manual-20250812-170641.xml
Route::get('/exports/{folder}/{any}', [ExportController::class, 'showByPath'])
    ->where('any', '.*');
// GEÇİCİ! Sorun bitince sil.
Route::get('/_debug/exports/{folder}/{any}', function ($folder, $any) {
    $path = "exports/{$folder}/{$any}";
    $disk = Storage::disk('exports');
    if (!$disk->exists($path)) abort(404, 'bulunamadı');
    return response($disk->get($path), 200)->header('Content-Type','application/xml');
})->where('any','.*');

	
Route::middleware(['web', FilamentAuthenticate::class])
    ->prefix('admin/devlogs')      // <--- BURASI değişti
    ->name('admin.devlogs.')
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
    ->name('feeds.show');

Route::get('/admin/exports/{exportRun}/download', [ExportFeedController::class, 'download'])
    ->name('admin.exports.download')   // Filament’te kullandığın isimle aynı
    ->middleware(['auth']);            // İstersen kaldır/özelleştir

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