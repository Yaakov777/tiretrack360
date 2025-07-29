<?php
require_once 'config.php';
Auth::requireLogin();

$db = new Database();

// Procesamiento de acciones
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $db->query("
                INSERT INTO neumaticos (
                    codigo_interno, numero_serie, dot, marca_id, diseno_id, medida_id,
                    nuevo_usado, remanente_nuevo, garantia_horas, vida_util_horas, costo_nuevo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $_POST['codigo_interno'],
                $_POST['numero_serie'],
                $_POST['dot'],
                $_POST['marca_id'],
                $_POST['diseno_id'],
                $_POST['medida_id'],
                $_POST['nuevo_usado'],
                $_POST['remanente_nuevo'],
                $_POST['garantia_horas'],
                $_POST['vida_util_horas'],
                $_POST['costo_nuevo']
            ]);

            $success = "Neumático registrado exitosamente";
        }

        if ($_POST['action'] === 'update') {
            $stmt = $db->query("
                UPDATE neumaticos SET
                    numero_serie = ?, dot = ?, marca_id = ?, diseno_id = ?, medida_id = ?,
                    nuevo_usado = ?, remanente_nuevo = ?, garantia_horas = ?, 
                    vida_util_horas = ?, costo_nuevo = ?
                WHERE id = ?
            ", [
                $_POST['numero_serie'],
                $_POST['dot'],
                $_POST['marca_id'],
                $_POST['diseno_id'],
                $_POST['medida_id'],
                $_POST['nuevo_usado'],
                $_POST['remanente_nuevo'],
                $_POST['garantia_horas'],
                $_POST['vida_util_horas'],
                $_POST['costo_nuevo'],
                $_POST['id']
            ]);

            $success = "Neumático actualizado exitosamente";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filtros
$where_conditions = ["1=1"];
$params = [];

if (!empty($_GET['search'])) {
    $where_conditions[] = "(n.codigo_interno LIKE ? OR n.numero_serie LIKE ? OR m.nombre LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search, $search, $search]);
}

if (!empty($_GET['estado'])) {
    $where_conditions[] = "n.estado = ?";
    $params[] = $_GET['estado'];
}

