<?php
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Procesamiento de acciones
if ($_POST) {
    try {
        if ($_POST['action'] === 'instalar') {
            $db->beginTransaction();

            // Verificar que la posición esté libre
            $stmt = $db->query("
                SELECT i.id, n.codigo_interno 
                FROM instalaciones i 
                JOIN neumaticos n ON i.neumatico_id = n.id
                WHERE i.equipo_id = ? AND i.posicion = ? AND i.activo = 1
            ", [$_POST['equipo_id'], $_POST['posicion']]);

            if ($stmt->fetch()) {
                throw new Exception("La posición ya está ocupada");
            }

            // Verificar que el neumático esté disponible
            $stmt = $db->query("
                SELECT estado FROM neumaticos WHERE id = ?
            ", [$_POST['neumatico_id']]);
            $neumatico = $stmt->fetch();

            if (!$neumatico || $neumatico['estado'] !== 'inventario') {
                throw new Exception("El neumático no está disponible para instalación");
            }

            // Llamar al procedimiento almacenado para instalar
            $stmt = $db->query("
                CALL sp_instalar_neumatico(?, ?, ?, ?, ?, ?, ?)
            ", [
                $_POST['neumatico_id'],
                $_POST['equipo_id'],
                $_POST['posicion'],
                $_POST['fecha_instalacion'],
                $_POST['horometro_instalacion'],
                $_POST['cocada_inicial'],
                $_POST['observaciones']
            ]);

            $db->commit();
            $success = "Neumático instalado exitosamente";
        }

        if ($_POST['action'] === 'rotar') {
            $db->beginTransaction();

            // Obtener instalaciones origen y destino
            $stmt = $db->query("
                SELECT i.*, n.codigo_interno 
                FROM instalaciones i 
                JOIN neumaticos n ON i.neumatico_id = n.id
                WHERE i.id IN (?, ?) AND i.activo = 1
            ", [$_POST['instalacion_origen'], $_POST['instalacion_destino']]);
            $instalaciones = $stmt->fetchAll();

            if (count($instalaciones) !== 2) {
                throw new Exception("No se encontraron las instalaciones para rotación");
            }

            // Intercambiar posiciones
            foreach ($instalaciones as $instalacion) {
                if ($instalacion['id'] == $_POST['instalacion_origen']) {
                    $origen = $instalacion;
                } else {
                    $destino = $instalacion;
                }
            }

            // Desactivar instalaciones actuales
            $db->query(
                "UPDATE instalaciones SET activo = 0 WHERE id IN (?, ?)",
                [$origen['id'], $destino['id']]
            );

            // Crear nuevas instalaciones con posiciones intercambiadas
            $stmt = $db->query("
                INSERT INTO instalaciones (
                    neumatico_id, equipo_id, posicion, fecha_instalacion,
                    horometro_instalacion, cocada_inicial, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $origen['neumatico_id'],
                $origen['equipo_id'],
                $destino['posicion'],
                $_POST['fecha_rotacion'],
                $_POST['horometro_rotacion'],
                $_POST['cocada_origen'],
                $_POST['motivo_rotacion']
            ]);

            $stmt = $db->query("
                INSERT INTO instalaciones (
                    neumatico_id, equipo_id, posicion, fecha_instalacion,
                    horometro_instalacion, cocada_inicial, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $destino['neumatico_id'],
                $destino['equipo_id'],
                $origen['posicion'],
                $_POST['fecha_rotacion'],
                $_POST['horometro_rotacion'],
                $_POST['cocada_destino'],
                $_POST['motivo_rotacion']
            ]);

            // Registrar movimientos
            $db->query("
                INSERT INTO movimientos (
                    neumatico_id, equipo_origen_id, posicion_origen,
                    equipo_destino_id, posicion_destino, fecha_movimiento,
                    horometro_movimiento, tipo_movimiento, motivo, cocada_movimiento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'rotacion', ?, ?)
            ", [
                $origen['neumatico_id'],
                $origen['equipo_id'],
                $origen['posicion'],
                $origen['equipo_id'],
                $destino['posicion'],
                $_POST['fecha_rotacion'],
                $_POST['horometro_rotacion'],
                $_POST['motivo_rotacion'],
                $_POST['cocada_origen']
            ]);

            $db->query("
                INSERT INTO movimientos (
                    neumatico_id, equipo_origen_id, posicion_origen,
                    equipo_destino_id, posicion_destino, fecha_movimiento,
                    horometro_movimiento, tipo_movimiento, motivo, cocada_movimiento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'rotacion', ?, ?)
            ", [
                $destino['neumatico_id'],
                $destino['equipo_id'],
                $destino['posicion'],
                $destino['equipo_id'],
                $origen['posicion'],
                $_POST['fecha_rotacion'],
                $_POST['horometro_rotacion'],
                $_POST['motivo_rotacion'],
                $_POST['cocada_destino']
            ]);

            $db->commit();
            $success = "Rotación realizada exitosamente";
        }

        if ($_POST['action'] === 'retirar') {
            $db->beginTransaction();

            // Desactivar instalación
            $db->query("UPDATE instalaciones SET activo = 0 WHERE id = ?", [$_POST['instalacion_id']]);

            // Actualizar estado del neumático
            $estado_destino = $_POST['destino'] === 'inventario' ? 'inventario' : 'desechado';
            $db->query(
                "UPDATE neumaticos SET estado = ? WHERE id = ?",
                [$estado_destino, $_POST['neumatico_id']]
            );

            // Registrar movimiento
            $stmt = $db->query("
                SELECT equipo_id, posicion FROM instalaciones WHERE id = ?
            ", [$_POST['instalacion_id']]);
            $instalacion = $stmt->fetch();

            $db->query("
                INSERT INTO movimientos (
                    neumatico_id, equipo_origen_id, posicion_origen,
                    fecha_movimiento, horometro_movimiento, tipo_movimiento,
                    motivo, cocada_movimiento
                ) VALUES (?, ?, ?, ?, ?, 'retiro', ?, ?)
            ", [
                $_POST['neumatico_id'],
                $instalacion['equipo_id'],
                $instalacion['posicion'],
                $_POST['fecha_retiro'],
                $_POST['horometro_retiro'],
                $_POST['motivo_retiro'],
                $_POST['cocada_retiro']
            ]);

            // Si es desecho, registrar en tabla de desechos
            if ($_POST['destino'] === 'desecho') {
                $db->query("
                    CALL sp_desechar_neumatico(?, ?, ?, ?, ?)
                ", [
                    $_POST['neumatico_id'],
                    $_POST['fecha_retiro'],
                    $_POST['horometro_retiro'],
                    $_POST['cocada_retiro'],
                    $_POST['motivo_retiro']
                ]);
            }

            $db->commit();
            $success = "Neumático retirado exitosamente";
        }
    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Filtros
$where_conditions = ["i.activo = 1"];
$params = [];

if (!empty($_GET['equipo'])) {
    $where_conditions[] = "i.equipo_id = ?";
    $params[] = $_GET['equipo'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "(e.codigo LIKE ? OR n.codigo_interno LIKE ? OR n.numero_serie LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search, $search, $search]);
}

if (!empty($_GET['marca'])) {
    $where_conditions[] = "m.id = ?";
    $params[] = $_GET['marca'];
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener instalaciones activas con información completa
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM instalaciones i
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN equipos e ON i.equipo_id = e.id
    JOIN marcas m ON n.marca_id = m.id
    WHERE $where_clause
", $params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$stmt = $db->query("
    SELECT i.*, n.codigo_interno, n.numero_serie, n.costo_nuevo,
           e.codigo as equipo_codigo, e.nombre as equipo_nombre,
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
           DATEDIFF(CURDATE(), i.fecha_instalacion) as dias_instalado,
           (SELECT COUNT(*) FROM alertas a WHERE a.instalacion_id = i.id AND a.estado = 'pendiente') as alertas_pendientes
    FROM instalaciones i
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN equipos e ON i.equipo_id = e.id
    JOIN marcas m ON n.marca_id = m.id
    JOIN disenos d ON n.diseno_id = d.id
    JOIN medidas med ON n.medida_id = med.id
    WHERE $where_clause
    ORDER BY e.codigo, i.posicion
    LIMIT $per_page OFFSET $offset
", $params);
$instalaciones = $stmt->fetchAll();

// Obtener datos para formularios
$equipos_disponibles = $db->query("
    SELECT * FROM equipos WHERE activo = 1 ORDER BY codigo
")->fetchAll();

$neumaticos_disponibles = $db->query("
    SELECT n.*, m.nombre as marca_nombre, d.nombre as diseno_nombre, med.medida
    FROM neumaticos n
    JOIN marcas m ON n.marca_id = m.id
    JOIN disenos d ON n.diseno_id = d.id
    JOIN medidas med ON n.medida_id = med.id
    WHERE n.estado = 'inventario'
    ORDER BY n.codigo_interno
")->fetchAll();

$marcas = $db->query("SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Estadísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_instalaciones,
        COUNT(CASE WHEN ss.porcentaje_desgaste >= 30 THEN 1 END) as requieren_rotacion,
        COUNT(CASE WHEN ss.porcentaje_desgaste >= 70 THEN 1 END) as criticos,
        AVG(ss.porcentaje_desgaste) as desgaste_promedio,
        SUM(n.costo_nuevo) as valor_total_instalado
    FROM instalaciones i
    JOIN neumaticos n ON i.neumatico_id = n.id
    LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
    WHERE i.activo = 1
");
$estadisticas = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Instalaciones - TireSystem</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

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

    .table-responsive {
        border-radius: 1rem;
        overflow: hidden;
    }

    .btn-action {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
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

    .posicion-badge {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }

    .desgaste-progress {
        height: 20px;
    }

    .instalacion-row {
        transition: all 0.2s ease;
    }

    .instalacion-row:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .alert-indicator {
        position: relative;
    }

    .alert-indicator::after {
        content: '';
        position: absolute;
        top: -2px;
        right: -2px;
        width: 8px;
        height: 8px;
        background-color: #dc3545;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.2);
            opacity: 0.7;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .position-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        max-width: 200px;
    }

    .position-slot {
        aspect-ratio: 1;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8em;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .position-slot.occupied {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        border-style: solid;
    }

    .position-slot.warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }

    .position-slot.danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .position-slot:hover {
        transform: scale(1.05);
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
                        <a class="nav-link active" href="instalaciones.php">
                            <i class="bi bi-arrow-repeat"></i> Instalaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="seguimiento.php">
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
                        <i class="bi bi-arrow-repeat text-primary"></i> Gestión de Instalaciones
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#instalarModal">
                            <i class="bi bi-plus-lg"></i> Nueva Instalación
                        </button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                            data-bs-target="#rotarModal">
                            <i class="bi bi-arrow-repeat"></i> Rotación
                        </button>
                        <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown">
                            <span class="visually-hidden">Más acciones</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="verVistaEquipos()">
                                    <i class="bi bi-truck"></i> Vista por Equipos
                                </a></li>
                            <li><a class="dropdown-item" href="#" onclick="generarReporteRotaciones()">
                                    <i class="bi bi-file-text"></i> Reporte de Rotaciones
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" onclick="planificarMantenimiento()">
                                    <i class="bi bi-calendar-check"></i> Planificar Mantenimiento
                                </a></li>
                        </ul>
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
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Instalaciones</h6>
                                        <h3 class="mb-0"><?= $estadisticas['total_instalaciones'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-gear h2"></i>
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
                                        <h6 class="card-title">Requieren Rotación</h6>
                                        <h3 class="mb-0"><?= $estadisticas['requieren_rotacion'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-arrow-repeat h2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Estado Crítico</h6>
                                        <h3 class="mb-0"><?= $estadisticas['criticos'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle h2"></i>
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
                                        <h6 class="card-title">Valor Instalado</h6>
                                        <h3 class="mb-0"><?= formatCurrency($estadisticas['valor_total_instalado']) ?>
                                        </h3>
                                    </div>
                                    <div class="align-self-center">
                                        <span class="h2" style="font-family:inherit; font-weight: bold;">S/</span>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y búsqueda -->
                <div class="card search-box mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" name="search"
                                        placeholder="Buscar por equipo o neumático..."
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="equipo">
                                    <option value="">Todos los equipos</option>
                                    <?php foreach ($equipos_disponibles as $equipo): ?>
                                    <option value="<?= $equipo['id'] ?>"
                                        <?= ($_GET['equipo'] ?? '') == $equipo['id'] ? 'selected' : '' ?>>
                                        <?= $equipo['codigo'] ?> - <?= $equipo['nombre'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="marca">
                                    <option value="">Todas las marcas</option>
                                    <?php foreach ($marcas as $marca): ?>
                                    <option value="<?= $marca['id'] ?>"
                                        <?= ($_GET['marca'] ?? '') == $marca['id'] ? 'selected' : '' ?>>
                                        <?= $marca['nombre'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <a href="instalaciones.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de instalaciones -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Instalaciones Activas
                            <span class="badge bg-secondary"><?= $total_records ?></span>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-info" onclick="toggleVistaCompacta()">
                                <i class="bi bi-list"></i> Vista Compacta
                            </button>
                            <button type="button" class="btn btn-outline-success">
                                <i class="bi bi-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="instalacionesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pos.</th>
                                        <th>Equipo</th>
                                        <th>Neumático</th>
                                        <th>Marca/Diseño</th>
                                        <th>Cocada</th>
                                        <th>Desgaste</th>
                                        <th>Días Inst.</th>
                                        <th>Horas</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($instalaciones)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox h1"></i><br>
                                            No se encontraron instalaciones activas
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($instalaciones as $instalacion): ?>
                                    <tr class="instalacion-row" data-instalacion-id="<?= $instalacion['id'] ?>">
                                        <td>
                                            <div class="posicion-badge bg-<?=
                                                                                    $instalacion['posicion'] <= 2 ? 'primary' : ($instalacion['posicion'] <= 4 ? 'info' : 'secondary')
                                                                                    ?>">
                                                <?= $instalacion['posicion'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($instalacion['equipo_codigo']) ?></strong><br>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($instalacion['equipo_nombre']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($instalacion['codigo_interno']) ?></strong><br>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($instalacion['numero_serie']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($instalacion['marca_nombre']) ?></strong><br>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($instalacion['diseno_nombre']) ?></small>
                                            <span
                                                class="badge bg-light text-dark"><?= htmlspecialchars($instalacion['medida_nombre']) ?></span>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="fw-bold">
                                                    <?= number_format($instalacion['cocada_actual'], 1) ?></div>
                                                <small class="text-muted">mm</small>
                                                <?php if ($instalacion['cocada_inicial'] != $instalacion['cocada_actual']): ?>
                                                <br><small class="text-info">Inicial:
                                                    <?= number_format($instalacion['cocada_inicial'], 1) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                                    $desgaste = $instalacion['porcentaje_desgaste'];
                                                    $color = $desgaste > 70 ? 'danger' : ($desgaste > 30 ? 'warning' : 'success');
                                                    ?>
                                            <div class="progress desgaste-progress">
                                                <div class="progress-bar bg-<?= $color ?>"
                                                    style="width: <?= min($desgaste, 100) ?>%">
                                                    <?= number_format($desgaste, 1) ?>%
                                                </div>
                                            </div>
                                            <?php if ($desgaste >= 30): ?>
                                            <small class="text-<?= $color ?>">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                <?= $desgaste >= 70 ? 'Crítico' : 'Rotación' ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="fw-bold"><?= $instalacion['dias_instalado'] ?></div>
                                            <small class="text-muted">días</small>
                                            <br><small
                                                class="text-info"><?= formatDate($instalacion['fecha_instalacion']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="fw-bold"><?= number_format($instalacion['horas_acumuladas']) ?>
                                            </div>
                                            <small class="text-muted">hrs</small>
                                            <?php if ($instalacion['horas_acumuladas'] > 0): ?>
                                            <?php $costo_hora = $instalacion['costo_nuevo'] / $instalacion['horas_acumuladas']; ?>
                                            <br><small
                                                class="text-success">S/<?= number_format($costo_hora, 2) ?>/hr</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($instalacion['alertas_pendientes'] > 0): ?>
                                            <span class="badge bg-danger alert-indicator">
                                                <?= $instalacion['alertas_pendientes'] ?> alerta(s)
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-warning btn-action"
                                                    onclick="prepararRotacion(<?= $instalacion['id'] ?>, '<?= $instalacion['codigo_interno'] ?>', <?= $instalacion['posicion'] ?>)"
                                                    title="Rotar neumático">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-action"
                                                    onclick="verHistorial(<?= $instalacion['id'] ?>)"
                                                    title="Ver historial">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-action"
                                                    onclick="prepararRetiro(<?= $instalacion['id'] ?>, <?= $instalacion['neumatico_id'] ?>, '<?= $instalacion['codigo_interno'] ?>')"
                                                    title="Retirar neumático">
                                                    <i class="bi bi-box-arrow-right"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Paginación de instalaciones">
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, function ($k) {
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
                                    <a class="page-link"
                                        href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, function ($k) {
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

    <!-- Modal para nueva instalación -->
    <div class="modal fade" id="instalarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="instalarForm">
                    <input type="hidden" name="action" value="instalar">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle"></i> Nueva Instalación
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="equipo_id" class="form-label">Equipo *</label>
                                <select class="form-select" id="equipo_id" name="equipo_id" required
                                    onchange="cargarPosicionesLibres()">
                                    <option value="">Seleccionar equipo</option>
                                    <?php foreach ($equipos_disponibles as $equipo): ?>
                                    <option value="<?= $equipo['id'] ?>"><?= $equipo['codigo'] ?> -
                                        <?= $equipo['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="posicion" class="form-label">Posición *</label>
                                <select class="form-select" id="posicion" name="posicion" required>
                                    <option value="">Seleccionar posición</option>
                                </select>
                                <div class="form-text">Las posiciones disponibles se cargarán al seleccionar el equipo
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="neumatico_id" class="form-label">Neumático *</label>
                                <select class="form-select" id="neumatico_id" name="neumatico_id" required
                                    onchange="mostrarInfoNeumatico()">
                                    <option value="">Seleccionar neumático</option>
                                    <?php foreach ($neumaticos_disponibles as $neumatico): ?>
                                    <option value="<?= $neumatico['id'] ?>"
                                        data-costo="<?= $neumatico['costo_nuevo'] ?>"
                                        data-remanente="<?= $neumatico['remanente_nuevo'] ?>"
                                        data-medida="<?= $neumatico['medida'] ?>">
                                        <?= $neumatico['codigo_interno'] ?> - <?= $neumatico['marca_nombre'] ?>
                                        <?= $neumatico['diseno_nombre'] ?> (<?= $neumatico['medida'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Información del neumático seleccionado -->
                        <div class="alert alert-info" id="infoNeumatico" style="display: none;">
                            <h6><i class="bi bi-info-circle"></i> Información del Neumático:</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Costo:</strong> <span id="costoNeumatico">-</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Remanente:</strong> <span id="remanenteNeumatico">-</span>%
                                </div>
                                <div class="col-md-4">
                                    <strong>Medida:</strong> <span id="medidaNeumatico">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_instalacion" class="form-label">Fecha de Instalación *</label>
                                <input type="date" class="form-control" id="fecha_instalacion" name="fecha_instalacion"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="horometro_instalacion" class="form-label">Horómetro</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="horometro_instalacion"
                                        name="horometro_instalacion" min="0" placeholder="0">
                                    <span class="input-group-text">hrs</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cocada_inicial" class="form-label">Cocada Inicial *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cocada_inicial" name="cocada_inicial"
                                        step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">mm</span>
                                </div>
                                <div class="form-text">Medición inicial de la cocada del neumático</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vista de Posiciones</label>
                                <div class="position-grid" id="positionGrid">
                                    <div class="position-slot" data-pos="1">1</div>
                                    <div class="position-slot" data-pos="2">2</div>
                                    <div class="position-slot" data-pos="3">3</div>
                                    <div class="position-slot" data-pos="4">4</div>
                                    <div class="position-slot" data-pos="5">5</div>
                                    <div class="position-slot" data-pos="6">6</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                    placeholder="Observaciones sobre la instalación..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Instalar Neumático
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para rotación -->
    <div class="modal fade" id="rotarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="rotarForm">
                    <input type="hidden" name="action" value="rotar">
                    <input type="hidden" name="instalacion_origen" id="rotacion_origen_id">
                    <input type="hidden" name="instalacion_destino" id="rotacion_destino_id">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-arrow-repeat"></i> Rotación de Neumáticos
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Información de Rotación:</h6>
                            <p class="mb-0">Seleccione dos neumáticos para intercambiar sus posiciones. El sistema
                                registrará automáticamente el movimiento.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Neumático Origen</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div id="rotacion_origen_info">
                                            <p class="text-muted">Seleccione un neumático de la tabla</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Neumático Destino</label>
                                <select class="form-select" id="rotacion_destino_select"
                                    onchange="seleccionarDestino()">
                                    <option value="">Seleccionar neumático destino</option>
                                </select>
                                <div class="card mt-2">
                                    <div class="card-body">
                                        <div id="rotacion_destino_info">
                                            <p class="text-muted">Seleccione el neumático destino</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_rotacion" class="form-label">Fecha de Rotación *</label>
                                <input type="date" class="form-control" id="fecha_rotacion" name="fecha_rotacion"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="horometro_rotacion" class="form-label">Horómetro</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="horometro_rotacion"
                                        name="horometro_rotacion" min="0">
                                    <span class="input-group-text">hrs</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cocada_origen" class="form-label">Cocada Origen *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cocada_origen" name="cocada_origen"
                                        step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cocada_destino" class="form-label">Cocada Destino *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cocada_destino" name="cocada_destino"
                                        step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="motivo_rotacion" class="form-label">Motivo de la Rotación</label>
                                <select class="form-select" id="motivo_rotacion" name="motivo_rotacion">
                                    <option value="">Seleccionar motivo</option>
                                    <option value="Desgaste irregular">Desgaste irregular</option>
                                    <option value="Rotación programada 30%">Rotación programada 30%</option>
                                    <option value="Optimización de desgaste">Optimización de desgaste</option>
                                    <option value="Mantenimiento preventivo">Mantenimiento preventivo</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-repeat"></i> Realizar Rotación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para retiro -->
    <div class="modal fade" id="retirarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="retirarForm">
                    <input type="hidden" name="action" value="retirar">
                    <input type="hidden" name="instalacion_id" id="retiro_instalacion_id">
                    <input type="hidden" name="neumatico_id" id="retiro_neumatico_id">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-box-arrow-right"></i> Retirar Neumático
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle"></i> Atención:</h6>
                            <p class="mb-0">Va a retirar el neumático <strong id="retiro_neumatico_codigo"></strong>.
                                Esta acción registrará el retiro en el historial.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_retiro" class="form-label">Fecha de Retiro *</label>
                                <input type="date" class="form-control" id="fecha_retiro" name="fecha_retiro"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="horometro_retiro" class="form-label">Horómetro</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="horometro_retiro"
                                        name="horometro_retiro" min="0">
                                    <span class="input-group-text">hrs</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cocada_retiro" class="form-label">Cocada Final *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cocada_retiro" name="cocada_retiro"
                                        step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="destino" class="form-label">Destino *</label>
                                <select class="form-select" id="destino" name="destino" required>
                                    <option value="">Seleccionar destino</option>
                                    <option value="inventario">Retornar a Inventario</option>
                                    <option value="desecho">Enviar a Desecho</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="motivo_retiro" class="form-label">Motivo del Retiro *</label>
                                <select class="form-select" id="motivo_retiro" name="motivo_retiro" required>
                                    <option value="">Seleccionar motivo</option>
                                    <option value="Desgaste normal">Desgaste normal</option>
                                    <option value="Desgaste crítico">Desgaste crítico</option>
                                    <option value="Corte en banda">Corte en banda</option>
                                    <option value="Corte en costado">Corte en costado</option>
                                    <option value="Separación de banda">Separación de banda</option>
                                    <option value="Falla de carcasa">Falla de carcasa</option>
                                    <option value="Mantenimiento programado">Mantenimiento programado</option>
                                    <option value="Rotación a otro equipo">Rotación a otro equipo</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i> Retirar Neumático
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
    $(document).ready(function() {
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });

    // Cargar posiciones libres del equipo seleccionado
    function cargarPosicionesLibres() {
        const equipoId = $('#equipo_id').val();
        const posicionSelect = $('#posicion');

        posicionSelect.html('<option value="">Cargando...</option>');

        if (!equipoId) {
            posicionSelect.html('<option value="">Seleccionar posición</option>');
            actualizarGridPosiciones([]);
            return;
        }

        $.ajax({
            url: 'api/posiciones_libres.php',
            method: 'GET',
            data: {
                equipo_id: equipoId
            },
            success: function(response) {
                posicionSelect.html('<option value="">Seleccionar posición</option>');

                if (response.success) {
                    response.posiciones_libres.forEach(function(pos) {
                        posicionSelect.append(`<option value="${pos}">${pos}</option>`);
                    });

                    actualizarGridPosiciones(response.posiciones_ocupadas);
                } else {
                    posicionSelect.append('<option value="">Error al cargar posiciones</option>');
                }
            },
            error: function() {
                posicionSelect.html('<option value="">Error al cargar</option>');
            }
        });
    }

    // Actualizar grid visual de posiciones
    function actualizarGridPosiciones(ocupadas) {
        $('.position-slot').removeClass('occupied warning danger').each(function() {
            const pos = parseInt($(this).data('pos'));
            const ocupada = ocupadas.find(o => o.posicion == pos);

            if (ocupada) {
                $(this).addClass('occupied');
                if (ocupada.desgaste > 70) $(this).addClass('danger');
                else if (ocupada.desgaste > 30) $(this).addClass('warning');
                $(this).attr('title', `Ocupada - ${ocupada.desgaste}% desgaste`);
            } else {
                $(this).attr('title', 'Posición libre');
            }
        });
    }


    // Preparar rotación
    function prepararRotacion(instalacionId, codigoNeumatico, posicion) {
        $('#rotacion_origen_id').val(instalacionId);
        $('#rotacion_origen_info').html(`
                <strong>${codigoNeumatico}</strong><br>
                <small class="text-muted">Posición ${posicion}</small>
            `);

        // Cargar neumáticos disponibles para rotación
        cargarNeumaticosParaRotacion(instalacionId);

        $('#rotarModal').modal('show');
    }


    // Cargar neumáticos disponibles para rotación
    function cargarNeumaticosParaRotacion(instalacionOrigenId) {
        $.ajax({
            url: 'api/neumaticos_rotacion.php',
            method: 'GET',
            data: {
                instalacion_origen: instalacionOrigenId
            },
            success: function(response) {
                const select = $('#rotacion_destino_select');
                select.html('<option value="">Seleccionar neumático destino</option>');

                if (response.success) {
                    response.neumaticos.forEach(function(neumatico) {
                        select.append(`
                                <option value="${neumatico.instalacion_id}" 
                                        data-codigo="${neumatico.codigo_interno}"
                                        data-posicion="${neumatico.posicion}"
                                        data-cocada="${neumatico.cocada_actual}">
                                    ${neumatico.codigo_interno} - Pos. ${neumatico.posicion} (${neumatico.cocada_actual}mm)
                                </option>
                            `);
                    });
                }
            },
            error: function() {
                $('#rotacion_destino_select').html('<option value="">Error al cargar</option>');
            }
        });
    }



    // Seleccionar destino para rotación
    function seleccionarDestino() {
        const select = $('#rotacion_destino_select');
        const option = select.find('option:selected');

        if (option.val()) {
            $('#rotacion_destino_id').val(option.val());
            $('#rotacion_destino_info').html(`
                    <strong>${option.data('codigo')}</strong><br>
                    <small class="text-muted">Posición ${option.data('posicion')}</small>
                `);

            // Pre-llenar cocadas
            $('#cocada_destino').val(option.data('cocada'));
        } else {
            $('#rotacion_destino_id').val('');
            $('#rotacion_destino_info').html('<p class="text-muted">Seleccione el neumático destino</p>');
        }
    }


    // Preparar retiro
    function prepararRetiro(instalacionId, neumaticoId, codigoNeumatico) {
        $('#retiro_instalacion_id').val(instalacionId);
        $('#retiro_neumatico_id').val(neumaticoId);
        $('#retiro_neumatico_codigo').text(codigoNeumatico);
        $('#retirarModal').modal('show');
    }

    // Ver historial
    function verHistorial(instalacionId) {
        window.open(`api/historial_instalacion.php?id=${instalacionId}`, '_blank');
    }

    // Toggle vista compacta
    function toggleVistaCompacta() {
        $('#instalacionesTable').toggleClass('table-sm');
    }

    // Ver vista por equipos
    function verVistaEquipos() {
        window.location.href = 'vista_equipos.php';
    }

    // Generar reporte de rotaciones
    function generarReporteRotaciones() {
        window.open('api/reporte_rotaciones.php', '_blank');
    }

    // Planificar mantenimiento
    function planificarMantenimiento() {
        window.location.href = 'planificacion.php';
    }



    // Form validation para instalación
    $('#instalarForm').on('submit', function(e) {
        const equipoId = $('#equipo_id').val();
        const neumaticoId = $('#neumatico_id').val();
        const posicion = $('#posicion').val();
        const fecha = $('#fecha_instalacion').val();
        const cocada = $('#cocada_inicial').val();

        if (!equipoId || !neumaticoId || !posicion || !fecha || !cocada) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios');
            return false;
        }

        if (parseFloat(cocada) <= 0 || parseFloat(cocada) > 100) {
            e.preventDefault();
            alert('La cocada debe estar entre 0.1 y 100 mm');
            return false;
        }

        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Instalando...').prop(
            'disabled', true);
    });



    // Form validation para rotación
    $('#rotarForm').on('submit', function(e) {
        const origenId = $('#rotacion_origen_id').val();
        const destinoId = $('#rotacion_destino_id').val();
        const fecha = $('#fecha_rotacion').val();
        const cocadaOrigen = $('#cocada_origen').val();
        const cocadaDestino = $('#cocada_destino').val();

        if (!origenId || !destinoId || !fecha || !cocadaOrigen || !cocadaDestino) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios');
            return false;
        }

        if (origenId === destinoId) {
            e.preventDefault();
            alert('No puede rotar un neumático consigo mismo');
            return false;
        }

        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Rotando...').prop(
            'disabled', true);
    });


    // Form validation para retiro
    $('#retirarForm').on('submit', function(e) {
        const fecha = $('#fecha_retiro').val();
        const cocada = $('#cocada_retiro').val();
        const destino = $('#destino').val();
        const motivo = $('#motivo_retiro').val();

        if (!fecha || !cocada || !destino || !motivo) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios');
            return false;
        }

        if (parseFloat(cocada) < 0 || parseFloat(cocada) > 100) {
            e.preventDefault();
            alert('La cocada final debe estar entre 0 y 100 mm');
            return false;
        }

        // Confirmación para desecho
        if (destino === 'desecho') {
            if (!confirm(
                    '¿Está seguro de que desea enviar este neumático a desecho? Esta acción no se puede deshacer.'
                )) {
                e.preventDefault();
                return false;
            }
        }

        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Retirando...').prop(
            'disabled', true);
    });



    // Reset forms when modals are hidden
    $('.modal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $(this).find('button[type="submit"]').html($(this).find('button[type="submit"]').data('original-text'))
            .prop('disabled', false);

        // Reset specific elements
        $('#infoNeumatico').hide();
        $('#rotacion_origen_info').html('<p class="text-muted">Seleccione un neumático de la tabla</p>');
        $('#rotacion_destino_info').html('<p class="text-muted">Seleccione el neumático destino</p>');
        $('.position-slot').removeClass('occupied warning danger');
    });

    // Store original button text
    $('.modal button[type="submit"]').each(function() {
        $(this).data('original-text', $(this).html());
    });

    // Search on enter
    $('input[name="search"]').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });


    // Highlight row on hover
    $('.instalacion-row').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );

    // Auto-refresh every 5 minutes
    setInterval(function() {
        if (!$('.modal').hasClass('show')) {
            location.reload();
        }
    }, 300000);

    // Tooltip initialization
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Position grid click handler
    $('.position-slot').on('click', function() {
        const pos = $(this).data('pos');
        if (!$(this).hasClass('occupied')) {
            $('#posicion').val(pos);
        }
    });


    // Auto-calculate days since installation
    function updateDaysInstalled() {
        $('.instalacion-row').each(function() {
            const fechaInstalacion = $(this).find('[data-fecha]').data('fecha');
            if (fechaInstalacion) {
                const dias = Math.floor((new Date() - new Date(fechaInstalacion)) / (1000 * 60 * 60 * 24));
                $(this).find('.dias-instalado').text(dias + ' días');
            }
        });
    }

    // Run on page load
    updateDaysInstalled();
    </script>