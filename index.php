<?php
// XAMPP Project Manager
// A comprehensive project management tool for XAMPP development environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Dynamically get the username
$currentUser = getenv('USERNAME') ?: getenv('USER') ?: 'User';

// Settings
$reposPath = 'C:\\Users\\' . $currentUser . '\\source\\repos';
$htdocsPath = 'C:\\xampp\\htdocs';

// Check if Repos folder exists
$reposExists = file_exists($reposPath);

// Process AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'list_directory':
            $path = $_POST['path'] ?? $reposPath;
            echo json_encode(listDirectory($path));
            exit;

        case 'create_directory':
            $path = $_POST['path'];
            $name = $_POST['name'];
            $result = createDirectory($path, $name);
            echo json_encode($result);
            exit;

        case 'delete_item':
            $path = $_POST['path'];
            $result = deleteItem($path);
            echo json_encode($result);
            exit;

        case 'create_symlink':
            $source = $_POST['source'];
            $name = $_POST['name'];
            $result = createSymlink($source, $name);
            echo json_encode($result);
            exit;

        case 'remove_symlink':
            $name = $_POST['name'];
            $result = removeSymlink($name);
            echo json_encode($result);
            exit;

        case 'get_symlinks':
            echo json_encode(getSymlinks());
            exit;

        case 'check_symlink':
            $name = $_POST['name'];
            $result = checkSymlink($name);
            echo json_encode($result);
            exit;

        case 'get_htdocs_folders':
            echo json_encode(getHtdocsFolders());
            exit;

        case 'create_file':
            $path = $_POST['path'];
            $name = $_POST['name'];
            $content = $_POST['content'] ?? '';
            $result = createFile($path, $name, $content);
            echo json_encode($result);
            exit;

        case 'create_symlink_batch':
            $source = $_POST['source'];
            $name = $_POST['name'];
            $result = createSymlinkBatch($source, $name);
            echo json_encode($result);
            exit;

        case 'create_remove_batch':
            $name = $_POST['name'];
            $result = createRemoveBatch($name);
            echo json_encode($result);
            exit;

        case 'create_repos_folder':
            $result = createReposFolder();
            echo json_encode($result);
            exit;

        case 'check_repos_exists':
            echo json_encode(['exists' => $reposExists, 'path' => $reposPath]);
            exit;

        case 'open_explorer':
            $path = $_POST['path'];
            $result = openExplorer($path);
            echo json_encode($result);
            exit;

        case 'read_file':
            $path = $_POST['path'];
            $result = readFileContent($path);
            echo json_encode($result);
            exit;

        case 'save_file':
            $path = $_POST['path'];
            $content = $_POST['content'];
            $result = saveFileContent($path, $content);
            echo json_encode($result);
            exit;

        case 'rename_file':
            $oldPath = $_POST['old_path'];
            $newPath = $_POST['new_path'];
            $result = renameFile($oldPath, $newPath);
            echo json_encode($result);
            exit;
    }
}

// Functions
function listDirectory($path) {
    $items = [];

    // Normalize Path
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
    $path = rtrim($path, DIRECTORY_SEPARATOR);

    // Check if directory exists
    if (!file_exists($path)) {
        return ['error' => 'Directory not found: ' . $path];
    }

    if (!is_dir($path)) {
        return ['error' => 'This is not a directory: ' . $path];
    }

    // Check if directory is readable
    if (!is_readable($path)) {
        return ['error' => 'Directory is not readable (permission issue): ' . $path];
    }

    try {
        $files = @scandir($path);

        if ($files === false) {
            return ['error' => 'Could not read directory contents: ' . $path];
        }

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;

            $fullPath = $path . DIRECTORY_SEPARATOR . $file;

            // Safely get file information
            $item = [
                'name' => $file,
                'path' => $fullPath,
                'is_dir' => @is_dir($fullPath),
                'is_php' => strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'php',
                'size' => @is_file($fullPath) ? @filesize($fullPath) : null,
                'modified' => @filemtime($fullPath) ? date("Y-m-d H:i:s", @filemtime($fullPath)) : 'N/A'
            ];

            // Check if it's a PHP project
            if ($item['is_dir']) {
                $indexPath = $fullPath . DIRECTORY_SEPARATOR . 'index.php';
                $item['has_index'] = @file_exists($indexPath);
            }

            $items[] = $item;
        }

        // Sort: directories first, then files
        usort($items, function($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

    } catch (Exception $e) {
        return ['error' => 'Error: ' . $e->getMessage()];
    }

    return [
        'items' => $items,
        'current_path' => $path,
        'item_count' => count($items)
    ];
}

function createDirectory($basePath, $name) {
    $newPath = $basePath . DIRECTORY_SEPARATOR . $name;

    if (file_exists($newPath)) {
        return ['success' => false, 'message' => 'A directory with this name already exists'];
    }

    if (mkdir($newPath, 0777, true)) {
        return ['success' => true, 'message' => 'Directory created successfully'];
    }

    return ['success' => false, 'message' => 'Could not create directory'];
}

function deleteItem($path) {
    if (is_dir($path)) {
        // Recursively delete directory
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ($files as $file) {
            deleteItem($path . DIRECTORY_SEPARATOR . $file);
        }
        if (rmdir($path)) {
            return ['success' => true, 'message' => 'Directory deleted'];
        }
    } else {
        if (unlink($path)) {
            return ['success' => true, 'message' => 'File deleted'];
        }
    }

    return ['success' => false, 'message' => 'Could not delete item'];
}

function createSymlink($source, $name) {
    global $htdocsPath;
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // Normalize Paths
    $source = str_replace('/', '\\', $source);
    $target = str_replace('/', '\\', $target);

    // Clear cache first
    clearstatcache(true, $target);

    // More specific check - only block if it truly exists
    if ((file_exists($target) && !is_link($target)) || is_link($target) || (is_dir($target) && is_junction($target))) {
        // Check again, maybe it's a cache issue
        clearstatcache(true, $target);
        if (file_exists($target) || is_link($target)) {
            return ['success' => false, 'message' => 'A link with this name already exists'];
        }
    }

    // Create and run batch file
    $batchFile = createSymlinkBatchQuick($source, $name);

    if ($batchFile['success']) {
        // Run batch file directly (will request UAC)
        $runCommand = 'powershell -Command "Start-Process \'' . $batchFile['batch_path'] . '\' -Verb RunAs -WindowStyle Hidden"';
        pclose(popen($runCommand, 'r'));

        // Wait for 3 seconds and check
        sleep(3);

        // Clear cache
        clearstatcache(true, $target);

        if (is_dir($target) || is_link($target)) {
            return ['success' => true, 'message' => 'Symlink created successfully!', 'auto_executed' => true];
        }

        // If unsuccessful, return batch path
        return [
            'success' => false,
            'batch_created' => true,
            'batch_path' => $batchFile['batch_path'],
            'message' => 'Could not create automatically. Batch file is ready, you can run it manually.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Could not create symlink. Activate Windows Developer Mode or run XAMPP as Administrator.'
    ];
}

function removeSymlink($name) {
    global $htdocsPath;
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // First try simple rmdir
    if (is_dir($target) || is_link($target)) {
        $command = 'rmdir "' . $target . '" 2>&1';
        exec($command, $output, $return);

        if ($return === 0 || !file_exists($target)) {
            // Wait a bit for Windows to fully remove
            usleep(500000); // Wait 0.5 seconds
            clearstatcache(true, $target); // Clear PHP file cache
            return ['success' => true, 'message' => 'Symlink removed'];
        }
    }

    // If unsuccessful, try with administrator privileges
    $batchFile = createRemoveSymlinkBatchQuick($name);
    if ($batchFile['success']) {
        // Run batch file directly (will request UAC)
        $runCommand = 'powershell -Command "Start-Process \'' . $batchFile['batch_path'] . '\' -Verb RunAs -WindowStyle Hidden"';
        pclose(popen($runCommand, 'r'));

        // Wait for 3 seconds and check
        sleep(3);

        // Clear cache and check
        clearstatcache(true, $target);

        if (!file_exists($target) && !is_dir($target) && !is_link($target)) {
            // Wait and clear cache one more time
            usleep(500000);
            clearstatcache(true, $target);
            return ['success' => true, 'message' => 'Symlink removed successfully!', 'auto_executed' => true];
        }

        // If unsuccessful, return batch path
        return [
            'success' => false,
            'batch_created' => true,
            'batch_path' => $batchFile['batch_path'],
            'message' => 'Could not remove automatically. Batch file is ready, you can run it manually.'
        ];
    }

    return ['success' => false, 'message' => 'Could not remove symlink'];
}

function getSymlinks() {
    global $htdocsPath;
    $symlinks = [];

    $files = scandir($htdocsPath);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;

        $fullPath = $htdocsPath . DIRECTORY_SEPARATOR . $file;

        // Check for Symlink or Junction
        if (is_link($fullPath) || (is_dir($fullPath) && is_junction($fullPath))) {
            // First try readlink
            $target = @readlink($fullPath);

            // If readlink fails, try with fsutil
            if (!$target) {
                $info = shell_exec('fsutil reparsepoint query "' . $fullPath . '" 2>NUL');
                if (preg_match('/Print Name:\s+(.+)/', $info, $matches)) {
                    $target = trim($matches[1]);
                }
            }

            if (!$target) {
                $target = 'N/A';
            }

            $symlinks[] = [
                'name' => $file,
                'target' => $target,
                'url' => "http://localhost/$file"
            ];
        }
    }

    return $symlinks;
}

function is_junction($path) {
    // Windows junction check
    if (PHP_OS_FAMILY === 'Windows') {
        // First check if path exists
        if (!file_exists($path)) {
            return false;
        }

        // Check for reparse point with fsutil
        $attr = shell_exec('fsutil reparsepoint query "' . $path . '" 2>NUL');

        // If fsutil output is empty, it's not a junction
        if (empty($attr)) {
            return false;
        }

        // If it contains "Reparse Tag Value" or "Symbolic Link" or "Mount Point" it is a junction/symlink
        return (strpos($attr, 'Reparse Tag Value') !== false) ||
               (strpos($attr, 'Symbolic Link') !== false) ||
               (strpos($attr, 'Mount Point') !== false);
    }
    return false;
}

// Check for symlink existence
function checkSymlink($name) {
    global $htdocsPath;
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // First check if file/directory exists
    if (!file_exists($target)) {
        return [
            'exists' => false,
            'path' => $target,
            'message' => 'Symlink/directory not found'
        ];
    }

    // Check if it's a real symlink/junction
    $isSymlink = is_link($target);
    $isJunction = is_junction($target);

    // If it's a normal directory (not a symlink), do not count it
    if (is_dir($target) && !$isSymlink && !$isJunction) {
        // Normal folder exists but not a symlink
        return [
            'exists' => false,
            'path' => $target,
            'is_normal_dir' => true,
            'message' => 'Normal folder exists but not a symlink'
        ];
    }

    // If symlink or junction exists
    if ($isSymlink || $isJunction) {
        return [
            'exists' => true,
            'path' => $target,
            'is_symlink' => true,
            'message' => 'Symlink found and ready to use'
        ];
    }

    return [
        'exists' => false,
        'path' => $target,
        'message' => 'Symlink not found'
    ];
}

// Create quick symlink batch file (for auto-execution)
function createSymlinkBatchQuick($source, $name) {
    global $htdocsPath;

    // Temporary file name
    $tempBatch = $htdocsPath . DIRECTORY_SEPARATOR . 'symlink_' . $name . '_' . time() . '.bat';
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // Batch file content (auto-run, silent mode)
    $batchContent = '@echo off
cd /d "' . $htdocsPath . '"
mklink /D "' . $name . '" "' . $source . '" >nul 2>&1
if %errorlevel% equ 0 (
    echo SUCCESS
) else (
    echo FAILED
)
timeout /t 1 /nobreak >nul
del "%~f0"
exit';

    // Create file
    if (file_put_contents($tempBatch, $batchContent)) {
        return [
            'success' => true,
            'batch_path' => $tempBatch
        ];
    }

    return ['success' => false];
}

// Create temporary batch file for symlink creation (for manual use)
function createSymlinkBatch($source, $name) {
    global $htdocsPath;

    // Temporary file name
    $tempBatch = $htdocsPath . DIRECTORY_SEPARATOR . 'temp_symlink_' . time() . '.bat';
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // Batch file content
    $batchContent = '@echo off
cls
echo =====================================
echo    SYMLINK CREATION PROCESS
echo =====================================
echo.

:: Administrator privilege check
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [!] Requesting Administrator privileges...
    echo.
    powershell -Command "Start-Process \'%~f0\' -Verb RunAs"
    exit /b
)

echo Project: ' . $name . '
echo Target: ' . $target . '
echo Source: ' . $source . '
echo.
echo Creating symlink...
echo.

mklink /D "' . $target . '" "' . $source . '"

if %errorLevel% equ 0 (
    echo.
    echo [SUCCESS] Symlink created!
    echo.
    echo You can access your project at:
    echo http://localhost/' . $name . '
) else (
    echo.
    echo [ERROR] Could not create symlink!
    echo.
    echo Possible reasons:
    echo - Target folder already exists
    echo - Source folder not found
)

echo.
echo =====================================
echo Press any key to close...
pause > nul

:: Self-delete
del "%~f0"
exit';

    // Create file
    if (file_put_contents($tempBatch, $batchContent)) {
        return [
            'success' => true,
            'batch_path' => $tempBatch,
            'command' => '"' . $tempBatch . '"',
            'message' => 'Batch file created'
        ];
    }

    return ['success' => false, 'message' => 'Could not create batch file'];
}

