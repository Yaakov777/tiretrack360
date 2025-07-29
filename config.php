<?php
// config.php - Configuración de base de datos y constantes del sistema

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tire_management_system');

// Configuración del sistema
define('SITE_URL', 'http://192.168.1.41/tiretracker360');
define('ADMIN_EMAIL', 'admin@sistema.com');

// Configuraciones operacionales
define('HORAS_MES_PROMEDIO', 500);
define('DESGASTE_SEMANAL_PROMEDIO', 2); // mm por semana
define('MODELO_30_LIMITE', 30); // Porcentaje para modelo 30-30-30

class Database
{
    private $connection;

    public function __construct()
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }
}

// Clase para manejo de sesiones y autenticación
class Auth
{
    public static function init()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($email, $password)
    {
        $db = new Database();
        $stmt = $db->query(
            "SELECT id, nombre, apellidos, email, rol, password FROM usuarios WHERE email = ? AND activo = 1",
            [$email]
        );

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellidos'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['rol'];

            // Actualizar último acceso
            $db->query(
                "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?",
                [$user['id']]
            );

            return true;
        }

        return false;
    }

    public static function logout()
    {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function hasRole($role)
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }

    public static function canAccess($roles = [])
    {
        if (empty($roles)) return true;
        return in_array($_SESSION['user_role'], $roles);
    }
}

// Funciones de utilidad
function formatCurrency($amount)
{
    return 'S/' . number_format($amount, 2);
}

function formatDate($date)
{
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime)
{
    return date('d/m/Y H:i', strtotime($datetime));
}

function calculateAge($dot)
{
    if (empty($dot) || strlen($dot) < 4) return 'N/A';

    $week = substr($dot, 0, 2);
    $year = substr($dot, -2);
    $year = ($year > 50) ? '19' . $year : '20' . $year;

    $currentYear = date('Y');
    $age = $currentYear - $year;

    return $age . ' años';
}

function getEstadoColor($estado)
{
    switch ($estado) {
        case 'inventario':
            return 'success';
        case 'instalado':
            return 'primary';
        case 'desechado':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getPrioridadColor($prioridad)
{
    switch ($prioridad) {
        case 'critica':
            return 'danger';
        case 'alta':
            return 'warning';
        case 'media':
            return 'info';
        case 'baja':
            return 'success';
        default:
            return 'secondary';
    }
}

// Constantes para el sistema
$POSICIONES = [
    1 => 'Delantera Izquierda',
    2 => 'Delantera Derecha',
    3 => 'Intermedia Izquierda',
    4 => 'Intermedia Derecha',
    5 => 'Posterior Izquierda',
    6 => 'Posterior Derecha'
];

$TIPOS_ALERTA = [
    'rotacion_30' => 'Rotación 30%',
    'desgaste_limite' => 'Desgaste Límite',
    'mantenimiento' => 'Mantenimiento'
];

$ESTADOS_ALERTA = [
    'pendiente' => 'Pendiente',
    'revisada' => 'Revisada',
    'resuelta' => 'Resuelta'
];

$PRIORIDADES = [
    'baja' => 'Baja',
    'media' => 'Media',
    'alta' => 'Alta',
    'critica' => 'Crítica'
];

// Inicializar sesión automáticamente
Auth::init();