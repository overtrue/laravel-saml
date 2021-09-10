<?php

namespace Overtrue\LaravelSaml;

use OneLogin\Saml2\Auth;
use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;

/**
 * @method static void validateAuthentication()
 * @method static \Illuminate\Http\RedirectResponse redirect()
 * @method static \Illuminate\Http\RedirectResponse redirectToLogout()
 * @method static \Overtrue\LaravelSaml\SamlUser getAuthenticatedUser()
 * @method static \Illuminate\Http\RedirectResponse handleLogoutRequest()
 * @method static \Illuminate\Http\Response getMetadataXML()
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse getMetadataXMLAsStreamResponse()
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
    public static function idp(?string $idpName = self::DEFAULT_IDP_NAME, ?array $settings = null): SamlAuth
    {
        if (!isset(self::$resolved[$idpName])) {
            $idpConfig = $settings ?? \call_user_func(self::$idpConfigResolver);

            if (!\is_array($idpConfig) || empty($idpConfig)) {
                throw new InvalidConfigException('Cannot resolve idp config from resolver.');
            }

            $settings = self::normalizeConfig(array_merge(config('saml', []), ['idp' => $idpConfig]));

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
        return \call_user_func_array([self::idp(self::DEFAULT_IDP_NAME), $name], $arguments);
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     */
    public static function normalizeConfig(array $config): array
    {
        if (empty($config['sp']['entityId'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.entityId".');
        }

        if (empty($config['sp']['assertionConsumerService']['url'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.assertionConsumerService.url".');
        }

        if (!empty($config['sp']['singleLogoutService']) && empty($config['sp']['singleLogoutService']['url'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.singleLogoutService.url".');
        }

        if (\file_exists($config['sp']['privateKey'])) {
            $config['sp']['privateKey'] = Utils::loadKeyFromFile($config['sp']['privateKey']);
        }

        if (\file_exists($config['sp']['x509cert'])) {
            $config['sp']['x509cert'] = Utils::loadCertFromFile($config['sp']['x509cert']);
        }

        if (\file_exists($config['idp']['x509cert'])) {
            $config['idp']['x509cert'] = Utils::loadCertFromFile($config['idp']['x509cert']);
        }

        return $config;
    }
}
