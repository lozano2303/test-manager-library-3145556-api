<?php

namespace Tests\Unit\Domain\ValueObjects;

use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Test for Quantity Value Object
 * 
 * Evidence for T-03: cantidad > 0 (quantity must be positive)
 * Tests all validation rules and operations
 */
class QuantityTest extends TestCase
{
    public function testValidQuantityIsCreated(): void
    {
        $quantity = Quantity::from(5);
        
        $this->assertEquals(5, $quantity->value());
        $this->assertEquals('5', (string) $quantity);
    }

    public function testQuantityCannotBeNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than or equal to 0');
        
        Quantity::from(-1);
    }

    public function testQuantityCanBeZero(): void
    {
        $quantity = Quantity::zero();
        
        $this->assertEquals(0, $quantity->value());
        $this->assertTrue($quantity->isZero());
        $this->assertFalse($quantity->isPositive());
    }

    public function testQuantityFromStaticMethod(): void
    {
        $quantity = Quantity::from(10);
        
        $this->assertEquals(10, $quantity->value());
        $this->assertTrue($quantity->isPositive());
        $this->assertFalse($quantity->isZero());
    }

    public function testQuantityEquality(): void
    {
        $quantity1 = Quantity::from(5);
        $quantity2 = Quantity::from(5);
        $quantity3 = Quantity::from(10);
        
        $this->assertTrue($quantity1->equals($quantity2));
        $this->assertFalse($quantity1->equals($quantity3));
    }

    public function testQuantityComparisons(): void
    {
        $small = Quantity::from(3);
        $large = Quantity::from(7);
        
        $this->assertTrue($large->greaterThan($small));
        $this->assertTrue($large->greaterThanOrEqual($small));
        $this->assertTrue($small->lessThan($large));
        $this->assertFalse($small->greaterThan($large));
    }

    public function testQuantityAddition(): void
    {
        $quantity1 = Quantity::from(3);
        $quantity2 = Quantity::from(5);
        
        $result = $quantity1->add($quantity2);
        
        $this->assertEquals(8, $result->value());
        $this->assertEquals(3, $quantity1->value()); // Original unchanged
        $this->assertEquals(5, $quantity2->value()); // Original unchanged
    }

    public function testQuantitySubtraction(): void
    {
        $quantity1 = Quantity::from(10);
        $quantity2 = Quantity::from(3);
        
        $result = $quantity1->subtract($quantity2);
        
        $this->assertEquals(7, $result->value());
        $this->assertEquals(10, $quantity1->value()); // Original unchanged
        $this->assertEquals(3, $quantity2->value()); // Original unchanged
    }

    public function testQuantitySubtractionCannotGoNegative(): void
    {
        $quantity1 = Quantity::from(3);
        $quantity2 = Quantity::from(5);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative after subtraction');
        
        $quantity1->subtract($quantity2);
    }

    public function testQuantitySubtractionToZero(): void
    {
        $quantity1 = Quantity::from(5);
        $quantity2 = Quantity::from(5);
        
        $result = $quantity1->subtract($quantity2);
        
        $this->assertEquals(0, $result->value());
        $this->assertTrue($result->isZero());
    }

    /**
     * Data provider for valid quantities
     */
    public function validQuantityProvider(): array
    {
        return [
            [0],
            [1], 
            [5],
            [100],
            [999999],
        ];
    }

    /**
     * @dataProvider validQuantityProvider
     */
    public function testValidQuantitiesAreAccepted(int $validQuantity): void
    {
        $quantity = Quantity::from($validQuantity);
        
        $this->assertEquals($validQuantity, $quantity->value());
    }

    /**
     * Data provider for invalid quantities
     */
    public function invalidQuantityProvider(): array
    {
        return [
            [-1],
            [-5], 
            [-100],
        ];
    }

    /**
     * @dataProvider invalidQuantityProvider
     */
    public function testInvalidQuantitiesAreRejected(int $invalidQuantity): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        Quantity::from($invalidQuantity);
    }

    public function testQuantityPositiveCheck(): void
    {
        $zero = Quantity::zero();
        $positive = Quantity::from(1);
        
        $this->assertFalse($zero->isPositive());
        $this->assertTrue($positive->isPositive());
    }

    public function testQuantityGreaterThanOrEqual(): void
    {
        $five = Quantity::from(5);
        $alsoFive = Quantity::from(5);
        $three = Quantity::from(3);
        
        $this->assertTrue($five->greaterThanOrEqual($alsoFive));
        $this->assertTrue($five->greaterThanOrEqual($three));
        $this->assertFalse($three->greaterThanOrEqual($five));
    }
}