#!/usr/bin/env php
<?php
/**
 * 🤖 Bot Launcher - Script Manager
 * 
 * @author GLITCH083
 * @version 2.0.0
 */

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// CONFIGURATION
// ============================================
define("BASE_DIR", __DIR__);
define("SCRIPTS_DIR", BASE_DIR . "/scripts");

// Load required files
require_once BASE_DIR . "/functions/function.php";
require_once BASE_DIR . "/functions/captcha.php";

// ============================================
// GITHUB CONFIGURATION
// ============================================
$tokenFile = BASE_DIR . "/github_token.txt";
if (file_exists($tokenFile)) {
    $githubToken = trim(file_get_contents($tokenFile));
} else {
    $githubToken = "";
}

define("GITHUB_TOKEN", $githubToken);
define("GITHUB_USERNAME", "GLITCH083");
define("GITHUB_REPO", "Buxads-scripts");
define("GITHUB_BRANCH", "main");
define("GITHUB_API_URL", "https://api.github.com/repos/" . GITHUB_USERNAME . "/" . GITHUB_REPO);
define("VERSION_FILE", "version.json");
define("TEMP_DIR", BASE_DIR . "/temp_update");
define("APP_HOST", "buxads-Bot");
define("api_endpoint", "http://37.60.224.60:7860/api");

date_default_timezone_set("Asia/Karachi");

if (!defined("anticaptcha_key")) {
    define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
}

