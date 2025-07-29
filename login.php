<?php
require_once 'config.php';

$error = '';

// Si ya está logueado, redirigir al dashboard
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Procesar login
if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        if (Auth::login($email, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Neumáticos - Login</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 1rem 1rem 0 0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            color: white;
        }

        .system-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 2rem;
        }

        .demo-credentials {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="login-container">
                    <div class="login-header text-center p-4">
                        <i class="bi bi-gear-wide h1 mb-3"></i>
                        <h3 class="mb-1">TireSystem</h3>
                        <p class="mb-0 opacity-75">Sistema de Gestión de Neumáticos</p>
                    </div>

                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> Email
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    placeholder="usuario@ejemplo.com" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> Contraseña
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="Ingrese su contraseña" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">
                                    Recordarme
                                </label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-login btn-lg" id="btnLogin">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>

                        <!-- Credenciales de demo -->
                        <div class="demo-credentials">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-info-circle"></i> Credenciales de Demo:
                            </h6>
                            <small class="text-muted">
                                <strong>Admin:</strong> admin@sistema.com<br>
                                <strong>Supervisor:</strong> supervisor@sistema.com<br>
                                <strong>Operador:</strong> operador@sistema.com<br>
                                <strong>Contraseña:</strong> password (para todos)
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Información del sistema -->
                <div class="system-info text-center text-white">
                    <h6><i class="bi bi-shield-check"></i> Sistema Seguro</h6>
                    <small class="opacity-75">
                        Control integral de neumáticos mineros<br>
                        Modelo 30-30-30 • Alertas automáticas • Reportes avanzados
                    </small>
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
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const passwordFieldType = passwordField.attr('type');
                const icon = $(this).find('i');

                if (passwordFieldType === 'password') {
                    passwordField.attr('type', 'text');
                    icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });

            // Auto-fill demo credentials
            $('.demo-credentials').on('click', function() {
                $('#email').val('admin@sistema.com');
                $('#password').val('password');
            });

            // Form validation and submission
            $('#loginForm').on('submit', function(e) {
                const email = $('#email').val().trim();
                const password = $('#password').val();

                if (!email || !password) {
                    e.preventDefault();
                    showAlert('Por favor complete todos los campos', 'warning');
                    return false;
                }

                // Show loading state
                $('#btnLogin').html('<i class="bi bi-hourglass-split"></i> Iniciando...').prop('disabled', true);
            });

            // Focus on first field
            $('#email').focus();

            // Enter key navigation
            $('#email').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#password').focus();
                }
            });
        });

        function showAlert(message, type = 'danger') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            $('.login-container .p-4').prepend(alertHtml);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>

</html>