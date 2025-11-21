<?php
session_start();

// Si ya está logueado, redirigir al dashboard correspondiente
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['rol']) {
        case 'gerente':
            header('Location: views/dashboard-gerente.php');
            break;
        case 'gerente_general':
            header('Location: views/dashboard-gerente-general.php');
            break;
        case 'admin_tecnico':
            header('Location: views/dashboard-admin.php');
            break;
        case 'tecnico':
            header('Location: views/dashboard-tecnico.php');
            break;
        case 'usuario':
        default:
            header('Location: views/dashboard-usuario.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Tickets TI - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h3 class="mb-0">🎫 Sistema de Tickets TI</h3>
            <p class="mb-0 mt-2" style="opacity: 0.9;">Ingresa tus credenciales</p>
        </div>
        
        <div class="login-body">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                    switch ($_GET['error']) {
                        case 'credenciales':
                            echo 'Email o contraseña incorrectos';
                            break;
                        case 'inactivo':
                            echo 'Tu cuenta está inactiva. Contacta al administrador.';
                            break;
                        default:
                            echo 'Error al iniciar sesión';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Sesión cerrada correctamente
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="actions/login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="usuario@empresa.com">
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    Iniciar Sesión
                </button>
            </form>

            <div class="mt-4 text-center">
                <small class="text-muted">
                    <strong>Usuarios de prueba:</strong><br>
                    gerente@empresa.com<br>
                    gerente.general@acp.com<br>
                    admin@empresa.com<br>
                    tecnico1@empresa.com<br>
                    usuario1@empresa.com<br>
                    <em>Password: password / password123</em>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>