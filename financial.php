<?php
// =============================================================
// DARINE SYSTEM — API endpoints: DRE e Fluxo de Caixa
// Cada endpoint retorna JSON para o frontend (Chart.js)
//
// Rotas (adapte ao seu MVC):
//   GET /api/dre?from=2025-01-01&to=2025-12-31
//   GET /api/dre/monthly?from=2025-01-01&to=2025-12-31
//   GET /api/dre/variance?from=2025-01-01&to=2025-12-31
//   GET /api/cashflow/weekly?from=2026-01-01&to=2026-04-30
//   GET /api/cashflow/monthly?from=2025-01-01&to=2025-12-31
//   GET /api/cashflow/summary?from=2025-01-01&to=2025-12-31
//   GET /api/cashflow/budget?from=2026-01-01&to=2026-04-30
//   GET /api/cashflow/week?date=2026-03-29
// =============================================================

require_once __DIR__ . '/../vendor/autoload.php';

use Darine\Query\DreQuery;
use Darine\Query\CashFlowQuery;

header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------
// Conexão PDO (reaproveitada do seu MVC — ajuste conforme necessário)
// ----------------------------------------------------------------
function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/../config/db.php';
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------
function jsonOk(mixed $data): void
{
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function jsonError(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function getDateRange(): array
{
    $from = $_GET['from'] ?? date('Y-01-01');
    $to   = $_GET['to']   ?? date('Y-12-31');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        jsonError('Parâmetros from e to devem estar no formato YYYY-MM-DD');
    }

    return [$from, $to];
}

// ----------------------------------------------------------------
// Roteamento simples — substitua pela rota do seu MVC
// ----------------------------------------------------------------
$route = $_GET['route'] ?? '';

try {
    $pdo       = getPdo();
    $dreQuery  = new DreQuery($pdo);
    $cfQuery   = new CashFlowQuery($pdo);

    switch ($route) {

        // DRE completo (estrutura com subtotais)
        case 'dre':
            [$from, $to] = getDateRange();
            jsonOk($dreQuery->getDre($from, $to));

        // DRE mês a mês (para gráfico de barras/linhas)
        case 'dre/monthly':
            [$from, $to] = getDateRange();
            jsonOk($dreQuery->getDreByMonth($from, $to));

        // Variação % Budget vs Realizado por conta
        case 'dre/variance':
            [$from, $to] = getDateRange();
            jsonOk($dreQuery->getVarianceByAccount($from, $to));

        // Fluxo de caixa semanal
        case 'cashflow/weekly':
            [$from, $to] = getDateRange();
            jsonOk($cfQuery->getWeeklyFlow($from, $to));

        // Fluxo de caixa mensal
        case 'cashflow/monthly':
            [$from, $to] = getDateRange();
            jsonOk($cfQuery->getMonthlyFlow($from, $to));

        // KPI cards: Gross Inflow / Outflow / Net Change
        case 'cashflow/summary':
            [$from, $to] = getDateRange();
            jsonOk($cfQuery->getSummary($from, $to));

        // Budget vs Realizado por semana
        case 'cashflow/budget':
            [$from, $to] = getDateRange();
            jsonOk($cfQuery->getBudgetVsRealized($from, $to));

        // Detalhes de uma semana (drill-down)
        case 'cashflow/week':
            $date = $_GET['date'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                jsonError('Parâmetro date obrigatório (YYYY-MM-DD)');
            }
            jsonOk($cfQuery->getWeekDetail($date));

        default:
            jsonError("Rota '$route' não encontrada", 404);
    }

} catch (\Throwable $e) {
    jsonError($e->getMessage(), 500);
}
