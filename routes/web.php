<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\User;

Route::get('/debug/users-list', function () {
    return User::select('id','name','email','is_admin')->orderBy('id')->limit(10)->get();
});
Route::get('/debug/create-admin', function () {
    $u = \App\Models\User::updateOrCreate(
        ['email' => 'kadirmertozden@ankaverse.com.tr'],
        [
            'name' => 'Kadir Mert Özden',
            'password' => bcrypt('Discovery96.'),
            'is_admin' => true,
        ]
    );
    return $u ? 'ok: '.$u->id : 'failed';
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