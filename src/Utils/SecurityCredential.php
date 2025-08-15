<?php

/**
 * M-Pesa SDK Security Credential Utility
 * 
 * Handles security credential generation and encryption for M-Pesa API.
 * Used for B2C, B2B, and other services requiring security credentials.
 * 
 * @package MpesaSDK\Utils
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Utils;

use MpesaSDK\Exceptions\MpesaException;

class SecurityCredential
{
    /**
     * Generate security credential by encrypting initiator password with M-Pesa public key
     */
    public static function generate(string $initiatorPassword, string $environment = 'sandbox'): string
    {
        $publicKey = self::getPublicKey($environment);
        
        if (!$publicKey) {
            throw new MpesaException('Failed to load M-Pesa public key');
        }
        
        $encrypted = '';
        if (!openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new MpesaException('Failed to encrypt security credential');
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * Get M-Pesa public key for the specified environment
     */
    private static function getPublicKey(string $environment): ?string
    {
        if ($environment === 'production') {
            return self::getProductionPublicKey();
        }
        
        return self::getSandboxPublicKey();
    }
    
    /**
     * Get sandbox public key
     */
    private static function getSandboxPublicKey(): string
    {
        return "-----BEGIN CERTIFICATE-----
MIIGkzCCBXugAwIBAgIKBxiO5WBdXH8jfTANBgkqhkiG9w0BAQsFADByMQswCQYD
VQQGEwJLRTEQMA4GA1UECAwHVW5rbm93bjEQMA4GA1UEBwwHVW5rbm93bjEQMA4G
A1UECgwHVW5rbm93bjEQMA4GA1UECwwHVW5rbm93bjEbMBkGA1UEAwwSc2FuZGJv
eC5zYWZhcmljb20uY28wHhcNMjMwNDE3MDkwNjA3WhcNMjQwNDE2MDkwNjA3WjBy
MQswCQYDVQQGEwJLRTEQMA4GA1UECAwHVW5rbm93bjEQMA4GA1UEBwwHVW5rbm93
bjEQMA4GA1UECgwHVW5rbm93bjEQMA4GA1UECwwHVW5rbm93bjEbMBkGA1UEAwwS
c2FuZGJveC5zYWZhcmljb20uY28wggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIK
AoICAQC2uw==
-----END CERTIFICATE-----";
    }
    
    /**
     * Get production public key
     */
    private static function getProductionPublicKey(): string
    {
        return "-----BEGIN CERTIFICATE-----
MIIGkzCCBXugAwIBAgIKBxiO5WBdXH8jfTANBgkqhkiG9w0BAQsFADByMQswCQYD
VQQGEwJLRTEQMA4GA1UECAwHVW5rbm93bjEQMA4GA1UEBwwHVW5rbm93bjEQMA4G
A1UECgwHVW5rbm93bjEQMA4GA1UECwwHVW5rbm93bjEbMBkGA1UEAwwSYXBpLnNh
ZmFyaWNvbS5jby5rZTAeFw0yMzA0MTcwOTA2MDdaFw0yNDA0MTYwOTA2MDdaMHIx
CzAJBgNVBAYTAktFMRAwDgYDVQQIDAdVbmtub3duMRAwDgYDVQQHDAdVbmtub3du
MRAwDgYDVQQKDAdVbmtub3duMRAwDgYDVQQLDAdVbmtub3duMRswGQYDVQQDDBJh
cGkuc2FmYXJpY29tLmNvLmtlMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKC
AgEAuw==
-----END CERTIFICATE-----";
    }
}