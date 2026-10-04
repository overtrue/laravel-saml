<?php

namespace Tests;

use Mockery;
use OneLogin\Saml2\Auth;
use Overtrue\LaravelSaml\Exceptions\AssertException;
use Overtrue\LaravelSaml\Exceptions\UnauthenticatedException;
use Overtrue\LaravelSaml\SamlAuth;

class SamlAuthTest extends TestCase
{
    public function test_login_forwards_options_and_saves_request_id(): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->expects('login')->with('/home', ['tenant' => 'example'], true, false, true, false, 'user@example.com')
            ->andReturn('https://idp.example.com/login');
        $auth->expects('getLastRequestID')->andReturn('authn-request');

        $response = (new SamlAuth($auth))->redirect('/home', ['tenant' => 'example'], true, false, false, 'user@example.com');

        $this->assertSame('https://idp.example.com/login', $response->getTargetUrl());
        $this->assertSame('authn-request', session('saml.authnRequestId'));
    }

    public function test_logout_forwards_options_and_saves_request_id(): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->expects('logout')->with('/home', ['tenant' => 'example'], 'user@example.com', 'session-index', true, 'name-id-format', 'idp', 'sp')
            ->andReturn('https://idp.example.com/logout');
        $auth->expects('getLastRequestID')->andReturn('logout-request');

        $response = (new SamlAuth($auth))->redirectToLogout('/home', ['tenant' => 'example'], 'user@example.com', 'session-index', 'name-id-format', 'idp', 'sp');

        $this->assertSame('https://idp.example.com/logout', $response->getTargetUrl());
        $this->assertSame('logout-request', session('saml.logoutRequestId'));
    }

    public function test_authenticated_user_requires_valid_response_for_saved_request(): void
    {
        session(['saml.authnRequestId' => 'authn-request']);
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processResponse')->with('authn-request')->ordered();
        $auth->expects('getErrors')->andReturn([])->ordered();
        $auth->expects('isAuthenticated')->andReturn(true)->ordered();
        $auth->expects('getAttributes')->andReturn([])->ordered();
        $auth->expects('getNameId')->andReturn('user@example.com');

        $user = (new SamlAuth($auth))->getAuthenticatedUser();

        $this->assertSame($auth, $user->getAuth());
        $this->assertSame('user@example.com', $user->getUserId());
    }

    public function test_assertion_errors_prevent_user_creation(): void
    {
        $previous = new \RuntimeException('Signature validation failed');
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processResponse')->with(null);
        $auth->expects('getErrors')->andReturn(['invalid_response']);
        $auth->expects('getLastErrorReason')->andReturn('Signature validation failed');
        $auth->expects('getLastErrorException')->andReturn($previous);
        $auth->shouldNotReceive('isAuthenticated', 'getAttributes');

        try {
            (new SamlAuth($auth))->getAuthenticatedUser();
            $this->fail('An invalid response must not create a user.');
        } catch (AssertException $exception) {
            $this->assertSame(['invalid_response'], $exception->errors);
            $this->assertSame('Signature validation failed', $exception->lastErrorReason);
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_processing_exception_is_preserved(): void
    {
        $previous = new \RuntimeException('Malformed response');
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processResponse')->with(null)->andThrow($previous);
        $auth->expects('getErrors')->andReturn(['invalid_response']);
        $auth->expects('getLastErrorReason')->andReturn('Malformed response');
        $auth->shouldNotReceive('isAuthenticated', 'getAttributes');

        try {
            (new SamlAuth($auth))->getAuthenticatedUser();
            $this->fail('A processing exception must not create a user.');
        } catch (AssertException $exception) {
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_processing_error_is_preserved_as_assertion_failure(): void
    {
        $previous = new \Error('Unable to process response');
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processResponse')->with(null)->andThrow($previous);
        $auth->expects('getErrors')->andReturn(['invalid_response']);
        $auth->expects('getLastErrorReason')->andReturn('Unable to process response');
        $auth->shouldNotReceive('isAuthenticated', 'getAttributes');

        try {
            (new SamlAuth($auth))->getAuthenticatedUser();
            $this->fail('A processing error must not create a user.');
        } catch (AssertException $exception) {
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_logout_processing_error_is_preserved_and_keeps_request_id(): void
    {
        session(['saml.logoutRequestId' => 'logout-request']);
        $previous = new \TypeError('Unable to process logout');
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processSLO')->with(false, 'logout-request', false, Mockery::type('callable'), true)->andThrow($previous);
        $auth->expects('getErrors')->andReturn(['invalid_logout_response']);
        $auth->expects('getLastErrorReason')->andReturn('Unable to process logout');

        try {
            (new SamlAuth($auth))->handleLogoutRequest();
            $this->fail('A processing error must not complete logout.');
        } catch (AssertException $exception) {
            $this->assertSame($previous, $exception->getPrevious());
            $this->assertSame('logout-request', session('saml.logoutRequestId'));
        }
    }

    public function test_unauthenticated_response_prevents_user_creation(): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processResponse')->with(null);
        $auth->expects('getErrors')->andReturn([]);
        $auth->expects('isAuthenticated')->andReturn(false);
        $auth->expects('getLastErrorReason')->andReturn(null);
        $auth->expects('getLastErrorException')->andReturn(null);
        $auth->shouldNotReceive('getAttributes');

        $this->expectException(UnauthenticatedException::class);
        (new SamlAuth($auth))->getAuthenticatedUser();
    }

    public function test_successful_logout_forwards_callback_and_clears_request_id(): void
    {
        session(['saml.logoutRequestId' => 'logout-request']);
        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processSLO')->with(false, 'logout-request', true, $callback, true)
            ->andReturnUsing(function ($keepLocalSession, $requestId, $retrieveParametersFromServer, $cbDeleteSession) {
                $cbDeleteSession();

                return null;
            });
        $auth->expects('getErrors')->andReturn([]);

        $this->assertNull((new SamlAuth($auth))->handleLogoutRequest($callback, true));
        $this->assertTrue($called);
        $this->assertFalse(session()->has('saml.logoutRequestId'));
    }

    public function test_failed_logout_keeps_request_id_and_preserves_error(): void
    {
        session(['saml.logoutRequestId' => 'logout-request']);
        $previous = new \RuntimeException('Invalid logout signature');
        $auth = Mockery::mock(Auth::class);
        $auth->expects('processSLO')->with(false, 'logout-request', false, Mockery::type('callable'), true);
        $auth->expects('getErrors')->andReturn(['invalid_logout_response']);
        $auth->expects('getLastErrorReason')->andReturn('Invalid logout signature');
        $auth->expects('getLastErrorException')->andReturn($previous);

        try {
            (new SamlAuth($auth))->handleLogoutRequest();
            $this->fail('An invalid logout response must fail validation.');
        } catch (AssertException $exception) {
            $this->assertSame(['invalid_logout_response'], $exception->errors);
            $this->assertSame($previous, $exception->getPrevious());
            $this->assertSame('logout-request', session('saml.logoutRequestId'));
        }
    }
}
