<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];

// Busca o ID do lab de CSRF
$lab_res = $conn->query("SELECT id FROM labs WHERE categoria = 'CSRF' LIMIT 1");
$lab     = $lab_res->fetch_assoc();
$lab_id  = $lab['id'] ?? 3;

// Verifica se já completou
$check = $conn->prepare("SELECT id FROM progresso WHERE usuario_id = ? AND lab_id = ?");
$check->bind_param("ii", $user_id, $lab_id);
$check->execute();
$ja_completou = $check->get_result()->num_rows > 0;

// ─── ZONA VULNERÁVEL ─────────────────────────────────────────────────────────
// Formulário de "troca de senha" PROPOSITALMENTE sem token CSRF.
$vuln_resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_senha'])) {
    $nova_senha = trim($_POST['nova_senha']);
    $origem     = $_POST['origem'] ?? 'formulario';

    // ⚠️ SEM verificação de token CSRF — aceita qualquer requisição POST
    if (!empty($nova_senha)) {
        // Simula a troca (não altera o banco de verdade — só demonstra o fluxo)
        if ($origem === 'csrf_externo') {
            $vuln_resultado = 'csrf';
        } else {
            $vuln_resultado = 'sucesso';
        }

        if (!$ja_completou) {
            $ins = $conn->prepare("INSERT INTO progresso (usuario_id, lab_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $lab_id);
            $ins->execute();
            $ja_completou = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>CSRF — CyberSec Dojo</title>
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
        <h1>Cross-Site Request Forgery</h1>
        <p>Entenda como requisições forjadas exploram sessões autenticadas — e como se defender.</p>
    </div>

    <!-- SEÇÃO 1: O QUE É CSRF -->
    <div class="lab-section">
        <h2>O que é CSRF?</h2>
        <p>
            Cross-Site Request Forgery (CSRF) é um ataque que força o navegador de uma vítima
            autenticada a enviar uma requisição indesejada para um site onde ela já tem sessão ativa.
            O servidor recebe a requisição com os cookies legítimos da vítima e a executa normalmente —
            sem saber que foi iniciada por um site malicioso.
        </p>
        <p>
            Diferente do XSS, que injeta código <strong>dentro</strong> da aplicação alvo, o CSRF
            parte de um <strong>site externo</strong> controlado pelo atacante. A vítima só precisa
            estar logada no site alvo e visitar a página maliciosa — nenhuma interação adicional
            é necessária se o ataque usar uma requisição GET, e em POST basta um clique ou um
            formulário que se auto-submete com JavaScript.
        </p>
        <p>
            Ataques CSRF comuns incluem: troca de e-mail ou senha da conta, transferências bancárias,
            publicação de conteúdo e alteração de configurações de segurança.
        </p>
    </div>

    <!-- SEÇÃO 2: COMO FUNCIONA -->
    <div class="lab-section">
        <h2>Como o ataque funciona</h2>

        <div class="command-block">
            <code>
                &lt;!-- Página maliciosa hospedada em evil.com --&gt;<br>
                &lt;form action="https://banco.com/transferir" method="POST"&gt;<br>
                &nbsp;&nbsp;&lt;input type="hidden" name="valor" value="5000"&gt;<br>
                &nbsp;&nbsp;&lt;input type="hidden" name="destino" value="conta_atacante"&gt;<br>
                &lt;/form&gt;<br>
                &lt;script&gt;document.forms[0].submit();&lt;/script&gt;
            </code>
            <p class="cmd-desc">
                <strong>CSRF com auto-submit.</strong> A vítima abre a página maliciosa e o formulário
                se submete automaticamente via JavaScript. O banco recebe uma requisição POST legítima
                com os cookies de sessão da vítima — e processa a transferência normalmente. A vítima
                não viu nada acontecer.
            </p>
        </div>

        <div class="command-block">
            <code>&lt;img src="https://app.com/trocar-senha?senha=hacker123"&gt;</code>
            <p class="cmd-desc">
                <strong>CSRF via GET com tag de imagem.</strong> Se a ação de troca de senha aceitar
                GET, uma simples tag img já dispara a requisição quando a página carrega. O navegador
                faz o request automaticamente tentando carregar a "imagem" — levando os cookies junto.
            </p>
        </div>

        <div class="command-block">
            <code>
                &lt;!-- Link disfarçado enviado por e-mail ou mensagem --&gt;<br>
                &lt;a href="https://app.com/deletar-conta?confirm=true"&gt;<br>
                &nbsp;&nbsp;Clique para ganhar um prêmio!<br>
                &lt;/a&gt;
            </code>
            <p class="cmd-desc">
                <strong>CSRF via link.</strong> O atacante envia um link para a vítima por e-mail,
                chat ou redes sociais. Se a ação crítica aceitar GET e a vítima estiver logada, um
                clique basta para executar a ação — deletar a conta, mudar o e-mail, etc.
            </p>
        </div>

        <div class="command-block">
            <code>
                &lt;!-- Token CSRF ausente = vulnerável --&gt;<br>
                POST /trocar-senha HTTP/1.1<br>
                Host: app.com<br>
                Cookie: PHPSESSID=abc123<br><br>
                nova_senha=hacker123
            </code>
            <p class="cmd-desc">
                <strong>Requisição forjada sem token.</strong> Se o servidor só verifica o cookie de
                sessão e não exige um token secreto por requisição, qualquer site externo pode forjar
                esse POST. O servidor vê o cookie válido e executa a ação sem questionar a origem.
            </p>
        </div>
    </div>

    <!-- BOTÃO DE ANCORAGEM -->
    <div style="text-align: center; margin: 10px 0 20px;">
        <a href="#zona-csrf" class="btn-test">↓ Ir para o desafio</a>
    </div>

    <!-- ZONA VULNERÁVEL -->
    <div class="vuln-zone" id="zona-csrf">
        <div class="vuln-zone-header">
            <h2>Formulário Vulnerável — Sem Token CSRF</h2>
            <span class="warning-badge">⚠ INTENCIONALMENTE INSEGURO</span>
        </div>
        <p class="desc">
            Este formulário de troca de senha não valida a origem da requisição.
            Simule um ataque enviando a requisição como se viesse de um site externo.
        </p>

        <?php if ($ja_completou && $vuln_resultado === null): ?>
            <div class="result-box result-success show">
                ✓ Você já completou este laboratório anteriormente.
            </div>
        <?php endif; ?>

        <?php if ($vuln_resultado === 'csrf'): ?>
            <div class="result-box result-success show">
                ✓ Ataque CSRF simulado com sucesso! O servidor aceitou a requisição forjada. Lab registrado.
            </div>
        <?php elseif ($vuln_resultado === 'sucesso'): ?>
            <div class="result-box result-info show">
                → Requisição aceita pelo formulário legítimo. Agora tente simular a origem externa abaixo.
            </div>
        <?php endif; ?>

        <!-- Formulário legítimo (simula o site real) -->
        <div style="margin-bottom: 28px;">
            <div style="font-family: var(--mono); font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                Formulário legítimo (site real):
            </div>
            <form action="csrf.php#zona-csrf" method="POST" class="vuln-form">
                <input type="hidden" name="origem" value="formulario">
                <div class="form-group">
                    <label>Nova senha</label>
                    <input type="text" name="nova_senha" placeholder="nova senha aqui" autocomplete="off">
                </div>
                <button type="submit" class="btn-vuln">Trocar Senha</button>
            </form>
        </div>

        <!-- Formulário externo (simula o site do atacante) -->
        <div style="border-top: 1px solid var(--border); padding-top: 24px;">
            <div style="font-family: var(--mono); font-size: 11px; color: #ff4d6d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                Requisição forjada (simulando evil.com):
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--muted); margin-bottom: 14px;">
                O servidor aceita? Deveria recusar — mas sem token CSRF, não tem como saber a diferença.
            </div>
            <form action="csrf.php#zona-csrf" method="POST" class="vuln-form">
                <input type="hidden" name="origem" value="csrf_externo">
                <input type="hidden" name="nova_senha" value="senha_do_atacante">
                <button type="submit" class="btn-vuln">Disparar Requisição Forjada</button>
            </form>
        </div>
    </div>

    <!-- DEFESA -->
    <div class="lab-section" style="margin-top: 48px;">
        <h2>Como se defender</h2>
        <p>
            A defesa principal contra CSRF é o uso de <strong>tokens CSRF</strong>: um valor
            aleatório e secreto gerado pelo servidor, incluído em cada formulário e validado
            a cada requisição. Um site externo não tem como conhecer esse token — então não
            consegue forjar uma requisição válida.
        </p>
        <div class="command-block">
            <code>
                // Gera o token na sessão<br>
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));<br><br>
                // Inclui no formulário<br>
                &lt;input type="hidden" name="csrf_token"<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;value="&lt;?= $_SESSION['csrf_token'] ?&gt;"&gt;<br><br>
                // Valida no servidor antes de processar<br>
                if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {<br>
                &nbsp;&nbsp;die("Requisição inválida.");<br>
                }
            </code>
            <p class="cmd-desc">
                O token é gerado com <em>random_bytes()</em> (criptograficamente seguro) e comparado
                com <em>hash_equals()</em> para evitar timing attacks. Cada formulário sensível deve
                ter seu próprio token validado antes de qualquer processamento.
            </p>
        </div>
        <p>
            O cabeçalho <strong>SameSite=Strict</strong> nos cookies é uma segunda linha de defesa:
            impede que o navegador envie cookies em requisições originadas de outros sites. Já está
            configurado neste projeto — mas tokens CSRF continuam sendo necessários porque
            SameSite não é suportado em todos os browsers mais antigos.
        </p>
    </div>

</div>
</body>
</html>
