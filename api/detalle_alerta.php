<?php
require_once '../config.php';
Auth::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de alerta inválido']);
    } else {
        echo '<div class="alert alert-danger">ID de alerta inválido</div>';
    }
    exit;
}

$alerta_id = (int)$_GET['id'];
$db = new Database();

try {
    // Obtener información completa de la alerta
    $stmt = $db->query("
        SELECT a.*, 
               n.codigo_interno, n.numero_serie, n.dot, n.costo_nuevo,
               n.garantia_horas, n.vida_util_horas, n.nuevo_usado,
               e.codigo as equipo_codigo, e.nombre as equipo_nombre, e.tipo as equipo_tipo,
               i.posicion,
               m.nombre as marca_nombre, d.nombre as diseno_nombre, med.medida as medida_nombre,
               i.fecha_instalacion, i.cocada_inicial, i.observaciones as observaciones_instalacion,
               DATEDIFF(CURDATE(), a.fecha_alerta) as dias_desde_alerta,
               DATEDIFF(CURDATE(), a.updated_at) as dias_desde_actualizacion,
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
        FROM alertas a
        JOIN instalaciones i ON a.instalacion_id = i.id
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE a.id = ?
    ", [$alerta_id]);
    
    $alerta = $stmt->fetch();
    
    if (!$alerta) {
        throw new Exception('Alerta no encontrada');
    }
    
    // Obtener historial de cambios de estado de la alerta
    $stmt = $db->query("
        SELECT 'created' as tipo_cambio, a.created_at as fecha_cambio, 
               'pendiente' as estado_anterior, 'pendiente' as estado_nuevo,
               'Sistema automático' as usuario_cambio,
               'Alerta generada automáticamente' as motivo
        FROM alertas a 
        WHERE a.id = ?
        UNION ALL
        SELECT 'updated' as tipo_cambio, a.updated_at as fecha_cambio,
               'pendiente' as estado_anterior, a.estado as estado_nuevo,
               'Usuario' as usuario_cambio,
               CASE 
                   WHEN a.estado = 'revisada' THEN 'Alerta marcada como revisada'
                   WHEN a.estado = 'resuelta' THEN 'Alerta resuelta'
                   ELSE 'Estado actualizado'
               END as motivo
        FROM alertas a 
        WHERE a.id = ? AND a.updated_at != a.created_at
        ORDER BY fecha_cambio DESC
    ", [$alerta_id, $alerta_id]);
    $historial_cambios = $stmt->fetchAll();
    
    // Obtener seguimiento relacionado con la fecha de la alerta
    $stmt = $db->query("
        SELECT ss.*, 
               CASE 
                   WHEN ss.fecha_medicion = ? THEN 1
                   WHEN ABS(DATEDIFF(ss.fecha_medicion, ?)) <= 3 THEN 2
                   ELSE 3
               END as relevancia
        FROM seguimiento_semanal ss
        WHERE ss.instalacion_id = ?
        ORDER BY relevancia ASC, ABS(DATEDIFF(ss.fecha_medicion, ?)) ASC
        LIMIT 5
    ", [$alerta['fecha_alerta'], $alerta['fecha_alerta'], $alerta['instalacion_id'], $alerta['fecha_alerta']]);
    $seguimiento_relacionado = $stmt->fetchAll();
    
    // Obtener alertas relacionadas de la misma instalación
    $stmt = $db->query("
        SELECT a.*, 
               CASE 
                   WHEN a.estado = 'pendiente' THEN 1
                   WHEN a.estado = 'revisada' THEN 2
                   ELSE 3
               END as orden_estado
        FROM alertas a
        WHERE a.instalacion_id = ? AND a.id != ?
        ORDER BY orden_estado ASC, a.fecha_alerta DESC
        LIMIT 10
    ", [$alerta['instalacion_id'], $alerta_id]);
    $alertas_relacionadas = $stmt->fetchAll();
    
    // Calcular métricas y análisis
    $analisis = generarAnalisisAlerta($alerta, $seguimiento_relacionado);
    $recomendaciones = generarRecomendaciones($alerta, $analisis);
    
    // Si se solicita JSON, retornar datos estructurados
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'alerta' => $alerta,
            'historial_cambios' => $historial_cambios,
            'seguimiento_relacionado' => $seguimiento_relacionado,
            'alertas_relacionadas' => $alertas_relacionadas,
            'analisis' => $analisis,
            'recomendaciones' => $recomendaciones
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
 * Generar análisis detallado de la alerta
 */
function generarAnalisisAlerta($alerta, $seguimiento) {
    $analisis = [
        'urgencia' => 'normal',
        'impacto' => 'medio',
        'tendencia' => 'estable',
        'riesgo_operacional' => 'bajo',
        'costo_estimado' => 0,
        'tiempo_resolucion_estimado' => '1-2 días',
        'factores_criticos' => [],
        'metricas' => []
    ];
    
    // Evaluar urgencia
    if ($alerta['prioridad'] === 'critica') {
        $analisis['urgencia'] = 'critica';
    } elseif ($alerta['prioridad'] === 'alta' && $alerta['dias_desde_alerta'] > 3) {
        $analisis['urgencia'] = 'alta';
    } elseif ($alerta['dias_desde_alerta'] > 7) {
        $analisis['urgencia'] = 'alta';
    }
    
    // Evaluar impacto según tipo de alerta
    switch ($alerta['tipo_alerta']) {
        case 'rotacion_30':
            $analisis['impacto'] = 'medio';
            $analisis['costo_estimado'] = 200; // Costo estimado de rotación
            $analisis['tiempo_resolucion_estimado'] = '4-6 horas';
            break;
        case 'desgaste_limite':
            $analisis['impacto'] = 'alto';
            $analisis['costo_estimado'] = $alerta['costo_nuevo'] * 0.3; // 30% del costo del neumático
            $analisis['tiempo_resolucion_estimado'] = '1-2 días';
            break;
        case 'mantenimiento':
            $analisis['impacto'] = 'medio';
            $analisis['costo_estimado'] = 500;
            $analisis['tiempo_resolucion_estimado'] = '2-4 horas';
            break;
    }
    
    // Evaluar tendencia basada en seguimiento
    if (count($seguimiento) >= 2) {
        $desgaste_reciente = array_slice($seguimiento, 0, 2);
        if (count($desgaste_reciente) === 2) {
            $diferencia = $desgaste_reciente[0]['porcentaje_desgaste'] - $desgaste_reciente[1]['porcentaje_desgaste'];
            if ($diferencia > 5) {
                $analisis['tendencia'] = 'acelerando';
            } elseif ($diferencia < 1) {
                $analisis['tendencia'] = 'estable';
            } else {
                $analisis['tendencia'] = 'normal';
            }
        }
    }
    
    // Evaluar riesgo operacional
    $factores_riesgo = 0;
    
    if ($alerta['porcentaje_desgaste'] > 70) {
        $factores_riesgo += 3;
        $analisis['factores_criticos'][] = 'Desgaste crítico (>70%)';
    }
    
    if ($alerta['dias_desde_alerta'] > 7) {
        $factores_riesgo += 2;
        $analisis['factores_criticos'][] = 'Alerta pendiente más de 7 días';
    }
    
    if ($alerta['equipo_tipo'] === 'Camión Minero' && in_array($alerta['posicion'], [1, 2, 5, 6])) {
        $factores_riesgo += 1;
        $analisis['factores_criticos'][] = 'Posición crítica en equipo principal';
    }
    
    // Determinar nivel de riesgo
    if ($factores_riesgo >= 4) {
        $analisis['riesgo_operacional'] = 'critico';
    } elseif ($factores_riesgo >= 2) {
        $analisis['riesgo_operacional'] = 'alto';
    } elseif ($factores_riesgo >= 1) {
        $analisis['riesgo_operacional'] = 'medio';
    }
    
    // Calcular métricas adicionales
    $analisis['metricas'] = [
        'eficiencia_horas' => $alerta['horas_acumuladas'] > 0 ? 
            round($alerta['costo_nuevo'] / $alerta['horas_acumuladas'], 2) : 0,
        'vida_util_restante' => round(100 - $alerta['porcentaje_desgaste'], 1),
        'valor_remanente' => round($alerta['costo_nuevo'] * (1 - $alerta['porcentaje_desgaste'] / 100), 2),
        'dias_operacion' => (int)((strtotime($alerta['ultima_medicion']) - strtotime($alerta['fecha_instalacion'])) / (60*60*24))
    ];
    
    return $analisis;
}

/**
 * Generar recomendaciones específicas
 */
function generarRecomendaciones($alerta, $analisis) {
    $recomendaciones = [
        'accion_inmediata' => [],
        'accion_preventiva' => [],
        'seguimiento' => [],
        'prioridad_general' => 'media'
    ];
    
    // Recomendaciones según tipo de alerta
    switch ($alerta['tipo_alerta']) {
        case 'rotacion_30':
            $recomendaciones['accion_inmediata'][] = 'Programar rotación según modelo 30-30-30';
            $recomendaciones['accion_inmediata'][] = 'Identificar posición de destino optimal';
            $recomendaciones['seguimiento'][] = 'Monitorear desgaste post-rotación';
            break;
            
        case 'desgaste_limite':
            $recomendaciones['accion_inmediata'][] = 'Evaluar retiro inmediato del neumático';
            $recomendaciones['accion_inmediata'][] = 'Inspeccionar condición física del neumático';
            $recomendaciones['accion_preventiva'][] = 'Preparar neumático de reemplazo';
            $recomendaciones['prioridad_general'] = 'alta';
            break;
            
        case 'mantenimiento':
            $recomendaciones['accion_inmediata'][] = 'Ejecutar mantenimiento programado';
            $recomendaciones['seguimiento'][] = 'Verificar efectividad del mantenimiento';
            break;
    }
    
    // Recomendaciones según análisis de riesgo
    if ($analisis['riesgo_operacional'] === 'critico') {
        $recomendaciones['accion_inmediata'][] = 'ATENCIÓN INMEDIATA REQUERIDA';
        $recomendaciones['accion_inmediata'][] = 'Considerar parada temporal del equipo';
        $recomendaciones['prioridad_general'] = 'critica';
    }
    
    if ($analisis['tendencia'] === 'acelerando') {
        $recomendaciones['accion_preventiva'][] = 'Investigar causas de desgaste acelerado';
        $recomendaciones['seguimiento'][] = 'Aumentar frecuencia de seguimiento';
    }
    
    // Recomendaciones según tiempo pendiente
    if ($alerta['dias_desde_alerta'] > 7) {
        $recomendaciones['accion_inmediata'][] = 'Escalar a supervisor por tiempo excesivo pendiente';
    }
    
    return $recomendaciones;
}

/**
 * Obtener color según estado
 */
function getEstadoColorAlerta($estado) {
    switch ($estado) {
        case 'pendiente': return 'danger';
        case 'revisada': return 'warning';
        case 'resuelta': return 'success';
        default: return 'secondary';
    }
}

/**
 * Obtener color según prioridad
 */
function getPrioridadColorAlerta($prioridad) {
    switch ($prioridad) {
        case 'critica': return 'danger';
        case 'alta': return 'warning';
        case 'media': return 'info';
        case 'baja': return 'success';
        default: return 'secondary';
    }
}

/**
 * Obtener color según riesgo
 */
function getRiesgoColor($riesgo) {
    switch ($riesgo) {
        case 'critico': return 'danger';
        case 'alto': return 'warning';
        case 'medio': return 'info';
        case 'bajo': return 'success';
        default: return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Alerta</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    .urgencia-critica {
        border-left: 5px solid #dc3545;
    }

    .urgencia-alta {
        border-left: 5px solid #fd7e14;
    }

    .urgencia-normal {
        border-left: 5px solid #0dcaf0;
    }

    .metric-card {
        transition: transform 0.2s ease;
    }

    .metric-card:hover {
        transform: translateY(-2px);
    }

    .timeline-item {
        border-left: 3px solid #dee2e6;
        padding-left: 1rem;
        margin-bottom: 1rem;
        position: relative;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 8px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #6c757d;
    }

    .timeline-item.created::before {
        background: #0dcaf0;
    }

    .timeline-item.updated::before {
        background: #198754;
    }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <!-- Header de la alerta -->
        <div class="bg-<?= getPrioridadColor($alerta['prioridad']) ?> text-white p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">
                        <i class="bi bi-<?= 
                            $alerta['tipo_alerta'] == 'rotacion_30' ? 'arrow-repeat' : 
                            ($alerta['tipo_alerta'] == 'desgaste_limite' ? 'exclamation-triangle' : 'tools')
                        ?>"></i>
                        Alerta #<?= $alerta['id'] ?>
                    </h4>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light text-dark me-2"><?= ucfirst($alerta['prioridad']) ?></span>
                        <span class="me-3"><?= $alerta['equipo_codigo'] ?> - Pos. <?= $alerta['posicion'] ?></span>
                        <small class="opacity-75">
                            <i class="bi bi-calendar"></i> <?= formatDate($alerta['fecha_alerta']) ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="h5 mb-1">
                        <span class="badge bg-<?= getEstadoColorAlerta($alerta['estado']) ?> fs-6">
                            <?= ucfirst($alerta['estado']) ?>
                        </span>
                    </div>
                    <small class="opacity-75"><?= $alerta['dias_desde_alerta'] ?> días pendiente</small>
                </div>
            </div>
        </div>

        <!-- Resumen de análisis -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card h-100 urgencia-<?= $analisis['urgencia'] ?>">
                    <div class="card-body text-center">
                        <div class="h4 text-<?= 
                            $analisis['urgencia'] == 'critica' ? 'danger' : 
                            ($analisis['urgencia'] == 'alta' ? 'warning' : 'info') 
                        ?>">
                            <i class="bi bi-<?= 
                                $analisis['urgencia'] == 'critica' ? 'exclamation-triangle-fill' : 
                                ($analisis['urgencia'] == 'alta' ? 'exclamation-circle' : 'info-circle') 
                            ?>"></i>
                        </div>
                        <h6 class="card-title">Urgencia</h6>
                        <span class="badge bg-<?= 
                            $analisis['urgencia'] == 'critica' ? 'danger' : 
                            ($analisis['urgencia'] == 'alta' ? 'warning' : 'info') 
                        ?>">
                            <?= ucfirst($analisis['urgencia']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h4 text-<?= getRiesgoColor($analisis['riesgo_operacional']) ?>">
                            <i
                                class="bi bi-shield-<?= $analisis['riesgo_operacional'] == 'bajo' ? 'check' : 'exclamation' ?>"></i>
                        </div>
                        <h6 class="card-title">Riesgo Operacional</h6>
                        <span class="badge bg-<?= getRiesgoColor($analisis['riesgo_operacional']) ?>">
                            <?= ucfirst($analisis['riesgo_operacional']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h4 text-warning"><?= formatCurrency($analisis['costo_estimado']) ?></div>
                        <h6 class="card-title">Costo Estimado</h6>
                        <small class="text-muted">Resolución</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="h4 text-info">
                            <i class="bi bi-<?= 
                                $analisis['tendencia'] == 'acelerando' ? 'trending-up' : 
                                ($analisis['tendencia'] == 'estable' ? 'dash' : 'trending-down') 
                            ?>"></i>
                        </div>
                        <h6 class="card-title">Tendencia</h6>
                        <span class="badge bg-<?= 
                            $analisis['tendencia'] == 'acelerando' ? 'danger' : 
                            ($analisis['tendencia'] == 'estable' ? 'success' : 'info') 
                        ?>">
                            <?= ucfirst($analisis['tendencia']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>


        <!-- Historial de cambios -->
        <?php if (!empty($historial_cambios)): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Cambios</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($historial_cambios as $cambio): ?>
                            <div class="timeline-item <?= $cambio['tipo_cambio'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($cambio['motivo']) ?></h6>
                                        <small class="text-muted">
                                            Por: <?= htmlspecialchars($cambio['usuario_cambio']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?= formatDateTime($cambio['fecha_cambio']) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Seguimiento Relacionado</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($seguimiento_relacionado)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Cocada</th>
                                        <th>Desgaste</th>
                                        <th>Horas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seguimiento_relacionado as $seg): ?>
                                    <tr class="<?= $seg['relevancia'] == 1 ? 'table-warning' : '' ?>">
                                        <td>
                                            <?= formatDate($seg['fecha_medicion']) ?>
                                            <?php if ($seg['relevancia'] == 1): ?>
                                            <i class="bi bi-star-fill text-warning" title="Fecha de la alerta"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($seg['cocada_actual'], 1) ?> mm</td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                        $seg['porcentaje_desgaste'] > 70 ? 'danger' : 
                                                        ($seg['porcentaje_desgaste'] > 30 ? 'warning' : 'success') 
                                                    ?>">
                                                <?= number_format($seg['porcentaje_desgaste'], 1) ?>%
                                            </span>
                                        </td>
                                        <td><?= $seg['horas_trabajadas'] ?> hrs</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-muted text-center py-3">
                            No hay seguimiento registrado
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Alertas relacionadas -->
        <?php if (!empty($alertas_relacionadas)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-link-45deg"></i> Otras Alertas de esta Instalación
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alertas_relacionadas as $rel): ?>
                                    <tr>
                                        <td><?= formatDate($rel['fecha_alerta']) ?></td>
                                        <td>
                                            <i class="bi bi-<?= 
                                                    $rel['tipo_alerta'] == 'rotacion_30' ? 'arrow-repeat' : 
                                                    ($rel['tipo_alerta'] == 'desgaste_limite' ? 'exclamation-triangle' : 'tools')
                                                ?>"></i>
                                            <small><?= $TIPOS_ALERTA[$rel['tipo_alerta']] ?? $rel['tipo_alerta'] ?></small>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars(substr($rel['descripcion'], 0, 50)) ?><?= strlen($rel['descripcion']) > 50 ? '...' : '' ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getPrioridadColor($rel['prioridad']) ?>">
                                                <?= ucfirst($rel['prioridad']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getEstadoColorAlerta($rel['estado']) ?>">
                                                <?= ucfirst($rel['estado']) ?>
                                            </span>
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


        <!-- Métricas adicionales -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-calculator"></i> Métricas Adicionales</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="h5 text-primary"><?= $analisis['metricas']['dias_operacion'] ?>
                                        </div>
                                        <small class="text-muted">Días en Operación</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="h5 text-success">
                                            S/<?= number_format($analisis['metricas']['eficiencia_horas'], 2) ?></div>
                                        <small class="text-muted">Costo/Hora</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="h5 text-warning"><?= $analisis['metricas']['vida_util_restante'] ?>%
                                        </div>
                                        <small class="text-muted">Vida Útil Restante</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="h5 text-info">
                                            <?= formatCurrency($analisis['metricas']['valor_remanente']) ?></div>
                                        <small class="text-muted">Valor Remanente</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Acciones recomendadas -->
        <div class="row">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-list-check"></i> Plan de Acción Recomendado
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (!empty($recomendaciones['seguimiento'])): ?>
                            <div class="col-md-12">
                                <h6 class="text-info"><i class="bi bi-eye"></i> Seguimiento Requerido:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($recomendaciones['seguimiento'] as $seguimiento): ?>
                                    <li class="mb-2">
                                        <i class="bi bi-arrow-right-circle text-info"></i>
                                        <?= htmlspecialchars($seguimiento) ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="alert alert-<?= 
                            $recomendaciones['prioridad_general'] == 'critica' ? 'danger' : 
                            ($recomendaciones['prioridad_general'] == 'alta' ? 'warning' : 'info') 
                        ?> mt-3">
                            <h6>
                                <i class="bi bi-info-circle"></i>
                                Prioridad General:
                                <strong><?= ucfirst($recomendaciones['prioridad_general']) ?></strong>
                            </h6>
                            <p class="mb-0">
                                Tiempo estimado de resolución:
                                <strong><?= $analisis['tiempo_resolucion_estimado'] ?></strong><br>
                                Costo estimado: <strong><?= formatCurrency($analisis['costo_estimado']) ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Información de la alerta -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información de la Alerta</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Tipo:</td>
                                <td><strong><?= $TIPOS_ALERTA[$alerta['tipo_alerta']] ?? $alerta['tipo_alerta'] ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Descripción:</td>
                                <td><?= htmlspecialchars($alerta['descripcion']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Generada:</td>
                                <td><?= formatDateTime($alerta['created_at']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Última actualización:</td>
                                <td><?= formatDateTime($alerta['updated_at']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tiempo estimado:</td>
                                <td><span class="badge bg-info"><?= $analisis['tiempo_resolucion_estimado'] ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-gear"></i> Estado del Neumático</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Neumático:</td>
                                <td><strong><?= $alerta['codigo_interno'] ?></strong> (<?= $alerta['marca_nombre'] ?>)
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Desgaste actual:</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?= 
                                            $alerta['porcentaje_desgaste'] > 70 ? 'danger' : 
                                            ($alerta['porcentaje_desgaste'] > 30 ? 'warning' : 'success') 
                                        ?>" style="width: <?= min($alerta['porcentaje_desgaste'], 100) ?>%">
                                            <?= number_format($alerta['porcentaje_desgaste'], 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Cocada actual:</td>
                                <td><?= number_format($alerta['cocada_actual'], 1) ?> mm</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Horas acumuladas:</td>
                                <td><?= number_format($alerta['horas_acumuladas']) ?> hrs</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Valor remanente:</td>
                                <td class="fw-bold text-success">
                                    <?= formatCurrency($analisis['metricas']['valor_remanente']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recomendaciones -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Acción Inmediata
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recomendaciones['accion_inmediata'])): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($recomendaciones['accion_inmediata'] as $accion): ?>
                            <li class="mb-2">
                                <i class="bi bi-arrow-right-circle text-danger"></i>
                                <?= htmlspecialchars($accion) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="text-muted">No hay acciones inmediatas requeridas</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 text-warning">
                            <i class="bi bi-shield-check"></i> Acción Preventiva
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recomendaciones['accion_preventiva'])): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($recomendaciones['accion_preventiva'] as $accion): ?>
                            <li class="mb-2">
                                <i class="bi bi-arrow-right-circle text-warning"></i>
                                <?= htmlspecialchars($accion) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="text-muted">No hay acciones preventivas sugeridas</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Factores críticos -->
        <?php if (!empty($analisis['factores_criticos'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="bi bi-exclamation-triangle"></i> Factores Críticos Identificados
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($analisis['factores_criticos'] as $factor): ?>
                            <div class="col-md-6 mb-2">
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($factor) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Destacar elementos críticos
    $(document).ready(function() {
        // Aplicar efectos visuales según urgencia
        const urgencia = '<?= $analisis['urgencia'] ?>';
        if (urgencia === 'critica') {
            $('.card').addClass('shadow-lg');
            $('body').addClass('bg-light');
        }
    });
    </script>
</body>

</html>