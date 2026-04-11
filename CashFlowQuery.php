<?php
// =============================================================
// DARINE SYSTEM — Query: Fluxo de Caixa (v2 — usa calendar)
// Comparativo Budget x Realizado agrupado por week_label
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
    // PRINCIPAL: Budget x Realizado por semana
    // ----------------------------------------------------------------
    public function getWeeklyBudgetVsRealized(
        int $year,
        int $monthFrom = 1,
        int $monthTo   = 12
    ): array {

        $sqlRealized = "
            SELECT
                cr.week_label,
                cr.nome_mes,
                cr.month_num,
                cr.week_num,
                SUM(CASE WHEN m.dre_group = 'income'
                    THEN r.amount * m.sign ELSE 0 END)          AS income_realized,
                SUM(CASE WHEN m.dre_group != 'income'
                    THEN ABS(r.amount * m.sign) ELSE 0 END)     AS expenses_realized
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            JOIN calendar cr   ON cr.cal_date = r.period_date
            WHERE cr.year = :year
              AND cr.month_num BETWEEN :m_from AND :m_to
            GROUP BY cr.week_label, cr.nome_mes, cr.month_num, cr.week_num
        ";

        $sqlBudget = "
            SELECT
                cb.week_label,
                SUM(CASE WHEN m.dre_group = 'income'
                    THEN b.amount ELSE 0 END)                   AS income_budget,
                SUM(CASE WHEN m.dre_group != 'income'
                    THEN ABS(b.amount) ELSE 0 END)              AS expenses_budget
            FROM dre_budget b
            JOIN account_map m ON m.account_name = b.account
            JOIN calendar cb   ON cb.cal_date = b.period_date
            WHERE cb.year = :year
              AND cb.month_num BETWEEN :m_from AND :m_to
            GROUP BY cb.week_label
        ";

        $stmtR = $this->pdo->prepare($sqlRealized);
        $stmtR->execute([':year' => $year, ':m_from' => $monthFrom, ':m_to' => $monthTo]);
        $realized = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        $stmtB = $this->pdo->prepare($sqlBudget);
        $stmtB->execute([':year' => $year, ':m_from' => $monthFrom, ':m_to' => $monthTo]);
        $budget = array_column($stmtB->fetchAll(PDO::FETCH_ASSOC), null, 'week_label');

        $result = [];
        foreach ($realized as $row) {
            $wk = $row['week_label'];
            $b  = $budget[$wk] ?? ['income_budget' => 0, 'expenses_budget' => 0];

            $incR = (float)$row['income_realized'];
            $expR = (float)$row['expenses_realized'];
            $netR = $incR - $expR;

            $incB = (float)$b['income_budget'];
            $expB = (float)$b['expenses_budget'];
            $netB = $incB - $expB;

            $result[] = [
                'week_label'         => $wk,
                'week_short'         => 'W' . $row['week_num'],
                'nome_mes'           => $row['nome_mes'],
                'month_num'          => (int)$row['month_num'],
                'week_num'           => (int)$row['week_num'],
                'income_budget'      => round($incB, 2),
                'income_realized'    => round($incR, 2),
                'income_var'         => round($incR - $incB, 2),
                'income_var_pct'     => $this->varPct($incR, $incB),
                'expenses_budget'    => round($expB, 2),
                'expenses_realized'  => round($expR, 2),
                'expenses_var'       => round($expR - $expB, 2),
                'expenses_var_pct'   => $this->varPct($expR, $expB),
                'net_budget'         => round($netB, 2),
                'net_realized'       => round($netR, 2),
                'net_var'            => round($netR - $netB, 2),
                'net_var_pct'        => $this->varPct($netR, $netB),
            ];
        }

        usort($result, fn($a, $b) => strcmp($a['week_label'], $b['week_label']));
        return $result;
    }

    // ----------------------------------------------------------------
    // Totais do período (linha de rodapé)
    // ----------------------------------------------------------------
    public function getPeriodTotals(array $weeks): array
    {
        $t = [
            'week_label'         => 'TOTAL',
            'week_short'         => 'Total',
            'nome_mes'           => '',
            'income_budget'      => 0, 'income_realized'   => 0,
            'expenses_budget'    => 0, 'expenses_realized' => 0,
            'net_budget'         => 0, 'net_realized'      => 0,
        ];

        foreach ($weeks as $w) {
            $t['income_budget']      += $w['income_budget'];
            $t['income_realized']    += $w['income_realized'];
            $t['expenses_budget']    += $w['expenses_budget'];
            $t['expenses_realized']  += $w['expenses_realized'];
            $t['net_budget']         += $w['net_budget'];
            $t['net_realized']       += $w['net_realized'];
        }

        $t['income_var']       = round($t['income_realized']   - $t['income_budget'],   2);
        $t['income_var_pct']   = $this->varPct($t['income_realized'],   $t['income_budget']);
        $t['expenses_var']     = round($t['expenses_realized'] - $t['expenses_budget'], 2);
        $t['expenses_var_pct'] = $this->varPct($t['expenses_realized'], $t['expenses_budget']);
        $t['net_var']          = round($t['net_realized']       - $t['net_budget'],      2);
        $t['net_var_pct']      = $this->varPct($t['net_realized'], $t['net_budget']);

        return $t;
    }

    // ----------------------------------------------------------------
    // KPI cards do topo
    // ----------------------------------------------------------------
    public function getSummaryKPIs(int $year, int $monthFrom = 1, int $monthTo = 12): array
    {
        $weeks  = $this->getWeeklyBudgetVsRealized($year, $monthFrom, $monthTo);
        $totals = $this->getPeriodTotals($weeks);

        return [
            'gross_inflow_realized'  => $totals['income_realized'],
            'gross_inflow_budget'    => $totals['income_budget'],
            'gross_inflow_var_pct'   => $totals['income_var_pct'],
            'gross_outflow_realized' => $totals['expenses_realized'],
            'gross_outflow_budget'   => $totals['expenses_budget'],
            'gross_outflow_var_pct'  => $totals['expenses_var_pct'],
            'net_realized'           => $totals['net_realized'],
            'net_budget'             => $totals['net_budget'],
            'net_var_pct'            => $totals['net_var_pct'],
            'weeks_count'            => count($weeks),
        ];
    }

    // ----------------------------------------------------------------
    // Detalhe por conta de uma semana (drill-down)
    // ----------------------------------------------------------------
    public function getWeekDetail(string $weekLabel): array
    {
        $sql = "
            SELECT
                r.account,
                m.dre_group,
                SUM(r.amount * m.sign) AS realized
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            JOIN calendar cr   ON cr.cal_date = r.period_date
            WHERE cr.week_label = :week
            GROUP BY r.account, m.dre_group
            ORDER BY m.dre_group, realized DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':week' => $weekLabel]);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $g = $row['dre_group'];
            if (!isset($grouped[$g])) {
                $grouped[$g] = ['total' => 0, 'lines' => []];
            }
            $grouped[$g]['total']   += (float)$row['realized'];
            $grouped[$g]['lines'][] = [
                'account'  => $row['account'],
                'realized' => round((float)$row['realized'], 2),
            ];
        }

        return $grouped;
    }

    private function varPct(float $realized, float $budget): ?float
    {
        if ($budget == 0) return null;
        return round((($realized - $budget) / abs($budget)) * 100, 1);
    }
}
