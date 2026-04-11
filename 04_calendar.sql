-- =============================================================
-- DARINE SYSTEM — Migration: tabela calendar
-- Executar após 03_account_map.sql
-- =============================================================

USE darine;

CREATE TABLE IF NOT EXISTS calendar (
    cal_date    DATE         NOT NULL PRIMARY KEY,
    nome_mes    VARCHAR(10)  NOT NULL,              -- 'Jan', 'Feb', ...
    dezena      VARCHAR(10)  NOT NULL,              -- '1st TDP', '2nd TDP', '3rd TDP'
    week_label  VARCHAR(15)  NOT NULL,              -- '2026_01_W2'
    year        SMALLINT UNSIGNED NOT NULL,
    month_num   TINYINT UNSIGNED  NOT NULL,         -- 1..12
    week_num    TINYINT UNSIGNED  NOT NULL,         -- 1..5

    INDEX idx_week_label (week_label),
    INDEX idx_year_month (year, month_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
