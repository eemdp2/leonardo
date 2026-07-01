<?php
require_once 'config.php';

// Proteção da página: se não houver sessão do sistema APA, joga para o login
if (!isset($_SESSION['apa_prof_id'])) {
    header('Location: index.php');
    exit;
}

$prof_id = $_SESSION['apa_prof_id'];
$prof_nome = $_SESSION['apa_prof_nome'];
$prof_disc = $_SESSION['apa_prof_disc'];

// Captura filtros da URL ou define o padrão (6º Ano A)
$ano_filtro = $_GET['ano'] ?? '6';
$turma_filtro = $_GET['turma'] ?? 'A';

// ==========================================
// PROCESSAMENTO DO SALVAMENTO AUTOMÁTICO (AJAX)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'salvar_nivel') {
    header('Content-Type: application/json; charset=utf-8');
    
    $estudante_id = (int)($_POST['estudante_id'] ?? 0);
    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '';

    $camposPermitidos = ['aval_diagnostica', 'primeiro_bimestre', 'segundo_bimestre', 'terceiro_bimestre', 'quarto_bimestre'];
    
    if (!in_array($campo, $camposPermitidos) || empty($estudante_id)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos para gravação.']);
        exit;
    }

    try {
        // Verifica se já existe uma linha de notas criada para este aluno nesta disciplina específica
        $check = $pdo->prepare("SELECT id FROM apa_niveis WHERE estudante_id = ? AND disciplina = ?");
        $check->execute([$estudante_id, $prof_disc]);
        $registro = $check->fetch();

        if ($registro) {
            // Se já existir, atualiza o bimestre correspondente
            $stmt = $pdo->prepare("UPDATE apa_niveis SET {$campo} = ?, professor_id = ? WHERE id = ?");
            $stmt->execute([$valor, $prof_id, $registro['id']]);
        } else {
            // Se for inédito, faz o insert associando a disciplina do professor logado
            $stmt = $pdo->prepare("INSERT INTO apa_niveis (estudante_id, professor_id, disciplina, {$campo}) VALUES (?, ?, ?, ?)");
            $stmt->execute([$estudante_id, $prof_id, $prof_disc, $valor]);
        }
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// CONSULTA PRINCIPAL DOS ALUNOS DA TURMA
// ==========================================
$query = $pdo->prepare("
    SELECT 
        e.id, e.nome, e.paede,
        n.aval_diagnostica, n.primeiro_bimestre, n.segundo_bimestre, n.terceiro_bimestre, n.quarto_bimestre
    FROM estudantes e
    LEFT JOIN apa_niveis n ON n.estudante_id = e.id AND n.disciplina = ?
    WHERE e.ano = ? AND e.turma = ?
    ORDER BY e.nome ASC
");
$query->execute([$prof_disc, $ano_filtro, $turma_filtro]);
$estudantes = $query->fetchAll();

// Função auxiliar para renderizar os selects coloridos no padrão da sua planilha Excel
function renderSelect($estudante_id, $campo, $valorAtual) {
    $niveis = [
        'Nível 01' => 'background: #f2a6a6; color: #721c24;',
        'Nível 02' => 'background: #f7d6a3; color: #856404;',
        'Nível 03' => 'background: #d4edda; color: #155724;',
        'Nível 04' => 'background: #c3e6cb; color: #155724;',
        'Nível 05' => 'background: #b8daff; color: #004085;'
    ];
    
    // Define a cor inicial da caixinha baseada no valor atual vindo do banco
    $estilo = $niveis[$valorAtual] ?? 'background: #fff; color: #000;';
    
    $html = "<select class='nivel-select' data-id='{$estudante_id}' data-campo='{$campo}' style='{$estilo} font-weight:bold; padding:6px; border-radius:4px; width:100%; text-align:center; border:1px solid #ccc; cursor:pointer;'>";
    $html .= "<option value='' style='background:#fff; color:#000;'>-</option>";
    
    foreach ($niveis as $nivel => $style) {
        $selected = ($valorAtual === $nivel) ? 'selected' : '';
        $html .= "<option value='{$nivel}' {$selected} style='{$style}'>{$nivel}</option>";
    }
    $html .= "</select>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilha APA - Controle de Lançamentos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; color: #333; }
        .header-area { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .filtros { display: flex; gap: 15px; align-items: center; background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: center; }
        th { background: #4e73df; color: white; font-size: 14px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f8f9fc; }
        .salvando { background: #4e73df; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; display: none; margin-left: 10px; animation: pulse 1.5s infinite; }
        .btn-sair { background: #c62828; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .btn-sair:hover { background: #b01a1a; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
    </style>
</head>
<body>

<div class="header-area">
    <div>
        <h2 style="margin:0 0 5px 0;">📊 Projeto APA - Grade de Lançamentos <span id="status-salva" class="salvando">💾 SALVANDO...</span></h2>
        <p style="margin:0; color:#555;">Componente Curricular: <strong style="color:#4e73df;"><?=$prof_disc?></strong> | Professor(a): <strong><?=$prof_nome?></strong></p>
    </div>
    <a href="logout.php" class="btn-sair">Sair do Sistema</a>
</div>

<div class="filtros">
    <form method="GET" style="display:flex; gap:15px; align-items:center; width:100%; flex-wrap:wrap;">
        <label><strong>Ano Letivo:</strong></label>
        <select name="ano" style="padding:8px; border-radius:4px; border:1px solid #ccc;">
            <option value="6" <?=($ano_filtro=='6')?'selected':''?>>6º Ano</option>
            <option value="7" <?=($ano_filtro=='7')?'selected':''?>>7º Ano</option>
            <option value="8" <?=($ano_filtro=='8')?'selected':''?>>8º Ano</option>
            <option value="9" <?=($ano_filtro=='9')?'selected':''?>>9º Ano</option>
        </select>

        <label><strong>Turma:</strong></label>
        <select name="turma" style="padding:8px; border-radius:4px; border:1px solid #ccc;">
            <option value="A" <?=($turma_filtro=='A')?'selected':''?>>A</option>
            <option value="B" <?=($turma_filtro=='B')?'selected':''?>>B</option>
            <option value="C" <?=($turma_filtro=='C')?'selected':''?>>C</option>
        </select>

        <button type="submit" style="padding:8px 20px; background:#4e73df; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold; transition: background 0.2s;">🔍 Filtrar Turma</button>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th style="text-align:left; width:35%; padding-left:15px;">Nome Completo do Estudante</th>
            <th style="width:10%;">PAEDE</th>
            <th style="width:11%;">Aval. Diagnóstica</th>
            <th style="width:11%;">1º Bimestre</th>
            <th style="width:11%;">2º Bimestre</th>
            <th style="width:11%;">3º Bimestre</th>
            <th style="width:11%;">4º Bimestre</th>
        </tr>
    </thead>
    <tbody>
        <?php if(empty($estudantes)): ?>
            <tr><td colspan="7" style="padding:40px; color:#888; font-style:italic; font-size:15px;">Nenhum estudante regular foi localizado na tabela para a combinação selecionada.</td></tr>
        <?php else: ?>
            <?php foreach($estudantes as $est): ?>
                <tr>
                    <td style="text-align:left; font-weight:bold; color:#2c3e50; padding-left:15px;"><?=$est['nome']?></td>
                    <td>
                        <span style="font-weight:bold; color: <?=$est['paede']=='Sim'?'#d9534f':'#5cb85c'?>">
                            <?=$est['paede']?>
                        </span>
                    </td>
                    <td><?=renderSelect($est['id'], 'aval_diagnostica', $est['aval_diagnostica'])?></td>
                    <td><?=renderSelect($est['id'], 'primeiro_bimestre', $est['primeiro_bimestre'])?></td>
                    <td><?=renderSelect($est['id'], 'segundo_bimestre', $est['segundo_bimestre'])?></td>
                    <td><?=renderSelect($est['id'], 'terceiro_bimestre', $est['terceiro_bimestre'])?></td>
                    <td><?=renderSelect($est['id'], 'quarto_bimestre', $est['quarto_bimestre'])?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('nivel-select')) {
        const select = e.target;
        const estId = select.dataset.id;
        const campo = select.dataset.campo;
        const valor = select.value;
        const indicador = document.getElementById('status-salva');

        // Mapeamento dinâmico de cores para renderizar em tempo de clique (UI fluida)
        const cores = {
            'Nível 01': { bg: '#f2a6a6', text: '#721c24' },
            'Nível 02': { bg: '#f7d6a3', text: '#856404' },
            'Nível 03': { bg: '#d4edda', text: '#155724' },
            'Nível 04': { bg: '#c3e6cb', text: '#155724' },
            'Nível 05': { bg: '#b8daff', text: '#004085' }
        };

        if (cores[valor]) {
            select.style.backgroundColor = cores[valor].bg;
            select.style.color = cores[valor].text;
        } else {
            select.style.backgroundColor = '#fff';
            select.style.color = '#000';
        }

        // Exibe o balão piscante de "Salvando..."
        indicador.style.display = 'inline-block';

        const formData = new FormData();
        formData.append('action', 'salvar_nivel');
        formData.append('estudante_id', estId);
        formData.append('campo', campo);
        formData.append('valor', valor);

        // Dispara para a própria planilha.php tratar em background
        fetch('planilha.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(dados => {
            indicador.style.display = 'none';
            if(!dados.sucesso) {
                alert('Aviso: Não foi possível salvar a alteração. Erro: ' + dados.erro);
            }
        })
        .catch(() => {
            indicador.style.display = 'none';
            alert('Erro crítico: Falha de conexão na rede com o servidor.');
        });
    }
});
</script>
</body>
</html>