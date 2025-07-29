<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: application/json');

$db = new Database();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Obtener lista de equipos
            $activos_solo = $_GET['activos'] ?? true;
            $where_clause = $activos_solo ? "WHERE activo = 1" : "";

            $stmt = $db->query("
                SELECT 
                    e.id,
                    e.codigo,
                    e.nombre,
                    e.tipo,
                    e.modelo,
                    e.horas_mes_promedio,
                    e.activo,
                    COUNT(DISTINCT i.id) as neumaticos_instalados,
                    COALESCE(AVG(ss.porcentaje_desgaste), 0) as desgaste_promedio,
                    MAX(ss.fecha_medicion) as ultima_medicion
                FROM equipos e
                LEFT JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
                LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
                {$where_clause}
                GROUP BY e.id
                ORDER BY e.codigo
            ");

            $equipos = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'equipos' => $equipos,
                'total' => count($equipos),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'POST':
            // Crear nuevo equipo
            if (!Auth::canAccess(['admin'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $db->query("
                INSERT INTO equipos (codigo, nombre, tipo, modelo, horas_mes_promedio, activo)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $data['codigo'],
                $data['nombre'],
                $data['tipo'],
                $data['modelo'],
                $data['horas_mes_promedio'] ?? 500,
                $data['activo'] ?? 1
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Equipo creado exitosamente',
                'equipo_id' => $db->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Actualizar equipo
            if (!Auth::canAccess(['admin', 'supervisor'])) {
                echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $equipo_id = $_GET['id'] ?? null;

            if (!$equipo_id) {
                echo json_encode(['success' => false, 'message' => 'ID de equipo requerido']);
                exit;
            }

            $stmt = $db->query("
                UPDATE equipos 
                SET nombre = ?, tipo = ?, modelo = ?, horas_mes_promedio = ?, activo = ?
                WHERE id = ?
            ", [
                $data['nombre'],
                $data['tipo'],
                $data['modelo'],
                $data['horas_mes_promedio'],
                $data['activo'],
                $equipo_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Equipo actualizado exitosamente'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en API equipos: ' . $e->getMessage()
    ]);
}
