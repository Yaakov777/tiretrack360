<?php
// =====================================================
// API/CONFIGURACION.PHP - Endpoint para configuración del sistema
// =====================================================
?>
<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: application/json');

$db = new Database();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Obtener configuración del sistema
            $clave = $_GET['clave'] ?? null;

            if ($clave) {
                // Obtener configuración específica
                $stmt = $db->query("SELECT * FROM configuracion_sistema WHERE clave = ?", [$clave]);
                $config = $stmt->fetch();

                if ($config) {
                    $config['valor'] = json_decode($config['valor'], true);
                }

                echo json_encode([
                    'success' => true,
                    'configuracion' => $config,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Obtener todas las configuraciones
                $stmt = $db->query("SELECT * FROM configuracion_sistema ORDER BY clave");
                $configuraciones = $stmt->fetchAll();

                $config_array = [];
                foreach ($configuraciones as $config) {
                    $config_array[$config['clave']] = [
                        'valor' => json_decode($config['valor'], true),
                        'descripcion' => $config['descripcion'],
                        'updated_at' => $config['updated_at']
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'configuraciones' => $config_array,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;

        case 'POST':
        case 'PUT':
            // Actualizar configuración
            if (!Auth::canAccess(['admin'])) {
                echo json_encode(['success' => false, 'message' => 'Solo administradores pueden modificar configuración']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $db->query("
                INSERT INTO configuracion_sistema (clave, valor, descripcion, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    valor = VALUES(valor), 
                    descripcion = VALUES(descripcion), 
                    updated_at = NOW()
            ", [
                $data['clave'],
                json_encode($data['valor']),
                $data['descripcion'] ?? null
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API configuración: ' . $e->getMessage()
    ]);
}
?>
