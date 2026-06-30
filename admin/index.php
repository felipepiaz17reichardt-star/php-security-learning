<?php
session_set_cookie_params([
    'httponly' => true,
    'secure'   => false,
    //'secure'   => true, // Para produção, habilite o HTTPS e descomente esta linha
    'samesite' => 'Strict'
]);
session_start();
require_once '../db/conexao.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

// ─── RESTRIÇÃO POR IP ────────────────────────────────────────────────────────
$ips_permitidos = ['127.0.0.1', '192.168.56.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $ips_permitidos)) {
    http_response_code(404);
    exit;
}

$erro      = "";
$bloqueado = false;
$tempo_restante = 0;

$ip                = $_SERVER['REMOTE_ADDR'];
$limite_tentativas = 3;   // Admin tem limite menor — 3 tentativas
$janela_minutos    = 30;  // E bloqueio mais longo — 30 minutos

function admin_contar_tentativas($conn, $ip, $usuario, $janela) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM tentativas_login
        WHERE ip = ? AND usuario = ?
        AND tentativa_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("ssi", $ip, $usuario, $janela);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

function admin_registrar_tentativa($conn, $ip, $usuario) {
    $stmt = $conn->prepare("INSERT INTO tentativas_login (ip, usuario) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $usuario);
    $stmt->execute();
}

function admin_tempo_bloqueio($conn, $ip, $usuario, $janela) {
    $stmt = $conn->prepare("
        SELECT tentativa_em FROM tentativas_login
        WHERE ip = ? AND usuario = ?
        AND tentativa_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY tentativa_em ASC
        LIMIT 1
    ");
    $stmt->bind_param("ssi", $ip, $usuario, $janela);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return 0;
    $desbloqueio = strtotime($row['tentativa_em']) + ($janela * 60);
    return max(0, ceil(($desbloqueio - time()) / 60));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha   = $_POST['senha'];

    if (empty($usuario) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $tentativas = admin_contar_tentativas($conn, $ip, $usuario, $janela_minutos);

        if ($tentativas >= $limite_tentativas) {
            $bloqueado      = true;
            $tempo_restante = admin_tempo_bloqueio($conn, $ip, $usuario, $janela_minutos);
            $erro = "Acesso bloqueado por {$tempo_restante} minuto(s) devido a muitas tentativas.";
        } else {
            $stmt = $conn->prepare("SELECT id, usuario, senha FROM administradores WHERE usuario = ?");
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $admin = $res->fetch_assoc();
                if (password_verify($senha, $admin['senha'])) {
                    // Login OK — limpa tentativas
                    $del = $conn->prepare("DELETE FROM tentativas_login WHERE ip = ? AND usuario = ?");
                    $del->bind_param("ss", $ip, $usuario);
                    $del->execute();

                    $_SESSION['admin_id']   = $admin['id'];
                    $_SESSION['admin_user'] = $admin['usuario'];
                    header("Location: dashboard.php");
                    exit;
                }
            }

            // Senha errada — registra tentativa
            admin_registrar_tentativa($conn, $ip, $usuario);
            $tentativas_restantes = $limite_tentativas - ($tentativas + 1);

            if ($tentativas_restantes <= 0) {
                $bloqueado      = true;
                $tempo_restante = $janela_minutos;
                $erro = "Acesso bloqueado por {$janela_minutos} minutos devido a muitas tentativas.";
            } else {
                $erro = "Credenciais inválidas. Tentativas restantes: {$tentativas_restantes}.";
            }
            $stmt->close();
        }
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
        .login-box h2 { color: #bd00ff; }
        .login-box h2::before { color: #7a00aa; }
        .form-group input:focus { border-color: #bd00ff; box-shadow: 0 0 0 2px rgba(189,0,255,0.08); }
        button[type="submit"] { border-color: #bd00ff; color: #bd00ff; }
        button[type="submit"]:hover { background: #bd00ff; color: #0a0c10; }
        button[type="submit"]:disabled { opacity: 0.4; cursor: not-allowed; }
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
                <input type="text" id="usuario" name="usuario" required autocomplete="off"
                       <?= $bloqueado ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required
                       <?= $bloqueado ? 'disabled' : '' ?>>
            </div>
            <button type="submit" <?= $bloqueado ? 'disabled' : '' ?>>
                <?= $bloqueado ? "Bloqueado por {$tempo_restante} min" : "Autenticar" ?>
            </button>
        </form>
    </div>
</body>
</html>
