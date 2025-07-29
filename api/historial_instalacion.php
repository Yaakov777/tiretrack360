<?php
require_once '../config.php';
Auth::requireLogin();

// Verificar parámetros
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de instalación inválido']);
    } else {
        echo '<div class="alert alert-danger">ID de instalación inválido</div>';
    }
    exit;
}

$instalacion_id = (int)$_GET['id'];
$db = new Database();

try {
    // Obtener información detallada de la instalación
    $stmt = $db->query("
        SELECT i.*, n.codigo_interno, n.numero_serie, n.dot, n.costo_nuevo,
               n.garantia_horas, n.vida_util_horas, n.nuevo_usado,
               e.codigo as equipo_codigo, e.nombre as equipo_nombre, e.tipo as equipo_tipo,
               m.nombre as marca_nombre, d.nombre as diseno_nombre, med.medida as medida_nombre,
               DATEDIFF(CURDATE(), i.fecha_instalacion) as dias_instalado,
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
               COALESCE(
                   (SELECT MAX(ss.porcentaje_desgaste) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as porcentaje_desgaste,
               COALESCE(
                   (SELECT ss.fecha_medicion 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id 
                    ORDER BY ss.fecha_medicion DESC 
                    LIMIT 1), 
                   i.fecha_instalacion
               ) as ultima_medicion
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.id = ?
    ", [$instalacion_id]);

    $instalacion = $stmt->fetch();

    if (!$instalacion) {
        throw new Exception('Instalación no encontrada');
    }

    // Obtener historial completo de seguimiento semanal
    $stmt = $db->query("
        SELECT ss.*, 
               WEEK(ss.fecha_medicion) as semana_numero,
               YEAR(ss.fecha_medicion) as ano_medicion
        FROM seguimiento_semanal ss
        WHERE ss.instalacion_id = ?
        ORDER BY ss.fecha_medicion DESC
    ", [$instalacion_id]);
    $seguimiento_historial = $stmt->fetchAll();

    // Obtener movimientos relacionados con este neumático
    $stmt = $db->query("
        SELECT m.*, 
               eq_origen.codigo as equipo_origen_codigo,
               eq_destino.codigo as equipo_destino_codigo,
               CASE 
                   WHEN m.tipo_movimiento = 'instalacion' THEN 'Instalación Inicial'
                   WHEN m.tipo_movimiento = 'rotacion' THEN 'Rotación'
                   WHEN m.tipo_movimiento = 'retiro' THEN 'Retiro'
                   ELSE 'Otro'
               END as tipo_movimiento_desc
        FROM movimientos m
        LEFT JOIN equipos eq_origen ON m.equipo_origen_id = eq_origen.id
        LEFT JOIN equipos eq_destino ON m.equipo_destino_id = eq_destino.id
        WHERE m.neumatico_id = ?
        ORDER BY m.fecha_movimiento DESC, m.created_at DESC
    ", [$instalacion['neumatico_id']]);
    $movimientos = $stmt->fetchAll();

    // Obtener alertas asociadas a esta instalación
    $stmt = $db->query("
        SELECT a.*, 
               CASE 
                   WHEN a.prioridad = 'critica' THEN 'Crítica'
                   WHEN a.prioridad = 'alta' THEN 'Alta'
                   WHEN a.prioridad = 'media' THEN 'Media'
                   WHEN a.prioridad = 'baja' THEN 'Baja'
                   ELSE 'Desconocida'
               END as prioridad_desc,
               CASE 
                   WHEN a.estado = 'pendiente' THEN 'Pendiente'
                   WHEN a.estado = 'revisada' THEN 'Revisada'
                   WHEN a.estado = 'resuelta' THEN 'Resuelta'
                   ELSE 'Desconocido'
               END as estado_desc
        FROM alertas a
        WHERE a.instalacion_id = ?
        ORDER BY a.fecha_alerta DESC, a.created_at DESC
    ", [$instalacion_id]);
    $alertas = $stmt->fetchAll();

    // Calcular estadísticas avanzadas
    $estadisticas = calcularEstadisticasInstalacion($instalacion, $seguimiento_historial);

    // Generar análisis de rendimiento
    $analisis = generarAnalisisRendimiento($instalacion, $seguimiento_historial, $movimientos);

    // Si se solicita JSON, retornar datos estructurados
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'instalacion' => $instalacion,
            'seguimiento' => $seguimiento_historial,
            'movimientos' => $movimientos,
            'alertas' => $alertas,
            'estadisticas' => $estadisticas,
            'analisis' => $analisis
        ]);
        exit;
    }

    // Retornar HTML para mostrar en modal

} catch (Exception $e) {
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    exit;
}

/**
 * Calcular estadísticas avanzadas de la instalación
 */
function calcularEstadisticasInstalacion($instalacion, $seguimiento)
{
    $estadisticas = [
        'tiempo_instalacion' => [
            'dias_total' => $instalacion['dias_instalado'],
            'semanas_total' => ceil($instalacion['dias_instalado'] / 7),
            'meses_total' => round($instalacion['dias_instalado'] / 30.44, 1)
        ],
        'desgaste' => [
            'inicial' => $instalacion['cocada_inicial'],
            'actual' => $instalacion['cocada_actual'],
            'desgaste_total_mm' => $instalacion['cocada_inicial'] - $instalacion['cocada_actual'],
            'porcentaje_desgaste' => $instalacion['porcentaje_desgaste'],
            'vida_util_restante' => 100 - $instalacion['porcentaje_desgaste']
        ],
        'horas' => [
            'acumuladas' => $instalacion['horas_acumuladas'],
            'promedio_semanal' => count($seguimiento) > 0 ?
                $instalacion['horas_acumuladas'] / count($seguimiento) : 0,
            'promedio_diario' => $instalacion['dias_instalado'] > 0 ?
                $instalacion['horas_acumuladas'] / $instalacion['dias_instalado'] : 0
        ],
        'economia' => [
            'costo_inicial' => $instalacion['costo_nuevo'],
            'costo_por_hora' => $instalacion['horas_acumuladas'] > 0 ?
                $instalacion['costo_nuevo'] / $instalacion['horas_acumuladas'] : 0,
            'valor_remanente' => $instalacion['costo_nuevo'] * (1 - $instalacion['porcentaje_desgaste'] / 100),
            'depreciacion' => $instalacion['costo_nuevo'] * ($instalacion['porcentaje_desgaste'] / 100)
        ]
    ];

    // Calcular tendencias si hay suficiente historial
    if (count($seguimiento) >= 3) {
        $ultimas_mediciones = array_slice($seguimiento, 0, 3);
        $desgaste_semanal_promedio = 0;

        for ($i = 0; $i < count($ultimas_mediciones) - 1; $i++) {
            $desgaste_semanal_promedio += $ultimas_mediciones[$i]['desgaste_semanal'];
        }
        $desgaste_semanal_promedio /= (count($ultimas_mediciones) - 1);

        $estadisticas['tendencias'] = [
            'desgaste_semanal_promedio' => $desgaste_semanal_promedio,
            'semanas_restantes_estimadas' => $desgaste_semanal_promedio > 0 ?
                $estadisticas['desgaste']['vida_util_restante'] / ($desgaste_semanal_promedio * 100 / $instalacion['cocada_inicial']) : 0,
            'fecha_rotacion_estimada' => null,
            'fecha_retiro_estimada' => null
        ];

        // Calcular fechas estimadas
        if ($estadisticas['tendencias']['semanas_restantes_estimadas'] > 0) {
            $semanas_hasta_30 = max(0, (30 - $instalacion['porcentaje_desgaste']) / ($desgaste_semanal_promedio * 100 / $instalacion['cocada_inicial']));
            $semanas_hasta_70 = max(0, (70 - $instalacion['porcentaje_desgaste']) / ($desgaste_semanal_promedio * 100 / $instalacion['cocada_inicial']));

            if ($semanas_hasta_30 > 0) {
                $estadisticas['tendencias']['fecha_rotacion_estimada'] = date('Y-m-d', strtotime("+{$semanas_hasta_30} weeks"));
            }
            if ($semanas_hasta_70 > 0) {
                $estadisticas['tendencias']['fecha_retiro_estimada'] = date('Y-m-d', strtotime("+{$semanas_hasta_70} weeks"));
            }
        }
    }

    return $estadisticas;
}

/**
 * Generar análisis de rendimiento
 */
function generarAnalisisRendimiento($instalacion, $seguimiento, $movimientos)
{
    $analisis = [
        'evaluacion_general' => 'normal',
        'puntos_fuertes' => [],
        'areas_mejora' => [],
        'recomendaciones' => [],
        'comparacion_garantia' => [],
        'eficiencia_posicion' => []
    ];

    // Evaluación general basada en múltiples factores
    $score = 0;

    // Factor 1: Desgaste vs tiempo instalado
    $desgaste_esperado = ($instalacion['dias_instalado'] / 30.44) * 3; // 3% por mes estimado
    if ($instalacion['porcentaje_desgaste'] <= $desgaste_esperado) {
        $score += 25;
        $analisis['puntos_fuertes'][] = 'Desgaste dentro de lo esperado para el tiempo instalado';
    } else {
        $analisis['areas_mejora'][] = 'Desgaste superior al esperado para el tiempo instalado';
    }

    // Factor 2: Rendimiento por hora
    if ($instalacion['horas_acumuladas'] > 0) {
        $costo_hora = $instalacion['costo_nuevo'] / $instalacion['horas_acumuladas'];
        if ($costo_hora <= 15) { // Umbral ajustable
            $score += 25;
            $analisis['puntos_fuertes'][] = 'Excelente costo por hora de operación';
        } elseif ($costo_hora <= 25) {
            $score += 15;
            $analisis['puntos_fuertes'][] = 'Buen costo por hora de operación';
        } else {
            $analisis['areas_mejora'][] = 'Costo por hora elevado';
        }
    }

    // Factor 3: Consistencia en seguimiento
    if (count($seguimiento) >= 4) {
        $score += 20;
        $analisis['puntos_fuertes'][] = 'Seguimiento consistente registrado';
    } else {
        $analisis['areas_mejora'][] = 'Falta de seguimiento regular';
        $analisis['recomendaciones'][] = 'Implementar seguimiento semanal constante';
    }

    // Factor 4: Gestión de alertas
    $alertas_pendientes = count(array_filter($alertas ?? [], function ($a) {
        return $a['estado'] == 'pendiente';
    }));

    if ($alertas_pendientes == 0) {
        $score += 15;
        $analisis['puntos_fuertes'][] = 'No hay alertas pendientes';
    } else {
        $analisis['areas_mejora'][] = 'Tiene alertas pendientes de atención';
        $analisis['recomendaciones'][] = 'Atender alertas pendientes prioritariamente';
    }

    // Factor 5: Posición según modelo 30-30-30
    $posicion_grupo = obtenerGrupoPosicion($instalacion['posicion']);
    if ($posicion_grupo == 'intermedia' && $instalacion['porcentaje_desgaste'] < 30) {
        $score += 15;
        $analisis['puntos_fuertes'][] = 'Neumático nuevo en posición intermedia (uso eficiente)';
    } elseif ($posicion_grupo == 'delantera' && $instalacion['porcentaje_desgaste'] >= 30) {
        $analisis['areas_mejora'][] = 'Neumático con >30% en posición delantera';
        $analisis['recomendaciones'][] = 'Considerar rotación según modelo 30-30-30';
    }

    // Determinar evaluación general
    if ($score >= 80) {
        $analisis['evaluacion_general'] = 'excelente';
    } elseif ($score >= 60) {
        $analisis['evaluacion_general'] = 'bueno';
    } elseif ($score >= 40) {
        $analisis['evaluacion_general'] = 'regular';
    } else {
        $analisis['evaluacion_general'] = 'deficiente';
    }

    // Comparación con garantía
    $porcentaje_garantia_usado = $instalacion['horas_acumuladas'] / $instalacion['garantia_horas'] * 100;
    $porcentaje_vida_util_usado = $instalacion['horas_acumuladas'] / $instalacion['vida_util_horas'] * 100;

    $analisis['comparacion_garantia'] = [
        'horas_garantia' => $instalacion['garantia_horas'],
        'horas_vida_util' => $instalacion['vida_util_horas'],
        'porcentaje_garantia_usado' => min(100, $porcentaje_garantia_usado),
        'porcentaje_vida_util_usado' => min(100, $porcentaje_vida_util_usado),
        'estado_garantia' => $porcentaje_garantia_usado > 100 ? 'vencida' : 'vigente'
    ];

    // Eficiencia por posición
    $analisis['eficiencia_posicion'] = [
        'posicion' => $instalacion['posicion'],
        'grupo' => $posicion_grupo,
        'desgaste_esperado_posicion' => calcularDesgasteEsperadoPorPosicion($instalacion['posicion'], $instalacion['dias_instalado']),
        'performance_vs_esperado' => 'normal'
    ];

    // Recomendaciones específicas basadas en datos
    if ($instalacion['porcentaje_desgaste'] >= 30 && $instalacion['porcentaje_desgaste'] < 70) {
        $analisis['recomendaciones'][] = 'Programar rotación según modelo 30-30-30';
    }

    if ($instalacion['porcentaje_desgaste'] >= 70) {
        $analisis['recomendaciones'][] = 'Considerar retiro próximo del neumático';
    }

    if (count($seguimiento) == 0 || (count($seguimiento) > 0 &&
        (time() - strtotime($seguimiento[0]['fecha_medicion'])) > 7 * 24 * 60 * 60)) {
        $analisis['recomendaciones'][] = 'Realizar seguimiento semanal actualizado';
    }

    return $analisis;
}

/**
 * Obtener grupo de posición
 */
function obtenerGrupoPosicion($posicion)
{
    if (in_array($posicion, [1, 2])) return 'delantera';
    if (in_array($posicion, [3, 4])) return 'intermedia';
    if (in_array($posicion, [5, 6])) return 'posterior';
    return 'otro';
}


function obtenerNombrePosicion($posicion)
{
    $nombres = [
        1 => 'Delantera Izquierda',
        2 => 'Delantera Derecha',
        3 => 'Intermedia Izquierda',
        4 => 'Intermedia Derecha',
        5 => 'Posterior Izquierda',
        6 => 'Posterior Derecha'
    ];

    return $nombres[$posicion] ?? "Posición $posicion";
}
/**
 * Calcular desgaste esperado por posición
 */
function calcularDesgasteEsperadoPorPosicion($posicion, $dias_instalado)
{
    $factor_posicion = 1.0;

    // Factores de desgaste por posición
    switch (obtenerGrupoPosicion($posicion)) {
        case 'delantera':
            $factor_posicion = 1.2; // 20% más desgaste
            break;
        case 'posterior':
            $factor_posicion = 1.15; // 15% más desgaste
            break;
        case 'intermedia':
            $factor_posicion = 0.9; // 10% menos desgaste
            break;
    }

    $desgaste_base_diario = 0.1; // 0.1% por día base
    return $dias_instalado * $desgaste_base_diario * $factor_posicion;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Instalación</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    .evaluacion-excelente {
        color: #198754;
    }

    .evaluacion-bueno {
        color: #0dcaf0;
    }

    .evaluacion-regular {
        color: #ffc107;
    }

    .evaluacion-deficiente {
        color: #dc3545;
    }

    .chart-container {
        height: 300px;
    }

    .timeline-item {
        border-left: 3px solid #dee2e6;
        padding-left: 1rem;
        margin-bottom: 1rem;
    }

    .timeline-item.importante {
        border-left-color: #ffc107;
    }

    .timeline-item.critico {
        border-left-color: #dc3545;
    }

    .metric-card {
        transition: transform 0.2s ease;
    }

    .metric-card:hover {
        transform: translateY(-2px);
    }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <!-- Header del historial -->
        <div class="bg-primary text-white p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">
                        <i class="bi bi-clock-history"></i>
                        Historial de Instalación
                    </h4>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light text-dark me-2"><?= $instalacion['codigo_interno'] ?></span>
                        <span class="me-3"><?= $instalacion['equipo_codigo'] ?> - Posición
                            <?= $instalacion['posicion'] ?></span>
                        <small class="opacity-75">
                            <i class="bi bi-calendar"></i> Instalado:
                            <?= formatDate($instalacion['fecha_instalacion']) ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="h5 mb-1"><?= $instalacion['dias_instalado'] ?> días</div>
                    <small class="opacity-75">Tiempo instalado</small>
                </div>
            </div>
        </div>

        <!-- Resumen de estado actual -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h3 text-primary"><?= number_format($instalacion['porcentaje_desgaste'], 1) ?>%</div>
                        <small class="text-muted">Desgaste Actual</small>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-<?= $instalacion['porcentaje_desgaste'] > 70 ? 'danger' : ($instalacion['porcentaje_desgaste'] > 30 ? 'warning' : 'success') ?>"
                                style="width: <?= min($instalacion['porcentaje_desgaste'], 100) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h3 text-success"><?= number_format($instalacion['horas_acumuladas']) ?></div>
                        <small class="text-muted">Horas Acumuladas</small>
                        <div class="mt-2">
                            <small class="text-info">
                                <?= number_format($estadisticas['horas']['promedio_diario'], 1) ?> hrs/día
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h3 text-warning"><?= formatCurrency($estadisticas['economia']['valor_remanente']) ?>
                        </div>
                        <small class="text-muted">Valor Remanente</small>
                        <div class="mt-2">
                            <small class="text-info">
                                S/<?= number_format($estadisticas['economia']['costo_por_hora'], 2) ?>/hr
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h3 evaluacion-<?= $analisis['evaluacion_general'] ?>">
                            <i class="bi bi-<?=
                                            $analisis['evaluacion_general'] == 'excelente' ? 'star-fill' : ($analisis['evaluacion_general'] == 'bueno' ? 'hand-thumbs-up' : ($analisis['evaluacion_general'] == 'regular' ? 'dash-circle' : 'exclamation-triangle'))
                                            ?>"></i>
                        </div>
                        <small class="text-muted">Evaluación</small>
                        <div class="mt-2">
                            <span class="badge bg-<?=
                                                    $analisis['evaluacion_general'] == 'excelente' ? 'success' : ($analisis['evaluacion_general'] == 'bueno' ? 'info' : ($analisis['evaluacion_general'] == 'regular' ? 'warning' : 'danger'))
                                                    ?>">
                                <?= ucfirst($analisis['evaluacion_general']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información detallada del neumático -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información del Neumático</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Código:</td>
                                <td><strong><?= $instalacion['codigo_interno'] ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Serie:</td>
                                <td><?= $instalacion['numero_serie'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">DOT:</td>
                                <td><?= $instalacion['dot'] ?: 'No especificado' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Marca/Diseño:</td>
                                <td><?= $instalacion['marca_nombre'] ?> <?= $instalacion['diseno_nombre'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Medida:</td>
                                <td><span class="badge bg-info"><?= $instalacion['medida_nombre'] ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Condición:</td>
                                <td>
                                    <span
                                        class="badge bg-<?= $instalacion['nuevo_usado'] == 'N' ? 'success' : 'warning' ?>">
                                        <?= $instalacion['nuevo_usado'] == 'N' ? 'Nuevo' : ($instalacion['nuevo_usado'] == 'U' ? 'Usado' : 'Reencauche') ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-truck"></i> Información del Equipo</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Equipo:</td>
                                <td><strong><?= $instalacion['equipo_codigo'] ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nombre:</td>
                                <td><?= $instalacion['equipo_nombre'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipo:</td>
                                <td><?= $instalacion['equipo_tipo'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Posición:</td>
                                <td>
                                    <span class="badge bg-primary"><?= $instalacion['posicion'] ?></span>
                                    <small class="text-muted ms-2">
                                        (<?= obtenerNombrePosicion($instalacion['posicion']) ?>)
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Fecha Instalación:</td>
                                <td><?= formatDate($instalacion['fecha_instalacion']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Cocada Inicial:</td>
                                <td><?= number_format($instalacion['cocada_inicial'], 1) ?> mm</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de evolución del desgaste -->
        <?php if (!empty($seguimiento_historial)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-down"></i> Evolución del Desgaste</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="desgasteChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Análisis y recomendaciones -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Análisis de Rendimiento</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($analisis['puntos_fuertes'])): ?>
                        <h6 class="text-success">Puntos Fuertes:</h6>
                        <ul class="list-unstyled">
                            <?php foreach ($analisis['puntos_fuertes'] as $punto): ?>
                            <li><i class="bi bi-check-circle text-success"></i> <?= $punto ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <?php if (!empty($analisis['areas_mejora'])): ?>
                        <h6 class="text-warning mt-3">Áreas de Mejora:</h6>
                        <ul class="list-unstyled">
                            <?php foreach ($analisis['areas_mejora'] as $area): ?>
                            <li><i class="bi bi-exclamation-triangle text-warning"></i> <?= $area ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-list-check"></i> Recomendaciones</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($analisis['recomendaciones'])): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($analisis['recomendaciones'] as $recomendacion): ?>
                            <li class="mb-2">
                                <i class="bi bi-arrow-right text-primary"></i>
                                <?= $recomendacion ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-check-circle h3"></i>
                            <p>No hay recomendaciones específicas en este momento</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de seguimiento -->
        <?php if (!empty($seguimiento_historial)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Seguimiento Semanal</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Semana</th>
                                        <th>Cocada</th>
                                        <th>Desgaste</th>
                                        <th>% Desgaste</th>
                                        <th>Horas</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seguimiento_historial as $seguimiento): ?>
                                    <tr>
                                        <td><?= formatDate($seguimiento['fecha_medicion']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= $seguimiento['semana_numero'] ?>/<?= $seguimiento['ano_medicion'] ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($seguimiento['cocada_actual'], 1) ?> mm</td>
                                        <td><?= number_format($seguimiento['desgaste_semanal'], 1) ?> mm</td>
                                        <td>
                                            <span
                                                class="badge bg-<?= $seguimiento['porcentaje_desgaste'] > 70 ? 'danger' : ($seguimiento['porcentaje_desgaste'] > 30 ? 'warning' : 'success') ?>">
                                                <?= number_format($seguimiento['porcentaje_desgaste'], 1) ?>%
                                            </span>
                                        </td>
                                        <td><?= $seguimiento['horas_trabajadas'] ?> hrs</td>
                                        <td>
                                            <small class="text-muted">
                                                <?= $seguimiento['observaciones'] ?: '-' ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline de movimientos -->
        <?php if (!empty($movimientos)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-arrow-repeat"></i> Historial de Movimientos</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($movimientos as $movimiento): ?>
                        <div
                            class="timeline-item <?= $movimiento['tipo_movimiento'] == 'retiro' ? 'critico' : ($movimiento['tipo_movimiento'] == 'rotacion' ? 'importante' : '') ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-<?=
                                                                $movimiento['tipo_movimiento'] == 'instalacion' ? 'plus-circle' : ($movimiento['tipo_movimiento'] == 'rotacion' ? 'arrow-repeat' : 'x-circle')
                                                                ?>"></i>
                                        <?= $movimiento['tipo_movimiento_desc'] ?>
                                    </h6>
                                    <div class="small text-muted">
                                        <?php if ($movimiento['equipo_origen_codigo']): ?>
                                        Desde: <?= $movimiento['equipo_origen_codigo'] ?>
                                        <?= $movimiento['posicion_origen'] ? '(Pos. ' . $movimiento['posicion_origen'] . ')' : '' ?>
                                        <?php endif; ?>
                                        <?php if ($movimiento['equipo_destino_codigo']): ?>
                                        → Hacia: <?= $movimiento['equipo_destino_codigo'] ?>
                                        <?= $movimiento['posicion_destino'] ? '(Pos. ' . $movimiento['posicion_destino'] . ')' : '' ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($movimiento['motivo']): ?>
                                    <div class="small text-info mt-1">
                                        <i class="bi bi-chat-text"></i> <?= $movimiento['motivo'] ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted"><?= formatDate($movimiento['fecha_movimiento']) ?>
                                    </div>
                                    <?php if ($movimiento['horometro_movimiento']): ?>
                                    <div class="small text-info">
                                        <?= number_format($movimiento['horometro_movimiento']) ?> hrs</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alertas asociadas -->
        <?php if (!empty($alertas)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Alertas Asociadas</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($alertas as $alerta): ?>
                        <div class="alert alert-<?= getPrioridadColor($alerta['prioridad']) ?> alert-dismissible">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= $alerta['tipo_alerta'] ?></h6>
                                    <p class="mb-1"><?= $alerta['descripcion'] ?></p>
                                    <small class="text-muted">
                                        Generada: <?= formatDate($alerta['fecha_alerta']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?= getPrioridadColor($alerta['prioridad']) ?>">
                                        <?= $alerta['prioridad_desc'] ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= $alerta['estado_desc'] ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Generar gráfico de evolución del desgaste
    <?php if (!empty($seguimiento_historial)): ?>
    const ctx = document.getElementById('desgasteChart');
    if (ctx) {
        const seguimientoData = <?= json_encode(array_reverse($seguimiento_historial)) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: seguimientoData.map(s => new Date(s.fecha_medicion).toLocaleDateString('es-ES')),
                datasets: [{
                    label: 'Porcentaje de Desgaste',
                    data: seguimientoData.map(s => s.porcentaje_desgaste),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Límite Rotación (30%)',
                    data: seguimientoData.map(() => 30),
                    borderColor: 'rgb(255, 205, 86)',
                    borderDash: [5, 5],
                    pointRadius: 0
                }, {
                    label: 'Límite Crítico (70%)',
                    data: seguimientoData.map(() => 70),
                    borderColor: 'rgb(255, 99, 132)',
                    borderDash: [5, 5],
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Evolución del Desgaste en el Tiempo'
                    },
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Porcentaje de Desgaste (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Fecha de Medición'
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    </script>
</body>

</html>