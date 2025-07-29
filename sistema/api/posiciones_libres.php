<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['equipo_id']) || !is_numeric($_GET['equipo_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de equipo inválido']);
    exit;
}

$equipo_id = (int)$_GET['equipo_id'];
$db = new Database();

try {
    // Obtener información del equipo
    $stmt = $db->query("
        SELECT e.*, 
               CASE 
                   WHEN e.tipo = 'Camión Minero' THEN 6
                   WHEN e.tipo = 'Camioneta' THEN 4
                   WHEN e.tipo = 'Excavadora' THEN 4
                   WHEN e.tipo = 'Cargador Frontal' THEN 4
                   WHEN e.tipo = 'Bulldozer' THEN 4
                   WHEN e.tipo = 'Motoniveladora' THEN 6
                   WHEN e.tipo = 'Compactadora' THEN 4
                   ELSE 6
               END as max_posiciones
        FROM equipos e 
        WHERE e.id = ? AND e.activo = 1
    ", [$equipo_id]);

    $equipo = $stmt->fetch();

    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado o inactivo']);
        exit;
    }

    // Obtener posiciones ocupadas con información detallada
    $stmt = $db->query("
        SELECT i.posicion, n.codigo_interno, n.numero_serie,
               m.nombre as marca_nombre, d.nombre as diseno_nombre,
               med.medida as medida_nombre,
               COALESCE(
                   (SELECT ss.porcentaje_desgaste
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   0
               ) as porcentaje_desgaste,
               COALESCE(
                   (SELECT ss.cocada_actual
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   i.cocada_inicial
               ) as cocada_actual,
               COALESCE(
                   (SELECT SUM(ss.horas_trabajadas)
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as horas_acumuladas,
               DATEDIFF(CURDATE(), i.fecha_instalacion) as dias_instalado,
               (SELECT COUNT(*) FROM alertas a WHERE a.instalacion_id = i.id AND a.estado = 'pendiente') as alertas_pendientes,
               n.costo_nuevo,
               i.fecha_instalacion,
               i.id as instalacion_id
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.equipo_id = ? AND i.activo = 1
        ORDER BY i.posicion
    ", [$equipo_id]);

    $posiciones_ocupadas = $stmt->fetchAll();

    // Crear array de todas las posiciones (1 hasta max_posiciones)
    $todas_posiciones = range(1, $equipo['max_posiciones']);
    $posiciones_ocupadas_nums = array_column($posiciones_ocupadas, 'posicion');
    $posiciones_libres = array_diff($todas_posiciones, $posiciones_ocupadas_nums);

    // Obtener configuración de posiciones según tipo de equipo
    $configuracion_posiciones = obtenerConfiguracionPosiciones($equipo['tipo']);

    // Enriquecer información de posiciones ocupadas
    $posiciones_ocupadas_detalle = [];
    foreach ($posiciones_ocupadas as $pos) {
        $estado_desgaste = 'normal';
        $color_desgaste = 'success';

        if ($pos['porcentaje_desgaste'] >= 70) {
            $estado_desgaste = 'critico';
            $color_desgaste = 'danger';
        } elseif ($pos['porcentaje_desgaste'] >= 30) {
            $estado_desgaste = 'rotacion';
            $color_desgaste = 'warning';
        }

        $costo_hora = $pos['horas_acumuladas'] > 0 ?
            $pos['costo_nuevo'] / $pos['horas_acumuladas'] : 0;

        $posiciones_ocupadas_detalle[] = [
            'posicion' => (int)$pos['posicion'],
            'instalacion_id' => (int)$pos['instalacion_id'],
            'neumatico' => [
                'codigo_interno' => $pos['codigo_interno'],
                'numero_serie' => $pos['numero_serie'],
                'marca' => $pos['marca_nombre'],
                'diseno' => $pos['diseno_nombre'],
                'medida' => $pos['medida_nombre']
            ],
            'estado' => [
                'desgaste' => round($pos['porcentaje_desgaste'], 1),
                'cocada_actual' => round($pos['cocada_actual'], 1),
                'estado_desgaste' => $estado_desgaste,
                'color_desgaste' => $color_desgaste,
                'dias_instalado' => (int)$pos['dias_instalado'],
                'horas_acumuladas' => (int)$pos['horas_acumuladas'],
                'alertas_pendientes' => (int)$pos['alertas_pendientes']
            ],
            'economia' => [
                'costo_nuevo' => (float)$pos['costo_nuevo'],
                'costo_hora' => round($costo_hora, 2),
                'valor_remanente' => round($pos['costo_nuevo'] * (1 - $pos['porcentaje_desgaste'] / 100), 2)
            ],
            'fechas' => [
                'instalacion' => $pos['fecha_instalacion'],
                'instalacion_formateada' => date('d/m/Y', strtotime($pos['fecha_instalacion']))
            ],
            'configuracion' => $configuracion_posiciones[$pos['posicion']] ?? []
        ];
    }

    // Enriquecer información de posiciones libres
    $posiciones_libres_detalle = [];
    foreach ($posiciones_libres as $pos) {
        $posiciones_libres_detalle[] = [
            'posicion' => (int)$pos,
            'configuracion' => $configuracion_posiciones[$pos] ?? [],
            'disponible' => true
        ];
    }

    // Calcular estadísticas del equipo
    $estadisticas = [
        'total_posiciones' => (int)$equipo['max_posiciones'],
        'posiciones_ocupadas' => count($posiciones_ocupadas),
        'posiciones_libres' => count($posiciones_libres),
        'porcentaje_ocupacion' => round((count($posiciones_ocupadas) / $equipo['max_posiciones']) * 100, 1),
        'desgaste_promedio' => count($posiciones_ocupadas) > 0 ?
            round(array_sum(array_column($posiciones_ocupadas, 'porcentaje_desgaste')) / count($posiciones_ocupadas), 1) : 0,
        'valor_total_instalado' => array_sum(array_column($posiciones_ocupadas, 'costo_nuevo')),
        'alertas_totales' => array_sum(array_column($posiciones_ocupadas, 'alertas_pendientes')),
        'neumaticos_criticos' => count(array_filter($posiciones_ocupadas, function ($p) {
            return $p['porcentaje_desgaste'] >= 70;
        })),
        'neumaticos_rotacion' => count(array_filter($posiciones_ocupadas, function ($p) {
            return $p['porcentaje_desgaste'] >= 30 && $p['porcentaje_desgaste'] < 70;
        }))
    ];

    // Obtener recomendaciones de instalación
    $recomendaciones = generarRecomendacionesInstalacion($posiciones_libres, $configuracion_posiciones, $posiciones_ocupadas);

    // Preparar respuesta
    $response = [
        'success' => true,
        'equipo' => [
            'id' => (int)$equipo['id'],
            'codigo' => $equipo['codigo'],
            'nombre' => $equipo['nombre'],
            'tipo' => $equipo['tipo'],
            'modelo' => $equipo['modelo'],
            'max_posiciones' => (int)$equipo['max_posiciones'],
            'horas_mes_promedio' => (int)$equipo['horas_mes_promedio']
        ],
        'posiciones_libres' => array_values($posiciones_libres),
        'posiciones_ocupadas' => $posiciones_ocupadas_detalle,
        'posiciones_libres_detalle' => $posiciones_libres_detalle,
        'estadisticas' => $estadisticas,
        'configuracion_posiciones' => $configuracion_posiciones,
        'recomendaciones' => $recomendaciones
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener posiciones: ' . $e->getMessage()
    ]);
}

/**
 * Obtener configuración de posiciones según tipo de equipo
 */
function obtenerConfiguracionPosiciones($tipo_equipo)
{
    $configuraciones = [
        'Camión Minero' => [
            1 => ['nombre' => 'Delantera Izquierda', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            2 => ['nombre' => 'Delantera Derecha', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            3 => ['nombre' => 'Intermedia Izquierda', 'grupo' => 'intermedia', 'criticidad' => 'media', 'modelo_30' => 2],
            4 => ['nombre' => 'Intermedia Derecha', 'grupo' => 'intermedia', 'criticidad' => 'media', 'modelo_30' => 2],
            5 => ['nombre' => 'Posterior Izquierda', 'grupo' => 'posterior', 'criticidad' => 'alta', 'modelo_30' => 3],
            6 => ['nombre' => 'Posterior Derecha', 'grupo' => 'posterior', 'criticidad' => 'alta', 'modelo_30' => 3]
        ],
        'Camioneta' => [
            1 => ['nombre' => 'Delantera Izquierda', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            2 => ['nombre' => 'Delantera Derecha', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            3 => ['nombre' => 'Posterior Izquierda', 'grupo' => 'posterior', 'criticidad' => 'media', 'modelo_30' => 2],
            4 => ['nombre' => 'Posterior Derecha', 'grupo' => 'posterior', 'criticidad' => 'media', 'modelo_30' => 2]
        ],
        'Excavadora' => [
            1 => ['nombre' => 'Delantera Izquierda', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            2 => ['nombre' => 'Delantera Derecha', 'grupo' => 'delantera', 'criticidad' => 'alta', 'modelo_30' => 1],
            3 => ['nombre' => 'Posterior Izquierda', 'grupo' => 'posterior', 'criticidad' => 'media', 'modelo_30' => 2],
            4 => ['nombre' => 'Posterior Derecha', 'grupo' => 'posterior', 'criticidad' => 'media', 'modelo_30' => 2]
        ]
    ];

    return $configuraciones[$tipo_equipo] ?? $configuraciones['Camión Minero'];
}

/**
 * Generar recomendaciones de instalación
 */
function generarRecomendacionesInstalacion($posiciones_libres, $configuracion, $posiciones_ocupadas)
{
    $recomendaciones = [];

    if (empty($posiciones_libres)) {
        return [
            'mensaje' => 'No hay posiciones libres disponibles',
            'tipo' => 'info',
            'sugerencias' => ['Considere realizar rotaciones para optimizar el desgaste']
        ];
    }

    // Analizar las posiciones libres y dar recomendaciones
    foreach ($posiciones_libres as $pos) {
        $config = $configuracion[$pos] ?? [];
        $sugerencia = [
            'posicion' => $pos,
            'nombre' => $config['nombre'] ?? "Posición $pos",
            'grupo' => $config['grupo'] ?? 'desconocido',
            'criticidad' => $config['criticidad'] ?? 'media',
            'recomendacion' => ''
        ];

        // Lógica de recomendación según criticidad y grupo
        switch ($config['criticidad'] ?? 'media') {
            case 'alta':
                $sugerencia['recomendacion'] = 'Instalar neumático nuevo o con bajo desgaste (<15%)';
                $sugerencia['color'] = 'success';
                $sugerencia['prioridad'] = 1;
                break;
            case 'media':
                $sugerencia['recomendacion'] = 'Apropiado para neumáticos con desgaste moderado (15-30%)';
                $sugerencia['color'] = 'info';
                $sugerencia['prioridad'] = 2;
                break;
            case 'baja':
                $sugerencia['recomendacion'] = 'Posición ideal para neumáticos con mayor desgaste (30-70%)';
                $sugerencia['color'] = 'warning';
                $sugerencia['prioridad'] = 3;
                break;
        }

        $recomendaciones[] = $sugerencia;
    }

    // Ordenar por prioridad
    usort($recomendaciones, function ($a, $b) {
        return $a['prioridad'] - $b['prioridad'];
    });

    return [
        'mensaje' => count($posiciones_libres) . ' posición(es) disponible(s)',
        'tipo' => 'success',
        'posiciones' => $recomendaciones,
        'sugerencia_general' => generarSugerenciaGeneral($posiciones_libres, $posiciones_ocupadas, $configuracion)
    ];
}

/**
 * Generar sugerencia general basada en el estado del equipo
 */
function generarSugerenciaGeneral($libres, $ocupadas, $configuracion)
{
    $total_posiciones = count($libres) + count($ocupadas);
    $porcentaje_ocupacion = (count($ocupadas) / $total_posiciones) * 100;

    if ($porcentaje_ocupacion >= 100) {
        return 'Equipo completamente ocupado. Considere rotaciones para optimizar desgaste.';
    } elseif ($porcentaje_ocupacion >= 75) {
        return 'Equipo casi completo. Priorice posiciones críticas para nuevas instalaciones.';
    } elseif ($porcentaje_ocupacion >= 50) {
        return 'Equipo con ocupación moderada. Balancee instalaciones entre posiciones críticas y normales.';
    } else {
        return 'Equipo con baja ocupación. Priorice completar posiciones críticas primero.';
    }
}

/**
 * Obtener historial de la posición específica
 */
function obtenerHistorialPosicion($equipo_id, $posicion)
{
    global $db;

    $stmt = $db->query("
        SELECT i.fecha_instalacion, i.fecha_instalacion as fecha_retiro,
               n.codigo_interno, m.nombre as marca,
               'activa' as estado, i.cocada_inicial,
               COALESCE(MAX(ss.cocada_actual), i.cocada_inicial) as cocada_final,
               COALESCE(SUM(ss.horas_trabajadas), 0) as horas_trabajadas
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN marcas m ON n.marca_id = m.id
        LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
        WHERE i.equipo_id = ? AND i.posicion = ?
        GROUP BY i.id
        ORDER BY i.fecha_instalacion DESC
        LIMIT 5
    ", [$equipo_id, $posicion]);

    return $stmt->fetchAll();
}

// Endpoint para historial de posición específica
if (isset($_GET['posicion']) && isset($_GET['historial'])) {
    $posicion = (int)$_GET['posicion'];
    $historial = obtenerHistorialPosicion($equipo_id, $posicion);

    echo json_encode([
        'success' => true,
        'posicion' => $posicion,
        'historial' => $historial
    ]);
    exit;
}
