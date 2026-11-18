<?php
/**
 * Modelo de Usuario
 */
class Usuario extends Model {
    protected string $table = 'Usuario';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/usuarios_backup.json';
    }

    /**
     * Obtener lista de usuarios desde BD o almacenamiento local de respaldo
     */
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.documento, u.correo, u.telefono, 
                           u.estado, u.id_rol, r.nombre AS rol 
                    FROM Usuario u 
                    LEFT JOIN Rol r ON u.id_rol = r.id_rol 
                    ORDER BY u.id_usuario ASC";
            $users = $this->fetchAll($sql);
            if (!empty($users)) {
                return $users;
            }
        }
        return $this->getFromJson();
    }

    /**
     * Buscar usuario por correo electrónico
     */
    public function findByEmail(string $email): ?array {
        $email = strtolower(trim($email));
        if ($this->isDbConnected()) {
            $sql = "SELECT u.*, r.nombre AS rol_nombre 
                    FROM Usuario u 
                    LEFT JOIN Rol r ON u.id_rol = r.id_rol 
                    WHERE LOWER(u.correo) = :correo LIMIT 1";
            $user = $this->fetchOne($sql, [':correo' => $email]);
            if ($user) {
                $user['rol'] = $user['rol_nombre'] ?? 'Administrador';
                return $user;
            }
        }

        // Respaldo JSON o por defecto
        $users = $this->getFromJson();
        foreach ($users as $u) {
            if (strtolower($u['correo']) === $email) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Autenticar usuario con correo y contraseña
     */
    public function authenticate(string $email, string $password): ?array {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        // Verificar contraseña (soporta tanto texto plano como hash para máxima compatibilidad)
        $passwordMatches = false;
        if (isset($user['contrasena'])) {
            if ($user['contrasena'] === $password || password_verify($password, $user['contrasena'])) {
                $passwordMatches = true;
            }
        }

        if ($passwordMatches) {
            if (isset($user['estado']) && strtolower($user['estado']) === 'inactivo') {
                return ['error' => 'inactive'];
            }
            return $user;
        }

        return null;
    }

    /**
     * Registrar un nuevo usuario
     */
    public function create(array $data): bool {
        $nombre = trim($data['nombre'] ?? '');
        $correo = strtolower(trim($data['correo'] ?? ''));
        $telefono = trim($data['telefono'] ?? '');
        $rol = trim($data['rol'] ?? 'Cliente');
        $contrasena = $data['contrasena'] ?? '';
        $documento = trim($data['documento'] ?? (string)time());

        if (empty($nombre) || empty($correo) || empty($contrasena)) {
            return false;
        }

        if ($this->isDbConnected()) {
            // Obtener id_rol
            $rolRow = $this->fetchOne("SELECT id_rol FROM Rol WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1", [':nombre' => $rol]);
            $idRol = $rolRow ? (int)$rolRow['id_rol'] : 1;

            $sql = "INSERT INTO Usuario (nombre, apellido, documento, correo, telefono, contrasena, estado, id_rol) 
                    VALUES (:nombre, '', :documento, :correo, :telefono, :contrasena, 'Activo', :id_rol)";
            $success = $this->execute($sql, [
                ':nombre'     => $nombre,
                ':documento'  => $documento,
                ':correo'     => $correo,
                ':telefono'   => $telefono,
                ':contrasena' => $contrasena,
                ':id_rol'     => $idRol
            ]);
            if ($success) return true;
        }

        // Guardar en respaldo JSON
        $users = $this->getFromJson();
        $users[] = [
            'id_usuario' => count($users) + 1,
            'nombre'     => $nombre,
            'correo'     => $correo,
            'telefono'   => $telefono,
            'rol'        => $rol,
            'contrasena' => $contrasena,
            'activo'     => true,
            'estado'     => 'Activo'
        ];
        return $this->saveToJson($users);
    }

    /**
     * Obtener usuarios desde el archivo JSON de respaldo
     */
    private function getFromJson(): array {
        if (file_exists($this->jsonStorageFile)) {
            $data = file_get_contents($this->jsonStorageFile);
            $decoded = json_decode($data, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Usuarios semilla iniciales
        $defaultUsers = [
            [
                'id_usuario' => 1,
                'nombre'     => 'Juan Pérez',
                'correo'     => 'admin@requesty.com',
                'telefono'   => '3101234567',
                'rol'        => 'Administrador',
                'contrasena' => 'admin123',
                'activo'     => true,
                'estado'     => 'Activo',
                'permisos'   => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes']
            ],
            [
                'id_usuario' => 2,
                'nombre'     => 'Carlos Cajero',
                'correo'     => 'cajero@requesty.com',
                'telefono'   => '3119876543',
                'rol'        => 'Cajero',
                'contrasena' => 'caja123',
                'activo'     => true,
                'estado'     => 'Activo',
                'permisos'   => ['menu', 'mesas', 'pedidos', 'caja']
            ],
            [
                'id_usuario' => 3,
                'nombre'     => 'Mateo Mesero',
                'correo'     => 'mesero@requesty.com',
                'telefono'   => '3124567890',
                'rol'        => 'Mesero',
                'contrasena' => 'mesero123',
                'activo'     => true,
                'estado'     => 'Activo',
                'permisos'   => ['menu', 'mesas', 'pedidos']
            ],
            [
                'id_usuario' => 4,
                'nombre'     => 'Claudia Cocina',
                'correo'     => 'cocina@requesty.com',
                'telefono'   => '3131122334',
                'rol'        => 'Cocina',
                'contrasena' => 'cocina123',
                'activo'     => true,
                'estado'     => 'Activo',
                'permisos'   => ['menu', 'pedidos', 'cocina']
            ],
            [
                'id_usuario' => 5,
                'nombre'     => 'Cliente Demo',
                'correo'     => 'cliente@gmail.com',
                'telefono'   => '3145566778',
                'rol'        => 'Cliente',
                'contrasena' => 'cliente123',
                'activo'     => true,
                'estado'     => 'Activo',
                'permisos'   => ['menu', 'pqrs']
            ]
        ];
        $this->saveToJson($defaultUsers);
        return $defaultUsers;
    }

    /**
     * Guardar en archivo JSON de respaldo
     */
    public function saveToJson(array $users): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
