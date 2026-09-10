<?php

// Expected-error test: never execute the invalid fixture or suppress source errors.
$root = dirname(__DIR__, 2);
$process = proc_open([
    PHP_BINARY, $root.'/vendor/phpstan/phpstan/phpstan', 'analyse',
    __DIR__.'/invalid-callback-types.php', '--debug', '--no-progress', '--error-format=json',
], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (! is_resource($process)) {
    throw new RuntimeException('Could not start PHPStan.');
}
$output = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
// Debug mode avoids worker sockets and prefixes JSON with analysed filenames.
$start = strpos($output, '{');
$report = $start === false ? null : json_decode(substr($output, $start), true);
$messages = array_merge(...array_values(array_map(fn (array $file): array => $file['messages'], $report['files'] ?? [])));
$expected = ['Column::url()', '$transformModelUsing', '$withQueryBuilder'];
if ($status !== 1 || count($messages) !== 3 || ($report['errors'] ?? []) !== []) {
    throw new RuntimeException("Expected exactly three callback type errors.\n".$output.$stderr);
}
foreach ($expected as $fragment) {
    $matches = array_filter($messages, fn (array $message): bool => $message['identifier'] === 'argument.type' && str_contains($message['message'], $fragment));
    if (count($matches) !== 1) {
        throw new RuntimeException('Missing expected diagnostic: '.$fragment."\n".$output);
    }
}
echo "All three invalid callback contracts were rejected.\n";
