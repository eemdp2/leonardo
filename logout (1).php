<?php
require_once 'config.php';

// Limpa todas as variáveis salvas na sessão do APA
$_SESSION = [];

// Destrói a sessão no servidor
if (ini_get("session_use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redireciona o usuário de volta para a tela de login
header('Location: index.php');
exit;