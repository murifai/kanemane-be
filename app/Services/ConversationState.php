<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ConversationState
{
    private const TTL = 600; // 10 minutes

    /**
     * Get conversation state for a user
     */
    public static function get(string $phone): ?array
    {
        return Cache::get(self::key($phone));
    }

    /**
     * Set conversation state for a user
     */
    public static function set(string $phone, string $step, array $data = []): void
    {
        Cache::put(self::key($phone), [
            'step' => $step,
            'data' => $data,
            'expires_at' => now()->addSeconds(self::TTL)->timestamp,
        ], self::TTL);
    }

    /**
     * Update conversation data without changing step
     */
    public static function updateData(string $phone, array $data): void
    {
        $state = self::get($phone);
        if ($state) {
            $state['data'] = array_merge($state['data'], $data);
            Cache::put(self::key($phone), $state, self::TTL);
        }
    }

    /**
     * Clear conversation state for a user
     */
    public static function clear(string $phone): void
    {
        Cache::forget(self::key($phone));
    }

    /**
     * Check if user has active conversation
     */
    public static function hasActive(string $phone): bool
    {
        return Cache::has(self::key($phone));
    }

    /**
     * Get current step
     */
    public static function getStep(string $phone): ?string
    {
        $state = self::get($phone);
        return $state['step'] ?? null;
    }

    /**
     * Get conversation data
     */
    public static function getData(string $phone): array
    {
        $state = self::get($phone);
        return $state['data'] ?? [];
    }

    /**
     * Generate cache key for phone number
     */
    private static function key(string $phone): string
    {
        // Remove @c.us or @lid suffix for consistent keys
        $cleanPhone = str_replace(['@c.us', '@lid'], '', $phone);
        return "conversation:{$cleanPhone}";
    }
}
