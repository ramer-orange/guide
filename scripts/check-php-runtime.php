<?php

declare(strict_types=1);

function assertSecurePhpVersion(string $version = PHP_VERSION): void
{
    $minimums = ['8.2' => '8.2.36', '8.3' => '8.3.37', '8.4' => '8.4.28', '8.5' => '8.5.13'];
    $line = implode('.', array_slice(explode('.', $version), 0, 2));
    if (! isset($minimums[$line]) || version_compare($version, $minimums[$line], '<') || preg_match('/(?:alpha|beta|RC|dev)/i', $version)) {
        throw new RuntimeException("Unsupported or vulnerable PHP {$version}; use PHP 8.2.36+, 8.3.37+, 8.4.28+, or 8.5.13+ within those release lines.");
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assertSecurePhpVersion();
    echo 'PHP runtime verified: '.PHP_VERSION.PHP_EOL;
}
