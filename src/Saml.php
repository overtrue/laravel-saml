<?php

namespace Overtrue\LaravelSaml;

use OneLogin\Saml2\Auth;
use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;

/**
 * @method static \Illuminate\Http\RedirectResponse redirect()
 * @method static \Illuminate\Http\RedirectResponse redirectToLogout()
 * @method static \Illuminate\Http\RedirectResponse|\Overtrue\LaravelSaml\SamlUser acs()
 * @method static \Illuminate\Http\RedirectResponse sls()
 * @method static \Illuminate\Http\Response metadata()
 * @method static \Illuminate\Http\StreamResponse metadataAsStreamResponse()
 * @method static \Overtrue\LaravelSaml\SamlUser getSamlUser()
 */
class Saml
{
    public const DEFAULT_IDP_NAME = 'default';

    protected static array $resolved;
    protected static \Closure $idpConfigResolver;

    /**
     * @throws \OneLogin\Saml2\Error
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     */
    public static function fromIdp(string $idpName, ?array $settings = null): SamlAuth
    {
        if (!isset(self::$resolved[$idpName])) {
            $idpConfig = $settings ?? \call_user_func(self::$idpConfigResolver);

            if (!\is_array($idpConfig) || empty($idpConfig)) {
                throw new InvalidConfigException('Cannot resolve idp config from resolver.');
            }

            $settings = array_merge(config('saml', []), ['idp' => $idpConfig]);

            self::$resolved[$idpName] = new SamlAuth(new Auth($settings));
        }

        return self::$resolved[$idpName];
    }

    public static function configureIdpUsing(\Closure $closure)
    {
        self::$idpConfigResolver = $closure;
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     * @throws \OneLogin\Saml2\Error
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return \call_user_func_array([self::fromIdp(self::DEFAULT_IDP_NAME), $name], $arguments);
    }
}
