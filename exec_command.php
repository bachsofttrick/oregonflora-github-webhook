<?php

$command = 'cd ~ && ls';

$output = [];
$exitCode = 0;

exec($command, $output, $exitCode);

echo "Command: " . $command . "\n";
echo "\n--- Output ---\n";
echo implode("\n", $output) . "\n";
echo "\n--- Exit Code ---\n";
echo "Code: " . $exitCode . "\n";
?>
