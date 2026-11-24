<?php
/**
 * Validacion estricta de DTOs y proteccion contra Mass Assignment.
 */
class DtoValidator {
    public static function parseJsonInput(array $allowedFields): array {
        $raw = file_get_contents('php://input');
        $data = null;

        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }
            $data = $decoded;
        } elseif (!empty($_POST)) {
            $data = $_POST;
        } else {
            throw new DomainValidationException('El cuerpo de la solicitud no puede estar vacio.', 400);
        }

        $unknown = array_diff(array_keys($data), $allowedFields);
        if (!empty($unknown)) {
            throw new DomainValidationException('Campos no permitidos en la solicitud (Mass Assignment bloqueado).', 422);
        }

        return $data;
    }

    public static function requireString(array $data, string $field, int $minLen = 1, int $maxLen = 255): string {
        if (!isset($data[$field]) || !is_string($data[$field])) {
            throw new DomainValidationException("Campo obligatorio invalido: {$field}.");
        }
        $val = trim($data[$field]);
        $len = mb_strlen($val, 'UTF-8');
        if ($len < $minLen || $len > $maxLen) {
            throw new DomainValidationException("Longitud invalida para {$field}.");
        }
        return $val;
    }

    public static function optionalString(array $data, string $field, int $maxLen = 1000): ?string {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }
        if (!is_string($data[$field])) {
            throw new DomainValidationException("Tipo invalido para {$field}.");
        }
        $val = trim($data[$field]);
        if (mb_strlen($val, 'UTF-8') > $maxLen) {
            throw new DomainValidationException("Longitud excedida para {$field}.");
        }
        return $val === '' ? null : $val;
    }

    public static function requireEmail(array $data, string $field = 'correo'): string {
        $email = strtolower(self::requireString($data, $field, 5, 150));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainValidationException("Formato de correo invalido.");
        }
        return $email;
    }

    public static function requireInt(array $data, string $field, int $min = 1, int $max = PHP_INT_MAX): int {
        if (!isset($data[$field]) || filter_var($data[$field], FILTER_VALIDATE_INT) === false) {
            throw new DomainValidationException("Entero obligatorio invalido: {$field}.");
        }
        $val = (int)$data[$field];
        if ($val < $min || $val > $max) {
            throw new DomainValidationException("Valor fuera de rango para {$field}.");
        }
        return $val;
    }

    public static function optionalInt(array $data, string $field, int $min = 1, int $max = PHP_INT_MAX): ?int {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }
        return self::requireInt($data, $field, $min, $max);
    }

    public static function requirePositiveAmount(array $data, string $field, bool $allowZero = false): float {
        if (!isset($data[$field]) || !is_numeric($data[$field])) {
            throw new DomainValidationException("Monto numerico invalido: {$field}.");
        }
        $val = round((float)$data[$field], 2);
        if (($allowZero && $val < 0) || (!$allowZero && $val <= 0)) {
            throw new DomainValidationException("El monto de {$field} esta fuera del rango permitido.");
        }
        return $val;
    }

    public static function requireEnum(array $data, string $field, array $allowedValues): string {
        $val = strtoupper(self::requireString($data, $field, 1, 50));
        if (!in_array($val, $allowedValues, true)) {
            throw new DomainValidationException("Valor no permitido para {$field}.");
        }
        return $val;
    }

    public static function validatePasswordStrength(string $password): ?string {
        $len = mb_strlen($password, 'UTF-8');
        if ($len < 8 || $len > 128) {
            return 'La contrasena debe tener entre 8 y 128 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'La contrasena debe incluir al menos una mayuscula, una minuscula y un numero.';
        }
        return null;
    }

    public static function requireStrongPassword(array $data, string $field = 'contrasena'): string {
        if (!isset($data[$field]) || !is_string($data[$field])) {
            throw new DomainValidationException("Campo obligatorio invalido: {$field}.");
        }
        $pass = $data[$field];
        $err = self::validatePasswordStrength($pass);
        if ($err !== null) {
            throw new DomainValidationException($err);
        }
        return $pass;
    }
}
