<?php

namespace Omnipay\SebLink\Utils;

/**
 * Pizza protocol helper functions
 *
 * @package Omnipay\SebLink\Utils
 */
class Pizza
{
    /**
     * Returns base64 encoded control code
     *
     * @param array $data
     * @param $encoding
     * @param $privateCertPath
     * @param null $passPhrase
     * @return string
     */
    public static function generateControlCode(array $data, $encoding, $privateCertPath, $passPhrase = null)
    {
        $hash = self::createHash($data, $encoding);

        $certContent = file_get_contents($privateCertPath);
        $privateKey = openssl_get_privatekey($certContent, $passPhrase);
        openssl_sign($hash, $controlCode, $privateKey);
        openssl_free_key($privateKey);

        return base64_encode($controlCode);
    }

    /**
     * @param array $data
     * @param $encoding
     * @return string
     */
    public static function createHash(array $data, $encoding)
    {
        $hash = '';
        foreach ($data as $fieldName => $fieldValue) {
            $content = $data[$fieldName];
            $length = mb_strlen($content, $encoding);
            $hash .= str_pad($length, 3, '0', STR_PAD_LEFT) . $content;
        }
        return $hash;
    }

    /**
     * Verifies if control code is valid for data
     *
     * @param array $data
     * @param $signatureEncoded
     * @param $publicCertPath
     * @param $encoding
     * @return bool
     */
    public static function isValidControlCode(array $data, $signatureEncoded, $publicCertPath, $encoding)
    {
        $hash = self::createHash($data, $encoding);
        $signature = base64_decode($signatureEncoded);
        $certContent = file_get_contents($publicCertPath);
        $publicKey = openssl_get_publickey($certContent);

        if ($publicKey === false) {
            throw new \RuntimeException('Certificate error :' . openssl_error_string());
        }

        $result = openssl_verify($hash, $signature, $publicKey);

        openssl_free_key($publicKey);

        if ($result !== 1 && $result !== 0) {
            // OpenSSL error, problem with pem certificate
            throw new \RuntimeException('Verification error :' . openssl_error_string());
        }

        return boolval($result);
    }

    /**
     * Test encoding/decoding by comparing results, this will allow also help debugging certificate file problems
     *
     * @param array $data
     * @param $privateCertPath
     * @param $encoding
     * @param null $passPhrase
     * @return bool
     */
    public static function test(array $data, $privateCertPath, $encoding, $passPhrase = null)
    {
        $cCode = self::generateControlCode($data, $encoding, $privateCertPath, $passPhrase);
        $result = self::isValidControlCode($data, $cCode, $privateCertPath, $encoding);
        return $result;
    }

}