<?php
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Procesamiento de acciones
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            // Verificar que el código no exista
            $stmt = $db->query("SELECT id FROM equipos WHERE codigo = ?", [$_POST['codigo']]);
            if ($stmt->fetch()) {
                throw new Exception("El código de equipo ya existe");
            }

            $stmt = $db->query("
                INSERT INTO equipos (
                    codigo, nombre, tipo, modelo, horas_mes_promedio
                ) VALUES (?, ?, ?, ?, ?)
            ", [
                strtoupper($_POST['codigo']),
                $_POST['nombre'],
                $_POST['tipo'],
                $_POST['modelo'],
                $_POST['horas_mes_promedio']
            ]);

            $success = "Equipo registrado exitosamente";
        }

        if ($_POST['action'] === 'update') {
            // Verificar que el código no exista en otro equipo
            $stmt = $db->query("SELECT id FROM equipos WHERE codigo = ? AND id != ?", [$_POST['codigo'], $_POST['id']]);
            if ($stmt->fetch()) {
                throw new Exception("El código de equipo ya existe en otro registro");
            }

            $stmt = $db->query("
                UPDATE equipos SET
                    codigo = ?, nombre = ?, tipo = ?, modelo = ?, horas_mes_promedio = ?
                WHERE id = ?
            ", [
                strtoupper($_POST['codigo']),
                $_POST['nombre'],
                $_POST['tipo'],
                $_POST['modelo'],
                $_POST['horas_mes_promedio'],
                $_POST['id']
            ]);

            $success = "Equipo actualizado exitosamente";
        }

        if ($_POST['action'] === 'toggle_status') {
            $stmt = $db->query("
                UPDATE equipos SET activo = !activo WHERE id = ?
            ", [$_POST['id']]);

            $success = "Estado del equipo actualizado";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filtros
$where_conditions = ["1=1"];
$params = [];

if (!empty($_GET['search'])) {
    $where_conditions[] = "(e.codigo LIKE ? OR e.nombre LIKE ? OR e.tipo LIKE ? OR e.modelo LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search, $search, $search, $search]);
}

if (!empty($_GET['tipo'])) {
    $where_conditions[] = "e.tipo = ?";
    $params[] = $_GET['tipo'];
}

if (isset($_GET['activo']) && $_GET['activo'] !== '') {
    $where_conditions[] = "e.activo = ?";
    $params[] = $_GET['activo'];
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener equipos con información adicional
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM equipos e
    WHERE $where_clause
", $params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$stmt = $db->query("
    SELECT e.*,
           COUNT(i.id) as neumaticos_instalados,
           COALESCE(SUM(n.costo_nuevo * (n.remanente_nuevo / 100)), 0) as valor_neumaticos,
           COALESCE(AVG(ss.porcentaje_desgaste), 0) as desgaste_promedio,
           COALESCE(SUM(ss.horas_trabajadas), 0) as horas_acumuladas_total
    FROM equipos e
    LEFT JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
    LEFT JOIN neumaticos n ON i.neumatico_id = n.id
    LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id
    WHERE $where_clause
    GROUP BY e.id
    ORDER BY e.activo DESC, e.codigo ASC
    LIMIT $per_page OFFSET $offset
", $params);
$equipos = $stmt->fetchAll();

// Obtener tipos de equipo únicos para filtro
$stmt = $db->query("SELECT DISTINCT tipo FROM equipos WHERE tipo IS NOT NULL ORDER BY tipo");
$tipos_equipo = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas generales
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_equipos,
        COUNT(CASE WHEN activo = 1 THEN 1 END) as equipos_activos,
        COUNT(CASE WHEN activo = 0 THEN 1 END) as equipos_inactivos,
        COALESCE(SUM(horas_mes_promedio), 0) as horas_mes_total
    FROM equipos
");
$estadisticas = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipos - TireSystem</title>

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

        .table-responsive {
            border-radius: 1rem;
            overflow: hidden;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .equipo-card {
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .equipo-card:hover {
            transform: translateY(-2px);
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

        .equipo-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .neumaticos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .neumatico-pos {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            font-size: 0.8em;
        }

        .neumatico-pos.installed {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        .neumatico-pos.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .neumatico-pos.danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
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
                        <a class="nav-link active" href="equipos.php">
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
                        <i class="bi bi-truck text-primary"></i> Gestión de Equipos
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#equipoModal">
                            <i class="bi bi-plus-lg"></i> Nuevo Equipo
                        </button>
                        <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportarDatos('excel')">
                                    <i class="bi bi-file-excel"></i> Exportar Excel
                                </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportarDatos('pdf')">
                                    <i class="bi bi-file-pdf"></i> Exportar PDF
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" onclick="importarDatos()">
                                    <i class="bi bi-upload"></i> Importar Datos
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
                                        <h6 class="card-title">Total Equipos</h6>
                                        <h3 class="mb-0"><?= $estadisticas['total_equipos'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-truck h2"></i>
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
                                        <h6 class="card-title">Activos</h6>
                                        <h3 class="mb-0"><?= $estadisticas['equipos_activos'] ?></h3>
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
                                        <h6 class="card-title">Inactivos</h6>
                                        <h3 class="mb-0"><?= $estadisticas['equipos_inactivos'] ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-pause-circle h2"></i>
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
                                        <h6 class="card-title">Horas/Mes Total</h6>
                                        <h3 class="mb-0"><?= number_format($estadisticas['horas_mes_total']) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock h2"></i>
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
                                        placeholder="Buscar por código, nombre, tipo o modelo..."
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="tipo">
                                    <option value="">Todos los tipos</option>
                                    <?php foreach ($tipos_equipo as $tipo): ?>
                                        <option value="<?= htmlspecialchars($tipo) ?>"
                                            <?= ($_GET['tipo'] ?? '') == $tipo ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tipo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="activo">
                                    <option value="">Todos los estados</option>
                                    <option value="1" <?= ($_GET['activo'] ?? '') === '1' ? 'selected' : '' ?>>
                                        Activos
                                    </option>
                                    <option value="0" <?= ($_GET['activo'] ?? '') === '0' ? 'selected' : '' ?>>
                                        Inactivos
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <a href="equipos.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Vista en tarjetas de equipos -->
                <div class="row">
                    <?php if (empty($equipos)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-inbox h1 text-muted"></i>
                            <h4 class="text-muted">No se encontraron equipos</h4>
                            <p class="text-muted">Intente ajustar los filtros de búsqueda</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($equipos as $equipo): ?>
                            <div class="col-xl-4 col-lg-6 mb-4">
                                <div class="card equipo-card h-100" onclick="verDetalleEquipo(<?= $equipo['id'] ?>)">
                                    <div class="card-body position-relative">
                                        <!-- Estado del equipo -->
                                        <div class="equipo-status">
                                            <span class="badge bg-<?= $equipo['activo'] ? 'success' : 'secondary' ?>">
                                                <?= $equipo['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </div>

                                        <!-- Información principal -->
                                        <div class="mb-3">
                                            <h5 class="card-title mb-1">
                                                <i class="bi bi-truck text-primary"></i>
                                                <?= htmlspecialchars($equipo['codigo']) ?>
                                            </h5>
                                            <h6 class="text-muted mb-2"><?= htmlspecialchars($equipo['nombre']) ?></h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-info"><?= htmlspecialchars($equipo['tipo']) ?></span>
                                                <small class="text-muted"><?= htmlspecialchars($equipo['modelo']) ?></small>
                                            </div>
                                        </div>

                                        <!-- Estadísticas del equipo -->
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <div class="fw-bold text-primary"><?= $equipo['neumaticos_instalados'] ?></div>
                                                <small class="text-muted">Neumáticos</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-success"><?= number_format($equipo['horas_mes_promedio']) ?></div>
                                                <small class="text-muted">Hrs/Mes</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-warning"><?= number_format($equipo['desgaste_promedio'], 1) ?>%</div>
                                                <small class="text-muted">Desgaste</small>
                                            </div>
                                        </div>

                                        <!-- Valor de neumáticos -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">Valor Neumáticos:</small>
                                                <strong class="text-success"><?= formatCurrency($equipo['valor_neumaticos']) ?></strong>
                                            </div>
                                        </div>

                                        <!-- Grid de posiciones de neumáticos -->
                                        <div class="neumaticos-grid" id="grid_<?= $equipo['id'] ?>">
                                            <?php
                                            // Simular posiciones (1-6 para camiones mineros)
                                            for ($pos = 1; $pos <= 6; $pos++):
                                                $installed = $pos <= $equipo['neumaticos_instalados'];
                                                $class = $installed ? 'installed' : '';
                                                if ($installed && $equipo['desgaste_promedio'] > 70) $class = 'danger';
                                                elseif ($installed && $equipo['desgaste_promedio'] > 30) $class = 'warning';
                                            ?>
                                                <div class="neumatico-pos <?= $class ?>">
                                                    <div class="fw-bold"><?= $pos ?></div>
                                                    <div style="font-size: 0.7em;">
                                                        <?= $installed ? 'INST' : 'LIBRE' ?>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <!-- Acciones -->
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                onclick="event.stopPropagation(); editEquipo(<?= htmlspecialchars(json_encode($equipo)) ?>)">
                                                <i class="bi bi-pencil"></i> Editar
                                            </button>
                                            <button type="button" class="btn btn-outline-<?= $equipo['activo'] ? 'warning' : 'success' ?> btn-sm"
                                                onclick="event.stopPropagation(); toggleEstado(<?= $equipo['id'] ?>, <?= $equipo['activo'] ? 'false' : 'true' ?>)">
                                                <i class="bi bi-<?= $equipo['activo'] ? 'pause' : 'play' ?>-circle"></i>
                                                <?= $equipo['activo'] ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm"
                                                onclick="event.stopPropagation(); verSeguimiento(<?= $equipo['id'] ?>)">
                                                <i class="bi bi-graph-up"></i> Seguimiento
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Paginación de equipos">
                            <ul class="pagination">
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
            </main>
        </div>
    </div>

    <!-- Modal para crear/editar equipo -->
    <div class="modal fade" id="equipoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="equipoForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="equipoId">

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="bi bi-plus-circle"></i> Nuevo Equipo
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="codigo" class="form-label">Código del Equipo *</label>
                                <input type="text" class="form-control text-uppercase" id="codigo" name="codigo"
                                    placeholder="Ej: K-01, CF-05" required>
                                <div class="form-text">Código único del equipo (se convertirá a mayúsculas)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre del Equipo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    placeholder="Ej: KOMATSU 730E N°01" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tipo" class="form-label">Tipo de Equipo *</label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="Camión Minero">Camión Minero</option>
                                    <option value="Camioneta">Camioneta</option>
                                    <option value="Excavadora">Excavadora</option>
                                    <option value="Cargador Frontal">Cargador Frontal</option>
                                    <option value="Bulldozer">Bulldozer</option>
                                    <option value="Motoniveladora">Motoniveladora</option>
                                    <option value="Compactadora">Compactadora</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="modelo" name="modelo"
                                    placeholder="Ej: KOMATSU 730E, CAT 777">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="horas_mes_promedio" class="form-label">Horas Promedio/Mes *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="horas_mes_promedio"
                                        name="horas_mes_promedio" min="0" max="744" value="500" required>
                                    <span class="input-group-text">hrs</span>
                                </div>
                                <div class="form-text">Horas de operación promedio mensual (máx. 744 hrs)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Información Calculada</label>
                                <div class="card bg-light">
                                    <div class="card-body p-2">
                                        <small class="text-muted">
                                            <strong>Horas/día:</strong> <span id="horasDia">-</span><br>
                                            <strong>Días/mes:</strong> <span id="diasMes">-</span><br>
                                            <strong>Utilización:</strong> <span id="utilizacion">-</span>%
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle"></i> Configuración de Posiciones</h6>
                                    <p class="mb-2">Este sistema maneja equipos con las siguientes configuraciones típicas:</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Camiones Mineros:</strong><br>
                                            <small>• Posiciones 1-2: Delanteras<br>
                                                • Posiciones 3-4: Intermedias<br>
                                                • Posiciones 5-6: Posteriores</small>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Camionetas:</strong><br>
                                            <small>• Posiciones 1-2: Delanteras<br>
                                                • Posiciones 3-4: Posteriores</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <span id="btnText">Guardar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para detalle del equipo -->
    <div class="modal fade" id="detalleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalleTitle">
                        <i class="bi bi-truck"></i> Detalle del Equipo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="irASeguimiento()">
                        <i class="bi bi-graph-up"></i> Ver Seguimiento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        let equipoSeleccionado = null;

        $(document).ready(function() {
            // Auto-dismiss alerts
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Calcular información al cambiar horas
            $('#horas_mes_promedio').on('input', calcularInfo);
            calcularInfo(); // Calcular al cargar
        });

        function calcularInfo() {
            const horasMes = parseFloat($('#horas_mes_promedio').val()) || 0;
            const horasDia = (horasMes / 30).toFixed(1);
            const diasMes = Math.ceil(horasMes / 20); // Asumiendo 20 hrs/día efectivas
            const utilizacion = ((horasMes / 744) * 100).toFixed(1); // 744 = 24 * 31 hrs máximas

            $('#horasDia').text(horasDia + ' hrs');
            $('#diasMes').text(diasMes + ' días');
            $('#utilizacion').text(utilizacion + '%');
        }

        function editEquipo(equipo) {
            $('#formAction').val('update');
            $('#equipoId').val(equipo.id);
            $('#modalTitle').html('<i class="bi bi-pencil"></i> Editar Equipo');
            $('#btnText').text('Actualizar');

            // Llenar formulario
            $('#codigo').val(equipo.codigo);
            $('#nombre').val(equipo.nombre);
            $('#tipo').val(equipo.tipo);
            $('#modelo').val(equipo.modelo);
            $('#horas_mes_promedio').val(equipo.horas_mes_promedio);

            calcularInfo();
            $('#equipoModal').modal('show');
        }

        function resetForm() {
            $('#formAction').val('create');
            $('#equipoId').val('');
            $('#modalTitle').html('<i class="bi bi-plus-circle"></i> Nuevo Equipo');
            $('#btnText').text('Guardar');
            $('#equipoForm')[0].reset();
            $('#horas_mes_promedio').val('500');
            calcularInfo();
        }

        // Reset form when modal is hidden
        $('#equipoModal').on('hidden.bs.modal', function() {
            resetForm();
        });

        function toggleEstado(equipoId, nuevoEstado) {
            const accion = nuevoEstado ? 'activar' : 'desactivar';
            const mensaje = `¿Está seguro de que desea ${accion} este equipo?`;

            if (confirm(mensaje)) {
                $.ajax({
                    url: 'equipos.php',
                    method: 'POST',
                    data: {
                        action: 'toggle_status',
                        id: equipoId
                    },
                    success: function(response) {
                        location.reload();
                    },
                    error: function() {
                        alert('Error al cambiar el estado del equipo');
                    }
                });
            }
        }

        function verDetalleEquipo(equipoId) {
            equipoSeleccionado = equipoId;
            $('#detalleModal').modal('show');

            // Cargar detalle del equipo
            $.ajax({
                url: 'api/equipo_detalle.php',
                method: 'GET',
                data: {
                    id: equipoId
                },
                success: function(response) {
                    $('#detalleContent').html(response);
                },
                error: function() {
                    $('#detalleContent').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Error al cargar el detalle del equipo
                        </div>
                    `);
                }
            });
        }

        function verSeguimiento(equipoId) {
            window.location.href = `seguimiento.php?equipo=${equipoId}`;
        }

        function irASeguimiento() {
            if (equipoSeleccionado) {
                window.location.href = `seguimiento.php?equipo=${equipoSeleccionado}`;
            }
        }

        // Form validation
        $('#equipoForm').on('submit', function(e) {
            const codigo = $('#codigo').val().trim();
            const nombre = $('#nombre').val().trim();
            const tipo = $('#tipo').val();
            const horas = $('#horas_mes_promedio').val();

            if (!codigo || !nombre || !tipo) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios (*)');
                return false;
            }

            if (parseFloat(horas) <= 0 || parseFloat(horas) > 744) {
                e.preventDefault();
                alert('Las horas por mes deben estar entre 1 y 744');
                return false;
            }

            // Show loading state
            $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Guardando...').prop('disabled', true);
        });

        // Auto-uppercase codigo
        $('#codigo').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        // Search on enter
        $('input[name="search"]').on('keypress', function(e) {
            if (e.which === 13) {
                $(this).closest('form').submit();
            }
        });

        // Funciones de exportación
        function exportarDatos(formato) {
            const params = new URLSearchParams(window.location.search);
            params.append('export', formato);
            window.location.href = `api/exportar_equipos.php?${params.toString()}`;
        }

        function importarDatos() {
            // Crear input file dinámicamente
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.xlsx,.xls,.csv';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('file', file);

                    $.ajax({
                        url: 'api/importar_equipos.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Datos importados exitosamente');
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Error al importar los datos');
                        }
                    });
                }
            };
            input.click();
        }

        // Tooltip initialization
        $(function() {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });

        // Animación de las tarjetas
        $('.equipo-card').each(function(index) {
            $(this).css('animation-delay', (index * 100) + 'ms');
        });
    </script>

    <style>
        /* Animaciones para las tarjetas */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .equipo-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        /* Hover effects */
        .neumatico-pos {
            transition: all 0.2s ease;
        }

        .neumatico-pos:hover {
            transform: scale(1.05);
        }

        /* Loading states */
        .btn:disabled {
            opacity: 0.7;
        }

        /* Custom scrollbar for modal */
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
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