<?php
session_set_cookie_params([
    'httponly' => true,
    'secure'   => false,
    //'secure'   => true, // Para produção, habilite o HTTPS e descomente esta linha
    'samesite' => 'Strict'
]);
session_start();
require_once 'db/conexao.php';

if (isset($_SESSION['user_id'])) {
    header("Location: modulos/index.php");
    exit;
}

$erro    = "";
$sucesso = "";
$bloqueado = false;
$tempo_restante = 0;

// Mensagem vinda do cadastro
if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso') {
    $sucesso = "Conta criada! Faça login para começar.";
}

$ip = $_SERVER['REMOTE_ADDR'];
$limite_tentativas = 5;
$janela_minutos    = 15;

// Função: conta tentativas recentes deste IP + usuário
function contar_tentativas($conn, $ip, $usuario, $janela_minutos) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM tentativas_login
        WHERE ip = ? AND usuario = ?
        AND tentativa_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("ssi", $ip, $usuario, $janela_minutos);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

// Função: registra uma tentativa falha
function registrar_tentativa($conn, $ip, $usuario) {
    $stmt = $conn->prepare("INSERT INTO tentativas_login (ip, usuario) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $usuario);
    $stmt->execute();
}

// Função: calcula minutos restantes do bloqueio
function tempo_bloqueio($conn, $ip, $usuario, $janela_minutos) {
    $stmt = $conn->prepare("
        SELECT tentativa_em FROM tentativas_login
        WHERE ip = ? AND usuario = ?
        AND tentativa_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY tentativa_em ASC
        LIMIT 1
    ");
    $stmt->bind_param("ssi", $ip, $usuario, $janela_minutos);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return 0;
    $desbloqueio = strtotime($row['tentativa_em']) + ($janela_minutos * 60);
    return max(0, ceil(($desbloqueio - time()) / 60));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha   = $_POST['senha'];

    if (empty($usuario) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        // Verifica se está bloqueado ANTES de checar a senha
        $tentativas = contar_tentativas($conn, $ip, $usuario, $janela_minutos);

        if ($tentativas >= $limite_tentativas) {
            $bloqueado      = true;
            $tempo_restante = tempo_bloqueio($conn, $ip, $usuario, $janela_minutos);
            $erro = "Acesso bloqueado. Muitas tentativas incorretas. Tente novamente em {$tempo_restante} minuto(s).";
        } else {
            $stmt = $conn->prepare("SELECT id, usuario, senha FROM usuarios WHERE usuario = ?");
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();
                if (password_verify($senha, $user['senha'])) {
                    // Login OK — limpa tentativas deste IP+usuário
                    $del = $conn->prepare("DELETE FROM tentativas_login WHERE ip = ? AND usuario = ?");
                    $del->bind_param("ss", $ip, $usuario);
                    $del->execute();

                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['usuario'];
                    header("Location: modulos/index.php");
                    exit;
                }
            }

            // Senha errada — registra tentativa
            registrar_tentativa($conn, $ip, $usuario);
            $tentativas_restantes = $limite_tentativas - ($tentativas + 1);

            if ($tentativas_restantes <= 0) {
                $bloqueado      = true;
                $tempo_restante = $janela_minutos;
                $erro = "Acesso bloqueado por {$janela_minutos} minutos devido a muitas tentativas incorretas.";
            } else {
                $erro = "Usuário ou senha incorretos. Tentativas restantes: {$tentativas_restantes}.";
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
    <title>Login — CyberSec Dojo</title>
    <link rel="stylesheet" href="CSS/index.css">
</head>
<body>
    <div class="login-box">
        <h2>Dojo Login</h2>
        <div class="subtitle">Acesse sua conta para continuar</div>

        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off"
                       <?= $bloqueado ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required
                       <?= $bloqueado ? 'disabled' : '' ?>>
            </div>
            <button type="submit" <?= $bloqueado ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '' ?>>
                <?= $bloqueado ? "Bloqueado por {$tempo_restante} min" : "Entrar" ?>
            </button>
        </form>

        <a href="cadastro.php" class="link-alt">Não tem conta? Cadastre-se</a>
    </div>
</body>
</html>
