<?php
// =====================================================
// API/NEUMATICOS.PHP - Endpoint para gestión de neumáticos
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
            // Obtener lista de neumáticos
            $estado = $_GET['estado'] ?? null;
            $marca_id = $_GET['marca_id'] ?? null;
            $incluir_instalados = $_GET['incluir_instalados'] ?? true;

            $where_conditions = [];
            $params = [];

            if ($estado) {
                $where_conditions[] = "n.estado = ?";
                $params[] = $estado;
            }

            if ($marca_id) {
                $where_conditions[] = "n.marca_id = ?";
                $params[] = $marca_id;
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

            $stmt = $db->query("
                SELECT 
                    n.id,
                    n.codigo_interno,
                    n.numero_serie,
                    n.dot,
                    n.nuevo_usado,
                    n.remanente_nuevo,
                    n.garantia_horas,
                    n.vida_util_horas,
                    n.costo_nuevo,
                    n.estado,
                    n.created_at,
                    ma.nombre as marca,
                    d.nombre as diseno,
                    med.medida,
                    -- Información de instalación actual si aplica
                    e.codigo as equipo_actual,
                    i.posicion as posicion_actual,
                    i.fecha_instalacion,
                    -- Estadísticas de seguimiento
                    COALESCE(MAX(ss.porcentaje_desgaste), 0) as desgaste_actual,
                    COALESCE(MAX(ss.cocada_actual), i.cocada_inicial) as cocada_actual,
                    COALESCE(SUM(ss.horas_trabajadas), 0) as horas_acumuladas,
                    MAX(ss.fecha_medicion) as ultima_medicion,
                    -- Valor remanente calculado
                    CASE 
                        WHEN n.estado = 'instalado' THEN 
                            n.costo_nuevo * (COALESCE(MAX(ss.cocada_actual), i.cocada_inicial) / 100)
                        ELSE n.costo_nuevo * (n.remanente_nuevo / 100)
                    END as valor_remanente_actual,
                    -- Alertas pendientes
                    COUNT(DISTINCT a.id) as alertas_pendientes
                FROM neumaticos n
                JOIN marcas ma ON n.marca_id = ma.id
                JOIN disenos d ON n.diseno_id = d.id
                JOIN medidas med ON n.medida_id = med.id
                LEFT JOIN instalaciones i ON n.id = i.neumatico_id AND i.activo = 1
                LEFT JOIN equipos e ON i.equipo_id = e.id
                LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
                LEFT JOIN alertas a ON i.id = a.instalacion_id AND a.estado IN ('pendiente', 'revisada')
                {$where_clause}
                GROUP BY n.id
                ORDER BY n.codigo_interno
            ", $params);

            $neumaticos = $stmt->fetchAll();

            // Estadísticas generales
            $stmt = $db->query(
                "
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN estado = 'instalado' THEN 1 END) as instalados,
                    COUNT(CASE WHEN estado = 'inventario' THEN 1 END) as en_inventario,
                    COUNT(CASE WHEN estado = 'desechado' THEN 1 END) as desechados,
                    SUM(costo_nuevo) as inversion_total,
                    AVG(costo_nuevo) as costo_promedio
                FROM neumaticos
                WHERE 1=1 " . ($estado ? "AND estado = ?" : ""),
                $estado ? [$estado] : []
            );

            $estadisticas = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'neumaticos' => $neumaticos,
                'estadisticas' => $estadisticas,
                'total' => count($neumaticos),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'GET_DETAIL':
            // Obtener detalle completo de un neumático
            $neumatico_id = $_GET['id'] ?? null;

            if (!$neumatico_id) {
                echo json_encode(['success' => false, 'message' => 'ID de neumático requerido']);
                exit;
            }

            // Información básica del neumático
            $stmt = $db->query("
                SELECT 
                    n.*,
                    ma.nombre as marca,
                    d.nombre as diseno,
                    d.tipo as diseno_tipo,
                    med.medida
                FROM neumaticos n
                JOIN marcas ma ON n.marca_id = ma.id
                JOIN disenos d ON n.diseno_id = d.id
                JOIN medidas med ON n.medida_id = med.id
                WHERE n.id = ?
            ", [$neumatico_id]);

            $neumatico = $stmt->fetch();

            if (!$neumatico) {
                echo json_encode(['success' => false, 'message' => 'Neumático no encontrado']);
                exit;
            }

            // Historial de instalaciones
            $stmt = $db->query("
                SELECT 
                    i.*,
                    e.codigo as equipo_codigo,
                    e.nombre as equipo_nombre
                FROM instalaciones i
                JOIN equipos e ON i.equipo_id = e.id
                WHERE i.neumatico_id = ?
                ORDER BY i.fecha_instalacion DESC
            ", [$neumatico_id]);

            $historial_instalaciones = $stmt->fetchAll();

            // Historial de seguimiento
            $stmt = $db->query("
                SELECT 
                    ss.*,
                    i.posicion,
                    e.codigo as equipo_codigo
                FROM seguimiento_semanal ss
                JOIN instalaciones i ON ss.instalacion_id = i.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE i.neumatico_id = ?
                ORDER BY ss.fecha_medicion DESC
                LIMIT 50
            ", [$neumatico_id]);

            $historial_seguimiento = $stmt->fetchAll();

            // Historial de movimientos
            $stmt = $db->query("
                SELECT 
                    m.*,
                    eo.codigo as equipo_origen_codigo,
                    ed.codigo as equipo_destino_codigo
                FROM movimientos m
                LEFT JOIN equipos eo ON m.equipo_origen_id = eo.id
                LEFT JOIN equipos ed ON m.equipo_destino_id = ed.id
                WHERE m.neumatico_id = ?
                ORDER BY m.fecha_movimiento DESC
            ", [$neumatico_id]);

            $historial_movimientos = $stmt->fetchAll();

            // Alertas relacionadas
            $stmt = $db->query("
                SELECT 
                    a.*,
                    i.posicion,
                    e.codigo as equipo_codigo
                FROM alertas a
                JOIN instalaciones i ON a.instalacion_id = i.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE i.neumatico_id = ?
                ORDER BY a.created_at DESC
                LIMIT 20
            ", [$neumatico_id]);

            $alertas = $stmt->fetchAll();

            // Estadísticas del neumático
            $estadisticas_neumatico = [
                'total_instalaciones' => count($historial_instalaciones),
                'total_mediciones' => count($historial_seguimiento),
                'total_movimientos' => count($historial_movimientos),
                'alertas_pendientes' => count(array_filter($alertas, function ($a) {
                    return in_array($a['estado'], ['pendiente', 'revisada']);
                })),
                'horas_acumuladas' => array_sum(array_column($historial_seguimiento, 'horas_trabajadas')),
                'desgaste_actual' => $historial_seguimiento ? $historial_seguimiento[0]['porcentaje_desgaste'] : 0,
                'vida_util_consumida' => 0
            ];

            if ($neumatico['vida_util_horas'] > 0) {
                $estadisticas_neumatico['vida_util_consumida'] =
                    ($estadisticas_neumatico['horas_acumuladas'] / $neumatico['vida_util_horas']) * 100;
            }

            echo json_encode([
                'success' => true,
                'neumatico' => $neumatico,
                'historial_instalaciones' => $historial_instalaciones,
                'historial_seguimiento' => $historial_seguimiento,
                'historial_movimientos' => $historial_movimientos,
                'alertas' => $alertas,
                'estadisticas' => $estadisticas_neumatico,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'POST':
            // Crear nuevo neumático
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validar código interno único
            $stmt = $db->query("SELECT COUNT(*) FROM neumaticos WHERE codigo_interno = ?", [$data['codigo_interno']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El código interno ya existe']);
                exit;
            }

            $stmt = $db->query("
                INSERT INTO neumaticos (
                    codigo_interno, numero_serie, dot, marca_id, diseno_id, medida_id,
                    nuevo_usado, remanente_nuevo, garantia_horas, vida_util_horas, costo_nuevo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $data['codigo_interno'],
                $data['numero_serie'],
                $data['dot'],
                $data['marca_id'],
                $data['diseno_id'],
                $data['medida_id'],
                $data['nuevo_usado'] ?? 'N',
                $data['remanente_nuevo'] ?? 100.00,
                $data['garantia_horas'] ?? 5000,
                $data['vida_util_horas'] ?? 5000,
                $data['costo_nuevo']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Neumático creado exitosamente',
                'neumatico_id' => $db->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Actualizar neumático
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $neumatico_id = $_GET['id'] ?? null;

            if (!$neumatico_id) {
                echo json_encode(['success' => false, 'message' => 'ID de neumático requerido']);
                exit;
            }

            $stmt = $db->query("
                UPDATE neumaticos 
                SET numero_serie = ?, dot = ?, marca_id = ?, diseno_id = ?, medida_id = ?,
                    nuevo_usado = ?, garantia_horas = ?, vida_util_horas = ?, costo_nuevo = ?
                WHERE id = ?
            ", [
                $data['numero_serie'],
                $data['dot'],
                $data['marca_id'],
                $data['diseno_id'],
                $data['medida_id'],
                $data['nuevo_usado'],
                $data['garantia_horas'],
                $data['vida_util_horas'],
                $data['costo_nuevo'],
                $neumatico_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Neumático actualizado exitosamente'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API neumáticos: ' . $e->getMessage()
    ]);
}
?>
