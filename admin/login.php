<?php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once 'db/conexao.php';

// 1. Verifica se a tabela já tem alguém
$result = $conn->query("SELECT COUNT(*) as total FROM administradores");
$row = $result->fetch_assoc();

// Se o banco estiver totalmente vazio, cria o admin padrão com HASH
if ($row['total'] == 0) {
    $senha_com_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO administradores (usuario, senha) VALUES ('admin', '$senha_com_hash')");

    // Atualiza a página para o sistema reconhecer o novo usuário criado
    header("Location: index.php");
    exit;
}

$erro = "";

// 2. Processa o formulário de Login quando o botão é clicado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    // DEFESA CONTRA SQLi: Busca APENAS pelo usuário usando Prepared Statement
    $stmt = $conn->prepare("SELECT * FROM administradores WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $admin = $res->fetch_assoc();

        // VERIFICAÇÃO DO HASH: Confere se a senha bate com a criptografia do banco
        if (password_verify($senha, $admin['senha'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['usuario'];

            // Redireciona para o painel principal
            header("Location: dashboard.php");
            exit;
        } else {
            $erro = "Usuário ou senha incorretos!";
        }
    } else {
        $erro = "Usuário ou senha incorretos!";
    }
} // Fechamento correto do bloco POST
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Login - CyberSec Dojo</title>
    <link rel="stylesheet" href="CSS/index.css">
</head>

<body>

    <div class="login-box">
        <h2>Dojo Login</h2>
        <div class="subtitle">Insira usuario e senha</div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
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
    </div>

</body>
</html>