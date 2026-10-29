# Painel de transparência UOSCC
coNY9/H6oqIopgr7b4ETvZe40D06hFIi

## Configuração no XAMPP (Windows)

O projeto usa MySQL/MariaDB em `127.0.0.1`, banco `uoscc`. O arquivo `.htaccess` na raiz do projeto define a conexão usada pelo Apache:

```apache
SetEnv UOSCC_DB_DSN "mysql:host=127.0.0.1;dbname=uoscc;charset=utf8mb4"
SetEnv UOSCC_DB_USER "root"
SetEnv UOSCC_DB_PASSWORD ""
```

Esses valores correspondem à instalação local do XAMPP com o usuário `root` sem senha. Se o usuário ou a senha do MySQL forem diferentes, ajuste `UOSCC_DB_USER` e `UOSCC_DB_PASSWORD` no `.htaccess`. Em instalações compartilhadas ou de produção, use um usuário MySQL próprio com permissões limitadas, nunca `root`.

Passos para configurar este projeto em outro computador:

1. Instale o XAMPP, inicie **Apache** e **MySQL** e confirme que a extensão `pdo_mysql` está habilitada no PHP (`C:\xampp\php\php.ini`).
2. Copie a pasta `UOSCC` para `C:\xampp\htdocs\Master\UOSCC` (ou ajuste os links conforme o caminho escolhido).
3. No phpMyAdmin (`http://localhost/phpmyadmin`), crie o banco `uoscc` com collation `utf8mb4_unicode_ci`. Para restaurar os registros e contas existentes, selecione esse banco, use **Importar** e escolha o backup SQL exportado do computador anterior (por exemplo, `MySql\uoscc.sql`). A importação de um backup completo substitui o conteúdo do banco de destino; faça backup antes de importar.
4. Se o backup for anterior ao recurso de destinação, atualize o banco com o SQL da seção **Atualizar banco restaurado sem redefinir a senha** abaixo.
5. Confira os três valores de conexão no `.htaccess` da raiz do projeto. Reinicie o Apache se necessário.
6. Acesse `http://localhost/Master/UOSCC/php/index.php?action=me`. Uma resposta JSON HTTP 200 (por exemplo, `{"user":null}` sem login) confirma que a API iniciou e conseguiu abrir a conexão; erro HTTP 500 indica que host, banco, credenciais, serviço MySQL ou `pdo_mysql` precisam ser conferidos.
7. Acesse `http://localhost/Master/UOSCC/html/login.html` e entre com uma conta já restaurada. Os usuários e hashes de senha estão no banco; as senhas não são redefinidas pela cópia dos arquivos.
8. Para preservar todos os dados em uma próxima troca de computador, exporte o banco `uoscc` pelo phpMyAdmin e copie também a pasta `UOSCC\uploads`, onde ficam os arquivos enviados. Guarde o backup SQL em local seguro.

O setup da aplicação é executado pelo PHP CLI, que não lê as variáveis definidas no `.htaccess`. Para criar as tabelas em um banco vazio ou aplicar migrações, configure as variáveis também no PowerShell e execute o instalador conforme a seção **MySQL** abaixo. O instalador configura a senha do administrador informado; evite executá-lo sobre uma base restaurada se não quiser alterar essa senha.

## MySQL

Crie o banco e configure o PHP com a extensão `pdo_mysql` habilitada:

```sh
mysql -e "CREATE DATABASE uoscc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
export UOSCC_DB_DSN='mysql:host=127.0.0.1;dbname=uoscc;charset=utf8mb4'
export UOSCC_DB_USER='uoscc'
export UOSCC_DB_PASSWORD='sua-senha-do-banco'
php php/setup.php admin@example.org 'uma-senha-forte-com-12-caracteres'
```

No XAMPP, execute o setup pelo PowerShell (ajuste o e-mail e a senha; a senha deve ter pelo menos 12 caracteres):

```powershell
Set-Location 'C:\xampp\htdocs\Master\UOSCC'
$env:UOSCC_DB_DSN = 'mysql:host=127.0.0.1;dbname=uoscc;charset=utf8mb4'
$env:UOSCC_DB_USER = 'root'
$env:UOSCC_DB_PASSWORD = ''
& 'C:\xampp\php\php.exe' '.\php\setup.php' 'admin@example.org' 'uma-senha-forte-com-12-caracteres'
```

O instalador cria as tabelas usando `UOSCC/php/schema.sql` e configura ou atualiza a conta administrativa. A senha da conta deve ter pelo menos 12 caracteres.

### Atualizar banco restaurado sem redefinir a senha

Para aplicar somente a tabela de destinações a um backup antigo, selecione `uoscc` no phpMyAdmin, abra a aba **SQL** e execute:

