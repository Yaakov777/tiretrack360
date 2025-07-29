<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir PHPMailer
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'tire_management_system';
$username = 'root';
$password = '';

// Configuración de email
$email_config = [
    'smtp_host' => 'tiretrack360.com',
    'smtp_port' => 465,
    'smtp_secure' => PHPMailer::ENCRYPTION_SMTPS, // SSL para puerto 465
    'smtp_username' => 'ventas@tiretrack360.com',
    'smtp_password' => 'SeDipE1986@',
    'from_email' => 'ventas@tiretrack360.com',
    'from_name' => 'Tire Track 360 - Equipo de Ventas',
    'admin_email' => 'ventas@tiretrack360.com'
];

// Función para limpiar datos de entrada
function limpiarEntrada($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

// Función para enviar email con PHPMailer
function enviarEmailPHPMailer($to, $to_name, $subject, $body, $config, $is_html = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = $config['smtp_port'];

        // Configuración de caracteres
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Remitente y destinatario
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $to_name);
        $mail->addReplyTo($config['from_email'], $config['from_name']);

        // Contenido
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

// Template para email de confirmación al cliente
function getClientConfirmationTemplate($client_name, $request_id) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Demo Solicitada - Tire Track 360</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .highlight { background: #e0f2fe; padding: 20px; border-left: 4px solid #0ea5e9; margin: 25px 0; border-radius: 0 8px 8px 0; }
            .button { display: inline-block; background: #10b981; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; margin: 8px; font-weight: 600; }
            .whatsapp-button { background: #25D366; }
            .footer { background: #f8fafc; padding: 30px; text-align: center; color: #64748b; border-top: 1px solid #e2e8f0; }
            .logo { font-size: 24px; font-weight: bold; color: #1e3a8a; }
            ul { padding-left: 20px; }
            li { margin: 8px 0; }
            @media (max-width: 600px) {
                .content, .header { padding: 30px 20px; }
                .button { display: block; margin: 10px 0; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🚛 Tire Track 360</div>
                <h1>¡Demo Solicitada Exitosamente!</h1>
                <p>Tu solicitud #$request_id ha sido recibida</p>
            </div>
            
            <div class='content'>
                <h2>Estimado/a " . htmlspecialchars($client_name) . ",</h2>
                
                <p>¡Gracias por elegir <strong>Tire Track 360</strong>! Estamos emocionados de mostrarte cómo revolucionaremos la gestión de neumáticos en tu empresa.</p>
                
                <div class='highlight'>
                    <h3>🚀 Próximos Pasos</h3>
                    <ul>
                        <li><strong>Contacto inmediato:</strong> Te llamaremos en las próximas 2 horas</li>
                        <li><strong>Demo personalizada:</strong> Programaremos una sesión adaptada a tu flota</li>
                        <li><strong>Análisis gratuito:</strong> Evaluaremos tus necesidades específicas</li>
                        <li><strong>Propuesta comercial:</strong> Recibirás una cotización personalizada</li>
                    </ul>
                </div>
                
                <h3>💡 ¿Necesitas contacto inmediato?</h3>
                <p style='text-align: center;'>
                    <a href='https://wa.me/51970881946?text=Hola, solicité demo de Tire Track 360 (ID: $request_id)' class='button whatsapp-button'>
                        📱 WhatsApp: +51 970 881 946
                    </a>
                    <a href='https://wa.me/51978504847?text=Hola, solicité demo de Tire Track 360 (ID: $request_id)' class='button whatsapp-button'>
                        📱 WhatsApp: +51 978 504 847
                    </a>
                </p>
                
                <div class='highlight'>
                    <h3>✨ Lo que descubrirás en tu demo:</h3>
                    <ul>
                        <li>Sistema de seguimiento en tiempo real de toda tu flota</li>
                        <li>Modelo 30-30-30 de rotación inteligente automática</li>
                        <li>Reportes automáticos de costos y proyecciones de compra</li>
                        <li>Alertas preventivas que evitan paradas no programadas</li>
                        <li>Análisis de rendimiento por marca y posición</li>
                        <li>Dashboard ejecutivo para toma de decisiones</li>
                    </ul>
                </div>
                
                <h3>📍 Información de Contacto</h3>
                <ul>
                    <li><strong>Email:</strong> ventas@tiretrack360.com</li>
                    <li><strong>Oficina:</strong> Av. Pedro Ruiz 959, 2do Piso</li>
                    <li><strong>Horario:</strong> Lunes a Viernes 8:00 AM - 6:00 PM</li>
                    <li><strong>Sábados:</strong> 9:00 AM - 1:00 PM</li>
                </ul>
                
                <div style='background: #fef3c7; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                    <h3 style='color: #92400e; margin-top: 0;'>💰 ¿Sabías que nuestros clientes ahorran?</h3>
                    <ul style='color: #92400e;'>
                        <li><strong>40% en costos de neumáticos</strong> mediante rotación optimizada</li>
                        <li><strong>15 horas semanales</strong> en gestión administrativa</li>
                        <li><strong>S/50,000 anuales</strong> en flotas de tamaño medio</li>
                    </ul>
                </div>
            </div>
            
            <div class='footer'>
                <div class='logo' style='margin-bottom: 10px;'>🚛 Tire Track 360</div>
                <p><strong>Optimiza costos • Maximiza rendimiento • Toma decisiones inteligentes</strong></p>
                <p>© 2025 Tire Track 360. Todos los derechos reservados.</p>
                <p style='font-size: 12px; margin-top: 15px;'>
                    Recibiste este email porque solicitaste una demo en nuestro sitio web.<br>
                    Si no solicitaste esta demo, puedes ignorar este mensaje.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Template para email de notificación al equipo de ventas
function getAdminNotificationTemplate($data, $request_id) {
    $name = htmlspecialchars($data['name']);
    $company = htmlspecialchars($data['company']);
    $email = htmlspecialchars($data['email']);
    $phone = htmlspecialchars($data['phone']);
    $fleet = htmlspecialchars($data['fleet'] ?: 'No especificado');
    $message = htmlspecialchars($data['message'] ?: 'Sin mensaje adicional');
    $timestamp = date('d/m/Y H:i:s');
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 700px; margin: 0 auto; background: #ffffff; }
            .urgent-banner { background: #fbbf24; color: #92400e; padding: 15px; text-align: center; font-weight: bold; font-size: 18px; }
            .header { background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 25px; text-align: center; }
            .content { padding: 25px; }
            .info-box { background: #f8fafc; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6; border-radius: 0 8px 8px 0; }
            .urgent { background: #fef3c7; border-left-color: #f59e0b; }
            .success { background: #ecfdf5; border-left-color: #10b981; }
            .button { display: inline-block; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 8px 4px; font-weight: 600; }
            .whatsapp { background: #25D366; }
            .phone { background: #3b82f6; }
            .email-btn { background: #f59e0b; }
            .priority-high { background: #fecaca; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='urgent-banner'>
                🚨 ACCIÓN REQUERIDA: CONTACTAR EN LAS PRÓXIMAS 2 HORAS
            </div>
            
            <div class='header'>
                <h1>🎯 NUEVA SOLICITUD DE DEMO</h1>
                <h2>Tire Track 360 - ID #$request_id</h2>
                <p><span class='priority-high'>PRIORIDAD ALTA</span></p>
            </div>
            
            <div class='content'>
                <div class='info-box urgent'>
                    <h3>⚡ RESUMEN EJECUTIVO</h3>
                    <p><strong>Cliente:</strong> $name</p>
                    <p><strong>Empresa:</strong> $company</p>
                    <p><strong>Timestamp:</strong> $timestamp</p>
                    <p><strong>Fuente:</strong> Landing Page (Lead Directo)</p>
                    <p><strong>Probabilidad:</strong> 75% - Lead Caliente 🔥</p>
                </div>
                
                <div class='info-box'>
                    <h3>📞 INFORMACIÓN DE CONTACTO</h3>
                    <p><strong>Nombre:</strong> $name</p>
                    <p><strong>Empresa:</strong> $company</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Teléfono:</strong> $phone</p>
                    <p><strong>Flota:</strong> $fleet</p>
                </div>
                
                <div class='info-box'>
                    <h3>💭 MENSAJE DEL CLIENTE</h3>
                    <p style='font-style: italic; background: white; padding: 15px; border-radius: 6px;'>\"$message\"</p>
                </div>
                
                <div class='info-box success'>
                    <h3>🎯 ACCIONES INMEDIATAS</h3>
                    <p style='text-align: center; margin: 20px 0;'>
                        <a href='https://wa.me/$phone?text=Hola $name, soy del equipo de Tire Track 360. Te contacto por tu solicitud de demo para $company.' class='button whatsapp'>
                            📱 WhatsApp
                        </a>
                        <a href='tel:$phone' class='button phone'>
                            ☎️ Llamar Ahora
                        </a>
                        <a href='mailto:$email?subject=Demo Tire Track 360 - $company&body=Estimado/a $name,%0D%0A%0D%0AGracias por tu interés en Tire Track 360. Me complace contactarte para programar tu demo personalizada.%0D%0A%0D%0ASaludos,%0D%0AEquipo Tire Track 360' class='button email-btn'>
                            ✉️ Responder Email
                        </a>
                    </p>
                    
                    <h4>📋 CHECKLIST DE SEGUIMIENTO:</h4>
                    <ul style='background: white; padding: 15px; border-radius: 6px;'>
                        <li>☐ <strong>Contactar en máximo 2 horas</strong></li>
                        <li>☐ Calificar tamaño y tipo de flota</li>
                        <li>☐ Identificar pain points actuales</li>
                        <li>☐ Programar demo personalizada</li>
                        <li>☐ Enviar material pre-demo</li>
                        <li>☐ Actualizar estado en CRM</li>
                        <li>☐ Preparar propuesta comercial</li>
                    </ul>
                </div>
                
                <div class='info-box'>
                    <h3>💰 ANÁLISIS DE OPORTUNIDAD</h3>
                    <p><strong>Valor Mensual Estimado:</strong> S/299 - S/1,299 SOLES</p>
                    <p><strong>LTV Anual:</strong> S/3,588 - S/15,588 SOLES</p>
                    <p><strong>Probabilidad de Cierre:</strong> Alta (solicitud directa)</p>
                    <p><strong>Tiempo Estimado de Cierre:</strong> 7-14 días</p>
                </div>
                
                <div class='info-box urgent'>
                    <h3>⚠️ PUNTOS CRÍTICOS</h3>
                    <ul>
                        <li>🕐 <strong>Tiempo de respuesta crítico:</strong> Máximo 2 horas</li>
                        <li>🏆 <strong>Competencia:</strong> Posible evaluación paralela</li>
                        <li>💡 <strong>Oportunidad:</strong> Demostrar profesionalismo desde contacto inicial</li>
                        <li>📈 <strong>Upsell:</strong> Identificar necesidades adicionales durante demo</li>
                    </ul>
                </div>
                
                <div class='info-box success'>
                    <h3>🎯 ESTRATEGIA DE VENTA RECOMENDADA</h3>
                    <ul>
                        <li><strong>Fase 1:</strong> Contacto telefónico inmediato - Calificar necesidades</li>
                        <li><strong>Fase 2:</strong> Demo personalizada - Mostrar ROI específico para su flota</li>
                        <li><strong>Fase 3:</strong> Propuesta comercial - Incluir análisis de ahorro proyectado</li>
                        <li><strong>Fase 4:</strong> Seguimiento - Trial gratuito de 15 días si es necesario</li>
                    </ul>
                </div>
            </div>
            
            <div style='background: #1e293b; color: white; padding: 25px; text-align: center;'>
                <h3>🚀 Tire Track 360 - Sales Alert System</h3>
                <p><strong>Email automático generado el $timestamp</strong></p>
                <p style='margin-top: 15px;'>
                    <a href='https://wa.me/51970881946' style='color: #25D366; text-decoration: none; margin: 0 10px;'>
                        📱 +51 970 881 946
                    </a>
                    |
                    <a href='https://wa.me/51978504847' style='color: #25D366; text-decoration: none; margin: 0 10px;'>
                        📱 +51 978 504 847
                    </a>
                </p>
                <p style='font-size: 12px; opacity: 0.8; margin-top: 10px;'>
                    Sistema de notificaciones v2.0 - ventas@tiretrack360.com<br>
                    Av. Pedro Ruiz 959, 2do Piso
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

try {
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar y limpiar datos
        $name = limpiarEntrada($_POST['name'] ?? '');
        $company = limpiarEntrada($_POST['company'] ?? '');
        $email = filter_var(limpiarEntrada($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = limpiarEntrada($_POST['phone'] ?? '');
        $fleet = limpiarEntrada($_POST['fleet'] ?? '');
        $message = limpiarEntrada($_POST['message'] ?? '');
        
        // Validaciones básicas
        if (empty($name) || empty($company) || empty($email) || empty($phone)) {
            throw new Exception('Por favor completa todos los campos obligatorios.');
        }
        
        if (!$email) {
            throw new Exception('Por favor ingresa un email válido.');
        }
        
        // Validar teléfono (formato básico)
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone_clean) < 8) {
            throw new Exception('Por favor ingresa un número de teléfono válido.');
        }
        
        // Determinar prioridad basada en el tamaño de flota
        $priority = 'alta';
        if ($fleet && (
            strpos(strtolower($fleet), 'camion') !== false || 
            strpos(strtolower($fleet), 'flota') !== false ||
            preg_match('/\d{2,}/', $fleet) ||
            strpos(strtolower($fleet), 'minera') !== false ||
            strpos(strtolower($fleet), 'transport') !== false
        )) {
            $priority = 'urgente';
        }
        
        // Calcular valor estimado basado en flota
        $estimated_value = 599.00; // valor por defecto
        if ($fleet) {
            $numbers = [];
            preg_match_all('/\d+/', $fleet, $numbers);
            if (!empty($numbers[0])) {
                $max_number = max($numbers[0]);
                if ($max_number >= 20) $estimated_value = 1299.00;
                elseif ($max_number >= 10) $estimated_value = 899.00;
                elseif ($max_number >= 5) $estimated_value = 599.00;
                else $estimated_value = 299.00;
            }
        }
        
        // Insertar en la base de datos
        $sql = "INSERT INTO demo_requests (name, company, email, phone, fleet_size, message, status, priority, source, conversion_probability, estimated_value, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'nuevo', ?, 'landing_page', 75, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $company, $email, $phone, $fleet, $message, $priority, $estimated_value]);
        
        $request_id = $pdo->lastInsertId();
        
        // Preparar datos para emails
        $client_data = [
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'fleet' => $fleet,
            'message' => $message
        ];
        
        // Enviar email de confirmación al cliente
        $client_subject = "✅ Demo solicitada - Tire Track 360 (ID: #$request_id)";
        $client_body = getClientConfirmationTemplate($name, $request_id);
        $client_email_sent = enviarEmailPHPMailer($email, $name, $client_subject, $client_body, $email_config, true);
        
        // Enviar email de notificación al equipo de ventas
        $admin_subject = "🚨 NUEVA SOLICITUD DEMO #$request_id - CONTACTAR AHORA - Tire Track 360";
        $admin_body = getAdminNotificationTemplate($client_data, $request_id);
        $admin_email_sent = enviarEmailPHPMailer($email_config['admin_email'], 'Equipo de Ventas', $admin_subject, $admin_body, $email_config, true);
        
        // Registrar actividad en el log de contacto
        $contact_sql = "INSERT INTO contact_log (demo_request_id, contact_type, notes, contacted_by, contact_date, next_action, next_action_date) 
                       VALUES (?, 'email', 'Email de confirmación enviado automáticamente al cliente. Email de alerta enviado al equipo de ventas.', 'Sistema Automático', NOW(), 'Contactar por teléfono/WhatsApp', DATE_ADD(NOW(), INTERVAL 2 HOUR))";
        $contact_stmt = $pdo->prepare($contact_sql);
        $contact_stmt->execute([$request_id]);
        
        // Log de éxito para debugging
        error_log("Nueva solicitud de demo procesada exitosamente - ID: $request_id, Cliente: $name ($company), Email: $email, Prioridad: $priority, Valor estimado: $estimated_value");
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud enviada correctamente',
            'request_id' => $request_id,
            'client_email_sent' => $client_email_sent,
            'admin_email_sent' => $admin_email_sent,
            'priority' => $priority,
            'estimated_value' => $estimated_value,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error de base de datos en process_demo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor intenta nuevamente.',
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    // Error general
    error_log("Error en process_demo.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'VALIDATION_ERROR'
    ]);
}
?>