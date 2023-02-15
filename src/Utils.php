<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Support\Str;
use Overtrue\LaravelSaml\Exceptions\InvalidConfigException;

class Utils
{
    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     */
    public static function loadKeyFromFile(string $path)
    {
        if (! \file_exists($path)) {
            throw new InvalidConfigException("Private key file '{$path}' not exists.");
        }

        $privateKey = openssl_get_privatekey(Str::start($path, 'file://'));

        if (empty($privateKey)) {
            throw new InvalidConfigException("Could not parse private key from '{$path}'.");
        }

        openssl_pkey_export($privateKey, $contents);

        return static::extractOpensslString($contents, 'PRIVATE KEY');
    }

    /**
     * @throws \Overtrue\LaravelSaml\Exceptions\InvalidConfigException
     */
    public static function loadCertFromFile(string $path)
    {
        if (! \file_exists($path)) {
            throw new InvalidConfigException("X509 certificate file '{$path}' not exists.");
        }

        $certificate = openssl_x509_read(file_get_contents($path));

        if (empty($certificate)) {
            throw new InvalidConfigException("Could not parse X509 certificate from '{$path}'.");
        }

        openssl_x509_export($certificate, $contents);

        return static::extractOpensslString($contents, 'CERTIFICATE');
    }

    public static function extractOpensslString($contents, $delimiter)
    {
        $contents = str_replace(["\r", "\n"], '', $contents);

        $regex = '/-{5}BEGIN(?:\s|\w)+'.$delimiter.'-{5}\s*(.+?)\s*-{5}END(?:\s|\w)+'.$delimiter.'-{5}/m';

        preg_match($regex, $contents, $matches);

        return empty($matches[1]) ? '' : $matches[1];
    }
}
