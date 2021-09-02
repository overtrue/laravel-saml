<?php

use Illuminate\Http\Request;
use OneLogin\Saml2\Auth;
use Overtrue\LaravelSaml\SamlAssertException;
use Overtrue\LaravelSaml\SamlAuthenticationException;
use Overtrue\LaravelSaml\SamlConfigException;

class SamlAuth
{
    public function __construct(protected Auth $auth, protected Request $request)
    {
    }

    public function fromIdp(array $settings)
    {
        //TODO: Implement fromIdp() method.
        return new Auth($settings);
    }

    public function authenticated()
    {
        return $this->auth->isAuthenticated();
    }

    public function getSamlUser()
    {
        return new SamlUser($this->auth);
    }

    public function getLastMessageId()
    {
        return $this->auth->getLastMessageId();
    }

    public function login(
        string $returnTo = null,
        array $parameters = [],
        bool $forceAuthn = false,
        bool $isPassive = false,
        bool $stay = false,
        bool $setNameIdPolicy = true
    ) {
        return $this->auth->login($returnTo, $parameters, $forceAuthn, $isPassive, $stay, $setNameIdPolicy);
    }

    public function logout(
        string $returnTo = null,
        string $nameId = null,
        string $sessionIndex = null,
        string $nameIdFormat = null,
        bool $stay = false,
        string $nameIdNameQualifier = null
    ) {
        return $this->auth->logout($returnTo, [], $nameId, $sessionIndex, $stay, $nameIdFormat, $nameIdNameQualifier);
    }

    /**
     * Process a Saml response (assertion consumer service)
     *
     * @return void
     */
    public function acs()
    {
        $this->auth->processResponse();

        $errors = $this->auth->getErrors();

        if (!empty($errors)) {
            throw new SamlAssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        if (!$this->auth->isAuthenticated()) {
            throw new SamlAuthenticationException($this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        return null;
    }

    /**
     * Process a Saml response (assertion consumer service)
     */
    public function sls($retrieveParametersFromServer = false)
    {
        $callback = fn () => null;

        $this->auth->processSLO(false, null, $retrieveParametersFromServer, $callback);

        $errors = $this->auth->getErrors();

        if (!empty($errors)) {
            throw new SamlAssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        return null;
    }

    public function getMetadata()
    {
        $settings = $this->auth->getSettings();
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if (empty($errors)) {
            return $metadata;
        }

        throw new SamlConfigException(
            sprintf('Invalid SP metadata: %s', implode(', ', $errors)),
            OneLogin\Saml2\Error::METADATA_SP_INVALID,
            $this->auth->getLastErrorException()
        );
    }

    public function getLastErrorReason()
    {
        return $this->auth->getLastErrorReason();
    }

    public function getLastErrorException()
    {
        return $this->auth->getLastErrorException();
    }
}
