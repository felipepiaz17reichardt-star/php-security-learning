<?php
session_start();

// DEFESA: Se a sessão não existir, chuta o cara de volta para o login
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Dojo</title>
    <link rel="stylesheet" href="CSS/dashboard.css"> </head>
<body>

    <div class="container">
        <h2>Bem-vindo ao CyberSec Dojo, <?php echo htmlspecialchars($_SESSION['admin_user']); ?>!</h2>
        <p>Este é o seu painel administrativo totalmente protegido contra acessos não autorizados.</p>
        
        <hr>
        
        <a href="logout.php" class="btn-logout">Sair do Sistema</a>
    </div>

</body>
</html>