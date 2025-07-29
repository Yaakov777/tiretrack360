<?php
require_once '../config.php';
Auth::requireLogin();

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID de equipo inválido</div>';
    exit;
}

$equipo_id = (int)$_GET['id'];
$db = new Database();

try {
    // Obtener información detallada del equipo
    $stmt = $db->query("
        SELECT e.*,
               COUNT(i.id) as neumaticos_instalados,
               COALESCE(SUM(n.costo_nuevo), 0) as valor_total_neumaticos,
               COALESCE(SUM(n.costo_nuevo * (n.remanente_nuevo / 100)), 0) as valor_actual_neumaticos,
               COALESCE(AVG(ss.porcentaje_desgaste), 0) as desgaste_promedio,
               COALESCE(SUM(ss.horas_trabajadas), 0) as horas_acumuladas_total,
               DATE(e.created_at) as fecha_registro
        FROM equipos e
        LEFT JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
        LEFT JOIN neumaticos n ON i.neumatico_id = n.id
        LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
        WHERE e.id = ?
        GROUP BY e.id
    ", [$equipo_id]);

    $equipo = $stmt->fetch();

    if (!$equipo) {
        echo '<div class="alert alert-warning">Equipo no encontrado</div>';
        exit;
    }

    // Obtener neumáticos instalados con detalles
    $stmt = $db->query("
        SELECT i.*, n.codigo_interno, n.numero_serie, n.costo_nuevo,
               m.nombre as marca_nombre, d.nombre as diseno_nombre,
               med.medida as medida_nombre, i.cocada_inicial,
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
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.equipo_id = ? AND i.activo = 1
        ORDER BY i.posicion
    ", [$equipo_id]);
    $neumaticos = $stmt->fetchAll();

    // Obtener alertas activas del equipo
    $stmt = $db->query("
        SELECT a.*, i.posicion, n.codigo_interno
        FROM alertas a
        JOIN instalaciones i ON a.instalacion_id = i.id
        JOIN neumaticos n ON i.neumatico_id = n.id
        WHERE i.equipo_id = ? AND a.estado = 'pendiente'
        ORDER BY FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'), a.fecha_alerta DESC
    ", [$equipo_id]);
    $alertas = $stmt->fetchAll();

    // Obtener historial de movimientos recientes
    $stmt = $db->query("
        SELECT m.*, n.codigo_interno,
               eq_origen.codigo as equipo_origen_codigo,
               eq_destino.codigo as equipo_destino_codigo
        FROM movimientos m
        JOIN neumaticos n ON m.neumatico_id = n.id
        LEFT JOIN equipos eq_origen ON m.equipo_origen_id = eq_origen.id
        LEFT JOIN equipos eq_destino ON m.equipo_destino_id = eq_destino.id
        WHERE m.equipo_origen_id = ? OR m.equipo_destino_id = ?
        ORDER BY m.fecha_movimiento DESC
        LIMIT 10
    ", [$equipo_id, $equipo_id]);
    $movimientos = $stmt->fetchAll();

    // Calcular eficiencia y proyecciones
    $eficiencia_costo = $equipo['horas_acumuladas_total'] > 0 ?
        $equipo['valor_total_neumaticos'] / $equipo['horas_acumuladas_total'] : 0;

    $vida_util_restante = 100 - $equipo['desgaste_promedio'];
    $horas_proyectadas = $vida_util_restante > 0 && $equipo['desgaste_promedio'] > 0 ?
        ($equipo['horas_acumuladas_total'] * 100) / $equipo['desgaste_promedio'] : 0;
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error al cargar los datos: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<!-- Información principal del equipo -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-info-circle"></i> Información General
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Código:</td>
                                <td><strong><?= htmlspecialchars($equipo['codigo']) ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nombre:</td>
                                <td><?= htmlspecialchars($equipo['nombre']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipo:</td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($equipo['tipo']) ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Modelo:</td>
                                <td><?= htmlspecialchars($equipo['modelo']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Estado:</td>
                                <td>
                                    <span class="badge bg-<?= $equipo['activo'] ? 'success' : 'secondary' ?>">
                                        <?= $equipo['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Horas/Mes:</td>
                                <td><?= number_format($equipo['horas_mes_promedio']) ?> hrs</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Registrado:</td>
                                <td><?= formatDate($equipo['fecha_registro']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Utilización:</td>
                                <td>
                                    <?php $utilizacion = ($equipo['horas_mes_promedio'] / 744) * 100; ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?= $utilizacion > 80 ? 'success' : ($utilizacion > 50 ? 'warning' : 'info') ?>"
                                            style="width: <?= $utilizacion ?>%">
                                            <?= number_format($utilizacion, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-graph-up"></i> Estadísticas
                </h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="h4 text-primary"><?= $equipo['neumaticos_instalados'] ?></div>
                        <small class="text-muted">Neumáticos Instalados</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-success"><?= formatCurrency($equipo['valor_actual_neumaticos']) ?></div>
                        <small class="text-muted">Valor Actual</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-warning"><?= number_format($equipo['desgaste_promedio'], 1) ?>%</div>
                        <small class="text-muted">Desgaste Prom.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Neumáticos instalados -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-circle"></i> Neumáticos Instalados
                </h6>
                <span class="badge bg-primary"><?= count($neumaticos) ?> instalados</span>
            </div>
            <div class="card-body">
                <?php if (empty($neumaticos)): ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-inbox h3"></i><br>
                    No hay neumáticos instalados en este equipo
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Pos.</th>
                                <th>Neumático</th>
                                <th>Marca/Diseño</th>
                                <th>Medida</th>
                                <th>Cocada</th>
                                <th>Desgaste</th>
                                <th>Horas</th>
                                <th>Costo/Hr</th>
                                <th>Última Med.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($neumaticos as $neumatico): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= $neumatico['posicion'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($neumatico['codigo_interno']) ?></strong><br>
                                    <small
                                        class="text-muted"><?= htmlspecialchars($neumatico['numero_serie']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($neumatico['marca_nombre']) ?></strong><br>
                                    <small
                                        class="text-muted"><?= htmlspecialchars($neumatico['diseno_nombre']) ?></small>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-info"><?= htmlspecialchars($neumatico['medida_nombre']) ?></span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <div class="fw-bold"><?= number_format($neumatico['cocada_actual'], 1) ?></div>
                                        <small class="text-muted">mm</small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                            $desgaste = $neumatico['porcentaje_desgaste'];
                                            $color = $desgaste > 70 ? 'danger' : ($desgaste > 30 ? 'warning' : 'success');
                                            ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?= $color ?>"
                                            style="width: <?= min($desgaste, 100) ?>%">
                                            <?= number_format($desgaste, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="fw-bold"><?= number_format($neumatico['horas_acumuladas']) ?></div>
                                    <small class="text-muted">hrs</small>
                                </td>
                                <td class="text-center">
                                    <?php $costo_hora = $neumatico['horas_acumuladas'] > 0 ?
                                                $neumatico['costo_nuevo'] / $neumatico['horas_acumuladas'] : 0; ?>
                                    <div class="fw-bold">S/<?= number_format($costo_hora, 2) ?></div>
                                    <small class="text-muted">/hr</small>
                                </td>
                                <td>
                                    <small><?= formatDate($neumatico['ultima_medicion']) ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alertas y Proyecciones -->
<div class="row mb-4">
    <!-- Alertas Activas -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Alertas Activas
                </h6>
                <span class="badge bg-<?= count($alertas) > 0 ? 'warning' : 'success' ?>">
                    <?= count($alertas) ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($alertas)): ?>
                <div class="text-center text-muted">
                    <i class="bi bi-check-circle h3 text-success"></i><br>
                    No hay alertas pendientes
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($alertas as $alerta): ?>
                    <div
                        class="list-group-item border-start border-<?= getPrioridadColor($alerta['prioridad']) ?> border-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-1">Pos. <?= $alerta['posicion'] ?> - <?= $alerta['codigo_interno'] ?></h6>
                                <p class="mb-1 small"><?= htmlspecialchars($alerta['descripcion']) ?></p>
                                <small class="text-muted"><?= formatDate($alerta['fecha_alerta']) ?></small>
                            </div>
                            <span class="badge bg-<?= getPrioridadColor($alerta['prioridad']) ?>">
                                <?= ucfirst($alerta['prioridad']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Proyecciones -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-graph-up-arrow"></i> Proyecciones
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="h5 text-primary"><?= number_format($vida_util_restante, 1) ?>%</div>
                        <small class="text-muted">Vida Útil Restante</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h5 text-info"><?= number_format($horas_proyectadas) ?></div>
                        <small class="text-muted">Horas Proyectadas</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-success">S/<?= number_format($eficiencia_costo, 2) ?></div>
                        <small class="text-muted">Costo/Hora Actual</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-warning">
                            <?= $equipo['horas_mes_promedio'] > 0 ?
                                number_format(($vida_util_restante / 100) * ($equipo['horas_mes_promedio'] / 30), 1) : '0' ?>
                        </div>
                        <small class="text-muted">Días Restantes</small>
                    </div>
                </div>

                <!-- Gráfico de progreso general -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Progreso General del Equipo</small>
                        <small class="fw-bold"><?= number_format($equipo['desgaste_promedio'], 1) ?>%</small>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-gradient"
                            style="width: <?= min($equipo['desgaste_promedio'], 100) ?>%; 
                                    background: linear-gradient(90deg, 
                                    <?= $equipo['desgaste_promedio'] > 70 ? '#dc3545' : ($equipo['desgaste_promedio'] > 30 ? '#ffc107' : '#198754') ?> 0%, 
                                    <?= $equipo['desgaste_promedio'] > 70 ? '#c82333' : ($equipo['desgaste_promedio'] > 30 ? '#e0a800' : '#157347') ?> 100%)">
                            <?= number_format($equipo['desgaste_promedio'], 1) ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movimientos Recientes -->
<?php if (!empty($movimientos)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-arrow-repeat"></i> Movimientos Recientes
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Neumático</th>
                                <th>Tipo</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Motivo</th>
                                <th>Horas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $movimiento): ?>
                            <tr>
                                <td><?= formatDate($movimiento['fecha_movimiento']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($movimiento['codigo_interno']) ?></td>
                                <td>
                                    <span class="badge bg-<?=
                                                                    $movimiento['tipo_movimiento'] == 'instalacion' ? 'success' : ($movimiento['tipo_movimiento'] == 'rotacion' ? 'warning' : 'danger')
                                                                    ?>">
                                        <?= ucfirst($movimiento['tipo_movimiento']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $movimiento['equipo_origen_codigo'] ?
                                                htmlspecialchars($movimiento['equipo_origen_codigo']) .
                                                ($movimiento['posicion_origen'] ? ' (Pos. ' . $movimiento['posicion_origen'] . ')' : '')
                                                : '-' ?>
                                </td>
                                <td>
                                    <?= $movimiento['equipo_destino_codigo'] ?
                                                htmlspecialchars($movimiento['equipo_destino_codigo']) .
                                                ($movimiento['posicion_destino'] ? ' (Pos. ' . $movimiento['posicion_destino'] . ')' : '')
                                                : '-' ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($movimiento['motivo'] ?: '-') ?>
                                    </small>
                                </td>
                                <td><?= number_format($movimiento['horas_acumuladas']) ?></td>
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