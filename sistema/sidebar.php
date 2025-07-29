<?php
// sidebar.php - Versión mejorada
// Verificar que las variables de sesión existan
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$user_role = $_SESSION['user_role'] ?? 'invitado';

// Obtener el archivo actual para marcar la opción activa
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar si existe la variable $stats (puede no estar definida en todas las páginas)
$alertas_count = isset($stats['alertas_pendientes']) ? $stats['alertas_pendientes'] : 0;
?>

<!-- Botón hamburguesa SOLO escritorio (md en adelante) -->
<div class="d-none d-md-block position-fixed" style="top:10px;left:10px;z-index:1040;">
    <button class="btn btn-outline-secondary" id="toggleSidebar" type="button" title="Mostrar/Ocultar menú">
        <i class="bi bi-list"></i>
    </button>
</div>

<!-- Sidebar ESCRITORIO (colapsable) -->
<nav id="sidebarMenu" class="col-auto col-md-2 d-none d-md-block sidebar p-3 position-fixed h-100"
    style="top:0;left:0;z-index:1030;overflow-y:auto;">

    <!-- Header del sidebar -->
    <div class="text-center mb-4 mt-3">
        <h4 class="text-white mb-1">
            <i class="bi bi-gear-wide"></i> TireSystem
        </h4>
        <small class="text-light opacity-75">v1.0</small>
    </div>

    <!-- Menú de navegación -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'index.php' ? ' active' : '' ?>" href="index.php">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'neumaticos.php' ? ' active' : '' ?>" href="neumaticos.php">
                <i class="bi bi-circle me-2"></i> Neumáticos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'equipos.php' ? ' active' : '' ?>" href="equipos.php">
                <i class="bi bi-truck me-2"></i> Equipos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'instalaciones.php' ? ' active' : '' ?>" href="instalaciones.php">
                <i class="bi bi-arrow-repeat me-2"></i> Instalaciones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'seguimiento.php' ? ' active' : '' ?>" href="seguimiento.php">
                <i class="bi bi-graph-up me-2"></i> Seguimiento
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'alertas.php' ? ' active' : '' ?>" href="alertas.php">
                <i class="bi bi-exclamation-triangle me-2"></i> Alertas
                <?php if ($alertas_count > 0): ?>
                <span class="badge bg-danger ms-1"><?= $alertas_count ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'reportes2.php' || $current_page == 'reportes2.php' ? ' active' : '' ?>"
                href="reportes2.php">
                <i class="bi bi-file-text me-2"></i> Reportes
            </a>
        </li>

        <!-- Separador -->
        <li class="nav-item">
            <hr class="text-light my-3 opacity-50">
        </li>

        <!-- Configuración y salir -->
        <li class="nav-item">
            <a class="nav-link<?= $current_page == 'configuracion.php' ? ' active' : '' ?>" href="configuracion.php">
                <i class="bi bi-gear me-2"></i> Configuración
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php" onclick="return confirm('¿Está seguro que desea salir?')">
                <i class="bi bi-box-arrow-right me-2"></i> Salir
            </a>
        </li>
    </ul>

    <!-- Información del usuario (fijo en la parte inferior) -->
    <div class="position-absolute bottom-0 start-0 end-0 p-3">
        <div class="text-center text-light">
            <div class="bg-white bg-opacity-10 rounded p-2">
                <small class="d-block">
                    <i class="bi bi-person-circle me-1"></i>
                    <strong><?= htmlspecialchars($user_name) ?></strong>
                </small>
                <small class="text-light opacity-75">
                    <?= ucfirst(htmlspecialchars($user_role)) ?>
                </small>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar MÓVIL (offcanvas Bootstrap) -->
