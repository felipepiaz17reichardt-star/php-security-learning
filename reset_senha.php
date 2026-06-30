<?php
require_once '../db/conexao.php';

$erro    = "";
$sucesso = "";

// Busca o usuário pelo ID passado na URL
$usuario_id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$usuario_nome = "";

if ($usuario_id > 0) {
    $stmt = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $usuario_nome = $res['usuario'];
    } else {
        $erro = "Usuário não encontrado.";
    }
} else {
    $erro = "ID de usuário inválido.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_id > 0 && empty($erro)) {
    $senha_nova = $_POST['senha_nova'];
    $senha_conf = $_POST['senha_conf'];

    if (empty($senha_nova) || empty($senha_conf)) {
        $erro = "Preencha todos os campos.";
    } elseif (strlen($senha_nova) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($senha_nova !== $senha_conf) {
        $erro = "A nova senha e a confirmação não coincidem.";
    } else {
        $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $upd->bind_param("si", $novo_hash, $usuario_id);
        if ($upd->execute()) {
            $sucesso = "Senha de \"" . htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') . "\" redefinida com sucesso.";
        } else {
            $erro = "Erro ao redefinir senha. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha — CyberSec Dojo Admin</title>
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">Cyber<span>Sec</span> Dojo <span style="color:#7a00aa; font-size:11px; margin-left:8px;">ADMIN</span></div>
    <div class="navbar-user">
        <a href="dashboard.php" class="btn-logout">← Dashboard</a>
        <a href="logout.php" class="btn-logout">Sair</a>
    </div>
</nav>

<div class="container" style="max-width: 480px;">
    <div class="page-header">
        <h1>Redefinir Senha</h1>
        <?php if ($usuario_nome): ?>
            <p>Definindo nova senha para o usuário <strong style="color: var(--text);"><?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        <?php endif; ?>
    </div>

    <?php if ($sucesso): ?>
        <div class="result-box result-success show" style="margin-bottom: 24px;">
            ✓ <?= $sucesso ?>
        </div>
        <a href="dashboard.php" class="btn-logout" style="display:inline-block; padding: 10px 20px; font-family: var(--mono); font-size: 12px;">
            ← Voltar ao dashboard
        </a>

    <?php elseif ($erro && !$usuario_nome): ?>
        <div class="result-box result-fail show" style="margin-bottom: 24px;">
            ✗ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <a href="dashboard.php" class="btn-logout" style="display:inline-block; padding: 10px 20px; font-family: var(--mono); font-size: 12px;">
            ← Voltar ao dashboard
        </a>

    <?php else: ?>
        <?php if ($erro): ?>
            <div class="result-box result-fail show" style="margin-bottom: 24px;">
                ✗ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="padding: 28px;">
            <form action="reset_senha.php?id=<?= $usuario_id ?>" method="POST" class="vuln-form">
                <div class="form-group">
                    <label>Senha Atual</label>
                    <input type="password" name="senha_atual" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Nova senha</label>
                    <input type="password" name="senha_nova" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirmar nova senha</label>
                    <input type="password" name="senha_conf" required minlength="6">
                </div>
                <button type="submit" class="btn-vuln" style="width:100%; margin-top:8px; border-color:var(--accent2); color:var(--accent2);">
                    Redefinir Senha
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
