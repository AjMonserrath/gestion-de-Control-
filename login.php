<?php
session_start();

// Si quieres ver el login siempre que entres a esta página directamente:
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
}

// Comentamos o quitamos la redirección automática a index.php:
/*
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}
*/

$error = '';
// ... resto de tu código normal


// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    // Validación básica
    if (empty($usuario) || empty($contrasena)) {
        $error = 'Por favor, ingrese usuario y contraseña.';
    } else {
        // --- CONFIGURACIÓN DE USUARIOS ---
        // Aquí defines tus usuarios y contraseñas directamente.
        // Para producción, esto debería venir de una base de datos.

        $usuarios_correctos = [
            'admin' => 'admin123',  // Usuario: admin | Contraseña: admin123
            'tutor' => 'tutor123',  // Usuario: tutor | Contraseña: tutor123
            'demo'  => 'demo123'    // Usuario: demo  | Contraseña: demo123
        ];

        // Verificar si el usuario existe y la contraseña coincide
        if (isset($usuarios_correctos[$usuario]) && $usuarios_correctos[$usuario] === $contrasena) {
            // ¡Login exitoso!
            $_SESSION['usuario'] = $usuario;
            $_SESSION['nombre'] = ucfirst($usuario);
            header("Location: index.php");
            exit;
        } else {
            // Credenciales incorrectas
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login - Sistema Escolar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1877F2, #0d6efd);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: #fff;
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .login-body {
            padding: 2rem;
            background: #fff;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        }

        .btn-primary {
            background: #0d6efd;
            border: none;
        }

        .btn-primary:hover {
            background: #0b5ed7;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="login-header">
            <h2 class="fw-bold text-primary">
                <i class="bi bi-file-earmark-text"></i> Sistema Escolar
            </h2>
            <p class="text-muted mb-0">Inicia sesión para continuar</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario"
                            value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>