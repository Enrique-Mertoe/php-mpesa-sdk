<?php

/**
 * M-Pesa SDK Validation Utility
 *
 * Provides validation methods for M-Pesa API parameters including
 * phone numbers, amounts, URLs, and other transaction data.
 *
 * @package MpesaSDK\Utils
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Utils;

use MpesaSDK\Exceptions\ValidationException;

class Validator
{
    /**
     * Validate phone number format
     */
    public static function validatePhoneNumber(string $phoneNumber): string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (empty($cleaned)) {
            throw new ValidationException('Phone number cannot be empty');
        }
        if (str_starts_with($cleaned, '254'))
            $cleaned = substr($cleaned, 3);

        // Handle different formats
        if (strlen($cleaned) == 10 && str_starts_with($cleaned, '0')) {
            // Convert 0712345678 to 254712345678
            $cleaned = '254' . substr($cleaned, 1);
        } elseif (strlen($cleaned) == 9) {
            // Convert 712345678 to 254712345678
            $cleaned = '254' . $cleaned;
        } elseif (strlen($cleaned) == 12 || strlen($cleaned) == 13) {
            // Already in correct format
        } else {
            throw new ValidationException('Invalid phone number format. Expected formats: 0712345678, 712345678, or 254712345678');
        }

        // Validate Kenyan mobile number prefixes
        $validPrefixes = ['2547', '2541','25401','25407'];

        $prefix = substr($cleaned, 0, 5);
        if (!in_array($prefix, $validPrefixes)) {
            throw new ValidationException("Invalid Kenyan mobile number prefix $prefix");
        }

        return $cleaned;
    }

    /**
     * Validate amount
     */
    public static function validateAmount($amount): float
    {
        if (!is_numeric($amount)) {
            throw new ValidationException('Amount must be numeric');
        }

        $amount = (float)$amount;

        if ($amount <= 0) {
            throw new ValidationException('Amount must be greater than zero');
        }

        if ($amount > 70000) {
            throw new ValidationException('Amount cannot exceed KES 70,000');
        }

        // Round to 2 decimal places
        return round($amount, 2);
    }

    /**
     * Validate business short code
     */
    public static function validateBusinessShortCode(string $shortCode): string
    {
        $shortCode = trim($shortCode);

        if (empty($shortCode)) {
            throw new ValidationException('Business short code cannot be empty');
        }

        if (!preg_match('/^[0-9]+$/', $shortCode)) {
            throw new ValidationException('Business short code must contain only numbers');
        }

        if (strlen($shortCode) < 5 || strlen($shortCode) > 7) {
            throw new ValidationException('Business short code must be between 5-7 digits');
        }

        return $shortCode;
    }

    /**
     * Validate account reference
     */
    public static function validateAccountReference(string $reference): string
    {
        $reference = trim($reference);

        if (empty($reference)) {
            throw new ValidationException('Account reference cannot be empty');
        }

        if (strlen($reference) > 12) {
            throw new ValidationException('Account reference cannot exceed 12 characters');
        }

        // Remove special characters except alphanumeric and common business characters
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $reference)) {
            throw new ValidationException('Account reference contains invalid characters. Use only alphanumeric, dash, underscore, and period');
        }

        return $reference;
    }

    /**
     * Validate transaction description
     */
    public static function validateTransactionDescription(string $description): string
    {
        $description = trim($description);

        if (empty($description)) {
            throw new ValidationException('Transaction description cannot be empty');
        }

        if (strlen($description) > 13) {
            throw new ValidationException('Transaction description cannot exceed 13 characters');
        }

        return $description;
    }

    /**
     * Validate callback URL
     */
    public static function validateCallbackUrl(string $url): string
    {
        $url = trim($url);

        if (empty($url)) {
            throw new ValidationException('Callback URL cannot be empty');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid callback URL format');
        }

        $parsedUrl = parse_url($url);
        if (!in_array($parsedUrl['scheme'], ['http', 'https'])) {
            throw new ValidationException('Callback URL must use HTTP or HTTPS protocol');
        }

        return $url;
    }

    /**
     * Validate transaction ID
     */
    public static function validateTransactionId(string $transactionId): string
    {
        $transactionId = trim($transactionId);

        if (empty($transactionId)) {
            throw new ValidationException('Transaction ID cannot be empty');
        }

        if (strlen($transactionId) < 10 || strlen($transactionId) > 15) {
            throw new ValidationException('Transaction ID must be between 10-15 characters');
        }

        if (!preg_match('/^[A-Z0-9]+$/', $transactionId)) {
            throw new ValidationException('Transaction ID must contain only uppercase letters and numbers');
        }

        return $transactionId;
    }

    /**
     * Validate command ID
     */
    public static function validateCommandId(string $commandId): string
    {
        $validCommands = [
            'SalaryPayment',
            'BusinessPayment',
            'PromotionPayment',
            'AccountBalance',
            'CustomerPayBillOnline',
            'TransactionStatusQuery',
            'CheckIdentity',
            'BusinessPayToBulk',
            'BusinessBuyGoods',
            'DisburseFundsToBusiness',
            'BusinessToBusinessTransfer',
            'BusinessTransferFromMMFToUtility'
        ];

        if (!in_array($commandId, $validCommands)) {
            throw new ValidationException('Invalid command ID: ' . $commandId);
        }

        return $commandId;
    }

    /**
     * Validate environment
     */
    public static function validateEnvironment(string $environment): string
    {
        $validEnvironments = ['sandbox', 'production'];

        if (!in_array($environment, $validEnvironments)) {
            throw new ValidationException('Invalid environment. Must be either sandbox or production');
        }

        return $environment;
    }

    /**
     * Validate all required fields are present
     */
    public static function validateRequired(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new ValidationException('Missing required fields: ' . implode(', ', $missing));
        }
    }
}