<div class="d-md-none">
    <button class="btn btn-dark m-2 position-fixed top-0 start-0" type="button" data-bs-toggle="offcanvas"
        data-bs-target="#offcanvasSidebar" style="z-index:1050;">
        <i class="bi bi-list"></i>
    </button>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header bg-dark text-white">
            <h5 class="offcanvas-title" id="offcanvasSidebarLabel">
                <i class="bi bi-gear-wide"></i> TireSystem
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Cerrar"></button>
        </div>

        <div class="offcanvas-body p-0 bg-light">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'index.php' ? ' bg-primary text-white' : '' ?>"
                        href="index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'neumaticos.php' ? ' bg-primary text-white' : '' ?>"
                        href="neumaticos.php">
                        <i class="bi bi-circle me-2"></i> Neumáticos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'equipos.php' ? ' bg-primary text-white' : '' ?>"
                        href="equipos.php">
                        <i class="bi bi-truck me-2"></i> Equipos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'instalaciones.php' ? ' bg-primary text-white' : '' ?>"
                        href="instalaciones.php">
                        <i class="bi bi-arrow-repeat me-2"></i> Instalaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'seguimiento.php' ? ' bg-primary text-white' : '' ?>"
                        href="seguimiento.php">
                        <i class="bi bi-graph-up me-2"></i> Seguimiento
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'alertas.php' ? ' bg-primary text-white' : '' ?>"
                        href="alertas.php">
                        <i class="bi bi-exclamation-triangle me-2"></i> Alertas
                        <?php if ($alertas_count > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $alertas_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'reportes2.php' || $current_page == 'reportes2.php' ? ' bg-primary text-white' : '' ?>"
                        href="reportes2.php">
                        <i class="bi bi-file-text me-2"></i> Reportes
                    </a>
                </li>

                <li class="nav-item">
                    <hr class="my-2">
                </li>

                <li class="nav-item">
                    <a class="nav-link text-dark<?= $current_page == 'configuracion.php' ? ' bg-primary text-white' : '' ?>"
                        href="configuracion.php">
                        <i class="bi bi-gear me-2"></i> Configuración
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php"
                        onclick="return confirm('¿Está seguro que desea salir?')">
                        <i class="bi bi-box-arrow-right me-2"></i> Salir
                    </a>
                </li>
            </ul>

            <!-- Información del usuario en móvil -->
            <div class="mt-auto p-3 bg-dark text-white">
                <div class="text-center">
                    <small class="d-block">
                        <i class="bi bi-person-circle me-1"></i>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                    </small>
                    <small class="text-light opacity-75">
                        <?= ucfirst(htmlspecialchars($user_role)) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos CSS mejorados -->
<style>
.sidebar {
    min-height: 100vh;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    transition: all 0.3s ease;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar .nav-link {
    color: #ecf0f1;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    margin: 0.25rem 0;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
}

.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
    color: #fff;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.2);
    color: #fff;
    font-weight: 600;
    border-left: 4px solid #3498db;
}

.sidebar .nav-link.text-danger:hover {
    background-color: rgba(231, 76, 60, 0.2);
    transform: translateX(5px);
}

/* Ajuste para el contenido principal cuando el sidebar está visible */
.main-content {
    margin-left: 0;
    transition: margin-left 0.3s ease;
}

@media (min-width: 768px) {
    .main-content {
        margin-left: 16.66667%;
        /* Ancho del sidebar en md (col-2) */
    }

    .sidebar.collapsed+.main-content {
        margin-left: 0;
    }
}

/* Mejoras para el offcanvas móvil */
.offcanvas-body .nav-link {
    padding: 0.75rem 1rem;
    border-radius: 0;
    margin: 0;
    border-bottom: 1px solid #dee2e6;
}

.offcanvas-body .nav-link:hover {
    background-color: #e9ecef;
}

.offcanvas-body .nav-link.bg-primary {
    border-color: #0d6efd;
}

/* Animación para el botón de toggle */
#toggleSidebar {
    transition: all 0.3s ease;
}

#toggleSidebar:hover {
    transform: scale(1.1);
    background-color: rgba(108, 117, 125, 0.2);
}

/* Scroll personalizado para el sidebar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>

<!-- JavaScript mejorado para el toggle del sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebarMenu');
    const mainContent = document.getElementById('mainContent');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function() {
            // Toggle classes para mostrar/ocultar sidebar
            sidebar.classList.toggle('d-md-block');
            sidebar.classList.toggle('d-none');
            sidebar.classList.toggle('collapsed');

            // Ajustar el contenido principal
            if (sidebar.classList.contains('d-none')) {
                mainContent.style.marginLeft = '0';
                toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                toggleBtn.title = 'Mostrar menú';
            } else {
                mainContent.style.marginLeft = '16.66667%';
                toggleBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                toggleBtn.title = 'Ocultar menú';
            }
        });
    }

    // Cerrar offcanvas automáticamente al hacer clic en un enlace (móvil)
    const offcanvasLinks = document.querySelectorAll('#offcanvasSidebar .nav-link');
    const offcanvas = document.getElementById('offcanvasSidebar');

    offcanvasLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (offcanvas && window.innerWidth < 768) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvas);
                if (bsOffcanvas) {
                    bsOffcanvas.hide();
                }
            }
        });
    });
});

// Ajustar el margin del contenido principal al redimensionar la ventana
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebarMenu');
    const mainContent = document.getElementById('mainContent');

    if (sidebar && mainContent && window.innerWidth >= 768) {
        if (!sidebar.classList.contains('d-none')) {
            mainContent.style.marginLeft = '16.66667%';
        } else {
            mainContent.style.marginLeft = '0';
        }
    } else if (mainContent && window.innerWidth < 768) {
        mainContent.style.marginLeft = '0';
    }
});
</script>