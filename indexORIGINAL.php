<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TireTrack Pro - Sistema de Gestión de Neumáticos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        .hero {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><pattern id="tire" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="50" cy="50" r="30" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></pattern></defs><rect width="100%" height="100%" fill="url(%23tire)"/></svg>') repeat;
            opacity: 0.1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #fff, #e0f2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-text .subtitle {
            font-size: 1.4rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .hero-text p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.8;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #f59e0b, #eab308);
            color: white;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(245, 158, 11, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .dashboard-preview {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: perspective(1000px) rotateY(-10deg) rotateX(5deg);
            transition: transform 0.5s ease;
        }

        .dashboard-preview:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #fbbf24;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .features {
            padding: 100px 0;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #1e3a8a, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.3rem;
            color: #64748b;
            margin-bottom: 80px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .feature-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #3b82f6, #06b6d4);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1e293b;
        }

        .feature-card p {
            color: #64748b;
            line-height: 1.7;
        }

        .benefits {
            padding: 100px 0;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
        }

        .benefits-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .benefits-list {
            list-style: none;
        }

        .benefits-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .benefit-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #f59e0b, #eab308);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .pricing {
            padding: 100px 0;
            background: white;
        }

        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 2px solid #e2e8f0;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .pricing-card.featured {
            border-color: #3b82f6;
            transform: scale(1.05);
        }

        .pricing-card.featured::before {
            content: 'MÁS POPULAR';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(45deg, #3b82f6, #06b6d4);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .price {
            font-size: 3rem;
            font-weight: 700;
            color: #1e293b;
            margin: 20px 0;
        }

        .price-period {
            font-size: 1rem;
            color: #64748b;
        }

        .contact {
            padding: 100px 0;
            background: linear-gradient(135deg, #f59e0b 0%, #eab308 100%);
            color: white;
            text-align: center;
        }

        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(20px);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            bottom: 30%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(10deg);
            }
        }

        @media (max-width: 768px) {

            .hero-content,
            .benefits-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .features-grid,
            .pricing-cards {
                grid-template-columns: 1fr;
            }

            .cta-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="floating-elements">
            <i class="fas fa-cog floating-element" style="font-size: 3rem;"></i>
            <i class="fas fa-chart-line floating-element" style="font-size: 2.5rem;"></i>
            <i class="fas fa-truck floating-element" style="font-size: 3.5rem;"></i>
        </div>

        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Tire Track 360</h1>
                    <div class="subtitle">Sistema Inteligente de Gestión de Neumáticos</div>
                    <p>Optimiza costos, maximiza rendimiento y toma decisiones informadas con el sistema más avanzado
                        para el control total de tu flota de neumáticos.</p>

                    <div class="cta-buttons">
                        <a href="#contact" class="btn btn-primary">
                            <i class="fas fa-rocket"></i>
                            Prueba Gratuita
                        </a>
                        <a href="#features" class="btn btn-secondary">
                            <i class="fas fa-play"></i>
                            Ver Demo
                        </a>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="dashboard-preview">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number">89%</div>
                                <div class="stat-label">Eficiencia</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">24</div>
                                <div class="stat-label">Equipos</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">$47K</div>
                                <div class="stat-label">Ahorrado</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">96</div>
                                <div class="stat-label">Neumáticos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Funcionalidades Revolucionarias</h2>
            <p class="section-subtitle">Todo lo que necesitas para gestionar tu flota de neumáticos de manera
                profesional y rentable</p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3>Registro Inteligente</h3>
                    <p>Crea equipos y registra neumáticos con historial completo. Cada unidad tiene número único, marca,
                        medida, cocada inicial y costo. Base de datos centralizada para control total.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3>Seguimiento Automático</h3>
                    <p>Monitoreo semanal de desgaste y rotación. Sistema que calcula automáticamente el consumo de
                        cocada y alerta cuando es necesario rotar o reemplazar neumáticos.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Modelo 30-30-30</h3>
                    <p>Sistema inteligente que distribuye el desgaste: 30% delanteras, 30% posteriores, 30% intermedias
                        y 10% margen de seguridad. Alertas automáticas para rotación óptima.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Reportes Detallados</h3>
                    <p>Informes mensuales completos por equipo: horas acumuladas, vida útil restante, costos actuales,
                        valor remanente y proyección de compras anuales.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Gráficos Ejecutivos</h3>
                    <p>Visualización clara del rendimiento por posiciones de neumáticos. Identifica patrones de
                        desgaste, planifica rotaciones y optimiza la vida útil de cada unidad.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Alertas Inteligentes</h3>
                    <p>Sistema de notificaciones automáticas que te avisa cuando es momento de rotar, reemplazar o dar
                        mantenimiento. Previene errores costosos y maximiza eficiencia.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits">
        <div class="container">
            <div class="benefits-content">
                <div>
                    <h2 class="section-title">¿Por qué elegir Tire Track 360?</h2>
                    <ul class="benefits-list">
                        <li>
                            <div class="benefit-icon"><i class="fas fa-dollar-sign"></i></div>
                            <span><strong>Reduce costos hasta 40%</strong> - Optimización inteligente de rotaciones y
                                reemplazos</span>
                        </li>
                        <li>
                            <div class="benefit-icon"><i class="fas fa-clock"></i></div>
                            <span><strong>Ahorra 15 horas semanales</strong> - Automatización de seguimiento y
                                reportes</span>
                        </li>
                        <li>
                            <div class="benefit-icon"><i class="fas fa-shield-alt"></i></div>
                            <span><strong>Previene errores humanos</strong> - Sistema automatizado elimina olvidos
                                costosos</span>
                        </li>
                        <li>
                            <div class="benefit-icon"><i class="fas fa-chart-bar"></i></div>
                            <span><strong>Decisiones basadas en datos</strong> - Análisis detallado de marcas y
                                rendimiento</span>
                        </li>
                        <li>
                            <div class="benefit-icon"><i class="fas fa-mobile-alt"></i></div>
                            <span><strong>Acceso desde cualquier lugar</strong> - Plataforma web responsive y
                                móvil</span>
                        </li>
                        <li>
                            <div class="benefit-icon"><i class="fas fa-users"></i></div>
                            <span><strong>Soporte especializado 24/7</strong> - Equipo de expertos siempre
                                disponible</span>
                        </li>
                    </ul>
                </div>

                <div style="text-align: center;">
                    <div
                        style="background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; backdrop-filter: blur(10px);">
                        <h3 style="font-size: 2.5rem; margin-bottom: 20px; color: #fbbf24;">+500</h3>
                        <p style="font-size: 1.2rem; margin-bottom: 30px;">Empresas confían en nosotros</p>

                        <h3 style="font-size: 2.5rem; margin-bottom: 20px; color: #fbbf24;">S/2.3M</h3>
                        <p style="font-size: 1.2rem; margin-bottom: 30px;">Ahorrados por nuestros clientes</p>

                        <h3 style="font-size: 2.5rem; margin-bottom: 20px; color: #fbbf24;">98%</h3>
                        <p style="font-size: 1.2rem;">Satisfacción del cliente</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="container">
            <h2 class="section-title">Planes que se Adaptan a tu Negocio</h2>
            <p class="section-subtitle">Desde pequeñas flotas hasta grandes corporaciones, tenemos el plan perfecto para
                ti</p>

            <div class="pricing-cards">
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <div class="price">S/299<span class="price-period">/mes</span></div>
                    <ul style="list-style: none; padding: 0; margin: 30px 0;">
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Hasta 5 equipos</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>50 neumáticos</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Reportes básicos</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Soporte por email</li>
                    </ul>
                    <a href="#contact" class="btn btn-secondary"
                        style="width: 100%; justify-content: center;">Comenzar</a>
                </div>

                <div class="pricing-card featured">
                    <h3>Professional</h3>
                    <div class="price">S/599<span class="price-period">/mes</span></div>
                    <ul style="list-style: none; padding: 0; margin: 30px 0;">
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Hasta 25 equipos</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>500 neumáticos</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Reportes avanzados</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Alertas inteligentes</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Soporte 24/7</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary" style="width: 100%; justify-content: center;">Más
                        Popular</a>
                </div>

                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="price">S/1,299<span class="price-period">/mes</span></div>
                    <ul style="list-style: none; padding: 0; margin: 30px 0;">
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Equipos ilimitados</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Neumáticos ilimitados</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>API personalizada</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Integración ERP</li>
                        <li style="margin: 10px 0;"><i class="fas fa-check"
                                style="color: #10b981; margin-right: 10px;"></i>Gerente dedicado</li>
                    </ul>
                    <a href="#contact" class="btn btn-secondary"
                        style="width: 100%; justify-content: center;">Contactar</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <h2 class="section-title" style="color: white; margin-bottom: 20px;">¿Listo para Revolucionar tu Gestión?
            </h2>
            <p style="font-size: 1.3rem; margin-bottom: 50px; opacity: 0.9;">Comienza tu prueba gratuita hoy y descubre
                por qué somos líderes en gestión de neumáticos</p>

            <div class="contact-form">
                <form id="demoForm" action="process_demo.php" method="POST">
                    <div class="form-group">
                        <label for="name">Nombre Completo *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="company">Empresa *</label>
                        <input type="text" id="company" name="company" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Corporativo *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Teléfono *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label for="fleet">Tamaño de Flota</label>
                        <input type="text" id="fleet" name="fleet" placeholder="Ej: 15 camiones, 60 neumáticos">
                    </div>

                    <div class="form-group">
                        <label for="message">¿Qué desafíos enfrentas actualmente?</label>
                        <textarea id="message" name="message" rows="4"
                            placeholder="Cuéntanos sobre tu operación actual..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"
                        style="width: 100%; justify-content: center; font-size: 1.2rem; padding: 20px;">
                        <i class="fas fa-rocket"></i>
                        Solicitar Demo Personalizado
                    </button>
                </form>

                <div style="margin-top: 30px; text-align: center;">
                    <p style="opacity: 0.9; font-size: 1.1rem; margin-bottom: 20px;">
                        <i class="fab fa-whatsapp" style="color: #25D366; font-size: 1.3rem; margin-right: 10px;"></i>
                        <strong>Contacto directo WhatsApp:</strong>
                    </p>
                    <div
                        style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px;">
                        <a href="https://wa.me/51970881946" target="_blank"
                            style="color: white; text-decoration: none; background: rgba(37, 211, 102, 0.2); padding: 10px 20px; border-radius: 25px; border: 2px solid #25D366; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#25D366'"
                            onmouseout="this.style.background='rgba(37, 211, 102, 0.2)'">
                            <i class="fab fa-whatsapp"></i> +51 970 881 946
                        </a>
                        <a href="https://wa.me/51978504847" target="_blank"
                            style="color: white; text-decoration: none; background: rgba(37, 211, 102, 0.2); padding: 10px 20px; border-radius: 25px; border: 2px solid #25D366; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#25D366'"
                            onmouseout="this.style.background='rgba(37, 211, 102, 0.2)'">
                            <i class="fab fa-whatsapp"></i> +51 978 504 847
                        </a>
                    </div>
                    <p style="opacity: 0.8; margin-bottom: 10px;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 10px;"></i>
                        <strong>Oficina:</strong> Av. Pedro Ruiz 959, 2do Piso
                    </p>
                    <p style="opacity: 0.8;">🔒 Tus datos están seguros | 📞 Demo personalizada incluida | ✅ Sin
                        compromisos</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Form submission with AJAX and success redirect
        document.getElementById('demoForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando solicitud...';
            submitBtn.disabled = true;

            fetch('process_demo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success state
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> ¡Solicitud Enviada!';
                        submitBtn.style.background = 'linear-gradient(45deg, #10b981, #059669)';

                        // Create success modal
                        const successModal = document.createElement('div');
                        successModal.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.8);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 10000;
                        animation: fadeIn 0.3s ease;
                    `;

                        const priorityText = data.priority === 'urgente' ?
                            '<span style="color: #dc2626; font-weight: bold;">ALTA PRIORIDAD</span>' :
                            '<span style="color: #059669; font-weight: bold;">PRIORIDAD ALTA</span>';

                        const estimatedValue = data.estimated_value ?
                            `<p style="color: #059669; font-weight: bold; font-size: 1.1rem;">💰 Valor estimado: ${data.estimated_value} USD/mes</p>` :
                            '';

                        successModal.innerHTML = `
                        <div style="
                            background: white;
                            padding: 40px;
                            border-radius: 20px;
                            max-width: 500px;
                            width: 90%;
                            text-align: center;
                            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                            animation: slideIn 0.4s ease;
                        ">
                            <div style="font-size: 4rem; margin-bottom: 20px;">🎉</div>
                            <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 1.8rem;">
                                ¡Solicitud Enviada Exitosamente!
                            </h2>
                            <div style="background: #ecfdf5; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #10b981;">
                                <h3 style="color: #065f46; margin: 0 0 10px 0;">
                                    📋 Solicitud ID: #${data.request_id}
                                </h3>
                                <p style="margin: 5px 0; color: #065f46;">
                                    📅 Fecha: ${new Date(data.timestamp).toLocaleString('es-PE')}
                                </p>
                                <p style="margin: 5px 0; color: #065f46;">
                                    ⚡ Estado: ${priorityText}
                                </p>
                                ${estimatedValue}
                            </div>
                            
                            <div style="background: #eff6ff; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #3b82f6;">
                                <h4 style="color: #1e40af; margin: 0 0 15px 0;">📧 Confirmaciones Enviadas:</h4>
                                <p style="margin: 5px 0; color: #1e40af;">
                                    ✅ Email de confirmación enviado a tu correo
                                </p>
                                <p style="margin: 5px 0; color: #1e40af;">
                                    🚨 Alerta enviada al equipo de ventas
                                </p>
                            </div>
                            
                            <div style="background: #fef3c7; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                                <h4 style="color: #92400e; margin: 0 0 10px 0;">⏰ Próximos Pasos:</h4>
                                <p style="margin: 5px 0; color: #92400e; font-weight: 500;">
                                    Te contactaremos en las próximas 2 horas
                                </p>
                                <p style="margin: 10px 0 0 0;">
                                    <a href="https://wa.me/51970881946?text=Hola, solicité demo de Tire Track 360 (ID: ${data.request_id})" 
                                       style="color: #25D366; text-decoration: none; font-weight: bold;">
                                        📱 WhatsApp: +51 970 881 946
                                    </a> | 
                                    <a href="https://wa.me/51978504847?text=Hola, solicité demo de Tire Track 360 (ID: ${data.request_id})" 
                                       style="color: #25D366; text-decoration: none; font-weight: bold;">
                                        📱 +51 978 504 847
                                    </a>
                                </p>
                            </div>
                            
                            <div style="margin-top: 30px;">
                                <p style="color: #64748b; margin-bottom: 20px;">
                                    Serás redirigido automáticamente al sistema en <span id="countdown">5</span> segundos...
                                </p>
                                <button onclick="redirectToSystem()" style="
                                    background: linear-gradient(45deg, #1e3a8a, #3b82f6);
                                    color: white;
                                    border: none;
                                    padding: 15px 30px;
                                    border-radius: 50px;
                                    font-size: 1.1rem;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                    margin: 0 10px;
                                " onmouseover="this.style.transform='translateY(-2px)'" 
                                   onmouseout="this.style.transform='translateY(0)'">
                                    🚀 Ir al Sistema Ahora
                                </button>
                                <button onclick="closeModal()" style="
                                    background: rgba(107, 114, 128, 0.1);
                                    color: #374151;
                                    border: 2px solid #d1d5db;
                                    padding: 15px 30px;
                                    border-radius: 50px;
                                    font-size: 1.1rem;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                    margin: 0 10px;
                                " onmouseover="this.style.background='rgba(107, 114, 128, 0.2)'" 
                                   onmouseout="this.style.background='rgba(107, 114, 128, 0.1)'">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    `;

                        // Add CSS animations
                        const style = document.createElement('style');
                        style.textContent = `
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideIn {
                            from { 
                                opacity: 0; 
                                transform: translateY(-50px) scale(0.9); 
                            }
                            to { 
                                opacity: 1; 
                                transform: translateY(0) scale(1); 
                            }
                        }
                    `;
                        document.head.appendChild(style);

                        document.body.appendChild(successModal);

                        // Countdown timer
                        let countdown = 5;
                        const countdownElement = document.getElementById('countdown');
                        const countdownTimer = setInterval(() => {
                            countdown--;
                            if (countdownElement) {
                                countdownElement.textContent = countdown;
                            }
                            if (countdown <= 0) {
                                clearInterval(countdownTimer);
                                redirectToSystem();
                            }
                        }, 1000);

                        // Global functions for buttons
                        window.redirectToSystem = function() {
                            window.location.href = 'https://tiretrack360.com/sistema/';
                        };

                        window.closeModal = function() {
                            document.body.removeChild(successModal);
                            // Reset form
                            document.getElementById('demoForm').reset();
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            submitBtn.style.background = 'linear-gradient(45deg, #f59e0b, #eab308)';
                        };

                    } else {
                        throw new Error(data.message || 'Error al enviar la solicitud');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);

                    // Show error state
                    submitBtn.innerHTML =
                        '<i class="fas fa-exclamation-triangle"></i> Error - Inténtalo de nuevo';
                    submitBtn.style.background = 'linear-gradient(45deg, #ef4444, #dc2626)';

                    // Create error modal
                    const errorModal = document.createElement('div');
                    errorModal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                `;

                    errorModal.innerHTML = `
                    <div style="
                        background: white;
                        padding: 40px;
                        border-radius: 20px;
                        max-width: 400px;
                        width: 90%;
                        text-align: center;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    ">
                        <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
                        <h2 style="color: #dc2626; margin-bottom: 15px;">Error al Enviar</h2>
                        <p style="color: #6b7280; margin-bottom: 25px;">
                            Hubo un problema al enviar tu solicitud. Por favor intenta nuevamente o contactanos directamente.
                        </p>
                        <div style="margin: 20px 0;">
                            <a href="https://wa.me/51970881946?text=Hola, tuve problemas enviando la solicitud de demo de Tire Track 360" 
                               style="color: #25D366; text-decoration: none; font-weight: bold; display: block; margin: 5px 0;">
                                📱 WhatsApp: +51 970 881 946
                            </a>
                            <a href="https://wa.me/51978504847?text=Hola, tuve problemas enviando la solicitud de demo de Tire Track 360" 
                               style="color: #25D366; text-decoration: none; font-weight: bold; display: block; margin: 5px 0;">
                                📱 WhatsApp: +51 978 504 847
                            </a>
                        </div>
                        <button onclick="document.body.removeChild(this.closest('[style*=position]'))" style="
                            background: #ef4444;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            margin-top: 15px;
                        ">
                            Cerrar e Intentar Nuevamente
                        </button>
                    </div>
                `;

                    document.body.appendChild(errorModal);

                    // Reset button after 5 seconds
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.style.background = 'linear-gradient(45deg, #f59e0b, #eab308)';
                        if (document.body.contains(errorModal)) {
                            document.body.removeChild(errorModal);
                        }
                    }, 5000);
                });
        });

        // Add scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all feature cards and pricing cards
        document.querySelectorAll('.feature-card, .pricing-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Add floating animation to hero elements
        setInterval(() => {
            document.querySelectorAll('.floating-element').forEach((el, index) => {
                el.style.transform =
                    `translateY(${Math.sin(Date.now() * 0.001 + index) * 10}px) rotate(${Math.sin(Date.now() * 0.0008 + index) * 5}deg)`;
            });
        }, 50);

        // Counter animation for stats
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        element.textContent = Math.floor(current) + (element.textContent.includes('%') ? '%' :
                            element.textContent.includes(') ? '
                                K ' : '
                                ');
                            }, 20);
                    }

                    // Trigger counter animation when stats come into view
                    const statsObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const statNumbers = entry.target.querySelectorAll('.stat-number');
                                statNumbers.forEach((stat, index) => {
                                    const targets = [89, 24, 47, 96];
                                    setTimeout(() => {
                                        animateCounter(stat, targets[index]);
                                    }, index * 200);
                                });
                                statsObserver.unobserve(entry.target);
                            }
                        });
                    });

                    const dashboardPreview = document.querySelector('.dashboard-preview');
                    if (dashboardPreview) {
                        statsObserver.observe(dashboardPreview);
                    }
    </script>
</body>

</html>