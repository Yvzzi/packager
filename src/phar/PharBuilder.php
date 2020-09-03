<?php
namespace phar;

use common\util\ZipUtil;
use common\util\FileIO;

class PharBuilder {
    private $target;
    private $ignore;
    private $stub = null;

    public function __construct(string $target, array $ignore = []) {
        $this->target = $target;
        $this->ignore = $ignore;
    }

    public static function unphar(string $phar, string $output, bool $isZip):void {
        $phar = new \Phar($phar);
        if ($isZip) {
            $tmp = tempnam(sys_get_temp_dir(), "Tmp");
            unlink($tmp);
            mkdir($tmp, 0755, true);
            $phar->extractTo($tmp);

            if (file_exists($output)) unlink($output);
            ZipUtil::zipDir($tmp, $output);
            FileIO::delPath($tmp);
        } else {
            if (!is_dir($output)) mkdir($output, 0755, true);
            $phar->extractTo($output);
        }
    }

    private function folderToPhar(string $folder, \Phar &$phar, string $localDir = ""):void {
        $folder = FileIO::getAbsolutePath($folder);
        $queue = [$folder];
        while (count($queue) > 0) {
            $dir = array_shift($queue);
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f == '.' || $f == '..') continue;
                if ($f == '.git') continue;
                $filePath = "$dir/$f";
                $localPath = FileIO::getRelativePath($filePath, $folder);

                $flag = false;
                foreach ($this->ignore as $e) {
                    $e = str_replace("/", "\\/", $e);
                    if (preg_match('/' . $e . '/', $localPath) !== 0) {
                        $flag = true;
                        break;
                    }
                }
                if ($flag) continue;
                // echo $localPath . PHP_EOL;
                // Remove prefix from file path before add to phar.
                if ($localDir !== null)
                    $localPath = $localDir . "/" . $localPath;

                if (is_file($filePath)) {
                    $phar->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $phar->addEmptyDir($localPath);
                    array_push($queue, $filePath);
                }
            }
        }
    }
    
    public function setDefaultStub(string $cliIndex, string $webIndex):void {
        $this->stub = \Phar::createDefaultStub($cliIndex, $webIndex);
    }
    
    public function setStub(string $stub):void {
        $this->stub = $stub;
    }
    
    public function build(string $output):void {
        if (file_exists($output)) unlink($output);
        $phar = new \Phar($output);
        
        $phar->startBuffering();
        $this->folderToPhar($this->target, $phar);

        if ($this->stub !== null) {
            $phar->setStub($this->stub);
        }
        $phar->stopBuffering();
    }
}
?>