<?php

namespace Diffyne\Security;

use Illuminate\Support\Facades\Config;

/**
 * Signs and verifies component state using HMAC to prevent tampering.
 */
class StateSigner
{
    /**
     * Generate HMAC signature for state.
     */
    public static function sign(array $state, string $componentId): string
    {
        $key = self::getSigningKey();
        $normalizedState = self::normalizeState($state);
        $payload = json_encode($normalizedState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $componentId;
        
        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Verify state signature.
     */
    public static function verify(array $state, string $componentId, string $signature): bool
    {
        $expectedSignature = self::sign($state, $componentId);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Normalize state for consistent signature generation.
     * Handles differences between client/server serialization.
     */
    protected static function normalizeState(array $state): array
    {
        // Sort keys alphabetically for consistent ordering
        ksort($state);
        
        // Recursively normalize values
        foreach ($state as $key => $value) {
            if (is_array($value)) {
                // Recursively normalize nested arrays
                $state[$key] = self::normalizeState($value);
            }
            // Note: We keep null as null for now, as the real fix is to match
            // the initial component state types
        }
        
        return $state;
    }

    /**
     * Get the signing key from config.
     */
    protected static function getSigningKey(): string
    {
        $key = Config::get('diffyne.security.signing_key') ?: Config::get('app.key');
        
        if (! $key) {
            throw new \RuntimeException('No signing key configured. Set DIFFYNE_SIGNING_KEY or APP_KEY.');
        }

        // Remove "base64:" prefix if present
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }
}
