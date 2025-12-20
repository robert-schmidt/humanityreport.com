<?php
/**
 * Build Script for HumanityReport.com
 *
 * Handles:
 * 1. CSS/JS minification
 * 2. Updating file references with cache busting
 *
 * Usage: php build.php
 */

echo "Starting build process...\n";

// Create backups of existing minified files
$backupFiles = [
    'styles.min.css' => 'styles.min.css.bak',
    'script.min.js' => 'script.min.js.bak'
];

foreach ($backupFiles as $original => $backup) {
    if (file_exists($original)) {
        copy($original, $backup);
        echo "Created backup: $backup\n";
    }
}

// Minify CSS
$cssContent = file_get_contents('styles.css');
if ($cssContent === false) {
    die("Error: Could not read styles.css\n");
}

$originalCssSize = strlen($cssContent);

// CSS minification
$cssContent = preg_replace('/\/\*.*?\*\//s', '', $cssContent); // Remove comments
$cssContent = preg_replace('/\s+/', ' ', $cssContent); // Collapse whitespace
$cssContent = preg_replace('/;\s*}/', '}', $cssContent); // Remove semicolon before closing brace
$cssContent = preg_replace('/\s*{\s*/', '{', $cssContent); // Remove spaces around opening brace
$cssContent = preg_replace('/;\s*/', ';', $cssContent); // Remove spaces after semicolon
$cssContent = preg_replace('/:\s*/', ':', $cssContent); // Remove spaces after colon
$cssContent = preg_replace('/,\s*/', ',', $cssContent); // Remove spaces after comma
$cssContent = trim($cssContent);

$minifiedCssSize = strlen($cssContent);
$cssReduction = $originalCssSize > 0 ? round((($originalCssSize - $minifiedCssSize) / $originalCssSize) * 100, 2) : 0;

file_put_contents('styles.min.css', $cssContent);
echo "CSS minified: styles.css -> styles.min.css ({$cssReduction}% reduction)\n";

// Minify JavaScript
$jsContent = file_get_contents('script.js');
if ($jsContent === false) {
    die("Error: Could not read script.js\n");
}

$originalJsSize = strlen($jsContent);

// Conservative JavaScript minification to avoid breaking URLs
$jsContent = preg_replace('/\/\/.*$/m', '', $jsContent); // Remove single-line comments
$jsContent = preg_replace('/\/\*.*?\*\//s', '', $jsContent); // Remove multi-line comments
$jsContent = preg_replace('/\s+/', ' ', $jsContent); // Collapse whitespace
$jsContent = preg_replace('/\s*([{}();,])\s*/', '$1', $jsContent); // Remove spaces around operators
$jsContent = trim($jsContent);

$minifiedJsSize = strlen($jsContent);
$jsReduction = $originalJsSize > 0 ? round((($originalJsSize - $minifiedJsSize) / $originalJsSize) * 100, 2) : 0;

file_put_contents('script.min.js', $jsContent);
echo "JavaScript minified: script.js -> script.min.js ({$jsReduction}% reduction)\n";

// Update HTML with cache-busting timestamps
$htmlContent = file_get_contents('index.html');
if ($htmlContent === false) {
    die("Error: Could not read index.html\n");
}

$timestamp = time();

// Update CSS link
$htmlContent = preg_replace(
    '/styles\.min\.css\?[0-9]+/',
    "styles.min.css?$timestamp",
    $htmlContent
);

// Update JS link
$htmlContent = preg_replace(
    '/script\.min\.js\?[0-9]+/',
    "script.min.js?$timestamp",
    $htmlContent
);

file_put_contents('index.html', $htmlContent);
echo "Updated HTML with cache-busting timestamps\n";

// Calculate total reduction
$totalOriginalSize = $originalCssSize + $originalJsSize;
$totalMinifiedSize = $minifiedCssSize + $minifiedJsSize;
$totalReduction = $totalOriginalSize > 0 ? round((($totalOriginalSize - $totalMinifiedSize) / $totalOriginalSize) * 100, 2) : 0;

echo "\nBuild completed!\n";
echo "=== File Size Summary ===\n";
echo "CSS: " . number_format($originalCssSize / 1024, 2) . " KB -> " . number_format($minifiedCssSize / 1024, 2) . " KB ({$cssReduction}% reduction)\n";
echo "JS: " . number_format($originalJsSize / 1024, 2) . " KB -> " . number_format($minifiedJsSize / 1024, 2) . " KB ({$jsReduction}% reduction)\n";
echo "Total: " . number_format($totalOriginalSize / 1024, 2) . " KB -> " . number_format($totalMinifiedSize / 1024, 2) . " KB ({$totalReduction}% reduction)\n";

echo "\nDeployment files:\n";
echo "- index.html\n";
echo "- styles.min.css\n";
echo "- script.min.js\n";
echo "- ai_views.json\n";

echo "\nCache information:\n";
echo "- Timestamp: $timestamp\n";
echo "- CSS Hash: " . substr(md5($cssContent), 0, 8) . "\n";
echo "- JS Hash: " . substr(md5($jsContent), 0, 8) . "\n";

echo "\nIf you encounter any issues with the minified files, you can restore from backups with:\n";
echo "cp styles.min.css.bak styles.min.css\n";
echo "cp script.min.js.bak script.min.js\n";

echo "\nDone! Upload these files to your server.\n";
