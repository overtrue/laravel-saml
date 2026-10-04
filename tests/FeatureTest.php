<?php

namespace Tests;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;
use Overtrue\LaravelSaml\Exceptions\AssertException;
use Overtrue\LaravelSaml\Saml;
use Overtrue\LaravelSaml\SamlAuth;

class FeatureTest extends TestCase
{
    public function test_service_provider_loads_security_defaults(): void
    {
        $settings = $this->createAuth()->getSettings();

        $this->assertTrue($settings->isStrict());
        $this->assertTrue($settings->getSecurityData()['wantXMLValidation']);
        $this->assertFalse($settings->getSecurityData()['relaxDestinationValidation']);
        $this->assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $settings->getSecurityData()['signatureAlgorithm']);
        $this->assertSame('http://www.w3.org/2001/04/xmlenc#sha256', $settings->getSecurityData()['digestAlgorithm']);
    }

    public function test_metadata_is_valid_xml_response(): void
    {
        $response = Saml::getMetadataXML();
        $document = new \DOMDocument;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertTrue($document->loadXML($response->getContent()));
        $this->assertSame(config('saml.sp.entityId'), $document->documentElement->getAttribute('entityID'));
    }

    public function test_unsigned_assertion_is_rejected_by_real_toolkit(): void
    {
        $this->assertResponseRejected($this->unsignedResponse('authn-request'), 'No Signature found');
    }

    public function test_response_for_different_request_is_rejected_by_real_toolkit(): void
    {
        $this->assertResponseRejected($this->unsignedResponse('other-request'), 'does not match the ID of the AuthNRequest');
    }

    public function test_signed_response_authenticates_with_real_toolkit(): void
    {
        [$response, $certificate] = $this->signedResponse();

        $this->withResponse($response, function () use ($certificate) {
            $user = (new SamlAuth($this->createAuth($certificate)))->getAuthenticatedUser();

            $this->assertSame('user@example.com', $user->getUserId());
            $this->assertSame('session-index', $user->getSessionIndex());
        });
    }

    public function test_tampered_signed_response_is_rejected_by_real_toolkit(): void
    {
        [$response, $certificate] = $this->signedResponse();
        $response = str_replace('user@example.com', 'attacker@example.com', $response);

        $this->assertResponseRejected($response, 'Reference validation failed', $certificate);
    }

    private function signedResponse(): array
    {
        // Generate a throwaway key pair so no private signing key is stored in the repository.
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $request = openssl_csr_new(['commonName' => 'idp.example.com'], $key);
        $certificate = openssl_csr_sign($request, null, $key, 1);
        openssl_pkey_export($key, $privateKey);
        openssl_x509_export($certificate, $publicCertificate);

        return [Utils::addSign($this->unsignedResponse('authn-request'), $privateKey, $publicCertificate), $publicCertificate];
    }

    private function createAuth(?string $certificate = null): Auth
    {
        $config = config('saml');
        $config['debug'] = false;
        $config['idp'] = [
            'entityId' => 'https://idp.example.com/saml',
            'singleSignOnService' => ['url' => 'https://idp.example.com/login'],
            'x509cert' => $certificate ?? __DIR__.'/fixtures/idp.crt',
        ];

        return new Auth(Saml::normalizeConfig($config));
    }

    private function assertResponseRejected(string $response, string $reason, ?string $certificate = null): void
    {
        $this->withResponse($response, function () use ($reason, $certificate) {
            try {
                (new SamlAuth($this->createAuth($certificate)))->getAuthenticatedUser();
                $this->fail('An invalid SAML response must not authenticate.');
            } catch (AssertException $exception) {
                $this->assertSame(['invalid_response'], $exception->errors);
                $this->assertStringContainsString($reason, $exception->lastErrorReason);
            }
        });
    }

    private function withResponse(string $response, callable $callback): void
    {
        $post = $_POST;
        $server = $_SERVER;

        try {
            $_POST['SAMLResponse'] = base64_encode($response);
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['HTTP_HOST'] = 'sp.example.com';
            $_SERVER['SERVER_PORT'] = '443';
            $_SERVER['REQUEST_URI'] = '/saml/acs';
            session(['saml.authnRequestId' => 'authn-request']);

            $callback();
        } finally {
            $_POST = $post;
            $_SERVER = $server;
        }
    }

    private function unsignedResponse(string $requestId): string
    {
        $issued = gmdate('Y-m-d\TH:i:s\Z');
        $expires = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
        $starts = gmdate('Y-m-d\TH:i:s\Z', time() - 60);

        return <<<XML
            <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="response-id" Version="2.0" IssueInstant="{$issued}" Destination="https://sp.example.com/saml/acs" InResponseTo="{$requestId}">
              <saml:Issuer>https://idp.example.com/saml</saml:Issuer>
              <samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>
              <saml:Assertion ID="assertion-id" Version="2.0" IssueInstant="{$issued}">
                <saml:Issuer>https://idp.example.com/saml</saml:Issuer>
                <saml:Subject>
                  <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">user@example.com</saml:NameID>
                  <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                    <saml:SubjectConfirmationData InResponseTo="{$requestId}" Recipient="https://sp.example.com/saml/acs" NotOnOrAfter="{$expires}"/>
                  </saml:SubjectConfirmation>
                </saml:Subject>
                <saml:Conditions NotBefore="{$starts}" NotOnOrAfter="{$expires}">
                  <saml:AudienceRestriction><saml:Audience>https://sp.example.com/saml</saml:Audience></saml:AudienceRestriction>
                </saml:Conditions>
                <saml:AuthnStatement AuthnInstant="{$issued}" SessionIndex="session-index">
                  <saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext>
                </saml:AuthnStatement>
              </saml:Assertion>
            </samlp:Response>
            XML;
    }
}
