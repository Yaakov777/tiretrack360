<?php
// =====================================================
// API/MOVIMIENTOS.PHP - Endpoint para movimientos de neumáticos
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
            // Obtener historial de movimientos
            $neumatico_id = $_GET['neumatico_id'] ?? null;
            $equipo_id = $_GET['equipo_id'] ?? null;
            $tipo_movimiento = $_GET['tipo'] ?? null;
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $limit = $_GET['limit'] ?? 100;

            $where_conditions = [];
            $params = [];

            if ($neumatico_id) {
                $where_conditions[] = "m.neumatico_id = ?";
                $params[] = $neumatico_id;
            }

            if ($equipo_id) {
                $where_conditions[] = "(m.equipo_origen_id = ? OR m.equipo_destino_id = ?)";
                $params[] = $equipo_id;
                $params[] = $equipo_id;
            }

            if ($tipo_movimiento) {
                $where_conditions[] = "m.tipo_movimiento = ?";
                $params[] = $tipo_movimiento;
            }

            if ($fecha_desde) {
                $where_conditions[] = "m.fecha_movimiento >= ?";
                $params[] = $fecha_desde;
            }

            if ($fecha_hasta) {
                $where_conditions[] = "m.fecha_movimiento <= ?";
                $params[] = $fecha_hasta;
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

            $stmt = $db->query("
                SELECT 
                    m.*,
                    n.codigo_interno,
                    n.numero_serie,
                    ma.nombre as marca,
                    d.nombre as diseno,
                    eo.codigo as equipo_origen_codigo,
                    eo.nombre as equipo_origen_nombre,
                    ed.codigo as equipo_destino_codigo,
                    ed.nombre as equipo_destino_nombre,
                    CASE 
                        WHEN m.posicion_origen IN (1,2) AND m.posicion_destino IN (3,4) THEN 'Delantera → Intermedia'
                        WHEN m.posicion_origen IN (5,6) AND m.posicion_destino IN (3,4) THEN 'Posterior → Intermedia'
                        WHEN m.posicion_origen IN (3,4) AND m.posicion_destino IN (5,6) THEN 'Intermedia → Posterior'
                        WHEN m.tipo_movimiento = 'instalacion' THEN 'Instalación inicial'
                        WHEN m.tipo_movimiento = 'retiro' THEN 'Retiro/Desecho'
                        ELSE 'Otro movimiento'
                    END as tipo_rotacion,
                    CASE 
                        WHEN m.tipo_movimiento = 'rotacion' AND 
                             ((m.posicion_origen IN (1,2,5,6) AND m.posicion_destino IN (3,4)) OR
                              (m.posicion_origen IN (3,4) AND m.posicion_destino IN (5,6)))
                        THEN 1 ELSE 0
                    END as cumple_modelo_30_30_30
                FROM movimientos m
                JOIN neumaticos n ON m.neumatico_id = n.id
                JOIN marcas ma ON n.marca_id = ma.id
                JOIN disenos d ON n.diseno_id = d.id
                LEFT JOIN equipos eo ON m.equipo_origen_id = eo.id
                LEFT JOIN equipos ed ON m.equipo_destino_id = ed.id
                {$where_clause}
                ORDER BY m.fecha_movimiento DESC, m.created_at DESC
                LIMIT ?
            ", array_merge($params, [$limit]));

            $movimientos = $stmt->fetchAll();

            // Estadísticas de movimientos
            $stmt = $db->query(
                "
                SELECT 
                    COUNT(*) as total_movimientos,
                    COUNT(CASE WHEN tipo_movimiento = 'instalacion' THEN 1 END) as instalaciones,
                    COUNT(CASE WHEN tipo_movimiento = 'rotacion' THEN 1 END) as rotaciones,
                    COUNT(CASE WHEN tipo_movimiento = 'retiro' THEN 1 END) as retiros,
                    COUNT(DISTINCT neumatico_id) as neumaticos_involucrados,
                    COUNT(DISTINCT equipo_destino_id) as equipos_involucrados
                FROM movimientos m
                WHERE 1=1 " . str_replace(
                    "m.neumatico_id",
                    "neumatico_id",
                    str_replace(
                        "m.equipo_origen_id",
                        "equipo_origen_id",
                        str_replace(
                            "m.equipo_destino_id",
                            "equipo_destino_id",
                            str_replace(
                                "m.tipo_movimiento",
                                "tipo_movimiento",
                                str_replace("m.fecha_movimiento", "fecha_movimiento", $where_clause)
                            )
                        )
                    )
                ),
                $params
            );

            $estadisticas = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'movimientos' => $movimientos,
                'estadisticas' => $estadisticas,
                'total' => count($movimientos),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'POST':
            // Registrar nuevo movimiento
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $db->beginTransaction();

            try {
                // Validar que el neumático existe
                $stmt = $db->query("SELECT * FROM neumaticos WHERE id = ?", [$data['neumatico_id']]);
                $neumatico = $stmt->fetch();

                if (!$neumatico) {
                    throw new Exception('Neumático no encontrado');
                }

                // Procesar según tipo de movimiento
                switch ($data['tipo_movimiento']) {
                    case 'instalacion':
                        // Desactivar instalación anterior si existe
                        $stmt = $db->query("
                            UPDATE instalaciones 
                            SET activo = 0 
                            WHERE neumatico_id = ? AND activo = 1
                        ", [$data['neumatico_id']]);

                        // Crear nueva instalación
                        $stmt = $db->query("
                            INSERT INTO instalaciones (
                                neumatico_id, equipo_id, posicion, fecha_instalacion,
                                horometro_instalacion, cocada_inicial, observaciones
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ", [
                            $data['neumatico_id'],
                            $data['equipo_destino_id'],
                            $data['posicion_destino'],
                            $data['fecha_movimiento'],
                            $data['horometro_movimiento'] ?? null,
                            $data['cocada_movimiento'] ?? $neumatico['remanente_nuevo'],
                            $data['observaciones'] ?? null
                        ]);

                        // Actualizar estado del neumático
                        $stmt = $db->query("UPDATE neumaticos SET estado = 'instalado' WHERE id = ?", [$data['neumatico_id']]);
                        break;

                    case 'rotacion':
                        // Buscar instalación activa actual
                        $stmt = $db->query("
                            SELECT * FROM instalaciones 
                            WHERE neumatico_id = ? AND activo = 1
                        ", [$data['neumatico_id']]);
                        $instalacion_actual = $stmt->fetch();

                        if (!$instalacion_actual) {
                            throw new Exception('No se encontró instalación activa para rotación');
                        }

                        // Desactivar instalación actual
                        $stmt = $db->query("
                            UPDATE instalaciones 
                            SET activo = 0 
                            WHERE id = ?
                        ", [$instalacion_actual['id']]);

                        // Crear nueva instalación en nueva posición
                        $stmt = $db->query("
                            INSERT INTO instalaciones (
                                neumatico_id, equipo_id, posicion, fecha_instalacion,
                                horometro_instalacion, cocada_inicial, observaciones
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ", [
                            $data['neumatico_id'],
                            $data['equipo_destino_id'],
                            $data['posicion_destino'],
                            $data['fecha_movimiento'],
                            $data['horometro_movimiento'] ?? null,
                            $data['cocada_movimiento'] ?? $instalacion_actual['cocada_inicial'],
                            $data['observaciones'] ?? 'Rotación según modelo 30-30-30'
                        ]);

                        // Actualizar datos de origen en el movimiento
                        $data['equipo_origen_id'] = $instalacion_actual['equipo_id'];
                        $data['posicion_origen'] = $instalacion_actual['posicion'];
                        break;

                    case 'retiro':
                        // Desactivar instalación actual
                        $stmt = $db->query("
                            UPDATE instalaciones 
                            SET activo = 0 
                            WHERE neumatico_id = ? AND activo = 1
                        ", [$data['neumatico_id']]);

                        // Actualizar estado del neumático
                        $estado_final = $data['estado_final'] ?? 'inventario';
                        $stmt = $db->query("UPDATE neumaticos SET estado = ? WHERE id = ?", [$estado_final, $data['neumatico_id']]);
                        break;
                }

                // Registrar el movimiento
                $stmt = $db->query("
                    INSERT INTO movimientos (
                        neumatico_id, equipo_origen_id, posicion_origen, equipo_destino_id, posicion_destino,
                        fecha_movimiento, horometro_movimiento, tipo_movimiento, motivo, cocada_movimiento, horas_acumuladas
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $data['neumatico_id'],
                    $data['equipo_origen_id'] ?? null,
                    $data['posicion_origen'] ?? null,
                    $data['equipo_destino_id'] ?? null,
                    $data['posicion_destino'] ?? null,
                    $data['fecha_movimiento'],
                    $data['horometro_movimiento'] ?? null,
                    $data['tipo_movimiento'],
                    $data['motivo'] ?? null,
                    $data['cocada_movimiento'] ?? null,
                    $data['horas_acumuladas'] ?? null
                ]);

                $movimiento_id = $db->lastInsertId();

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Movimiento registrado exitosamente',
                    'movimiento_id' => $movimiento_id
                ]);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API movimientos: ' . $e->getMessage()
    ]);
}
?>