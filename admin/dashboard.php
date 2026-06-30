<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
require_once '../db/conexao.php';

// ─── FILTROS DE PROGRESSO ─────────────────────────────────────────────────────
$busca_usuario  = trim($_GET['busca_usuario'] ?? '');
$filtro_labs    = $_GET['filtro_labs'] ?? '';   // '', '0', '1', '2', '3'...

// ─── FILTROS DE TENTATIVAS ────────────────────────────────────────────────────
$busca_ip       = trim($_GET['busca_ip'] ?? '');
$busca_alvo     = trim($_GET['busca_alvo'] ?? '');
$filtro_periodo = $_GET['periodo'] ?? '15';  // minutos: 15, 60, 1440, 'todos'

// ─── ESTATÍSTICAS GERAIS ─────────────────────────────────────────────────────
$total_usuarios  = $conn->query("SELECT COUNT(*) as c FROM usuarios")->fetch_assoc()['c'];
$total_labs      = $conn->query("SELECT COUNT(*) as c FROM labs WHERE ativo = 1")->fetch_assoc()['c'];
$total_completos = $conn->query("SELECT COUNT(*) as c FROM progresso")->fetch_assoc()['c'];

// ─── PROGRESSO POR USUÁRIO (com filtros) ──────────────────────────────────────
$where_prog   = [];
$params_prog  = [];
$types_prog   = "";

if ($busca_usuario !== '') {
    $where_prog[] = "u.usuario LIKE ?";
    $params_prog[] = "%{$busca_usuario}%";
    $types_prog   .= "s";
}

$having_prog = "";
if ($filtro_labs !== '') {
    $having_prog = "HAVING labs_completos = ?";
    $params_prog[] = (int) $filtro_labs;
    $types_prog   .= "i";
}

$where_sql = count($where_prog) ? "WHERE " . implode(" AND ", $where_prog) : "";

$sql_prog = "
    SELECT
        u.id,
        u.usuario,
        u.criado_em,
        COUNT(p.id) AS labs_completos,
        MAX(p.completado_em) AS ultimo_lab
    FROM usuarios u
    LEFT JOIN progresso p ON p.usuario_id = u.id
    {$where_sql}
    GROUP BY u.id
    {$having_prog}
    ORDER BY labs_completos DESC, u.criado_em DESC
";

if ($types_prog) {
    $stmt_prog = $conn->prepare($sql_prog);
    $stmt_prog->bind_param($types_prog, ...$params_prog);
    $stmt_prog->execute();
    $progresso_res = $stmt_prog->get_result();
} else {
    $progresso_res = $conn->query($sql_prog);
}

