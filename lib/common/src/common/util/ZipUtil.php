<?php
namespace common\util;

use common\util\FileIO;

class ZipUtil {
    /**
     * Add files and sub-directories in a folder to zip file.
     *
     * @param string $folder
     * @param ZipArchive $zipFile
     * @param string $localDir = null
     */
    private static function folderToZip(string $folder, \ZipArchive &$zipFile, string $localDir = null):void {
        $queue = [$folder];
        while (count($queue) > 0) {
            $dir = array_shift($queue);
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f == '.' || $f == '..') continue;
                $filePath = "$dir/$f";
                // Remove prefix from file path before add to zip.
                $localPath = FileIO::getRelativePath($filePath, $folder);
                if ($localDir !== null)
                    $localPath = $localDir . "/" . $localPath;

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    array_push($queue, $filePath);
                }
            }
        }
    }

    /**
     * Zip a folder (include itself).
     *
     * Usage:
     *   ZipUtil::zipDir('/path/to/sourceDir', '/path/to/out.zip');
     *
     * @param string $sourcePath Path of directory to be zip.
     * @param string $outZipPath Path of output zip file.
     */
    public static function zip(string $sourcePath, string $outZipPath):void {
        if (file_exists($outZipPath)) unlink($outZipPath);
        $z = new \ZipArchive();
        $z->open($outZipPath, \ZipArchive::CREATE);
        self::folderToZip($sourcePath, $z);
        $z->close();
    }

    public static function zipAll(array $sources, string $outZipPath): void {
        if (file_exists($outZipPath)) unlink($outZipPath);
        $z = new \ZipArchive();
        $z->open($outZipPath, \ZipArchive::CREATE);
        foreach ($sources as $k => $v) {
            if (is_dir($k)) {
                self::folderToZip($k, $z, $v);
            } else {
                $z->addFile($k, $v);
            }
        }
        $z->close();
    }

    public static function unzip(string $sourceZipPath, string $outPath) {
        $z = new \ZipArchive();
        $z->open($sourceZipPath, \ZipArchive::CREATE);
        $z->extractTo($outPath);
        $z->close();
    }
}
?>