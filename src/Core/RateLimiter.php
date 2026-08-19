<?php

namespace App\Core;

/**
 * RateLimiter — generic, reusable, DB-backed fixed-window throttle.
 *
 * Session-based throttling (the old approach in LoginController) is
 * trivially bypassed by clearing cookies. Storing the counter in the
 * `rate_limits` table means the limit survives across sessions, tabs,
 * and devices for the same key (e.g. same email or same IP).
 *
 * Usage (typical controller):
 *
 *   $key = 'login:' . $email;
 *   if (RateLimiter::tooManyAttempts($key, 5)) {
 *       $wait = RateLimiter::availableIn($key);
 *       // show "try again in {$wait}s"
 *   }
 *   RateLimiter::hit($key, 120); // call after a failed attempt
 *   RateLimiter::clear($key);    // call after a successful attempt
 *
 * Or via RateLimitMiddleware for whole routes (see that class).
 */
final class RateLimiter
{
    /**
     * Whether the key has already exceeded $maxAttempts within its
     * current window. Does NOT record a new attempt.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $row = self::row($key);

        if ($row === null) {
            return false;
        }

        if (self::windowExpired($row)) {
            return false;
        }

        return (int) $row['attempts'] >= $maxAttempts;
    }

    /**
     * Record one attempt against $key. Starts a new $decaySeconds
     * window if none is active or the previous one expired.
     * Returns the attempt count after this hit.
     */
    public static function hit(string $key, int $decaySeconds): int
    {
        $db  = DB::master();
        $row = self::row($key);
        $now = new \DateTimeImmutable();

        if ($row === null || self::windowExpired($row)) {
            $resetAt = $now->modify("+{$decaySeconds} seconds")->format('Y-m-d H:i:s');

            $db->prepare("
                INSERT INTO rate_limits (rl_key, attempts, reset_at)
                VALUES (:key, 1, :reset_at)
                ON DUPLICATE KEY UPDATE attempts = 1, reset_at = :reset_at2
            ")->execute([
                ':key'      => $key,
                ':reset_at' => $resetAt,
                ':reset_at2' => $resetAt,
            ]);

            return 1;
        }

        $db->prepare("
            UPDATE rate_limits SET attempts = attempts + 1 WHERE rl_key = :key
        ")->execute([':key' => $key]);

        return (int) $row['attempts'] + 1;
    }

    /**
     * Seconds remaining until the key's current window resets.
     */
    public static function availableIn(string $key): int
    {
        $row = self::row($key);

        if ($row === null) {
            return 0;
        }

        $diff = (new \DateTimeImmutable($row['reset_at']))->getTimestamp() - time();

        return max(0, $diff);
    }

    public static function attempts(string $key): int
    {
        $row = self::row($key);

        if ($row === null || self::windowExpired($row)) {
            return 0;
        }

        return (int) $row['attempts'];
    }

    /**
     * Reset a key immediately — call after a successful login, a
     * successful reset, etc., so the user isn't punished later for
     * earlier failed attempts.
     */
    public static function clear(string $key): void
    {
        DB::master()
            ->prepare("DELETE FROM rate_limits WHERE rl_key = :key")
            ->execute([':key' => $key]);
    }

    /**
     * Housekeeping: drop expired rows. Cheap enough to call
     * opportunistically (e.g. a small random chance per request);
     * not required for correctness since expired rows are treated
     * as "no limit" anyway.
     */
    public static function sweep(): void
    {
        DB::master()
            ->prepare("DELETE FROM rate_limits WHERE reset_at < :now")
            ->execute([':now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }

    // ── internal ─────────────────────────────────────────────

    private static function row(string $key): ?array
    {
        $stmt = DB::master()->prepare("SELECT attempts, reset_at FROM rate_limits WHERE rl_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private static function windowExpired(array $row): bool
    {
        return (new \DateTimeImmutable($row['reset_at']))->getTimestamp() <= time();
    }
}
