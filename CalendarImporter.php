<?php
// =============================================================
// DARINE SYSTEM — Importador: tab_calendario.xlsx → calendar
// =============================================================

namespace Darine\Import;

class CalendarImporter extends BaseImporter
{
    public function run(): array
    {
        $this->pdo->exec("TRUNCATE TABLE calendar");

        $stmt = $this->pdo->prepare("
            INSERT INTO calendar (cal_date, nome_mes, dezena, week_label, year, month_num, week_num)
            VALUES (:cal_date, :nome_mes, :dezena, :week_label, :year, :month_num, :week_num)
            ON DUPLICATE KEY UPDATE
                nome_mes   = VALUES(nome_mes),
                dezena     = VALUES(dezena),
                week_label = VALUES(week_label),
                year       = VALUES(year),
                month_num  = VALUES(month_num),
                week_num   = VALUES(week_num)
        ");

        $rows = $this->readExcel();

        foreach ($rows as $i => $row) {
            $dateRaw  = $row['Date']          ?? null;
            $nomeMes  = trim($row['nome_mes'] ?? '');
            $dezena   = trim($row['Dezenas']  ?? '');
            $weekLabel= trim($row['Semana do mes'] ?? '');

            $date = $this->parseDate($dateRaw);

            if ($date === null || $weekLabel === '') {
                $this->rowsSkipped++;
                continue;
            }

            // Extrai year, month_num, week_num do week_label (ex: "2026_01_W3")
            // Formato: YYYY_MM_WN
            if (!preg_match('/^(\d{4})_(\d{2})_W(\d+)$/', $weekLabel, $m)) {
                $this->rowsSkipped++;
                $this->errors[] = "Linha $i: week_label inválido '$weekLabel'";
                continue;
            }

            try {
                $stmt->execute([
                    ':cal_date'   => $date,
                    ':nome_mes'   => $nomeMes,
                    ':dezena'     => $dezena,
                    ':week_label' => $weekLabel,
                    ':year'       => (int)$m[1],
                    ':month_num'  => (int)$m[2],
                    ':week_num'   => (int)$m[3],
                ]);
                $this->rowsImported++;
            } catch (\PDOException $e) {
                $this->rowsSkipped++;
                $this->errors[] = "Linha $i: " . $e->getMessage();
            }
        }

        $this->logImport('calendar');
        return $this->summary('calendar');
    }

    private function readExcel(): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->sourceFile);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) return [];

        $headers = array_map('trim', $rows[0]);
        $data    = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, $rows[$i]);
            if ($row !== false) $data[] = $row;
        }

        return $data;
    }
}