if (!empty($_GET['marca'])) {
    $where_conditions[] = "n.marca_id = ?";
    $params[] = $_GET['marca'];
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener neumáticos con paginación
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM neumaticos n
    LEFT JOIN marcas m ON n.marca_id = m.id
    WHERE $where_clause
", $params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$stmt = $db->query("
    SELECT n.*, m.nombre as marca_nombre, d.nombre as diseno_nombre, 
           med.medida as medida_nombre, e.codigo as equipo_codigo,
           i.posicion as posicion_actual
    FROM neumaticos n
    LEFT JOIN marcas m ON n.marca_id = m.id
    LEFT JOIN disenos d ON n.diseno_id = d.id
    LEFT JOIN medidas med ON n.medida_id = med.id
    LEFT JOIN instalaciones i ON n.id = i.neumatico_id AND i.activo = 1
    LEFT JOIN equipos e ON i.equipo_id = e.id
    WHERE $where_clause
    ORDER BY n.created_at DESC
    LIMIT $per_page OFFSET $offset
", $params);
$neumaticos = $stmt->fetchAll();

// Obtener datos para formularios
$marcas = $db->query("SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre")->fetchAll();
$disenos = $db->query("SELECT * FROM disenos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$medidas = $db->query("SELECT * FROM medidas WHERE activo = 1 ORDER BY medida")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Neumáticos - TireSystem</title>

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

    .estado-badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
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
                    <h1 class="h2">
                        <i class="bi bi-circle text-primary"></i> Gestión de Neumáticos
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#neumaticModal">
                        <i class="bi bi-plus-lg"></i> Nuevo Neumático
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
                                        placeholder="Buscar por código, serie o marca..."
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="inventario"
                                        <?= ($_GET['estado'] ?? '') == 'inventario' ? 'selected' : '' ?>>
                                        Inventario
                                    </option>
                                    <option value="instalado"
                                        <?= ($_GET['estado'] ?? '') == 'instalado' ? 'selected' : '' ?>>
                                        Instalado
                                    </option>
                                    <option value="desechado"
                                        <?= ($_GET['estado'] ?? '') == 'desechado' ? 'selected' : '' ?>>
                                        Desechado
                                    </option>
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
                            <div class="col-md-4">
                                <div class="btn-group" role="group">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <a href="neumaticos.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de neumáticos -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Lista de Neumáticos
                            <span class="badge bg-secondary"><?= $total_records ?></span>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success">
                                <i class="bi bi-file-excel"></i> Excel
                            </button>
                            <button type="button" class="btn btn-outline-danger">
                                <i class="bi bi-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Serie</th>
                                        <th>Marca/Diseño</th>
                                        <th>Medida</th>
                                        <th>Estado</th>
                                        <th>Ubicación</th>
                                        <th>Remanente</th>
                                        <th>Costo</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($neumaticos)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox h1"></i><br>
                                            No se encontraron neumáticos
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($neumaticos as $neumatico): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $neumatico['codigo_interno'] ?></td>
                                        <td>
                                            <span class="text-muted small"><?= $neumatico['numero_serie'] ?></span>
                                            <?php if ($neumatico['dot']): ?>
                                            <br><small class="text-info">DOT: <?= $neumatico['dot'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= $neumatico['marca_nombre'] ?></strong><br>
                                            <small class="text-muted"><?= $neumatico['diseno_nombre'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $neumatico['medida_nombre'] ?></span>
                                        </td>
                                        <td>
                                            <span
                                                class="badge estado-badge bg-<?= getEstadoColor($neumatico['estado']) ?>">
                                                <?= ucfirst($neumatico['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($neumatico['estado'] == 'instalado'): ?>
                                            <strong><?= $neumatico['equipo_codigo'] ?></strong><br>
                                            <small class="text-muted">Pos. <?= $neumatico['posicion_actual'] ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?= $neumatico['remanente_nuevo'] > 70 ? 'success' : ($neumatico['remanente_nuevo'] > 30 ? 'warning' : 'danger') ?>"
                                                    style="width: <?= $neumatico['remanente_nuevo'] ?>%">
                                                    <?= $neumatico['remanente_nuevo'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fw-bold"><?= formatCurrency($neumatico['costo_nuevo']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-action"
                                                    onclick="editNeumatico(<?= htmlspecialchars(json_encode($neumatico)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-action"
                                                    onclick="viewHistory(<?= $neumatico['id'] ?>)">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                                <?php if ($neumatico['estado'] != 'desechado'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-action"
                                                    onclick="deleteNeumatico(<?= $neumatico['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
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
                        <nav aria-label="Paginación de neumáticos">
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

    <!-- Modal para crear/editar neumático -->
    <div class="modal fade" id="neumaticModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="neumaticForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="neumaticId">

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="bi bi-plus-circle"></i> Nuevo Neumático
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="codigo_interno" class="form-label">Código Interno *</label>
                                <input type="text" class="form-control" id="codigo_interno" name="codigo_interno"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="numero_serie" class="form-label">Número de Serie</label>
                                <input type="text" class="form-control" id="numero_serie" name="numero_serie">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dot" class="form-label">DOT</label>
                                <input type="text" class="form-control" id="dot" name="dot" placeholder="WWYY">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="marca_id" class="form-label">Marca *</label>
                                <select class="form-select" id="marca_id" name="marca_id" required>
                                    <option value="">Seleccionar marca</option>
                                    <?php foreach ($marcas as $marca): ?>
                                    <option value="<?= $marca['id'] ?>"><?= $marca['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nuevo_usado" class="form-label">Condición *</label>
                                <select class="form-select" id="nuevo_usado" name="nuevo_usado" required>
                                    <option value="N">Nuevo</option>
                                    <option value="U">Usado</option>
                                    <option value="R">Reencauche</option>
                                    <option value="RXT">RXT</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="diseno_id" class="form-label">Diseño *</label>
                                <select class="form-select" id="diseno_id" name="diseno_id" required>
                                    <option value="">Seleccionar diseño</option>
                                    <?php foreach ($disenos as $diseno): ?>
                                    <option value="<?= $diseno['id'] ?>"><?= $diseno['nombre'] ?>
                                        (<?= $diseno['tipo'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="medida_id" class="form-label">Medida *</label>
                                <select class="form-select" id="medida_id" name="medida_id" required>
                                    <option value="">Seleccionar medida</option>
                                    <?php foreach ($medidas as $medida): ?>
                                    <option value="<?= $medida['id'] ?>"><?= $medida['medida'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="remanente_nuevo" class="form-label">Remanente (%)</label>
                                <input type="number" class="form-control" id="remanente_nuevo" name="remanente_nuevo"
                                    min="0" max="100" step="0.01" value="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="garantia_horas" class="form-label">Garantía (hrs)</label>
                                <input type="number" class="form-control" id="garantia_horas" name="garantia_horas"
                                    min="0" value="5000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="vida_util_horas" class="form-label">Vida Útil (hrs)</label>
                                <input type="number" class="form-control" id="vida_util_horas" name="vida_util_horas"
                                    min="0" value="5000">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="costo_nuevo" class="form-label">Costo Nuevo ($) *</label>
                                <input type="number" class="form-control" id="costo_nuevo" name="costo_nuevo" min="0"
                                    step="0.01" required>
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

    <!-- Modal para historial -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-clock-history"></i> Historial del Neumático
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="historyContent">
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
    });

    function editNeumatico(neumatico) {
        $('#formAction').val('update');
        $('#neumaticId').val(neumatico.id);
        $('#modalTitle').html('<i class="bi bi-pencil"></i> Editar Neumático');
        $('#btnText').text('Actualizar');

        // Deshabilitar código interno en edición
        $('#codigo_interno').val(neumatico.codigo_interno).prop('readonly', true);

        // Llenar formulario
        $('#numero_serie').val(neumatico.numero_serie);
        $('#dot').val(neumatico.dot);
        $('#marca_id').val(neumatico.marca_id);
        $('#diseno_id').val(neumatico.diseno_id);
        $('#medida_id').val(neumatico.medida_id);
        $('#nuevo_usado').val(neumatico.nuevo_usado);
        $('#remanente_nuevo').val(neumatico.remanente_nuevo);
        $('#garantia_horas').val(neumatico.garantia_horas);
        $('#vida_util_horas').val(neumatico.vida_util_horas);
        $('#costo_nuevo').val(neumatico.costo_nuevo);

        $('#neumaticModal').modal('show');
    }

    function resetForm() {
        $('#formAction').val('create');
        $('#neumaticId').val('');
        $('#modalTitle').html('<i class="bi bi-plus-circle"></i> Nuevo Neumático');
        $('#btnText').text('Guardar');
        $('#codigo_interno').prop('readonly', false);
        $('#neumaticForm')[0].reset();
        $('#remanente_nuevo').val('100');
        $('#garantia_horas').val('5000');
        $('#vida_util_horas').val('5000');
    }

    // Reset form when modal is hidden
    $('#neumaticModal').on('hidden.bs.modal', function() {
        resetForm();
    });

    function viewHistory(neumaticId) {
        $('#historyModal').modal('show');

        $.ajax({
            url: 'api/neumatico_history.php',
            method: 'GET',
            data: {
                id: neumaticId
            },
            success: function(response) {
                $('#historyContent').html(response);
            },
            error: function() {
                $('#historyContent').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Error al cargar el historial
                        </div>
                    `);
            }
        });
    }

    function deleteNeumatico(neumaticId) {
        if (confirm('¿Está seguro de que desea eliminar este neumático?')) {
            $.ajax({
                url: 'api/delete_neumatico.php',
                method: 'POST',
                data: {
                    id: neumaticId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al eliminar el neumático');
                }
            });
        }
    }

    // Form validation
    $('#neumaticForm').on('submit', function(e) {
        const codigo = $('#codigo_interno').val().trim();
        const marca = $('#marca_id').val();
        const diseno = $('#diseno_id').val();
        const medida = $('#medida_id').val();
        const costo = $('#costo_nuevo').val();

        if (!codigo || !marca || !diseno || !medida || !costo) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios (*)');
            return false;
        }

        if (parseFloat(costo) <= 0) {
            e.preventDefault();
            alert('El costo debe ser mayor a 0');
            return false;
        }

        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="bi bi-hourglass-split"></i> Guardando...').prop(
            'disabled', true);
    });

    // Search on enter
    $('input[name="search"]').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });

    // Auto-format DOT field
    $('#dot').on('input', function() {
        let value = $(this).val().replace(/\D/g, ''); // Solo números
        if (value.length > 4) {
            value = value.substring(0, 4);
        }
        $(this).val(value);
    });

    // Auto-format currency
    $('#costo_nuevo').on('blur', function() {
        const value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    </script>
</body>

</html>