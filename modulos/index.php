<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];

// Busca labs ativos
$labs = $conn->query("SELECT * FROM labs WHERE ativo = 1 ORDER BY id");

// Busca progresso do usuário
$prog_stmt = $conn->prepare("SELECT lab_id FROM progresso WHERE usuario_id = ?");
$prog_stmt->bind_param("i", $user_id);
$prog_stmt->execute();
$prog_res = $prog_stmt->get_result();

$completados = [];
while ($row = $prog_res->fetch_assoc()) {
    $completados[$row['lab_id']] = true;
}

$total_labs      = $labs->num_rows;
$total_completos = count($completados);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Laboratórios — CyberSec Dojo</title>
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">Cyber<span>Sec</span> Dojo</div>
    <div class="navbar-user">
        <span>Olá, <strong><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></strong></span>
        <span style="color: var(--muted); font-size:11px;">
            <?= $total_completos ?>/<?= $total_labs ?> labs concluídos
        </span>
        <a href="../logout.php" class="btn-logout">Sair</a>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1>Laboratórios</h1>
        <p>Escolha um módulo para começar. Complete os desafios para registrar seu progresso.</p>
    </div>

    <div class="labs-grid">
        <?php
        $labs->data_seek(0);
        while ($lab = $labs->fetch_assoc()):
            $done      = isset($completados[$lab['id']]);
            $cat_class = strtolower($lab['categoria']) === 'xss' ? 'xss' : '';
            $file_map  = ['SQLi' => 'sqli.php', 'XSS' => 'xss.php', 'CSRF' => 'csrf.php'];
            $file      = $file_map[$lab['categoria']] ?? '#';
        ?>
        <a href="<?= $file ?>" class="lab-card <?= $cat_class ?>">
            <div class="lab-card-tag"><?= htmlspecialchars($lab['categoria'], ENT_QUOTES, 'UTF-8') ?></div>
            <h3><?= htmlspecialchars($lab['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($lab['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="lab-card-footer">
                <?php if ($done): ?>
                    <span class="badge-completed">✓ Concluído</span>
                <?php else: ?>
                    <span class="badge-pending">Pendente</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
