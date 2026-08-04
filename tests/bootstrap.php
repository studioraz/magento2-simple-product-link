<?php

declare(strict_types=1);

// Prefer module-local dependencies in CI, then fall back to the containing Magento project.
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        break;
    }
}

if (!isset($autoloadFile) || !file_exists($autoloadFile)) {
    throw new RuntimeException('Unable to locate a Composer autoloader.');
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
}, true, true);

// Provide a stub for Magento's translation function when running standalone unit tests
if (!function_exists('__')) {
    function __(string $text, ...$args): string
    {
        return $args ? vsprintf($text, $args) : $text;
    }
}
