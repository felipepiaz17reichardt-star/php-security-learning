<?php
session_set_cookie_params([
    'httponly' => true,
    'secure'   => false,  // ← false em localhost
    'samesite' => 'Strict'
]);
session_start();
require_once 'db/conexao.php';

// Já logado? Vai direto para os labs
if (isset($_SESSION['user_id'])) {
    header("Location: modulos/index.php");
    exit;
}

$erro    = "";
$sucesso = "";

// Mensagem vinda do cadastro
if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso') {
    $sucesso = "Conta criada! Faça login para começar.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha   = $_POST['senha'];

    if (empty($usuario) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmt = $conn->prepare("SELECT id, usuario, senha FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if (password_verify($senha, $user['senha'])) {
                //session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['usuario'];
                header("Location: modulos/index.php");
                exit;
            }
        }
        // Mensagem genérica — não revela se usuário existe
        $erro = "Usuário ou senha incorretos.";
        $stmt->close();
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
                <input type="text" id="usuario" name="usuario" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit">Entrar</button>
        </form>

        <a href="cadastro.php" class="link-alt">Não tem conta? Cadastre-se</a>
    </div>
</body>
</html>
