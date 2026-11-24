<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class FileRateLimiter
{
    public static function check(Request $request, string $bucket, int $maxRequests, int $windowSeconds): void
    {
        $ip = $request->ip() ?? 'cli';
        $key = hash('sha256', $bucket . '|' . $ip);

        $dir = storage_path('app/ratelimit');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $file = $dir . '/' . $key . '.json';
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return;
        }

        $allowed = true;
        $retryAfter = $windowSeconds;
        $now = time();

        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $timestamps = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($timestamps)) {
                $timestamps = [];
            }

            $cutoff = $now - $windowSeconds;
            $timestamps = array_values(array_filter($timestamps, static fn ($ts) => (int) $ts > $cutoff));

            if (count($timestamps) >= $maxRequests) {
                $allowed = false;
                $oldest = (int) ($timestamps[0] ?? $now);
                $retryAfter = max(1, ($oldest + $windowSeconds) - $now);
            } else {
                $timestamps[] = $now;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($timestamps));
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if (!$allowed) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Demasiadas solicitudes. Intente nuevamente mas tarde.',
                    'retry_after' => $retryAfter,
                ], 429, ['Retry-After' => (string) $retryAfter])
            );
        }
    }
}
