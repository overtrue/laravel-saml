<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Fluent;
use JetBrains\PhpStorm\Pure;
use OneLogin\Saml2\Auth;

/**
 * @method string getNameIdFormat()
 * @method string getNameIdNameQualifier()
 * @method string getNameIdSPNameQualifier()
 * @method array|null getAttributeWithFriendlyName($friendlyName)
 * @method array|null getAttributesWithFriendlyName()
 */
class SamlUser extends Fluent
{
    protected Request $request;

    public function __construct(protected Auth $auth, ?Request $request = null)
    {
        parent::__construct();
        $this->request = $request ?? \request();
        $this->parseAttributes($this->auth->getAttributes());
    }

    #[Pure]
    public function getUserId(): string
    {
        return $this->auth->getNameId();
    }

    public function getIntendedUrl()
    {
        $relayState = $this->request->input('RelayState');

        if ($relayState && URL::full() != $relayState) {
            return $relayState;
        }

        return null;
    }

    public function getSamlAttribute(string $attribute): ?array
    {
        return $this->auth->getAttribute($attribute);
    }

    public function parseAttributes($attributes = []): static
    {
        foreach ($attributes as $propertyName => $samlAttribute) {
            $this->$propertyName = $this->auth->getAttribute($propertyName);
        }

        return $this;
    }

    #[Pure]
    public function getSessionIndex(): ?string
    {
        return $this->auth->getSessionIndex();
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    public function __call($method, $parameters)
    {
        return \call_user_func_array([$this->auth, $method], $parameters);
    }
}
