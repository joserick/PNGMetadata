<?php

declare(strict_types=1);

/*
 * Coverage gate for the Clover report produced by `composer coverage`.
 *
 * BASELINE is a ratchet: it is set to the measured floor when introduced and
 * may only be raised in later changes, never lowered. CI fails below it.
 *
 * Usage:
 *   php tools/check-coverage.php [clover-file] [override-min]
 */

const DEFAULT_CLOVER = 'coverage.xml';
const BASELINE = 95.0;

$file = $argv[1] ?? DEFAULT_CLOVER;
$min  = isset($argv[2]) && is_numeric($argv[2]) ? (float) $argv[2] : BASELINE;

if (!is_file($file)) {
	fwrite(STDERR, sprintf("Clover report not found: %s\n", $file));
	exit(2);
}

$clover = simplexml_load_file($file);

if ($clover === false || !isset($clover->project->metrics)) {
	fwrite(STDERR, sprintf("Unreadable Clover report: %s\n", $file));
	exit(2);
}

$total   = (int) $clover->project->metrics['statements'];
$covered = (int) $clover->project->metrics['coveredstatements'];

if ($total === 0) {
	fwrite(STDERR, "Clover report contains no statements.\n");
	exit(2);
}

$percent = $covered / $total * 100;

printf("Coverage: %.2f%% (%d/%d statements) — gate: %.2f%%%s\n", $percent, $covered, $total, $min, $min === BASELINE ? ' (baseline)' : ' (override)');

if ($percent < $min) {
	fwrite(STDERR, sprintf("FAIL: %.2f%% is below the required %.2f%%.\n", $percent, $min));
	exit(1);
}

echo "PASS\n";
exit(0);
