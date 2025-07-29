<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reportes - Gestión de Neumáticos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .header h1 {
        color: #2c3e50;
        font-size: 2.5em;
        margin-bottom: 10px;
        font-weight: 300;
    }

    .header p {
        color: #7f8c8d;
        font-size: 1.1em;
    }

    .nav-tabs {
        display: flex;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 10px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow-x: auto;
        gap: 5px;
    }

    .nav-tab {
        flex: 1;
        min-width: 180px;
        padding: 15px 20px;
        border: none;
        background: transparent;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
    }

    .nav-tab:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
    }

    .nav-tab.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .nav-tab i {
        margin-right: 8px;
        font-size: 1.1em;
    }

    .report-section {
        display: none;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .report-section.active {
        display: block;
        animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .controls {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }

    .control-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .control-group label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9em;
    }

    .control-group input,
    .control-group select {
        padding: 10px 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        outline: none;
        transition: all 0.3s ease;
        min-width: 150px;
    }

    .control-group input:focus,
    .control-group select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        margin-top: auto;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .loading {
        text-align: center;
        padding: 50px;
        color: #667eea;
    }

    .loading i {
        font-size: 3em;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kpi-card {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 25px;
        border-radius: 15px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(-100%);
        transition: transform 0.6s ease;
    }

    .kpi-card:hover::before {
        transform: translateX(100%);
    }

    .kpi-value {
        font-size: 2.5em;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .kpi-label {
        font-size: 1em;
        opacity: 0.9;
        font-weight: 500;
    }

    .kpi-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 2em;
        opacity: 0.3;
    }

    .chart-container {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .chart-title {
        font-size: 1.3em;
        font-weight: 600;
        margin-bottom: 20px;
        color: #2c3e50;
        text-align: center;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .data-table th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }

    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.3s ease;
    }

    .data-table tr:hover td {
        background-color: #f8f9fa;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-critico {
        background: #dc3545;
        color: white;
    }

    .status-alto {
        background: #fd7e14;
        color: white;
    }

    .status-medio {
        background: #ffc107;
        color: #212529;
    }

    .status-bajo {
        background: #28a745;
        color: white;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-left: 4px solid;
    }

    .alert-warning {
        background: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }

    .alert-danger {
        background: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }

    .alert-info {
        background: #d1ecf1;
        border-color: #17a2b8;
        color: #0c5460;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin: 10px 0;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transition: width 0.8s ease;
    }

    .recommendations {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 25px;
        margin-top: 30px;
    }

    .recommendation-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: white;
        border-radius: 10px;
        margin-bottom: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .recommendation-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }

    .priority-alta {
        background: #dc3545;
    }

    .priority-media {
        background: #ffc107;
    }

    .priority-critica {
        background: #6f42c1;
    }

    .responsive-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }

        .nav-tabs {
            flex-direction: column;
        }

        .nav-tab {
            min-width: auto;
        }

        .controls {
            flex-direction: column;
            align-items: stretch;
        }

        .kpi-grid {
            grid-template-columns: 1fr;
        }

        .header h1 {
            font-size: 2em;
        }
    }

    .export-options {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Sistema de Reportes</h1>
            <p>Gestión Integral de Neumáticos - Dashboard Ejecutivo</p>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showReport('dashboard')">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
            <button class="nav-tab" onclick="showReport('estado-actual')">
                <i class="fas fa-truck"></i> Estado Actual
            </button>
            <button class="nav-tab" onclick="showReport('desgaste')">
                <i class="fas fa-chart-pie"></i> Desgaste
            </button>
            <button class="nav-tab" onclick="showReport('financiero')">
                <i class="fas fa-dollar-sign"></i> Financiero
            </button>
            <button class="nav-tab" onclick="showReport('movimientos')">
                <i class="fas fa-exchange-alt"></i> Movimientos
            </button>
            <button class="nav-tab" onclick="showReport('desechos')">
                <i class="fas fa-trash-alt"></i> Desechos
            </button>
            <button class="nav-tab" onclick="showReport('proyecciones')">
                <i class="fas fa-crystal-ball"></i> Proyecciones
            </button>
        </div>

        <!-- DASHBOARD PRINCIPAL -->
        <div id="dashboard" class="report-section active">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard Principal</h2>

            <div class="controls">
                <button class="btn btn-primary" onclick="loadDashboard()">
                    <i class="fas fa-sync-alt"></i> Actualizar Dashboard
                </button>
                <button class="btn btn-success" onclick="exportReport('dashboard')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="dashboard-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando dashboard...</p>
                </div>
            </div>
        </div>

        <!-- ESTADO ACTUAL -->
        <div id="estado-actual" class="report-section">
            <h2><i class="fas fa-truck"></i> Estado Actual de Equipos</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Equipo</label>
                    <select id="equipo-filter">
                        <option value="">Todos los equipos</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="loadEstadoActual()">
                    <i class="fas fa-search"></i> Consultar
                </button>
                <button class="btn btn-success" onclick="exportReport('estado_actual')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="estado-actual-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando estado actual...</p>
                </div>
            </div>
        </div>

        <!-- ANÁLISIS DE DESGASTE -->
        <div id="desgaste" class="report-section">
            <h2><i class="fas fa-chart-pie"></i> Análisis de Desgaste por Posición</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Fecha Inicio</label>
                    <input type="date" id="desgaste-fecha-inicio">
                </div>
                <div class="control-group">
                    <label>Fecha Fin</label>
                    <input type="date" id="desgaste-fecha-fin">
                </div>
                <button class="btn btn-primary" onclick="loadDesgaste()">
                    <i class="fas fa-chart-bar"></i> Analizar
                </button>
                <button class="btn btn-success" onclick="exportReport('desgaste_posicion')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="desgaste-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando análisis de desgaste...</p>
                </div>
            </div>
        </div>

        <!-- ANÁLISIS FINANCIERO -->
        <div id="financiero" class="report-section">
            <h2><i class="fas fa-dollar-sign"></i> Análisis Financiero y ROI</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Mes</label>
                    <select id="financiero-mes">
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7" selected>Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Año</label>
                    <select id="financiero-ano">
                        <option value="2024">2024</option>
                        <option value="2025" selected>2025</option>
                        <option value="2026">2026</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="loadFinanciero()">
                    <i class="fas fa-calculator"></i> Calcular
                </button>
                <button class="btn btn-success" onclick="exportReport('analisis_financiero')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="financiero-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando análisis financiero...</p>
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE MOVIMIENTOS -->
        <div id="movimientos" class="report-section">
            <h2><i class="fas fa-exchange-alt"></i> Historial de Movimientos</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Neumático</label>
                    <select id="neumatico-filter">
                        <option value="">Todos los neumáticos</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Fecha Inicio</label>
                    <input type="date" id="movimientos-fecha-inicio">
                </div>
                <div class="control-group">
                    <label>Fecha Fin</label>
                    <input type="date" id="movimientos-fecha-fin">
                </div>
                <button class="btn btn-primary" onclick="loadMovimientos()">
                    <i class="fas fa-history"></i> Consultar
                </button>
                <button class="btn btn-success" onclick="exportReport('historial_movimientos')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="movimientos-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando historial de movimientos...</p>
                </div>
            </div>
        </div>

        <!-- REPORTE DE DESECHOS -->
        <div id="desechos" class="report-section">
            <h2><i class="fas fa-trash-alt"></i> Reporte de Desechos</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Mes</label>
                    <select id="desechos-mes">
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7" selected>Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Año</label>
                    <select id="desechos-ano">
                        <option value="2024">2024</option>
                        <option value="2025" selected>2025</option>
                        <option value="2026">2026</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="loadDesechos()">
                    <i class="fas fa-recycle"></i> Analizar
                </button>
                <button class="btn btn-success" onclick="exportReport('desechos')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="desechos-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando reporte de desechos...</p>
                </div>
            </div>
        </div>

        <!-- PROYECCIONES -->
        <div id="proyecciones" class="report-section">
            <h2><i class="fas fa-crystal-ball"></i> Proyecciones de Compra</h2>

            <div class="controls">
                <div class="control-group">
                    <label>Meses a Proyectar</label>
                    <select id="proyecciones-meses">
                        <option value="3">3 meses</option>
                        <option value="6">6 meses</option>
                        <option value="12" selected>12 meses</option>
                        <option value="24">24 meses</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="loadProyecciones()">
                    <i class="fas fa-chart-line"></i> Proyectar
                </button>
                <button class="btn btn-success" onclick="exportReport('proyecciones')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>

            <div id="proyecciones-content">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando proyecciones...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
    // Variables globales
    let charts = {};
    const API_BASE = 'tiretrack360/reportes.php';


    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar fechas por defecto
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        const ninetyDaysAgo = new Date(today.getTime() - (90 * 24 * 60 * 60 * 1000));

        document.getElementById('desgaste-fecha-inicio').value = formatDate(thirtyDaysAgo);
        document.getElementById('desgaste-fecha-fin').value = formatDate(today);
        document.getElementById('movimientos-fecha-inicio').value = formatDate(ninetyDaysAgo);
        document.getElementById('movimientos-fecha-fin').value = formatDate(today);

        // Cargar opciones de equipos y neumáticos
        loadEquipos();
        loadNeumaticos();

        // Cargar dashboard inicial
        loadDashboard();
    });

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    function showReport(reportId) {
        // Ocultar todas las secciones
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.remove('active');
        });

        // Remover clase active de todos los tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Mostrar sección seleccionada
        document.getElementById(reportId).classList.add('active');

        // Activar tab correspondiente
        event.target.classList.add('active');
    }

    async function apiCall(endpoint, params = {}) {
        try {
            const url = new URL(endpoint, window.location.origin);
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== '') {
                    url.searchParams.append(key, params[key]);
                }
            });

            const response = await fetch(url.toString());

            if (!response.ok) {
                // Error HTTP: 404, 500, etc.
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text(); // por si llega HTML o algo más
                throw new Error('Respuesta no es JSON. Probablemente es HTML: ' + text.slice(0, 80));
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'La API respondió con éxito = false');
            }

            return data;

        } catch (error) {
            console.error('🚨 Error en apiCall():', error.message);
            // Si tienes un contenedor para mostrar errores al usuario:
            // document.getElementById('error-container').textContent = error.message;
            return {
                success: false,
                error: error.message
            };
        }
    }


    function showLoading(containerId) {
        document.getElementById(containerId).innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando datos...</p>
                </div>
            `;
    }

    function showError(containerId, message) {
        document.getElementById(containerId).innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error:</strong> ${message}
                </div>
            `;
    }

    async function loadEquipos() {
        try {
            const response = await fetch('api/equipos.php');
            const data = await response.json();

            const select = document.getElementById('equipo-filter');
            select.innerHTML = '<option value="">Todos los equipos</option>';

            if (data.success && data.equipos) {
                data.equipos.forEach(equipo => {
                    select.innerHTML +=
                        `<option value="${equipo.id}">${equipo.codigo} - ${equipo.nombre}</option>`;
                });
            }
        } catch (error) {
            console.error('Error cargando equipos:', error);
        }
    }

    async function loadNeumaticos() {
        try {
            const response = await fetch('api/neumaticos.php');
            const data = await response.json();

            const select = document.getElementById('neumatico-filter');
            select.innerHTML = '<option value="">Todos los neumáticos</option>';

            if (data.success && data.neumaticos) {
                data.neumaticos.forEach(neumatico => {
                    select.innerHTML +=
                        `<option value="${neumatico.id}">${neumatico.codigo_interno} - ${neumatico.numero_serie}</option>`;
                });
            }
        } catch (error) {
            console.error('Error cargando neumáticos:', error);
        }
    }

    async function loadDashboard() {
        showLoading('dashboard-content');

        try {
            const data = await apiCall(API_BASE, {
                tipo: 'dashboard_alertas'
            });
            renderDashboard(data.data);
        } catch (error) {
            showError('dashboard-content', error.message);
        }
    }

    function renderDashboard(data) {
        const container = document.getElementById('dashboard-content');

        // KPIs principales
        const kpisHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-tire"></i></div>
                        <div class="kpi-value">${data.kpis.neumaticos_activos || 0}</div>
                        <div class="kpi-label">Neumáticos Activos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="kpi-value">${data.kpis.alertas_pendientes || 0}</div>
                        <div class="kpi-label">Alertas Pendientes</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="kpi-value">$${formatNumber(data.kpis.valor_remanente_total || 0)}</div>
                        <div class="kpi-label">Valor Remanente Total</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="kpi-value">${Math.round(data.kpis.desgaste_promedio_flota || 0)}%</div>
                        <div class="kpi-label">Desgaste Promedio Flota</div>
                    </div>
                </div>
            `;

        // Alertas por tipo
        let alertasHtml = '<div class="chart-container"><div class="chart-title">Resumen de Alertas por Tipo</div>';
        if (data.alertas_resumen && data.alertas_resumen.length > 0) {
            alertasHtml +=
                '<table class="data-table"><thead><tr><th>Tipo de Alerta</th><th>Prioridad</th><th>Cantidad</th><th>Más Antigua</th></tr></thead><tbody>';
            data.alertas_resumen.forEach(alerta => {
                const badgeClass = `status-${alerta.prioridad.toLowerCase()}`;
                alertasHtml += `
                        <tr>
                            <td>${formatTipoAlerta(alerta.tipo_alerta)}</td>
                            <td><span class="status-badge ${badgeClass}">${alerta.prioridad}</span></td>
                            <td>${alerta.cantidad}</td>
                            <td>${formatDate(alerta.mas_antigua)}</td>
                        </tr>
                    `;
            });
            alertasHtml += '</tbody></table>';
        } else {
            alertasHtml +=
                '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay alertas pendientes</div>';
        }
        alertasHtml += '</div>';

        // Neumáticos críticos
        let criticosHtml = '<div class="chart-container"><div class="chart-title">Top 10 Neumáticos Críticos</div>';
        if (data.neumaticos_criticos && data.neumaticos_criticos.length > 0) {
            criticosHtml +=
                '<table class="data-table"><thead><tr><th>Equipo</th><th>Pos.</th><th>Código</th><th>Marca</th><th>Desgaste %</th><th>Días sin Medición</th><th>Alertas</th></tr></thead><tbody>';
            data.neumaticos_criticos.forEach(neumatico => {
                const desgasteClass = neumatico.desgaste >= 70 ? 'status-critico' : neumatico.desgaste >= 50 ?
                    'status-alto' : 'status-medio';
                criticosHtml += `
                        <tr>
                            <td>${neumatico.equipo}</td>
                            <td>${neumatico.posicion}</td>
                            <td>${neumatico.codigo_interno}</td>
                            <td>${neumatico.marca}</td>
                            <td><span class="status-badge ${desgasteClass}">${neumatico.desgaste}%</span></td>
                            <td>${neumatico.dias_sin_medicion}</td>
                            <td>${neumatico.alertas_pendientes}</td>
                        </tr>
                    `;
            });
            criticosHtml += '</tbody></table>';
        } else {
            criticosHtml +=
                '<div class="alert alert-info"><i class="fas fa-check-circle"></i> No hay neumáticos críticos detectados</div>';
        }
        criticosHtml += '</div>';

        // Eficiencia del sistema
        const eficienciaHtml = `
                <div class="chart-container">
                    <div class="chart-title">Eficiencia del Sistema</div>
                    <div class="responsive-grid">
                        <div>
                            <h4>Resolución de Alertas</h4>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${data.eficiencia_sistema.porcentaje_eficiencia}%"></div>
                            </div>
                            <p>${data.eficiencia_sistema.porcentaje_eficiencia}% de eficiencia</p>
                            <p>Tiempo promedio: ${Math.round(data.eficiencia_sistema.tiempo_promedio_resolucion || 0)} días</p>
                        </div>
                        <div>
                            <h4>Estadísticas (Últimos 30 días)</h4>
                            <p><strong>Alertas resueltas:</strong> ${data.eficiencia_sistema.resueltas || 0}</p>
                            <p><strong>Total de alertas:</strong> ${data.eficiencia_sistema.total || 0}</p>
                        </div>
                    </div>
                </div>
            `;

        container.innerHTML = kpisHtml + alertasHtml + criticosHtml + eficienciaHtml;
    }

    async function loadEstadoActual() {
        showLoading('estado-actual-content');

        try {
            const equipoId = document.getElementById('equipo-filter').value;
            const params = {
                tipo: 'estado_actual'
            };
            if (equipoId) params.equipo_id = equipoId;

            const data = await apiCall(API_BASE, params);
            renderEstadoActual(data.data);
        } catch (error) {
            showError('estado-actual-content', error.message);
        }
    }

    function renderEstadoActual(data) {
        const container = document.getElementById('estado-actual-content');

        // Resumen general
        const resumenHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-tire"></i></div>
                        <div class="kpi-value">${data.resumen.total_neumaticos}</div>
                        <div class="kpi-label">Total Neumáticos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="kpi-value">${formatNumber(data.resumen.valor_total_remanente)}</div>
                        <div class="kpi-label">Valor Total Remanente</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="kpi-value">${data.resumen.neumaticos_criticos}</div>
                        <div class="kpi-label">Neumáticos Críticos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                        <div class="kpi-value">${data.resumen.sin_medicion_14_dias}</div>
                        <div class="kpi-label">Sin Medición +14 Días</div>
                    </div>
                </div>
            `;

        // Tabla detallada
        let tablaHtml = '<div class="chart-container"><div class="chart-title">Estado Detallado por Equipo</div>';

        if (data.data && data.data.length > 0) {
            tablaHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Pos.</th>
                                <th>Código Neumático</th>
                                <th>Marca</th>
                                <th>Diseño</th>
                                <th>Cocada Inicial</th>
                                <th>Cocada Actual</th>
                                <th>% Desgaste</th>
                                <th>Horas Acum.</th>
                                <th>Valor Remanente</th>
                                <th>Nivel Riesgo</th>
                                <th>Días sin Medición</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.data.forEach(item => {
                const riesgoClass = `status-${item.nivel_riesgo.toLowerCase()}`;
                const diasClass = item.dias_sin_medicion > 14 ? 'status-critico' : item.dias_sin_medicion > 7 ?
                    'status-alto' : 'status-bajo';

                tablaHtml += `
                        <tr>
                            <td>${item.equipo_codigo}</td>
                            <td>${item.posicion}</td>
                            <td>${item.codigo_interno}</td>
                            <td>${item.marca}</td>
                            <td>${item.diseno}</td>
                            <td>${item.cocada_inicial}</td>
                            <td>${item.cocada_actual}</td>
                            <td><span class="status-badge ${riesgoClass}">${item.porcentaje_desgaste}%</span></td>
                            <td>${formatNumber(item.horas_acumuladas)}</td>
                            <td>${formatNumber(item.valor_remanente)}</td>
                            <td><span class="status-badge ${riesgoClass}">${item.nivel_riesgo}</span></td>
                            <td><span class="status-badge ${diasClass}">${item.dias_sin_medicion}</span></td>
                        </tr>
                    `;
            });

            tablaHtml += '</tbody></table>';
        } else {
            tablaHtml +=
                '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No se encontraron datos para mostrar</div>';
        }

        tablaHtml += '</div>';

        container.innerHTML = resumenHtml + tablaHtml;
    }

    async function loadDesgaste() {
        showLoading('desgaste-content');

        try {
            const fechaInicio = document.getElementById('desgaste-fecha-inicio').value;
            const fechaFin = document.getElementById('desgaste-fecha-fin').value;

            const data = await apiCall(API_BASE, {
                tipo: 'desgaste_posicion',
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            });

            renderDesgaste(data.data);
        } catch (error) {
            showError('desgaste-content', error.message);
        }
    }

    function renderDesgaste(data) {
        const container = document.getElementById('desgaste-content');

        // Gráfico de desgaste por posición
        const chartHtml = `
                <div class="chart-container">
                    <div class="chart-title">Desgaste Promedio por Posición</div>
                    <canvas id="desgasteChart" width="400" height="200"></canvas>
                </div>
            `;

        // Tabla de análisis por posición
        let tablaHtml = '<div class="chart-container"><div class="chart-title">Análisis Detallado por Posición</div>';

        if (data.data && data.data.length > 0) {
            tablaHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Posición</th>
                                <th>Grupo</th>
                                <th>Total Neumáticos</th>
                                <th>Desgaste Promedio</th>
                                <th>Desgaste Máximo</th>
                                <th>Horas Promedio</th>
                                <th>Requieren Rotación</th>
                                <th>Críticos</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.data.forEach(item => {
                const desgasteClass = item.desgaste_promedio >= 70 ? 'status-critico' :
                    item.desgaste_promedio >= 50 ? 'status-alto' :
                    item.desgaste_promedio >= 30 ? 'status-medio' : 'status-bajo';

                tablaHtml += `
                        <tr>
                            <td>${item.posicion}</td>
                            <td>${item.grupo_posicion}</td>
                            <td>${item.total_neumaticos}</td>
                            <td><span class="status-badge ${desgasteClass}">${Math.round(item.desgaste_promedio)}%</span></td>
                            <td>${Math.round(item.desgaste_maximo)}%</td>
                            <td>${formatNumber(item.horas_promedio)}</td>
                            <td>${item.requieren_rotacion}</td>
                            <td>${item.criticos}</td>
                        </tr>
                    `;
            });

            tablaHtml += '</tbody></table>';
        }

        tablaHtml += '</div>';

        // Eficiencia del modelo 30-30-30
        const eficienciaHtml = `
                <div class="chart-container">
                    <div class="chart-title">Eficiencia del Modelo 30-30-30</div>
                    <div class="responsive-grid">
                        <div>
                            <h4>Cumplimiento del Modelo</h4>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 85%"></div>
                            </div>
                            <p>85% de cumplimiento del modelo</p>
                        </div>
                        <div>
                            <h4>Estadísticas de Rotación</h4>
                            <p><strong>Delanteras que requieren rotación:</strong> ${data.eficiencia_modelo?.delanteras_30 || 0}</p>
                            <p><strong>Posteriores que requieren rotación:</strong> ${data.eficiencia_modelo?.posteriores_30 || 0}</p>
                            <p><strong>Intermedias que requieren rotación:</strong> ${data.eficiencia_modelo?.intermedias_30 || 0}</p>
                        </div>
                    </div>
                </div>
            `;

        container.innerHTML = chartHtml + tablaHtml + eficienciaHtml;

        // Crear gráfico
        setTimeout(() => {
            createDesgasteChart(data.data);
        }, 100);
    }

    function createDesgasteChart(data) {
        const ctx = document.getElementById('desgasteChart');
        if (!ctx) return;

        // Limpiar gráfico anterior si existe
        if (charts.desgaste) {
            charts.desgaste.destroy();
        }

        const labels = data.map(item => `Pos. ${item.posicion} (${item.grupo_posicion})`);
        const desgasteData = data.map(item => Math.round(item.desgaste_promedio));
        const horasData = data.map(item => Math.round(item.horas_promedio / 100)); // Escalar para visualización

        charts.desgaste = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Desgaste Promedio (%)',
                    data: desgasteData,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }, {
                    label: 'Horas Promedio (x100)',
                    data: horasData,
                    backgroundColor: 'rgba(118, 75, 162, 0.8)',
                    borderColor: 'rgba(118, 75, 162, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }

    async function loadFinanciero() {
        showLoading('financiero-content');

        try {
            const mes = document.getElementById('financiero-mes').value;
            const ano = document.getElementById('financiero-ano').value;

            const data = await apiCall(API_BASE, {
                tipo: 'analisis_financiero',
                mes: mes,
                ano: ano
            });

            renderFinanciero(data.data);
        } catch (error) {
            showError('financiero-content', error.message);
        }
    }

    function renderFinanciero(data) {
        const container = document.getElementById('financiero-content');

        // KPIs financieros
        const totalInversion = data.analisis_equipos.reduce((sum, item) => sum + parseFloat(item.inversion_total || 0),
            0);
        const totalDepreciacion = data.analisis_equipos.reduce((sum, item) => sum + parseFloat(item
            .depreciacion_acumulada || 0), 0);
        const costoPromedio = data.analisis_equipos.reduce((sum, item) => sum + parseFloat(item.costo_por_hora || 0),
            0) / data.analisis_equipos.length;

        const kpisHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="kpi-value">${formatNumber(totalInversion)}</div>
                        <div class="kpi-label">Inversión Total</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-chart-line-down"></i></div>
                        <div class="kpi-value">${formatNumber(totalDepreciacion)}</div>
                        <div class="kpi-label">Depreciación Acumulada</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                        <div class="kpi-value">${costoPromedio.toFixed(2)}</div>
                        <div class="kpi-label">Costo Promedio/Hora</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="kpi-value">${formatNumber(data.inversion_proyectada.total_3_meses)}</div>
                        <div class="kpi-label">Inversión Requerida 3M</div>
                    </div>
                </div>
            `;

        // Análisis por equipo
        let equiposHtml = '<div class="chart-container"><div class="chart-title">Análisis Financiero por Equipo</div>';
        if (data.analisis_equipos && data.analisis_equipos.length > 0) {
            equiposHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Total Neumáticos</th>
                                <th>Inversión Total</th>
                                <th>Valor Remanente</th>
                                <th>Depreciación</th>
                                <th>Horas Totales</th>
                                <th>Costo/Hora</th>
                                <th>Desgaste Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.analisis_equipos.forEach(equipo => {
                const desgasteClass = equipo.desgaste_promedio >= 70 ? 'status-critico' :
                    equipo.desgaste_promedio >= 50 ? 'status-alto' :
                    equipo.desgaste_promedio >= 30 ? 'status-medio' : 'status-bajo';

                equiposHtml += `
                        <tr>
                            <td>${equipo.equipo}</td>
                            <td>${equipo.total_neumaticos}</td>
                            <td>${formatNumber(equipo.inversion_total)}</td>
                            <td>${formatNumber(equipo.valor_remanente_total)}</td>
                            <td>${formatNumber(equipo.depreciacion_acumulada)}</td>
                            <td>${formatNumber(equipo.horas_totales)}</td>
                            <td>${parseFloat(equipo.costo_por_hora || 0).toFixed(2)}</td>
                            <td><span class="status-badge ${desgasteClass}">${Math.round(equipo.desgaste_promedio)}%</span></td>
                        </tr>
                    `;
            });

            equiposHtml += '</tbody></table>';
        }
        equiposHtml += '</div>';

        // Análisis por marca
        let marcasHtml = '<div class="chart-container"><div class="chart-title">Rendimiento por Marca</div>';
        if (data.analisis_marcas && data.analisis_marcas.length > 0) {
            marcasHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Marca</th>
                                <th>Total Neumáticos</th>
                                <th>Costo Promedio</th>
                                <th>Desgaste Promedio</th>
                                <th>Horas Promedio</th>
                                <th>Costo/Hora</th>
                                <th>Inversión Total</th>
                                <th>Críticos</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.analisis_marcas.forEach(marca => {
                const eficienciaClass = marca.costo_por_hora_marca <= 10 ? 'status-bajo' :
                    marca.costo_por_hora_marca <= 20 ? 'status-medio' :
                    marca.costo_por_hora_marca <= 30 ? 'status-alto' : 'status-critico';

                marcasHtml += `
                        <tr>
                            <td><strong>${marca.marca}</strong></td>
                            <td>${marca.total_neumaticos}</td>
                            <td>${formatNumber(marca.costo_promedio)}</td>
                            <td>${Math.round(marca.desgaste_promedio)}%</td>
                            <td>${formatNumber(marca.horas_promedio)}</td>
                            <td><span class="status-badge ${eficienciaClass}">${parseFloat(marca.costo_por_hora_marca || 0).toFixed(2)}</span></td>
                            <td>${formatNumber(marca.inversion_total)}</td>
                            <td>${marca.neumaticos_criticos}</td>
                        </tr>
                    `;
            });

            marcasHtml += '</tbody></table>';
        }
        marcasHtml += '</div>';

        // Proyecciones de inversión
        const proyeccionesHtml = `
                <div class="chart-container">
                    <div class="chart-title">Proyecciones de Inversión (Próximos 3 Meses)</div>
                    <div class="responsive-grid">
                        <div>
                            <h4>Requerimientos Inmediatos</h4>
                            <div class="kpi-value" style="color: #dc3545;">${formatNumber(data.inversion_proyectada.inmediato)}</div>
                            <p>Inversión requerida inmediata</p>
                        </div>
                        <div>
                            <h4>Este Mes</h4>
                            <div class="kpi-value" style="color: #fd7e14;">${formatNumber(data.inversion_proyectada.este_mes)}</div>
                            <p>Inversión requerida este mes</p>
                        </div>
                        <div>
                            <h4>Próximos 2 Meses</h4>
                            <div class="kpi-value" style="color: #ffc107;">${formatNumber(data.inversion_proyectada.proximos_2_meses)}</div>
                            <p>Inversión próximos 2 meses</p>
                        </div>
                        <div>
                            <h4>Total 3 Meses</h4>
                            <div class="kpi-value" style="color: #667eea;">${formatNumber(data.inversion_proyectada.total_3_meses)}</div>
                            <p>Inversión total proyectada</p>
                        </div>
                    </div>
                </div>
            `;

        container.innerHTML = kpisHtml + equiposHtml + marcasHtml + proyeccionesHtml;
    }

    async function loadMovimientos() {
        showLoading('movimientos-content');

        try {
            const neumaticoId = document.getElementById('neumatico-filter').value;
            const fechaInicio = document.getElementById('movimientos-fecha-inicio').value;
            const fechaFin = document.getElementById('movimientos-fecha-fin').value;

            const params = {
                tipo: 'historial_movimientos',
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            };

            if (neumaticoId) params.neumatico_id = neumaticoId;

            const data = await apiCall(API_BASE, params);
            renderMovimientos(data.data);
        } catch (error) {
            showError('movimientos-content', error.message);
        }
    }

    function renderMovimientos(data) {
        const container = document.getElementById('movimientos-content');

        // Estadísticas de movimientos
        const estadisticasHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="kpi-value">${data.estadisticas.total_movimientos}</div>
                        <div class="kpi-label">Total Movimientos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-sync-alt"></i></div>
                        <div class="kpi-value">${data.estadisticas.por_tipo.rotacion || 0}</div>
                        <div class="kpi-label">Rotaciones</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-plus-circle"></i></div>
                        <div class="kpi-value">${data.estadisticas.por_tipo.instalacion || 0}</div>
                        <div class="kpi-label">Instalaciones</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-percentage"></i></div>
                        <div class="kpi-value">${data.estadisticas.cumplimiento_30_30_30}%</div>
                        <div class="kpi-label">Cumplimiento 30-30-30</div>
                    </div>
                </div>
            `;

        // Tabla de movimientos
        let tablaHtml =
            '<div class="chart-container"><div class="chart-title">Historial Detallado de Movimientos</div>';

        if (data.movimientos && data.movimientos.length > 0) {
            tablaHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Código Neumático</th>
                                <th>Serie</th>
                                <th>Marca</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Tipo Movimiento</th>
                                <th>Tipo Rotación</th>
                                <th>Cocada</th>
                                <th>Horómetro</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.movimientos.forEach(mov => {
                const tipoClass = mov.tipo_movimiento === 'instalacion' ? 'status-bajo' :
                    mov.tipo_movimiento === 'rotacion' ? 'status-medio' : 'status-alto';

                tablaHtml += `
                        <tr>
                            <td>${formatDate(mov.fecha_movimiento)}</td>
                            <td>${mov.codigo_interno}</td>
                            <td>${mov.numero_serie || '-'}</td>
                            <td>${mov.marca}</td>
                            <td>${mov.equipo_origen ? `${mov.equipo_origen} (${mov.posicion_origen})` : '-'}</td>
                            <td>${mov.equipo_destino ? `${mov.equipo_destino} (${mov.posicion_destino})` : '-'}</td>
                            <td><span class="status-badge ${tipoClass}">${formatTipoMovimiento(mov.tipo_movimiento)}</span></td>
                            <td>${mov.tipo_rotacion}</td>
                            <td>${mov.cocada_movimiento || '-'}</td>
                            <td>${formatNumber(mov.horometro_movimiento)}</td>
                        </tr>
                    `;
            });

            tablaHtml += '</tbody></table>';
        } else {
            tablaHtml +=
                '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No se encontraron movimientos en el período seleccionado</div>';
        }

        tablaHtml += '</div>';

        container.innerHTML = estadisticasHtml + tablaHtml;
    }

    async function loadDesechos() {
        showLoading('desechos-content');

        try {
            const mes = document.getElementById('desechos-mes').value;
            const ano = document.getElementById('desechos-ano').value;

            const data = await apiCall(API_BASE, {
                tipo: 'desechos',
                mes: mes,
                ano: ano
            });

            renderDesechos(data.data);
        } catch (error) {
            showError('desechos-content', error.message);
        }
    }

    function renderDesechos(data) {
        const container = document.getElementById('desechos-content');

        // KPIs de desechos
        const kpisHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-trash-alt"></i></div>
                        <div class="kpi-value">${data.resumen.total_desechos}</div>
                        <div class="kpi-label">Total Desechados</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="kpi-value">${formatNumber(data.resumen.valor_recuperado)}</div>
                        <div class="kpi-label">Valor Recuperado</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                        <div class="kpi-value">${formatNumber(data.resumen.horas_totales)}</div>
                        <div class="kpi-label">Horas Totales</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-percentage"></i></div>
                        <div class="kpi-value">${data.resumen.porcentaje_recuperacion}%</div>
                        <div class="kpi-label">% Recuperación</div>
                    </div>
                </div>
            `;

        // Tabla de desechos
        let tablaHtml = '<div class="chart-container"><div class="chart-title">Detalle de Neumáticos Desechados</div>';

        if (data.desechos && data.desechos.length > 0) {
            tablaHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Serie</th>
                                <th>Marca</th>
                                <th>Fecha Desecho</th>
                                <th>Cocada Final</th>
                                <th>% Desgaste</th>
                                <th>Horas Trabajadas</th>
                                <th>Costo Original</th>
                                <th>Valor Remanente</th>
                                <th>Costo/Hora</th>
                                <th>Rendimiento</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.desechos.forEach(desecho => {
                const rendimientoClass = desecho.rendimiento_clasificacion === 'Excelente' ? 'status-bajo' :
                    desecho.rendimiento_clasificacion === 'Bueno' ? 'status-medio' :
                    desecho.rendimiento_clasificacion === 'Regular' ? 'status-alto' : 'status-critico';

                tablaHtml += `
                        <tr>
                            <td>${desecho.codigo_interno}</td>
                            <td>${desecho.numero_serie}</td>
                            <td>${desecho.marca}</td>
                            <td>${formatDate(desecho.fecha_desecho)}</td>
                            <td>${desecho.cocada_final}</td>
                            <td>${Math.round(desecho.porcentaje_desgaste_final)}%</td>
                            <td>${formatNumber(desecho.horas_totales_trabajadas)}</td>
                            <td>${formatNumber(desecho.costo_nuevo)}</td>
                            <td>${formatNumber(desecho.valor_remanente)}</td>
                            <td>${parseFloat(desecho.costo_hora || 0).toFixed(2)}</td>
                            <td><span class="status-badge ${rendimientoClass}">${desecho.rendimiento_clasificacion}</span></td>
                            <td>${desecho.motivo_desecho || 'No especificado'}</td>
                        </tr>
                    `;
            });

            tablaHtml += '</tbody></table>';
        } else {
            tablaHtml +=
                '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay neumáticos desechados en este período</div>';
        }

        tablaHtml += '</div>';

        // Análisis por marca
        let marcasHtml = '<div class="chart-container"><div class="chart-title">Análisis de Desechos por Marca</div>';

        if (data.analisis_por_marca && Object.keys(data.analisis_por_marca).length > 0) {
            marcasHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Marca</th>
                                <th>Cantidad</th>
                                <th>Inversión Total</th>
                                <th>Valor Recuperado</th>
                                <th>% Recuperación</th>
                                <th>Horas Promedio</th>
                                <th>Costo/Hora Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            Object.entries(data.analisis_por_marca).forEach(([marca, datos]) => {
                const recuperacionClass = datos.porcentaje_recuperacion >= 50 ? 'status-bajo' :
                    datos.porcentaje_recuperacion >= 30 ? 'status-medio' :
                    datos.porcentaje_recuperacion >= 15 ? 'status-alto' : 'status-critico';

                marcasHtml += `
                        <tr>
                            <td><strong>${marca}</strong></td>
                            <td>${datos.cantidad}</td>
                            <td>${formatNumber(datos.inversion_total)}</td>
                            <td>${formatNumber(datos.valor_recuperado)}</td>
                            <td><span class="status-badge ${recuperacionClass}">${datos.porcentaje_recuperacion}%</span></td>
                            <td>${formatNumber(datos.horas_promedio)}</td>
                            <td>${parseFloat(datos.costo_hora_promedio || 0).toFixed(2)}</td>
                        </tr>
                    `;
            });

            marcasHtml += '</tbody></table>';
        } else {
            marcasHtml +=
                '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay datos de marcas para analizar</div>';
        }

        marcasHtml += '</div>';

        container.innerHTML = kpisHtml + tablaHtml + marcasHtml;
    }

    async function loadProyecciones() {
        showLoading('proyecciones-content');

        try {
            const meses = document.getElementById('proyecciones-meses').value;

            const data = await apiCall(API_BASE, {
                tipo: 'proyecciones',
                meses: meses
            });

            renderProyecciones(data.data);
        } catch (error) {
            showError('proyecciones-content', error.message);
        }
    }

    function renderProyecciones(data) {
        const container = document.getElementById('proyecciones-content');

        // KPIs de proyección
        const kpisHtml = `
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="kpi-value">${formatNumber(data.total_inversion_proyectada)}</div>
                        <div class="kpi-label">Inversión Total Proyectada</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-tire"></i></div>
                        <div class="kpi-value">${data.proyecciones_detalle.length}</div>
                        <div class="kpi-label">Neumáticos a Reemplazar</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-calendar"></i></div>
                        <div class="kpi-value">${data.periodo_proyeccion}</div>
                        <div class="kpi-label">Meses Proyectados</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="kpi-value">${Object.keys(data.presupuesto_mensual).length}</div>
                        <div class="kpi-label">Meses con Reemplazos</div>
                    </div>
                </div>
            `;

        // Cronograma mensual
        let cronogramaHtml =
            '<div class="chart-container"><div class="chart-title">Cronograma de Reemplazos por Mes</div>';

        if (data.presupuesto_mensual && Object.keys(data.presupuesto_mensual).length > 0) {
            cronogramaHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mes/Año</th>
                                <th>Cantidad</th>
                                <th>Inversión Requerida</th>
                                <th>% del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            Object.entries(data.presupuesto_mensual).forEach(([mes, datos]) => {
                const porcentaje = ((datos.inversion_requerida / data.total_inversion_proyectada) * 100)
                    .toFixed(1);
                const intensidadClass = datos.cantidad >= 10 ? 'status-critico' :
                    datos.cantidad >= 5 ? 'status-alto' :
                    datos.cantidad >= 2 ? 'status-medio' : 'status-bajo';

                cronogramaHtml += `
                        <tr>
                            <td>${mes}</td>
                            <td><span class="status-badge ${intensidadClass}">${datos.cantidad}</span></td>
                            <td>${formatNumber(datos.inversion_requerida)}</td>
                            <td>${porcentaje}%</td>
                        </tr>
                    `;
            });

            cronogramaHtml += '</tbody></table>';
        } else {
            cronogramaHtml +=
                '<div class="alert alert-info"><i class="fas fa-check-circle"></i> No hay reemplazos proyectados para el período seleccionado</div>';
        }

        cronogramaHtml += '</div>';

        // Detalle de neumáticos a reemplazar
        let detalleHtml =
            '<div class="chart-container"><div class="chart-title">Detalle de Neumáticos Próximos a Reemplazar</div>';

        if (data.proyecciones_detalle && data.proyecciones_detalle.length > 0) {
            detalleHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Pos.</th>
                                <th>Código</th>
                                <th>Marca</th>
                                <th>Desgaste Actual</th>
                                <th>Semanas Restantes</th>
                                <th>Fecha Reemplazo Est.</th>
                                <th>Costo</th>
                                <th>Prioridad</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.proyecciones_detalle.forEach(proj => {
                const urgenciaClass = proj.semanas_restantes_estimadas <= 4 ? 'status-critico' :
                    proj.semanas_restantes_estimadas <= 8 ? 'status-alto' :
                    proj.semanas_restantes_estimadas <= 12 ? 'status-medio' : 'status-bajo';

                const desgasteClass = proj.desgaste_actual >= 80 ? 'status-critico' :
                    proj.desgaste_actual >= 60 ? 'status-alto' : 'status-medio';

                detalleHtml += `
                        <tr>
                            <td>${proj.equipo}</td>
                            <td>${proj.posicion}</td>
                            <td>${proj.codigo_interno}</td>
                            <td>${proj.marca}</td>
                            <td><span class="status-badge ${desgasteClass}">${Math.round(proj.desgaste_actual)}%</span></td>
                            <td><span class="status-badge ${urgenciaClass}">${proj.semanas_restantes_estimadas}</span></td>
                            <td>${formatDate(proj.fecha_reemplazo_estimada)}</td>
                            <td>${formatNumber(proj.costo_nuevo)}</td>
                            <td><span class="status-badge ${urgenciaClass}">
                                ${proj.semanas_restantes_estimadas <= 4 ? 'URGENTE' :
                                  proj.semanas_restantes_estimadas <= 8 ? 'ALTO' : 'MEDIO'}
                            </span></td>
                        </tr>
                    `;
            });

            detalleHtml += '</tbody></table>';
        }

        detalleHtml += '</div>';

        // Análisis por marca para negociación
        let negociacionHtml =
            '<div class="chart-container"><div class="chart-title">Análisis por Marca para Negociación</div>';

        if (data.analisis_por_marca && data.analisis_por_marca.length > 0) {
            negociacionHtml += `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Marca</th>
                                <th>Cantidad Proyectada</th>
                                <th>Inversión Proyectada</th>
                                <th>Costo Promedio</th>
                                <th>Primera Compra</th>
                                <th>Potencial Descuento</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

            data.analisis_por_marca.forEach(marca => {
                const volumenClass = marca.cantidad_proyectada >= 10 ? 'status-bajo' :
                    marca.cantidad_proyectada >= 5 ? 'status-medio' : 'status-alto';

                const descuentoPotencial = marca.cantidad_proyectada >= 10 ? '15-20%' :
                    marca.cantidad_proyectada >= 5 ? '10-15%' :
                    marca.cantidad_proyectada >= 3 ? '5-10%' : '0-5%';

                negociacionHtml += `
                        <tr>
                            <td><strong>${marca.marca}</strong></td>
                            <td><span class="status-badge ${volumenClass}">${marca.cantidad_proyectada}</span></td>
                            <td>${formatNumber(marca.inversion_proyectada)}</td>
                            <td>${formatNumber(marca.costo_promedio)}</td>
                            <td>${formatDate(marca.primera_compra)}</td>
                            <td><span class="status-badge ${volumenClass}">${descuentoPotencial}</span></td>
                        </tr>
                    `;
            });

            negociacionHtml += '</tbody></table>';
        }

        negociacionHtml += '</div>';

        // Recomendaciones
        const recomendacionesHtml = `
                <div class="recommendations">
                    <h3><i class="fas fa-lightbulb"></i> Recomendaciones de Compra</h3>
                    <div class="recommendation-item">
                        <div class="recommendation-icon priority-${data.recomendaciones_compra.negociacion_volumen ? 'alta' : 'media'}">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div>
                            <h4>Negociación por Volumen</h4>
                            <p>${data.recomendaciones_compra.negociacion_volumen ? 
                                'Recomendado: El volumen proyectado justifica negociar descuentos por volumen' : 
                                'No recomendado: Volumen insuficiente para negociación'}</p>
                        </div>
                    </div>
                    <div class="recommendation-item">
                        <div class="recommendation-icon priority-${data.recomendaciones_compra.compra_anticipada ? 'alta' : 'media'}">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <h4>Compra Anticipada</h4>
                            <p>${data.recomendaciones_compra.compra_anticipada ? 
                                'Recomendado: Considerar compras anticipadas para asegurar disponibilidad' : 
                                'No necesario: Compras pueden hacerse según calendario normal'}</p>
                        </div>
                    </div>
                    <div class="recommendation-item">
                        <div class="recommendation-icon priority-${data.recomendaciones_compra.diversificacion_marcas ? 'alta' : 'media'}">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div>
                            <h4>Diversificación de Marcas</h4>
                            <p>${data.recomendaciones_compra.diversificacion_marcas ? 
                                'Recomendado: Considerar diversificar proveedores para reducir riesgo' : 
                                'Opcional: La diversificación actual es adecuada'}</p>
                        </div>
                    </div>
                </div>
            `;

        container.innerHTML = kpisHtml + cronogramaHtml + detalleHtml + negociacionHtml + recomendacionesHtml;
    }

    async function exportReport(tipoReporte) {
        try {
            // Obtener parámetros según el tipo de reporte
            let params = {
                tipo: 'exportar_excel',
                sub_tipo: tipoReporte
            };

            switch (tipoReporte) {
                case 'estado_actual':
                    const equipoId = document.getElementById('equipo-filter').value;
                    if (equipoId) params.equipo_id = equipoId;
                    break;
                case 'desgaste_posicion':
                    params.fecha_inicio = document.getElementById('desgaste-fecha-inicio').value;
                    params.fecha_fin = document.getElementById('desgaste-fecha-fin').value;
                    break;
                case 'analisis_financiero':
                    params.mes = document.getElementById('financiero-mes').value;
                    params.ano = document.getElementById('financiero-ano').value;
                    break;
                    // Agregar más casos según necesidad
            }

            const data = await apiCall(API_BASE, params);

            if (data.success) {
                // Crear enlace de descarga
                const link = document.createElement('a');
                link.href = data.data.url_descarga;
                link.download = data.data.archivo;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Mostrar mensaje de éxito
                showNotification('Reporte exportado exitosamente', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            showNotification('Error exportando reporte: ' + error.message, 'error');
        }
    }

    // Funciones de utilidad
    function formatNumber(num) {
        return new Intl.NumberFormat('es-ES').format(Math.round(num || 0));
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('es-ES');
    }

    function formatTipoAlerta(tipo) {
        const tipos = {
            'rotacion_30': 'Rotación 30%',
            'desgaste_limite': 'Desgaste Límite',
            'mantenimiento': 'Mantenimiento',
            'garantia': 'Garantía'
        };
        return tipos[tipo] || tipo;
    }

    function formatTipoMovimiento(tipo) {
        const tipos = {
            'instalacion': 'Instalación',
            'rotacion': 'Rotación',
            'retiro': 'Retiro'
        };
        return tipos[tipo] || tipo;
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '1000';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
            `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    // Auto-refresh para dashboard cada 5 minutos
    setInterval(() => {
        if (document.querySelector('#dashboard.active')) {
            loadDashboard();
        }
    }, 300000); // 5 minutos
    </script>
</body>

</html>