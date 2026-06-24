<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../db/conexao.php';

$msg = "";

// Toggle ativo/inativo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id = (int) $_POST['toggle_id'];
    $stmt = $conn->prepare("UPDATE labs SET ativo = NOT ativo WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $msg = "Lab atualizado.";
}

$labs = $conn->query("SELECT * FROM labs ORDER BY id");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Labs — CyberSec Dojo</title>
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

<div class="container">
    <div class="page-header">
        <h1>Gerenciar Labs</h1>
        <p>Ative ou desative laboratórios. Labs inativos não aparecem para os usuários.</p>
    </div>

    <?php if ($msg): ?>
        <div style="font-family: var(--mono); font-size: 12px; color: var(--success); margin-bottom: 20px;">✓ <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="table-wrap">
        <div class="table-wrap-header">Laboratórios cadastrados</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Categoria</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($lab = $labs->fetch_assoc()): ?>
                <tr>
                    <td style="color: var(--muted);"><?= $lab['id'] ?></td>
                    <td><?= htmlspecialchars($lab['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="<?= strtolower($lab['categoria']) === 'xss' ? 'tag-xss' : 'tag-sqli' ?>">
                            <?= htmlspecialchars($lab['categoria'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($lab['ativo']): ?>
                            <span style="color: var(--success); font-family: var(--mono); font-size: 11px;">● Ativo</span>
                        <?php else: ?>
                            <span style="color: var(--muted); font-family: var(--mono); font-size: 11px;">○ Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_id" value="<?= $lab['id'] ?>">
                            <button type="submit" style="background: transparent; border: 1px solid var(--border); color: var(--muted); font-family: var(--mono); font-size: 11px; padding: 4px 10px; border-radius: 2px; cursor: pointer;">
                                <?= $lab['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
