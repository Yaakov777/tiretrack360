<?php
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Procesamiento de acciones
if ($_POST) {
    try {
        if ($_POST['action'] === 'marcar_revisada') {
            $stmt = $db->query("
                UPDATE alertas SET 
                    estado = 'revisada',
                    updated_at = NOW()
                WHERE id = ?
            ", [$_POST['alerta_id']]);

            $success = "Alerta marcada como revisada";
        }

        if ($_POST['action'] === 'resolver') {
            $stmt = $db->query("
                UPDATE alertas SET 
                    estado = 'resuelta',
                    updated_at = NOW()
                WHERE id = ?
            ", [$_POST['alerta_id']]);

            $success = "Alerta resuelta exitosamente";
        }

        if ($_POST['action'] === 'crear_alerta') {
            $stmt = $db->query("
                INSERT INTO alertas (
                    instalacion_id, tipo_alerta, descripcion, 
                    fecha_alerta, prioridad
                ) VALUES (?, ?, ?, ?, ?)
            ", [
                $_POST['instalacion_id'],
                $_POST['tipo_alerta'],
                $_POST['descripcion'],
                $_POST['fecha_alerta'],
                $_POST['prioridad']
            ]);

            $success = "Alerta creada exitosamente";
        }

        if ($_POST['action'] === 'marcar_multiple') {
            $alertas_ids = $_POST['alertas_ids'] ?? [];
            $nuevo_estado = $_POST['nuevo_estado'];

            if (!empty($alertas_ids)) {
                $placeholders = str_repeat('?,', count($alertas_ids) - 1) . '?';
                $stmt = $db->query("
                    UPDATE alertas SET 
                        estado = ?,
                        updated_at = NOW()
                    WHERE id IN ($placeholders)
                ", array_merge([$nuevo_estado], $alertas_ids));

                $success = count($alertas_ids) . " alertas actualizadas a estado: " . $nuevo_estado;
            }
        }

        if ($_POST['action'] === 'configurar_alertas') {
            // Guardar configuración de alertas automáticas
            $config = [
                'rotacion_30_activa' => isset($_POST['rotacion_30_activa']),
                'desgaste_limite_activa' => isset($_POST['desgaste_limite_activa']),
                'mantenimiento_activa' => isset($_POST['mantenimiento_activa']),
                'limite_desgaste_critico' => $_POST['limite_desgaste_critico'],
                'dias_sin_seguimiento' => $_POST['dias_sin_seguimiento'],
                'horas_limite_garantia' => $_POST['horas_limite_garantia']
            ];

            // En una implementación real, guardarías esto en una tabla de configuración
            // Por ahora simulamos el guardado
            $success = "Configuración de alertas actualizada";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filtros
$where_conditions = ["1=1"];
$params = [];

if (!empty($_GET['estado'])) {
    $where_conditions[] = "a.estado = ?";
    $params[] = $_GET['estado'];
} else {
    // Por defecto mostrar solo pendientes y revisadas
    $where_conditions[] = "a.estado IN ('pendiente', 'revisada')";
}

if (!empty($_GET['prioridad'])) {
    $where_conditions[] = "a.prioridad = ?";
    $params[] = $_GET['prioridad'];
}

if (!empty($_GET['tipo'])) {
    $where_conditions[] = "a.tipo_alerta = ?";
    $params[] = $_GET['tipo'];
}

if (!empty($_GET['equipo'])) {
    $where_conditions[] = "i.equipo_id = ?";
    $params[] = $_GET['equipo'];
}

if (!empty($_GET['fecha_desde'])) {
    $where_conditions[] = "a.fecha_alerta >= ?";
    $params[] = $_GET['fecha_desde'];
}

if (!empty($_GET['fecha_hasta'])) {
    $where_conditions[] = "a.fecha_alerta <= ?";
    $params[] = $_GET['fecha_hasta'];
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener alertas con información completa
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM alertas a
    JOIN instalaciones i ON a.instalacion_id = i.id
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN equipos e ON i.equipo_id = e.id
    WHERE $where_clause
", $params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$stmt = $db->query("
    SELECT a.*, n.codigo_interno, e.codigo as equipo_codigo, e.nombre as equipo_nombre,
           i.posicion, m.nombre as marca_nombre, d.nombre as diseno_nombre,
           COALESCE(
               (SELECT ss.porcentaje_desgaste
                FROM seguimiento_semanal ss 
                WHERE ss.instalacion_id = i.id 
                ORDER BY ss.fecha_medicion DESC 
                LIMIT 1), 
               0
           ) as desgaste_actual,
           COALESCE(
               (SELECT ss.cocada_actual
                FROM seguimiento_semanal ss 
                WHERE ss.instalacion_id = i.id 
                ORDER BY ss.fecha_medicion DESC 
                LIMIT 1), 
               i.cocada_inicial
           ) as cocada_actual,
           DATEDIFF(CURDATE(), a.fecha_alerta) as dias_pendiente,
           CASE 
               WHEN a.estado = 'pendiente' AND DATEDIFF(CURDATE(), a.fecha_alerta) > 7 THEN 1
               ELSE 0
           END as es_urgente
    FROM alertas a
    JOIN instalaciones i ON a.instalacion_id = i.id
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN equipos e ON i.equipo_id = e.id
    JOIN marcas m ON n.marca_id = m.id
    JOIN disenos d ON n.diseno_id = d.id
    WHERE $where_clause
    ORDER BY 
        CASE WHEN a.estado = 'pendiente' THEN 1 ELSE 2 END,
        FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'),
        a.fecha_alerta DESC
    LIMIT $per_page OFFSET $offset
", $params);
$alertas = $stmt->fetchAll();

// Estadísticas de alertas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_alertas,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'revisada' THEN 1 END) as revisadas,
        COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas,
        COUNT(CASE WHEN prioridad = 'critica' AND estado = 'pendiente' THEN 1 END) as criticas_pendientes,
        COUNT(CASE WHEN prioridad = 'alta' AND estado = 'pendiente' THEN 1 END) as altas_pendientes,
        AVG(CASE WHEN estado = 'resuelta' THEN DATEDIFF(updated_at, created_at) END) as tiempo_resolucion_promedio
    FROM alertas
    WHERE fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$estadisticas = $stmt->fetch();

// Obtener equipos para filtros
$equipos = $db->query("
    SELECT DISTINCT e.id, e.codigo, e.nombre
    FROM equipos e
    JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
    ORDER BY e.codigo
")->fetchAll();

// Obtener instalaciones activas para crear nuevas alertas
$instalaciones_activas = $db->query("
    SELECT i.id, i.posicion, n.codigo_interno, e.codigo as equipo_codigo
    FROM instalaciones i
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN equipos e ON i.equipo_id = e.id
    WHERE i.activo = 1
    ORDER BY e.codigo, i.posicion
")->fetchAll();

// Obtener alertas por tipo para gráficos
$stmt = $db->query("
    SELECT tipo_alerta, COUNT(*) as cantidad
    FROM alertas
    WHERE fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY tipo_alerta
");
$alertas_por_tipo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Tendencia de alertas de los últimos 7 días
$stmt = $db->query("
    SELECT DATE(fecha_alerta) as fecha, COUNT(*) as cantidad
    FROM alertas
    WHERE fecha_alerta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_alerta)
    ORDER BY fecha
");
$tendencia_alertas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Alertas - TireSystem</title>

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

        .search-box {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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

        .alerta-row {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .alerta-row:hover {
            background-color: rgba(0, 123, 255, 0.05);
            transform: translateX(2px);
        }

        .alerta-critica {
            border-left: 4px solid #dc3545;
        }

        .alerta-alta {
            border-left: 4px solid #fd7e14;
        }

        .alerta-media {
            border-left: 4px solid #0dcaf0;
        }

        .alerta-baja {
            border-left: 4px solid #198754;
        }

        .urgente {
            animation: pulseRed 2s infinite;
        }

        @keyframes pulseRed {
            0% {
                background-color: transparent;
            }

            50% {
                background-color: rgba(220, 53, 69, 0.1);
            }

            100% {
                background-color: transparent;
            }
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            min-width: 18px;
            height: 18px;
            font-size: 11px;
            line-height: 18px;
            border-radius: 9px;
        }

        .filter-pills .nav-link {
            border-radius: 20px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .filter-pills .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .alerta-timeline {
            position: relative;
            padding-left: 30px;
        }

        .alerta-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -19px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }

        .timeline-item.critica::before {
            background: #dc3545;
        }

        .timeline-item.alta::before {
            background: #fd7e14;
        }

        .timeline-item.media::before {
            background: #0dcaf0;
        }

        .timeline-item.baja::before {
            background: #198754;
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
                        <a class="nav-link" href="seguimiento.php">
                            <i class="bi bi-graph-up"></i> Seguimiento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="alertas.php">
                            <i class="bi bi-exclamation-triangle"></i> Alertas
                            <?php if ($estadisticas['pendientes'] > 0): ?>
                                <span class="badge bg-danger notification-badge"><?= $estadisticas['pendientes'] ?></span>
                            <?php endif; ?>
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
                        <i class="bi bi-exclamation-triangle text-warning"></i> Sistema de Alertas
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaAlertaModal">
                            <i class="bi bi-plus-lg"></i> Nueva Alerta
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#configuracionModal">
                            <i class="bi bi-gear"></i> Configuración
                        </button>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-tools"></i> Acciones
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="marcarTodasRevisadas()">
                                        <i class="bi bi-check-all"></i> Marcar Todas Revisadas
                                    </a></li>
                                <li><a class="dropdown-item" href="#" onclick="generarReporteAlertas()">
                                        <i class="bi bi-file-pdf"></i> Generar Reporte
                                    </a></li>
                                <li><a class="dropdown-item" href="#" onclick="ejecutarVerificacionAlertas()">
                                        <i class="bi bi-arrow-clockwise"></i> Verificar Alertas Automáticas
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="#" onclick="exportarAlertas()">
                                        <i class="bi bi-download"></i> Exportar Datos
                                    </a></li>
                            </ul>
                        </div>
                    </div>
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

                <!-- Estadísticas principales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Críticas Pendientes</h6>
                                        <h3 class="mb-0"><?= $estadisticas['criticas_pendientes'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle h2"></i>
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
                                        <h6 class="card-title">Pendientes Total</h6>
                                        <h3 class="mb-0"><?= $estadisticas['pendientes'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock h2"></i>
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
                                        <h6 class="card-title">Resueltas (30d)</h6>
                                        <h3 class="mb-0"><?= $estadisticas['resueltas'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle h2"></i>
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
                                        <h6 class="card-title">Tiempo Res. Prom.</h6>
                                        <h3 class="mb-0"><?= round($estadisticas['tiempo_resolucion_promedio'] ?? 0, 1) ?></h3>
                                        <small class="opacity-75">días</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-speedometer2 h2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros rápidos -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <div class="filter-pills">
                                        <ul class="nav nav-pills">
                                            <li class="nav-item">
                                                <a class="nav-link <?= empty($_GET['estado']) ? 'active' : '' ?>"
                                                    href="alertas.php">
                                                    Activas (<?= $estadisticas['pendientes'] + $estadisticas['revisadas'] ?>)
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= ($_GET['estado'] ?? '') == 'pendiente' ? 'active' : '' ?>"
                                                    href="?estado=pendiente">
                                                    Pendientes (<?= $estadisticas['pendientes'] ?>)
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= ($_GET['prioridad'] ?? '') == 'critica' ? 'active' : '' ?>"
                                                    href="?prioridad=critica">
                                                    Críticas (<?= $estadisticas['criticas_pendientes'] ?>)
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= ($_GET['estado'] ?? '') == 'resuelta' ? 'active' : '' ?>"
                                                    href="?estado=resuelta">
                                                    Resueltas
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#filtrosAvanzados">
                                        <i class="bi bi-funnel"></i> Filtros Avanzados
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros avanzados (colapsable) -->
                <div class="collapse mb-4" id="filtrosAvanzados">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de Alerta</label>
                                    <select class="form-select" name="tipo">
                                        <option value="">Todos los tipos</option>
                                        <option value="rotacion_30" <?= ($_GET['tipo'] ?? '') == 'rotacion_30' ? 'selected' : '' ?>>
                                            Rotación 30%
                                        </option>
                                        <option value="desgaste_limite" <?= ($_GET['tipo'] ?? '') == 'desgaste_limite' ? 'selected' : '' ?>>
                                            Desgaste Límite
                                        </option>
                                        <option value="mantenimiento" <?= ($_GET['tipo'] ?? '') == 'mantenimiento' ? 'selected' : '' ?>>
                                            Mantenimiento
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Equipo</label>
                                    <select class="form-select" name="equipo">
                                        <option value="">Todos los equipos</option>
                                        <?php foreach ($equipos as $equipo): ?>
                                            <option value="<?= $equipo['id'] ?>"
                                                <?= ($_GET['equipo'] ?? '') == $equipo['id'] ? 'selected' : '' ?>>
                                                <?= $equipo['codigo'] ?> - <?= $equipo['nombre'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Desde</label>
                                    <input type="date" class="form-control" name="fecha_desde"
                                        value="<?= $_GET['fecha_desde'] ?? '' ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" class="form-control" name="fecha_hasta"
                                        value="<?= $_GET['fecha_hasta'] ?? '' ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="btn-group w-100">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Filtrar
                                        </button>
                                        <a href="alertas.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de resumen -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-graph-up"></i> Tendencia de Alertas (7 días)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="tendenciaChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-pie-chart"></i> Alertas por Tipo
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="tipoChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de alertas -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Lista de Alertas
                            <span class="badge bg-secondary"><?= $total_records ?></span>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" onclick="seleccionarTodas()">
                                <i class="bi bi-check-square"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="accionesMultiples('revisada')">
                                <i class="bi bi-eye"></i> Marcar Revisadas
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="accionesMultiples('resuelta')">
                                <i class="bi bi-check-circle"></i> Resolver
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($alertas)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success h1"></i>
                                <h4 class="text-muted">No hay alertas</h4>
                                <p class="text-muted">
                                    <?= empty($_GET) ? 'No hay alertas pendientes en este momento' : 'No se encontraron alertas con los filtros aplicados' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Prioridad</th>
                                            <th>Tipo</th>
                                            <th>Equipo/Neumático</th>
                                            <th>Descripción</th>
                                            <th>Estado Actual</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alertas as $alerta): ?>
                                            <tr class="alerta-row alerta-<?= $alerta['prioridad'] ?> <?= $alerta['es_urgente'] ? 'urgente' : '' ?>"
                                                data-alerta-id="<?= $alerta['id'] ?>">
                                                <td>
                                                    <input type="checkbox" class="form-check-input alerta-checkbox"
                                                        value="<?= $alerta['id'] ?>">
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getPrioridadColor($alerta['prioridad']) ?> position-relative">
                                                        <?= ucfirst($alerta['prioridad']) ?>
                                                        <?php if ($alerta['es_urgente']): ?>
                                                            <i class="bi bi-exclamation-triangle-fill position-absolute top-0 start-100 translate-middle text-danger"
                                                                style="font-size: 0.6rem;" title="Urgente - Más de 7 días pendiente"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-<?=
                                                                        $alerta['tipo_alerta'] == 'rotacion_30' ? 'arrow-repeat' : ($alerta['tipo_alerta'] == 'desgaste_limite' ? 'exclamation-triangle' : 'tools')
                                                                        ?> me-2"></i>
                                                        <span class="small">
                                                            <?= $TIPOS_ALERTA[$alerta['tipo_alerta']] ?? $alerta['tipo_alerta'] ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= $alerta['equipo_codigo'] ?></strong> - Pos. <?= $alerta['posicion'] ?><br>
                                                        <small class="text-muted">
                                                            <?= $alerta['codigo_interno'] ?> (<?= $alerta['marca_nombre'] ?>)
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 300px;">
                                                        <?= htmlspecialchars($alerta['descripcion']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <div class="progress mb-1" style="height: 15px;">
                                                            <div class="progress-bar bg-<?=
                                                                                        $alerta['desgaste_actual'] > 70 ? 'danger' : ($alerta['desgaste_actual'] > 30 ? 'warning' : 'success')
                                                                                        ?>"
                                                                style="width: <?= min($alerta['desgaste_actual'], 100) ?>%">
                                                                <?= number_format($alerta['desgaste_actual'], 1) ?>%
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">
                                                            Cocada: <?= number_format($alerta['cocada_actual'], 1) ?>mm
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <div class="fw-bold"><?= formatDate($alerta['fecha_alerta']) ?></div>
                                                        <small class="text-muted">
                                                            <?= $alerta['dias_pendiente'] ?> día(s)
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                                            $alerta['estado'] == 'pendiente' ? 'danger' : ($alerta['estado'] == 'revisada' ? 'warning' : 'success')
                                                                            ?>">
                                                        <?= $ESTADOS_ALERTA[$alerta['estado']] ?? $alerta['estado'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($alerta['estado'] == 'pendiente'): ?>
                                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                                onclick="marcarRevisada(<?= $alerta['id'] ?>)"
                                                                title="Marcar como revisada">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($alerta['estado'] != 'resuelta'): ?>
                                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                                onclick="resolverAlerta(<?= $alerta['id'] ?>)"
                                                                title="Resolver alerta">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                            onclick="verDetalleAlerta(<?= $alerta['id'] ?>)"
                                                            title="Ver detalle">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>

                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                            onclick="irAInstalacion(<?= $alerta['instalacion_id'] ?>)"
                                                            title="Ir a instalación">
                                                            <i class="bi bi-arrow-right"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Paginación de alertas">
                                <ul class="pagination pagination-sm mb-0 justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, function ($k) {
                                                                                                return $k != 'page';
                                                                                            }, ARRAY_FILTER_USE_KEY)) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function ($k) {
                                                                                            return $k != 'page';
                                                                                        }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, function ($k) {
                                                                                                return $k != 'page';
                                                                                            }, ARRAY_FILTER_USE_KEY)) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para nueva alerta -->
    <div class="modal fade" id="nuevaAlertaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="nuevaAlertaForm">
                    <input type="hidden" name="action" value="crear_alerta">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle"></i> Nueva Alerta Manual
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Nota:</strong> El sistema genera alertas automáticamente según el modelo 30-30-30.
                            Use este formulario solo para alertas manuales específicas.
                        </div>

                        <div class="mb-3">
                            <label for="instalacion_id" class="form-label">Instalación *</label>
                            <select class="form-select" id="instalacion_id" name="instalacion_id" required>
                                <option value="">Seleccionar instalación</option>
                                <?php foreach ($instalaciones_activas as $instalacion): ?>
                                    <option value="<?= $instalacion['id'] ?>">
                                        <?= $instalacion['equipo_codigo'] ?> - Pos. <?= $instalacion['posicion'] ?>
                                        (<?= $instalacion['codigo_interno'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tipo_alerta" class="form-label">Tipo de Alerta *</label>
                                <select class="form-select" id="tipo_alerta" name="tipo_alerta" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="rotacion_30">Rotación 30%</option>
                                    <option value="desgaste_limite">Desgaste Límite</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prioridad" class="form-label">Prioridad *</label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="">Seleccionar prioridad</option>
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                    <option value="critica">Crítica</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fecha_alerta" class="form-label">Fecha de Alerta *</label>
                            <input type="date" class="form-control" id="fecha_alerta" name="fecha_alerta"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion"
                                rows="3" required placeholder="Descripción detallada de la alerta..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Crear Alerta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para configuración de alertas -->
    <div class="modal fade" id="configuracionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="configuracionForm">
                    <input type="hidden" name="action" value="configurar_alertas">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-gear"></i> Configuración de Alertas Automáticas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Tipos de Alertas</h6>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="rotacion_30_activa"
                                        name="rotacion_30_activa" checked>
                                    <label class="form-check-label" for="rotacion_30_activa">
                                        <strong>Rotación 30%</strong><br>
                                        <small class="text-muted">Alerta cuando el neumático alcanza 30% de desgaste</small>
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="desgaste_limite_activa"
                                        name="desgaste_limite_activa" checked>
                                    <label class="form-check-label" for="desgaste_limite_activa">
                                        <strong>Desgaste Límite</strong><br>
                                        <small class="text-muted">Alerta cuando se alcanza el límite crítico</small>
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="mantenimiento_activa"
                                        name="mantenimiento_activa" checked>
                                    <label class="form-check-label" for="mantenimiento_activa">
                                        <strong>Mantenimiento</strong><br>
                                        <small class="text-muted">Alertas de mantenimiento preventivo</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-primary">Parámetros</h6>

                                <div class="mb-3">
                                    <label for="limite_desgaste_critico" class="form-label">
                                        Límite Desgaste Crítico (%)
                                    </label>
                                    <input type="number" class="form-control" id="limite_desgaste_critico"
                                        name="limite_desgaste_critico" min="50" max="90" value="70" step="5">
                                    <div class="form-text">Porcentaje para generar alerta crítica</div>
                                </div>

                                <div class="mb-3">
                                    <label for="dias_sin_seguimiento" class="form-label">
                                        Días sin Seguimiento
                                    </label>
                                    <input type="number" class="form-control" id="dias_sin_seguimiento"
                                        name="dias_sin_seguimiento" min="7" max="30" value="14">
                                    <div class="form-text">Días para alerta de falta de seguimiento</div>
                                </div>

                                <div class="mb-3">
                                    <label for="horas_limite_garantia" class="form-label">
                                        % Horas Garantía
                                    </label>
                                    <input type="number" class="form-control" id="horas_limite_garantia"
                                        name="horas_limite_garantia" min="80" max="100" value="90" step="5">
                                    <div class="form-text">% de horas de garantía para alerta</div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle"></i> Frecuencia de Verificación</h6>
                            <p class="mb-0">
                                El sistema verifica automáticamente las condiciones cada vez que se registra
                                seguimiento semanal. También puede ejecutar verificaciones manuales.
                            </p>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-info" onclick="ejecutarVerificacionAlertas()">
                            <i class="bi bi-play-circle"></i> Verificar Ahora
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para detalle de alerta -->
    <div class="modal fade" id="detalleAlertaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle"></i> Detalle de Alerta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleAlertaContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {
            // Auto-dismiss alerts
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Inicializar gráficos
            initCharts();

            // Auto-refresh cada 2 minutos para alertas críticas
            setInterval(function() {
                if ($('.alerta-critica').length > 0) {
                    location.reload();
                }
            }, 120000);
        });

        // Inicializar gráficos
        function initCharts() {
            // Gráfico de tendencia
            const tendenciaData = <?= json_encode($tendencia_alertas) ?>;
            const ctxTendencia = document.getElementById('tendenciaChart');

            if (ctxTendencia && tendenciaData.length > 0) {
                new Chart(ctxTendencia, {
                    type: 'line',
                    data: {
                        labels: tendenciaData.map(item => new Date(item.fecha).toLocaleDateString('es-ES')),
                        datasets: [{
                            label: 'Alertas Generadas',
                            data: tendenciaData.map(item => item.cantidad),
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Alertas Generadas por Día'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Cantidad'
                                }
                            }
                        }
                    }
                });
            }

            // Gráfico de tipos
            const tiposData = <?= json_encode($alertas_por_tipo) ?>;
            const ctxTipos = document.getElementById('tipoChart');

            if (ctxTipos && Object.keys(tiposData).length > 0) {
                new Chart(ctxTipos, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(tiposData).map(tipo => {
                            const tipos = {
                                'rotacion_30': 'Rotación 30%',
                                'desgaste_limite': 'Desgaste Límite',
                                'mantenimiento': 'Mantenimiento'
                            };
                            return tipos[tipo] || tipo;
                        }),
                        datasets: [{
                            data: Object.values(tiposData),
                            backgroundColor: [
                                '#FF6384',
                                '#36A2EB',
                                '#FFCE56',
                                '#4BC0C0'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }

        // Marcar alerta como revisada
        function marcarRevisada(alertaId) {
            if (confirm('¿Marcar esta alerta como revisada?')) {
                $.post('alertas.php', {
                    action: 'marcar_revisada',
                    alerta_id: alertaId
                }, function() {
                    location.reload();
                });
            }
        }

        // Resolver alerta
        function resolverAlerta(alertaId) {
            if (confirm('¿Marcar esta alerta como resuelta?')) {
                $.post('alertas.php', {
                    action: 'resolver',
                    alerta_id: alertaId
                }, function() {
                    location.reload();
                });
            }
        }

        // Ver detalle de alerta
        function verDetalleAlerta(alertaId) {
            $('#detalleAlertaModal').modal('show');

            $.ajax({
                url: 'api/detalle_alerta.php',
                method: 'GET',
                data: {
                    id: alertaId
                },
                success: function(response) {
                    $('#detalleAlertaContent').html(response);
                },
                error: function() {
                    $('#detalleAlertaContent').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Error al cargar el detalle de la alerta
                        </div>
                    `);
                }
            });
        }

        // Ir a instalación
        function irAInstalacion(instalacionId) {
            window.location.href = `instalaciones.php?instalacion=${instalacionId}`;
        }

        // Seleccionar todas las alertas
        function seleccionarTodas() {
            const checkboxes = document.querySelectorAll('.alerta-checkbox');
            const selectAll = document.getElementById('selectAll');

            checkboxes.forEach(checkbox => {
                checkbox.checked = !selectAll.checked;
            });

            selectAll.checked = !selectAll.checked;
        }

        // Toggle select all
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.alerta-checkbox');
            const selectAll = document.getElementById('selectAll');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Acciones múltiples
        function accionesMultiples(accion) {
            const alertasSeleccionadas = [];
            document.querySelectorAll('.alerta-checkbox:checked').forEach(checkbox => {
                alertasSeleccionadas.push(checkbox.value);
            });

            if (alertasSeleccionadas.length === 0) {
                alert('Seleccione al menos una alerta');
                return;
            }

            const mensaje = accion === 'revisada' ?
                `¿Marcar ${alertasSeleccionadas.length} alerta(s) como revisadas?` :
                `¿Resolver ${alertasSeleccionadas.length} alerta(s)?`;

            if (confirm(mensaje)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="marcar_multiple">
                    <input type="hidden" name="nuevo_estado" value="${accion}">
                    ${alertasSeleccionadas.map(id => 
                        `<input type="hidden" name="alertas_ids[]" value="${id}">`
                    ).join('')}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Ejecutar verificación de alertas
        function ejecutarVerificacionAlertas() {
            if (confirm('¿Ejecutar verificación automática de alertas?')) {
                $.ajax({
                    url: 'api/verificar_alertas.php',
                    method: 'POST',
                    success: function(response) {
                        if (response.success) {
                            alert(`Verificación completada. ${response.alertas_generadas} nuevas alertas generadas.`);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error al ejecutar la verificación');
                    }
                });
            }
        }

        // Otras funciones de utilidad
        function marcarTodasRevisadas() {
            if (confirm('¿Marcar TODAS las alertas pendientes como revisadas?')) {
                window.location.href = 'api/marcar_todas_revisadas.php';
            }
        }

        function generarReporteAlertas() {
            const params = new URLSearchParams(window.location.search);
            window.open(`api/reporte_alertas.php?${params.toString()}`, '_blank');
        }

        function exportarAlertas() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `api/exportar_alertas.php?${params.toString()}`;
        }

        // Form validation
        $('#nuevaAlertaForm').on('submit', function(e) {
            const instalacion = $('#instalacion_id').val();
            const tipo = $('#tipo_alerta').val();
            const prioridad = $('#prioridad').val();
            const descripcion = $('#descripcion').val().trim();

            if (!instalacion || !tipo || !prioridad || !descripcion) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios');
                return false;
            }

            // Show loading state
            $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Creando...').prop('disabled', true);
        });

        // Destacar alertas urgentes
        function destacarAlertasUrgentes() {
            $('.urgente').each(function() {
                $(this).addClass('table-warning');
            });
        }

        // Sonido de notificación para alertas críticas (opcional)
        function notificarAlertasCriticas() {
            const criticas = $('.alerta-critica').length;
            if (criticas > 0) {
                // Aquí podrías agregar un sonido de notificación
                console.log(`${criticas} alertas críticas pendientes`);
            }
        }

        // Actualización en tiempo real del contador
        function actualizarContadores() {
            const pendientes = $('.alerta-row').filter('[data-estado="pendiente"]').length;
            const criticas = $('.alerta-critica').length;

            // Actualizar badges en sidebar si es necesario
            const sidebarBadge = $('.sidebar .notification-badge');
            if (sidebarBadge.length && pendientes > 0) {
                sidebarBadge.text(pendientes);
            }
        }

        // Ejecutar funciones al cargar
        destacarAlertasUrgentes();
        notificarAlertasCriticas();
        actualizarContadores();

        // Hover effects para filas de alertas
        $('.alerta-row').hover(
            function() {
                $(this).addClass('table-active');
            },
            function() {
                $(this).removeClass('table-active');
            }
        );

        // Auto-save de filtros en localStorage
        $('form[method="GET"]').on('change', 'select, input', function() {
            const formData = $(this).closest('form').serialize();
            localStorage.setItem('alertas_filtros', formData);
        });

        // Restaurar filtros guardados
        $(document).ready(function() {
            const savedFilters = localStorage.getItem('alertas_filtros');
            if (savedFilters && window.location.search === '') {
                // Opcional: restaurar filtros automáticamente
            }
        });

        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + N = Nueva alerta
            if (e.ctrlKey && e.which === 78) {
                e.preventDefault();
                $('#nuevaAlertaModal').modal('show');
            }

            // Ctrl + R = Reload/Refresh
            if (e.ctrlKey && e.which === 82) {
                e.preventDefault();
                location.reload();
            }

            // Escape = Cerrar modales
            if (e.which === 27) {
                $('.modal').modal('hide');
            }
        });

        // Tooltip initialization
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Initialize tooltips for dynamically added content
        $(document).on('mouseenter', '[title]', function() {
            $(this).tooltip('show');
        });
    </script>
    <style>
        /* Estilos adicionales para mejor UX */
        .alerta-row.table-active {
            background-color: rgba(13, 110, 253, 0.075) !important;
        }

        .notification-badge {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            20%,
            53%,
            80%,
            100% {
                transform: translate3d(0, 0, 0);
            }

            40%,
            43% {
                transform: translate3d(0, -8px, 0);
            }

            70% {
                transform: translate3d(0, -4px, 0);
            }

            90% {
                transform: translate3d(0, -2px, 0);
            }
        }

        /* Estilos para impresión */
        @media print {

            .sidebar,
            .btn,
            .dropdown,
            .modal {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }

            .alerta-critica {
                background-color: #f8d7da !important;
            }

            .alerta-alta {
                background-color: #fff3cd !important;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }

            .btn-group-sm .btn {
                padding: 0.125rem 0.25rem;
                font-size: 0.75rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .alerta-row td {
                padding: 0.5rem 0.25rem;
            }
        }

        /* Dark mode support (opcional) */
        @media (prefers-color-scheme: dark) {
            .card {
                background-color: #2d3748;
                border-color: #4a5568;
            }

            .table {
                color: #e2e8f0;
            }

            .table-light {
                background-color: #4a5568;
                border-color: #718096;
            }
        }

        /* Animaciones suaves */
        .card,
        .btn,
        .badge {
            transition: all 0.3s ease;
        }

        .alerta-row {
            transition: all 0.2s ease;
        }

        /* Loading states */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Custom scrollbar */
        .modal-body {
            scrollbar-width: thin;
            scrollbar-color: #6c757d #f8f9fa;
        }

        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</body>

</html>