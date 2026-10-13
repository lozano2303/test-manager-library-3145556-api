<?php

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\Outbound\TokenGeneratorPort;
use Library\Domain\Entities\User;

class JwtTokenGenerator implements TokenGeneratorPort
{
    public function generateToken(User $user): string
    {
        // Basic JWT implementation - 8 hours validity
        $payload = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRole(),
            'exp' => time() + (8 * 60 * 60) // 8 hours
        ];
        
        return base64_encode(json_encode($payload));
    }

    public function validateToken(string $token): ?string
    {
        try {
            $payload = json_decode(base64_decode($token), true);
            if ($payload['exp'] < time()) {
                return null;
            }
            return $payload['user_id'];
        } catch (\Exception $e) {
            return null;
        }
    }
}