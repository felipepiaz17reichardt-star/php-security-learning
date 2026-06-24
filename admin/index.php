<?php
$ips_permitidos = ['127.0.0.1', '::1', '192.168.56.1']; // adiciona os IPs autorizados

if (!in_array($_SERVER['REMOTE_ADDR'], $ips_permitidos)) {
    http_response_code(404);
    exit; // parece que a página não existe
}
session_set_cookie_params([
    'httponly' => true,
    'secure'   => false,  // ←  false em localhost
    //'secure'   => true, // ← true em produção
    'samesite' => 'Strict'
]);
session_start();
require_once '../db/conexao.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha   = $_POST['senha'];

    if (empty($usuario) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmt = $conn->prepare("SELECT id, usuario, senha FROM administradores WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $admin = $res->fetch_assoc();
            if (password_verify($senha, $admin['senha'])) {
                //session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_user'] = $admin['usuario'];
                header("Location: dashboard.php");
                exit;
            }
        }
        $erro = "Credenciais inválidas.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Admin — CyberSec Dojo</title>
    <link rel="stylesheet" href="../CSS/index.css">
    <style>
        .login-box h2 {
            color: #bd00ff;
        }

        .login-box h2::before {
            color: #7a00aa;
        }

        .form-group input:focus {
            border-color: #bd00ff;
            box-shadow: 0 0 0 2px rgba(189, 0, 255, 0.08);
        }

        button[type="submit"] {
            border-color: #bd00ff;
            color: #bd00ff;
        }

        button[type="submit"]:hover {
            background: #bd00ff;
            color: #0a0c10;
        }

        .admin-badge {
            text-align: center;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #4a5568;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 24px;
            padding: 6px;
            border: 1px solid #1f2330;
            border-radius: 2px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>Admin Panel</h2>
        <div class="subtitle">Acesso restrito — administradores</div>
        <div class="admin-badge">⬡ Área Administrativa</div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="usuario">Usuário Admin</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit">Autenticar</button>
        </form>
    </div>
</body>

</html>