<?php
/**
 * Rate Limiter atomico por IP + bucket para prevenir fuerza bruta y abuso.
 */
class RateLimiter {
    public static function check(string $bucket, int $maxRequests, int $windowSeconds): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $key = hash('sha256', $bucket . '|' . $ip);

        $dir = ROOTPATH . '/storage/ratelimit';
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
            $timestamps = array_values(array_filter($timestamps, fn($ts) => (int)$ts > $cutoff));

            if (count($timestamps) >= $maxRequests) {
                $allowed = false;
                $oldest = (int)($timestamps[0] ?? $now);
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
            header("Retry-After: {$retryAfter}");
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'     => false,
                'code'        => 'RATE_LIMIT_EXCEEDED',
                'message'     => 'Demasiadas solicitudes. Intente nuevamente mas tarde.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
