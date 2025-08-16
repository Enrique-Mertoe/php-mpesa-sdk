<?php

/**
 * Validator Unit Tests
 *
 * @package MpesaSDK\Tests\Unit
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Tests\Unit;

use MpesaSDK\Tests\TestCase;
use MpesaSDK\Utils\Validator;
use MpesaSDK\Exceptions\ValidationException;

class ValidatorTest extends TestCase
{
    public function testValidPhoneNumber()
    {
        $this->assertTrue(Validator::isValidPhoneNumber('254712345678'));
        $this->assertTrue(Validator::isValidPhoneNumber('0712345678'));
        $this->assertTrue(Validator::isValidPhoneNumber('712345678'));
    }

    public function testInvalidPhoneNumber()
    {
        $this->assertFalse(Validator::isValidPhoneNumber('123'));
        $this->assertFalse(Validator::isValidPhoneNumber('abcdefghij'));
        $this->assertFalse(Validator::isValidPhoneNumber(''));
        $this->assertFalse(Validator::isValidPhoneNumber('2547123456789'));
    }

    public function testValidAmount()
    {
        $this->assertTrue(Validator::isValidAmount(100));
        $this->assertTrue(Validator::isValidAmount(1.50));
        $this->assertTrue(Validator::isValidAmount('100'));
        $this->assertTrue(Validator::isValidAmount('1.50'));
    }

    public function testInvalidAmount()
    {
        $this->assertFalse(Validator::isValidAmount(0));
        $this->assertFalse(Validator::isValidAmount(-10));
        $this->assertFalse(Validator::isValidAmount('abc'));
        $this->assertFalse(Validator::isValidAmount(''));
    }

    public function testValidUrl()
    {
        $this->assertTrue(Validator::isValidUrl('https://example.com'));
        $this->assertTrue(Validator::isValidUrl('http://test.com/callback'));
        $this->assertTrue(Validator::isValidUrl('https://secure.example.com/webhook'));
    }

    public function testInvalidUrl()
    {
        $this->assertFalse(Validator::isValidUrl('not-a-url'));
        $this->assertFalse(Validator::isValidUrl(''));
        $this->assertFalse(Validator::isValidUrl('ftp://example.com'));
    }

    public function testFormatPhoneNumber()
    {
        $this->assertEquals('254712345678', Validator::formatPhoneNumber('0712345678'));
        $this->assertEquals('254712345678', Validator::formatPhoneNumber('712345678'));
        $this->assertEquals('254712345678', Validator::formatPhoneNumber('254712345678'));
        $this->assertEquals('254712345678', Validator::formatPhoneNumber('+254712345678'));
    }

    public function testValidateRequiredFields()
    {
        $data = [
            'phone' => '254712345678',
            'amount' => 100,
            'description' => 'Test payment'
        ];

        $rules = [
            'phone' => 'required|phone',
            'amount' => 'required|amount',
            'description' => 'required'
        ];

        $this->assertTrue(Validator::validate($data, $rules));
    }

    public function testValidateRequiredFieldsThrowsException()
    {
        $this->expectException(ValidationException::class);

        $data = [
            'phone' => '',
            'amount' => 0
        ];

        $rules = [
            'phone' => 'required|phone',
            'amount' => 'required|amount'
        ];

        Validator::validate($data, $rules);
    }
}