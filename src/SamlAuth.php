<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use Overtrue\LaravelSaml\Exceptions\AssertException;
use Overtrue\LaravelSaml\Exceptions\MethodNotFoundException;
use Overtrue\LaravelSaml\Exceptions\UnauthenticatedException;
use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;

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
        return new RedirectResponse(
            $this->auth->login(
                returnTo:        $returnTo,
                parameters:      $parameters,
                forceAuthn:      $forceAuthn,
                isPassive:       $isPassive,
                stay:            true,
                setNameIdPolicy: $setNameIdPolicy,
                nameIdValueReq:  $nameIdValueReq
            )
        );
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
        return new RedirectResponse(
            $this->auth->logout(
                returnTo:              $returnTo,
                parameters:            $parameters,
                nameId:                $nameId,
                sessionIndex:          $sessionIndex,
                stay:                  true,
                nameIdFormat:          $nameIdFormat,
                nameIdNameQualifier:   $nameIdNameQualifier,
                nameIdSPNameQualifier: $nameIdSPNameQualifier
            )
        );
    }

    /**
     * Assertion Consumer Service. Processes the SAML Responses.
     *
     * @throws \Overtrue\LaravelSaml\Exceptions\AssertException
     * @throws \Overtrue\LaravelSaml\Exceptions\UnauthenticatedException
     */
    public function acs(bool $redirectToRelayState = false): RedirectResponse|SamlUser
    {
        try {
            $this->auth->processResponse();
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

        $relayState = $this->request->get('RelayState');

        if ($redirectToRelayState && !!$relayState && \OneLogin\Saml2\Utils::getSelfURL() != $relayState) {
            return new RedirectResponse($relayState);
        }

        return new SamlUser($this->auth);
    }

    /**
     * Process the SAML Logout Response / Logout Request sent by the IdP.
     *
     * @throws \Overtrue\LaravelSaml\Exceptions\AssertException
     */
    public function sls(bool $retrieveParametersFromServer = false)
    {
        $callback = fn () => null;

        try {
            $redirectUrl = $this->auth->processSLO(
                keepLocalSession:             false,
                requestId:                    null,
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

        return null;
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     */
    public function metadata(): Response
    {
        try {
            $settings = $this->auth->getSettings();
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);

            if (empty($errors)) {
                return new Response($metadata, 200, ['Content-Type' => 'text/xml']);
            }

            throw new InvalidConfigException(
                sprintf('Invalid SP metadata: %s', implode(', ', $errors)),
                Error::METADATA_SP_INVALID,
                $this->auth->getLastErrorException()
            );
        } catch (\Throwable $e) {
            throw new InvalidConfigException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\MethodNotFoundException
     */
    public function __call(string $name, array $arguments)
    {
        if (\is_callable([$this->auth, $name])) {
            return \call_user_func_array([$this->auth, $name], $arguments);
        }

        throw new MethodNotFoundException(\sprintf('Method "%s" not found.', $name));
    }
}
