#!/usr/bin/env php
<?php
// =============================================================
// DARINE SYSTEM — Runner de importação
//
// USO (linha de comando):
//   php import.php jobs    /caminho/data_base_maidpad.xlsx
//   php import.php darine  /caminho/data_base_darine.xlsx
//   php import.php all     /caminho/data_base_maidpad.xlsx  /caminho/data_base_darine.xlsx
//
// USO via web (POST multipart/form-data):
//   POST /import.php
//   campos: source=jobs|darine|all, file_jobs=<upload>, file_darine=<upload>
// =============================================================

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

use Darine\Import\JobsImporter;
use Darine\Import\DarineImporter;

// ----------------------------------------------------------------
// Detecta contexto: CLI ou Web
// ----------------------------------------------------------------
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    runCli($argv);
} else {
    runWeb();
}

// ================================================================
// CLI
// ================================================================
function runCli(array $argv): void
{
    if (count($argv) < 3) {
        echo "Uso:\n";
        echo "  php import.php jobs   <arquivo_jobs.xlsx>\n";
        echo "  php import.php darine <arquivo_darine.xlsx>\n";
        echo "  php import.php all    <arquivo_jobs.xlsx> <arquivo_darine.xlsx>\n";
        exit(1);
    }

    $source = strtolower($argv[1]);

    switch ($source) {
        case 'jobs':
            $results = [(new JobsImporter($argv[2]))->run()];
            break;

        case 'darine':
            $results = (new DarineImporter($argv[2]))->run();
            break;

        case 'all':
            if (count($argv) < 4) {
                echo "Para 'all' informe dois arquivos: jobs e darine.\n";
                exit(1);
            }
            $results   = [(new JobsImporter($argv[2]))->run()];
            $results   = array_merge($results, (new DarineImporter($argv[3]))->run());
            break;

        default:
            echo "Source inválido: $source. Use: jobs | darine | all\n";
            exit(1);
    }

    // Exibe resultado
    echo "\n=== Resultado da importação ===\n";
    foreach ($results as $r) {
        $status = empty($r['errors']) ? 'OK' : 'COM ERROS';
        echo sprintf(
            "%-30s  importados: %4d  pulados: %4d  [%s]\n",
            $r['table'],
            $r['imported'],
            $r['skipped'],
            $status
        );
        if (!empty($r['errors'])) {
            foreach (array_slice($r['errors'], 0, 5) as $err) {
                echo "  !! $err\n";
            }
        }
    }
    echo "\n";
}

// ================================================================
// Web (upload de planilha via formulário)
// ================================================================
function runWeb(): void
{
    header('Content-Type: application/json; charset=utf-8');

    // Segurança simples: bloqueia acesso sem token
    $token = $_SERVER['HTTP_X_IMPORT_TOKEN'] ?? $_POST['token'] ?? '';
    $expectedToken = getenv('IMPORT_TOKEN') ?: 'troque-este-token';

    if ($token !== $expectedToken) {
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }

    $source = $_POST['source'] ?? '';
    $results = [];

    try {
        switch ($source) {
            case 'jobs':
                $path = moveUpload('file_jobs');
                $results = [(new JobsImporter($path))->run()];
                break;

            case 'darine':
                $path = moveUpload('file_darine');
                $results = (new DarineImporter($path))->run();
                break;

            case 'all':
                $pathJobs   = moveUpload('file_jobs');
                $pathDarine = moveUpload('file_darine');
                $results    = [(new JobsImporter($pathJobs))->run()];
                $results    = array_merge($results, (new DarineImporter($pathDarine))->run());
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => "source inválido: '$source'"]);
                exit;
        }

        echo json_encode(['success' => true, 'results' => $results]);

    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Move o arquivo enviado via upload para /tmp e retorna o caminho
 */
function moveUpload(string $field): string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException("Arquivo '$field' não enviado ou com erro.");
    }

    $allowed = ['xlsx', 'xls'];
    $ext     = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        throw new \RuntimeException("Extensão não permitida: .$ext");
    }

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('darine_', true) . '.' . $ext;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $tmpPath)) {
        throw new \RuntimeException("Falha ao mover arquivo para $tmpPath");
    }

    return $tmpPath;
}
