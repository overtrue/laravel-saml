<?php

class Util
{
    public static function extractPkeyFromFile($path)
    {
        $result = openssl_get_privatekey($path);

        if (empty($result)) {
            throw new \Exception('Could not read private key-file at path \'' . $path . '\'');
        }

        openssl_pkey_export($result, $pkey);

        return static::extractOpensslString($pkey, 'PRIVATE KEY');
    }

    public static function extractCertFromFile($path)
    {
        $result = openssl_x509_read(file_get_contents($path));

        if (empty($result)) {
            throw new \Exception('Could not read X509 certificate-file at path \'' . $path . '\'');
        }

        openssl_x509_export($result, $cert);

        return static::extractOpensslString($cert, 'CERTIFICATE');
    }

    public static function extractOpensslString(string $contents, string $delimiter)
    {
        $contents = str_replace(["\r", "\n"], "", $contents);
        $regex = '/-{5}BEGIN(?:\s|\w)+' . $delimiter . '-{5}\s*(.+?)\s*-{5}END(?:\s|\w)+' . $delimiter . '-{5}/m';

        preg_match($regex, $contents, $matches);

        return $matches[1] ?? '';
    }
}