// ============================================
// GITHUB API FUNCTIONS
// ============================================
function githubApiRequest($endpoint) {
    $url = GITHUB_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Buxads-Bot/2.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: token ' . GITHUB_TOKEN,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: Buxads-Bot/2.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() deprecated in PHP 8.5+ (handle auto-closes)
        if (\PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }
        unset($ch);
    
    if ($httpCode == 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

function githubDownloadFile($path) {
    // Try GitHub API first (works for private repos)
    $data = githubApiRequest("/contents/" . $path);
    
    if ($data && isset($data['content'])) {
        return base64_decode($data['content']);
    }
    
    // Fallback to raw URL
    $rawUrl = "https://raw.githubusercontent.com/" . GITHUB_USERNAME . "/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/" . $path;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $rawUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Buxads-Bot/2.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: token ' . GITHUB_TOKEN,
            'User-Agent: Buxads-Bot/2.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() deprecated in PHP 8.5+ (handle auto-closes)
        if (\PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }
        unset($ch);
    
    if ($httpCode == 200 && $response) {
        return $response;
    }
    
    return false;
}

/**
 * NEW: Fetch the full recursive file tree from GitHub.
 * Returns an array of relative paths (e.g. "scripts/faucet/auto.php").
 */
function githubGetTree() {
    $data = githubApiRequest("/git/trees/" . GITHUB_BRANCH . "?recursive=1");
    
    if ($data && isset($data['tree']) && is_array($data['tree'])) {
        $files = [];
        foreach ($data['tree'] as $item) {
            if (isset($item['type'], $item['path']) && $item['type'] === 'blob') {
                $files[] = $item['path'];
            }
        }
        return $files;
    }
    
    return [];
}

// ============================================
// VERSION FUNCTIONS
// ============================================
function getCurrentVersion() {
    $localFile = BASE_DIR . "/" . VERSION_FILE;
    
    if (file_exists($localFile)) {
        $content = file_get_contents($localFile);
        $data = json_decode($content, true);
        
        if ($data && isset($data['version'])) {
            return $data;
        }
    }
    
    return ['version' => '0.0.0', 'features' => []];
}

function fetchLatestVersion() {
    $content = githubDownloadFile(VERSION_FILE);
    
    if ($content) {
        $data = json_decode($content, true);
        if ($data && isset($data['version'])) {
            return $data;
        }
    }
    
    return null;
}

function isNewerVersion($current, $latest) {
    if (!$latest || !isset($latest['version']) || !isset($current['version'])) {
        return false;
    }
    
    return version_compare($latest['version'], $current['version'], '>');
}

// Cache update status for the session
$updateStatus = null;

function checkUpdateStatus() {
    global $updateStatus;
    if ($updateStatus !== null) {
        return $updateStatus;
    }
    
    $current = getCurrentVersion();
    $latest = fetchLatestVersion();
    $available = false;
    $version = null;
    $features = [];
    
    if ($latest && isNewerVersion($current, $latest)) {
        $available = true;
        $version = $latest['version'];
        $features = isset($latest['features']) ? $latest['features'] : [];
    }
    
    $updateStatus = [
        'available' => $available,
        'latest_version' => $version,
        'features' => $features,
        'current_version' => $current['version']
    ];
    
    return $updateStatus;
}

// ============================================
// UPDATE FUNCTIONS
// ============================================
function displayUpdateInfo($current, $latest) {
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  📦 UPDATE AVAILABLE!" . str_repeat(" ", 48) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Current Version: " . YELLOW . $current['version'] . str_repeat(" ", 50 - strlen($current['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Latest Version:  " . GREEN . $latest['version'] . str_repeat(" ", 50 - strlen($latest['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  📝 What's New:" . str_repeat(" ", 56) . CYAN . "║\n" . RESET;
    
    $news = [];
    if (isset($latest['whats_new']) && is_array($latest['whats_new'])) {
        $news = $latest['whats_new'];
    } elseif (isset($latest['features']) && is_array($latest['features'])) {
        $news = $latest['features'];
    }
    foreach ($news as $feature) {
        $feature = substr((string)$feature, 0, 60);
        echo CYAN . "║  " . WHITE . "• " . $feature . str_repeat(" ", max(0, 61 - strlen($feature))) . CYAN . "║\n" . RESET;
    }
    
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

/**
 * NEW: Recursively delete a directory.
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    
    @rmdir($dir);
}

/**
 * NEW: Remove empty subdirectories inside $dir.
 */
function removeEmptyDirs($dir) {
    if (!is_dir($dir)) return;
    
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($items as $item) {
        if ($item->isDir()) {
            $path = $item->getPathname();
            if (count(scandir($path)) <= 2) {
                @rmdir($path);
            }
        }
    }
}

/**
 * NEW: Delete local files that no longer exist on GitHub.
 * Scans: BASE_DIR/functions/*, BASE_DIR/scripts/**\/*, and root managed files.
 * Returns number of deleted files.
 */
function cleanupOrphanedFiles($remoteFiles) {
    // Quick lookup set (normalized with forward slashes)
    $remoteSet = array_flip($remoteFiles);
    
    $deleted = 0;
    
    // ---- 1. Root managed files ----
    $rootManaged = ['bot.php', VERSION_FILE];
    foreach ($rootManaged as $file) {
        if (!isset($remoteSet[$file]) && file_exists(BASE_DIR . "/" . $file)) {
            @unlink(BASE_DIR . "/" . $file);
            echo "  🗑️  Deleted: " . $file . "\n";
            $deleted++;
        }
    }
    
    // ---- 2. Recursive scan of managed directories ----
    $scanDirs = ['functions', 'scripts'];
    foreach ($scanDirs as $dir) {
        $fullDir = BASE_DIR . "/" . $dir;
        if (!is_dir($fullDir)) continue;
        
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($items as $item) {
            if (!$item->isFile()) continue;
            
            // Build relative path (with forward slashes)
            $rel = substr($item->getPathname(), strlen(BASE_DIR) + 1);
            $rel = str_replace('\\', '/', $rel);
            
            // Skip backup files and dot files we don't manage
            if (preg_match('/\.(bak|tmp|log)$/i', $rel)) continue;
            
            if (!isset($remoteSet[$rel])) {
                @unlink($item->getPathname());
                echo "  🗑️  Deleted: " . $rel . "\n";
                $deleted++;
            }
        }
        
        // Clean up empty folders
        removeEmptyDirs($fullDir);
    }
    
    return $deleted;
}

/**
 * UPDATED: Download files preserving directory structure,
 * then apply them and clean up orphaned files.
 */
function applyUpdate($latest) {
    echo "\n" . YELLOW . "📥 Fetching repository file list..." . RESET . "\n";
    
    // 1. Get full recursive tree from GitHub
    $remoteFiles = githubGetTree();
    
    if (empty($remoteFiles)) {
        echo RED . "✗ Could not fetch repository tree from GitHub." . RESET . "\n";
        echo YELLOW . "  Check internet connection / token permissions." . RESET . "\n";
        return false;
    }
    
    // 2. Filter to only managed file types
    $managedExt = ['php', 'json'];
    $remoteFiles = array_values(array_filter($remoteFiles, function ($path) use ($managedExt) {
        // Skip hidden folders (.git, .github, etc.)
        if (strpos($path, '.') === 0) return false;
        if (strpos($path, '.github/') === 0) return false;
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $managedExt);
    }));
    
    if (empty($remoteFiles)) {
        echo RED . "✗ No managed files found in repo." . RESET . "\n";
        return false;
    }
    
    echo GREY . "  Found " . count($remoteFiles) . " file(s) on GitHub" . RESET . "\n\n";
    
    // 3. Prepare temp dir
    if (is_dir(TEMP_DIR)) deleteDirectory(TEMP_DIR);
    mkdir(TEMP_DIR, 0777, true);
    
    // 4. Download every file (preserving folder structure)
    $downloaded = 0;
    $total = count($remoteFiles);
    
    foreach ($remoteFiles as $file) {
        echo "  Downloading: " . $file . " ... ";
        
        $content = githubDownloadFile($file);
        if ($content !== false) {
            $tempFile = TEMP_DIR . "/" . $file;
            $dir = dirname($tempFile);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            file_put_contents($tempFile, $content);
            $downloaded++;
            echo GREEN . "✓" . RESET . "\n";
        } else {
            echo RED . "✗" . RESET . "\n";
        }
    }
    
    echo "\n" . GREEN . "✓ Downloaded " . $downloaded . "/" . $total . " files" . RESET . "\n";
    
    if ($downloaded == 0) {
        echo RED . "✗ No files downloaded. Update failed." . RESET . "\n";
        deleteDirectory(TEMP_DIR);
        return false;
    }
    
    // 5. Backup current bot.php
    if (file_exists(BASE_DIR . "/bot.php")) {
        copy(BASE_DIR . "/bot.php", BASE_DIR . "/bot.php.bak");
        echo "  ✓ Created backup: bot.php.bak\n";
    }
    
    // 6. Apply files
    echo "\n" . YELLOW . "🔄 Applying update..." . RESET . "\n";
    
    $applied = 0;
    foreach ($remoteFiles as $file) {
        $tempFile = TEMP_DIR . "/" . $file;
        if (!file_exists($tempFile)) continue;
        
        $dest = BASE_DIR . "/" . $file;
        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        if (rename($tempFile, $dest)) {
            echo "  ✓ Updated: " . $file . "\n";
            $applied++;
        }
    }
    
    // 7. Delete orphaned files
    echo "\n" . YELLOW . "🗑️  Removing obsolete files..." . RESET . "\n";
    $deleted = cleanupOrphanedFiles($remoteFiles);
    
    if ($deleted > 0) {
        echo GREEN . "  ✓ Removed " . $deleted . " obsolete file(s)" . RESET . "\n";
    } else {
        echo GREY . "  ✓ No obsolete files found" . RESET . "\n";
    }
    
    // 8. Clean up temp directory
    deleteDirectory(TEMP_DIR);
    
    echo "\n" . GREEN . "✅ Update applied successfully!" . RESET . "\n";
    echo GREY . "   Applied: " . $applied . " file(s), Deleted: " . $deleted . " file(s)\n" . RESET;
    return true;
}

function checkForUpdates() {
    echo "\n" . CYAN . "🔍 Checking for updates..." . RESET . "\n";
    
    $current = getCurrentVersion();
    echo GREY . "  Current version: " . $current['version'] . RESET . "\n";
    
    $latest = fetchLatestVersion();
    
    if (!$latest) {
        echo RED . "❌ Could not fetch version information from GitHub.\n" . RESET;
        echo YELLOW . "  Please check your internet connection and token.\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        fgets(STDIN);
        return false;
    }
    
    echo GREY . "  Latest version: " . $latest['version'] . RESET . "\n";
    
    if (isNewerVersion($current, $latest)) {
        displayUpdateInfo($current, $latest);
        
        echo WHITE . "Do you want to download and install the update? (y/n): " . RESET;
        $choice = strtolower(trim(fgets(STDIN)));
        
        if ($choice === 'y' || $choice === 'yes') {
            if (applyUpdate($latest)) {
                echo "\n" . GREEN . "✅ Update complete! Please restart the bot.\n" . RESET;
            }
        } else {
            echo YELLOW . "Update skipped.\n" . RESET;
        }
    } else {
        echo GREEN . "✅ You are using the latest version (" . $current['version'] . ")\n" . RESET;
    }
    
    echo WHITE . "Press Enter to continue..." . RESET;
    fgets(STDIN);
    return true;
}

// ============================================
// SCRIPT MANAGEMENT FUNCTIONS
// ============================================
function getScripts() {
    $scripts = [];
    
    if (!is_dir(SCRIPTS_DIR)) {
        mkdir(SCRIPTS_DIR, 0777, true);
        return $scripts;
    }
    
    // Get all subdirectories (categories)
    $dirs = glob(SCRIPTS_DIR . "/*", GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $category = basename($dir);
        $files = glob($dir . "/*.php");
        foreach ($files as $file) {
            $basename = basename($file);
            $excluded = ['function.php', 'captcha.php', 'bot.php', 'config.php'];
            if (in_array($basename, $excluded)) continue;
            
            $scripts[] = [
                'file'      => $file,
                'basename'  => $basename,
                'description' => getScriptDescription($basename),
                'version'   => getScriptVersion($file),
                'color'     => getScriptColor($basename),
                'category'  => $category
            ];
        }
    }
    
    usort($scripts, function($a, $b) {
        return strcasecmp($a['basename'], $b['basename']);
    });
    
    return $scripts;
}

function getScriptDescription($filename) {
    $descriptions = [
        'adcoins.cc.php' => 'AdCoins.cc Faucet Bot',
        'claimcrypto.in.php' => 'ClaimCrypto.in Faucet Bot',
        'cryptoearns.com.php' => 'CryptoEarns.com Faucet Bot',
        'gamefaucet.fun.php' => 'GameFaucet.fun Faucet Bot',
        'spaceshooter.net.php' => 'SpaceShooter.net Faucet Bot',
        'faucet.php' => 'Faucet Claim Bot',
        'shortlink.php' => 'Shortlink Claim Bot',
        'auto.php' => 'Auto Claim Bot',
        'claim.php' => 'Claim Bot',
        'mail.php' => 'Mail Bot'
    ];
    
    foreach ($descriptions as $pattern => $desc) {
        if (stripos($filename, str_replace('.php', '', $pattern)) !== false) {
            return $desc;
        }
    }
    
    $name = str_replace(['.php', '_', '-'], ' ', $filename);
    return ucwords(trim($name));
}

function getScriptVersion($filePath) {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        if (preg_match('/@version\s+([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/v(?:ersion)?\s*([0-9.]+)/i', $content, $matches)) {
            return 'v' . $matches[1];
        }
    }
    
    return 'v1.0';
}

function getScriptColor($name) {
    if (stripos($name, 'faucet') !== false) return GREEN;
    if (stripos($name, 'shortlink') !== false) return YELLOW;
    if (stripos($name, 'auto') !== false) return CYAN;
    if (stripos($name, 'claim') !== false) return PURPLE;
    if (stripos($name, 'bot') !== false) return BLUE;
    if (stripos($name, 'mail') !== false) return CYAN;
    if (stripos($name, 'adcoins') !== false) return GREEN;
    if (stripos($name, 'cryptoearns') !== false) return GREEN;
    if (stripos($name, 'spaceshooter') !== false) return GREEN;
    if (stripos($name, 'gamefaucet') !== false) return GREEN;
    if (stripos($name, 'claimcrypto') !== false) return GREEN;
    return WHITE;
}

// ============================================
// DISPLAY FUNCTIONS
// ============================================
function displayMainMenu() {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = function_exists('get_balance') ? get_balance() : 'N/A';
    $update = checkUpdateStatus();
    
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  🤖  BOT LAUNCHER v" . $version['version'] . str_repeat(" ", 47 - strlen($version['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Repo: " . GITHUB_USERNAME . str_repeat(" ", 57 - strlen(GITHUB_USERNAME)) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Balance: " . GREEN . $balance . WHITE . str_repeat(" ", 52 - strlen($balance)) . CYAN . "║\n" . RESET;
    
    if ($update['available']) {
        echo CYAN . "║" . YELLOW . "  ⚠️  Update available: v" . $update['latest_version'] . 
             " (current: v" . $update['current_version'] . ")" . 
             str_repeat(" ", 39 - strlen($update['latest_version']) - strlen($update['current_version'])) . CYAN . "║\n" . RESET;
    }
    
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  [1] Run Scripts" . str_repeat(" ", 63) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  [2] Check for Updates" . str_repeat(" ", 58) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  [3] Exit" . str_repeat(" ", 70) . CYAN . "║\n" . RESET;
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

function getCategories() {
    $dirs = glob(SCRIPTS_DIR . "/*", GLOB_ONLYDIR);
    $categories = [];
    foreach ($dirs as $dir) {
        $categories[] = basename($dir);
    }
    sort($categories);
    return $categories;
}

function displayCategoryMenu($categories) {
    clearScreen();
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  📂 SCRIPT CATEGORIES" . str_repeat(" ", 53) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  [0] All scripts" . str_repeat(" ", 63) . CYAN . "║\n" . RESET;
    $num = 1;
    foreach ($categories as $cat) {
        $display = ucwords(str_replace(['-', '_'], ' ', $cat));
        $line = "  [" . $num . "] " . $display . str_repeat(" ", 65 - strlen($display) - strlen($num));
        echo CYAN . "║" . WHITE . $line . CYAN . "║\n" . RESET;
        $num++;
    }
    echo CYAN . "║" . WHITE . "  [" . $num . "] Back" . str_repeat(" ", 68) . CYAN . "║\n" . RESET;
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

function displayScriptsList($scripts) {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = function_exists('get_balance') ? get_balance() : 'N/A';
    
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  🤖  BOT LAUNCHER v" . $version['version'] . str_repeat(" ", 47 - strlen($version['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Repo: " . GITHUB_USERNAME . str_repeat(" ", 57 - strlen(GITHUB_USERNAME)) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Balance: " . GREEN . $balance . WHITE . str_repeat(" ", 52 - strlen($balance)) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    
    if (empty($scripts)) {
        echo CYAN . "║" . YELLOW . "  ❌ No scripts found in this category." . str_repeat(" ", 39) . CYAN . "║\n" . RESET;
    } else {
        $i = 1;
        foreach ($scripts as $script) {
            $num = str_pad($i, 2, " ", STR_PAD_LEFT);
            $name = substr($script['basename'], 0, 25);
            $name = str_pad($name, 25);
            $desc = substr($script['description'], 0, 30);
            $desc = str_pad($desc, 30);
            $version = str_pad($script['version'], 8);
            
            $color = $script['color'];
            
            echo CYAN . "║  " . $color . $num . ". " . $color . $name . RESET . " " . DIM . $desc . RESET . " " . GREY . $version . RESET . "  ║\n" . RESET;
            $i++;
        }
    }
    
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  [0] Back to Categories" . str_repeat(" ", 55) . CYAN . "║\n" . RESET;
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

function runScript($script) {
    echo "
";
    echo GREEN . "▶  Running: " . WHITE . $script['basename'] . "
" . RESET;
    echo CYAN . str_repeat("═", 70) . "
" . RESET;
    echo "
";

    $file = $script['file'];
    if (!file_exists($file)) {
        echo RED . "✗ Script file not found: " . $file . "
" . RESET;
        return;
    }

    // Run in SEPARATE php process (include kills whole bot on any fatal error)
    // Windows-safe + show PHP errors (fixes silent exit 255)
    $php = (defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : 'php';
    $cwd = getcwd();
    if (defined('BASE_DIR') && is_dir(BASE_DIR)) {
        chdir(BASE_DIR);
    }
    $exitCode = 0;
    $cmdArr = [$php, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL', $file];
    if (function_exists('proc_open')) {
        $desc = [0 => STDIN, 1 => STDOUT, 2 => STDOUT];
        $proc = @proc_open($cmdArr, $desc, $pipes, (defined('BASE_DIR') ? BASE_DIR : $cwd));
        if (is_resource($proc)) {
            $exitCode = proc_close($proc);
        } else {
            passthru(escapeshellarg($php) . ' -d display_errors=1 ' . escapeshellarg($file), $exitCode);
        }
    } else {
        passthru(escapeshellarg($php) . ' -d display_errors=1 ' . escapeshellarg($file), $exitCode);
    }
    chdir($cwd);

    echo "\n";
    echo CYAN . str_repeat("═", 70) . "\n" . RESET;
    if ($exitCode === 0) {
        echo GREEN . "✓ Script completed!\n" . RESET;
    } else {
        echo YELLOW . "⚠ Script exited with code " . $exitCode . "\n" . RESET;
        echo YELLOW . "  Re-check PHP errors printed above. Do not use old folders.\n" . RESET;
    }
}


function getUserInput($prompt, $default = null) {
    echo WHITE . $prompt . RESET;
    if ($default !== null) {
        echo " [" . CYAN . $default . RESET . "]";
    }
    echo ": ";
    
    $input = trim(fgets(STDIN));
    return empty($input) ? $default : $input;
}

function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

// ============================================
// MAIN PROGRAM
// ============================================
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() {
        echo "\n" . YELLOW . "Interrupted by user. Exiting...\n" . RESET;
        exit(0);
    });
}

if (!is_dir(SCRIPTS_DIR)) {
    mkdir(SCRIPTS_DIR, 0777, true);
}

while (true) {
    $scripts = getScripts();
    displayMainMenu();
    
    $choice = getUserInput("Select option (1-3)", "1");
    
    switch ($choice) {
        case '3':
            echo GREEN . "Goodbye!\n" . RESET;
            exit(0);
            
        case '2':
            checkForUpdates();
            break;
            
        case '1':
            $categories = getCategories();
            
            while (true) {
                displayCategoryMenu($categories);
                $maxCat = count($categories);
                $catChoice = getUserInput("Choose category (0-" . ($maxCat) . ")", "0");
                
                if ($catChoice == ($maxCat + 1)) {
                    break 2;
                }
                
                if (!is_numeric($catChoice) || $catChoice < 0 || $catChoice > $maxCat) {
                    echo RED . "❌ Invalid choice!\n" . RESET;
                    echo WHITE . "Press Enter to continue..." . RESET;
                    fgets(STDIN);
                    continue;
                }
                
                if ($catChoice == 0) {
                    $filtered = $scripts;
                } else {
                    $selectedCategory = $categories[$catChoice - 1];
                    $filtered = array_filter($scripts, function($s) use ($selectedCategory) {
                        return $s['category'] === $selectedCategory;
                    });
                    $filtered = array_values($filtered);
                }
                
                while (true) {
                    displayScriptsList($filtered);
                    
                    if (empty($filtered)) {
                        echo YELLOW . "📁 No scripts in this category.\n" . RESET;
                        echo WHITE . "Press Enter to continue..." . RESET;
                        fgets(STDIN);
                        break;
                    }
                    
                    $scriptChoice = getUserInput("Select script (1-" . count($filtered) . " or 0 to go back)", "0");
                    
                    if ($scriptChoice === '0' || $scriptChoice === '') {
                        break;
                    }
                    
                    if (!is_numeric($scriptChoice) || $scriptChoice < 1 || $scriptChoice > count($filtered)) {
                        echo RED . "❌ Invalid selection!\n" . RESET;
                        echo WHITE . "Press Enter to continue..." . RESET;
                        fgets(STDIN);
                        continue;
                    }
                    
                    $selectedScript = $filtered[$scriptChoice - 1];
                    
                    echo "\n";
                    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
                    echo CYAN . "║" . WHITE . BOLD . "  📄 SCRIPT DETAILS" . str_repeat(" ", 50) . CYAN . "║\n" . RESET;
                    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
                    echo CYAN . "║" . WHITE . "  Name:        " . $selectedScript['color'] . $selectedScript['basename'] . RESET . str_repeat(" ", 27 - strlen($selectedScript['basename'])) . CYAN . "║\n" . RESET;
                    echo CYAN . "║" . WHITE . "  Description: " . substr($selectedScript['description'], 0, 27) . str_repeat(" ", 27 - strlen(substr($selectedScript['description'], 0, 27))) . CYAN . "║\n" . RESET;
                    echo CYAN . "║" . WHITE . "  Version:     " . $selectedScript['version'] . str_repeat(" ", 27 - strlen($selectedScript['version'])) . CYAN . "║\n" . RESET;
                    echo CYAN . "║" . WHITE . "  Category:    " . ucwords(str_replace(['-', '_'], ' ', $selectedScript['category'])) . str_repeat(" ", 27 - strlen(ucwords(str_replace(['-', '_'], ' ', $selectedScript['category'])))) . CYAN . "║\n" . RESET;
                    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
                    echo "\n";
                    
                    $confirm = getUserInput("Run this script? (y/n)", "y");
                    if (strtolower($confirm) !== 'y') {
                        continue;
                    }
                    
                    runScript($selectedScript);
                    
                    echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                    fgets(STDIN);
                }
            }
            break;
            
        default:
            echo RED . "❌ Invalid option!\n" . RESET;
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
    }
}