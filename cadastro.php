<?php
session_set_cookie_params([
    'httponly' => true,
    'secure'   => false,  // ← false em localhost
    //'secure'   => true, // ← true em produção
    'samesite' => 'Strict'
]);
session_start();
require_once 'db/conexao.php';

// Já logado? Vai direto para os labs
if (isset($_SESSION['user_id'])) {
    header("Location: modulos/index.php");
    exit;
}

$erro   = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha   = $_POST['senha'];

    if (empty($usuario) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } elseif (strlen($usuario) < 3) {
        $erro = "Usuário deve ter pelo menos 3 caracteres.";
    } elseif (strlen($senha) < 6) {
        $erro = "Senha deve ter pelo menos 6 caracteres.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt_check->bind_param("s", $usuario);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            $erro = "Este nome de usuário já está em uso.";
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (usuario, senha) VALUES (?, ?)");
            $stmt->bind_param("ss", $usuario, $hash);

            if ($stmt->execute()) {
                header("Location: login.php?cadastro=sucesso");
                exit;
            } else {
                $erro = "Erro ao criar conta. Tente novamente.";
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro — CyberSec Dojo</title>
    <link rel="stylesheet" href="CSS/index.css">
</head>
<body>
    <div class="login-box">
        <h2>Criar Conta</h2>
        <div class="subtitle">Registre-se para acessar os laboratórios</div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="cadastro.php" method="POST">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario"
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required autocomplete="off" minlength="3">
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required minlength="6">
            </div>
            <button type="submit">Criar conta</button>
        </form>

        <a href="login.php" class="link-alt">Já tem uma conta? Faça login</a>
    </div>
</body>
</html>
