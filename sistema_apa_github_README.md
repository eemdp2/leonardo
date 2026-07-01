# 📊 Sistema APA Independente (Acompanhamento Personalizado de Aprendizagem)

Este é um sistema Web totalmente desvinculado, seguro e otimizado para o lançamento bimestral de níveis de aprendizagem dos estudantes do 6º ao 9º ano nas disciplinas de Língua Portuguesa e Matemática. Foi desenhado para rodar de forma isolada em `apa.eemdp2.com.br`.

## 📁 Estrutura de Arquivos
* `database.sql` - Script de criação das tabelas no banco de dados.
* `config.php` - Configuração da conexão PDO com tratamento de erros.
* `index.php` - Tela de login exclusiva para os professores.
* `planilha.php` - Grade de lançamentos dinâmica com salvamento automático via AJAX (Single Page Application).
* `logout.php` - Encerramento seguro de sessão.
* `importar_dados.php` - Script de migração automática a partir do banco do sistema PEI.

## 🚀 Instalação Rápida
1. Crie o banco de dados `leo90192_apa_sistema` na sua hospedagem.
2. Execute os comandos contidos em `database.sql` dentro deste banco de dados.
3. Suba todos os arquivos `.php` para a pasta raiz do subdomínio `apa.eemdp2.com.br`.
4. Acesse `https://apa.eemdp2.com.br/importar_dados.php` no navegador para migrar automaticamente todos os professores e alunos do sistema PEI antigo.
5. **Importante:** Apague o arquivo `importar_dados.php` do servidor após o sucesso da migração por motivos de segurança.
