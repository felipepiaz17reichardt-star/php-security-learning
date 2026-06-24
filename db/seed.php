<?php
// db/seed.php
// Execute UMA VEZ no terminal: php db/seed.php
// Popula a tabela 'labs' com os laboratórios do sistema.

require_once __DIR__ . '/conexao.php';

$labs = [
    [
        'titulo'    => 'SQL Injection',
        'descricao' => 'Aprenda como funciona o ataque de injeção SQL e explore uma autenticação vulnerável.',
        'categoria' => 'SQLi',
        'ativo'     => 1
    ],
    [
        'titulo'    => 'Cross-Site Scripting (XSS)',
        'descricao' => 'Entenda como scripts maliciosos são injetados em páginas e teste um campo vulnerável.',
        'categoria' => 'XSS',
        'ativo'     => 1
    ],
];

$stmt = $conn->prepare("INSERT INTO labs (titulo, descricao, categoria, ativo) VALUES (?, ?, ?, ?)");

foreach ($labs as $lab) {
    // Evita duplicatas
    $check = $conn->prepare("SELECT id FROM labs WHERE titulo = ?");
    $check->bind_param("s", $lab['titulo']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "[SKIP] Lab '{$lab['titulo']}' já existe.\n";
        continue;
    }

    $stmt->bind_param("sssi", $lab['titulo'], $lab['descricao'], $lab['categoria'], $lab['ativo']);
    if ($stmt->execute()) {
        echo "[OK]   Lab '{$lab['titulo']}' criado (ID: {$conn->insert_id}).\n";
    } else {
        echo "[ERRO] Falha ao criar '{$lab['titulo']}'.\n";
    }
}

echo "\nSeed concluído.\n";
