<?php

declare(strict_types=1);

// Use the Magento project vendor autoloader which provides Magento framework classes.
// This lets PHPUnit mock Magento classes without needing them installed as composer deps.
$magentoVendor = '/Users/itay/PhpstormProjects/magento/vendor/autoload.php';
if (file_exists($magentoVendor)) {
    require_once $magentoVendor;
} else {
    // Fallback: local vendor autoloader (e.g. in CI with magento/framework installed)
    require_once __DIR__ . '/../vendor/autoload.php';
}

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
    $sourceFile = __DIR__ . '/../src/' . $relativePath;
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
