# Módulo Financeiro — Guia de uso e exemplos de resposta

## Arquivos desta fase

```
darine/
├── sql/
│   └── 03_account_map.sql          ← NOVO: mapeia contas para grupos DRE
├── src/Query/
│   ├── DreQuery.php                ← NOVO: queries do DRE
│   └── CashFlowQuery.php           ← NOVO: queries do fluxo de caixa
└── api/
    └── financial.php               ← NOVO: endpoints JSON para o frontend
```

---

## Passo 1 — Executar a migration

No phpMyAdmin, execute `sql/03_account_map.sql`.
Isso cria a tabela `account_map` que classifica cada conta do Wave/Budget
em um grupo do DRE (`income`, `cogs`, `opex`, `owner`, `personal`, `loan`).

---

## Endpoints disponíveis

### DRE completo
```
GET /api/financial.php?route=dre&from=2025-01-01&to=2025-12-31
```

Resposta:
```json
{
  "success": true,
  "data": {
    "income":   { "realized": 937541.92, "budget": 190375.25, "accounts": [...] },
    "cogs":     { "realized": 581074.70, "budget": 0,         "accounts": [...] },
    "gross_profit": { "realized": 356467.22, "budget": 190375.25 },
    "opex":     { "realized": 136937.72, "budget": 0,         "accounts": [...] },
    "ebitda":   { "realized": 219529.50, "budget": 190375.25 },
    "owner":    { "realized": 153855.26, "budget": 0,         "accounts": [...] },
    "personal": { "realized": 22085.78,  "budget": 0,         "accounts": [...] },
    "net_profit":  { "realized": 43588.46,  "budget": 190375.25 },
    "loan":     { "realized": 110000.00, "budget": 0,         "accounts": [...] },
    "cash_result": { "realized": 153588.46, "budget": 190375.25 }
  }
}
```

### DRE mês a mês (para gráfico)
```
GET /api/financial.php?route=dre/monthly&from=2025-01-01&to=2025-12-31
```

Resposta — array de meses:
```json
{
  "data": [
    {
      "month": "2025-01",
      "income_realized": 60995.50,
      "income_budget": 0,
      "cogs_realized": 38420.00,
      "opex_realized": 9876.54,
      "gross_profit_realized": 22575.50,
      "ebitda_realized": 12698.96,
      "net_profit_realized": -2156.30
    },
    ...
  ]
}
```

### Variação % Budget vs Realizado (tela C.F.v.a%)
```
GET /api/financial.php?route=dre/variance&from=2026-01-01&to=2026-04-30
```

Resposta:
```json
{
  "data": [
    {
      "account": "Cleaning Services - Card",
      "dre_group": "income",
      "realized": 172940.86,
      "budget": 190375.25,
      "variance_pct": -9.16
    },
    ...
  ]
}
```

### Fluxo de caixa mensal (para gráfico linha/coluna combo)
```
GET /api/financial.php?route=cashflow/monthly&from=2025-01-01&to=2025-12-31
```

Resposta:
```json
{
  "data": [
    {
      "month": "2025-01",
      "sales": 60995.50,
      "purchases": 49820.00,
      "payroll": 1712.92,
      "financing": 0,
      "net_operating": 9462.58,
      "net_total": 9462.58,
      "operating_margin_pct": 15.51
    },
    ...
  ]
}
```

### Budget vs Realizado semanal (tela C.F. Budget_Real)
```
GET /api/financial.php?route=cashflow/budget&from=2026-01-01&to=2026-04-30
```

Resposta:
```json
{
  "data": [
    {
      "period": "2026-01-10",
      "sales_realized": 13178.38,
      "sales_budget": 13978.21,
      "purchases_realized": -14320.31,
      "purchases_budget": 0,
      "net_realized": -1141.93,
      "net_budget": 13978.21,
      "net_variance": -15120.14,
      "net_variance_pct": -108.17
    },
    ...
  ]
}
```

### KPI summary (cards do topo)
```
GET /api/financial.php?route=cashflow/summary&from=2025-01-01&to=2025-12-31
```

Resposta:
```json
{
  "data": {
    "gross_inflow": 937541.92,
    "gross_outflow": 718012.42,
    "net_change": 219529.50
  }
}
```

### Detalhes de uma semana (drill-down ao clicar no gráfico)
```
GET /api/financial.php?route=cashflow/week&date=2026-03-29
```

Resposta:
```json
{
  "data": {
    "sales": {
      "total": 13178.38,
      "lines": [
        { "account": "Cleaning Services - Card", "amount": 12798.35 },
        { "account": "Cleaning Services - Zelle", "amount": 380.00 }
      ]
    },
    "purchases": {
      "total": -16420.31,
      "lines": [
        { "account": "Subcontractors",     "amount": -8900.00 },
        { "account": "Payments to Green Card (4006)", "amount": -4000.00 }
      ]
    }
  }
}
```

---

## Como integrar no seu MVC

No seu controller, substitua o roteamento simples do `financial.php` pela
convenção do seu MVC. Exemplo:

```php
// FinancialController.php
public function dre(): void
{
    $from = $_GET['from'] ?? date('Y-01-01');
    $to   = $_GET['to']   ?? date('Y-12-31');

    $query = new \Darine\Query\DreQuery($this->pdo);
    $this->json($query->getDre($from, $to));
}

public function cashflowMonthly(): void
{
    [$from, $to] = $this->dateRange();
    $query = new \Darine\Query\CashFlowQuery($this->pdo);
    $this->json($query->getMonthlyFlow($from, $to));
}
```

---

## Próximo passo

Com os endpoints prontos, o próximo passo é a **Fase 3 — telas HTML/PHP**:
a DRE visual (tabela Budget vs Realizado + gráfico de barras por mês)
e o Fluxo de Caixa (gráfico combo linha+coluna semanal + cards de KPI).
