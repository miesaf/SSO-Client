<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// The client sends users to the SSO server, swaps the code for a token, and reads the user with it
class SsoLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.passport' => ['client_id' => '7', 'client_secret' => 'rahsia', 'redirect_uri' => 'http://client.test/auth/callback', 'url' => 'http://sso.test']]);
    }

    public function testTheWelcomePageOffersTheSsoLogin()
    {
        $this->get('/')->assertOk()->assertSee(route('login.sso'), false);
    }

    public function testLoginRedirectsToTheServerWithAState()
    {
        $response = $this->get(route('login.sso'));
        $state = session('state');
        $this->assertSame(40, strlen($state));
        $response->assertRedirect('http://sso.test/oauth/authorize?' . http_build_query(['client_id' => '7', 'redirect_uri' => 'http://client.test/auth/callback', 'response_type' => 'code', 'scope' => null, 'state' => $state]));
    }

    public function testTheCallbackSwapsTheCodeAndReadsTheUser()
    {
        Http::fake([
            'sso.test/oauth/token' => Http::response(['access_token' => 'tok', 'token_type' => 'Bearer']),
            'sso.test/api/user' => Http::response(['id' => 1, 'name' => 'Ali']),
        ]);

        $this->withSession(['state' => 'abc'])->get('/auth/callback?state=abc&code=kod')->assertRedirect('/authuser');
        Http::assertSent(fn ($r) => $r->url() === 'http://sso.test/oauth/token' && $r['code'] === 'kod' && $r['client_secret'] === 'rahsia');

        $this->get(route('auth.user'))->assertOk()->assertJson(['name' => 'Ali']);
        Http::assertSent(fn ($r) => $r->url() === 'http://sso.test/api/user' && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function testAWrongStateIsRefusedAndA401LogsOut()
    {
        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->withSession(['state' => 'abc'])->get('/auth/callback?state=lain&code=kod');
        } finally {
            Http::fake(['sso.test/api/user' => Http::response([], 401)]);
            $this->withSession(['access_token' => 'lama'])->get(route('auth.user'))->assertRedirect(route('logout'));
        }
    }
}
