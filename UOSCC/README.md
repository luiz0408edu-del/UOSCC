# Painel de transparência UOSCC

## MySQL

Crie o banco e configure o PHP com a extensão `pdo_mysql` habilitada:

```sh
mysql -e "CREATE DATABASE uoscc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
export UOSCC_DB_DSN='mysql:host=127.0.0.1;dbname=uoscc;charset=utf8mb4'
export UOSCC_DB_USER='uoscc'
export UOSCC_DB_PASSWORD='sua-senha-do-banco'
php UOSCC/php/setup.php admin@example.org 'uma-senha-forte-com-12-caracteres'
```

O instalador cria as tabelas usando `UOSCC/php/schema.sql` e configura ou atualiza a conta administrativa. A senha da conta deve ter pelo menos 12 caracteres.

## Perfis

- `admin`: administrador UOSCC. Cria acessos OSC em **Acessos das OSCs**, cadastra recursos e apoiadores, anexa evidências e gera relatórios.
- `osc`: acesso vinculado a uma das seis áreas. A conta só consulta e registra projetos, recursos e indicadores da própria área.
- `supporter`: cria a própria conta na tela de login e acompanha as contribuições vinculadas a ela.
- `visitor`: abre `html/publico.html` sem conta para consultar os dados públicos.

As seis áreas são educação e formação cidadã, meio ambiente e sustentabilidade, saúde e bem-estar comunitário, assistência social e apoio às famílias, direitos humanos e inclusão social, e cultura, esporte e lazer.

## Execução local

Sem `UOSCC_DB_DSN`, o projeto usa SQLite em `var/uoscc.sqlite`, fora da pasta pública:

```sh
php UOSCC/php/setup.php admin@example.org 'uma-senha-forte-com-12-caracteres'
```

Inicie o servidor PHP com a pasta do projeto como raiz pública:

```sh
php -S localhost:8000 -t UOSCC
```

Abra `http://localhost:8000/` para a transparência pública ou `http://localhost:8000/html/login.html` para entrar. Para SQLite em outro local, configure `UOSCC_DB_PATH`.

A página `publico.html` não exige login. Novas evidências aceitam imagens, vídeos, áudio, texto simples e PDF de até 25 MB. O PHP precisa ter permissão de escrita em `UOSCC/uploads/evidence`; ajuste `upload_max_filesize` e `post_max_size` no `php.ini` para pelo menos `25M` e `26M`.

Relatórios permitem filtrar por OSC e intervalo de datas. O administrador também salva um título e uma narrativa por OSC/período, que entram na impressão/PDF e na planilha CSV. A opção de PDF usa a impressão do navegador (selecione “Salvar como PDF”); a planilha é exportada em CSV compatível com Excel.