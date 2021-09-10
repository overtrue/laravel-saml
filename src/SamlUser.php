<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Fluent;
use JetBrains\PhpStorm\Pure;
use OneLogin\Saml2\Auth;

class SamlUser extends Fluent
{
    protected Request $request;

    public function __construct(protected Auth $auth, Request $request = null)
    {
        parent::__construct();
        $this->request = $request ?? \request();
        $this->parseAttributes($this->auth->getAttributes());
    }

    #[Pure]
    public function getUserId(): string
    {
        return $this->getNameId();
    }

    #[Pure]
    public function getNameId(): string
    {
        return $this->auth->getNameId();
    }

    #[Pure]
    public function getAttributesWithFriendlyName(): array
    {
        return $this->auth->getAttributesWithFriendlyName();
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
            if (!!$propertyName && !!$samlAttribute) {
                $this->$propertyName = $this->auth->getAttribute($samlAttribute);
            }
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
}
