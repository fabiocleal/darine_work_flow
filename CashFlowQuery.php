<?php
// =============================================================
// DARINE SYSTEM — Query: Fluxo de Caixa (Cash Flow)
// Baseado em wave_data agrupado por período
// Estrutura: Sales → Purchases → Payroll → Financing → Net Change
// =============================================================

namespace Darine\Query;

use PDO;

class CashFlowQuery
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ----------------------------------------------------------------
    // Fluxo de caixa semanal (modelo da aba "calculos")
    // Retorna: Operating, Investing, Financing, Net Change por semana
    // ----------------------------------------------------------------
    public function getWeeklyFlow(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                r.period_date                          AS week_date,
                m.cash_flow_category                   AS category,
                r.account,
                SUM(r.amount * m.sign)                 AS amount
            FROM dre_realized r
            JOIN account_map m
                ON m.account_name = r.account
                AND m.cash_flow_category IS NOT NULL
            WHERE r.period_date BETWEEN :from AND :to
            GROUP BY r.period_date, m.cash_flow_category, r.account
            ORDER BY r.period_date, m.cash_flow_category, r.account
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $rows = $stmt->fetchAll();

        return $this->buildWeeklyStructure($rows);
    }

    // ----------------------------------------------------------------
    // Fluxo de caixa mensal (agrupado por mês — para gráfico de linhas)
    // ----------------------------------------------------------------
    public function getMonthlyFlow(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(r.period_date, '%Y-%m')    AS month,
                m.cash_flow_category                   AS category,
                SUM(r.amount * m.sign)                 AS amount
            FROM dre_realized r
            JOIN account_map m
                ON m.account_name = r.account
                AND m.cash_flow_category IS NOT NULL
            WHERE r.period_date BETWEEN :from AND :to
            GROUP BY month, m.cash_flow_category
            ORDER BY month, m.cash_flow_category
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $rows = $stmt->fetchAll();

        return $this->buildMonthlyStructure($rows);
    }

    // ----------------------------------------------------------------
    // Resumo de caixa: Gross Inflow / Gross Outflow / Net Change
    // Para os KPI cards do topo da tela
    // ----------------------------------------------------------------
    public function getSummary(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                CASE
                    WHEN r.amount * m.sign > 0 THEN 'inflow'
                    ELSE 'outflow'
                END AS flow_type,
                SUM(ABS(r.amount * m.sign)) AS total
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            WHERE r.period_date BETWEEN :from AND :to
              AND m.cash_flow_category IN ('sales', 'purchases', 'payroll')
            GROUP BY flow_type
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $inflow  = (float)($rows['inflow']  ?? 0);
        $outflow = (float)($rows['outflow'] ?? 0);

        return [
            'gross_inflow'  => $inflow,
            'gross_outflow' => $outflow,
            'net_change'    => $inflow - $outflow,
        ];
    }

    // ----------------------------------------------------------------
    // Budget vs Realizado por semana (para a tela C.F. Budget_Real)
    // ----------------------------------------------------------------
    public function getBudgetVsRealized(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                r.period_date                      AS period,
                m.cash_flow_category               AS category,
                SUM(r.amount * m.sign)             AS realized,
                COALESCE(SUM(b.amount * m.sign), 0) AS budget
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            LEFT JOIN dre_budget b
                ON b.account = r.account
                AND b.period_date = r.period_date
            WHERE r.period_date BETWEEN :from AND :to
              AND m.cash_flow_category IS NOT NULL
            GROUP BY r.period_date, m.cash_flow_category
            ORDER BY r.period_date, m.cash_flow_category
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $rows = $stmt->fetchAll();

        // Pivota: período → {sales_realized, sales_budget, purchases_realized, ...}
        $result = [];
        foreach ($rows as $row) {
            $p = $row['period'];
            if (!isset($result[$p])) {
                $result[$p] = ['period' => $p];
            }
            $cat = $row['category'];
            $result[$p][$cat . '_realized'] = (float)$row['realized'];
            $result[$p][$cat . '_budget']   = (float)$row['budget'];
        }

        // Calcula net por período
        foreach ($result as &$p) {
            $p['net_realized'] = ($p['sales_realized'] ?? 0)
                               - ($p['purchases_realized'] ?? 0)
                               - ($p['payroll_realized'] ?? 0);

            $p['net_budget']   = ($p['sales_budget'] ?? 0)
                               - ($p['purchases_budget'] ?? 0)
                               - ($p['payroll_budget'] ?? 0);

            $p['net_variance'] = $p['net_realized'] - $p['net_budget'];

            $p['net_variance_pct'] = $p['net_budget'] != 0
                ? round(($p['net_variance'] / abs($p['net_budget'])) * 100, 2)
                : null;
        }
        unset($p);

        return array_values($result);
    }

    // ----------------------------------------------------------------
    // Detalhes de uma semana específica (drill-down)
    // ----------------------------------------------------------------
    public function getWeekDetail(string $weekDate): array
    {
        $sql = "
            SELECT
                r.account,
                m.cash_flow_category AS category,
                m.dre_group,
                SUM(r.amount * m.sign) AS amount
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            WHERE r.period_date = :week_date
            GROUP BY r.account, m.cash_flow_category, m.dre_group
            ORDER BY m.cash_flow_category, amount DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':week_date' => $weekDate]);
        $rows = $stmt->fetchAll();

        // Agrupa por categoria
        $result = [];
        foreach ($rows as $row) {
            $cat = $row['category'] ?? 'other';
            if (!isset($result[$cat])) {
                $result[$cat] = ['total' => 0.0, 'lines' => []];
            }
            $result[$cat]['total'] += (float)$row['amount'];
            $result[$cat]['lines'][] = [
                'account' => $row['account'],
                'amount'  => (float)$row['amount'],
            ];
        }

        return $result;
    }

    // ----------------------------------------------------------------
    // Privados
    // ----------------------------------------------------------------
    private function buildWeeklyStructure(array $rows): array
    {
        $weeks = [];

        foreach ($rows as $row) {
            $w   = $row['week_date'];
            $cat = $row['category'];
            $amt = (float)$row['amount'];

            if (!isset($weeks[$w])) {
                $weeks[$w] = [
                    'week_date'  => $w,
                    'sales'      => 0.0,
                    'purchases'  => 0.0,
                    'payroll'    => 0.0,
                    'financing'  => 0.0,
                    'details'    => [],
                ];
            }

            if (isset($weeks[$w][$cat])) {
                $weeks[$w][$cat] += $amt;
            }

            $weeks[$w]['details'][] = [
                'category' => $cat,
                'account'  => $row['account'],
                'amount'   => $amt,
            ];
        }

        // Calcula net operating e net total por semana
        foreach ($weeks as &$w) {
            $w['net_operating'] = $w['sales'] - $w['purchases'] - $w['payroll'];
            $w['net_total']     = $w['net_operating'] + $w['financing'];
        }
        unset($w);

        return array_values($weeks);
    }

    private function buildMonthlyStructure(array $rows): array
    {
        $months = [];

        foreach ($rows as $row) {
            $m   = $row['month'];
            $cat = $row['category'];
            $amt = (float)$row['amount'];

            if (!isset($months[$m])) {
                $months[$m] = [
                    'month'     => $m,
                    'sales'     => 0.0,
                    'purchases' => 0.0,
                    'payroll'   => 0.0,
                    'financing' => 0.0,
                ];
            }

            if (isset($months[$m][$cat])) {
                $months[$m][$cat] += $amt;
            }
        }

        foreach ($months as &$m) {
            $m['net_operating'] = $m['sales'] - $m['purchases'] - $m['payroll'];
            $m['net_total']     = $m['net_operating'] + $m['financing'];

            // Margem operacional %
            $m['operating_margin_pct'] = $m['sales'] > 0
                ? round(($m['net_operating'] / $m['sales']) * 100, 2)
                : 0.0;
        }
        unset($m);

        return array_values($months);
    }
}
