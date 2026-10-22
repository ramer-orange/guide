<?php

require_once __DIR__.'/../../scripts/check-php-runtime.php';

it('rejects vulnerable PHP releases and unsupported branches', function (string $version) {
    expect(fn () => assertSecurePhpVersion($version))->toThrow(RuntimeException::class);
})->with(['7.4.33', '8.1.34', '8.2.34', '8.3.35', '8.4.8', '8.4.26', '8.5.11', '8.3.36RC1']);

it('accepts patched PHP releases and vendor builds', function (string $version) {
    assertSecurePhpVersion($version);
    expect(true)->toBeTrue();
})->with(['8.2.35', '8.3.36', '8.3.37', '8.4.27', '8.5.12', '8.3.36-1ubuntu1']);
