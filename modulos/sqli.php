<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];

// Busca o ID do lab de SQLi
$lab_res = $conn->query("SELECT id FROM labs WHERE categoria = 'SQLi' LIMIT 1");
$lab     = $lab_res->fetch_assoc();
$lab_id  = $lab['id'] ?? 1;

// Verifica se já completou
$check = $conn->prepare("SELECT id FROM progresso WHERE usuario_id = ? AND lab_id = ?");
$check->bind_param("ii", $user_id, $lab_id);
$check->execute();
$ja_completou = $check->get_result()->num_rows > 0;

// ─── ZONA VULNERÁVEL ─────────────────────────────────────────────────────────
// Login PROPOSITALMENTE vulnerável a SQLi — não use este padrão em sistemas reais!
$vuln_resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vuln_usuario'])) {
    $v_usuario = $_POST['vuln_usuario'];
    $v_senha   = $_POST['vuln_senha'];

    // ⚠️ QUERY VULNERÁVEL — concatenação direta sem sanitização
    $query = "SELECT * FROM administradores WHERE usuario = '$v_usuario' AND senha = '$v_senha'";

    $res = $conn->query($query);

    if ($res && $res->num_rows > 0) {
        $vuln_resultado = 'sucesso';

        // Registra conclusão do lab (se ainda não registrou)
        if (!$ja_completou) {
            $ins = $conn->prepare("INSERT INTO progresso (usuario_id, lab_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $lab_id);
            $ins->execute();
            $ja_completou = true;
        }
    } else {
        $vuln_resultado = 'falha';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SQL Injection — CyberSec Dojo</title>
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

<div class="lab-content">

    <!-- CABEÇALHO -->
    <div class="page-header">
        <h1>SQL Injection</h1>
        <p>Entenda o ataque, analise os payloads e explore o login vulnerável abaixo.</p>
    </div>

    <!-- SEÇÃO 1: O QUE É SQLi -->
    <div class="lab-section">
        <h2>O que é SQL Injection?</h2>
        <p>
            SQL Injection (SQLi) é uma vulnerabilidade que ocorre quando uma aplicação inclui dados
            fornecidos pelo usuário diretamente dentro de uma query SQL sem sanitização adequada.
            O atacante consegue manipular a lógica da query, podendo contornar autenticações,
            extrair dados do banco, modificar registros ou até destruir tabelas inteiras.
        </p>
        <p>
            O problema fundamental é a <strong>mistura de dados e código</strong>: quando a entrada
            do usuário é concatenada diretamente na query, o banco de dados não consegue distinguir
            o que é dado e o que é instrução SQL.
        </p>
    </div>

    <!-- SEÇÃO 2: COMANDOS -->
    <div class="lab-section">
        <h2>Payloads e o que fazem</h2>

        <div class="command-block">
            <code>' OR '1'='1</code>
            <p class="cmd-desc">
                <strong>Bypass de autenticação clássico.</strong> Quando inserido no campo de usuário,
                transforma a query em <em>WHERE usuario = '' OR '1'='1'</em>. Como '1'='1' é sempre
                verdadeiro, a condição inteira retorna verdadeiro — e o login é bem-sucedido sem
                precisar de senha válida.
            </p>
        </div>

        <div class="command-block">
            <code>' OR 1=1 --</code>
            <p class="cmd-desc">
                <strong>Comentário para ignorar o resto da query.</strong> O <em>--</em> é o delimitador
                de comentário em SQL. Tudo que vem depois dele é ignorado pelo banco. Isso descarta
                completamente a verificação da senha da query original.
            </p>
        </div>

        <div class="command-block">
            <code>admin' --</code>
            <p class="cmd-desc">
                <strong>Login direto como um usuário específico.</strong> Se você sabe (ou adivinha) que
                existe um usuário chamado "admin", esse payload faz login como ele sem conhecer a senha.
                O <em>--</em> descarta a parte AND senha = '...' da query.
            </p>
        </div>

        <div class="command-block">
            <code>' OR '1'='1' --</code>
            <p class="cmd-desc">
                <strong>Variação completa com comentário.</strong> Combina o bypass de '1'='1' com o
                comentário para garantir que nenhum trecho adicional da query interfira no resultado.
                Funciona mesmo quando a query original tem condições extras após a verificação de senha.
            </p>
        </div>

        <div class="command-block">
            <code>'; DROP TABLE usuarios; --</code>
            <p class="cmd-desc">
                <strong>Destruição de dados (Stacked Query).</strong> O ponto-e-vírgula encerra a query
                atual e inicia uma nova instrução SQL. Esse payload apagaria a tabela inteira de usuários.
                Nem todos os bancos/frameworks permitem múltiplas queries assim, mas quando permitem,
                o impacto é catastrófico.
            </p>
        </div>
    </div>

    <!-- BOTÃO DE ANCORAGEM -->
    <div style="text-align: center; margin: 10px 0 20px;">
        <a href="#zona-vulneravel" class="btn-test">↓ Ir para o desafio</a>
    </div>

    <!-- ZONA VULNERÁVEL -->
    <div class="vuln-zone" id="zona-vulneravel">
        <div class="vuln-zone-header">
            <h2>Login Vulnerável</h2>
            <span class="warning-badge">⚠ INTENCIONALMENTE INSEGURO</span>
        </div>
        <p class="desc">
            Este formulário usa concatenação direta de strings na query SQL.
            Use os payloads acima para bypassar a autenticação e completar o lab.
        </p>

        <?php if ($ja_completou && $vuln_resultado !== 'sucesso'): ?>
            <div class="result-box result-success show">
                ✓ Você já completou este laboratório anteriormente. Bom trabalho!
            </div>
        <?php endif; ?>

        <?php if ($vuln_resultado === 'sucesso'): ?>
            <div class="result-box result-success show">
                ✓ Acesso concedido! Você explorou o SQLi com sucesso. Lab registrado no seu progresso.
            </div>
        <?php elseif ($vuln_resultado === 'falha'): ?>
            <div class="result-box result-fail show">
                ✗ Acesso negado. Tente um dos payloads acima.
            </div>
        <?php endif; ?>

        <form action="sqli.php#zona-vulneravel" method="POST" class="vuln-form" style="margin-top: 20px;">
            <div class="form-group">
                <label>Usuário</label>
                <input type="text" name="vuln_usuario" placeholder="tente: admin' --" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="vuln_senha" placeholder="qualquer coisa">
            </div>
            <button type="submit" class="btn-vuln">Tentar Login</button>
        </form>
    </div>

    <!-- DEFESA -->
    <div class="lab-section" style="margin-top: 48px;">
        <h2>Como se defender</h2>
        <p>
            A defesa principal contra SQLi é o uso de <strong>Prepared Statements</strong>
            (queries parametrizadas). Com eles, os dados do usuário nunca são interpretados
            como código SQL — são sempre tratados como valores literais.
        </p>
        <div class="command-block">
            <code>$stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ?");<br>$stmt->bind_param("s", $usuario);</code>
            <p class="cmd-desc">
                O banco recebe a query e os dados separadamente. Não importa o que o usuário digitar —
                nunca será executado como instrução SQL.
            </p>
        </div>
    </div>

</div>
</body>
</html>
