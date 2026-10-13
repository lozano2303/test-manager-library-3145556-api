<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Library\Domain\Entities\Member;
use Library\Domain\Entities\User;
use InvalidArgumentException;

/**
 * Console command to verify T-06 Member and User Domain Entities evidence
 * 
 * Verifies:
 * - Member name normalization: "Ana " → "ana"
 * - User role validation: admin role rejected via API
 */
class VerifyMemberUserDomainCommand extends Command
{
    protected $signature = 'verify:member-user-domain';
    protected $description = 'Verify T-06 Member and User Domain Entities evidence: name normalization and role validation';

    public function handle(): int
    {
        $this->info('🔍 Verifying T-06 Member and User Domain Entities Evidence...');
        $this->newLine();

        $allPassed = true;

        // Test 1: Member name normalization
        $this->info('1. Testing Member name normalization ("Ana " → "ana")');
        if ($this->verifyMemberNameNormalization()) {
            $this->info('   ✅ Member normalization: "Ana " becomes "ana" - PASSED');
        } else {
            $this->error('   ❌ Member normalization: name normalization not working - FAILED');
            $allPassed = false;
        }

        // Test 2: User role validation
        $this->info('2. Testing User role validation (admin rejected via API)');
        if ($this->verifyUserRoleValidation()) {
            $this->info('   ✅ User roles: admin rejected via API, librarian allowed - PASSED');
        } else {
            $this->error('   ❌ User roles: role validation not working - FAILED');
            $allPassed = false;
        }

        // Test 3: Username normalization
        $this->info('3. Testing User username normalization');
        if ($this->verifyUsernameNormalization()) {
            $this->info('   ✅ Username normalization: spaces removed, lowercase applied - PASSED');
        } else {
            $this->error('   ❌ Username normalization: not working correctly - FAILED');
            $allPassed = false;
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('🎉 T-06 Evidence VERIFIED: Member and User domain entities work correctly!');
            $this->info('   • Member normalization: "Ana " → "ana" (case and whitespace)');
            $this->info('   • User roles: admin role rejected via API, librarian allowed');
            $this->info('   • Username normalization: lowercase and space removal');
            return self::SUCCESS;
        } else {
            $this->error('💥 T-06 Evidence FAILED: Some Member/User operations are not working');
            return self::FAILURE;
        }
    }

    private function verifyMemberNameNormalization(): bool
    {
        try {
            // T-06 specific evidence: "Ana " → "ana"
            $member1 = Member::create('member-1', 'Ana ', '12345');
            if ($member1->fullName() !== 'ana') {
                $this->error('   Expected "Ana " to become "ana", got: "' . $member1->fullName() . '"');
                return false;
            }

            // Additional normalization tests
            $testCases = [
                ['  MARÍA JOSÉ  ', 'maría josé'],      // Uppercase + spaces
                ['Juan Carlos', 'juan carlos'],        // Mixed case
                ['  pedro   luis  ', 'pedro luis'],    // Multiple spaces
                ['Ana-Sofía', 'ana-sofía'],           // Hyphens preserved
                ['José', 'josé'],                      // Accents preserved
            ];

            foreach ($testCases as [$input, $expected]) {
                $member = Member::create('member-' . uniqid(), $input, '12345');
                if ($member->fullName() !== $expected) {
                    $this->error("   Failed normalization: '$input' → expected '$expected', got '{$member->fullName()}'");
                    return false;
                }
            }

            // Test display name conversion
            $member2 = Member::create('member-2', 'ana maría', '67890');
            if ($member2->displayName() !== 'Ana María') {
                $this->error('   Display name conversion failed');
                return false;
            }

            // Test search functionality
            $member3 = Member::create('member-3', 'Carlos González', '11111');
            if (!$member3->matchesSearch('carlos') || !$member3->matchesSearch('GONZÁLEZ')) {
                $this->error('   Search functionality failed');
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing Member normalization: " . $e->getMessage());
            return false;
        }
    }

    private function verifyUserRoleValidation(): bool
    {
        try {
            // Test librarian role allowed via API
            $librarian = User::createViaApi('user-1', 'librarian1', 'hash123', 'librarian');
            if ($librarian->role() !== 'librarian') {
                $this->error('   Librarian creation via API failed');
                return false;
            }

            // T-06 Evidence: admin role rejected via API
            try {
                User::createViaApi('user-2', 'admin1', 'hash123', 'admin');
                $this->error('   Admin role should have been rejected via API');
                return false;
            } catch (InvalidArgumentException $e) {
                if (!str_contains($e->getMessage(), 'Role "admin" cannot be created via API')) {
                    $this->error('   Wrong error message for admin role rejection');
                    return false;
                }
                // Expected exception - admin role correctly rejected
            }

            // Test invalid role rejected
            try {
                User::createViaApi('user-3', 'user1', 'hash123', 'invalid');
                $this->error('   Invalid role should have been rejected');
                return false;
            } catch (InvalidArgumentException $e) {
                // Expected exception
            }

            // Test admin can still be created via system method
            $admin = User::create('user-4', 'admin1', 'hash123', 'admin');
            if ($admin->role() !== 'admin') {
                $this->error('   Admin creation via system method failed');
                return false;
            }

            // Test role permissions
            if (!$admin->canPerformAdminOperations()) {
                $this->error('   Admin permissions not working');
                return false;
            }

            if (!$admin->canPerformLibrarianOperations()) {
                $this->error('   Admin should be able to perform librarian operations');
                return false;
            }

            if ($librarian->canPerformAdminOperations()) {
                $this->error('   Librarian should not have admin permissions');
                return false;
            }

            if (!$librarian->canPerformLibrarianOperations()) {
                $this->error('   Librarian permissions not working');
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing User role validation: " . $e->getMessage());
            return false;
        }
    }

    private function verifyUsernameNormalization(): bool
    {
        try {
            $testCases = [
                ['  UserName  ', 'username'],           // Spaces and case
                ['User Name', 'username'],              // Internal space removed
                ['ADMIN123', 'admin123'],               // Uppercase to lowercase
                ['user.name_123', 'user.name_123'],    // Valid characters preserved
                ['user-name', 'user-name'],            // Hyphens preserved
            ];

            foreach ($testCases as [$input, $expected]) {
                $user = User::create('user-' . uniqid(), $input, 'hash', 'librarian');
                if ($user->username() !== $expected) {
                    $this->error("   Username normalization failed: '$input' → expected '$expected', got '{$user->username()}'");
                    return false;
                }
            }

            // Test username validation
            try {
                User::create('user-invalid', 'ab', 'hash', 'librarian'); // Too short
                $this->error('   Short username should be rejected');
                return false;
            } catch (InvalidArgumentException $e) {
                // Expected exception
            }

            try {
                User::create('user-invalid', 'user@name', 'hash', 'librarian'); // Invalid characters
                $this->error('   Invalid username characters should be rejected');
                return false;
            } catch (InvalidArgumentException $e) {
                // Expected exception
            }

            // Test password hash security (not in logs)
            $user = User::create('user-log', 'username', 'secret_hash', 'librarian');
            $logArray = $user->toLogArray();
            if (isset($logArray['passwordHash']) || isset($logArray['password'])) {
                $this->error('   Password hash should not be in log array');
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing username normalization: " . $e->getMessage());
            return false;
        }
    }
}