<?php
session_start();

// 1. Limpa todas as variáveis de sessão da memória do servidor
$_SESSION = array();

// 2. Destrói a sessão de vez
session_destroy();

// 3. Redireciona o usuário de volta para a tela de login
header("Location: index.php");
exit;