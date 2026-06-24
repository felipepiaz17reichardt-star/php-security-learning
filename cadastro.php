<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once 'db/conexao.php';

$erro = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $senha = $_POST['senha'];

    if (!empty($usuario) && !empty($senha)) {
        
        // DEFESA: Verifica se o usuário já existe na tabela 'clientes'
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt_check->bind_param("s", $usuario);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            $erro = "Este nome de usuário já está sendo usado!";
        } else {
            // SEGURANÇA: Cria o hash seguro da senha
            $senha_com_hash = password_hash($senha, PASSWORD_DEFAULT);

            // DEFESA: Insere o novo usuário comum no banco usando Prepared Statement
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (usuario, senha) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $usuario, $senha_com_hash);

            if ($stmt_insert->execute()) {
                // Redireciona para o login passando uma mensagem de sucesso na URL
                header("Location: login.php?cadastro=sucesso");
                exit;
            } else {
                $erro = "Algo deu errado ao criar a conta. Tente novamente.";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    } else {
        $erro = "Preencha todos os campos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - CyberSec Dojo</title>
    <link rel="stylesheet" href="CSS/index.css">
</head>
<body>

    <div class="login-box">
        <h2>Criar Conta</h2>
        <div class="subtitle">Cadastre seu usuário no Dojo</div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="cadastro.php" method="POST">
            <div class="form-group">
                <label for="usuario">Escolha um Usuário</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="senha">Escolha uma Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit">Cadastrar</button>
        </form>

        <div style="text-align: center; margin-top: 15px;">
            <a href="login.php" style="color: #0056b3; text-decoration: none; font-size: 14px;">Já tem uma conta? Faça login</a>
        </div>
    </div>

</body>
</html>