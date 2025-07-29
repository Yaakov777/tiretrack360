<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Obtener estadísticas del dashboard
$stats = [];

// Total de neumáticos por estado
$stmt = $db->query("
    SELECT estado, COUNT(*) as total 
    FROM neumaticos 
    GROUP BY estado
");
$stats['neumaticos'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Alertas pendientes
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM alertas 
    WHERE estado = 'pendiente'
");
$stats['alertas_pendientes'] = $stmt->fetchColumn();

// Equipos activos
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM equipos 
    WHERE activo = 1
");
$stats['equipos_activos'] = $stmt->fetchColumn();

// Valor total del inventario
$stmt = $db->query("
    SELECT SUM(costo_nuevo * (remanente_nuevo / 100)) as valor_total
    FROM neumaticos 
    WHERE estado IN ('inventario', 'instalado')
");
$stats['valor_inventario'] = $stmt->fetchColumn() ?: 0;

// Alertas recientes por prioridad
$stmt = $db->query("
    SELECT a.*, e.codigo as equipo_codigo, n.codigo_interno, i.posicion
    FROM alertas a
    JOIN instalaciones i ON a.instalacion_id = i.id
    JOIN equipos e ON i.equipo_id = e.id
    JOIN neumaticos n ON i.neumatico_id = n.id
    WHERE a.estado = 'pendiente'
    ORDER BY FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'), a.fecha_alerta DESC
    LIMIT 10
");
$alertas_recientes = $stmt->fetchAll();

// Neumáticos próximos a rotación (>25%)
$stmt = $db->query("
    SELECT n.codigo_interno, e.codigo as equipo_codigo, i.posicion,
           COALESCE(MAX(ss.porcentaje_desgaste), 0) as desgaste
    FROM neumaticos n
    JOIN instalaciones i ON n.id = i.neumatico_id AND i.activo = 1
    JOIN equipos e ON i.equipo_id = e.id
    LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
    WHERE n.estado = 'instalado'
    GROUP BY n.id
    HAVING desgaste >= 25 AND desgaste < 30
    ORDER BY desgaste DESC
    LIMIT 10
");
$proximas_rotaciones = $stmt->fetchAll();

// Rendimiento por marca
$stmt = $db->query("
    SELECT m.nombre as marca, 
           COUNT(n.id) as total_neumaticos,
           AVG(COALESCE(ss.porcentaje_desgaste, 0)) as desgaste_promedio,
           AVG(n.costo_nuevo) as costo_promedio
    FROM marcas m
    LEFT JOIN neumaticos n ON m.id = n.marca_id
    LEFT JOIN instalaciones i ON n.id = i.neumatico_id AND i.activo = 1
    LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
    WHERE m.activo = 1
    GROUP BY m.id
    ORDER BY total_neumaticos DESC
");
$rendimiento_marcas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Neumáticos - Dashboard</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Bootstrap 5 CDN -->



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

    .stat-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .alert-priority-critica {
        border-left: 5px solid #dc3545;
    }

    .alert-priority-alta {
        border-left: 5px solid #fd7e14;
    }

    .alert-priority-media {
        border-left: 5px solid #0dcaf0;
    }

    .alert-priority-baja {
        border-left: 5px solid #198754;
    }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">

            <?php include 'sidebar.php'; ?>
            <!-- Main Content -->
            <main id="mainContent" class="col main-content p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Dashboard</h1>
                    <div class="text-muted">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i') ?>
                    </div>
                </div>

                <!-- Estadísticas principales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Inventario</h6>
                                        <h3 class="mb-0"><?= $stats['neumaticos']['inventario'] ?? 0 ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box h2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Instalados</h6>
                                        <h3 class="mb-0"><?= $stats['neumaticos']['instalado'] ?? 0 ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle h2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Alertas</h6>
                                        <h3 class="mb-0"><?= $stats['alertas_pendientes'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle h2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Valor Total</h6>
                                        <h3 class="mb-0"><?= formatCurrency($stats['valor_inventario']) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <span class="h2" style="font-family:inherit; font-weight: bold;">S/</span>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos y datos -->
                <div class="row">
                    <!-- Alertas Recientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-exclamation-triangle text-warning"></i>
                                    Alertas Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($alertas_recientes)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-check-circle h1"></i>
                                    <p class="mb-0">No hay alertas pendientes</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($alertas_recientes as $alerta): ?>
                                    <div class="list-group-item alert-priority-<?= $alerta['prioridad'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1"><?= $alerta['equipo_codigo'] ?> - Pos.
                                                    <?= $alerta['posicion'] ?></h6>
                                                <p class="mb-1 small"><?= $alerta['descripcion'] ?></p>
                                                <small
                                                    class="text-muted"><?= formatDate($alerta['fecha_alerta']) ?></small>
                                            </div>
                                            <span class="badge bg-<?= getPrioridadColor($alerta['prioridad']) ?>">
                                                <?= ucfirst($alerta['prioridad']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="alertas.php" class="btn btn-outline-primary btn-sm">
                                        Ver todas las alertas
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Próximas Rotaciones -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-arrow-repeat text-info"></i>
                                    Próximas Rotaciones
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($proximas_rotaciones)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-check-circle h1"></i>
                                    <p class="mb-0">No hay rotaciones próximas</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($proximas_rotaciones as $rotacion): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= $rotacion['codigo_interno'] ?></h6>
                                                <small class="text-muted">
                                                    <?= $rotacion['equipo_codigo'] ?> - Posición
                                                    <?= $rotacion['posicion'] ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning">
                                                    <?= number_format($rotacion['desgaste'], 1) ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rendimiento por Marca -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-bar-chart text-success"></i>
                                    Rendimiento por Marca
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Marca</th>
                                                <th class="text-center">Total Neumáticos</th>
                                                <th class="text-center">Desgaste Promedio</th>
                                                <th class="text-end">Costo Promedio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rendimiento_marcas as $marca): ?>
                                            <tr>
                                                <td class="fw-bold"><?= $marca['marca'] ?></td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-primary"><?= $marca['total_neumaticos'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php $desgaste = $marca['desgaste_promedio']; ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?= $desgaste > 70 ? 'danger' : ($desgaste > 30 ? 'warning' : 'success') ?>"
                                                            style="width: <?= min($desgaste, 100) ?>%">
                                                            <?= number_format($desgaste, 1) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <?= formatCurrency($marca['costo_promedio']) ?>
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
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
    $(document).ready(function() {
        // Actualizar dashboard cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000);

        // Tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>

</html>