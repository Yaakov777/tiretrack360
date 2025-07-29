<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'tire_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar actualizaciones de estado
if ($_POST['action'] ?? '' === 'update_status') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    $sql = "UPDATE demo_requests SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[' , NOW(), '] ', ?) WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $notes, $id]);

    header('Location: admin_dashboard.php?updated=1');
    exit;
}

// Obtener estadísticas
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'nuevo' THEN 1 END) as nuevos,
        COUNT(CASE WHEN status = 'contactado' THEN 1 END) as contactados,
        COUNT(CASE WHEN status = 'demo_programada' THEN 1 END) as demos_programadas,
        COUNT(CASE WHEN status = 'convertido' THEN 1 END) as convertidos,
        COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as hoy,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as esta_semana
    FROM demo_requests
")->fetch();

// Obtener solicitudes recientes
$requests = $pdo->query("
    SELECT dr.*, 
           DATEDIFF(NOW(), dr.created_at) as days_ago,
           COUNT(cl.id) as contact_count
    FROM demo_requests dr
    LEFT JOIN contact_log cl ON dr.id = cl.demo_request_id
    GROUP BY dr.id
    ORDER BY dr.created_at DESC
    LIMIT 50
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Tire Track 360</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f1f5f9;
        color: #334155;
    }

    .header {
        background: linear-gradient(135deg, #1e3a8a, #3b82f6);
        color: white;
        padding: 1rem 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .header h1 {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 4px solid #3b82f6;
    }

    .stat-card.new {
        border-left-color: #ef4444;
    }

    .stat-card.contacted {
        border-left-color: #f59e0b;
    }

    .stat-card.demo {
        border-left-color: #8b5cf6;
    }

    .stat-card.converted {
        border-left-color: #10b981;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #1e293b;
    }

    .stat-label {
        color: #64748b;
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .requests-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .table-header {
        background: #f8fafc;
        padding: 1rem 2rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-header h2 {
        color: #1e293b;
        font-size: 1.3rem;
    }

    .filters {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .filter-select {
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    th {
        background: #f8fafc;
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: uppercase;
    }

    .status-nuevo {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-contactado {
        background: #fef3c7;
        color: #d97706;
    }

    .status-demo_programada {
        background: #e0e7ff;
        color: #7c3aed;
    }

    .status-demo_realizada {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-convertido {
        background: #d1fae5;
        color: #059669;
    }

    .status-cerrado {
        background: #f3f4f6;
        color: #6b7280;
    }

    .priority-alta {
        color: #dc2626;
        font-weight: bold;
    }

    .priority-media {
        color: #d97706;
    }

    .priority-baja {
        color: #059669;
    }

    .priority-urgente {
        color: #dc2626;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn {
        padding: 0.4rem 0.8rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.2s;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-warning {
        background: #f59e0b;
        color: white;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn:hover {
        transform: translateY(-1px);
        opacity: 0.9;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }

    .modal-content {
        background: white;
        margin: 5% auto;
        padding: 2rem;
        border-radius: 12px;
        max-width: 500px;
        position: relative;
    }

    .close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6b7280;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 1rem;
    }

    .whatsapp-link {
        color: #25D366;
        text-decoration: none;
        font-weight: 500;
    }

    .whatsapp-link:hover {
        text-decoration: underline;
    }

    .days-ago {
        font-size: 0.8rem;
        color: #6b7280;
    }

    .contact-count {
        background: #e0e7ff;
        color: #3730a3;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.8rem;
    }

    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    @media (max-width: 768px) {
        .container {
            padding: 1rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filters {
            flex-direction: column;
            gap: 0.5rem;
        }

        table {
            font-size: 0.8rem;
        }

        th,
        td {
            padding: 0.5rem;
        }
    }
    </style>
</head>

<body>
    <div class="header">
        <h1><i class="fas fa-tachometer-alt"></i> Panel de Administración - Tire Track 360</h1>
        <p>Gestión de Solicitudes de Demo</p>
    </div>

    <div class="container">
        <?php if (isset($_GET['updated'])): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i> Estado actualizado correctamente
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Solicitudes</div>
            </div>
            <div class="stat-card new">
                <div class="stat-number"><?= $stats['nuevos'] ?></div>
                <div class="stat-label">Nuevas (Sin Contactar)</div>
            </div>
            <div class="stat-card contacted">
                <div class="stat-number"><?= $stats['contactados'] ?></div>
                <div class="stat-label">Contactados</div>
            </div>
            <div class="stat-card demo">
                <div class="stat-number"><?= $stats['demos_programadas'] ?></div>
                <div class="stat-label">Demos Programadas</div>
            </div>
            <div class="stat-card converted">
                <div class="stat-number"><?= $stats['convertidos'] ?></div>
                <div class="stat-label">Convertidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['hoy'] ?></div>
                <div class="stat-label">Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['esta_semana'] ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
        </div>

        <!-- Tabla de Solicitudes -->
        <div class="requests-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Solicitudes de Demo</h2>
                <div class="filters">
                    <select class="filter-select" onchange="filterTable(this.value)">
                        <option value="">Todos los estados</option>
                        <option value="nuevo">Nuevos</option>
                        <option value="contactado">Contactados</option>
                        <option value="demo_programada">Demo Programada</option>
                        <option value="convertido">Convertidos</option>
                    </select>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>

            <table id="requestsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Flota</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Fecha</th>
                        <th>Contactos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr data-status="<?= $req['status'] ?>">
                        <td><strong>#<?= $req['id'] ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($req['name']) ?></strong>
                            <br>
                            <small><?= htmlspecialchars($req['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($req['company']) ?></td>
                        <td>
                            <a href="https://wa.me/<?= $req['phone'] ?>" class="whatsapp-link" target="_blank">
                                <i class="fab fa-whatsapp"></i> <?= $req['phone'] ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($req['fleet_size'] ?: 'No especificado') ?></td>
                        <td>
                            <span class="status-badge status-<?= $req['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $req['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="priority-<?= $req['priority'] ?>">
                                <?= ucfirst($req['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                            <br>
                            <span class="days-ago">hace <?= $req['days_ago'] ?> día(s)</span>
                        </td>
                        <td>
                            <span class="contact-count"><?= $req['contact_count'] ?> contactos</span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-primary"
                                    onclick="openUpdateModal(<?= $req['id'] ?>, '<?= $req['status'] ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="https://wa.me/<?= $req['phone'] ?>?text=Hola <?= urlencode($req['name']) ?>, soy de Tire Track 360. Te contacto por tu solicitud de demo."
                                    class="btn btn-success" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="mailto:<?= $req['email'] ?>?subject=Demo Tire Track 360&body=Estimado/a <?= urlencode($req['name']) ?>,%0D%0A%0D%0AGracias por tu interés en Tire Track 360..."
                                    class="btn btn-warning">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para actualizar estado -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Actualizar Estado de Solicitud</h3>

            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="modalRequestId">

                <div class="form-group">
                    <label for="modalStatus">Estado:</label>
                    <select name="status" id="modalStatus">
                        <option value="nuevo">Nuevo</option>
                        <option value="contactado">Contactado</option>
                        <option value="demo_programada">Demo Programada</option>
                        <option value="demo_realizada">Demo Realizada</option>
                        <option value="convertido">Convertido</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalNotes">Notas del seguimiento:</label>
                    <textarea name="notes" id="modalNotes" rows="4"
                        placeholder="Agregar nota sobre el contacto o seguimiento..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Actualizar
                </button>
            </form>
        </div>
    </div>

    <script>
    function openUpdateModal(id, currentStatus) {
        document.getElementById('modalRequestId').value = id;
        document.getElementById('modalStatus').value = currentStatus;
        document.getElementById('updateModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('updateModal').style.display = 'none';
    }

    function filterTable(status) {
        const rows = document.querySelectorAll('#requestsTable tbody tr');
        rows.forEach(row => {
            if (status === '' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('updateModal');
        if (event.target === modal) {
            closeModal();
        }
    }

    // Auto-refresh cada 5 minutos
    setTimeout(() => {
        location.reload();
    }, 300000);
    </script>
</body>

</html>