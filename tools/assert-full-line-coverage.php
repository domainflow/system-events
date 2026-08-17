<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php tools/assert-full-line-coverage.php <clover-report>\n");
    exit(2);
}

libxml_use_internal_errors(true);
$report = simplexml_load_file($argv[1]);

if ($report === false || !isset($report->project->metrics)) {
    fwrite(STDERR, "Coverage report is missing or invalid.\n");
    exit(2);
}

$metrics = $report->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "Coverage report contains no executable statements.\n");
    exit(2);
}

$percentage = $coveredStatements / $statements * 100;
printf("Line coverage: %.2f%% (%d/%d statements)\n", $percentage, $coveredStatements, $statements);

if ($coveredStatements !== $statements) {
    fwrite(STDERR, "Line coverage must be 100%.\n");
    exit(1);
}
