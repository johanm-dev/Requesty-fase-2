<?php
/**
 * Servicio Criptografico (AES-256 PII + Blind Index HMAC-SHA256 + Bcrypt Cost >= 12)
 * Homologado con las funciones pgcrypto de PostgreSQL 17 (fn_cifrar_documento, fn_hash_documento, trg_usuario_hash_contrasena).
 */
class CryptoService {
    private static function key(): string {
        return Env::requireNonEmpty('APP_ENCRYPTION_KEY', 32);
    }

    /**
     * Blind Index deterministico HMAC-SHA256 (64 caracteres hexadecimales) para busquedas O(log n).
     */
    public static function hashDocument(string $documento): string {
        $normalized = strtolower(trim($documento));
        if ($normalized === '') {
            throw new DomainValidationException('El documento no puede estar vacio.');
        }
        return hash_hmac('sha256', $normalized, self::key());
    }

    /**
     * Cifrado autenticado AES-256-CBC + HMAC-SHA256 para PII en reposo (documento_cifrado).
     */
    public static function encryptDocument(string $documento): string {
        $clean = trim($documento);
        if ($clean === '') {
            throw new DomainValidationException('El documento a cifrar no puede estar vacio.');
        }
        $rawKey = hash('sha256', self::key(), true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($clean, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Fallo al cifrar documento PII con AES-256.');
        }
        $mac = hash_hmac('sha256', $iv . $cipher, $rawKey, true);
        return base64_encode($iv . $mac . $cipher);
    }

    /**
     * Descifrado autenticado AES-256-CBC para PII.
     */
    public static function decryptDocument(?string $encryptedBase64): ?string {
        if ($encryptedBase64 === null || $encryptedBase64 === '') {
            return null;
        }
        $raw = base64_decode($encryptedBase64, true);
        if ($raw === false || strlen($raw) <= 48) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        $rawKey = hash('sha256', self::key(), true);
        $calcMac = hash_hmac('sha256', $iv . $cipher, $rawKey, true);
        if (!hash_equals($mac, $calcMac)) {
            return null;
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    /**
     * Valida complejidad y genera hash Bcrypt (cost >= 12) homologado con chk_usuario_contrasena_bcrypt.
     */
    public static function hashPassword(string $password, bool $enforcePolicy = true): string {
        if (preg_match('/^\$2[aby]\$(1[2-9]|2[0-9]|3[01])\$[.\/A-Za-z0-9]{53}$/', $password) === 1) {
            return $password;
        }
        if ($enforcePolicy) {
            if (
                strlen($password) < 8 ||
                preg_match('/[A-Z]/', $password) !== 1 ||
                preg_match('/[a-z]/', $password) !== 1 ||
                preg_match('/[0-9]/', $password) !== 1
            ) {
                throw new DomainValidationException(
                    'La contrasena no cumple los requisitos minimos de seguridad (minimo 8 caracteres, mayuscula, minuscula y digito).'
                );
            }
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $storedHash): bool {
        if (str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2a$') || str_starts_with($storedHash, '$2b$')) {
            return password_verify($plain, $storedHash);
        }
        return hash_equals($storedHash, $plain);
    }
}
