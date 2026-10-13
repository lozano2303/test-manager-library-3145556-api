<?php

namespace Tests\Unit\Domain\ValueObjects;

use Library\Domain\ValueObjects\Isbn;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Test for Isbn Value Object
 * 
 * Evidence for T-03: ISBN de 13 dígitos
 * Tests all validation rules and behavior
 */
class IsbnTest extends TestCase
{
    public function testValidIsbnIsCreated(): void
    {
        $isbn = Isbn::from('9780307474728');
        
        $this->assertEquals('9780307474728', $isbn->value());
        $this->assertEquals('9780307474728', (string) $isbn);
    }

    public function testIsbnMustBe13Digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must be exactly 13 digits');
        
        Isbn::from('123456789012'); // 12 digits
    }

    public function testIsbnCannotBe14Digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must be exactly 13 digits');
        
        Isbn::from('12345678901234'); // 14 digits
    }

    public function testIsbnMustBeNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must contain only digits');
        
        Isbn::from('978030747472A');
    }

    public function testIsbnCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN cannot be empty');
        
        Isbn::from('');
    }

    public function testIsbnCannotContainSpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must contain only digits');
        
        Isbn::from('978 0307 47472');
    }

    public function testIsbnCannotContainDashes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must contain only digits');
        
        Isbn::from('978-0307-47472');
    }

    public function testIsbnEquality(): void
    {
        $isbn1 = Isbn::from('9780307474728');
        $isbn2 = Isbn::from('9780307474728');
        $isbn3 = Isbn::from('9780465026562');
        
        $this->assertTrue($isbn1->equals($isbn2));
        $this->assertFalse($isbn1->equals($isbn3));
    }

    public function testIsbnWithLeadingZeros(): void
    {
        $isbn = Isbn::from('0000000000001');
        
        $this->assertEquals('0000000000001', $isbn->value());
    }

    /**
     * Data provider for valid 13-digit ISBNs
     */
    public function validIsbnProvider(): array
    {
        return [
            ['9780307474728'],
            ['9780465026562'],
            ['0000000000001'],
            ['1234567890123'],
            ['9999999999999'],
        ];
    }

    /**
     * @dataProvider validIsbnProvider
     */
    public function testValidIsbnsAreAccepted(string $validIsbn): void
    {
        $isbn = Isbn::from($validIsbn);
        
        $this->assertEquals($validIsbn, $isbn->value());
    }

    /**
     * Data provider for invalid ISBNs
     */
    public function invalidIsbnProvider(): array
    {
        return [
            [''],
            ['12345678901'],    // 11 digits
            ['123456789012'],   // 12 digits
            ['12345678901234'], // 14 digits
            ['123456789012345'], // 15 digits
            ['978030747472A'],  // Contains letter
            ['978-0307-47472'], // Contains dashes
            ['978 0307 47472'],  // Contains spaces
            ['978.0307.47472'],  // Contains dots
        ];
    }

    /**
     * @dataProvider invalidIsbnProvider
     */
    public function testInvalidIsbnsAreRejected(string $invalidIsbn): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        Isbn::from($invalidIsbn);
    }
}