// ─── BLOQUEIOS ATIVOS ────────────────────────────────────────────────────────
$bloqueios_res = $conn->query("
    SELECT ip, usuario, COUNT(*) AS tentativas,
           MIN(tentativa_em) AS primeira, MAX(tentativa_em) AS ultima
    FROM tentativas_login
    WHERE tentativa_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    GROUP BY ip, usuario
    HAVING tentativas >= 5
    ORDER BY ultima DESC
");

// ─── TODAS AS TENTATIVAS (histórico completo, com filtros) ────────────────────
$where_tent  = [];
$params_tent = [];
$types_tent  = "";

if ($busca_ip !== '') {
    $where_tent[] = "ip LIKE ?";
    $params_tent[] = "%{$busca_ip}%";
    $types_tent   .= "s";
}
if ($busca_alvo !== '') {
    $where_tent[] = "usuario LIKE ?";
    $params_tent[] = "%{$busca_alvo}%";
    $types_tent   .= "s";
}
if ($filtro_periodo !== 'todos' && is_numeric($filtro_periodo)) {
    $where_tent[] = "tentativa_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $params_tent[] = (int) $filtro_periodo;
    $types_tent   .= "i";
}

$where_tent_sql = count($where_tent) ? "WHERE " . implode(" AND ", $where_tent) : "";

$sql_tent = "
    SELECT ip, usuario, COUNT(*) AS tentativas,
           MIN(tentativa_em) AS primeira, MAX(tentativa_em) AS ultima
    FROM tentativas_login
    {$where_tent_sql}
    GROUP BY ip, usuario
    ORDER BY ultima DESC
    LIMIT 50
";

if ($types_tent) {
    $stmt_tent = $conn->prepare($sql_tent);
    $stmt_tent->bind_param($types_tent, ...$params_tent);
    $stmt_tent->execute();
    $tentativas_res = $stmt_tent->get_result();
} else {
    $tentativas_res = $conn->query($sql_tent);
}

// Total de tentativas no histórico
$total_tentativas = $conn->query("SELECT COUNT(*) as c FROM tentativas_login")->fetch_assoc()['c'];

// ─── ATIVIDADE RECENTE ────────────────────────────────────────────────────────
$atividade_res = $conn->query("
    SELECT u.usuario, l.titulo AS lab_titulo, l.categoria, p.completado_em
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
    <style>
        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.3
            }
        }

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .filter-bar input,
        .filter-bar select {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 3px;
            padding: 6px 10px;
            color: var(--text);
            font-family: var(--mono);
            font-size: 12px;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--accent);
        }

        .filter-bar input::placeholder {
            color: var(--muted);
        }

        .filter-bar button {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 1px;
            padding: 6px 14px;
            border-radius: 3px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .filter-bar button:hover {
            background: var(--accent);
            color: var(--bg);
        }

        .filter-bar a.clear {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--muted);
            text-decoration: none;
        }

        .filter-bar a.clear:hover {
            color: var(--danger);
        }

        .filter-label {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .result-count {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--muted);
            margin-left: auto;
        }
    </style>
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
            <p>Visão geral do sistema — usuários, labs e segurança.</p>
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
                    <?= $total_usuarios > 0 ? round(($total_completos / ($total_usuarios * max($total_labs, 1))) * 100) : 0 ?>%
                </div>
                <div class="stat-label">Taxa de conclusão</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--warning);"><?= $total_tentativas ?></div>
                <div class="stat-label">Tentativas no histórico</div>
            </div>
        </div>

        <!-- PROGRESSO DOS USUÁRIOS -->
        <div class="table-wrap">
            <div class="table-wrap-header">Progresso por usuário</div>

            <!-- FILTROS DE PROGRESSO -->
            <form method="GET" action="dashboard.php">
                <div class="filter-bar">
                    <span class="filter-label">Filtrar:</span>
                    <input type="text" name="busca_usuario"
                        placeholder="Buscar por nome..."
                        value="<?= htmlspecialchars($busca_usuario, ENT_QUOTES, 'UTF-8') ?>"
                        style="width: 200px;">
                    <select name="filtro_labs">
                        <option value="">Todos os labs</option>
                        <?php for ($i = 0; $i <= $total_labs; $i++): ?>
                            <option value="<?= $i ?>" <?= $filtro_labs === (string)$i ? 'selected' : '' ?>>
                                <?= $i ?> lab<?= $i !== 1 ? 's' : '' ?> completo<?= $i !== 1 ? 's' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <!-- Preserva filtros de tentativas -->
                    <?php if ($busca_ip):     ?><input type="hidden" name="busca_ip" value="<?= htmlspecialchars($busca_ip, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <?php if ($busca_alvo):   ?><input type="hidden" name="busca_alvo" value="<?= htmlspecialchars($busca_alvo, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <?php if ($filtro_periodo !== '15'): ?><input type="hidden" name="periodo" value="<?= htmlspecialchars($filtro_periodo, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <button type="submit">Buscar</button>
                    <?php if ($busca_usuario !== '' || $filtro_labs !== ''): ?>
                        <a href="dashboard.php" class="clear">✕ Limpar</a>
                    <?php endif; ?>
                    <span class="result-count"><?= $progresso_res->num_rows ?> resultado(s)</span>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Usuário</th>
                        <th>Cadastrado em</th>
                        <th>Labs completos</th>
                        <th>Progresso</th>
                        <th>Último lab</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($progresso_res->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="color: var(--muted); text-align:center; padding: 24px;">Nenhum resultado encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $progresso_res->fetch_assoc()):
                            $pct = $total_labs > 0 ? round(($row['labs_completos'] / $total_labs) * 100) : 0;
                        ?>
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
                                <td>
                                    <a href="reset_senha.php?id=<?= $row['id'] ?>"
                                        style="font-family: var(--mono); font-size: 11px; color: var(--accent2); text-decoration: none; border: 1px solid rgba(189,0,255,0.3); padding: 3px 8px; border-radius: 2px; white-space: nowrap;">
                                        Redefinir senha
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- BLOQUEIOS ATIVOS -->
        <?php if ($bloqueios_res->num_rows > 0): ?>
            <div class="table-wrap" style="border-top: 2px solid var(--danger);">
                <div class="table-wrap-header" style="color: var(--danger); display: flex; align-items: center; gap: 8px;">
                    <span style="display:inline-block; width:8px; height:8px; background:var(--danger); border-radius:50%; animation: pulse 1s infinite;"></span>
                    Acessos bloqueados agora — brute force detectado
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Usuário alvo</th>
                            <th>Tentativas</th>
                            <th>Primeira tentativa</th>
                            <th>Última tentativa</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $bloqueios_res->fetch_assoc()): ?>
                            <tr>
                                <td style="color: var(--danger); font-family: var(--mono);"><?= htmlspecialchars($b['ip'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($b['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="color: var(--danger);"><?= $b['tentativas'] ?>x</td>
                                <td style="color: var(--muted);"><?= date('d/m/Y H:i:s', strtotime($b['primeira'])) ?></td>
                                <td style="color: var(--muted);"><?= date('d/m/Y H:i:s', strtotime($b['ultima'])) ?></td>
                                <td>
                                    <span style="font-family: var(--mono); font-size: 10px; color: var(--danger); border: 1px solid rgba(255,77,109,0.4); padding: 2px 8px; border-radius: 2px; background: rgba(255,77,109,0.07);">
                                        ⚠ BLOQUEADO
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- HISTÓRICO DE TENTATIVAS (com filtros) -->
        <div class="table-wrap">
            <div class="table-wrap-header" style="color: var(--warning);">
                ⚡ Histórico de tentativas de login — <?= $total_tentativas ?> registros no total
            </div>

            <!-- FILTROS DE TENTATIVAS -->
            <form method="GET" action="dashboard.php">
                <div class="filter-bar">
                    <span class="filter-label">Filtrar:</span>
                    <input type="text" name="busca_ip"
                        placeholder="Buscar por IP..."
                        value="<?= htmlspecialchars($busca_ip, ENT_QUOTES, 'UTF-8') ?>"
                        style="width: 160px;">
                    <input type="text" name="busca_alvo"
                        placeholder="Buscar por usuário alvo..."
                        value="<?= htmlspecialchars($busca_alvo, ENT_QUOTES, 'UTF-8') ?>"
                        style="width: 180px;">
                    <select name="periodo">
                        <option value="15" <?= $filtro_periodo === '15'    ? 'selected' : '' ?>>Últimos 15 min</option>
                        <option value="60" <?= $filtro_periodo === '60'    ? 'selected' : '' ?>>Última hora</option>
                        <option value="1440" <?= $filtro_periodo === '1440'  ? 'selected' : '' ?>>Últimas 24h</option>
                        <option value="10080" <?= $filtro_periodo === '10080' ? 'selected' : '' ?>>Última semana</option>
                        <option value="todos" <?= $filtro_periodo === 'todos' ? 'selected' : '' ?>>Todo o histórico</option>
                    </select>
                    <!-- Preserva filtros de progresso -->
                    <?php if ($busca_usuario !== ''): ?><input type="hidden" name="busca_usuario" value="<?= htmlspecialchars($busca_usuario, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <?php if ($filtro_labs !== ''):   ?><input type="hidden" name="filtro_labs" value="<?= htmlspecialchars($filtro_labs, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <button type="submit">Buscar</button>
                    <?php if ($busca_ip !== '' || $busca_alvo !== '' || $filtro_periodo !== '15'): ?>
                        <a href="dashboard.php" class="clear">✕ Limpar</a>
                    <?php endif; ?>
                    <span class="result-count"><?= $tentativas_res->num_rows ?> grupo(s) — mostrando top 50</span>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Usuário alvo</th>
                        <th>Total de tentativas</th>
                        <th>Primeira tentativa</th>
                        <th>Última tentativa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tentativas_res->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" style="color: var(--muted); text-align:center; padding: 24px;">Nenhuma tentativa encontrada para este filtro.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($t = $tentativas_res->fetch_assoc()):
                            $bloqueado_agora = $t['tentativas'] >= 5
                                && strtotime($t['ultima']) >= strtotime('-15 minutes');
                        ?>
                            <tr>
                                <td style="font-family: var(--mono); color: <?= $bloqueado_agora ? 'var(--danger)' : 'var(--text)' ?>;">
                                    <?= htmlspecialchars($t['ip'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($t['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="color: <?= $t['tentativas'] >= 5 ? 'var(--danger)' : 'var(--warning)' ?>;">
                                    <?= $t['tentativas'] ?>x
                                </td>
                                <td style="color: var(--muted);"><?= date('d/m/Y H:i:s', strtotime($t['primeira'])) ?></td>
                                <td style="color: var(--muted);"><?= date('d/m/Y H:i:s', strtotime($t['ultima'])) ?></td>
                                <td>
                                    <?php if ($bloqueado_agora): ?>
                                        <span style="font-family: var(--mono); font-size: 10px; color: var(--danger); border: 1px solid rgba(255,77,109,0.4); padding: 2px 8px; border-radius: 2px; background: rgba(255,77,109,0.07);">⚠ BLOQUEADO</span>
                                    <?php elseif ($t['tentativas'] >= 5): ?>
                                        <span style="font-family: var(--mono); font-size: 10px; color: var(--muted); border: 1px solid var(--border); padding: 2px 8px; border-radius: 2px;">Expirado</span>
                                    <?php else: ?>
                                        <span style="font-family: var(--mono); font-size: 10px; color: var(--warning); border: 1px solid rgba(255,179,0,0.3); padding: 2px 8px; border-radius: 2px;">Suspeito</span>
                                    <?php endif; ?>
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
                        <tr>
                            <td colspan="4" style="color: var(--muted); text-align:center; padding: 24px;">Nenhuma atividade ainda.</td>
                        </tr>
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