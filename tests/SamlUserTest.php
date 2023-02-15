<?php

namespace Tests;

use OneLogin\Saml2\Auth;
use Overtrue\LaravelSaml\SamlUser;

class SamlUserTest extends TestCase
{
    public function tearDown(): void
    {
        \Mockery::close();
    }

    public function test_get_user_id()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getNameId()->andReturns('user@domain.com')->twice();
        $auth->expects()->getAttributes()->andReturns([])->twice();

        $this->assertSame('user@domain.com', (new SamlUser($auth))->getUserId());
        $this->assertSame('user@domain.com', (new SamlUser($auth))->getNameId());
    }

    public function test_get_attributes_with_fridenly_name()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getAttributesWithFriendlyName()->andReturns(['email' => 'user@domain.com']);
        $auth->expects()->getAttributes()->andReturns([]);

        $this->assertSame(['email' => 'user@domain.com'], (new SamlUser($auth))->getAttributesWithFriendlyName());
    }

    public function test_get_saml_attribute()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getAttribute('email')->andReturns(['email' => 'user@domain.com']);
        $auth->expects()->getAttributes()->andReturns([]);

        $this->assertSame(['email' => 'user@domain.com'], (new SamlUser($auth))->getSamlAttribute('email'));
    }

    public function test_get_session_index()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getSessionIndex()->andReturns('mock-session-index');
        $auth->expects()->getAttributes()->andReturns([]);

        $this->assertSame('mock-session-index', (new SamlUser($auth))->getSessionIndex());
    }

    public function test_get_auth()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getAttributes()->andReturns([]);

        $this->assertSame($auth, (new SamlUser($auth))->getAuth());
    }

    public function test_get_intended_url()
    {
        $auth = \Mockery::mock(Auth::class);
        $auth->expects()->getAttributes()->andReturns([]);

        \request()->merge([
            'RelayState' => 'http://foobar.com/saml/user',
        ]);

        $this->assertSame('http://foobar.com/saml/user', (new SamlUser($auth))->getIntendedUrl());
    }
}
