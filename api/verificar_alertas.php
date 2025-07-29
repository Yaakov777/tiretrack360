<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: application/json');

// Solo administradores y supervisores pueden ejecutar verificación manual
if (!Auth::canAccess(['admin', 'supervisor'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

/**
 * Obtener grupo de posición según modelo 30-30-30
 */
function obtenerGrupoPosicion($posicion)
{
    if (in_array($posicion, [1, 2])) return 'delantera';
    if (in_array($posicion, [3, 4])) return 'intermedia';
    if (in_array($posicion, [5, 6])) return 'posterior';
    return 'otro';
}

/**
 * Verificar condiciones específicas por equipo
 */
function verificarCondicionesEquipo($equipo_tipo, $posicion, $desgaste)
{
    $condiciones = [
        'requiere_atencion' => false,
        'nivel_criticidad' => 'normal',
        'factor_posicion' => 1.0
    ];

    // Factores específicos por tipo de equipo
    switch ($equipo_tipo) {
        case 'Camión Minero':
            // Posiciones críticas en camiones mineros
            if (in_array($posicion, [1, 2, 5, 6])) {
                $condiciones['factor_posicion'] = 1.2;
                if ($desgaste >= 25) {
                    $condiciones['requiere_atencion'] = true;
                    $condiciones['nivel_criticidad'] = 'alta';
                }
            }
            break;

        case 'Camioneta':
            // Todas las posiciones son importantes en camionetas
            $condiciones['factor_posicion'] = 1.1;
            if ($desgaste >= 30) {
                $condiciones['requiere_atencion'] = true;
                $condiciones['nivel_criticidad'] = 'media';
            }
            break;

        case 'Excavadora':
            // Posiciones delanteras más críticas
            if (in_array($posicion, [1, 2])) {
                $condiciones['factor_posicion'] = 1.3;
                if ($desgaste >= 20) {
                    $condiciones['requiere_atencion'] = true;
                    $condiciones['nivel_criticidad'] = 'alta';
                }
            }
            break;
    }

    return $condiciones;
}

/**
 * Calcular prioridad dinámica basada en múltiples factores
 */
function calcularPrioridadDinamica($desgaste, $dias_sin_seguimiento, $posicion, $equipo_tipo, $horas_garantia = null, $horas_acumuladas = 0)
{
    $score = 0;

    // Factor desgaste (peso: 40%)
    if ($desgaste >= 90) $score += 40;
    elseif ($desgaste >= 70) $score += 30;
    elseif ($desgaste >= 50) $score += 20;
    elseif ($desgaste >= 30) $score += 10;

    // Factor tiempo sin seguimiento (peso: 25%)
    if ($dias_sin_seguimiento >= 30) $score += 25;
    elseif ($dias_sin_seguimiento >= 21) $score += 20;
    elseif ($dias_sin_seguimiento >= 14) $score += 15;
    elseif ($dias_sin_seguimiento >= 7) $score += 10;

    // Factor posición crítica (peso: 20%)
    $condiciones = verificarCondicionesEquipo($equipo_tipo, $posicion, $desgaste);
    $score += ($condiciones['factor_posicion'] - 1) * 20;

    // Factor garantía (peso: 15%)
    if ($horas_garantia && $horas_acumuladas > 0) {
        $porcentaje_garantia = ($horas_acumuladas / $horas_garantia) * 100;
        if ($porcentaje_garantia >= 100) $score += 15;
        elseif ($porcentaje_garantia >= 95) $score += 12;
        elseif ($porcentaje_garantia >= 90) $score += 8;
        elseif ($porcentaje_garantia >= 85) $score += 5;
    }

    // Determinar prioridad final
    if ($score >= 70) return 'critica';
    if ($score >= 50) return 'alta';
    if ($score >= 30) return 'media';
    return 'baja';
}

/**
 * Generar descripción inteligente de alerta
 */
function generarDescripcionInteligente($tipo_alerta, $datos)
{
    $descripcion = '';

    switch ($tipo_alerta) {
        case 'rotacion_30':
            $grupo = obtenerGrupoPosicion($datos['posicion']);
            $descripcion = "Neumático {$datos['codigo_interno']} en posición {$grupo} {$datos['posicion']} ";
            $descripcion .= "alcanza {$datos['desgaste']}% de desgaste. ";

            if ($grupo == 'delantera') {
                $descripcion .= "Según modelo 30-30-30, debe rotar a posición intermedia (3-4).";
            } elseif ($grupo == 'posterior') {
                $descripcion .= "Según modelo 30-30-30, debe rotar a posición intermedia (3-4).";
            } elseif ($grupo == 'intermedia') {
                $descripcion .= "Según modelo 30-30-30, debe rotar a posición posterior (5-6).";
            }

            if ($datos['desgaste'] >= 40) {
                $descripcion .= " URGENTE: Desgaste elevado.";
            }
            break;

        case 'desgaste_limite':
            $descripcion = "Neumático {$datos['codigo_interno']} presenta {$datos['desgaste']}% de desgaste. ";

            if ($datos['desgaste'] >= 90) {
                $descripcion .= "CRÍTICO: Evaluar retiro inmediato por seguridad operacional.";
            } elseif ($datos['desgaste'] >= 80) {
                $descripcion .= "ALTO: Programar retiro en las próximas 48 horas.";
            } else {
                $descripcion .= "Monitorear de cerca y preparar reemplazo.";
            }

            // Agregar información de valor
            if (isset($datos['valor_remanente']) && $datos['valor_remanente'] > 1000) {
                $descripcion .= " Valor remanente: $" . number_format($datos['valor_remanente'], 0) . ".";
            }
            break;

        case 'mantenimiento':
            if (isset($datos['dias_sin_seguimiento'])) {
                $descripcion = "Neumático {$datos['codigo_interno']} sin seguimiento por {$datos['dias_sin_seguimiento']} días. ";

                if ($datos['dias_sin_seguimiento'] >= 30) {
                    $descripcion .= "CRÍTICO: Seguimiento muy atrasado, riesgo de pérdida de trazabilidad.";
                } elseif ($datos['dias_sin_seguimiento'] >= 21) {
                    $descripcion .= "URGENTE: Requiere medición inmediata.";
                } else {
                    $descripcion .= "Programar medición semanal.";
                }
            } else {
                $descripcion = "Mantenimiento preventivo requerido para neumático {$datos['codigo_interno']}.";
            }
            break;
    }

    return $descripcion;
}

/**
 * Endpoint para verificación programada (cron job)
 */
if (isset($_GET['cron']) && $_GET['cron'] === 'true') {
    // Verificar que sea una llamada desde el servidor local o autorizada
    $allowed_ips = ['127.0.0.1', '::1', 'localhost'];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($client_ip, $allowed_ips) && !isset($_GET['token'])) {
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
        exit;
    }

    // Simular usuario del sistema para cron
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Sistema Automático';
    $_SESSION['user_role'] = 'admin';

    // Ejecutar verificación automática
    echo json_encode(['success' => true, 'message' => 'Verificación automática ejecutada', 'cron' => true]);
}

/**
 * Endpoint para estadísticas de verificación
 */
if (isset($_GET['stats']) && $_GET['stats'] === 'true') {
    try {
        $stats = [
            'ultima_verificacion' => null,
            'alertas_hoy' => 0,
            'alertas_semana' => 0,
            'alertas_por_tipo' => [],
            'eficiencia_sistema' => 0
        ];

        // Obtener estadísticas de alertas
        $stmt = $db->query("
            SELECT 
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as hoy,
                COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as semana,
                tipo_alerta,
                COUNT(*) as total_tipo
            FROM alertas 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY tipo_alerta
        ");

        $resultados = $stmt->fetchAll();

        foreach ($resultados as $resultado) {
            $stats['alertas_hoy'] += $resultado['hoy'];
            $stats['alertas_semana'] += $resultado['semana'];
            $stats['alertas_por_tipo'][$resultado['tipo_alerta']] = $resultado['total_tipo'];
        }

        // Calcular eficiencia del sistema (alertas resueltas / alertas generadas)
        $stmt = $db->query("
            SELECT 
                COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas,
                COUNT(*) as total
            FROM alertas 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $eficiencia = $stmt->fetch();

        if ($eficiencia['total'] > 0) {
            $stats['eficiencia_sistema'] = round(($eficiencia['resueltas'] / $eficiencia['total']) * 100, 1);
        }

        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Endpoint para obtener configuración actual
 */
if (isset($_GET['config']) && $_GET['config'] === 'true') {
    $config = [
        'modelo_30_30_30' => [
            'activo' => true,
            'limite_desgaste_rotacion' => 30,
            'limite_desgaste_critico' => 70,
            'limite_desgaste_maximo' => 90
        ],
        'mantenimiento' => [
            'dias_limite_seguimiento' => 14,
            'dias_alerta_seguimiento' => 21,
            'dias_critico_seguimiento' => 30
        ],
        'garantia' => [
            'porcentaje_alerta' => 90,
            'porcentaje_critico' => 95
        ],
        'frecuencia_verificacion' => [
            'automatica' => 'diaria',
            'manual' => 'bajo_demanda'
        ]
    ];

    echo json_encode(['success' => true, 'config' => $config]);
    exit;
}

// PROCESO PRINCIPAL DE VERIFICACIÓN
$db = new Database();

try {
    $db->beginTransaction();

    $alertas_generadas = 0;
    $alertas_actualizadas = 0;
    $errores = [];
    $log_verificacion = [];

    // VERIFICACIÓN 1: Alertas de Rotación 30% (Modelo 30-30-30)
    $log_verificacion[] = "Iniciando verificación de alertas modelo 30-30-30...";

    $stmt = $db->query("
        SELECT i.id as instalacion_id, i.posicion, i.equipo_id,
               n.codigo_interno, e.codigo as equipo_codigo, e.tipo_equipo,
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
        WHERE i.activo = 1 
        AND n.estado = 'instalado'
    ");

    $instalaciones_activas = $stmt->fetchAll();
    $log_verificacion[] = "Analizando " . count($instalaciones_activas) . " instalaciones activas...";

    foreach ($instalaciones_activas as $instalacion) {
        $generar_alerta = false;
        $tipo_alerta = '';
        $descripcion = '';
        $prioridad = 'media';

        // Lógica del modelo 30-30-30
        $grupo_posicion = obtenerGrupoPosicion($instalacion['posicion']);
        $desgaste = $instalacion['porcentaje_desgaste'];

        // Verificar si ya existe una alerta pendiente para esta instalación
        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM alertas 
            WHERE instalacion_id = ? 
            AND estado IN ('pendiente', 'revisada') 
            AND tipo_alerta = 'rotacion_30'
        ", [$instalacion['instalacion_id']]);

        $alerta_existente = $stmt->fetchColumn();

        // Generar alerta si cumple condiciones y no existe una similar
        if (!$alerta_existente && $desgaste >= 30) {
            $generar_alerta = true;
            $tipo_alerta = 'rotacion_30';

            // Usar la función de descripción inteligente
            $datos_alerta = [
                'codigo_interno' => $instalacion['codigo_interno'],
                'posicion' => $instalacion['posicion'],
                'desgaste' => $desgaste
            ];

            $descripcion = generarDescripcionInteligente($tipo_alerta, $datos_alerta);

            // Calcular prioridad dinámica
            $prioridad = calcularPrioridadDinamica(
                $desgaste,
                0, // días sin seguimiento se calcula en otra verificación
                $instalacion['posicion'],
                $instalacion['tipo_equipo']
            );
        }

        if ($generar_alerta) {
            try {
                $stmt = $db->query("
                    INSERT INTO alertas (
                        instalacion_id, tipo_alerta, descripcion, 
                        fecha_alerta, prioridad, created_at
                    ) VALUES (?, ?, ?, CURDATE(), ?, NOW())
                ", [
                    $instalacion['instalacion_id'],
                    $tipo_alerta,
                    $descripcion,
                    $prioridad
                ]);

                $alertas_generadas++;
                $log_verificacion[] = "✓ Alerta generada: {$instalacion['equipo_codigo']} Pos.{$instalacion['posicion']} - {$desgaste}%";
            } catch (Exception $e) {
                $errores[] = "Error generando alerta para {$instalacion['equipo_codigo']}: " . $e->getMessage();
            }
        }
    }

    // VERIFICACIÓN 2: Alertas de Desgaste Límite (>70%)
    $log_verificacion[] = "Verificando alertas de desgaste límite...";

    $stmt = $db->query("
        SELECT i.id as instalacion_id, i.posicion,
               n.codigo_interno, n.valor_compra, e.codigo as equipo_codigo,
               COALESCE(
                   (SELECT MAX(ss.porcentaje_desgaste) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as porcentaje_desgaste
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        WHERE i.activo = 1 
        AND n.estado = 'instalado'
        AND COALESCE(
            (SELECT MAX(ss.porcentaje_desgaste) 
             FROM seguimiento_semanal ss 
             WHERE ss.instalacion_id = i.id), 
            0
        ) >= 70
    ");

    $neumaticos_criticos = $stmt->fetchAll();

    foreach ($neumaticos_criticos as $critico) {
        // Verificar si ya existe alerta de desgaste límite
        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM alertas 
            WHERE instalacion_id = ? 
            AND estado IN ('pendiente', 'revisada') 
            AND tipo_alerta = 'desgaste_limite'
        ", [$critico['instalacion_id']]);

        if ($stmt->fetchColumn() == 0) {
            try {
                $valor_remanente = $critico['valor_compra'] * (1 - $critico['porcentaje_desgaste'] / 100);

                $datos_alerta = [
                    'codigo_interno' => $critico['codigo_interno'],
                    'desgaste' => $critico['porcentaje_desgaste'],
                    'valor_remanente' => $valor_remanente
                ];

                $descripcion = generarDescripcionInteligente('desgaste_limite', $datos_alerta);
                $prioridad = $critico['porcentaje_desgaste'] >= 85 ? 'critica' : 'alta';

                $stmt = $db->query("
                    INSERT INTO alertas (
                        instalacion_id, tipo_alerta, descripcion, 
                        fecha_alerta, prioridad, created_at
                    ) VALUES (?, ?, ?, CURDATE(), ?, NOW())
                ", [
                    $critico['instalacion_id'],
                    'desgaste_limite',
                    $descripcion,
                    $prioridad
                ]);

                $alertas_generadas++;
                $log_verificacion[] = "✓ Alerta crítica: {$critico['equipo_codigo']} - {$critico['porcentaje_desgaste']}%";
            } catch (Exception $e) {
                $errores[] = "Error generando alerta crítica para {$critico['equipo_codigo']}: " . $e->getMessage();
            }
        }
    }

    // VERIFICACIÓN 3: Alertas de Mantenimiento (falta de seguimiento)
    $log_verificacion[] = "Verificando alertas de mantenimiento...";

    $stmt = $db->query("
        SELECT i.id as instalacion_id, 
               n.codigo_interno, e.codigo as equipo_codigo,
               COALESCE(
                   (SELECT MAX(ss.fecha_medicion) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   i.fecha_instalacion
               ) as ultima_medicion,
               DATEDIFF(CURDATE(), COALESCE(
                   (SELECT MAX(ss.fecha_medicion) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   i.fecha_instalacion
               )) as dias_sin_seguimiento
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        WHERE i.activo = 1 
        AND n.estado = 'instalado'
        HAVING dias_sin_seguimiento >= 14
    ");

    $sin_seguimiento = $stmt->fetchAll();

    foreach ($sin_seguimiento as $mantenimiento) {
        // Verificar si ya existe alerta de mantenimiento reciente
        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM alertas 
            WHERE instalacion_id = ? 
            AND estado IN ('pendiente', 'revisada') 
            AND tipo_alerta = 'mantenimiento'
            AND fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ", [$mantenimiento['instalacion_id']]);

        if ($stmt->fetchColumn() == 0) {
            try {
                $datos_alerta = [
                    'codigo_interno' => $mantenimiento['codigo_interno'],
                    'dias_sin_seguimiento' => $mantenimiento['dias_sin_seguimiento']
                ];

                $descripcion = generarDescripcionInteligente('mantenimiento', $datos_alerta);
                $prioridad = $mantenimiento['dias_sin_seguimiento'] >= 30 ? 'alta' : 'media';

                $stmt = $db->query("
                    INSERT INTO alertas (
                        instalacion_id, tipo_alerta, descripcion, 
                        fecha_alerta, prioridad, created_at
                    ) VALUES (?, ?, ?, CURDATE(), ?, NOW())
                ", [
                    $mantenimiento['instalacion_id'],
                    'mantenimiento',
                    $descripcion,
                    $prioridad
                ]);

                $alertas_generadas++;
                $log_verificacion[] = "✓ Alerta mantenimiento: {$mantenimiento['equipo_codigo']} - {$mantenimiento['dias_sin_seguimiento']} días";
            } catch (Exception $e) {
                $errores[] = "Error generando alerta de mantenimiento para {$mantenimiento['equipo_codigo']}: " . $e->getMessage();
            }
        }
    }

    // VERIFICACIÓN 4: Actualizar alertas obsoletas
    $log_verificacion[] = "Actualizando alertas obsoletas...";

    // Marcar como resueltas las alertas de rotación donde el neumático ya fue movido
    $stmt = $db->query("
        UPDATE alertas a
        JOIN instalaciones i ON a.instalacion_id = i.id
        SET a.estado = 'resuelta', a.updated_at = NOW()
        WHERE a.tipo_alerta = 'rotacion_30'
        AND a.estado = 'pendiente'
        AND EXISTS (
            SELECT 1 FROM movimientos m 
            WHERE m.neumatico_id = i.neumatico_id 
            AND m.fecha_movimiento > a.fecha_alerta
            AND m.tipo_movimiento = 'rotacion'
        )
    ");
    $alertas_actualizadas += $stmt->rowCount();

    // Marcar como resueltas las alertas de desgaste donde el neumático fue retirado
    $stmt = $db->query("
        UPDATE alertas a
        JOIN instalaciones i ON a.instalacion_id = i.id
        JOIN neumaticos n ON i.neumatico_id = n.id
        SET a.estado = 'resuelta', a.updated_at = NOW()
        WHERE a.tipo_alerta = 'desgaste_limite'
        AND a.estado IN ('pendiente', 'revisada')
        AND n.estado = 'desechado'
    ");
    $alertas_actualizadas += $stmt->rowCount();

    // VERIFICACIÓN 5: Alertas de garantía próxima a vencer
    $log_verificacion[] = "Verificando alertas de garantía...";

    $stmt = $db->query("
        SELECT i.id as instalacion_id,
               n.codigo_interno, e.codigo as equipo_codigo,
               n.garantia_horas,
               COALESCE(
                   (SELECT SUM(ss.horas_trabajadas) 
                    FROM seguimiento_semanal ss 
                    WHERE ss.instalacion_id = i.id), 
                   0
               ) as horas_acumuladas
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN equipos e ON i.equipo_id = e.id
        WHERE i.activo = 1 
        AND n.estado = 'instalado'
        AND n.garantia_horas > 0
        HAVING (horas_acumuladas / n.garantia_horas) >= 0.90
    ");

    $garantias_venciendo = $stmt->fetchAll();

    foreach ($garantias_venciendo as $garantia) {
        // Verificar si ya existe alerta de garantía
        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM alertas 
            WHERE instalacion_id = ? 
            AND estado IN ('pendiente', 'revisada') 
            AND descripcion LIKE '%garantía%'
            AND fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", [$garantia['instalacion_id']]);

        if ($stmt->fetchColumn() == 0) {
            try {
                $porcentaje_garantia = ($garantia['horas_acumuladas'] / $garantia['garantia_horas']) * 100;
                $horas_restantes = $garantia['garantia_horas'] - $garantia['horas_acumuladas'];

                $prioridad = $porcentaje_garantia >= 95 ? 'alta' : 'media';
                $descripcion = "Neumático {$garantia['codigo_interno']} al " . round($porcentaje_garantia, 1) . "% de garantía. Quedan {$horas_restantes} horas.";

                if ($porcentaje_garantia >= 100) {
                    $descripcion .= " VENCIDA: Garantía expirada.";
                    $prioridad = 'critica';
                }

                $stmt = $db->query("
                    INSERT INTO alertas (
                        instalacion_id, tipo_alerta, descripcion, 
                        fecha_alerta, prioridad, created_at
                    ) VALUES (?, ?, ?, CURDATE(), ?, NOW())
                ", [
                    $garantia['instalacion_id'],
                    'garantia',
                    $descripcion,
                    $prioridad
                ]);

                $alertas_generadas++;
                $log_verificacion[] = "✓ Alerta garantía: {$garantia['equipo_codigo']} - " . round($porcentaje_garantia, 1) . "%";
            } catch (Exception $e) {
                $errores[] = "Error generando alerta de garantía para {$garantia['equipo_codigo']}: " . $e->getMessage();
            }
        }
    }

    $db->commit();
    $log_verificacion[] = "Verificación completada exitosamente.";

    // Preparar respuesta
    $response = [
        'success' => true,
        'alertas_generadas' => $alertas_generadas,
        'alertas_actualizadas' => $alertas_actualizadas,
        'errores_encontrados' => count($errores),
        'instalaciones_analizadas' => count($instalaciones_activas),
        'log_verificacion' => $log_verificacion,
        'errores' => $errores,
        'timestamp' => date('Y-m-d H:i:s'),
        'usuario' => $_SESSION['user_name'] ?? 'Sistema',
        'resumen' => [
            'rotacion_30' => 0,
            'desgaste_limite' => 0,
            'mantenimiento' => 0,
            'garantia' => 0
        ]
    ];

    // Contar alertas por tipo generadas hoy
    if ($alertas_generadas > 0) {
        $stmt = $db->query("
            SELECT tipo_alerta, COUNT(*) as cantidad
            FROM alertas 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY tipo_alerta
        ");
        $tipos_generados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($tipos_generados as $tipo => $cantidad) {
            if (isset($response['resumen'][$tipo])) {
                $response['resumen'][$tipo] = $cantidad;
            }
        }
    }

    // Registrar la verificación en logs del sistema
    error_log("Verificación de alertas completada: " . json_encode([
        'alertas_generadas' => $alertas_generadas,
        'alertas_actualizadas' => $alertas_actualizadas,
        'usuario' => $_SESSION['user_name'] ?? 'Sistema'
    ]));

    echo json_encode($response);
} catch (Exception $e) {
    if (isset($db)) $db->rollback();

    // Log del error
    error_log("Error en verificación de alertas: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error durante la verificación: ' . $e->getMessage(),
        'alertas_generadas' => $alertas_generadas ?? 0,
        'alertas_actualizadas' => $alertas_actualizadas ?? 0,
        'log_verificacion' => $log_verificacion ?? [],
        'errores' => array_merge($errores ?? [], [$e->getMessage()]),
        'timestamp' => date('Y-m-d H:i:s'),
        'usuario' => $_SESSION['user_name'] ?? 'Sistema'
    ]);
}

/**
 * Función auxiliar para limpiar alertas duplicadas
 */
function limpiarAlertasDuplicadas($db)
{
    try {
        // Eliminar alertas duplicadas manteniendo la más reciente
        $stmt = $db->query("
            DELETE a1 FROM alertas a1
            INNER JOIN alertas a2 
            WHERE a1.id < a2.id 
            AND a1.instalacion_id = a2.instalacion_id 
            AND a1.tipo_alerta = a2.tipo_alerta
            AND a1.estado = 'pendiente'
            AND a2.estado = 'pendiente'
            AND DATE(a1.created_at) = DATE(a2.created_at)
        ");

        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error limpiando alertas duplicadas: " . $e->getMessage());
        return 0;
    }
}

/**
 * Función para obtener métricas de rendimiento del sistema
 */
function obtenerMetricasRendimiento($db)
{
    try {
        $metricas = [];

        // Tiempo promedio de resolución de alertas
        $stmt = $db->query("
            SELECT 
                AVG(DATEDIFF(updated_at, created_at)) as tiempo_promedio_resolucion,
                COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as alertas_resueltas,
                COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as alertas_pendientes,
                COUNT(*) as total_alertas
            FROM alertas 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");

        $resultado = $stmt->fetch();
        $metricas['tiempo_promedio_resolucion'] = $resultado['tiempo_promedio_resolucion'] ?? 0;
        $metricas['alertas_resueltas'] = $resultado['alertas_resueltas'];
        $metricas['alertas_pendientes'] = $resultado['alertas_pendientes'];
        $metricas['total_alertas'] = $resultado['total_alertas'];

        // Efectividad por tipo de alerta
        $stmt = $db->query("
            SELECT 
                tipo_alerta,
                COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas,
                COUNT(*) as total,
                ROUND((COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) / COUNT(*)) * 100, 1) as efectividad
            FROM alertas 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY tipo_alerta
        ");

        $metricas['efectividad_por_tipo'] = $stmt->fetchAll();

        // Tendencia de alertas por día
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as fecha,
                COUNT(*) as cantidad_alertas
            FROM alertas 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY fecha DESC
        ");

        $metricas['tendencia_semanal'] = $stmt->fetchAll();

        return $metricas;
    } catch (Exception $e) {
        error_log("Error obteniendo métricas de rendimiento: " . $e->getMessage());
        return [];
    }
}

/**
 * Endpoint para métricas de rendimiento
 */
if (isset($_GET['metricas']) && $_GET['metricas'] === 'true') {
    $db = new Database();
    $metricas = obtenerMetricasRendimiento($db);

    echo json_encode([
        'success' => true,
        'metricas' => $metricas,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Endpoint para limpiar alertas duplicadas
 */
if (isset($_GET['limpiar']) && $_GET['limpiar'] === 'true') {
    if (!Auth::canAccess(['admin'])) {
        echo json_encode(['success' => false, 'message' => 'Solo administradores pueden limpiar alertas']);
        exit;
    }

    $db = new Database();
    $alertas_eliminadas = limpiarAlertasDuplicadas($db);

    echo json_encode([
        'success' => true,
        'alertas_eliminadas' => $alertas_eliminadas,
        'message' => "Se eliminaron {$alertas_eliminadas} alertas duplicadas",
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Endpoint para configurar umbrales personalizados
 */
if (isset($_POST['configurar_umbrales'])) {
    if (!Auth::canAccess(['admin'])) {
        echo json_encode(['success' => false, 'message' => 'Solo administradores pueden configurar umbrales']);
        exit;
    }

    try {
        $umbrales = json_decode($_POST['umbrales'], true);

        // Validar datos de entrada
        $umbrales_validos = [
            'desgaste_rotacion' => (int)($umbrales['desgaste_rotacion'] ?? 30),
            'desgaste_critico' => (int)($umbrales['desgaste_critico'] ?? 70),
            'dias_seguimiento' => (int)($umbrales['dias_seguimiento'] ?? 14),
            'porcentaje_garantia' => (int)($umbrales['porcentaje_garantia'] ?? 90)
        ];

        // Guardar configuración en base de datos o archivo
        $db = new Database();
        $stmt = $db->query("
            INSERT INTO configuracion_sistema (clave, valor, updated_at) 
            VALUES ('umbrales_alertas', ?, NOW())
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()
        ", [json_encode($umbrales_validos)]);

        echo json_encode([
            'success' => true,
            'message' => 'Umbrales configurados correctamente',
            'umbrales' => $umbrales_validos
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error configurando umbrales: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Función para generar reporte de alertas
 */
function generarReporteAlertas($db, $fecha_inicio, $fecha_fin)
{
    try {
        $stmt = $db->query("
            SELECT 
                a.id,
                a.tipo_alerta,
                a.descripcion,
                a.prioridad,
                a.estado,
                a.fecha_alerta,
                a.created_at,
                a.updated_at,
                n.codigo_interno,
                e.codigo as equipo_codigo,
                e.tipo_equipo,
                i.posicion,
                COALESCE(
                    (SELECT MAX(ss.porcentaje_desgaste) 
                     FROM seguimiento_semanal ss 
                     WHERE ss.instalacion_id = i.id), 
                    0
                ) as desgaste_actual
            FROM alertas a
            JOIN instalaciones i ON a.instalacion_id = i.id
            JOIN neumaticos n ON i.neumatico_id = n.id
            JOIN equipos e ON i.equipo_id = e.id
            WHERE a.created_at BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ", [$fecha_inicio, $fecha_fin]);

        $alertas = $stmt->fetchAll();

        // Generar estadísticas del reporte
        $estadisticas = [
            'total_alertas' => count($alertas),
            'por_tipo' => [],
            'por_prioridad' => [],
            'por_estado' => [],
            'por_equipo' => []
        ];

        foreach ($alertas as $alerta) {
            // Por tipo
            $tipo = $alerta['tipo_alerta'];
            $estadisticas['por_tipo'][$tipo] = ($estadisticas['por_tipo'][$tipo] ?? 0) + 1;

            // Por prioridad
            $prioridad = $alerta['prioridad'];
            $estadisticas['por_prioridad'][$prioridad] = ($estadisticas['por_prioridad'][$prioridad] ?? 0) + 1;

            // Por estado
            $estado = $alerta['estado'];
            $estadisticas['por_estado'][$estado] = ($estadisticas['por_estado'][$estado] ?? 0) + 1;

            // Por tipo de equipo
            $tipo_equipo = $alerta['tipo_equipo'];
            $estadisticas['por_equipo'][$tipo_equipo] = ($estadisticas['por_equipo'][$tipo_equipo] ?? 0) + 1;
        }

        return [
            'alertas' => $alertas,
            'estadisticas' => $estadisticas,
            'periodo' => [
                'inicio' => $fecha_inicio,
                'fin' => $fecha_fin
            ]
        ];
    } catch (Exception $e) {
        throw new Exception("Error generando reporte: " . $e->getMessage());
    }
}

/**
 * Endpoint para generar reportes
 */
if (isset($_GET['reporte']) && $_GET['reporte'] === 'true') {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

    try {
        $db = new Database();
        $reporte = generarReporteAlertas($db, $fecha_inicio, $fecha_fin);

        echo json_encode([
            'success' => true,
            'reporte' => $reporte,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
