<?php

namespace App\Services;

use Illuminate\Http\Request;
use RuntimeException;

class JwtService
{
    public static function extractBearerToken(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if ($auth === '') {
            $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', trim((string) $auth), $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private static function secret(): string
    {
        $secret = (string) config('requesty.jwt_secret', '');
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres.');
        }

        return $secret;
    }

    private static function ttlSeconds(): int
    {
        $ttl = (int) config('requesty.jwt_ttl', 900);

        return ($ttl > 0 && $ttl <= 3600) ? $ttl : 900;
    }

    private static function revocationFile(): string
    {
        $dir = storage_path('app/security');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir . '/revoked_jti.json';
    }

    public static function issue(array $user): string
    {
        $now = time();
        $jti = bin2hex(random_bytes(16));
        $header = self::b64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64UrlEncode(json_encode([
            'iss' => 'requesty-api',
            'sub' => (int) $user['id_usuario'],
            'correo' => (string) $user['correo'],
            'nombre' => (string) $user['nombre'],
            'rol' => strtoupper((string) $user['rol']),
            'iat' => $now,
            'exp' => $now + self::ttlSeconds(),
            'jti' => $jti,
        ], JSON_UNESCAPED_UNICODE));

        $sig = hash_hmac('sha256', "{$header}.{$payload}", self::secret(), true);

        return "{$header}.{$payload}." . self::b64UrlEncode($sig);
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode(self::b64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
            return null;
        }

        $expectedSig = self::b64UrlEncode(hash_hmac('sha256', "{$headerB64}.{$payloadB64}", self::secret(), true));
        if (!hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        $claims = json_decode(self::b64UrlDecode($payloadB64), true);
        if (!is_array($claims)) {
            return null;
        }

        $now = time();
        if (($claims['exp'] ?? 0) <= $now || ($claims['iat'] ?? ($now + 1)) > ($now + 5)) {
            return null;
        }
        if (empty($claims['sub']) || empty($claims['rol']) || empty($claims['jti'])) {
            return null;
        }
        if (self::isRevoked((string) $claims['jti'])) {
            return null;
        }

        return $claims;
    }

    public static function revoke(string $jti, int $exp): void
    {
        $file = self::revocationFile();
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($data)) {
                $data = [];
            }
            $now = time();
            foreach ($data as $k => $expTs) {
                if ((int) $expTs <= $now) {
                    unset($data[$k]);
                }
            }
            $data[$jti] = $exp;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function isRevoked(string $jti): bool
    {
        $file = self::revocationFile();
        if (!is_file($file)) {
            return false;
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) && isset($data[$jti]) && (int) $data[$jti] > time();
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
