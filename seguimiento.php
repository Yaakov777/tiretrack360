<?php
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Procesamiento de registro de seguimiento
if ($_POST && $_POST['action'] === 'register_seguimiento') {
    try {
        $db->beginTransaction();

        // Llamar al procedimiento almacenado para registrar seguimiento
        $stmt = $db->query("
            CALL sp_registrar_seguimiento_semanal(?, ?, ?, ?, ?)
        ", [
            $_POST['instalacion_id'],
            $_POST['fecha_medicion'],
            $_POST['cocada_actual'],
            $_POST['horas_trabajadas'],
            $_POST['observaciones']
        ]);

        $db->commit();
        $success = "Seguimiento registrado exitosamente";
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener equipos con instalaciones activas
$stmt = $db->query("
    SELECT DISTINCT e.id, e.codigo, e.nombre, e.tipo,
           COUNT(i.id) as total_neumaticos
    FROM equipos e
    JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
    WHERE e.activo = 1
    GROUP BY e.id
    ORDER BY e.codigo
");
$equipos = $stmt->fetchAll();

// Obtener equipo seleccionado
$equipo_seleccionado = $_GET['equipo'] ?? ($equipos[0]['id'] ?? null);

// Obtener instalaciones del equipo seleccionado
$instalaciones = [];
if ($equipo_seleccionado) {
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
               ) as porcentaje_desgaste
        FROM instalaciones i
        JOIN neumaticos n ON i.neumatico_id = n.id
        JOIN marcas m ON n.marca_id = m.id
        JOIN disenos d ON n.diseno_id = d.id
        JOIN medidas med ON n.medida_id = med.id
        WHERE i.equipo_id = ? AND i.activo = 1
        ORDER BY i.posicion
    ", [$equipo_seleccionado]);
    $instalaciones = $stmt->fetchAll();
}

// Obtener seguimiento semanal reciente
$seguimiento_reciente = [];
if ($equipo_seleccionado) {
    $stmt = $db->query("
        SELECT ss.*, i.posicion, n.codigo_interno
        FROM seguimiento_semanal ss
        JOIN instalaciones i ON ss.instalacion_id = i.id
        JOIN neumaticos n ON i.neumatico_id = n.id
        WHERE i.equipo_id = ?
        ORDER BY ss.fecha_medicion DESC
        LIMIT 20
    ", [$equipo_seleccionado]);
    $seguimiento_reciente = $stmt->fetchAll();
}

// Datos para gráficos
$graficos_data = [];
if ($equipo_seleccionado) {
    $stmt = $db->query("
        SELECT i.posicion, n.codigo_interno,
               ss.fecha_medicion, ss.cocada_actual, ss.porcentaje_desgaste
        FROM seguimiento_semanal ss
        JOIN instalaciones i ON ss.instalacion_id = i.id
        JOIN neumaticos n ON i.neumatico_id = n.id
        WHERE i.equipo_id = ? AND ss.fecha_medicion >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ORDER BY ss.fecha_medicion ASC
    ", [$equipo_seleccionado]);
    $graficos_data = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Semanal - TireSystem</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    }

    .sidebar .nav-link {
        color: #ecf0f1;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin: 0.25rem 0;
        transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    .main-content {
        background-color: #f8f9fa;
        min-height: 100vh;
    }

    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .position-card {
        transition: transform 0.2s ease;
        cursor: pointer;
    }

    .position-card:hover {
        transform: translateY(-2px);
    }

    .desgaste-progress {
        height: 25px;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .equipment-selector {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar p-3">
                <div class="text-center mb-4">
                    <h4 class="text-white">
                        <i class="bi bi-gear-wide"></i> TireSystem
                    </h4>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="neumaticos.php">
                            <i class="bi bi-circle"></i> Neumáticos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="equipos.php">
                            <i class="bi bi-truck"></i> Equipos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="instalaciones.php">
                            <i class="bi bi-arrow-repeat"></i> Instalaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="seguimiento.php">
                            <i class="bi bi-graph-up"></i> Seguimiento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="alertas.php">
                            <i class="bi bi-exclamation-triangle"></i> Alertas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reportes.php">
                            <i class="bi bi-file-text"></i> Reportes
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto main-content p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-graph-up text-primary"></i> Seguimiento Semanal
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#seguimientoModal">
                        <i class="bi bi-plus-lg"></i> Registrar Medición
                    </button>
                </div>

                <!-- Alertas -->
                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Selector de equipo -->
                <div class="equipment-selector mb-4">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <label for="equipoSelect" class="form-label fw-bold">Seleccionar Equipo:</label>
                                <select class="form-select" id="equipoSelect" onchange="cambiarEquipo(this.value)">
                                    <?php foreach ($equipos as $equipo): ?>
                                    <option value="<?= $equipo['id'] ?>"
                                        <?= $equipo['id'] == $equipo_seleccionado ? 'selected' : '' ?>>
                                        <?= $equipo['codigo'] ?> - <?= $equipo['nombre'] ?>
                                        (<?= $equipo['total_neumaticos'] ?> neumáticos)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8 text-end">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-file-excel"></i> Exportar Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-printer"></i> Imprimir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($instalaciones)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox h1 text-muted"></i>
                    <h4 class="text-muted">No hay instalaciones activas</h4>
                    <p class="text-muted">Seleccione un equipo con neumáticos instalados</p>
                </div>
                <?php else: ?>

                <!-- Estado actual del equipo -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-truck"></i>
                                    Estado Actual -
                                    <?= $equipos[array_search($equipo_seleccionado, array_column($equipos, 'id'))]['codigo'] ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($instalaciones as $instalacion): ?>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="card position-card h-100"
                                            onclick="abrirSeguimiento(<?= $instalacion['id'] ?>)">
                                            <div class="card-body text-center p-3">
                                                <div class="position-number mb-2">
                                                    <span class="badge bg-primary fs-6">
                                                        Pos. <?= $instalacion['posicion'] ?>
                                                    </span>
                                                </div>

                                                <h6 class="fw-bold mb-1"><?= $instalacion['codigo_interno'] ?></h6>
                                                <small class="text-muted d-block mb-2">
                                                    <?= $instalacion['marca_nombre'] ?>
                                                </small>

                                                <div class="mb-2">
                                                    <small class="text-muted">Cocada Actual:</small>
                                                    <div class="fw-bold">
                                                        <?= number_format($instalacion['cocada_actual'], 1) ?> mm</div>
                                                </div>

                                                <div class="progress desgaste-progress mb-2">
                                                    <?php
                                                            $desgaste = $instalacion['porcentaje_desgaste'];
                                                            $color = $desgaste > 70 ? 'danger' : ($desgaste > 30 ? 'warning' : 'success');
                                                            ?>
                                                    <div class="progress-bar bg-<?= $color ?>"
                                                        style="width: <?= min($desgaste, 100) ?>%">
                                                        <?= number_format($desgaste, 1) ?>%
                                                    </div>
                                                </div>

                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted">Horas:</small>
                                                        <div class="fw-bold small">
                                                            <?= number_format($instalacion['horas_acumuladas']) ?></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Costo/hr:</small>
                                                        <div class="fw-bold small">
                                                            S/<?= $instalacion['horas_acumuladas'] > 0 ? number_format($instalacion['costo_nuevo'] / $instalacion['horas_acumuladas'], 2) : '0' ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de seguimiento -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-down"></i> Evolución del Desgaste
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="desgasteChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-pie-chart"></i> Distribución por Posición
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="posicionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial reciente -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> Seguimiento Reciente
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($seguimiento_reciente)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-clock h1"></i><br>
                                    No hay registros de seguimiento
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Neumático</th>
                                                <th>Posición</th>
                                                <th>Cocada</th>
                                                <th>Desgaste</th>
                                                <th>Horas</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($seguimiento_reciente as $seguimiento): ?>
                                            <tr>
                                                <td><?= formatDate($seguimiento['fecha_medicion']) ?></td>
                                                <td class="fw-bold"><?= $seguimiento['codigo_interno'] ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        Pos. <?= $seguimiento['posicion'] ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($seguimiento['cocada_actual'], 1) ?> mm</td>
                                                <td>
                                                    <?php
                                                                $desgaste = $seguimiento['porcentaje_desgaste'];
                                                                $color = $desgaste > 70 ? 'danger' : ($desgaste > 30 ? 'warning' : 'success');
                                                                ?>
                                                    <span class="badge bg-<?= $color ?>">
                                                        <?= number_format($desgaste, 1) ?>%
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal para registrar seguimiento -->
    <div class="modal fade" id="seguimientoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="seguimientoForm">
                    <input type="hidden" name="action" value="register_seguimiento">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-graph-up"></i> Registrar Seguimiento Semanal
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="instalacion_id" class="form-label">Neumático/Posición *</label>
                                <select class="form-select" id="instalacion_id" name="instalacion_id" required>
                                    <option value="">Seleccionar neumático</option>
                                    <?php foreach ($instalaciones as $instalacion): ?>
                                    <option value="<?= $instalacion['id'] ?>"
                                        data-cocada="<?= $instalacion['cocada_actual'] ?>">
                                        Pos. <?= $instalacion['posicion'] ?> - <?= $instalacion['codigo_interno'] ?>
                                        (<?= $instalacion['marca_nombre'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fecha_medicion" class="form-label">Fecha de Medición *</label>
                                <input type="date" class="form-control" id="fecha_medicion" name="fecha_medicion"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cocada_actual" class="form-label">Cocada Actual (mm) *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cocada_actual" name="cocada_actual"
                                        step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">mm</span>
                                </div>
                                <div class="form-text">
                                    <small id="cocadaAnterior" class="text-muted"></small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="horas_trabajadas" class="form-label">Horas Trabajadas *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="horas_trabajadas"
                                        name="horas_trabajadas" min="0" required>
                                    <span class="input-group-text">hrs</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                    placeholder="Observaciones sobre el estado del neumático..."></textarea>
                            </div>
                        </div>

                        <!-- Información calculada -->
                        <div class="alert alert-info" id="infoCalculada" style="display: none;">
                            <h6><i class="bi bi-calculator"></i> Información Calculada:</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Desgaste Semanal:</strong><br>
                                    <span id="desgasteSemanal">-</span> mm
                                </div>
                                <div class="col-md-4">
                                    <strong>% Desgaste Total:</strong><br>
                                    <span id="porcentajeDesgaste">-</span>%
                                </div>
                                <div class="col-md-4">
                                    <strong>Proyección:</strong><br>
                                    <span id="proyeccion">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Registrar Seguimiento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
    // Variables globales para gráficos
    let desgasteChart, posicionChart;

    $(document).ready(function() {
        // Inicializar gráficos
        initCharts();

        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });

    function cambiarEquipo(equipoId) {
        window.location.href = `seguimiento.php?equipo=${equipoId}`;
    }

    function abrirSeguimiento(instalacionId) {
        $('#instalacion_id').val(instalacionId);
        $('#instalacion_id').trigger('change');
        $('#seguimientoModal').modal('show');
    }

    // Calcular información cuando cambian los valores
    $('#instalacion_id, #cocada_actual').on('change input', function() {
        const instalacionSelect = $('#instalacion_id');
        const cocadaActual = parseFloat($('#cocada_actual').val());

        if (instalacionSelect.val() && !isNaN(cocadaActual)) {
            const cocadaAnterior = parseFloat(instalacionSelect.find('option:selected').data('cocada'));

            if (cocadaAnterior) {
                $('#cocadaAnterior').text(`Cocada anterior: ${cocadaAnterior} mm`);

                const desgasteSemanal = cocadaAnterior - cocadaActual;
                const porcentajeDesgaste = ((100 - cocadaActual) / 100) * 100;

                $('#desgasteSemanal').text(desgasteSemanal.toFixed(1));
                $('#porcentajeDesgaste').text(porcentajeDesgaste.toFixed(1));

                // Determinar proyección según modelo 30-30-30
                let proyeccion = 'Normal';
                if (porcentajeDesgaste >= 30) {
                    proyeccion = 'Requiere rotación';
                } else if (porcentajeDesgaste >= 25) {
                    proyeccion = 'Próximo a rotación';
                }
                $('#proyeccion').text(proyeccion);

                $('#infoCalculada').show();
            }
        } else {
            $('#infoCalculada').hide();
            $('#cocadaAnterior').text('');
        }
    });

    // Form validation
    $('#seguimientoForm').on('submit', function(e) {
        const instalacion = $('#instalacion_id').val();
        const fecha = $('#fecha_medicion').val();
        const cocada = $('#cocada_actual').val();
        const horas = $('#horas_trabajadas').val();

        if (!instalacion || !fecha || !cocada || !horas) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios');
            return false;
        }

        if (parseFloat(cocada) <= 0 || parseFloat(horas) < 0) {
            e.preventDefault();
            alert('Los valores de cocada y horas deben ser válidos');
            return false;
        }

        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Registrando...').prop(
            'disabled', true);
    });

    function initCharts() {
        // Datos para los gráficos desde PHP
        const graficosData = <?= json_encode($graficos_data) ?>;

        // Procesar datos para gráfico de desgaste
        const fechas = [...new Set(graficosData.map(item => item.fecha_medicion))].sort();
        const posiciones = [...new Set(graficosData.map(item => item.posicion))].sort();

        const datasets = posiciones.map(pos => {
            const data = fechas.map(fecha => {
                const registro = graficosData.find(item =>
                    item.posicion == pos && item.fecha_medicion === fecha
                );
                return registro ? registro.porcentaje_desgaste : null;
            });

            return {
                label: `Posición ${pos}`,
                data: data,
                borderColor: getColorForPosition(pos),
                backgroundColor: getColorForPosition(pos, 0.1),
                tension: 0.4,
                fill: false
            };
        });

        // Gráfico de evolución del desgaste
        const ctxDesgaste = document.getElementById('desgasteChart');
        if (ctxDesgaste) {
            desgasteChart = new Chart(ctxDesgaste, {
                type: 'line',
                data: {
                    labels: fechas.map(fecha => new Date(fecha).toLocaleDateString('es-ES')),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolución del Desgaste por Posición'
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
                                text: 'Fecha'
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de distribución por posición
        const instalaciones = <?= json_encode($instalaciones) ?>;
        const posicionLabels = instalaciones.map(inst => `Pos. ${inst.posicion}`);
        const posicionData = instalaciones.map(inst => inst.porcentaje_desgaste);

        const ctxPosicion = document.getElementById('posicionChart');
        if (ctxPosicion) {
            posicionChart = new Chart(ctxPosicion, {
                type: 'doughnut',
                data: {
                    labels: posicionLabels,
                    datasets: [{
                        data: posicionData,
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Desgaste Actual por Posición'
                        },
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.toFixed(1) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    function getColorForPosition(position, alpha = 1) {
        const colors = {
            1: `rgba(255, 99, 132, ${alpha})`, // Rojo
            2: `rgba(54, 162, 235, ${alpha})`, // Azul
            3: `rgba(255, 205, 86, ${alpha})`, // Amarillo
            4: `rgba(75, 192, 192, ${alpha})`, // Verde agua
            5: `rgba(153, 102, 255, ${alpha})`, // Púrpura
            6: `rgba(255, 159, 64, ${alpha})` // Naranja
        };
        return colors[position] || `rgba(128, 128, 128, ${alpha})`;
    }

    // Reset form when modal is hidden
    $('#seguimientoModal').on('hidden.bs.modal', function() {
        $('#seguimientoForm')[0].reset();
        $('#fecha_medicion').val('<?= date('Y-m-d') ?>');
        $('#infoCalculada').hide();
        $('#cocadaAnterior').text('');
    });
    </script>
</body>

</html>