<?php

namespace Tests\Unit\Domain\Entities;

use Library\Domain\Entities\Member;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Test for Member Entity
 * 
 * Evidence for T-06: "Ana " queda "ana" (name normalization)
 */
class MemberTest extends TestCase
{
    public function testMemberCreation(): void
    {
        $member = Member::create(
            id: 'member-123',
            fullName: 'Ana María García',
            documentNumber: '12345678'
        );

        $this->assertEquals('member-123', $member->id());
        $this->assertEquals('ana maría garcía', $member->fullName()); // Normalized to lowercase
        $this->assertEquals('12345678', $member->documentNumber());
    }

    /**
     * T-06 Evidence: "Ana " → "ana" (name normalization)
     */
    public function testNameNormalization(): void
    {
        // Test the specific T-06 evidence case
        $member = Member::create(
            id: 'member-123',
            fullName: 'Ana ',  // With trailing space
            documentNumber: '12345678'
        );

        $this->assertEquals('ana', $member->fullName()); // "Ana " → "ana" ✓
    }

    /**
     * T-06 Evidence: Additional name normalization cases
     */
    public function testNameNormalizationVariousCases(): void
    {
        $testCases = [
            // [input, expected]
            ['Ana ', 'ana'],                    // T-06 specific case
            ['  María  ', 'maría'],             // Leading and trailing spaces
            ['JUAN CARLOS', 'juan carlos'],     // Uppercase to lowercase
            ['  Pedro   José  ', 'pedro josé'], // Multiple spaces collapsed
            ['José María', 'josé maría'],       // Accented characters preserved
            ['Ana-Sofía', 'ana-sofía'],        // Hyphens preserved
            ['O\'Connor', 'o\'connor'],         // Apostrophes preserved
        ];

        foreach ($testCases as [$input, $expected]) {
            $member = Member::create('member-' . uniqid(), $input, '12345678');
            $this->assertEquals($expected, $member->fullName(), "Failed for input: '$input'");
        }
    }

    public function testDisplayName(): void
    {
        $member = Member::create(
            id: 'member-123',
            fullName: 'ana maría garcía',  // Lowercase input
            documentNumber: '12345678'
        );

        // Display name should be in title case
        $this->assertEquals('Ana María García', $member->displayName());
    }

    public function testDocumentNumberTrimming(): void
    {
        $member = Member::create(
            id: 'member-123',
            fullName: 'Ana García',
            documentNumber: '  12345678  ' // With spaces
        );

        $this->assertEquals('12345678', $member->documentNumber()); // Trimmed
    }

    public function testUpdateInfo(): void
    {
        $member = Member::create(
            id: 'member-123',
            fullName: 'Ana García',
            documentNumber: '12345678'
        );

        $updatedMember = $member->updateInfo(
            fullName: '  María José  ',  // With spaces to test normalization
            documentNumber: '  87654321  '  // With spaces to test trimming
        );

        // Original unchanged
        $this->assertEquals('ana garcía', $member->fullName());
        $this->assertEquals('12345678', $member->documentNumber());

        // Updated version normalized
        $this->assertEquals('maría josé', $updatedMember->fullName());
        $this->assertEquals('87654321', $updatedMember->documentNumber());
        $this->assertEquals($member->id(), $updatedMember->id()); // Same ID
    }

    public function testMatchesSearch(): void
    {
        $member = Member::create(
            id: 'member-123',
            fullName: 'Ana María García',
            documentNumber: '12345678'
        );

        // Test name search (case insensitive)
        $this->assertTrue($member->matchesSearch('ana'));
        $this->assertTrue($member->matchesSearch('ANA'));
        $this->assertTrue($member->matchesSearch('maría'));
        $this->assertTrue($member->matchesSearch('GARCÍA'));
        $this->assertTrue($member->matchesSearch('ana maría'));
        
        // Test document search
        $this->assertTrue($member->matchesSearch('12345'));
        $this->assertTrue($member->matchesSearch('12345678'));
        
        // Test no match
        $this->assertFalse($member->matchesSearch('pedro'));
        $this->assertFalse($member->matchesSearch('99999'));
    }

    public function testEmptyNameValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member full name cannot be empty');

        Member::create(
            id: 'member-123',
            fullName: '   ',  // Only spaces
            documentNumber: '12345678'
        );
    }

    public function testEmptyDocumentValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member document number cannot be empty');

        Member::create(
            id: 'member-123',
            fullName: 'Ana García',
            documentNumber: '   '  // Only spaces
        );
    }

    public function testInvalidDocumentCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document number contains invalid characters');

        Member::create(
            id: 'member-123',
            fullName: 'Ana García',
            documentNumber: '123@456'  // Invalid character @
        );
    }

    public function testValidDocumentFormats(): void
    {
        $validDocuments = [
            '12345678',         // Numeric
            'A12345678',        // Alphanumeric
            '12.345.678-9',     // With dots and dash
            'ID-123456',        // With dash prefix
            'DOC123',           // Mixed case letters and numbers
        ];

        foreach ($validDocuments as $doc) {
            $member = Member::create('member-' . uniqid(), 'Ana García', $doc);
            $this->assertEquals($doc, $member->documentNumber());
        }
    }

    public function testMemberEquality(): void
    {
        $member1 = Member::create('member-123', 'Ana García', '12345678');
        $member2 = Member::create('member-123', 'Different Name', '87654321'); // Same ID
        $member3 = Member::create('member-456', 'Ana García', '12345678'); // Different ID

        $this->assertTrue($member1->equals($member2)); // Same ID
        $this->assertFalse($member1->equals($member3)); // Different ID
    }

    public function testFromPersistence(): void
    {
        // Simulate data already normalized in persistence
        $member = Member::fromPersistence(
            id: 'member-123',
            fullName: 'ana maría garcía', // Already normalized
            documentNumber: '12345678'
        );

        $this->assertEquals('member-123', $member->id());
        $this->assertEquals('ana maría garcía', $member->fullName());
        $this->assertEquals('12345678', $member->documentNumber());
    }

    /**
     * Comprehensive test for T-06 evidence: name normalization
     */
    public function testT06EvidenceComprehensive(): void
    {
        // T-06 Evidence: "Ana " queda "ana"
        $member1 = Member::create('member-1', 'Ana ', '12345');
        $this->assertEquals('ana', $member1->fullName()); // ✓

        // Additional normalization evidence
        $member2 = Member::create('member-2', '  MARÍA JOSÉ  ', '67890');
        $this->assertEquals('maría josé', $member2->fullName()); // Spaces trimmed, lowercase ✓

        // Verify display name works correctly
        $this->assertEquals('María José', $member2->displayName()); // Proper case for display ✓

        // Verify search works with normalized data
        $this->assertTrue($member2->matchesSearch('maria')); // Case insensitive search ✓
        $this->assertTrue($member2->matchesSearch('JOSÉ')); // Case insensitive search ✓

        // All T-06 name normalization evidence satisfied ✓
    }
}