```sql
CREATE TABLE IF NOT EXISTS resource_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    type VARCHAR(40) NOT NULL,
    quantity DECIMAL(15, 2) NOT NULL,
    material_name VARCHAR(200) NULL,
    unit VARCHAR(20) NULL,
    description VARCHAR(2000) NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resource_allocations_project_date (project_id, date),
    CONSTRAINT fk_resource_allocations_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_resource_allocations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Perfis

- `admin`: administrador UOSCC. Cria várias contas OSC para a mesma área em **Acessos das OSCs**, consulta seus e-mails e redefine suas senhas; cadastra recursos e apoiadores, anexa evidências e gera relatórios. Senhas são armazenadas como hashes e nunca podem ser consultadas; redefina-as quando necessário.
- `osc`: acesso vinculado a uma das seis áreas. A conta só consulta e registra projetos, recursos, indicadores e atendimentos de projetos da própria área.
- `osc_admin`: administrador local criado pelo administrador UOSCC. Pode criar e excluir projetos, registrar recursos, indicadores, atendimentos, evidências e destinações e preparar relatórios somente para a própria área. O servidor valida o vínculo da conta em cada operação; esse perfil não acessa projetos de outras OSCs nem gerencia apoiadores, que são compartilhados entre as áreas.
- `supporter`: cria a própria conta na tela de login, entra automaticamente após o cadastro e acompanha as contribuições vinculadas a ela.
- `visitor`: abre `html/publico.html` sem conta para consultar os dados públicos.

Ao cadastrar ou editar um projeto, o administrador UOSCC ou o administrador local define se ele recebe dinheiro, materiais e/ou voluntariado; o administrador local só pode fazê-lo na própria área. Doações de materiais registram descrição, quantidade e unidade. O painel e a página pública permitem filtrar contribuições por OSC e período e somam materiais por projeto, descrição e unidade. No fim da página pública há rankings acumulados dos dez maiores doadores de dinheiro e dos dez maiores apoiadores voluntários; materiais não participam. Nomes de apoiadores aparecem na transparência e nos rankings somente quando a própria pessoa autoriza em **Configurações**; por padrão, a exibição é anônima.

Em **Relatórios**, o administrador UOSCC pode registrar a destinação de dinheiro e materiais por qualquer projeto; o administrador local só pode fazê-lo em projetos da própria área. O sistema impede alocar mais do que foi recebido até a data informada, calcula o saldo restante e publica automaticamente a destinação na página pública e em **Evidências**. Horas voluntárias são exibidas por projeto, mas não são tratadas como despesa.

Em **Minha conta**, usuários autenticados podem alterar nome e foto, consultar dinheiro, horas e materiais doados e ver a posição ou o valor necessário para alcançar o top 10. No cadastro de apoiadores, o administrador pode autorizar ou não a exibição pública do nome; apoiadores também podem alterar essa autorização em **Configurações**. Os nomes autorizados no ranking levam a um perfil público com estatísticas agregadas das contribuições aprovadas. **Configurações** permite alterar senha ou excluir a própria conta (a última conta de administrador não pode ser excluída). A organização do menu só permite reordenar itens dentro dos tópicos **Menu**, **Gestão** e **Gerenciamento de contas**; os cartões do painel continuam podendo ser ocultados individualmente. No relatório, **Aumentar** soma pessoas ao registro do projeto e da data selecionados.

As seis áreas são educação e formação cidadã, meio ambiente e sustentabilidade, saúde e bem-estar comunitário, assistência social e apoio às famílias, direitos humanos e inclusão social, e cultura, esporte e lazer.

## Execução local

Sem `UOSCC_DB_DSN`, o projeto usa SQLite em `var/uoscc.sqlite`, fora da pasta pública:

```sh
php php/setup.php admin@example.org 'uma-senha-forte-com-12-caracteres'
```

Após atualizar o código em uma instalação existente, execute novamente o instalador com a configuração do banco usada pela aplicação para aplicar as novas colunas; ao executar, informe a senha administrativa que deve permanecer ativa. O usuário MySQL usado pelo instalador precisa de permissões de criação e alteração de tabelas; mantenha essas permissões fora do usuário de execução do site sempre que possível. Se o usuário da aplicação não tiver essas permissões, faça a migração pelo phpMyAdmin com uma conta autorizada. No Windows/XAMPP, as variáveis `UOSCC_DB_DSN`, `UOSCC_DB_USER` e `UOSCC_DB_PASSWORD` configuradas no `.htaccess` do Apache não são carregadas automaticamente pelo PHP CLI. Defina-as no PowerShell antes de executar o instalador para garantir que a migração use o mesmo banco do site, em vez de criar ou atualizar um SQLite separado.

Inicie o servidor PHP com a pasta do projeto como raiz pública:

```sh
php -S localhost:8000 -t .
```

Abra `http://localhost:8000/` para a transparência pública ou `http://localhost:8000/html/login.html` para entrar. Para SQLite em outro local, configure `UOSCC_DB_PATH`.

A página `publico.html` não exige login. Novas evidências aceitam imagens, vídeos, áudio, texto simples e PDF de até 100 MB. Com o XAMPP, `C:\xampp\php\php.ini` precisa usar `upload_max_filesize=100M` e `post_max_size=110M`; reinicie o Apache depois de alterar esses valores. O PHP também precisa ter permissão de escrita em `UOSCC/uploads/evidence` e `UOSCC/uploads/avatars`.

Relatórios permitem filtrar por OSC e intervalo de datas. O administrador também salva um título e uma narrativa por OSC/período, que entram na impressão/PDF e na planilha CSV. A opção de PDF usa a impressão do navegador (selecione “Salvar como PDF”); a planilha é exportada em CSV compatível com Excel.
