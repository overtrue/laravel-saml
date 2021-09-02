<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Fluent;
use OneLogin\Saml2\Auth;

class SamlUser extends Fluent
{
    public function __construct(protected Auth $auth, protected Request $request)
    {
    }

    public function getUserId()
    {
        return $this->getNameId();
    }

    public function getAttributesWithFriendlyName()
    {
        return $this->auth->getAttributesWithFriendlyName();
    }

    public function getRawSamlAssertion()
    {
        return $this->request->input('SAMLResponse');
    }

    public function getIntendedUrl()
    {
        $relayState = $this->request->input('RelayState');

        if ($relayState && URL::full() != $relayState) {
            return $relayState;
        }
    }

    public function parseAttributes($attributes = [])
    {
        foreach ($attributes as $propertyName => $samlAttribute) {
            $this->parseUserAttribute($samlAttribute, $propertyName);
        }
    }

    public function parseUserAttribute(string $samlAttribute = null, string $propertyName = null)
    {
        if (empty($samlAttribute)) {
            return null;
        }
        if (empty($propertyName)) {
            return $this->getAttribute($samlAttribute);
        }

        return $this->setAttribute($propertyName, $this->auth->getAttribute($samlAttribute));
    }

    public function getSessionIndex()
    {
        return $this->auth->getSessionIndex();
    }

    public function getNameId()
    {
        return $this->auth->getNameId();
    }
}
