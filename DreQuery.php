<?php
// =============================================================
// DARINE SYSTEM — Query: DRE (Budget vs Realizado)
// =============================================================

namespace Darine\Query;

use PDO;

class DreQuery
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ----------------------------------------------------------------
    // DRE completo: Budget vs Realizado com subtotais
    // Agrupa por mês ou semana conforme $groupBy
    //
    // Retorna array estruturado:
    // [
    //   'income'   => ['budget' => 0.0, 'realized' => 0.0, 'accounts' => [...]],
    //   'cogs'     => [...],
    //   'gross_profit' => ['budget' => 0.0, 'realized' => 0.0],  // subtotal
    //   'opex'     => [...],
    //   'ebitda'   => [...],
    //   'owner'    => [...],
    //   'personal' => [...],
    //   'net_profit'=> [...],
    //   'loan'     => [...],
    //   'cash_result'=> [...],
    // ]
    // ----------------------------------------------------------------
    public function getDre(
        string $dateFrom,
        string $dateTo,
        string $groupBy = 'month'  // 'month' | 'week'
    ): array {
        // 1. Busca realizados agrupados por conta e grupo DRE
        $realized = $this->fetchRealized($dateFrom, $dateTo);

        // 2. Busca budget agrupados por conta e grupo DRE
        $budget = $this->fetchBudget($dateFrom, $dateTo);

        // 3. Monta estrutura do DRE
        return $this->buildDreStructure($realized, $budget);
    }

    // ----------------------------------------------------------------
    // DRE mensal: retorna uma linha por mês para gráfico
    // ----------------------------------------------------------------
    public function getDreByMonth(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(r.period_date, '%Y-%m') AS month,
                m.dre_group,
                SUM(r.amount * m.sign)              AS realized,
                COALESCE(SUM(b.amount), 0)          AS budget
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            LEFT JOIN dre_budget b
                ON b.account = r.account
                AND DATE_FORMAT(b.period_date, '%Y-%m') = DATE_FORMAT(r.period_date, '%Y-%m')
            WHERE r.period_date BETWEEN :from AND :to
            GROUP BY month, m.dre_group
            ORDER BY month, m.dre_group
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $rows = $stmt->fetchAll();

        // Pivota: month → {income, cogs, opex, net_profit...}
        $result = [];
        foreach ($rows as $row) {
            $m = $row['month'];
            if (!isset($result[$m])) {
                $result[$m] = ['month' => $m];
            }
            $result[$m][$row['dre_group'] . '_realized'] = (float)$row['realized'];
            $result[$m][$row['dre_group'] . '_budget']   = (float)$row['budget'];
        }

        // Calcula subtotais por mês
        foreach ($result as &$m) {
            $inc  = $m['income_realized']   ?? 0;
            $cogs = $m['cogs_realized']     ?? 0;
            $opex = $m['opex_realized']     ?? 0;
            $own  = $m['owner_realized']    ?? 0;
            $pers = $m['personal_realized'] ?? 0;

            $m['gross_profit_realized'] = $inc - $cogs;
            $m['ebitda_realized']       = $inc - $cogs - $opex;
            $m['net_profit_realized']   = $inc - $cogs - $opex - $own - $pers;

            // Budget subtotais
            $incB  = $m['income_budget']   ?? 0;
            $cogsB = $m['cogs_budget']     ?? 0;
            $opexB = $m['opex_budget']     ?? 0;

            $m['gross_profit_budget'] = $incB - $cogsB;
            $m['ebitda_budget']       = $incB - $cogsB - $opexB;
        }
        unset($m);

        return array_values($result);
    }

    // ----------------------------------------------------------------
    // Variação percentual Budget vs Realizado por conta (v.a.%)
    // ----------------------------------------------------------------
    public function getVarianceByAccount(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                r.account,
                m.dre_group,
                SUM(r.amount * m.sign)     AS realized,
                COALESCE(SUM(b.amount), 0) AS budget,
                CASE
                    WHEN COALESCE(SUM(b.amount), 0) = 0 THEN NULL
                    ELSE ROUND(
                        ((SUM(r.amount * m.sign) - COALESCE(SUM(b.amount), 0))
                         / ABS(COALESCE(SUM(b.amount), 0))) * 100,
                        2
                    )
                END AS variance_pct
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            LEFT JOIN dre_budget b ON b.account = r.account
                AND DATE_FORMAT(b.period_date, '%Y-%m') = DATE_FORMAT(r.period_date, '%Y-%m')
            WHERE r.period_date BETWEEN :from AND :to
            GROUP BY r.account, m.dre_group
            ORDER BY m.dre_group, ABS(COALESCE(variance_pct, 0)) DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // Privados
    // ----------------------------------------------------------------
    private function fetchRealized(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                r.account,
                m.dre_group,
                SUM(r.amount * m.sign) AS total
            FROM dre_realized r
            JOIN account_map m ON m.account_name = r.account
            WHERE r.period_date BETWEEN :from AND :to
            GROUP BY r.account, m.dre_group
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll();
    }

    private function fetchBudget(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                b.account,
                m.dre_group,
                SUM(b.amount) AS total
            FROM dre_budget b
            JOIN account_map m ON m.account_name = b.account
            WHERE b.period_date BETWEEN :from AND :to
            GROUP BY b.account, m.dre_group
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll();
    }

    private function buildDreStructure(array $realized, array $budget): array
    {
        // Indexa por grupo
        $r = $this->indexByGroup($realized);
        $b = $this->indexByGroup($budget);

        $groups = ['income', 'cogs', 'opex', 'owner', 'personal', 'loan'];
        $dre    = [];

        foreach ($groups as $group) {
            $dre[$group] = [
                'realized' => $r[$group]['total'] ?? 0.0,
                'budget'   => $b[$group]['total'] ?? 0.0,
                'accounts' => $r[$group]['accounts'] ?? [],
            ];
        }

        // Subtotais
        $dre['gross_profit'] = [
            'realized' => $dre['income']['realized'] - $dre['cogs']['realized'],
            'budget'   => $dre['income']['budget']   - $dre['cogs']['budget'],
        ];

        $dre['ebitda'] = [
            'realized' => $dre['gross_profit']['realized'] - $dre['opex']['realized'],
            'budget'   => $dre['gross_profit']['budget']   - $dre['opex']['budget'],
        ];

        $dre['net_profit'] = [
            'realized' => $dre['ebitda']['realized'] - $dre['owner']['realized'] - $dre['personal']['realized'],
            'budget'   => $dre['ebitda']['budget']   - $dre['owner']['budget']   - $dre['personal']['budget'],
        ];

        $dre['cash_result'] = [
            'realized' => $dre['net_profit']['realized'] + $dre['loan']['realized'],
            'budget'   => $dre['net_profit']['budget']   + $dre['loan']['budget'],
        ];

        return $dre;
    }

    private function indexByGroup(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $g = $row['dre_group'];
            if (!isset($index[$g])) {
                $index[$g] = ['total' => 0.0, 'accounts' => []];
            }
            $index[$g]['total'] += (float)$row['total'];
            $index[$g]['accounts'][] = [
                'account'  => $row['account'],
                'total'    => (float)$row['total'],
            ];
        }
        return $index;
    }
}
