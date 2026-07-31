<?php

declare(strict_types=1);

// Prefer the Magento project autoloader when this package is installed in vendor/.
$magentoVendor = dirname(__DIR__, 3) . '/autoload.php';
if (!file_exists($magentoVendor)) {
    // Fallback for a standalone checkout with local Composer dependencies.
    $magentoVendor = __DIR__ . '/../vendor/autoload.php';
}
require_once $magentoVendor;

// Register our module source as a PSR-4 namespace,
// with fallback to the test stubs directory for DI-generated classes.
spl_autoload_register(function (string $class): void {
    $prefix = 'SR\\SimpleProductLink\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relative) . '.php';

    // 1. Try actual source
    $sourceFile = __DIR__ . '/../src/SimpleProductLink/' . $relativePath;
    if (file_exists($sourceFile)) {
        require $sourceFile;
        return;
    }

    // 2. Try test stubs (for DI-generated classes like CollectionFactory)
    $stubFile = __DIR__ . '/Stubs/' . $relativePath;
    if (file_exists($stubFile)) {
        require $stubFile;
    }
});

// Provide a stub for Magento's translation function when running standalone unit tests
if (!function_exists('__')) {
    function __(string $text, ...$args): string
    {
        return $args ? vsprintf($text, $args) : $text;
    }
}
