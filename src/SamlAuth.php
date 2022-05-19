<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use Overtrue\LaravelSaml\Exceptions\AssertException;
use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;
use Overtrue\LaravelSaml\Exceptions\MethodNotFoundException;
use Overtrue\LaravelSaml\Exceptions\UnauthenticatedException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SamlAuth
{
    protected Request $request;

    public function __construct(protected Auth $auth, ?Request $request = null)
    {
        $this->request = $request ?? \request();
    }

    /**
     * @throws \OneLogin\Saml2\Error
     */
    public function redirect(
        string $returnTo = null,
        array $parameters = [],
        bool $forceAuthn = false,
        bool $isPassive = false,
        bool $setNameIdPolicy = true,
        string $nameIdValueReq = null
    ): RedirectResponse {
        $redirectUrl = $this->auth->login(
            returnTo:        $returnTo,
            parameters:      $parameters,
            forceAuthn:      $forceAuthn,
            isPassive:       $isPassive,
            stay:            true,
            setNameIdPolicy: $setNameIdPolicy,
            nameIdValueReq:  $nameIdValueReq
        );

        \session(['saml.authnRequestId' => $this->auth->getLastRequestID()]);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws \OneLogin\Saml2\Error
     */
    public function redirectToLogout(
        string $returnTo = null,
        array $parameters = [],
        string $nameId = null,
        string $sessionIndex = null,
        string $nameIdFormat = null,
        string $nameIdNameQualifier = null,
        string $nameIdSPNameQualifier = null
    ): RedirectResponse {
        $redirectUrl = $this->auth->logout(
            returnTo:              $returnTo,
            parameters:            $parameters,
            nameId:                $nameId,
            sessionIndex:          $sessionIndex,
            stay:                  true,
            nameIdFormat:          $nameIdFormat,
            nameIdNameQualifier:   $nameIdNameQualifier,
            nameIdSPNameQualifier: $nameIdSPNameQualifier
        );

        \session(['saml.logoutRequestId' => $this->auth->getLastRequestID()]);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * Assertion Consumer Service. Processes the SAML Responses.
     *
     * @throws \Overtrue\LaravelSaml\Exceptions\AssertException
     * @throws \Overtrue\LaravelSaml\Exceptions\UnauthenticatedException
     */
    public function getAuthenticatedUser(): SamlUser
    {
        $this->validateAuthentication();

        return new SamlUser($this->auth);
    }

    /**
     * Process the SAML Logout Response / Logout Request sent by the IdP.
     *
     * @throws \Overtrue\LaravelSaml\Exceptions\AssertException
     */
    public function handleLogoutRequest(callable $callback = null, bool $retrieveParametersFromServer = false)
    {
        $callback ??= fn () => null;

        try {
            $redirectUrl = $this->auth->processSLO(
                keepLocalSession:             false,
                requestId:                    \session('saml.logoutRequestId'),
                retrieveParametersFromServer: $retrieveParametersFromServer,
                cbDeleteSession:              $callback,
                stay:                         true
            );
        } catch (\Throwable $e) {
            throw new AssertException($this->auth->getErrors(), $this->auth->getLastErrorReason(), $e);
        }

        $errors = $this->auth->getErrors();

        if (!empty($errors)) {
            throw new AssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        \session()->forget('saml.logoutRequestId');

        return null;
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\MethodNotFoundException
     */
    public function __call(
        string $name,
        array $arguments
    ) {
        if (\is_callable([$this->auth, $name])) {
            return \call_user_func_array([$this->auth, $name], $arguments);
        }

        throw new MethodNotFoundException(\sprintf('Method "%s" not found.', $name));
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\AssertException
     * @throws \Overtrue\LaravelSaml\Exceptions\UnauthenticatedException
     */
    public function validateAuthentication(): void
    {
        try {
            $this->auth->processResponse(\session('saml.authnRequestId'));
        } catch (\Throwable $e) {
            throw new AssertException($this->auth->getErrors(), $this->auth->getLastErrorReason(), $e);
        }

        $errors = $this->auth->getErrors();

        if (!empty($errors)) {
            throw new AssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        if (!$this->auth->isAuthenticated()) {
            throw new UnauthenticatedException($this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }
    }
}
