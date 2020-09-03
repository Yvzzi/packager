<?php
namespace common\util;

use common\exception\UnexpectedException;
use common\exception\FileNotExistException;

class FileIO {
	public const OVERWRITE = "w+";
	public const READ = "r+";
	public const APPEND = "a+";

	/** @var resource */
	private $file = null;
	private $path;

	function __construct(string $path, string $mode = self::OVERWRITE, string $default = null) {
        $path = self::getFixedPath($path);
		$dir = dirname($path);
		if (!is_dir($dir))
			mkdir($dir, 0777 ,true);
		if (!is_file($path)) {
            $this->file = fopen($path, "w+");
            if ($default !== null)
                $this->rewrite($default);
            $this->close();
		}
        $this->file = fopen($path, $mode);
		$this->path = $path;
	}

	public function rewrite(string $data): void {
		$this->write($data, 0, true);
	}

	/**
     * Get file Lock for multiply users
     * #Bug Cannot fwrite("0", 0, true)
     */
	public function write(string $data, int $offset = 0, bool $clear = true): void {
		if ($clear) $this->clear();
		if (empty($data)) return;
		fseek($this->file, $offset);
		$starttime = microtime();
		do {
			$canWrite = flock($this->file, LOCK_EX);
			if (!$canWrite) usleep(round(rand(0, 100) * 1000));
		} while ((!$canWrite) && (microtime() - $starttime) < 1000);
		if ($canWrite) {
			if (!fwrite($this->file, $data))
				throw new UnexpectedException("Fails to write to file with ". $data);
			flock($this->file, LOCK_UN);
		}
	}

	/**
	 * Get file Lock for multiply users
	 */
	public function read(): string {
		fseek($this->file, 0);
		$starttime = microtime();
		do {
			$canRead = flock($this->file, LOCK_SH);
			if (!$canRead) usleep(round(rand(0, 100) * 1000));
		} while ((!$canRead) && (microtime() - $starttime) < 1000);
		if ($canRead) {
			$filestr = "";
			while (!feof($this->file)) {
				$filestr .= fgets($this->file);
			}
		} else {
			throw new UnexpectedException("Fails to read to file with " . $this->path);
		}
		return $filestr;
	}

    public static function getBasename(string $path): string {
        return basename(self::getFixedPath($path));
    }

    public static function getDirname(string $path): string {
        return dirname(self::getFixedPath($path));
    }

    public static function getCharsetFixedPath(string $path): string {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            return iconv("utf-8", "gbk", $path);
        return $path;
    }

    public static function getFixedPath(string $path, bool $fixLast = true): string {
        $path = str_replace("\\", "/", $path);
        $path = str_replace("/./", "/", $path);
        $path = preg_replace('/\\/\\.$/', "", $path);
        $path = preg_replace('/^\\.\\//', "", $path);
        $path = preg_replace('/[\\/]{2,}/', '/', $path);
        if ($fixLast)
            return preg_replace('/\\/$/', "", $path);
        return $path;
    }

    public static function isRootPath(string $path, string $workDirecory = null): bool {
        $path = self::getAbsolutePath($path, $workDirecory);
        return preg_match("/^(\/|[A-Za-z]:)$/", $path) === 1;
    }

    public static function getAbsolutePath(string $path, string $workDirectory = null): string {
        if (preg_match('/^(\/|[A-Za-z]:)/', $path) === 0) {
            if ($workDirectory == null)
                $workDirectory = getcwd();
            $path = $workDirectory . "/" . $path;
        }
        $path = self::getFixedPath($path);
        $parts = explode("/", $path);
        $newParts = [];
        $len = count($parts);
        $i = 0;

        while ($i < $len) {
            if ($parts[$i] == "..") {
                if (count($newParts) === 0)
                    throw new UnexpectedException("There is no parent path of root");
                array_pop($newParts);
            } else {
                array_push($newParts, $parts[$i]);
            }
            $i++;
        }
        $absPath = implode("/", $newParts);
        if ($absPath === "") $absPath = "/";
        return $absPath;
    }

