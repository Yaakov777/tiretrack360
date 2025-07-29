<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['instalacion_origen']) || !is_numeric($_GET['instalacion_origen'])) {
    echo json_encode(['success' => false, 'message' => 'ID de instalación origen inválido']);
    exit;
}

$instalacion_origen_id = (int)$_GET['instalacion_origen'];
$db = new Database();

try {
    // Obtener información de la instalación origen
    $stmt = $db->query("
        SELECT i.*, n.codigo_interno, n.medida_id, e.id as equipo_id, e.codigo as equipo_codigo,
               m.nombre as marca_nombre, d.nombre as diseno_nombre, med.medida as medida_nombre,
               COALESCE(
                   (SELECT ss.cocada_actual 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   i.cocada_inicial
               ) as cocada_actual,
               COALESCE(
                   (SELECT MAX(ss.porcentaje_desgaste) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as porcentaje_desgaste
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.id = ? AND i.activo = 1
    ", [$instalacion_origen_id]);

    $instalacion_origen = $stmt->fetch();

    if (!$instalacion_origen) {
        echo json_encode(['success' => false, 'message' => 'Instalación origen no encontrada']);
        exit;
    }

    // Obtener neumáticos candidatos para rotación
    // Criterios de rotación:
    // 1. Mismo equipo (rotación interna prioritaria)
    // 2. Misma medida de neumático
    // 3. Diferente posición
    // 4. Estado activo
    // 5. Considerar modelo 30-30-30 para rotaciones inteligentes

    $neumaticos_rotacion = [];

    // FASE 1: Rotación interna en el mismo equipo (PRIORITARIA)
    $stmt = $db->query("
        SELECT i.id as instalacion_id, i.posicion, i.equipo_id,
               n.id as neumatico_id, n.codigo_interno, n.medida_id,
               e.codigo as equipo_codigo, e.nombre as equipo_nombre,
               m.nombre as marca_nombre, d.nombre as diseno_nombre,
               med.medida as medida_nombre,
               COALESCE(
                   (SELECT ss.cocada_actual 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   i.cocada_inicial
               ) as cocada_actual,
               COALESCE(
                   (SELECT MAX(ss.porcentaje_desgaste) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as porcentaje_desgaste,
               'mismo_equipo' as tipo_rotacion,
               CASE 
                   WHEN ? IN (1,2) AND i.posicion IN (3,4,5,6) THEN 1  -- Delantera a otras
                   WHEN ? IN (3,4) AND i.posicion IN (1,2,5,6) THEN 2  -- Intermedia a otras  
                   WHEN ? IN (5,6) AND i.posicion IN (1,2,3,4) THEN 3  -- Posterior a otras
                   ELSE 4
               END as prioridad_rotacion
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.equipo_id = ? 
        AND i.activo = 1 
        AND i.id != ?
        AND n.medida_id = ?
        ORDER BY prioridad_rotacion ASC, 
                 ABS(i.posicion - ?) ASC,  -- Posiciones cercanas primero
                 porcentaje_desgaste ASC   -- Menos desgastados primero
    ", [
        $instalacion_origen['posicion'],
        $instalacion_origen['posicion'],
        $instalacion_origen['posicion'],
        $instalacion_origen['equipo_id'],
        $instalacion_origen_id,
        $instalacion_origen['medida_id'],
        $instalacion_origen['posicion']
    ]);

    $rotacion_interna = $stmt->fetchAll();

    // FASE 2: Rotación entre equipos (SECUNDARIA)
    $stmt = $db->query("
        SELECT i.id as instalacion_id, i.posicion, i.equipo_id,
               n.id as neumatico_id, n.codigo_interno, n.medida_id,
               e.codigo as equipo_codigo, e.nombre as equipo_nombre,
               m.nombre as marca_nombre, d.nombre as diseno_nombre,
               med.medida as medida_nombre,
               COALESCE(
                   (SELECT ss.cocada_actual 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   i.cocada_inicial
               ) as cocada_actual,
               COALESCE(
                   (SELECT MAX(ss.porcentaje_desgaste) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as porcentaje_desgaste,
               'entre_equipos' as tipo_rotacion,
               5 as prioridad_rotacion  -- Prioridad menor que rotación interna
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id AND e.activo = 1
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.equipo_id != ?
        AND i.activo = 1
        AND n.medida_id = ?
        AND e.tipo = (SELECT tipo FROM equipos WHERE id = ?)  -- Mismo tipo de equipo
        ORDER BY porcentaje_desgaste ASC,
                 e.codigo ASC
        LIMIT 10  -- Limitar para no sobrecargar
    ", [
        $instalacion_origen['equipo_id'],
        $instalacion_origen['medida_id'],
        $instalacion_origen['equipo_id']
    ]);

    $rotacion_externa = $stmt->fetchAll();

    // Combinar ambos tipos de rotación
    $neumaticos_rotacion = array_merge($rotacion_interna, $rotacion_externa);

    // Aplicar lógica del modelo 30-30-30
    foreach ($neumaticos_rotacion as &$neumatico) {
        $neumatico['recomendacion'] = evaluarRecomendacionRotacion(
            $instalacion_origen['posicion'],
            $instalacion_origen['porcentaje_desgaste'],
            $neumatico['posicion'],
            $neumatico['porcentaje_desgaste']
        );

        $neumatico['beneficio_estimado'] = calcularBeneficioRotacion(
            $instalacion_origen['porcentaje_desgaste'],
            $neumatico['porcentaje_desgaste'],
            $neumatico['tipo_rotacion']
        );
    }

    // Ordenar por recomendación y beneficio
    usort($neumaticos_rotacion, function ($a, $b) {
        if ($a['recomendacion']['prioridad'] != $b['recomendacion']['prioridad']) {
            return $a['recomendacion']['prioridad'] - $b['recomendacion']['prioridad'];
        }
        return $b['beneficio_estimado'] - $a['beneficio_estimado'];
    });

    // Preparar respuesta
    $response = [
        'success' => true,
        'instalacion_origen' => [
            'id' => $instalacion_origen['id'],
            'codigo_interno' => $instalacion_origen['codigo_interno'],
            'posicion' => $instalacion_origen['posicion'],
            'equipo_codigo' => $instalacion_origen['equipo_codigo'],
            'cocada_actual' => $instalacion_origen['cocada_actual'],
            'porcentaje_desgaste' => $instalacion_origen['porcentaje_desgaste'],
            'medida' => $instalacion_origen['medida_nombre']
        ],
        'neumaticos' => array_map(function ($n) {
            return [
                'instalacion_id' => $n['instalacion_id'],
                'neumatico_id' => $n['neumatico_id'],
                'codigo_interno' => $n['codigo_interno'],
                'posicion' => $n['posicion'],
                'equipo_codigo' => $n['equipo_codigo'],
                'equipo_nombre' => $n['equipo_nombre'],
                'marca_nombre' => $n['marca_nombre'],
                'diseno_nombre' => $n['diseno_nombre'],
                'medida_nombre' => $n['medida_nombre'],
                'cocada_actual' => round($n['cocada_actual'], 1),
                'porcentaje_desgaste' => round($n['porcentaje_desgaste'], 1),
                'tipo_rotacion' => $n['tipo_rotacion'],
                'recomendacion' => $n['recomendacion'],
                'beneficio_estimado' => $n['beneficio_estimado']
            ];
        }, $neumaticos_rotacion),
        'estadisticas' => [
            'total_candidatos' => count($neumaticos_rotacion),
            'rotacion_interna' => count($rotacion_interna),
            'rotacion_externa' => count($rotacion_externa),
            'recomendaciones_altas' => count(array_filter($neumaticos_rotacion, function ($n) {
                return $n['recomendacion']['prioridad'] <= 2;
            }))
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener neumáticos para rotación: ' . $e->getMessage()
    ]);
}

/**
 * Evaluar recomendación de rotación según modelo 30-30-30
 */
function evaluarRecomendacionRotacion($pos_origen, $desgaste_origen, $pos_destino, $desgaste_destino)
{
    $recomendacion = [
        'tipo' => '',
        'descripcion' => '',
        'prioridad' => 5, // 1=muy alta, 5=muy baja
        'color' => 'secondary'
    ];

    // Lógica del modelo 30-30-30
    // Posiciones: 1-2 (delanteras), 3-4 (intermedias), 5-6 (posteriores)

    $grupo_origen = getGrupoPosicion($pos_origen);
    $grupo_destino = getGrupoPosicion($pos_destino);

    // Caso 1: Neumático con >30% en delanteras/posteriores debe ir a intermedias
    if (($grupo_origen == 'delantera' || $grupo_origen == 'posterior') &&
        $desgaste_origen >= 30 && $grupo_destino == 'intermedia'
    ) {
        $recomendacion = [
            'tipo' => 'Recomendada',
            'descripcion' => 'Rotación según modelo 30-30-30',
            'prioridad' => 1,
            'color' => 'success'
        ];
    }
    // Caso 2: Neumático con >30% en intermedias debe ir a posteriores
    elseif ($grupo_origen == 'intermedia' && $desgaste_origen >= 30 && $grupo_destino == 'posterior') {
        $recomendacion = [
            'tipo' => 'Recomendada',
            'descripcion' => 'Rotación según modelo 30-30-30',
            'prioridad' => 1,
            'color' => 'success'
        ];
    }
    // Caso 3: Intercambio equilibrado (desgastes similares)
    elseif (abs($desgaste_origen - $desgaste_destino) <= 5) {
        $recomendacion = [
            'tipo' => 'Equilibrada',
            'descripcion' => 'Desgastes similares, intercambio neutro',
            'prioridad' => 2,
            'color' => 'info'
        ];
    }
    // Caso 4: Optimización (neumático menos desgastado a posición más exigente)
    elseif (
        $desgaste_destino < $desgaste_origen &&
        ($grupo_destino == 'delantera' || $grupo_destino == 'posterior')
    ) {
        $recomendacion = [
            'tipo' => 'Optimización',
            'descripcion' => 'Neumático menos desgastado a posición crítica',
            'prioridad' => 3,
            'color' => 'warning'
        ];
    }
    // Caso 5: No recomendada
    else {
        $recomendacion = [
            'tipo' => 'No recomendada',
            'descripcion' => 'Rotación no beneficiosa según modelo 30-30-30',
            'prioridad' => 5,
            'color' => 'danger'
        ];
    }

    return $recomendacion;
}

/**
 * Obtener grupo de posición según modelo 30-30-30
 */
function getGrupoPosicion($posicion)
{
    if (in_array($posicion, [1, 2])) return 'delantera';
    if (in_array($posicion, [3, 4])) return 'intermedia';
    if (in_array($posicion, [5, 6])) return 'posterior';
    return 'otro';
}

/**
 * Calcular beneficio estimado de la rotación
 */
function calcularBeneficioRotacion($desgaste_origen, $desgaste_destino, $tipo_rotacion)
{
    $beneficio = 0;

    // Beneficio base por tipo de rotación
    if ($tipo_rotacion == 'mismo_equipo') {
        $beneficio += 10; // Priorizar rotación interna
    } else {
        $beneficio += 5; // Rotación externa
    }

    // Beneficio por diferencia de desgaste
    $diferencia = abs($desgaste_origen - $desgaste_destino);
    if ($diferencia <= 5) {
        $beneficio += 8; // Intercambio equilibrado
    } elseif ($diferencia <= 15) {
        $beneficio += 5; // Diferencia moderada
    } else {
        $beneficio += 2; // Diferencia alta
    }

    // Penalización si ambos están muy desgastados
    if ($desgaste_origen > 70 && $desgaste_destino > 70) {
        $beneficio -= 5;
    }

    // Bonificación si uno está en punto crítico de rotación (30%)
    if ($desgaste_origen >= 28 && $desgaste_origen <= 32) {
        $beneficio += 5;
    }

    return max(0, $beneficio); // No permitir beneficios negativos
}

/**
 * Obtener historial de rotaciones entre dos neumáticos
 */
function obtenerHistorialRotaciones($neumatico1_id, $neumatico2_id)
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*) as total_rotaciones,
               MAX(fecha_movimiento) as ultima_rotacion
        FROM movimientos 
        WHERE tipo_movimiento = 'rotacion'
        AND (
            (neumatico_id = ? AND equipo_destino_id IN (
                SELECT DISTINCT equipo_id FROM instalaciones WHERE neumatico_id = ? AND activo = 1
            )) OR
            (neumatico_id = ? AND equipo_destino_id IN (
                SELECT DISTINCT equipo_id FROM instalaciones WHERE neumatico_id = ? AND activo = 1
            ))
        )
    ", [$neumatico1_id, $neumatico2_id, $neumatico2_id, $neumatico1_id]);

    return $stmt->fetch();
}

/**
 * Validar compatibilidad de neumáticos para rotación
 */
function validarCompatibilidad($neumatico1, $neumatico2)
{
    $validaciones = [
        'medida_compatible' => $neumatico1['medida_id'] == $neumatico2['medida_id'],
        'diferencia_desgaste_aceptable' => abs($neumatico1['porcentaje_desgaste'] - $neumatico2['porcentaje_desgaste']) <= 30,
        'ambos_operativos' => $neumatico1['porcentaje_desgaste'] < 90 && $neumatico2['porcentaje_desgaste'] < 90
    ];

    $validaciones['compatible'] = $validaciones['medida_compatible'] &&
        $validaciones['diferencia_desgaste_aceptable'] &&
        $validaciones['ambos_operativos'];

    return $validaciones;
}

// Endpoint adicional para validación rápida
if (isset($_GET['validar']) && isset($_GET['destino_id'])) {
    try {
        $destino_id = (int)$_GET['destino_id'];

        // Obtener información de ambos neumáticos
        $stmt = $db->query("
            SELECT i.*, n.medida_id,
                   COALESCE(MAX(ss.porcentaje_desgaste), 0) as porcentaje_desgaste
            FROM instalaciones i
            JOIN neumaticos n ON i.neumatico_id = n.id
            LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
            WHERE i.id IN (?, ?)
            GROUP BY i.id
        ", [$instalacion_origen_id, $destino_id]);

        $instalaciones = $stmt->fetchAll();

        if (count($instalaciones) == 2) {
            $validacion = validarCompatibilidad($instalaciones[0], $instalaciones[1]);

            echo json_encode([
                'success' => true,
                'validacion' => $validacion,
                'recomendacion' => evaluarRecomendacionRotacion(
                    $instalaciones[0]['posicion'],
                    $instalaciones[0]['porcentaje_desgaste'],
                    $instalaciones[1]['posicion'],
                    $instalaciones[1]['porcentaje_desgaste']
                )
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontraron las instalaciones']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
