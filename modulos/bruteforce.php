<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db/conexao.php';

$user_id = $_SESSION['user_id'];

// Busca o ID do lab de Brute Force
$lab_res = $conn->query("SELECT id FROM labs WHERE categoria = 'BruteForce' LIMIT 1");
$lab     = $lab_res->fetch_assoc();
$lab_id  = $lab['id'] ?? 4;

// Verifica se já completou
$check = $conn->prepare("SELECT id FROM progresso WHERE usuario_id = ? AND lab_id = ?");
$check->bind_param("ii", $user_id, $lab_id);
$check->execute();
$ja_completou = $check->get_result()->num_rows > 0;

// ─── ZONA VULNERÁVEL ─────────────────────────────────────────────────────────
// Login PROPOSITALMENTE vulnerável a Brute Force — sem rate limiting, sem lockout!
$vuln_resultado  = null;
$tentativa_atual = null;

// Credenciais fracas embutidas (propósito didático)
define('VULN_USER', 'admin');
define('VULN_PASS', '1234');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vuln_usuario'])) {
    $v_usuario = $_POST['vuln_usuario'];
    $v_senha   = $_POST['vuln_senha'];

    // ⚠️ VULNERÁVEL: comparação simples, sem rate limiting, sem lockout, sem log
    if ($v_usuario === VULN_USER && $v_senha === VULN_PASS) {
        $vuln_resultado = 'sucesso';

        if (!$ja_completou) {
            $ins = $conn->prepare("INSERT INTO progresso (usuario_id, lab_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $lab_id);
            $ins->execute();
            $ja_completou = true;
        }
    } else {
        $vuln_resultado  = 'falha';
        $tentativa_atual = htmlspecialchars($v_senha, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Broken Authentication — CyberSec Dojo</title>
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .wordlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 12px;
        }
        .wordlist-item {
            background: var(--surface-0, #0d1117);
            border: 1px solid var(--border, #30363d);
            border-radius: 6px;
            padding: 6px 10px;
            font-family: monospace;
            font-size: 13px;
            color: var(--text-secondary, #8b949e);
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }
        .wordlist-item:hover {
            border-color: var(--accent, #58a6ff);
            color: var(--text-primary, #e6edf3);
        }
        .wordlist-item.tentando {
            border-color: #e3b341;
            color: #e3b341;
            animation: pulse-border 0.5s ease;
        }
        .wordlist-item.errou {
            border-color: #f85149;
            color: #f85149;
            opacity: 0.5;
        }
        .wordlist-item.acertou {
            border-color: #3fb950;
            color: #3fb950;
            font-weight: bold;
        }
        @keyframes pulse-border {
            0%   { box-shadow: 0 0 0 0 rgba(227,179,65,0.4); }
            100% { box-shadow: 0 0 0 6px rgba(227,179,65,0); }
        }
        .progress-bar-wrap {
            background: var(--surface-0, #0d1117);
            border: 1px solid var(--border, #30363d);
            border-radius: 6px;
            height: 8px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #58a6ff;
            width: 0%;
            transition: width 0.3s ease;
        }
        #status-msg {
            font-size: 13px;
            margin-top: 8px;
            color: var(--text-secondary, #8b949e);
            min-height: 20px;
        }
        .bf-controls {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .btn-bf {
            padding: 8px 18px;
            border-radius: 6px;
            border: 1px solid var(--border, #30363d);
            background: transparent;
            color: var(--text-primary, #e6edf3);
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-bf:hover  { background: var(--surface-1, #161b22); }
        .btn-bf:disabled { opacity: 0.4; cursor: not-allowed; }
        .speed-select {
            padding: 7px 12px;
            border-radius: 6px;
            border: 1px solid var(--border, #30363d);
            background: var(--surface-0, #0d1117);
            color: var(--text-primary, #e6edf3);
            font-size: 13px;
        }
        .attempt-counter {
            font-family: monospace;
            font-size: 13px;
            color: var(--text-secondary, #8b949e);
            padding: 6px 12px;
            border: 1px solid var(--border, #30363d);
            border-radius: 6px;
            background: var(--surface-0, #0d1117);
        }
    </style>
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
        <h1>Broken Authentication</h1>
        <p>Entenda ataques de força bruta, observe o simulador em ação e explore o login vulnerável abaixo.</p>
    </div>

    <!-- SEÇÃO 1: O QUE É -->
    <div class="lab-section">
        <h2>O que é Broken Authentication?</h2>
        <p>
            Broken Authentication ocorre quando mecanismos de autenticação são implementados de forma
            inadequada, permitindo que atacantes comprometam senhas, tokens de sessão ou chaves de API.
            Um dos vetores mais diretos é o <strong>ataque de força bruta</strong>: tentar sistematicamente
            combinações de senhas até encontrar a correta.
        </p>
        <p>
            Sistemas vulneráveis não impõem limites de tentativas, não monitoram padrões de acesso e
            aceitam credenciais fracas — criando uma superfície de ataque trivialmente explorável com
            ferramentas automatizadas como <strong>Hydra</strong>, <strong>Burp Suite Intruder</strong>
            ou scripts simples em Python.
        </p>
    </div>

    <!-- SEÇÃO 2: TÉCNICAS -->
    <div class="lab-section">
        <h2>Tipos de ataque de força bruta</h2>

        <div class="command-block">
            <code>Força bruta pura (Pure Brute Force)</code>
            <p class="cmd-desc">
                <strong>Testa todas as combinações possíveis</strong> dentro de um charset e comprimento definidos.
                Para senhas curtas (≤6 chars, apenas números), o espaço é pequeno o suficiente para ser
                exaurido em segundos. Para senhas longas com símbolos, o espaço pode ser astronomicamente grande.
            </p>
        </div>

        <div class="command-block">
            <code>Ataque de dicionário (Dictionary Attack)</code>
            <p class="cmd-desc">
                <strong>Usa uma wordlist</strong> — lista de senhas comuns, vazadas ou derivadas do contexto
                do alvo. É muito mais eficiente que a força bruta pura porque explora o comportamento humano:
                a maioria das pessoas escolhe senhas previsíveis como "123456", "password" ou o nome do time.
            </p>
        </div>

        <div class="command-block">
            <code>Credential Stuffing</code>
            <p class="cmd-desc">
                <strong>Usa pares usuário/senha de vazamentos anteriores</strong> (como breaches da RockYou,
                LinkedIn, Adobe). Como pessoas reutilizam credenciais entre serviços, ataques automatizados
                testam esses pares em outros sites — com taxa de sucesso surpreendentemente alta.
            </p>
        </div>

        <div class="command-block">
            <code>Password Spraying</code>
            <p class="cmd-desc">
                <strong>Inverte a lógica</strong>: em vez de testar muitas senhas para um usuário (e acionar
                o lockout), testa <em>uma senha muito comum</em> contra <em>muitos usuários</em>. Mais lento,
                mas evita bloqueios por tentativas excessivas numa única conta.
            </p>
        </div>
    </div>

    <!-- SEÇÃO 3: SIMULADOR VISUAL -->
    <div class="lab-section">
        <h2>Simulador de ataque de dicionário</h2>
        <p>
            O simulador abaixo demonstra como um script automatizado percorre uma wordlist testando cada
            senha. O alvo usa as credenciais <code>admin / 1234</code> — observe quantas tentativas são
            necessárias quando não há nenhuma proteção.
        </p>

        <div class="wordlist-grid" id="wordlist-grid">
            <!-- Populado via JS -->
        </div>

        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="progress-bar"></div>
        </div>

        <div id="status-msg">Pronto. Clique em "Iniciar ataque" para começar.</div>

        <div class="bf-controls">
            <button class="btn-bf" id="btn-start" onclick="startAttack()">▶ Iniciar ataque</button>
            <button class="btn-bf" id="btn-pause" onclick="pauseAttack()" disabled>⏸ Pausar</button>
            <button class="btn-bf" id="btn-reset" onclick="resetAttack()">↺ Resetar</button>
            <select class="speed-select" id="speed-select">
                <option value="600">Lento (600ms)</option>
                <option value="250" selected>Normal (250ms)</option>
                <option value="80">Rápido (80ms)</option>
                <option value="20">Turbo (20ms)</option>
            </select>
            <span class="attempt-counter" id="attempt-counter">0 / 0 tentativas</span>
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
            Este formulário não possui rate limiting, lockout por tentativas nem CAPTCHA.
            As credenciais corretas são <code>admin</code> / <code>1234</code> — use a wordlist
            acima para encontrá-las ou insira diretamente para completar o lab.
        </p>

        <?php if ($ja_completou && $vuln_resultado !== 'sucesso'): ?>
            <div class="result-box result-success show">
                ✓ Você já completou este laboratório anteriormente. Bom trabalho!
            </div>
        <?php endif; ?>

        <?php if ($vuln_resultado === 'sucesso'): ?>
            <div class="result-box result-success show">
                ✓ Acesso concedido! Você explorou o Broken Authentication com sucesso. Lab registrado no seu progresso.
            </div>
        <?php elseif ($vuln_resultado === 'falha'): ?>
            <div class="result-box result-fail show">
                ✗ Acesso negado com a senha <strong><?= $tentativa_atual ?></strong>. Continue tentando.
            </div>
        <?php endif; ?>

        <form action="bruteforce.php#zona-vulneravel" method="POST" class="vuln-form" style="margin-top: 20px;">
            <div class="form-group">
                <label>Usuário</label>
                <input type="text" name="vuln_usuario" value="admin" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="vuln_senha" placeholder="tente senhas da wordlist acima" id="senha-input">
            </div>
            <button type="submit" class="btn-vuln">Tentar Login</button>
        </form>
    </div>

    <!-- DEFESA -->
    <div class="lab-section" style="margin-top: 48px;">
        <h2>Como se defender</h2>
        <p>
            A defesa contra força bruta envolve múltiplas camadas — nenhuma medida isolada é suficiente:
        </p>
        <div class="command-block">
            <code>Lockout progressivo</code>
            <p class="cmd-desc">
                Bloqueie ou adicione delay exponencial após N tentativas falhas (ex.: 5 tentativas → bloqueio
                de 15 min). O CyberSec Dojo já implementa isso no login real — veja <code>tentativas_login</code>.
            </p>
        </div>
        <div class="command-block">
            <code>MFA (Multi-Factor Authentication)</code>
            <p class="cmd-desc">
                Mesmo que a senha seja descoberta, um segundo fator (TOTP, SMS, chave física) impede o acesso.
                É a proteção mais eficaz contra credential stuffing.
            </p>
        </div>
        <div class="command-block">
            <code>Política de senhas fortes + haveibeenpwned</code>
            <p class="cmd-desc">
                Exija senhas com ≥12 caracteres e verifique contra bases de senhas vazadas via API
                do <em>Have I Been Pwned</em>. Senhas comuns são descartadas antes do cadastro.
            </p>
        </div>
        <div class="command-block">
            <code>Rate limiting por IP + CAPTCHA</code>
            <p class="cmd-desc">
                Limite requisições de login por IP/hora com ferramentas como fail2ban ou middleware de
                aplicação. CAPTCHA adiciona fricção computacional que torna automação cara.
            </p>
        </div>
    </div>

</div>

<script>
const WORDLIST = [
    'admin','password','123456','1234','12345','123456789',
    'qwerty','abc123','letmein','monkey','dragon','master',
    'pass','iloveyou','sunshine','princess','welcome','shadow',
    'superman','michael','football','login','hello','charlie'
];

const CORRECT_PASS = '1234';

let attackInterval = null;
let currentIndex   = 0;
let running        = false;
let found          = false;

function buildGrid() {
    const grid = document.getElementById('wordlist-grid');
    grid.innerHTML = '';
    WORDLIST.forEach((word, i) => {
        const el = document.createElement('div');
        el.className    = 'wordlist-item';
        el.id           = 'word-' + i;
        el.textContent  = word;
        el.onclick      = () => { document.getElementById('senha-input').value = word; };
        grid.appendChild(el);
    });
}

function updateCounter() {
    document.getElementById('attempt-counter').textContent =
        currentIndex + ' / ' + WORDLIST.length + ' tentativas';
}

function startAttack() {
    if (found) return;
    running = true;
    document.getElementById('btn-start').disabled = true;
    document.getElementById('btn-pause').disabled = false;

    const speed = parseInt(document.getElementById('speed-select').value);

    attackInterval = setInterval(() => {
        if (currentIndex >= WORDLIST.length) {
            clearInterval(attackInterval);
            document.getElementById('status-msg').textContent =
                '✗ Wordlist esgotada sem sucesso. Tente outra lista.';
            document.getElementById('btn-start').disabled = false;
            document.getElementById('btn-pause').disabled = true;
            running = false;
            return;
        }

        const word    = WORDLIST[currentIndex];
        const el      = document.getElementById('word-' + currentIndex);
        const prevIdx = currentIndex - 1;

        if (prevIdx >= 0) {
            document.getElementById('word-' + prevIdx).classList.remove('tentando');
            document.getElementById('word-' + prevIdx).classList.add('errou');
        }

        el.classList.add('tentando');
        document.getElementById('senha-input').value = word;
        document.getElementById('status-msg').textContent =
            '→ Testando: ' + word + '…';

        const pct = Math.round(((currentIndex + 1) / WORDLIST.length) * 100);
        document.getElementById('progress-bar').style.width = pct + '%';

        updateCounter();

        if (word === CORRECT_PASS) {
            clearInterval(attackInterval);
            el.classList.remove('tentando');
            el.classList.add('acertou');
            document.getElementById('status-msg').innerHTML =
                '✓ Senha encontrada: <strong>' + word + '</strong>! Clique em "Tentar Login" para completar o lab.';
            document.getElementById('btn-start').disabled = false;
            document.getElementById('btn-pause').disabled = true;
            running = false;
            found   = true;
            currentIndex++;
            updateCounter();
            return;
        }

        currentIndex++;
    }, speed);
}

function pauseAttack() {
    if (attackInterval) {
        clearInterval(attackInterval);
        attackInterval = null;
    }
    running = false;
    document.getElementById('btn-start').disabled  = false;
    document.getElementById('btn-pause').disabled  = true;
    document.getElementById('status-msg').textContent = '⏸ Pausado na tentativa ' + currentIndex + '.';
}

function resetAttack() {
    if (attackInterval) clearInterval(attackInterval);
    attackInterval = null;
    currentIndex   = 0;
    running        = false;
    found          = false;

    document.getElementById('btn-start').disabled = false;
    document.getElementById('btn-pause').disabled = true;
    document.getElementById('progress-bar').style.width = '0%';
    document.getElementById('status-msg').textContent   = 'Pronto. Clique em "Iniciar ataque" para começar.';
    document.getElementById('senha-input').value        = '';
    updateCounter();
    buildGrid();
}

buildGrid();
updateCounter();
</script>

</body>
</html>