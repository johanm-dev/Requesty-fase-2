<?php
/**
 * Proteccion CSRF con tokens criptograficos por sesion para formularios web.
 */
class CsrfService {
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $submitted): bool {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $submitted);
    }

    public static function rotate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }
}
