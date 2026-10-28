<?php
/**
 * Sliding-window rate limiting backed by the rate_limits table.
 */

declare(strict_types=1);

function rate_limit_count(string $bucket, string $identifier, int $windowSeconds): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
        [$bucket, $identifier, $windowSeconds]
    );
}

function rate_limit_hit(string $bucket, string $identifier): void
{
    db_query('INSERT INTO rate_limits (bucket, identifier) VALUES (?, ?)', [$bucket, $identifier]);

    // Occasionally purge old rows so the table stays small
    if (random_int(1, 50) === 1) {
        db_query('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 2 DAY)');
    }
}

function rate_limit_clear(string $bucket, string $identifier): void
{
    db_query('DELETE FROM rate_limits WHERE bucket = ? AND identifier = ?', [$bucket, $identifier]);
}
