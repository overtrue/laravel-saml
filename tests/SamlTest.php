<?php

namespace Tests;

use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;
use Overtrue\LaravelSaml\Saml;

class SamlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSaml();
    }

    protected function tearDown(): void
    {
        $this->resetSaml();
        parent::tearDown();
    }

    public function test_explicit_idp_settings_work_without_a_resolver(): void
    {
        $settings = $this->idpSettings();

        $auth = Saml::idp('explicit', $settings)->getAuth();

        $this->assertSame($settings['entityId'], $auth->getSettings()->getIdPData()['entityId']);
        $this->assertTrue($auth->getSettings()->isStrict());
    }

    public function test_explicit_idp_settings_take_precedence_over_resolver(): void
    {
        Saml::configureIdpUsing(function () {
            $this->fail('The resolver must not be called when settings are explicitly supplied.');
        });

        $this->assertSame($this->idpSettings()['entityId'], Saml::idp('explicit', $this->idpSettings())->getAuth()->getSettings()->getIdPData()['entityId']);
    }

    public function test_resolver_receives_idp_name_and_resolved_auth_is_cached(): void
    {
        $names = [];
        Saml::configureIdpUsing(function ($name) use (&$names) {
            $names[] = $name;

            return $this->idpSettings();
        });

        $auth = Saml::idp('configured');

        $this->assertSame($auth, Saml::idp('configured'));
        $this->assertSame(['configured'], $names);
    }

    public function test_missing_idp_settings_are_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);
        Saml::idp('missing');
    }

    private function idpSettings(): array
    {
        return [
            'entityId' => 'https://idp.example.com/saml',
            'singleSignOnService' => ['url' => 'https://idp.example.com/login'],
            'x509cert' => __DIR__.'/fixtures/idp.crt',
        ];
    }

    private function resetSaml(): void
    {
        (new \ReflectionProperty(Saml::class, 'resolved'))->setValue(null, []);
        (new \ReflectionProperty(Saml::class, 'idpConfigResolver'))->setValue(null, null);
    }
}
