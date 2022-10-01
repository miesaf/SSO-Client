<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function (Request $request) {
    $request->session()->put("state", $state = Str::random(40));

    $query = http_build_query([
        "client_id" => config('auth.passport.client_id'),
        "redirect_uri" => config('auth.passport.redirect_uri'),
        "response_type" => "code",
        "scope" => null,
        "state" => $state
    ]);

    return redirect(config('auth.passport.url') . "/oauth/authorize?" . $query);
})->name('login.sso');

Route::get('/auth/callback', function (Request $request) {
    $state = $request->session()->pull("state");

    throw_unless(strlen($state) > 0 && $state == $request->state, InvalidArgumentException::class);

    $response = Http::asForm()->post(
        config('auth.passport.url') . "/oauth/token",
        [
            "grant_type" => "authorization_code",
            "client_id" => config('auth.passport.client_id'),
            "client_secret" => config('auth.passport.client_secret'),
            "redirect_uri" => config('auth.passport.redirect_uri'),
            "code" => $request->code
        ]
    );

    $request->session()->put($response->json());

    return redirect("/authuser");
});

Route::get('/authuser', function (Request $request) {
    $access_token = $request->session()->get("access_token");
    $response = Http::withHeaders([
        "Accept" => "application/json",
        "Authorization" => "Bearer " . $access_token
    ])->get(config('auth.passport.url') . "/api/user");

    return $response->json();
})->name('auth.user');

Route::get('/logout', function (Request $request) {
    $request->session()->flush();
    $request->session()->regenerate();

    return redirect("/");
})->name('logout');