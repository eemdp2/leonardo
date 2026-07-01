<?php
// ATENÇÃO: Ativado temporariamente para remover a tela branca e exibir o erro real na tela
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Garante o início da sessão para controle de login dos professores
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Credenciais de acesso fornecidas por você
$host = '127.0.0.1'; // Alterado de localhost para 127.0.0.1 para evitar falha de DNS interno
$db   = 'leo90192_apa_sistema';
$user = 'leo90192_apa'; 
$pass = '#Senha2024';     
$sgbd = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    // Estabelece a conexão usando a biblioteca PDO
    $pdo = new PDO($sgbd, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) {
    // Se a conexão falhar, interrompe o script e joga o erro na tela de forma limpa
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}