// Create quick symlink removal batch file (for auto-execution)
function createRemoveSymlinkBatchQuick($name) {
    global $htdocsPath;

    // Temporary file name
    $tempBatch = $htdocsPath . DIRECTORY_SEPARATOR . 'remove_' . $name . '_' . time() . '.bat';
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // Batch file content (auto-run, silent mode)
    $batchContent = '@echo off
cd /d "' . $htdocsPath . '"
rmdir "' . $name . '" >nul 2>&1
if %errorlevel% equ 0 (
    echo SUCCESS
) else (
    echo FAILED
)
timeout /t 1 /nobreak >nul
del "%~f0"
exit';

    // Create file
    if (file_put_contents($tempBatch, $batchContent)) {
        return [
            'success' => true,
            'batch_path' => $tempBatch
        ];
    }

    return ['success' => false];
}

// Create temporary batch file for symlink removal (for manual use)
function createRemoveBatch($name) {
    global $htdocsPath;

    // Temporary file name
    $tempBatch = $htdocsPath . DIRECTORY_SEPARATOR . 'temp_remove_' . time() . '.bat';
    $target = $htdocsPath . DIRECTORY_SEPARATOR . $name;

    // Batch file content
    $batchContent = '@echo off
cls
echo =====================================
echo    SYMLINK REMOVAL PROCESS
echo =====================================
echo.

:: Administrator privilege check
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [!] Requesting Administrator privileges...
    echo.
    powershell -Command "Start-Process \'%~f0\' -Verb RunAs"
    exit /b
)

echo Removing: ' . $name . '
echo Location: ' . $target . '
echo.

:: First check if it\'s a junction/symlink
fsutil reparsepoint query "' . $target . '" >nul 2>&1
if %errorLevel% equ 0 (
    echo [+] Symlink/Junction detected, removing...
    rmdir "' . $target . '" 2>&1
) else (
    :: Could be a normal folder, try with rmdir
    echo [*] Removing folder...
    rmdir /S /Q "' . $target . '" 2>&1
)

:: Check again
if not exist "' . $target . '" (
    echo.
    echo [SUCCESS] Symlink/Folder removed!
) else (
    echo.
    echo [ERROR] Could not remove!
    echo Possible reasons:
    echo - Folder is in use
    echo - Permission issue
    echo - File system error
)

echo.
echo =====================================
echo Press any key to close...
pause > nul

:: Self-delete
del "%~f0"
exit';

    // Create file
    if (file_put_contents($tempBatch, $batchContent)) {
        return [
            'success' => true,
            'batch_path' => $tempBatch,
            'command' => '"' . $tempBatch . '"',
            'message' => 'Batch file created'
        ];
    }

    return ['success' => false, 'message' => 'Could not create batch file'];
}

// Create new file
function createFile($basePath, $name, $content = '') {
    // Append .txt if no extension specified
    if (strpos($name, '.') === false) {
        $name .= '.txt';
    }

    $newFile = $basePath . DIRECTORY_SEPARATOR . $name;

    if (file_exists($newFile)) {
        return ['success' => false, 'message' => 'A file with this name already exists'];
    }

    // Create file
    if (file_put_contents($newFile, $content) !== false) {
        return ['success' => true, 'message' => 'File created successfully: ' . $name];
    }

    return ['success' => false, 'message' => 'Could not create file'];
}

// Create Repos folder
function createReposFolder() {
    global $reposPath;

    // If folder already exists
    if (file_exists($reposPath)) {
        return ['success' => true, 'message' => 'Repos folder already exists'];
    }

    // Try to create folder
    if (mkdir($reposPath, 0777, true)) {
        return ['success' => true, 'message' => 'Repos folder created successfully: ' . $reposPath];
    } else {
        return ['success' => false, 'message' => 'Could not create Repos folder. There might be a permission issue.'];
    }
}

// Read file content
function readFileContent($path) {
    // Check if file exists
    if (!file_exists($path)) {
        return ['success' => false, 'message' => 'File not found: ' . $path];
    }

    // Check if it's a file
    if (!is_file($path)) {
        return ['success' => false, 'message' => 'This is not a file: ' . $path];
    }

    // Check if file is readable
    if (!is_readable($path)) {
        return ['success' => false, 'message' => 'File is not readable (permission issue): ' . $path];
    }

    // Check file size (5MB limit)
    $fileSize = filesize($path);
    if ($fileSize > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File is too large (more than 5MB)'];
    }

    // Read file content
    $content = file_get_contents($path);
    if ($content === false) {
        return ['success' => false, 'message' => 'Could not read file content'];
    }

    // Get file info
    $info = pathinfo($path);
    $extension = isset($info['extension']) ? strtolower($info['extension']) : '';

    return [
        'success' => true,
        'content' => $content,
        'path' => $path,
        'name' => $info['basename'],
        'extension' => $extension,
        'size' => $fileSize,
        'modified' => date('Y-m-d H:i:s', filemtime($path))
    ];
}

// Save file content
function saveFileContent($path, $content) {
    // Check if file exists
    if (!file_exists($path)) {
        return ['success' => false, 'message' => 'File not found: ' . $path];
    }

    // Check if writable
    if (!is_writable($path)) {
        return ['success' => false, 'message' => 'File is not writable (permission issue): ' . $path];
    }

    // Backup
    $backupPath = $path . '.bak';
    if (!copy($path, $backupPath)) {
        // Continue even if backup fails
    }

    // Save file
    if (file_put_contents($path, $content) !== false) {
        return [
            'success' => true,
            'message' => 'File saved successfully',
            'backup' => $backupPath
        ];
    }

    return ['success' => false, 'message' => 'Could not save file'];
}

// Rename file
function renameFile($oldPath, $newPath) {
    // Check if old file exists
    if (!file_exists($oldPath)) {
        return ['success' => false, 'message' => 'Source file not found: ' . $oldPath];
    }

    // Check if new path already exists
    if (file_exists($newPath)) {
        return ['success' => false, 'message' => 'A file with this name already exists: ' . basename($newPath)];
    }

    // Get directory of the new path
    $newDir = dirname($newPath);
    if (!is_dir($newDir)) {
        return ['success' => false, 'message' => 'Target directory does not exist'];
    }

    // Check if we have write permission in the directory
    if (!is_writable(dirname($oldPath))) {
        return ['success' => false, 'message' => 'No write permission in the directory'];
    }

    // Try to rename the file
    if (rename($oldPath, $newPath)) {
        return [
            'success' => true,
            'message' => 'File renamed successfully',
            'new_path' => $newPath,
            'new_name' => basename($newPath)
        ];
    }

    return ['success' => false, 'message' => 'Could not rename file'];
}

// Open folder in Windows Explorer
function openExplorer($path) {
    // Check if path exists
    if (!file_exists($path)) {
        return ['success' => false, 'message' => 'Folder not found: ' . $path];
    }

    // explorer command on Windows
    if (PHP_OS_FAMILY === 'Windows') {
        // Convert path to Windows format
        $path = str_replace('/', '\\', $path);

        // Run Explorer command
        $command = 'explorer "' . $path . '"';

        // Run command in background
        pclose(popen('start /B ' . $command, 'r'));

        return ['success' => true, 'message' => 'Folder opened'];
    } else {
        return ['success' => false, 'message' => 'This feature only works on Windows'];
    }
}

