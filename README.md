# Darine System — Guia de instalação e importação

## Estrutura de arquivos

```
darine/
├── composer.json
├── import.php                  ← runner de importação (CLI ou web)
├── config/
│   └── db.php                  ← credenciais do banco (não suba para o Git)
├── sql/
│   ├── 01_schema.sql           ← cria as tabelas
│   └── 02_seed_dre_accounts.sql ← popula a estrutura do DRE
└── src/
    └── Import/
        ├── BaseImporter.php
        ├── JobsImporter.php    ← importa data_base_maidpad.xlsx
        └── DarineImporter.php  ← importa data_base_darine.xlsx
```

---

## Passo 1 — Criar o banco de dados

Abra seu cliente MySQL (phpMyAdmin, HeidiSQL, Sequel Pro ou linha de comando) e execute:

```sql
-- Arquivo: sql/01_schema.sql
-- Cria o banco "darine" e todas as tabelas
```

Depois execute:

```sql
-- Arquivo: sql/02_seed_dre_accounts.sql
-- Popula a estrutura do DRE (12 linhas fixas)
```

No phpMyAdmin: aba "SQL" → cole o conteúdo → Execute.

---

## Passo 2 — Configurar banco de dados

Edite `config/db.php` com suas credenciais:

```php
return [
    'host'     => 'localhost',
    'dbname'   => 'darine',
    'user'     => 'seu_usuario',
    'password' => 'sua_senha',
    'charset'  => 'utf8mb4',
];
```

---

## Passo 3 — Instalar dependências PHP

Na pasta `darine/`, rode:

```bash
composer install
```

Isso instala o PhpSpreadsheet, que é a biblioteca que lê arquivos `.xlsx`.

Se não tiver o Composer instalado:
- Windows: baixe em https://getcomposer.org/download/
- Após instalar, abra o terminal na pasta do projeto e rode o comando acima.

---

## Passo 4 — Importar as planilhas

### Via linha de comando

```bash
# Importa somente os jobs
php import.php jobs C:\caminho\data_base_maidpad.xlsx

# Importa budget, wave e subcontractors
php import.php darine C:\caminho\data_base_darine.xlsx

# Importa tudo de uma vez
php import.php all C:\caminho\data_base_maidpad.xlsx C:\caminho\data_base_darine.xlsx
```

### Via web (upload de planilha)

Se preferir fazer pelo navegador, faça um POST para `import.php` com:

| Campo        | Valor                                    |
|--------------|------------------------------------------|
| source       | `jobs` \| `darine` \| `all`             |
| file_jobs    | arquivo `data_base_maidpad.xlsx`         |
| file_darine  | arquivo `data_base_darine.xlsx`          |
| token        | valor definido em `IMPORT_TOKEN` no .env |

O endpoint retorna JSON com o resultado:

```json
{
  "success": true,
  "results": [
    { "table": "jobs",         "imported": 5140, "skipped": 0,  "errors": [] },
    { "table": "dre_budget",   "imported": 317,  "skipped": 0,  "errors": [] },
    { "table": "dre_realized", "imported": 431,  "skipped": 0,  "errors": [] },
    { "table": "subcontractors_weekly", "imported": 13, "skipped": 0, "errors": [] }
  ]
}
```

---

## O que cada tabela armazena

| Tabela                  | Origem                              | Conteúdo                               |
|-------------------------|-------------------------------------|----------------------------------------|
| `jobs`                  | data_base_maidpad.xlsx → Jobs       | Todos os serviços (5.140 registros)    |
| `dre_accounts`          | Mascara_dre.xlsx (seed fixo)        | Estrutura do DRE (12 contas)           |
| `dre_budget`            | data_base_darine.xlsx → budget_data | Valores orçados por semana (317 linhas)|
| `dre_realized`          | data_base_darine.xlsx → wave_data   | Valores realizados Wave (431 linhas)   |
| `subcontractors_weekly` | data_base_darine.xlsx → subcontractor | Headcount semanal (13 semanas)       |
| `imports_log`           | gerado automaticamente              | Histórico de cada importação           |

---

## Reimportação (atualização mensal)

Basta rodar o importador novamente com o arquivo novo. Cada importador faz `TRUNCATE` antes de inserir — os dados antigos são substituídos pelos novos.

Se quiser manter histórico e fazer importação incremental (sem apagar dados anteriores), avise que ajusto o importador para isso.

---

## Próximo passo

Com o banco populado, o próximo arquivo a receber será o **módulo de consultas** (`src/Query/`) — as queries PHP que alimentam cada tela do dashboard.
