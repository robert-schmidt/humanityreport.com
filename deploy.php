<?php
/**
 * Deployment Script for HumanityReport.com
 *
 * This script handles:
 * 1. CSS/JS minification
 * 2. Updating file references with cache busting
 * 3. Basic deployment process
 *
 * Usage: php deploy.php
 */

// Add a special backup option
$backupOption = true;

if ($backupOption) {
    // Create backups of original files
    if (file_exists('styles.min.css')) {
        copy('styles.min.css', 'styles.min.css.bak');
    }
    if (file_exists('script.min.js')) {
        copy('script.min.js', 'script.min.js.bak');
    }
    echo "Created backups of minified files\n";
}

// Step 2: Update HTML with cache-busting hashes
$htmlFile = 'index.html';
$htmlContent = file_get_contents($htmlFile);

// Add timestamp to avoid browser cache of changed files
$timestamp = time();

// Update CSS link with hash
$htmlContent = preg_replace(
    '/<link rel="stylesheet" href="styles\.min\.css(\?[a-z0-9]*)?">/',
    '<link rel="stylesheet" href="styles.min.css?' . $timestamp . '">',
    $htmlContent
);

// Update JS link with hash
$htmlContent = preg_replace(
    '/<script src="script\.min\.js(\?[a-z0-9]*)?"><\/script>/',
    '<script src="script.min.js?' . $timestamp . '"></script>',
    $htmlContent
);

// Save updated HTML
file_put_contents($htmlFile, $htmlContent);
echo "Updated HTML with cache-busting timestamps\n";

// Generate hash for display purposes
$cssHash = file_exists('styles.min.css') ? substr(md5_file('styles.min.css'), 0, 8) : 'nohash';
$jsHash = file_exists('script.min.js') ? substr(md5_file('script.min.js'), 0, 8) : 'nohash';

// Step 3: Calculate file sizes and reduction
function formatSize($bytes) {
    if (!is_numeric($bytes) || $bytes < 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes > 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

$originalCssSize = file_exists('styles.css') ? filesize('styles.css') : 0;
$minifiedCssSize = file_exists('styles.min.css') ? filesize('styles.min.css') : 0;
$originalJsSize = file_exists('script.js') ? filesize('script.js') : 0;
$minifiedJsSize = file_exists('script.min.js') ? filesize('script.min.js') : 0;

$cssReduction = $originalCssSize > 0 ? round(($originalCssSize - $minifiedCssSize) / $originalCssSize * 100, 2) : 0;
$jsReduction = $originalJsSize > 0 ? round(($originalJsSize - $minifiedJsSize) / $originalJsSize * 100, 2) : 0;
$totalOriginal = $originalCssSize + $originalJsSize;
$totalMinified = $minifiedCssSize + $minifiedJsSize;
$totalReduction = $totalOriginal > 0 ? round(($totalOriginal - $totalMinified) / $totalOriginal * 100, 2) : 0;

echo "\nDeployment completed!\n";
echo "=== File Size Summary ===\n";
echo "CSS: " . formatSize($originalCssSize) . " -> " . formatSize($minifiedCssSize) . " ($cssReduction% reduction)\n";
echo "JS: " . formatSize($originalJsSize) . " -> " . formatSize($minifiedJsSize) . " ($jsReduction% reduction)\n";
echo "Total: " . formatSize($totalOriginal) . " -> " . formatSize($totalMinified) . " ($totalReduction% reduction)\n";

// List all deployment files
echo "\nDeployment files:\n";
echo "- index.html\n";
echo "- styles.min.css\n";
echo "- script.min.js\n";
echo "- ai_views.json\n";

echo "\nCache-busting hashes:\n";
echo "- CSS: $cssHash\n";
echo "- JS: $jsHash\n";

echo "\nIf you encounter any issues with the minified files, you can restore from backups with:\n";
echo "cp styles.min.css.bak styles.min.css\n";
echo "cp script.min.js.bak script.min.js\n";

echo "\nDone! Upload these files to your server.\n";
