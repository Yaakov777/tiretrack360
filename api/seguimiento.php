<?php
// =====================================================
// API/SEGUIMIENTO.PHP - Endpoint para seguimiento semanal
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
            // Obtener mediciones de seguimiento
            $instalacion_id = $_GET['instalacion_id'] ?? null;
            $equipo_id = $_GET['equipo_id'] ?? null;
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $limit = $_GET['limit'] ?? 100;
            
            $where_conditions = [];
            $params = [];
            
            if ($instalacion_id) {
                $where_conditions[] = "ss.instalacion_id = ?";
                $params[] = $instalacion_id;
            }
            
            if ($equipo_id) {
                $where_conditions[] = "e.id = ?";
                $params[] = $equipo_id;
            }
            
            if ($fecha_desde) {
                $where_conditions[] = "ss.fecha_medicion >= ?";
                $params[] = $fecha_desde;
            }
            
            if ($fecha_hasta) {
                $where_conditions[] = "ss.fecha_medicion <= ?";
                $params[] = $fecha_hasta;
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";
            
            $stmt = $db->query("
                SELECT 
                    ss.*,
                    e.codigo as equipo_codigo,
                    e.nombre as equipo_nombre,
                    i.posicion,
                    n.codigo_interno,
                    n.numero_serie,
                    ma.nombre as marca,
                    i.cocada_inicial,
                    (i.cocada_inicial - ss.cocada_actual) as desgaste_acumulado,
                    CASE 
                        WHEN ss.porcentaje_desgaste >= 70 THEN 'Crítico'
                        WHEN ss.porcentaje_desgaste >= 50 THEN 'Alto'
                        WHEN ss.porcentaje_desgaste >= 30 THEN 'Medio'
                        ELSE 'Bajo'
                    END as nivel_riesgo
                FROM seguimiento_semanal ss
                JOIN instalaciones i ON ss.instalacion_id = i.id
                JOIN neumaticos n ON i.neumatico_id = n.id
                JOIN equipos e ON i.equipo_id = e.id
                JOIN marcas ma ON n.marca_id = ma.id
                {$where_clause}
                ORDER BY ss.fecha_medicion DESC, e.codigo, i.posicion
                LIMIT ?
            ", array_merge($params, [$limit]));
            
            $mediciones = $stmt->fetchAll();
            
            // Estadísticas del seguimiento
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total_mediciones,
                    COUNT(DISTINCT ss.instalacion_id) as neumaticos_medidos,
                    COUNT(DISTINCT e.id) as equipos_con_mediciones,
                    AVG(ss.porcentaje_desgaste) as desgaste_promedio,
                  AVG(ss.horas_trabajadas) as horas_promedio_semanal,
                    COUNT(CASE WHEN ss.porcentaje_desgaste >= 70 THEN 1 END) as mediciones_criticas,
                    COUNT(CASE WHEN ss.fecha_medicion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as mediciones_esta_semana
                FROM seguimiento_semanal ss
                JOIN instalaciones i ON ss.instalacion_id = i.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE 1=1 " . str_replace("ss.instalacion_id", "instalacion_id", str_replace("e.id", "i.equipo_id", $where_clause)),
                $params
            );
            
            $estadisticas = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'mediciones' => $mediciones,
                'estadisticas' => $estadisticas,
                'total' => count($mediciones),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'POST':
            // Crear nueva medición de seguimiento
            if (!Auth::canAccess(['admin', 'supervisor', 'operador'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar que la instalación existe y está activa
            $stmt = $db->query("
                SELECT i.*, n.codigo_interno, e.codigo as equipo_codigo
                FROM instalaciones i
                JOIN neumaticos n ON i.neumatico_id = n.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE i.id = ? AND i.activo = 1
            ", [$data['instalacion_id']]);
            
            $instalacion = $stmt->fetch();
            
            if (!$instalacion) {
                echo json_encode(['success' => false, 'message' => 'Instalación no encontrada o inactiva']);
                exit;
            }
            
            // Calcular desgaste semanal y porcentaje
            $cocada_anterior = $instalacion['cocada_inicial'];
            
            // Buscar la medición anterior para calcular desgaste semanal
            $stmt = $db->query("
                SELECT cocada_actual 
                FROM seguimiento_semanal 
                WHERE instalacion_id = ? 
                ORDER BY fecha_medicion DESC 
                LIMIT 1
            ", [$data['instalacion_id']]);
            
            $medicion_anterior = $stmt->fetch();
            if ($medicion_anterior) {
                $cocada_anterior = $medicion_anterior['cocada_actual'];
            }
            
            $desgaste_semanal = $cocada_anterior - $data['cocada_actual'];
            $porcentaje_desgaste = (($instalacion['cocada_inicial'] - $data['cocada_actual']) / $instalacion['cocada_inicial']) * 100;
            
            // Insertar nueva medición
            $stmt = $db->query("
                INSERT INTO seguimiento_semanal (
                    instalacion_id, fecha_medicion, semana, ano, cocada_actual,
                    desgaste_semanal, horas_trabajadas, porcentaje_desgaste, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $data['instalacion_id'],
                $data['fecha_medicion'],
                date('W', strtotime($data['fecha_medicion'])),
                date('Y', strtotime($data['fecha_medicion'])),
                $data['cocada_actual'],
                $desgaste_semanal,
                $data['horas_trabajadas'] ?? 0,
                $porcentaje_desgaste,
                $data['observaciones'] ?? null
            ]);
            
            $seguimiento_id = $db->lastInsertId();
            
            // Verificar si se deben generar alertas automáticas
            $alertas_generadas = [];
            
            // Alerta de rotación 30%
            if ($porcentaje_desgaste >= 30) {
                $stmt = $db->query("
                    SELECT COUNT(*) FROM alertas 
                    WHERE instalacion_id = ? AND tipo_alerta = 'rotacion_30' AND estado IN ('pendiente', 'revisada')
                ", [$data['instalacion_id']]);
                
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $db->query("
                        INSERT INTO alertas (instalacion_id, tipo_alerta, descripcion, fecha_alerta, prioridad)
                        VALUES (?, 'rotacion_30', ?, CURDATE(), 'alta')
                    ", [
                        $data['instalacion_id'],
                        "Neumático {$instalacion['codigo_interno']} en {$instalacion['equipo_codigo']} posición {$instalacion['posicion']} supera 30% de desgaste ({$porcentaje_desgaste}%). Requiere rotación."
                    ]);
                    
                    $alertas_generadas[] = 'rotacion_30';
                }
            }
            
            // Alerta de desgaste límite (70%)
            if ($porcentaje_desgaste >= 70) {
                $stmt = $db->query("
                    SELECT COUNT(*) FROM alertas 
                    WHERE instalacion_id = ? AND tipo_alerta = 'desgaste_limite' AND estado IN ('pendiente', 'revisada')
                ", [$data['instalacion_id']]);
                
                if ($stmt->fetchColumn() == 0) {
                    $prioridad = $porcentaje_desgaste >= 85 ? 'critica' : 'alta';
                    $stmt = $db->query("
                        INSERT INTO alertas (instalacion_id, tipo_alerta, descripcion, fecha_alerta, prioridad)
                        VALUES (?, 'desgaste_limite', ?, CURDATE(), ?)
                    ", [
                        $data['instalacion_id'],
                        "Neumático {$instalacion['codigo_interno']} alcanza {$porcentaje_desgaste}% de desgaste. Evaluar retiro inmediato.",
                        $prioridad
                    ]);
                    
                    $alertas_generadas[] = 'desgaste_limite';
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Medición registrada exitosamente',
                'seguimiento_id' => $seguimiento_id,
                'porcentaje_desgaste' => round($porcentaje_desgaste, 1),
                'desgaste_semanal' => round($desgaste_semanal, 1),
                'alertas_generadas' => $alertas_generadas
            ]);
            break;
            
        case 'PUT':
            // Actualizar medición existente
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $seguimiento_id = $_GET['id'] ?? null;
            
            if (!$seguimiento_id) {
                echo json_encode(['success' => false, 'message' => 'ID de seguimiento requerido']);
                exit;
            }
            
            // Recalcular porcentaje de desgaste
            $stmt = $db->query("
                SELECT i.cocada_inicial 
                FROM seguimiento_semanal ss
                JOIN instalaciones i ON ss.instalacion_id = i.id
                WHERE ss.id = ?
            ", [$seguimiento_id]);
            
            $instalacion = $stmt->fetch();
            $porcentaje_desgaste = (($instalacion['cocada_inicial'] - $data['cocada_actual']) / $instalacion['cocada_inicial']) * 100;
            
            $stmt = $db->query("
                UPDATE seguimiento_semanal 
                SET cocada_actual = ?, horas_trabajadas = ?, porcentaje_desgaste = ?, observaciones = ?
                WHERE id = ?
            ", [
                $data['cocada_actual'],
                $data['horas_trabajadas'],
                $porcentaje_desgaste,
                $data['observaciones'] ?? null,
                $seguimiento_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Medición actualizada exitosamente',
                'porcentaje_desgaste' => round($porcentaje_desgaste, 1)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API seguimiento: ' . $e->getMessage()
    ]);
}
?>