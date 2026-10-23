# Painel de transparência UOSCC

## MySQL

Crie o banco e configure o PHP com a extensão `pdo_mysql` habilitada:

```sh
mysql -e "CREATE DATABASE uoscc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
export UOSCC_DB_DSN='mysql:host=127.0.0.1;dbname=uoscc;charset=utf8mb4'
export UOSCC_DB_USER='uoscc'
export UOSCC_DB_PASSWORD='sua-senha-do-banco'
php php/setup.php admin@example.org 'uma-senha-forte-com-12-caracteres'
```

O instalador cria as tabelas usando `UOSCC/php/schema.sql` e configura ou atualiza a conta administrativa. A senha da conta deve ter pelo menos 12 caracteres.

## Perfis

- `admin`: administrador UOSCC. Cria acessos OSC em **Acessos das OSCs**, cadastra recursos e apoiadores, anexa evidências e gera relatórios.
- `osc`: acesso vinculado a uma das seis áreas. A conta só consulta e registra projetos, recursos, indicadores e atendimentos de projetos da própria área.
- `supporter`: cria a própria conta na tela de login, entra automaticamente após o cadastro e acompanha as contribuições vinculadas a ela.
- `visitor`: abre `html/publico.html` sem conta para consultar os dados públicos.

Ao cadastrar ou editar um projeto, o administrador define se ele recebe dinheiro, materiais e/ou voluntariado. Doações de materiais registram descrição, quantidade e unidade. O painel e a página pública permitem filtrar contribuições por OSC e período e somam materiais por projeto, descrição e unidade. No fim da página pública há rankings acumulados dos dez maiores doadores de dinheiro e dos dez maiores apoiadores voluntários; materiais não participam. Nomes de apoiadores aparecem na transparência e nos rankings somente quando a própria pessoa autoriza em **Configurações**; por padrão, a exibição é anônima.

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