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
        "client_id" => "937fc8e4-587d-4228-838c-cb36d6c4b671",
        "redirect_uri" => "http://localhost/SSO-Client-1/public/callback",
        "response_type" => "code",
        "scope" => "view-user",
        "state" => $state
    ]);

    return redirect("http://localhost/SSO/public/oauth/authorize?" . $query);
});

Route::get('/callback', function (Request $request) {
    $state = $request->session()->pull("state");

    throw_unless(strlen($state) > 0 && $state == $request->state, InvalidArgumentException::class);

    $response = Http::asForm()->post(
        "http://localhost/SSO/public/oauth/token",
        [
            "grant_type" => "authorization_code",
            "client_id" => "937fc8e4-587d-4228-838c-cb36d6c4b671",
            "client_secret" => "1Xk4c6rZCIzru7B2S11HjBwwwlS7HJjeLK1JNMnv",
            "redirect_uri" => "http://localhost/SSO-Client-1/public/callback",
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
    ])->get("http://localhost/SSO/public/api/user");

    return $response->json();
});
