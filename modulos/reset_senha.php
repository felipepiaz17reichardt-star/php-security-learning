<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];
$erro    = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = $_POST['senha_atual'];
    $senha_nova  = $_POST['senha_nova'];
    $senha_conf  = $_POST['senha_conf'];

    if (empty($senha_atual) || empty($senha_nova) || empty($senha_conf)) {
        $erro = "Preencha todos os campos.";
    } elseif (strlen($senha_nova) < 6) {
        $erro = "A nova senha deve ter pelo menos 6 caracteres.";
    } elseif ($senha_nova !== $senha_conf) {
        $erro = "A nova senha e a confirmação não coincidem.";
    } else {
        // Busca a senha atual do banco
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res  = $stmt->get_result()->fetch_assoc();

        if (!password_verify($senha_atual, $res['senha'])) {
            $erro = "Senha atual incorreta.";
        } else {
            $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $upd->bind_param("si", $novo_hash, $user_id);
            if ($upd->execute()) {
                $sucesso = "Senha alterada com sucesso!";
            } else {
                $erro = "Erro ao alterar senha. Tente novamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Alterar Senha — CyberSec Dojo</title>
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">Cyber<span>Sec</span> Dojo</div>
    <div class="navbar-user">
        <span><strong><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></strong></span>
        <a href="index.php" class="btn-logout">← Labs</a>
        <a href="../logout.php" class="btn-logout">Sair</a>
    </div>
</nav>

<div class="container" style="max-width: 480px;">
    <div class="page-header">
        <h1>Alterar Senha</h1>
        <p>Informe sua senha atual e escolha uma nova.</p>
    </div>

    <?php if ($sucesso): ?>
        <div class="result-box result-success show" style="margin-bottom: 24px;">
            ✓ <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="result-box result-fail show" style="margin-bottom: 24px;">
            ✗ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap" style="padding: 28px;">
        <form action="reset_senha.php" method="POST" class="vuln-form">
            <div class="form-group">
                <label>Senha atual</label>
                <input type="password" name="senha_atual" required>
            </div>
            <div class="form-group">
                <label>Nova senha</label>
                <input type="password" name="senha_nova" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirmar nova senha</label>
                <input type="password" name="senha_conf" required minlength="6">
            </div>
            <button type="submit" class="btn-vuln" style="width: 100%; margin-top: 8px; border-color: var(--accent); color: var(--accent);">
                Alterar Senha
            </button>
        </form>
    </div>
</div>

</body>
</html>
