-- =============================================================
-- DARINE SYSTEM — Migration: mapeamento de contas para grupos DRE
-- Executar após 02_seed_dre_accounts.sql
-- =============================================================

USE darine;

-- Tabela de mapeamento: conta Wave/Budget → grupo do DRE
-- Isso evita hardcode nas queries e permite adicionar contas novas
CREATE TABLE IF NOT EXISTS account_map (
    id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(150) NOT NULL UNIQUE,  -- nome exato da conta (trimado)
    dre_group    VARCHAR(50)  NOT NULL,          -- grupo lógico para o DRE
    cash_flow_category VARCHAR(50) DEFAULT NULL, -- categoria no fluxo de caixa
    sign         TINYINT(1)   NOT NULL DEFAULT 1 -- 1 = soma, -1 = subtrai do resultado
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- RECEITAS (Income)
-- ----------------------------------------------------------------
INSERT INTO account_map (account_name, dre_group, cash_flow_category, sign) VALUES
('Cleaning Services',              'income', 'sales', 1),
('Cleaning Services - Card',       'income', 'sales', 1),
('Cleaning Services - Zelle',      'income', 'sales', 1),
('Cleaning Services - Venmo e Cheque', 'income', 'sales', 1),
('Interest Earned',                'income', 'sales', 1),
('Tips',                           'income', 'sales', 1)
ON DUPLICATE KEY UPDATE dre_group=VALUES(dre_group), cash_flow_category=VALUES(cash_flow_category), sign=VALUES(sign);

-- ----------------------------------------------------------------
-- CUSTO DO SERVIÇO (Cost of Goods Sold)
-- ----------------------------------------------------------------
INSERT INTO account_map (account_name, dre_group, cash_flow_category, sign) VALUES
('Subcontractors',       'cogs', 'purchases', -1),
('Subcontractor - Bonus','cogs', 'payroll',   -1),
('Christmas Bonus',      'cogs', 'payroll',   -1),
('Easter Bonus',         'cogs', 'payroll',   -1),
('Tips Only',            'cogs', 'purchases', -1),
('Tips VA',              'cogs', 'purchases', -1),
('Material & Small Tools','cogs','purchases', -1)
ON DUPLICATE KEY UPDATE dre_group=VALUES(dre_group), cash_flow_category=VALUES(cash_flow_category), sign=VALUES(sign);

-- ----------------------------------------------------------------
-- DESPESAS OPERACIONAIS (Operating Expenses)
-- ----------------------------------------------------------------
INSERT INTO account_map (account_name, dre_group, cash_flow_category, sign) VALUES
('Advertising & Promotion',                          'opex', 'purchases', -1),
('Bank Service Charges',                             'opex', 'purchases', -1),
('Business Insurance',                               'opex', 'purchases', -1),
('Computer - Internet',                              'opex', 'purchases', -1),
('Computer – Internet',                              'opex', 'purchases', -1),
('Computer - Software',                              'opex', 'purchases', -1),
('Computer – Software',                              'opex', 'purchases', -1),
('Donations',                                        'opex', 'purchases', -1),
('Meals and Entertainment',                          'opex', 'purchases', -1),
('Membership & Subscriptions',                       'opex', 'purchases', -1),
('Office Rental',                                    'opex', 'purchases', -1),
('Office Supplies',                                  'opex', 'purchases', -1),
('Other Business Expense - VA',                      'opex', 'purchases', -1),
('Other Business Expenses',                          'opex', 'purchases', -1),
('Other Business Expenses - Charity',                'opex', 'purchases', -1),
('Other Business Expenses - Courses',                'opex', 'purchases', -1),
('Other Business Expenses - Gifts to Subs and Clients','opex','purchases',-1),
('Professional Fees',                                'opex', 'purchases', -1),
('Sales tax',                                        'opex', 'purchases', -1),
('Taxes Paid',                                       'opex', 'purchases', -1),
('Telephone - Wireless',                             'opex', 'purchases', -1),
('Telephone – Wireless',                             'opex', 'purchases', -1),
('Utilities',                                        'opex', 'purchases', -1),
('Vehicle - Parking and Tolls',                      'opex', 'purchases', -1),
('Vehicle - Tax / DMV',                              'opex', 'purchases', -1),
('Vehicle – Fuel',                                   'opex', 'purchases', -1),
('Vehicle – Repairs & Maintenance',                  'opex', 'purchases', -1)
ON DUPLICATE KEY UPDATE dre_group=VALUES(dre_group), cash_flow_category=VALUES(cash_flow_category), sign=VALUES(sign);

-- ----------------------------------------------------------------
-- OWNER / PESSOAL (abaixo do EBITDA)
-- ----------------------------------------------------------------
INSERT INTO account_map (account_name, dre_group, cash_flow_category, sign) VALUES
('Paid to Owner Investment / Drawings',   'owner',    'financing', -1),
('Received from Owner Investment / Drawings','owner', 'financing',  1),
('Paid to Personal Expenses',             'personal', 'financing', -1),
('Paid to Personal Expenses – Vehicle',   'personal', 'financing', -1),
('Payments to Green Card (4006)',          'personal', 'purchases', -1)
ON DUPLICATE KEY UPDATE dre_group=VALUES(dre_group), cash_flow_category=VALUES(cash_flow_category), sign=VALUES(sign);

-- ----------------------------------------------------------------
-- FINANCIAMENTOS (Loan)
-- ----------------------------------------------------------------
INSERT INTO account_map (account_name, dre_group, cash_flow_category, sign) VALUES
('Proceeds from Loan Square Capital', 'loan', 'financing', 1)
ON DUPLICATE KEY UPDATE dre_group=VALUES(dre_group), cash_flow_category=VALUES(cash_flow_category), sign=VALUES(sign);
