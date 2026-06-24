<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../db/conexao.php';

// ─── ESTATÍSTICAS GERAIS ─────────────────────────────────────────────────────
$total_usuarios = $conn->query("SELECT COUNT(*) as c FROM usuarios")->fetch_assoc()['c'];
$total_labs     = $conn->query("SELECT COUNT(*) as c FROM labs WHERE ativo = 1")->fetch_assoc()['c'];
$total_completos = $conn->query("SELECT COUNT(*) as c FROM progresso")->fetch_assoc()['c'];

// ─── PROGRESSO POR USUÁRIO ────────────────────────────────────────────────────
$progresso_res = $conn->query("
    SELECT
        u.id,
        u.usuario,
        u.criado_em,
        COUNT(p.id) AS labs_completos,
        MAX(p.completado_em) AS ultimo_lab
    FROM usuarios u
    LEFT JOIN progresso p ON p.usuario_id = u.id
    GROUP BY u.id
    ORDER BY labs_completos DESC, u.criado_em DESC
");

// ─── ATIVIDADE RECENTE (últimas 20 ações) ─────────────────────────────────────
$atividade_res = $conn->query("
    SELECT
        u.usuario,
        l.titulo AS lab_titulo,
        l.categoria,
        p.completado_em
    FROM progresso p
    JOIN usuarios u ON u.id = p.usuario_id
    JOIN labs l ON l.id = p.lab_id
    ORDER BY p.completado_em DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin — CyberSec Dojo</title>
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">Cyber<span>Sec</span> Dojo <span style="color:#7a00aa; font-size:11px; margin-left:8px;">ADMIN</span></div>
    <div class="navbar-user">
        <span>Olá, <strong><?= htmlspecialchars($_SESSION['admin_user'], ENT_QUOTES, 'UTF-8') ?></strong></span>
        <a href="logout.php" class="btn-logout">Sair</a>
    </div>
</nav>

<div class="container">

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Visão geral do sistema — usuários, labs e progresso.</p>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-value"><?= $total_usuarios ?></div>
            <div class="stat-label">Usuários cadastrados</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_labs ?></div>
            <div class="stat-label">Labs ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_completos ?></div>
            <div class="stat-label">Labs concluídos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--accent2);">
                <?= $total_usuarios > 0 ? round(($total_completos / ($total_usuarios * max($total_labs,1))) * 100) : 0 ?>%
            </div>
            <div class="stat-label">Taxa de conclusão</div>
        </div>
    </div>

    <!-- PROGRESSO DOS USUÁRIOS -->
    <div class="table-wrap">
        <div class="table-wrap-header">Progresso por usuário</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuário</th>
                    <th>Cadastrado em</th>
                    <th>Labs completos</th>
                    <th>Progresso</th>
                    <th>Último lab</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($progresso_res->num_rows === 0): ?>
                    <tr><td colspan="6" style="color: var(--muted); text-align:center; padding: 24px;">Nenhum usuário cadastrado ainda.</td></tr>
                <?php else: ?>
                    <?php while ($row = $progresso_res->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--muted);"><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="color: var(--muted);"><?= date('d/m/Y H:i', strtotime($row['criado_em'])) ?></td>
                        <td>
                            <span style="color: <?= $row['labs_completos'] == $total_labs ? 'var(--success)' : 'var(--text)' ?>">
                                <?= $row['labs_completos'] ?>/<?= $total_labs ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $pct = $total_labs > 0 ? round(($row['labs_completos'] / $total_labs) * 100) : 0;
                            ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 80px; height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;">
                                    <div style="width: <?= $pct ?>%; height: 100%; background: var(--accent); border-radius: 2px;"></div>
                                </div>
                                <span style="color: var(--muted); font-size: 11px;"><?= $pct ?>%</span>
                            </div>
                        </td>
                        <td style="color: var(--muted);">
                            <?= $row['ultimo_lab'] ? date('d/m/Y H:i', strtotime($row['ultimo_lab'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ATIVIDADE RECENTE -->
    <div class="table-wrap">
        <div class="table-wrap-header">Atividade recente</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Lab concluído</th>
                    <th>Categoria</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($atividade_res->num_rows === 0): ?>
                    <tr><td colspan="4" style="color: var(--muted); text-align:center; padding: 24px;">Nenhuma atividade ainda.</td></tr>
                <?php else: ?>
                    <?php while ($a = $atividade_res->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($a['lab_titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="<?= strtolower($a['categoria']) === 'xss' ? 'tag-xss' : 'tag-sqli' ?>">
                                <?= htmlspecialchars($a['categoria'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td style="color: var(--muted);"><?= date('d/m/Y H:i', strtotime($a['completado_em'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="gerenciar_lab.php" style="display: inline-block; font-family: var(--mono); font-size: 12px; color: var(--muted); text-decoration: none; border: 1px solid var(--border); padding: 8px 16px; border-radius: 3px; transition: color 0.2s, border-color 0.2s;" onmouseover="this.style.color='var(--accent)';this.style.borderColor='var(--accent)'" onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border)'">
        → Gerenciar Labs
    </a>

</div>
</body>
</html>