	public static function getRelativePath(string $subject, string $comparator, string $workDirectory = null): string {
        $subject = self::getAbsolutePath($subject, $workDirectory);
        $comparator = self::getAbsolutePath($comparator, $workDirectory);
        $subjectNodes = explode("/", $subject);
        $comparatorNodes = explode("/", $comparator);
        $intersectNodes = array_intersect($subjectNodes, $comparatorNodes);
        $buf = "";
        $comparatorCount = count($comparatorNodes);
        $intersectCount = count($intersectNodes);
        if ($comparatorCount !== $intersectCount)
            $buf .= str_repeat("../", $comparatorCount - $intersectCount);
        $buf .= implode("/", array_diff($subjectNodes, $intersectNodes));
        $pos = strpos($buf, ":");
        if ($pos !== false && $pos !== 1)
            throw new UnexpectedException("No such relative path");
        return $buf;
	}

	/**
	 *  Create Path if it dosen't exist
	 *
	 *  @param string $path
	 *  @param bool $dirMode = false
	 */
	public static function create(string $path, bool $dirMode = false): void {
        $path = self::getFixedPath($path);
		if ($dirMode) {
			if (!is_dir($path))
				mkdir($path, 0755, true);
		} else {
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0755, true);
            if (!is_file($path)) {
                $file = fopen($path, "w+");
                fclose($file);
            }
        }
	}

	/**
	 *  Del Path if it exists
	 *
	 *  @param string $path
	 */
	public static function del(string $path): void {
        $path = self::getFixedPath($path);
        if (!file_exists($path)) return;
		if (is_dir($path)) {
            $queue = [$path];
            $rmQueue = [];
            while (count($queue) > 0) {
                $dir = array_shift($queue);
                $p = scandir($dir);
                foreach ($p as $val) {
                    if ($val == "." || $val == "..")
                        continue;
                    $childPath = $dir . "/" . $val;
                    if (is_dir($childPath)) {
                        array_push($queue, $childPath);
                    } else {
                        unlink($childPath);
                    }
                }
                array_push($rmQueue, $dir);
            }
            while (count($rmQueue) > 0) {
                $dir = array_pop($rmQueue);
                rmdir($dir);
            }
		} elseif (is_file($path)) {
			unlink($path);
		} else {
			throw new FileNotExistException($path);
		}
	}

	/**
	 *  Mv Path to new Path
	 *
	 *  @param string $path
	 *  @param string $newPath
	 *  @param bool $dirMode = true
	 */
	public static function move(string $path, string $newPath, bool $dirMode = true):void {
        $path = self::getFixedPath($path);
        $newPath = self::getFixedPath($newPath);

		if (is_dir($path)) {
            $prefix = "";
            if ($dirMode) {
                $prefix = basename($path);
                if (!is_dir($newPath . "/" . $prefix))
                    mkdir($newPath, 0755, true);
            } else {
                if (!is_dir($newPath))
                    mkdir($newPath, 0755, true);
            }

            $queue = [$path];
            $rmQueue = [];
            while (count($queue) > 0) {
                $dir = array_shift($queue);
                $p = scandir($dir);
                foreach ($p as $val) {
                    if ($val == "." || $val == "..") continue;
                    $childPath = $dir . "/" . $val;
                    $relative = self::getRelativePath($childPath, $path);
                    if (is_dir($childPath)) {
                        if ($dirMode) {
                            if (!file_exists($newPath . "/" . $prefix . "/" . $relative))
                                mkdir($newPath . "/" . $prefix . "/" . $relative, 0755, true);
                        } else {
                            if (!file_exists($newPath . "/" . $relative))
                                mkdir($newPath . "/" . $relative, 0755, true);
                        }
                        array_push($queue, $childPath);
                    } else {
                        if ($dirMode) {
                            rename($childPath, $newPath . "/" . $prefix . "/" . $relative);
                        } else {
                            rename($childPath, $newPath . "/" . $relative);
                        }
                    }
                }
                array_push($rmQueue, $dir);
            }
            while (count($rmQueue) > 0) {
                $dir = array_pop($rmQueue);
                rmdir($dir);
            }
		} elseif (is_file($path)) {
            if ($dirMode) {
                if (!is_dir($newPath))
                    mkdir($newPath, 0755, true);
                rename($path, $newPath);
            } else {
                $dir = basename($newPath);
                if (!is_dir($dir))
                    mkdir($dir, 0755, true);
                rename($path, $newPath);
            }
		} else {
			throw new FileNotExistException($path);
		}
	}

	public function clear() {
		rewind($this->file);
		ftruncate($this->file, 0);
	}

	public function close() {
		fclose($this->file);
    }

    public function __destruct() {
        $this->close();
    }

	public function getPath():string {
		return $this->path;
	}
}