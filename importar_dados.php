<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Conexão com o banco NOVO (APA)
$host_novo = '127.0.0.1';
$db_novo   = 'leo90192_apa_sistema';
$user_novo = 'leo90192_apa';
$pass_novo = '#Senha2024';

try {
    $pdo_novo = new PDO("mysql:host=$host_novo;dbname=$db_novo;charset=utf8mb4", $user_novo, $pass_novo, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Erro ao conectar no banco NOVO: " . $e->getMessage());
}

// 2. Conexão com o banco ANTIGO (PEI)
// ATENÇÃO: Ajuste o nome do banco antigo abaixo caso não seja este!
$db_antigo = 'leo90192_sistema_pei'; 

try {
    $pdo_antigo = new PDO("mysql:host=$host_novo;dbname=$db_antigo;charset=utf8mb4", $user_novo, $pass_novo, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Erro ao conectar no banco ANTIGO do PEI. Verifique se o nome '$db_antigo' está correto: " . $e->getMessage());
}

echo "<h2>Iniciando Migração de Dados para apa.eemdp2.com.br</h2>";

try {
    // ---- PASS0 1: IMPORTAR PROFESSORES ----
    // Puxa usuários que são professores e ensinam Português ou Matemática
    $sql_prof = "SELECT id, nome, disciplina, senha FROM professores WHERE disciplina IN ('Língua Portuguesa', 'Matemática')";
    $professores = $pdo_antigo->query($sql_prof)->fetchAll(PDO::FETCH_ASSOC);
    
    $ins_prof = $pdo_novo->prepare("INSERT INTO professores (id, nome, disciplina, senha) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome=VALUES(nome), disciplina=VALUES(disciplina)");
    
    $cp = 0;
    foreach ($professores as $p) {
        $ins_prof->execute([$p['id'], $p['nome'], $p['disciplina'], $p['senha']]);
        $cp++;
    }
    echo "✅ {$cp} Professores de Português/Matemática migrados com sucesso!<br>";

    // ---- PASSO 2: IMPORTAR ESTUDANTES ----
    // Busca os estudantes e extrai o Ano e a Turma que estão no formato "6º A", "7º B", etc.
    $sql_est = "SELECT e.id, e.nome, t.nome AS turma_nome 
                FROM estudantes e 
                INNER JOIN turmas t ON t.id = e.turma_id";
    $estudantes = $pdo_antigo->query($sql_est)->fetchAll(PDO::FETCH_ASSOC);
    
    $ins_est = $pdo_novo->prepare("INSERT INTO estudantes (id, nome, ano, turma, paede) VALUES (?, ?, ?, ?, 'Não') ON DUPLICATE KEY UPDATE nome=VALUES(nome), ano=VALUES(ano), turma=VALUES(turma)");
    
    $ce = 0;
    foreach ($estudantes as $e) {
        // Limpa a string do nome da turma (Ex: "6º A" vira Ano: "6" e Turma: "A")
        $string = trim($e['turma_nome']);
        $ano = preg_replace('/[^0-9]/', '', $string); // Pega só o número
        $turma = trim(str_replace(['º', $ano, ' '], '', $string)); // Pega só a letra
        
        if (empty($ano)) $ano = '6';
        if (empty($turma)) $turma = 'A';

        $ins_est->execute([$e['id'], $e['nome'], $ano, $turma]);
        $ce++;
    }
    echo "✅ {$ce} Estudantes organizados e migrados com sucesso!<br>";
    echo "<br><strong>🎉 Migração concluída! Você já pode deletar este arquivo por segurança.</strong>";

} catch (Exception $e) {
    die("<br>❌ Erro durante a migração: " . $e->getMessage());
}