// Get all folders in htdocs
function getHtdocsFolders() {
    global $htdocsPath;
    $folders = [];

    $files = scandir($htdocsPath);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;

        $fullPath = $htdocsPath . DIRECTORY_SEPARATOR . $file;

        if (is_dir($fullPath)) {
            $isSymlink = is_link($fullPath) || is_junction($fullPath);
            $target = '';

            // If it's a symlink, find its target
            if ($isSymlink) {
                $target = @readlink($fullPath);
                if (!$target) {
                    // If readlink fails, alternative method for junction
                    $info = shell_exec('fsutil reparsepoint query "' . $fullPath . '" 2>NUL');
                    if (preg_match('/Print Name:\s+(.+)/', $info, $matches)) {
                        $target = trim($matches[1]);
                    }
                }
            }

            // Check for index.php/index.html
            $hasIndex = file_exists($fullPath . DIRECTORY_SEPARATOR . 'index.php');
            $hasIndexHtml = file_exists($fullPath . DIRECTORY_SEPARATOR . 'index.html');

            $folders[] = [
                'name' => $file,
                'path' => $fullPath,
                'url' => "http://localhost/$file",
                'is_symlink' => $isSymlink,
                'target' => $target,
                'has_index' => $hasIndex || $hasIndexHtml,
                'type' => $isSymlink ? 'symlink' : 'folder',
                'size' => $isSymlink ? 'Symlink' : count(scandir($fullPath)) - 2 . ' items'
            ];
        }
    }

    // Sort: symlinks first, then normal folders
    usort($folders, function($a, $b) {
        if ($a['is_symlink'] && !$b['is_symlink']) return -1;
        if (!$a['is_symlink'] && $b['is_symlink']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });

    return $folders;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="XAMPP Project Manager - A powerful tool to organize, link, and manage your local development projects with symlink support, file management, and project organization.">
    <meta name="keywords" content="XAMPP, Project Manager, PHP Development, Local Development, Symlink Manager, File Manager, Web Development">
    <meta name="author" content="XAMPP Project Manager">
    <title>XAMPP Project Manager - Manage Your Local Development Projects</title>
    <!-- Favicon - You can add your custom favicon here -->
    <!-- <link rel="icon" type="image/x-icon" href="/favicon.ico"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #f60;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;

            /* Light mode colors */
            --bg-color: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --card-bg: #ffffff;
            --modal-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #ced4da;
            --code-bg: #f4f4f4;
            --scrollbar-bg: #f1f1f1;
            --scrollbar-thumb: #888;
            --shadow: rgba(0, 0, 0, 0.1);
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
            --header-gradient-start: #f60;
            --header-gradient-end: #ff8c42;
        }

        /* Dark mode colors */
        [data-theme="dark"] {
            --primary-color: #ff8c42;
            --secondary-color: #e0e0e0;
            --success-color: #198754;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;

            --bg-color: #1a1a1a;
            --bg-secondary: #2b2b2b;
            --bg-tertiary: #3a3a3a;
            --text-color: #e0e0e0;
            --text-muted: #9a9a9a;
            --border-color: #404040;
            --card-bg: #2b2b2b;
            --modal-bg: #2b2b2b;
            --input-bg: #1a1a1a;
            --input-border: #404040;
            --code-bg: #2b2b2b;
            --scrollbar-bg: #2b2b2b;
            --scrollbar-thumb: #555;
            --shadow: rgba(0, 0, 0, 0.5);
            --gradient-start: #2d3561;
            --gradient-end: #3a3d5c;
            --header-gradient-start: #d45a00;
            --header-gradient-end: #ff6b1a;
        }

        body {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background 0.3s ease;
        }

        .main-container {
            background: var(--bg-color);
            color: var(--text-color);
            border-radius: 15px;
            box-shadow: 0 10px 40px var(--shadow);
            margin: 30px auto;
            max-width: 1400px;
            overflow: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .header {
            background: linear-gradient(135deg, var(--header-gradient-start) 0%, var(--header-gradient-end) 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .header h1 {
            margin: 0;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            font-size: 2.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.95;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .nav-tabs .nav-link {
            color: var(--secondary-color);
            font-weight: 500;
            border: none;
            padding: 15px 30px;
            transition: all 0.3s;
            border-radius: 10px 10px 0 0;
        }

        .nav-tabs .nav-link:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 -2px 10px var(--shadow);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 -3px 15px rgba(255, 102, 0, 0.3);
            transform: translateY(-2px);
        }

        .service-card {
            border: none;
            border-radius: 10px;
            transition: all 0.3s;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .service-icon i {
            vertical-align: middle;
        }

        .file-item {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.2s;
            background: var(--card-bg);
        }

        .file-item:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            transform: translateX(5px);
            box-shadow: 0 2px 10px var(--shadow);
        }

        .file-item.directory {
            background: var(--bg-secondary);
            border-left: 4px solid var(--primary-color);
        }

        .file-item.parent-directory {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        .breadcrumb {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px var(--shadow);
        }

        .symlink-item {
            border: 2px solid var(--success-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: var(--bg-secondary);
        }

        .project-card {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: var(--card-bg);
        }

        .project-card:hover {
            box-shadow: 0 5px 20px var(--shadow);
            transform: translateY(-2px);
        }

        .btn-custom {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--shadow);
        }

        /* Card hover effects */
        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow);
        }

        /* List group item hover effects */
        .list-group-item {
            transition: all 0.2s ease;
        }

        .list-group-item:hover {
            transform: translateX(5px);
            background-color: var(--bg-secondary);
            box-shadow: 0 2px 10px var(--shadow);
        }

        /* Theme toggle button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px var(--shadow);
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px var(--shadow);
            background: var(--primary-color);
        }

        .theme-toggle:hover i {
            color: white;
        }

        .theme-toggle i {
            font-size: 1.5rem;
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading-content {
            text-align: center;
            color: white;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.2rem;
            font-weight: 500;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-online {
            background: var(--success-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        /* Tooltip fixes */
        .tooltip {
            z-index: 10000 !important;
            pointer-events: none;
        }

        .tooltip.show {
            opacity: 0.9 !important;
        }

        /* Tooltip arrow fix */
        .tooltip .tooltip-arrow {
            display: block;
        }

        /* Prevent tooltip from disappearing on button hover */
        [data-bs-toggle="tooltip"] {
            position: relative;
        }

        /* CodeMirror scrollbar fixes */
        .CodeMirror-hscrollbar {
            display: block !important;
            right: 15px !important;
        }

        /* CodeMirror general improvements */
        .CodeMirror {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }

        .CodeMirror-scroll {
            min-height: 500px;
        }

        /* CodeMirror line numbers style */
        .CodeMirror-gutters {
            border-right: 1px solid var(--border-color);
            background-color: var(--bg-secondary);
        }

        .CodeMirror-linenumber {
            color: var(--text-muted);
            padding: 0 5px;
        }

        /* Dark mode specific adjustments */
        [data-theme="dark"] .card {
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .card-header {
            background-color: var(--bg-tertiary);
            border-bottom-color: var(--border-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .modal-content {
            background-color: var(--modal-bg);
            color: var(--text-color);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-color);
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: var(--input-bg);
            border-color: var(--primary-color);
            color: var(--text-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 140, 66, 0.25);
        }

        [data-theme="dark"] .list-group-item {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .list-group-item:hover {
            background-color: var(--bg-tertiary);
        }

        [data-theme="dark"] .btn-light {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .btn-light:hover {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .btn-outline-secondary {
            color: var(--text-color);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .alert-info {
            background-color: var(--bg-tertiary);
            border-color: var(--info-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .alert-warning {
            background-color: var(--bg-tertiary);
            border-color: var(--warning-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .alert-success {
            background-color: var(--bg-tertiary);
            border-color: var(--success-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .alert-danger {
            background-color: var(--bg-tertiary);
            border-color: var(--danger-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .text-muted {
            color: var(--text-muted) !important;
        }

        [data-theme="dark"] .CodeMirror {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* Scrollbar styles for dark mode */
        [data-theme="dark"] ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        [data-theme="dark"] ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
        }

        [data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        /* Dark mode - Breadcrumb */
        [data-theme="dark"] .breadcrumb {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            color: var(--text-color);
        }

        [data-theme="dark"] .breadcrumb-item + .breadcrumb-item::before {
            color: var(--text-muted);
        }

        [data-theme="dark"] .breadcrumb-item.active {
            color: var(--text-muted);
        }

        /* Dark mode - Symlink item */
        [data-theme="dark"] .symlink-item {
            background: var(--bg-tertiary);
            border-color: var(--success-color);
            color: var(--text-color);
        }

        [data-theme="dark"] .symlink-item h6 {
            color: var(--text-color);
        }

        [data-theme="dark"] .symlink-item small {
            color: var(--text-muted);
        }

        /* Dark mode - Input group text */
        [data-theme="dark"] .input-group-text {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        /* Dark mode - File item */
        [data-theme="dark"] .file-item.directory {
            background: rgba(255, 193, 7, 0.1);
            border-color: var(--primary-color);
        }

        /* Just subtle improvements to Bootstrap buttons */
        .btn {
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Dark mode button adjustments */
        [data-theme="dark"] .btn {
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .btn:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
        }

    </style>
</head>
<body>
    <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle">
        <i class="bi bi-moon-fill" id="themeIcon"></i>
    </div>

    <div class="container-fluid">
        <div class="main-container">
            <div class="header">
                <h1><i class="bi bi-code-slash"></i> XAMPP Project Manager</h1>
                <p class="mb-0 mt-2">Organize, Link, and Manage Your Development Projects</p>
            </div>

            <ul class="nav nav-tabs px-3 pt-3" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="filemanager-tab" data-bs-toggle="tab" data-bs-target="#filemanager" type="button">
                        <i class="bi bi-folder-open"></i> File Manager
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="symlinks-tab" data-bs-toggle="tab" data-bs-target="#symlinks" type="button">
                        <i class="bi bi-link-45deg"></i> Symlink Management
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button">
                        <i class="bi bi-code-square"></i> PHP Applications
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4" id="mainTabContent">
                <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                    <h3 class="mb-4">Quick Access</h3>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card service-card">
                                <div class="card-body text-center">
                                    <div class="service-icon text-primary">
                                        <i class="bi bi-speedometer2"></i>
                                    </div>
                                    <h5 class="card-title">XAMPP Dashboard</h5>
                                    <p class="card-text">Access the original XAMPP dashboard</p>
                                    <a href="/dashboard/" target="_blank" class="btn btn-primary btn-custom" data-bs-toggle="tooltip" title="Open the XAMPP dashboard in a new tab">
                                        <i class="bi bi-box-arrow-up-right"></i> Open
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="card service-card">
                                <div class="card-body text-center">
                                    <div class="service-icon text-info">
                                        <i class="bi bi-info-circle-fill"></i>
                                    </div>
                                    <h5 class="card-title">PHP Info</h5>
                                    <p class="card-text">PHP configuration information</p>
                                    <a href="/dashboard/phpinfo.php" target="_blank" class="btn btn-info btn-custom" data-bs-toggle="tooltip" title="View PHP version and configuration details">
                                        <i class="bi bi-box-arrow-up-right"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="card service-card">
                                <div class="card-body text-center">
                                    <div class="service-icon text-success">
                                        <i class="bi bi-database-fill"></i>
                                    </div>
                                    <h5 class="card-title">phpMyAdmin</h5>
                                    <p class="card-text">MySQL database management</p>
                                    <a href="/phpmyadmin/" target="_blank" class="btn btn-success btn-custom" data-bs-toggle="tooltip" title="Manage and query MySQL databases">
                                        <i class="bi bi-box-arrow-up-right"></i> Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-folder-symlink"></i> htdocs Folders</h5>
                                </div>
                                <div class="card-body">
                                    <div id="htdocsFoldersList">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-pc-display"></i> System Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                            <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                            <p><strong>PHP Execution Limit:</strong> <?php echo ini_get('max_execution_time'); ?> seconds</p>
                                            <p><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="filemanager" role="tabpanel">
                    <h3 class="mb-4">File Manager</h3>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-folder2-open"></i></span>
                                <input type="text" class="form-control" id="customPathInput" placeholder="Ex: <?php echo $reposPath; ?>" value="<?php echo $reposPath; ?>">
                                <button class="btn btn-primary" onclick="loadCustomPath()">
                                    <i class="bi bi-arrow-right-circle"></i> Go
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDirectory('<?php echo str_replace('\\', '\\\\', $reposPath); ?>')" data-bs-toggle="tooltip" title="Source code repository folder">
                                    <i class="bi bi-folder-fill"></i> Repos
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDirectory('C:\\xampp\\htdocs')" data-bs-toggle="tooltip" title="XAMPP web root directory">
                                    <i class="bi bi-folder-fill"></i> htdocs
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDirectory('C:\\')" data-bs-toggle="tooltip" title="C drive root directory">
                                    <i class="bi bi-folder-fill"></i> C:\
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDirectory('C:\\Users\\<?php echo $currentUser; ?>')" data-bs-toggle="tooltip" title="User home folder">
                                    <i class="bi bi-folder-fill"></i> User
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDirectory('C:\\Users\\<?php echo $currentUser; ?>\\Desktop')" data-bs-toggle="tooltip" title="Desktop folder">
                                    <i class="bi bi-folder-fill"></i> Desktop
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb" id="breadcrumb">
                                    <li class="breadcrumb-item active"><?php echo $reposPath; ?></li>
                                </ol>
                            </nav>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-success btn-sm" onclick="createNewDirectory()" data-bs-toggle="tooltip" title="Create new folder">
                                <i class="bi bi-folder-plus"></i> New Folder
                            </button>
                            <button class="btn btn-info btn-sm" onclick="createNewFile()" data-bs-toggle="tooltip" title="Create new file">
                                <i class="bi bi-file-earmark-plus"></i> New File
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="refreshDirectory()" data-bs-toggle="tooltip" title="Refresh list">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div id="fileManagerError" style="display:none;" class="alert alert-warning mb-3"></div>

                    <div id="fileListContainer">
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="symlinks" role="tabpanel">
                    <h3 class="mb-4">Symlink Management</h3>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        You can link your projects in the <strong><?php echo $reposPath; ?></strong> folder as symbolic links (symlinks) to htdocs.
                        This way, your projects will be accessible via localhost.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5>Repos Projects</h5>
                            <div id="reposProjectList" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5>Active Symlinks</h5>
                            <div id="activeSymlinks" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="projects" role="tabpanel">
                    <h3 class="mb-4">PHP Applications</h3>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                Below are listed projects linked to htdocs and PHP applications in the repos folder.
                            </div>
                        </div>
                    </div>

                    <div class="row" id="projectsList">
                        <div class="col-md-12 text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <div class="modal fade" id="symlinkModal" tabindex="-1" aria-labelledby="symlinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="symlinkModalLabel">Create Symlink</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="symlinkSource" class="form-label">Source File/Folder:</label>
                        <input type="text" class="form-control" id="symlinkSource" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="symlinkName" class="form-label">Symlink Name (in htdocs):</label>
                        <input type="text" class="form-control" id="symlinkName" placeholder="Ex: my-project">
                        <small class="text-muted">You will access it via localhost with this name</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> After symlink is created, you will be able to access it at <strong>http://localhost/<span id="symlinkPreview">project-name</span></strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="showManualSymlinkCommand()">Create Symlink</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="bi bi-check-circle"></i> Operation Successful
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-center mb-3" id="successMessage">Operation completed successfully!</h5>
                    <div id="successDetails" class="alert alert-success"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="successLinkBtn" class="btn btn-primary" target="_blank" style="display:none;">
                        <i class="bi bi-box-arrow-up-right"></i> Linke Git
                    </a>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i class="bi bi-check"></i> Tamam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="bi bi-x-circle"></i> Error
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-center mb-3" id="errorMessage">Operation failed!</h5>
                    <div id="errorDetails" class="alert alert-danger"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmRemoveModal" tabindex="-1" aria-labelledby="confirmRemoveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmRemoveModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Symlink Removal Confirmation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-unlink text-danger" style="font-size: 4rem;"></i>
                    </div>

                    <h5 class="text-center mb-3">
                        Are you sure you want to remove the symlink
                        <span class="badge bg-warning" id="removeSymlinkName" style="color: var(--text-color);">symlink-name</span>?
                    </h5>

                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i>
                        <strong>Warning:</strong> This operation is irreversible. Once the symlink is removed, you cannot access the project via localhost. You can create it again if needed.
                    </div>

                    <div id="removeProcessStatus" style="display:none;">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Processing...</span>
                                </div>
                                <span id="removeStatusText">Processing, please wait...</span>
                            </div>
                        </div>
                    </div>

                    <div id="removeResultMessage" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelRemoveBtn">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmRemoveSymlink()" id="confirmRemoveBtn">
                        <i class="bi bi-trash"></i> Yes, Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editFileModal" tabindex="-1" aria-labelledby="editFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFileModalLabel">
                        <i class="bi bi-pencil-square"></i> File Editor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-12 col-lg-7">
                                <div class="mb-2">
                                    <label class="form-label"><strong>File Name:</strong></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="editFileName" placeholder="Enter file name">
                                        <button class="btn btn-outline-primary" onclick="renameFile()" title="Rename File">
                                            <i class="bi bi-pencil"></i> Rename
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Original: <span id="editFilePath"></span></small>
                                </div>
                            </div>
                            <div class="col-12 col-lg-5">
                                <div class="mb-2">
                                    <label class="form-label"><strong>Editor Theme:</strong></label>
                                    <select class="form-select" id="editorTheme" onchange="changeEditorTheme()">
                                        <option value="light">Light Theme</option>
                                        <option value="dark">Dark Theme</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="editorContainer" style="min-height: 500px;">
                        <textarea id="fileEditor" class="form-control" style="width: 100%; height: 500px; font-family: 'Courier New', monospace; font-size: 14px; resize: none;"></textarea>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            <span id="editorInfo">Line: 1, Col: 1</span> |
                            <span id="fileSize">0 KB</span> |
                            <span id="fileType">Text</span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveFile()">
                        <i class="bi bi-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveAndClose()">
                        <i class="bi bi-check2-square"></i> Save and Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newFileModal" tabindex="-1" aria-labelledby="newFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newFileModalLabel">
                        <i class="bi bi-file-earmark-plus"></i> Create New File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newFileName" class="form-label">File Name:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="newFileName" placeholder="ex: index, style, script">
                            <select class="form-select" id="fileExtension" style="max-width: 120px;">
                                <option value=".txt">.txt</option>
                                <option value=".php">.php</option>
                                <option value=".html">.html</option>
                                <option value=".css">.css</option>
                                <option value=".js">.js</option>
                                <option value=".json">.json</option>
                                <option value=".xml">.xml</option>
                                <option value=".md">.md</option>
                                <option value=".sql">.sql</option>
                                <option value=".py">.py</option>
                                <option value=".java">.java</option>
                                <option value=".cpp">.cpp</option>
                                <option value=".c">.c</option>
                                <option value=".cs">.cs</option>
                                <option value=".bat">.bat</option>
                                <option value=".sh">.sh</option>
                                <option value=".env">.env</option>
                                <option value=".gitignore">.gitignore</option>
                                <option value="custom">Custom...</option>
                            </select>
                        </div>
                        <small class="text-muted">Or type the full file name (ex: index.php)</small>
                    </div>
                    <div class="mb-3">
                        <label for="fileContent" class="form-label">Content (Optional):</label>
                        <textarea class="form-control" id="fileContent" rows="10" placeholder="File content..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="addTemplate">
                        <label class="form-check-label" for="addTemplate">
                            Add template based on file type
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmCreateFile()">
                        <i class="bi bi-file-earmark-plus"></i> Create File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1" aria-labelledby="newFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newFolderModalLabel">
                        <i class="bi bi-folder-plus"></i> Yeni Klasör Oluştur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newFolderName" class="form-label">Klasör Adı:</label>
                        <input type="text" class="form-control" id="newFolderName" placeholder="örn: yeni-proje" autofocus>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-success" onclick="confirmCreateDirectory()">
                        <i class="bi bi-folder-plus"></i> Klasör Oluştur
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="manualCommandModal" tabindex="-1" aria-labelledby="manualCommandModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning" style="color: var(--text-color);">
                    <h5 class="modal-title" id="manualCommandModalLabel">
                        <i class="bi bi-terminal"></i> Administrator CMD Command
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle"></i> Automatic Administrator Privilege!</h5>
                        <p>Follow the steps below:</p>
                        <ol>
                            <li>Press **Windows + R** keys (Run window opens)</li>
                            <li>**Copy** the command below (Click the Copy button)</li>
                            <li>**Paste** into the Run window (Ctrl+V)</li>
                            <li>Press **Enter**</li>
                            <li>A **UAC window** will open, say **"Yes"**</li>
                            <li>The CMD window will open, create the symlink, and **close automatically**</li>
                        </ol>
                        <div class="alert alert-info mt-2">
                            <i class="bi bi-lightbulb"></i> **Tip:** This command will automatically request administrator privileges!
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">**Command to Copy:**</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace bg-dark text-light" id="commandText" readonly style="font-size: 14px;">
                            <button class="btn btn-primary" onclick="copyCommand()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-success">
                        <p class="mb-2">**If successful:**</p>
                        <ul class="mb-0">
                            <li>CMD window will open after UAC approval</li>
                            <li>Symlink will be created</li>
                            <li>CMD window will close automatically</li>
                            <li>Check the box below and verify</li>
                        </ul>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="commandExecuted">
                        <label class="form-check-label" for="commandExecuted">
                            I ran the command in administrator CMD
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="checkSymlinkCreated()" id="checkSymlinkBtn" disabled>
                        <i class="bi bi-check-circle"></i> Check and Add
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Universal Confirm Modal -->
    <div class="modal fade" id="universalConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> <span id="confirmTitle">Confirmation Required</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage" class="mb-3"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Universal Prompt Modal -->
    <div class="modal fade" id="universalPromptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-input-cursor"></i> <span id="promptTitle">Input Required</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="promptMessage" class="mb-3"></p>
                    <input type="text" class="form-control" id="promptInput" placeholder="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="promptActionBtn">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/selection/active-line.min.js"></script>
    <script>
        let currentPath = '<?php echo str_replace('\\', '\\\\', $reposPath); ?>';
        let pendingSymlinkPath = '';
        let editor = null; // CodeMirror editor instance
        let currentEditingFile = null;
        let currentSymlinks = []; // Store current symlinks

        // Universal confirm modal function
        function showConfirm(message, callback, title = 'Confirmation Required') {
            $('#confirmTitle').text(title);
            $('#confirmMessage').text(message);

            const modal = new bootstrap.Modal(document.getElementById('universalConfirmModal'));

            // Remove old event listeners
            $('#confirmActionBtn').off('click');
            $('#universalConfirmModal').off('hidden.bs.modal');

            // Add new event listeners
            $('#confirmActionBtn').on('click', function() {
                modal.hide();
                if (callback) callback(true);
            });

            $('#universalConfirmModal').on('hidden.bs.modal', function() {
                if (callback) callback(false);
                callback = null; // Prevent multiple calls
            });

            modal.show();
        }

        // Universal prompt modal function
        function showPrompt(message, defaultValue, callback, title = 'Input Required') {
            $('#promptTitle').text(title);
            $('#promptMessage').text(message);
            $('#promptInput').val(defaultValue || '');

            const modal = new bootstrap.Modal(document.getElementById('universalPromptModal'));

            // Remove old event listeners
            $('#promptActionBtn').off('click');
            $('#promptInput').off('keypress');
            $('#universalPromptModal').off('hidden.bs.modal');

            // Handle Enter key in input
            $('#promptInput').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#promptActionBtn').click();
                }
            });

            // Add new event listeners
            $('#promptActionBtn').on('click', function() {
                const value = $('#promptInput').val();
                modal.hide();
                if (callback) callback(value);
            });

            $('#universalPromptModal').on('hidden.bs.modal', function() {
                if (callback) callback(null);
                callback = null; // Prevent multiple calls
            });

            modal.show();

            // Focus on input after modal is shown
            setTimeout(() => {
                $('#promptInput').focus().select();
            }, 500);
        }

        // Show alert modal (replaces alert())
        function showAlert(message, title = 'Information') {
            // Use error modal for alerts
            $('#errorModalLabel').html('<i class="bi bi-info-circle"></i> ' + title);
            $('#errorMessage').html(message);
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
        }

        // Get icon based on file extension
        function getFileIcon(fileName, isDir) {
            if (isDir) {
                return 'bi-folder-fill text-warning';
            }

            const ext = fileName.split('.').pop().toLowerCase();

            const iconMap = {
                // Web files
                'html': 'bi-filetype-html text-danger',
                'htm': 'bi-filetype-html text-danger',
                'css': 'bi-filetype-css text-info',
                'scss': 'bi-filetype-scss text-pink',
                'sass': 'bi-filetype-sass text-pink',
                'js': 'bi-filetype-js text-warning',
                'jsx': 'bi-filetype-jsx text-info',
                'ts': 'bi-filetype-tsx text-primary',
                'tsx': 'bi-filetype-tsx text-primary',
                'json': 'bi-filetype-json text-warning',
                'xml': 'bi-filetype-xml text-success',
                'svg': 'bi-filetype-svg text-warning',

                // Programming languages
                'php': 'bi-filetype-php text-primary',
                'py': 'bi-filetype-py text-warning',
                'java': 'bi-filetype-java text-danger',
                'c': 'bi-file-earmark-code text-primary',
                'cpp': 'bi-file-earmark-code text-primary',
                'cs': 'bi-filetype-cs text-success',
                'rb': 'bi-filetype-rb text-danger',
                'go': 'bi-file-earmark-code text-info',
                'rs': 'bi-file-earmark-code text-warning',
                'swift': 'bi-file-earmark-code text-danger',
                'kt': 'bi-file-earmark-code text-purple',
                'r': 'bi-file-earmark-code text-primary',
                'pl': 'bi-file-earmark-code text-info',
                'sh': 'bi-filetype-sh text-success',
                'bash': 'bi-filetype-sh text-success',
                'ps1': 'bi-terminal text-primary',
                'bat': 'bi-filetype-exe text-secondary',
                'cmd': 'bi-terminal text-secondary',

                // Database
                'sql': 'bi-filetype-sql text-warning',
                'db': 'bi-database text-primary',
                'sqlite': 'bi-database text-primary',

                // Config files
                'yml': 'bi-filetype-yml text-danger',
                'yaml': 'bi-filetype-yml text-danger',
                'toml': 'bi-file-earmark-text text-warning',
                'ini': 'bi-gear text-secondary',
                'conf': 'bi-gear text-secondary',
                'config': 'bi-gear text-secondary',
                'env': 'bi-gear-fill text-warning',
                'gitignore': 'bi-git text-danger',
                'dockerignore': 'bi-file-earmark-text text-primary',
                'editorconfig': 'bi-gear text-secondary',
                'eslintrc': 'bi-file-earmark-text text-purple',
                'prettierrc': 'bi-file-earmark-text text-pink',

                // Documentation
                'md': 'bi-filetype-md text-primary',
                'mdx': 'bi-filetype-mdx text-primary',
                'txt': 'bi-filetype-txt text-secondary',
                'pdf': 'bi-filetype-pdf text-danger',
                'doc': 'bi-filetype-doc text-primary',
                'docx': 'bi-filetype-docx text-primary',
                'xls': 'bi-filetype-xls text-success',
                'xlsx': 'bi-filetype-xlsx text-success',
                'ppt': 'bi-filetype-ppt text-danger',
                'pptx': 'bi-filetype-pptx text-danger',

                // Images
                'jpg': 'bi-filetype-jpg text-success',
                'jpeg': 'bi-filetype-jpg text-success',
                'png': 'bi-filetype-png text-info',
                'gif': 'bi-filetype-gif text-warning',
                'bmp': 'bi-filetype-bmp text-purple',
                'ico': 'bi-file-earmark-image text-info',
                'webp': 'bi-file-earmark-image text-success',
                'tiff': 'bi-filetype-tiff text-secondary',
                'psd': 'bi-filetype-psd text-info',
                'ai': 'bi-filetype-ai text-warning',

                // Videos
                'mp4': 'bi-filetype-mp4 text-danger',
                'avi': 'bi-file-earmark-play text-danger',
                'mov': 'bi-filetype-mov text-danger',
                'wmv': 'bi-file-earmark-play text-primary',
                'flv': 'bi-file-earmark-play text-danger',
                'mkv': 'bi-file-earmark-play text-purple',
                'webm': 'bi-file-earmark-play text-success',

                // Audio
                'mp3': 'bi-filetype-mp3 text-success',
                'wav': 'bi-filetype-wav text-primary',
                'aac': 'bi-filetype-aac text-warning',
                'flac': 'bi-file-earmark-music text-info',
                'ogg': 'bi-file-earmark-music text-secondary',
                'm4a': 'bi-filetype-m4p text-danger',

                // Archives
                'zip': 'bi-file-earmark-zip text-warning',
                'rar': 'bi-file-earmark-zip text-purple',
                '7z': 'bi-file-earmark-zip text-secondary',
                'tar': 'bi-file-earmark-zip text-secondary',
                'gz': 'bi-file-earmark-zip text-info',
                'bz2': 'bi-file-earmark-zip text-primary',

                // Executables
                'exe': 'bi-filetype-exe text-danger',
                'msi': 'bi-file-earmark-binary text-danger',
                'app': 'bi-file-earmark-binary text-primary',
                'deb': 'bi-file-earmark-binary text-warning',
                'rpm': 'bi-file-earmark-binary text-danger',

                // Fonts
                'ttf': 'bi-filetype-ttf text-secondary',
                'otf': 'bi-filetype-otf text-secondary',
                'woff': 'bi-filetype-woff text-info',
                'woff2': 'bi-filetype-woff text-info',
                'eot': 'bi-filetype-eot text-primary',

                // Other
                'lock': 'bi-lock-fill text-warning',
                'log': 'bi-file-earmark-text text-secondary',
                'bak': 'bi-file-earmark-text text-muted',
                'tmp': 'bi-file-earmark text-muted',
                'cache': 'bi-file-earmark text-muted',
                'DS_Store': 'bi-file-earmark text-muted'
            };

            // Special files (check full name)
            const specialFiles = {
                'Dockerfile': 'bi-file-earmark-text text-primary',
                'docker-compose.yml': 'bi-file-earmark-text text-primary',
                'docker-compose.yaml': 'bi-file-earmark-text text-primary',
                'package.json': 'bi-filetype-json text-success',
                'package-lock.json': 'bi-lock-fill text-warning',
                'yarn.lock': 'bi-lock-fill text-info',
                'composer.json': 'bi-filetype-json text-warning',
                'composer.lock': 'bi-lock-fill text-warning',
                'Gemfile': 'bi-gem text-danger',
                'Gemfile.lock': 'bi-lock-fill text-danger',
                'Makefile': 'bi-file-earmark-text text-secondary',
                'CMakeLists.txt': 'bi-file-earmark-text text-success',
                'README.md': 'bi-book text-primary',
                'LICENSE': 'bi-file-earmark-text text-secondary',
                '.gitignore': 'bi-git text-danger',
                '.env': 'bi-gear-fill text-warning',
                '.htaccess': 'bi-shield-lock text-danger',
                'robots.txt': 'bi-robot text-secondary',
                'sitemap.xml': 'bi-diagram-3 text-success'
            };

            // Check for special files first
            if (specialFiles[fileName]) {
                return specialFiles[fileName];
            }

            // Return icon based on extension or default
            return iconMap[ext] || 'bi-file-earmark text-secondary';
        }

        // Check if folder is symlinked
        function isSymlinked(path) {
            // If currentSymlinks is empty, return false
            if (!currentSymlinks || currentSymlinks.length === 0) {
                return false;
            }

            // Normalize paths for comparison (lowercase and slash correction)
            let normalizedPath = path.toLowerCase().replace(/\//g, '\\').replace(/\\+/g, '\\').trim();

            // Search for this folder among symlinks
            for (let symlink of currentSymlinks) {
                if (!symlink.target || symlink.target === 'N/A') continue;

                // Normalize symlink target as well
                let normalizedTarget = symlink.target.toLowerCase().replace(/\//g, '\\').replace(/\\+/g, '\\').trim();

                // Exact match check
                if (normalizedTarget === normalizedPath) {
                    return symlink.name; // Return symlink name
                }
            }
            return false; // Not a symlink
        }

        // Theme management
        function initTheme() {
            // First check if user has a saved preference
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme) {
                // Use saved preference if it exists
                document.documentElement.setAttribute('data-theme', savedTheme);
                updateThemeIcon(savedTheme);
            } else {
                // No saved preference, check system preference
                const prefersDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const systemTheme = prefersDarkMode ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', systemTheme);
                updateThemeIcon(systemTheme);
                // Don't save to localStorage yet - let user make explicit choice
            }

            // Listen for system theme changes
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                mediaQuery.addEventListener('change', (e) => {
                    // Only apply system changes if user hasn't set a preference
                    if (!localStorage.getItem('theme')) {
                        const newTheme = e.matches ? 'dark' : 'light';
                        document.documentElement.setAttribute('data-theme', newTheme);
                        updateThemeIcon(newTheme);

                        // Update CodeMirror theme if editor is open
                        if (editor) {
                            const editorTheme = newTheme === 'dark' ? 'monokai' : 'default';
                            editor.setOption('theme', editorTheme);
                            $('#editorTheme').val(newTheme);
                        }
                    }
                });
            }
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);

            // Update CodeMirror theme if editor is open
            if (editor) {
                const editorTheme = newTheme === 'dark' ? 'monokai' : 'default';
                editor.setOption('theme', editorTheme);
                $('#editorTheme').val(newTheme);
            }
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (theme === 'dark') {
                icon.className = 'bi bi-sun-fill';
            } else {
                icon.className = 'bi bi-moon-fill';
            }
        }

        // Initialize theme before page load
        initTheme();

        // Modal helper functions
        function showSuccessModal(message, details) {
            $('#successMessage').text(message);
            $('#successDetails').html(details);

            // Close previous modals
            $('.modal').modal('hide');

            // Open Success modal
            var modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();
        }

        function showErrorModal(message, details) {
            $('#errorMessage').text(message);
            $('#errorDetails').html(details);

            // Close previous modals
            $('.modal').modal('hide');

            // Open Error modal
            var modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
        }

        // When page is loaded
        $(document).ready(function() {
            // Check if Repos folder exists
            checkReposFolder();

            // Load symlinks first, then others
            loadSymlinks(function() {
                // Load others after symlinks are loaded
                loadDirectory(currentPath);
                loadProjects();
                loadReposProjects();
                loadHtdocsFolders();
            });

            // Activate Tooltips (safe initialization)
            initializeTooltips();

            // Update preview when symlink name changes
            $('#symlinkName').on('input', function() {
                $('#symlinkPreview').text($(this).val() || 'project-name');
            });

            // Global click event - close tooltips
            $(document).on('click', function(e) {
                // If the clicked element is not a tooltip trigger
                if (!$(e.target).closest('[data-bs-toggle="tooltip"]').length) {
                    // Close all open tooltips
                    $('.tooltip').fadeOut(150, function() {
                        $(this).remove();
                    });
                }
            });

            // Clear tooltips when tab changes
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                $('.tooltip').remove();
            });
        });

        // Check Repos folder existence
        function checkReposFolder() {
            $.post('', {
                action: 'check_repos_exists'
            }, function(response) {
                if (!response.exists) {
                    // Show warning with Modal
                    showErrorModal(
                        'Repos Folder Not Found',
                        '<i class="bi bi-folder-x"></i> <strong>Folder not found:</strong><br>' +
                        '<code>' + response.path + '</code><br><br>' +
                        'This folder is required to store your projects.<br><br>' +
                        '<button class="btn btn-success" onclick="createReposFolder(); $(\'#errorModal\').modal(\'hide\');">' +
                        '<i class="bi bi-folder-plus"></i> Create Folder' +
                        '</button>'
                    );
                }
            }, 'json');
        }

        // Create Repos folder
        function createReposFolder() {
            showLoading('Creating Repos folder...');
            $.post('', {
                action: 'create_repos_folder'
            }, function(response) {
                hideLoading();
                if (response.success) {
                    showSuccessModal(
                        'Repos Folder Created!',
                        '<i class="bi bi-folder-check"></i> ' + response.message + '<br><br>' +
                        'Page is refreshing...'
                    );
                    // Refresh page
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorModal(
                        'Could Not Create Folder',
                        '<i class="bi bi-x-circle"></i> ' + response.message + '<br><br>' +
                        '<strong>You can create the folder manually:</strong><br>' +
                        currentPath
                    );
                }
            }, 'json');
        }

        // Navigate to custom folder path
        function loadCustomPath() {
            let customPath = $('#customPathInput').val();
            if (customPath) {
                loadDirectory(customPath);
            }
        }

        // Load file list
        function loadDirectory(path) {
            $('#fileManagerError').hide();

            // Write path to input
            $('#customPathInput').val(path);

            $.post('', {
                action: 'list_directory',
                path: path
            }, function(response) {
                if (response.error) {
                    $('#fileListContainer').html('<div class="alert alert-danger">' + response.error + '</div>');
                    $('#fileManagerError').html('Error: ' + response.error).show();
                    return;
                }

                currentPath = response.current_path;
                updateBreadcrumb(currentPath);

                let html = '<div class="list-group">';

                // Parent directory - always show (except root)
                let pathParts = currentPath.split('\\').filter(p => p);
                if (pathParts.length > 1 || (pathParts.length === 1 && pathParts[0].includes(':'))) {
                    html += `<div class="list-group-item file-item parent-directory">
                    <div class="d-inline align-items-center" onclick="navigateUp()" style="cursor:pointer;">
                        <i class="bi bi-arrow-up-circle text-primary"></i> <strong>.. (Parent Directory)</strong>
                    </div>
                    </div>`;
                }

                // Show info if no files/folders
                if (!response.items || response.items.length === 0) {
                    html += '<div class="list-group-item text-muted">No files or folders found in this directory.</div>';
                } else {
                    response.items.forEach(function(item) {
                        // Use the new getFileIcon function
                        let icon = getFileIcon(item.name, item.is_dir);

                        let sizeStr = item.size ? formatFileSize(item.size) : '';
                        let className = item.is_dir ? 'directory' : '';

                        // Escape backslashes in paths
                        let escapedPath = item.path.replace(/\\/g, '\\\\');
                        let escapedName = item.name.replace(/'/g, "\\'");

                        // Single click event for files
                        let clickEvent = !item.is_dir ? 'onclick="editFile(\'' + escapedPath + '\')"' : '';

                        // Check for symlink for folders
                        let symlinkName = item.is_dir ? isSymlinked(item.path) : false;

                        html += `<div class="list-group-item file-item ${className}" data-path="${escapedPath}" data-name="${item.name}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="cursor: pointer;" ${clickEvent}>
                                    <i class="bi ${icon}"></i> <span ${item.is_dir ? 'style="cursor:pointer;" onclick="navigateTo(\'' + escapedPath + '\')"' : ''}>${item.name}</span>
                                    ${item.has_index ? '<span class="badge bg-success ms-2" data-bs-toggle="tooltip" title="This folder has index.php">PHP Project</span>' : ''}
                                    ${symlinkName ? '<span class="badge bg-info ms-2" data-bs-toggle="tooltip" title="Linked as Symlink: ' + symlinkName + '"><i class="bi bi-link-45deg"></i> Linked</span>' : ''}
                                </div>
                                <div>
                                    ${sizeStr ? '<span class="text-muted me-3">' + sizeStr + '</span>' : ''}
                                    <span class="text-muted me-3">${item.modified}</span>
                                    ${!item.is_dir ? '<button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); editFile(\'' + escapedPath + '\')" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></button>' : ''}
                                    ${item.is_dir ? (symlinkName ?
                                        '<button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); removeSymlink(\'' + symlinkName + '\')" data-bs-toggle="tooltip" title="Remove Symlink"><i class="bi bi-link-45deg"></i> Remove</button>' :
                                        '<button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openSymlinkModal(\'' + escapedPath + '\', \'' + escapedName + '\')" data-bs-toggle="tooltip" title="Add as symlink to htdocs"><i class="bi bi-link-45deg"></i> Link</button>'
                                    ) : ''}
                                    ${item.is_php && !item.is_dir ? '<a href="/test-runner.php?file=' + encodeURIComponent(item.path) + '" target="_blank" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Run PHP file"><i class="bi bi-play"></i></a>' : ''}
                                    ${item.is_dir ? '<button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); openInExplorer(\'' + escapedPath + '\')" data-bs-toggle="tooltip" title="Open in Windows Explorer"><i class="bi bi-windows"></i></button>' : ''}
                                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteItem('${escapedPath}')" data-bs-toggle="tooltip" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                }

                html += '</div>';
                $('#fileListContainer').html(html);

                // Refresh tooltips
                refreshTooltips();
            }, 'json')
            .fail(function(xhr, status, error) {
                $('#fileManagerError').html('AJAX Error: ' + error).show();
                $('#fileListContainer').html('<div class="alert alert-danger">Error loading directory: ' + error + '</div>');
            });
        }

        // Initialize tooltips on first load
        function initializeTooltips() {
            // Dispose of any existing tooltips first
            $('.tooltip').remove();

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                // Create new tooltip for each element
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus',
                    placement: 'top',
                    container: 'body',
                    html: false,
                    boundary: 'window',
                    fallbackPlacements: ['top', 'bottom', 'left', 'right'],
                    delay: { show: 300, hide: 0 }
                });

                // Remove tooltip on mouseleave
                tooltipTriggerEl.addEventListener('mouseleave', function() {
                    var tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                    if (tooltip) {
                        tooltip.hide();
                    }
                });

                // Close tooltip on click
                tooltipTriggerEl.addEventListener('click', function() {
                    var tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                    if (tooltip) {
                        tooltip.hide();
                    }
                });
            });
        }

        // Refresh tooltips
        function refreshTooltips() {
            // Dispose of existing tooltips
            var existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            existingTooltips.forEach(function(element) {
                var existingTooltip = bootstrap.Tooltip.getInstance(element);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
            });

            // Create new tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus',
                    placement: 'top',
                    container: 'body',
                    boundary: 'window',
                    delay: { show: 500, hide: 100 }
                });
            });
        }

        // Open symlink modal
        function openSymlinkModal(path, name) {
            pendingSymlinkPath = path;
            $('#symlinkSource').val(path);
            $('#symlinkName').val(name);
            $('#symlinkPreview').text(name);

            var myModal = new bootstrap.Modal(document.getElementById('symlinkModal'));
            myModal.show();
        }

        // Create Symlink - Direct with Admin Privilege
        function showManualSymlinkCommand() {
            let name = $('#symlinkName').val();

            if (!name) {
                showAlert('Please enter a name for the symlink!', 'Input Required');
                return;
            }

            showLoading('Creating symlink...');

            // Create symlink directly (admin privilege will be automatically requested)
            $.post('', {
                action: 'create_symlink',
                source: pendingSymlinkPath,
                name: name
            }, function(response) {
                hideLoading();

                if (response.success) {
                    // Close modal
                    var symlinkModal = bootstrap.Modal.getInstance(document.getElementById('symlinkModal'));
                    symlinkModal.hide();

                    // Show success modal
                    showSuccessModal(
                        'Symlink Created!',
                        '<i class="bi bi-link-45deg"></i> <strong>' + name + '</strong> symlink created successfully.<br><br>' +
                        '<a href="http://localhost/' + name + '" target="_blank" class="btn btn-sm btn-success">' +
                        '<i class="bi bi-box-arrow-up-right"></i> Open Project (http://localhost/' + name + ')' +
                        '</a>'
                    );

                    // Refresh lists
                    loadSymlinks(function() {
                        loadProjects();
                        loadHtdocsFolders();
                        // Update file list
                        loadDirectory(currentPath);
                    });
                } else if (response.batch_created) {
                    // Batch file created but could not run automatically
                    var symlinkModal = bootstrap.Modal.getInstance(document.getElementById('symlinkModal'));
                    symlinkModal.hide();

                    showErrorModal(
                        'Manual Action Required',
                        '<i class="bi bi-exclamation-triangle"></i> Could not create automatically.<br><br>' +
                        '<strong>Batch file is ready:</strong><br>' +
                        '<code>' + response.batch_path + '</code><br><br>' +
                        '<button class="btn btn-warning" onclick="window.location.href=\'file:///' + response.batch_path.replace(/\\/g, '/') + '\'">' +
                        '<i class="bi bi-folder-open"></i> Open File (Requires Admin privilege)' +
                        '</button>'
                    );
                } else {
                    showErrorModal(
                        'Could Not Create Symlink',
                        '<i class="bi bi-exclamation-triangle"></i> ' + response.message +
                        '<br><br><strong>💡 Suggested solutions:</strong>' +
                        '<ul>' +
                        '<li>Run XAMPP as administrator</li>' +
                        '<li>Activate Windows Developer Mode</li>' +
                        '</ul>'
                    );
                }
            }, 'json');
        }

        // Copy command
        function copyCommand() {
            let commandText = document.getElementById('commandText');
            commandText.select();
            commandText.setSelectionRange(0, 99999); // For mobile

            try {
                document.execCommand('copy');

                // Change button text
                let copyBtn = event.target.closest('button');
                let originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                copyBtn.classList.remove('btn-primary');
                copyBtn.classList.add('btn-success');

                setTimeout(function() {
                    copyBtn.innerHTML = originalHtml;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-primary');
                }, 2000);
            } catch (err) {
                showAlert('Copy failed. Please copy manually.', 'Copy Error');
            }
        }

        // Check if symlink was created
        function checkSymlinkCreated() {
            let name = $('#symlinkName').val();

            showLoading();

            // Check if symlink exists
            $.post('', {
                action: 'check_symlink',
                name: name
            }, function(response) {
                hideLoading();

                if (response.exists) {
                    // Close modal
                    var manualModal = bootstrap.Modal.getInstance(document.getElementById('manualCommandModal'));

                    // Modal kapandıktan sonra success modal göster
                    $('#manualCommandModal').one('hidden.bs.modal', function() {
                        // Success modal içeriğini ayarla
                        $('#successMessage').text('Symlink başarıyla oluşturuldu!');
                        $('#successDetails').html('<strong>Proje Adı:</strong> ' + name + '<br><strong>URL:</strong> http://localhost/' + name);

                        // Link butonunu göster ve URL'yi ayarla
                        $('#successLinkBtn').attr('href', 'http://localhost/' + name).show();

                        // Success modal'ı göster
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();

                        // Modal kapandığında link butonunu gizle
                        $('#successModal').one('hidden.bs.modal', function() {
                            $('#successLinkBtn').hide();
                        });

                        // Refresh lists
                        loadSymlinks(function() {
                            loadProjects();
                            loadHtdocsFolders();
                            // Update file list
                            loadDirectory(currentPath);
                        });
                    });

                    manualModal.hide();

                    // Clear checkbox
                    $('#commandExecuted').prop('checked', false);
                    $('#checkSymlinkBtn').prop('disabled', true);
                } else {
                    showErrorModal(
                        'Symlink Not Found',
                        '❌ Symlink not found!<br><br>Please make sure you ran the command in administrator CMD.<br><br><strong>Alternative:</strong> If the command was successful, you should have seen the "symbolic link created" message.'
                    );
                }
            }, 'json');
        }

        // Old function - kept for compatibility
        function confirmCreateSymlink() {
            showManualSymlinkCommand();
        }

        // Navigate to directory
        function navigateTo(path) {
            loadDirectory(path);
        }

        // Navigate up one level
        function navigateUp() {
            let parts = currentPath.split('\\');
            parts.pop();
            let parentPath = parts.join('\\');
            if (parentPath.length > 0) {
                loadDirectory(parentPath);
            }
        }

        // Update breadcrumb
        function updateBreadcrumb(path) {
            let parts = path.split('\\');
            let html = '';
            let currentPath = '';

            parts.forEach(function(part, index) {
                if (index > 0) currentPath += '\\';
                currentPath += part;

                // Escape path for JavaScript
                let escapedPath = currentPath.replace(/\\/g, '\\\\');

                if (index === parts.length - 1) {
                    html += `<li class="breadcrumb-item active">${part}</li>`;
                } else {
                    html += `<li class="breadcrumb-item"><a href="#" onclick="navigateTo('${escapedPath}')">${part}</a></li>`;
                }
            });

            $('#breadcrumb').html(html);
        }

        // Create new directory
        function createNewDirectory() {
            // Modal'ı aç
            $('#newFolderName').val('');
            var myModal = new bootstrap.Modal(document.getElementById('newFolderModal'));
            myModal.show();

            // Enter tuşu ile de oluşturabilmek için
            $('#newFolderName').off('keypress').on('keypress', function(e) {
                if (e.which === 13) {
                    confirmCreateDirectory();
                }
            });
        }

        function confirmCreateDirectory() {
            let name = $('#newFolderName').val();

            if (!name) {
                showAlert('Please enter a folder name!', 'Input Required');
                return;
            }

            showLoading('Creating folder...');
            $.post('', {
                action: 'create_directory',
                path: currentPath,
                name: name
            }, function(response) {
                hideLoading();

                // Modal'ı kapat
                var modal = bootstrap.Modal.getInstance(document.getElementById('newFolderModal'));
                modal.hide();

                if (response.success) {
                    refreshDirectory();
                } else {
                    showErrorModal('Error', response.message);
                }
            }, 'json');
        }

        // Open New File modal
        function createNewFile() {
            // Prepare modal
            $('#newFileName').val('');
            $('#fileContent').val('');
            $('#fileExtension').val('.txt');
            $('#addTemplate').prop('checked', false);

            var myModal = new bootstrap.Modal(document.getElementById('newFileModal'));
            myModal.show();
        }

        // Confirm file creation
        function confirmCreateFile() {
            let fileName = $('#newFileName').val();
            let extension = $('#fileExtension').val();
            let content = $('#fileContent').val();
            let addTemplate = $('#addTemplate').is(':checked');

            if (!fileName) {
                showAlert('Please enter a file name!', 'Input Required');
                return;
            }

            // Append extension if not present
            if (fileName.includes('.')) {
                // User entered full file name
            } else if (extension === 'custom') {
                // Custom extension
                showPrompt('Enter custom extension (ex: .config):', '', function(customExt) {
                    if (customExt) {
                        fileName += customExt;
                        // Continue with file creation inside callback
                        continueFileCreation(fileName, content, addTemplate);
                    }
                }, 'Custom File Extension');
                return; // Exit here and continue in callback
            } else {
                // Append selected extension
                fileName += extension;
            }

            // Add template if needed and content is empty
            if (addTemplate && content === '') {
                content = getFileTemplate(fileName);
            }

            showLoading('Creating file...');
            $.post('', {
                action: 'create_file',
                path: currentPath,
                name: fileName,
                content: content
            }, function(response) {
                hideLoading();

                // Close modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('newFileModal'));
                modal.hide();

                if (response.success) {
                    refreshDirectory();
                    // Show success message
                    $('#fileManagerError').removeClass('alert-warning').addClass('alert-success').html(
                        '<i class="bi bi-check-circle"></i> ' + response.message
                    ).show();                   
                } else {
                    showErrorModal('Error', response.message);
                }
            }, 'json');
        }

        // Continue file creation (for custom extension)
        function continueFileCreation(fileName, content, addTemplate) {
            // Add template if needed and content is empty
            if (addTemplate && content === '') {
                content = getFileTemplate(fileName);
            }

            showLoading('Creating file...');
            $.post('', {
                action: 'create_file',
                path: currentPath,
                name: fileName,
                content: content
            }, function(response) {
                hideLoading();

                // Close modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('newFileModal'));
                modal.hide();

                if (response.success) {
                    refreshDirectory();
                    // Show success message
                    $('#fileManagerError').removeClass('alert-warning').addClass('alert-success').html(
                        '<i class="bi bi-check-circle"></i> ' + response.message
                    ).show();
                } else {
                    showErrorModal('Error', response.message);
                }
            }, 'json');
        }

        // Get file template
        function getFileTemplate(fileName) {
            let ext = fileName.split('.').pop().toLowerCase();

            const templates = {
                'php': '<?php\n// ' + fileName + '\n\n?>\n',
                'html': '<!DOCTYPE html>\n<html lang="en">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>Title</title>\n</head>\n<body>\n    <h1>Hello World!</h1>\n</body>\n</html>',
                'css': '/* ' + fileName + ' */\n\n* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}\n',
                'js': '// ' + fileName + '\n\n// JavaScript code here\n',
                'json': '{\n    "name": "project",\n    "version": "1.0.0"\n}',
                'sql': '-- ' + fileName + '\n-- SQL queries\n\n',
                'py': '# ' + fileName + '\n# Python code\n\n',
                'java': '// ' + fileName + '\n\npublic class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello World!");\n    }\n}',
                'md': '# Title\n\n## Subtitle\n\nMarkdown content here...\n',
                'env': '# Environment Variables\nAPP_NAME=MyApp\nAPP_ENV=local\nAPP_DEBUG=true\n',
                'gitignore': '# Dependencies\nnode_modules/\nvendor/\n\n# Build\ndist/\nbuild/\n\n# Environment\n.env\n.env.local\n\n# IDE\n.vscode/\n.idea/\n*.sublime-*\n\n# OS\n.DS_Store\nThumbs.db\n'
            };

            return templates[ext] || '// ' + fileName + '\n';
        }

        // Delete item
        function deleteItem(path) {
            showConfirm('Are you sure you want to delete this item?', function(confirmed) {
                if (!confirmed) return;

                showLoading('Deleting...');
                $.post('', {
                    action: 'delete_item',
                    path: path
                }, function(response) {
                    hideLoading();
                    if (response.success) {
                        refreshDirectory();
                    } else {
                        showErrorModal('Error', response.message);
                    }
                }, 'json');
            });
        }

        // Refresh directory
        function refreshDirectory() {
            loadDirectory(currentPath);
        }

        // Create symlink (quick)
        function createProjectSymlink(source, defaultName) {
            showPrompt('Project name (localhost/' + defaultName + '):', defaultName, function(name) {
                if (!name) return;

                showLoading('Creating symlink...');
                $.post('', {
                action: 'create_symlink',
                source: source,
                name: name
            }, function(response) {
                hideLoading();
                if (response.success) {
                    showSuccessModal(
                        'Symlink Created!',
                        '<i class="bi bi-link-45deg"></i> <strong>' + name + '</strong> symlink created successfully.<br><br>' +
                        '<a href="http://localhost/' + name + '" target="_blank" class="btn btn-sm btn-success">' +
                        '<i class="bi bi-box-arrow-up-right"></i> Open Project (http://localhost/' + name + ')' +
                        '</a>'
                    );
                    loadSymlinks(function() {
                        loadProjects();
                        loadHtdocsFolders();
                    });
                    // Update file list
                    setTimeout(() => loadDirectory(currentPath), 100);
                } else if (response.batch_created) {
                    // Batch file created but could not run automatically
                    showErrorModal(
                        'Manual Action Required',
                        '<i class="bi bi-exclamation-triangle"></i> Could not create automatically.<br><br>' +
                        'Batch file is ready. You can download and run it as administrator.<br><br>' +
                        '<button class="btn btn-warning" onclick="downloadSymlinkBatch(\'' + response.batch_path.replace(/\\/g, '\\\\') + '\', \'' + name + '\')">' +
                        '<i class="bi bi-download"></i> Download Batch File' +
                        '</button>'
                    );
                } else {
                    showErrorModal(
                        'Could Not Create Symlink',
                        '<i class="bi bi-x-circle"></i> ' + response.message
                    );
                }
            }, 'json');
            });
        }

        // Remove symlink - Open modal
        function removeSymlink(name) {
            // Update symlink name in modal
            $('#removeSymlinkName').text(name);

            // Store globally
            window.pendingRemoveSymlink = name;

            // Hide process status areas
            $('#removeProcessStatus').hide();
            $('#removeResultMessage').hide();

            // Show buttons
            $('#cancelRemoveBtn').show();
            $('#confirmRemoveBtn').show();

            // Open modal
            var myModal = new bootstrap.Modal(document.getElementById('confirmRemoveModal'));
            myModal.show();
        }

        // Confirm symlink removal
        function confirmRemoveSymlink() {
            let name = window.pendingRemoveSymlink;

            // Hide buttons
            $('#cancelRemoveBtn').hide();
            $('#confirmRemoveBtn').hide();

            // Show process status
            $('#removeProcessStatus').show();
            $('#removeStatusText').text('Removing symlink, please wait...');

            // AJAX request
            $.post('', {
                action: 'remove_symlink',
                name: name
            }, function(response) {
                $('#removeProcessStatus').hide();

                if (response.success) {
                    // Show success message
                    $('#removeResultMessage').html(
                        '<div class="alert alert-success">' +
                        '<i class="bi bi-check-circle-fill"></i> ' +
                        '<strong>Success!</strong> ' + name + ' symlink removed.' +
                        '</div>'
                    ).show();

                    // Refresh lists
                    loadSymlinks(function() {
                        loadProjects();
                        loadHtdocsFolders();
                        // Update file list
                        loadDirectory(currentPath);
                    });                  

                } else if (response.batch_created) {
                    // Batch file created but could not run automatically
                    $('#removeResultMessage').html(
                        '<div class="alert alert-warning">' +
                        '<i class="bi bi-exclamation-triangle"></i> ' +
                        '<strong>Manual action required!</strong><br>' +
                        'Batch file created. Download and run it as administrator.' +
                        '<div class="mt-2">' +
                        '<button class="btn btn-sm btn-warning" onclick="downloadRemoveBatch(\'' + response.batch_path + '\', \'' + name + '\')">' +
                        '<i class="bi bi-download"></i> Download Batch File' +
                        '</button>' +
                        '</div>' +
                        '</div>'
                    ).show();

                    // Show cancel button again
                    $('#cancelRemoveBtn').show();

                } else {
                    // Show error message
                    $('#removeResultMessage').html(
                        '<div class="alert alert-danger">' +
                        '<i class="bi bi-x-circle-fill"></i> ' +
                        '<strong>Error!</strong> ' + response.message +
                        '</div>'
                    ).show();

                    // Show buttons again
                    $('#cancelRemoveBtn').show();
                    $('#confirmRemoveBtn').show();
                }
            }, 'json')
            .fail(function() {
                $('#removeProcessStatus').hide();
                $('#removeResultMessage').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-x-circle-fill"></i> ' +
                    '<strong>Connection error!</strong> Operation failed.' +
                    '</div>'
                ).show();

                // Show buttons again
                $('#cancelRemoveBtn').show();
                $('#confirmRemoveBtn').show();
            });
        }

        // Download remove batch file
        function downloadRemoveBatch(path, name) {
            let link = document.createElement('a');
            link.href = path.replace('C:\\xampp\\htdocs\\', '/');
            link.download = 'remove_symlink_' + name + '.bat';
            link.click();

            setTimeout(function() {
                $('#removeResultMessage').append(
                    '<div class="alert alert-info mt-2">' +
                    '<i class="bi bi-info-circle"></i> ' +
                    'Batch file downloaded. Go to your Downloads folder, right-click the file, and select "Run as administrator".' +
                    '</div>'
                );
            }, 500);
        }

        // Download symlink batch file
        function downloadSymlinkBatch(path, name) {
            let link = document.createElement('a');
            link.href = path.replace('C:\\xampp\\htdocs\\', '/');
            link.download = 'create_symlink_' + name + '.bat';
            link.click();

            // Update modal
            setTimeout(function() {
                showSuccessModal(
                    'Batch File Downloaded',
                    '<i class="bi bi-download"></i> Batch file downloaded.<br><br>' +
                    '<strong>Steps to follow:</strong>' +
                    '<ol>' +
                    '<li>Go to your Downloads folder</li>' +
                    '<li>Right-click the file</li>' +
                    '<li>Select "Run as administrator"</li>' +
                    '</ol>'
                );
            }, 500);
        }

        // Copy remove command (old/manual way)
        function copyRemoveCommand() {
            let commandText = document.getElementById('removeCommandText');
            commandText.select();
            commandText.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');

                // Change button text
                let copyBtn = event.target.closest('button');
                let originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                copyBtn.classList.remove('btn-primary');
                copyBtn.classList.add('btn-success');

                setTimeout(function() {
                    copyBtn.innerHTML = originalHtml;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-primary');
                }, 2000);
            } catch (err) {
                showAlert('Copy failed. Please copy manually.', 'Copy Error');
            }
        }

        // Check if symlink was removed (old/manual way)
        function checkSymlinkRemoved() {
            let name = window.pendingRemoveSymlink;

            showLoading();

            // Check if symlink is still present
            $.post('', {
                action: 'check_symlink',
                name: name
            }, function(response) {
                hideLoading();

                if (!response.exists) {
                    // Close modal
                    var modal = bootstrap.Modal.getInstance(document.getElementById('removeSymlinkModal'));

                    // Modal kapandıktan sonra alert göster
                    $('#removeSymlinkModal').one('hidden.bs.modal', function() {
                        showSuccessModal('Success!', '✅ Symlink removed successfully: ' + name);

                        // Refresh lists
                        loadSymlinks(function() {
                            loadProjects();
                            loadHtdocsFolders();
                            // Update file list
                            loadDirectory(currentPath);
                        });
                    });

                    modal.hide();

                    // Clear checkbox
                    $('#removeCommandExecuted').prop('checked', false);
                    $('#checkRemoveBtn').prop('disabled', true);
                } else {
                    showErrorModal(
                        'Symlink Still Present',
                        '❌ Symlink is still present!<br><br>Please make sure you ran the command with Windows+R and granted UAC approval.<br><br><strong>Alternative:</strong> Go to C:\\xampp\\htdocs\\ in File Explorer and manually delete the <strong>' + name + '</strong> folder.'
                    );
                }
            }, 'json');
        }

        // Old function - for compatibility
        function removeSymlinkOld(name) {
            showConfirm('Are you sure you want to remove the link ' + name + '?', function(confirmed) {
                if (!confirmed) return;

                showLoading();
                $.post('', {
                    action: 'remove_symlink',
                    name: name
                }, function(response) {
                    hideLoading();
                    if (response.success) {
                        showSuccessModal('Success', response.message);
                        loadSymlinks(function() {
                            loadProjects();
                            loadHtdocsFolders();
                        });
                        // Update file list
                        setTimeout(() => loadDirectory(currentPath), 100);
                    } else {
                        showErrorModal('Error', response.message);
                    }
                }, 'json');
            });
        }

        // Load active symlinks
        function loadSymlinks(callback) {
            $.post('', {
                action: 'get_symlinks'
            }, function(response) {
                // Store symlinks globally
                currentSymlinks = response || [];

                let html = '';

                if (response.length === 0) {
                    html = '<p class="text-muted">No symlinks added yet.</p>';
                } else {
                    response.forEach(function(link) {
                        html += `<div class="symlink-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${link.name}</h6>
                                    <small class="text-muted">Target: ${link.target}</small>
                                </div>
                                <div>
                                    <a href="${link.url}" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> Open
                                    </a>
                                    <button class="btn btn-sm btn-secondary" onclick="openInExplorer('${link.target.replace(/\\/g, '\\\\')}')" data-bs-toggle="tooltip" title="Open source folder in Explorer">
                                        <i class="bi bi-windows"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="removeSymlink('${link.name}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                }

                $('#activeSymlinks').html(html);

                // Call callback if exists
                if (callback) callback();
            }, 'json');
        }

        // Load Repos projects
        function loadReposProjects() {
            $.post('', {
                action: 'list_directory',
                path: '<?php echo str_replace('\\', '\\\\', $reposPath); ?>'
            }, function(response) {
                let html = '';

                if (response.items) {
                    response.items.forEach(function(item) {
                        if (item.is_dir) {
                            // Escape path
                            let escapedPath = item.path.replace(/\\/g, '\\\\');
                            let escapedName = item.name.replace(/'/g, "\\'");

                            html += `<div class="project-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-folder-fill text-warning"></i> ${item.name}
                                            ${item.has_index ? '<span class="badge bg-success ms-2">PHP</span>' : ''}
                                        </h6>
                                    </div>
                                    <div>
                                        ${item.has_index ? '<button class="btn btn-sm btn-success" onclick="createProjectSymlink(\'' + escapedPath + '\', \'' + escapedName + '\')"><i class="bi bi-link"></i> Link to htdocs</button>' : ''}
                                        <button class="btn btn-sm btn-secondary ms-1" onclick="openInExplorer('${escapedPath}')" data-bs-toggle="tooltip" title="Open in Windows Explorer">
                                            <i class="bi bi-windows"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>`;
                        }
                    });
                }

                $('#reposProjectList').html(html || '<p class="text-muted">No projects found.</p>');
            }, 'json');
        }

        // Load projects
        function loadProjects() {
            $.post('', {
                action: 'get_symlinks'
            }, function(symlinks) {
                let html = '';

                // Symlink projects
                if (symlinks.length > 0) {
                    html += '<div class="col-md-12 mb-3"><h5><i class="bi bi-link-45deg"></i> Linked Projects</h5></div>';

                    symlinks.forEach(function(link) {
                        html += `<div class="col-md-6 mb-3">
                            <div class="project-card">
                                <h5 class="card-title">
                                    <span class="status-indicator status-online"></span>
                                    ${link.name}
                                </h5>
                                <p class="text-muted mb-2">Source: ${link.target}</p>
                                <div class="d-flex gap-2">
                                    <a href="${link.url}" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="bi bi-play-circle"></i> Run
                                    </a>
                                    <button class="btn btn-secondary btn-sm" onclick="openInExplorer('${link.target.replace(/\\/g, '\\\\')}')" data-bs-toggle="tooltip" title="Open source folder in Explorer">
                                        <i class="bi bi-windows"></i> Explorer
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="removeSymlink('${link.name}')">
                                        <i class="bi bi-unlink"></i> Remove Link
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                }

                // Other projects in htdocs
                html += '<div class="col-md-12 mb-3 mt-4"><h5><i class="bi bi-folder"></i> Htdocs Projects</h5></div>';

                // You could list other PHP projects in htdocs here

                $('#projectsList').html(html || '<div class="col-md-12"><p class="text-muted">No projects added yet.</p></div>');
            }, 'json');
        }

        // Load htdocs folders
        function loadHtdocsFolders() {
            $.post('', {
                action: 'get_htdocs_folders'
            }, function(folders) {
                let html = '<div class="row">';

                if (folders.length === 0) {
                    html = '<p class="text-muted">No folders found in the htdocs directory.</p>';
                } else {
                    folders.forEach(function(folder) {
                        let icon = folder.is_symlink ? 'bi-link-45deg text-success' : 'bi-folder-fill text-warning';
                        let badge = folder.is_symlink ? '<span class="badge bg-success">Symlink</span>' : '<span class="badge bg-secondary">Folder</span>';
                        let targetInfo = folder.target ? `<br><small class="text-muted">Target: ${folder.target}</small>` : '';

                        html += `<div class="col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 ${folder.is_symlink ? 'border-success' : ''}">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi ${icon}"></i> ${folder.name}
                                        ${badge}
                                    </h6>
                                    <p class="card-text small">
                                        ${folder.size}
                                        ${targetInfo}
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="${folder.url}" target="_blank" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Open in browser">
                                            <i class="bi bi-box-arrow-up-right"></i> Open
                                        </a>
                                        ${folder.is_symlink ? `<button class="btn btn-sm btn-danger" onclick="removeSymlink('${folder.name}')" data-bs-toggle="tooltip" title="Remove Symlink">
                                            <i class="bi bi-trash"></i>
                                        </button>` : ''}
                                        <button class="btn btn-sm btn-info" onclick="openInFileManager('${folder.path.replace(/\\/g, '\\\\')}')" data-bs-toggle="tooltip" title="Open in file manager">
                                            <i class="bi bi-folder2-open"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="openInExplorer('${folder.path.replace(/\\/g, '\\\\')}')" data-bs-toggle="tooltip" title="Open in Windows Explorer">
                                            <i class="bi bi-windows"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                }

                html += '</div>';
                $('#htdocsFoldersList').html(html);

                // Activate newly added tooltips
                refreshTooltips();
            }, 'json');
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Open in File Manager tab
        function openInFileManager(path) {
            // Switch to File Manager tab
            var tab = new bootstrap.Tab(document.getElementById('filemanager-tab'));
            tab.show();

            // Wait a bit and load the directory
            setTimeout(function() {
                loadDirectory(path);
                // Also update the input field
                $('#customPathInput').val(path);
            }, 200);
        }

        // Open in Windows Explorer
        function openInExplorer(path) {
            $.post('', {
                action: 'open_explorer',
                path: path
            }, function(response) {
                if (!response.success) {
                    showErrorModal(
                        'Could Not Open Folder',
                        '<i class="bi bi-x-circle"></i> ' + response.message
                    );
                }
                // If successful, it opens silently, no need to show a modal
            }, 'json');
        }

        // File editor functions
        function editFile(path) {
            showLoading('Opening file...');

            $.post('', {
                action: 'read_file',
                path: path
            }, function(response) {
                hideLoading();

                if (response.success) {
                    currentEditingFile = path;

                    // Prepare modal
                    $('#editFilePath').text(response.name);
                    $('#editFileName').val(response.name); // Set the file name in input
                    $('#fileSize').text(formatFileSize(response.size));
                    $('#fileType').text(getFileType(response.extension));

                    // Store content temporarily
                    window.tempFileContent = response.content;
                    window.tempFileExtension = response.extension;

                    // Clear and re-bind modal event listeners
                    $('#editFileModal').off('shown.bs.modal');
                    $('#editFileModal').on('shown.bs.modal', function() {
                        // Initialize CodeMirror editor
                        initCodeMirror(window.tempFileContent, window.tempFileExtension);

                        // Refresh editor and focus
                        setTimeout(function() {
                            if (editor) {
                                editor.refresh();
                                editor.focus();
                                // Go to the beginning of the first line
                                editor.setCursor(0, 0);
                            }
                        }, 200);
                    });

                    // Open modal
                    var myModal = new bootstrap.Modal(document.getElementById('editFileModal'));
                    myModal.show();
                } else {
                    showErrorModal('Could Not Open File', response.message);
                }
            }, 'json');
        }

        // Initialize CodeMirror editor
        function initCodeMirror(content, extension) {
            // Clear previous editor
            if (editor) {
                editor.toTextArea();
                editor = null;
            }

            // Clear textarea
            $('#fileEditor').val('');

            // Determine mode
            let mode = getCodeMirrorMode(extension);

            // Get current theme
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const editorTheme = currentTheme === 'dark' ? 'monokai' : 'default';

            // Create editor
            editor = CodeMirror.fromTextArea(document.getElementById('fileEditor'), {
                mode: mode,
                theme: editorTheme,
                lineNumbers: true,
                lineWrapping: false,
                matchBrackets: true,
                autoCloseBrackets: true,
                styleActiveLine: true,
                indentUnit: 4,
                indentWithTabs: false,
                tabSize: 4
            });

            // Update editor theme select to match current theme
            $('#editorTheme').val(currentTheme);

            // Set content and refresh
            editor.setValue(content || '');
            editor.setSize('100%', '500px');

            // Refresh editor (for display issues)
            setTimeout(function() {
                editor.refresh();
            }, 1);

            // Cursor position tracking
            editor.on('cursorActivity', function() {
                let cursor = editor.getCursor();
                $('#editorInfo').text('Line: ' + (cursor.line + 1) + ', Col: ' + (cursor.ch + 1));
            });
        }

        // CodeMirror mode based on file extension
        function getCodeMirrorMode(extension) {
            const modes = {
                'php': 'application/x-httpd-php',
                'html': 'text/html',
                'htm': 'text/html',
                'js': 'text/javascript',
                'json': 'application/json',
                'css': 'text/css',
                'xml': 'text/xml',
                'sql': 'text/x-sql',
                'py': 'text/x-python',
                'java': 'text/x-java',
                'c': 'text/x-csrc',
                'cpp': 'text/x-c++src',
                'cs': 'text/x-csharp',
                'sh': 'text/x-sh',
                'bat': 'text/x-sh',
                'md': 'text/x-markdown',
                'yml': 'text/x-yaml',
                'yaml': 'text/x-yaml'
            };

            return modes[extension] || 'text/plain';
        }

        // Determine file type
        function getFileType(extension) {
            const types = {
                'php': 'PHP',
                'html': 'HTML',
                'htm': 'HTML',
                'js': 'JavaScript',
                'json': 'JSON',
                'css': 'CSS',
                'xml': 'XML',
                'sql': 'SQL',
                'py': 'Python',
                'java': 'Java',
                'c': 'C',
                'cpp': 'C++',
                'cs': 'C#',
                'sh': 'Shell Script',
                'bat': 'Batch',
                'md': 'Markdown',
                'txt': 'Text',
                'log': 'Log',
                'ini': 'Config',
                'env': 'Environment'
            };

            return types[extension] || 'File';
        }

        // Change editor theme
        function changeEditorTheme() {
            if (editor) {
                let theme = $('#editorTheme').val() === 'dark' ? 'monokai' : 'default';
                editor.setOption('theme', theme);
                // Refresh after theme change
                setTimeout(function() {
                    editor.refresh();
                }, 10);
            }
        }

        // Save file
        function saveFile() {
            if (!editor || !currentEditingFile) return;

            let content = editor.getValue();

            showLoading('Saving file...');

            $.post('', {
                action: 'save_file',
                path: currentEditingFile,
                content: content
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showSuccessModal('File Saved',
                        '<i class="bi bi-check-circle"></i> ' + response.message + '<br>' +
                        (response.backup ? '<small class="text-muted">Backup: ' + response.backup + '</small>' : '')
                    );

                    // Refresh file list
                    refreshDirectory();
                } else {
                    showErrorModal('Save Error', response.message);
                }
            }, 'json');
        }

        // Rename file
        function renameFile() {
            if (!currentEditingFile) {
                showAlert('No file is currently being edited', 'Error');
                return;
            }

            let newName = $('#editFileName').val().trim();

            if (!newName) {
                showAlert('Please enter a valid file name', 'Invalid Name');
                return;
            }

            // Get the directory path
            let currentDir = currentEditingFile.substring(0, currentEditingFile.lastIndexOf('\\'));
            let newPath = currentDir + '\\' + newName;

            // Check if the name is actually different
            if (newPath === currentEditingFile) {
                showAlert('The file name has not changed', 'Same Name');
                return;
            }

            showConfirm('Are you sure you want to rename this file to "' + newName + '"?', function(confirmed) {
                if (!confirmed) return;

                showLoading('Renaming file...');

                $.post('', {
                    action: 'rename_file',
                    old_path: currentEditingFile,
                    new_path: newPath
                }, function(response) {
                    hideLoading();

                    if (response.success) {
                        // Update current editing file path
                        currentEditingFile = response.new_path;

                        // Update the display
                        $('#editFilePath').text(response.new_name);
                        $('#editFileName').val(response.new_name);

                        showSuccessModal('File Renamed',
                            '<i class="bi bi-check-circle"></i> ' + response.message + '<br>' +
                            '<small>New name: <strong>' + response.new_name + '</strong></small>'
                        );

                        // Refresh file list
                        refreshDirectory();
                    } else {
                        showErrorModal('Rename Error', response.message);
                    }
                }, 'json');
            }, 'Rename File');
        }

        // Save and close
        function saveAndClose() {
            if (!editor || !currentEditingFile) return;

            let content = editor.getValue();

            showLoading('Saving file...');

            $.post('', {
                action: 'save_file',
                path: currentEditingFile,
                content: content
            }, function(response) {
                hideLoading();

                if (response.success) {
                    // Close modal
                    var myModal = bootstrap.Modal.getInstance(document.getElementById('editFileModal'));
                    myModal.hide();

                    showSuccessModal('File Saved',
                        '<i class="bi bi-check-circle"></i> ' + response.message
                    );

                    // Refresh file list
                    refreshDirectory();

                    // Clear editor
                    if (editor) {
                        editor.toTextArea();
                        editor = null;
                    }
                    currentEditingFile = null;
                } else {
                    showErrorModal('Save Error', response.message);
                }
            }, 'json');
        }

        // Clear editor when modal is closed
        $('#editFileModal').on('hidden.bs.modal', function() {
            if (editor) {
                editor.toTextArea();
                editor = null;
            }
            currentEditingFile = null;
            // Clear event listeners
            $('#editFileModal').off('shown.bs.modal');
        });

        // Show/hide loading overlay
        function showLoading(text) {
            if (text) {
                $('#loadingOverlay .loading-text').text(text);
            } else {
                $('#loadingOverlay .loading-text').text('Processing...');
            }
            $('#loadingOverlay').addClass('show');
        }

        function hideLoading() {
            $('#loadingOverlay').removeClass('show');
        }
    </script>
</body>
</html>