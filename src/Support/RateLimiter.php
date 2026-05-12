<?php

namespace SeoCopilot\Support;

/**
 * Simple per-minute transient bucket.
 * Returns true if a token is granted, false if the bucket is empty.
 */
class RateLimiter
{
    public function allow(string $bucket, int $per_minute): bool
    {
        if ($per_minute <= 0) {
            return true;
        }
        $key = 'seocp_rl_' . md5($bucket . floor(time() / 60));
        $count = (int) get_transient($key);
        if ($count >= $per_minute) {
            return false;
        }
        set_transient($key, $count + 1, 70);
        return true;
    }
}
