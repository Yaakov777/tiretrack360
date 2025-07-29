<?php
// =====================================================
// API/ALERTAS.PHP - Endpoint para gestión de alertas
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
            // Obtener alertas con filtros
            $estado = $_GET['estado'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $prioridad = $_GET['prioridad'] ?? null;
            $equipo_id = $_GET['equipo_id'] ?? null;
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $limit = $_GET['limit'] ?? 100;

            $where_conditions = [];
            $params = [];

            if ($estado) {
                $where_conditions[] = "a.estado = ?";
                $params[] = $estado;
            }

            if ($tipo) {
                $where_conditions[] = "a.tipo_alerta = ?";
                $params[] = $tipo;
            }

            if ($prioridad) {
                $where_conditions[] = "a.prioridad = ?";
                $params[] = $prioridad;
            }

            if ($equipo_id) {
                $where_conditions[] = "e.id = ?";
                $params[] = $equipo_id;
            }

            if ($fecha_desde) {
                $where_conditions[] = "a.fecha_alerta >= ?";
                $params[] = $fecha_desde;
            }

            if ($fecha_hasta) {
                $where_conditions[] = "a.fecha_alerta <= ?";
                $params[] = $fecha_hasta;
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

            $stmt = $db->query("
                SELECT 
                    a.id,
                    a.tipo_alerta,
                    a.descripcion,
                    a.fecha_alerta,
                    a.prioridad,
                    a.estado,
                    a.created_at,
                    a.updated_at,
                    e.codigo as equipo_codigo,
                    e.nombre as equipo_nombre,
                    i.posicion,
                    n.codigo_interno,
                    n.numero_serie,
                    ma.nombre as marca,
                    COALESCE(MAX(ss.porcentaje_desgaste), 0) as desgaste_actual,
                    DATEDIFF(CURDATE(), a.fecha_alerta) as dias_pendiente
                FROM alertas a
                JOIN instalaciones i ON a.instalacion_id = i.id
                JOIN neumaticos n ON i.neumatico_id = n.id
                JOIN equipos e ON i.equipo_id = e.id
                JOIN marcas ma ON n.marca_id = ma.id
                LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
                {$where_clause}
                GROUP BY a.id
                ORDER BY 
                    FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'),
                    FIELD(a.estado, 'pendiente', 'revisada', 'resuelta'),
                    a.fecha_alerta DESC
                LIMIT ?
            ", array_merge($params, [$limit]));

            $alertas = $stmt->fetchAll();

            // Estadísticas de alertas
            $stmt = $db->query(
                "
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                    COUNT(CASE WHEN estado = 'revisada' THEN 1 END) as revisadas,
                    COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas,
                    COUNT(CASE WHEN prioridad = 'critica' THEN 1 END) as criticas,
                    COUNT(CASE WHEN prioridad = 'alta' THEN 1 END) as altas,
                    COUNT(CASE WHEN fecha_alerta = CURDATE() THEN 1 END) as hoy,
                    COUNT(CASE WHEN fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as esta_semana
                FROM alertas a
                JOIN instalaciones i ON a.instalacion_id = i.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE 1=1 " . str_replace("a.estado", "estado", str_replace("e.id", "i.equipo_id", $where_clause)),
                $params
            );

            $estadisticas = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'alertas' => $alertas,
                'estadisticas' => $estadisticas,
                'total' => count($alertas),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'PUT':
            // Actualizar estado de alerta
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $alerta_id = $_GET['id'] ?? null;

            if (!$alerta_id) {
                echo json_encode(['success' => false, 'message' => 'ID de alerta requerido']);
                exit;
            }

            $campos_permitidos = ['estado', 'observaciones'];
            $set_clauses = [];
            $params = [];

            foreach ($campos_permitidos as $campo) {
                if (isset($data[$campo])) {
                    $set_clauses[] = "{$campo} = ?";
                    $params[] = $data[$campo];
                }
            }

            if (empty($set_clauses)) {
                echo json_encode(['success' => false, 'message' => 'No hay campos para actualizar']);
                exit;
            }

            $params[] = $alerta_id;

            $stmt = $db->query("
                UPDATE alertas 
                SET " . implode(', ', $set_clauses) . ", updated_at = NOW()
                WHERE id = ?
            ", $params);

            // Registrar el cambio en el log
            $stmt = $db->query("
                INSERT INTO log_alertas (alerta_id, usuario_id, accion, estado_anterior, estado_nuevo, observaciones)
                SELECT ?, ?, 'cambio_estado', 
                    (SELECT estado FROM alertas WHERE id = ? LIMIT 1), 
                    ?, ?
            ", [
                $alerta_id,
                $_SESSION['user_id'],
                $alerta_id,
                $data['estado'] ?? null,
                $data['observaciones'] ?? null
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Alerta actualizada exitosamente'
            ]);
            break;

        case 'POST':
            // Crear alerta manual
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $db->query("
                INSERT INTO alertas (instalacion_id, tipo_alerta, descripcion, fecha_alerta, prioridad)
                VALUES (?, ?, ?, ?, ?)
            ", [
                $data['instalacion_id'],
                $data['tipo_alerta'],
                $data['descripcion'],
                $data['fecha_alerta'] ?? date('Y-m-d'),
                $data['prioridad'] ?? 'media'
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Alerta creada exitosamente',
                'alerta_id' => $db->lastInsertId()
            ]);
            break;

        case 'DELETE':
            // Eliminar alerta (solo admins)
            if (!Auth::canAccess(['admin'])) {
                echo json_encode(['success' => false, 'message' => 'Solo administradores pueden eliminar alertas']);
                exit;
            }

            $alerta_id = $_GET['id'] ?? null;

            if (!$alerta_id) {
                echo json_encode(['success' => false, 'message' => 'ID de alerta requerido']);
                exit;
            }

            $stmt = $db->query("DELETE FROM alertas WHERE id = ?", [$alerta_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Alerta eliminada exitosamente'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API alertas: ' . $e->getMessage()
    ]);
}
?>