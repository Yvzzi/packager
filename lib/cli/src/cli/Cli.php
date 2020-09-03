<?php
namespace cli;

use cli\TextFormat;
use cli\CliOption;

class Cli {
    public static function tell(string $msg = "", string $color = TextFormat::PURPLE) {
        fwrite(STDOUT, $color . $msg . "\n");
    }

    public static function ask(string $msg, string $colorInput = TextFormat::YELLOW, string $colorOutput = TextFormat::CYAN) {
        fwrite(STDOUT, "{$colorInput}? " . $msg . ":\n{$colorOutput}=> ");
        return trim(fgets(STDIN));
    }

    public static function select(string $msg, array $options, string $colorAsk = TextFormat::YELLOW, string $colorAnswer = TextFormat::CYAN): int {
        while (true) {
            $len = count($options);
            for ($i = 0; $i < $len; $i++) {
                self::tell("[{$i}] {$options[$i]}", $colorAsk) . "\n";
            }
            $ret = self::ask($msg, $colorAsk, $colorAnswer);
            if (!is_numeric($ret)) continue;
            if ($ret < $len || $ret >= 0)
                return $ret;
        }
    }

    public static function option(string $msg, array $options, string $colorAsk = TextFormat::YELLOW, string $colorAnswer = TextFormat::CYAN) {
        $optionStr = "";
        foreach ($options as $key => $value) {
            $optionStr .= "{$key}: {$value}, ";
        }
        $optionStr = substr($optionStr, 0, strlen($optionStr) - 2);
        $optionValues = array_values($options);
        while (true) {
            $ret = self::ask($msg . " (" . $optionStr . ")", $colorAsk, $colorAnswer);
            if (in_array($ret, $optionValues)) {
                return $ret;
            }
        }
    }
    
    public static function whether(string $msg, string $colorAsk = TextFormat::YELLOW, string $colorAnswer = TextFormat::CYAN): bool {
        $optionStr = "Y/n";
        while (true) {
            $ret = strtolower(self::ask($msg . " (" . $optionStr . ")", $colorAsk, $colorAnswer));
            if ($ret === "y") return true;
            if ($ret === "n") return false;
        }
    }
    
    public static function whetherOrExit(string $msg, string $colorAsk = TextFormat::YELLOW, string $colorAnswer = TextFormat::CYAN): void {
        if (self::whether($msg, $colorAsk, $colorAnswer)) {
            return;
        } else {
            exit;
        }
    }

    public static function exit(string $msg = ""): void {
        echo self::tell($msg, TextFormat::RED);
        exit(127);
    }

    public static function showTable(array $table, array $widths, int $spaceNum = 0): void {
        $row = 0;
        $maxRow = count($table);
        $maxCol = count($table[0]);
        while ($row < $maxRow) {
            $flag = true;
            for ($col = 0; $col < $maxCol; $col++) {
                if (strlen($table[$row][$col]) <= $widths[$col]) {
                    echo str_pad($table[$row][$col], $widths[$col]);
                    $table[$row][$col] = "";
                } else {
                    [$str, $count] = self::subWord($table[$row][$col], 0, $widths[$col]);
                    echo $str;
                    $table[$row][$col] = substr($table[$row][$col], $count);
                }
                if ($col !== $maxCol - 1)
                    echo str_repeat(" ", $spaceNum);
            }
            for ($col = 0; $col < $maxCol; $col++) {
                if (!empty($table[$row][$col])) $flag = false;
            }
            if ($flag) {
                $row++;
            }
            echo "\n";
        }
    }

    public static function showTableWithMaxLen(array $table, int $maxLen, int $sapceNum = 1): void {
        $widths = [];
        $maxCol = count($table[0]);
        for ($i = 0; $i < $maxCol - 1; $i++) {
            $widths[$i] = max(
                array_map(function ($value) {
                    return strlen($value);
                }, array_column($table, $i))
            );
            $maxLen -= $widths[$i];
            if ($maxLen < 0) {
                throw new \Exception("Max Length is too small");
            }
        }
        $widths[$maxCol - 1] = $maxLen;
        self::showTable($table, $widths, $sapceNum);
    }

    public static function getCliOption(array $args, array $options): CliOption {
        $posOpts = [];
        $opts = [];
        $optType = [];
        foreach ($options as $it) {
            if (strpos($it, "?") === strlen($it) - 1) {
                $it = substr($it, 0, strlen($it) - 1);
                $optType[$it] = 2;
            } elseif (strpos($it, ":") === strlen($it) - 1) {
                $it = substr($it, 0, strlen($it) - 1);
                $optType[$it] = 1;
            } else {
                $optType[$it] = 0;
            }
            $opts[$it] = false;
        }
        while (count($args) > 0) {
            $arg = array_shift($args);
            if ($arg === "--")
                throw new \Exception("Invalid Arguments");
            if (strpos($arg, "--") === 0) {
                $arg = substr($arg, 2);
                $opt = $arg;
                $optArg = null;
                $index = strpos($arg, "=");
                if ($index !== false) {
                    $opt = substr($arg, 0, $index);
                    $optArg = substr($arg, $index + 1);
                }
                // var_dump($optType);
                // var_dump($opt, $optArg);
                if (!isset($optType[$opt]))
                    continue;
                if ($optType[$opt] === 1) {
                    $opts[$opt] = self::fetchNextOptArg($args);
                } elseif ($optType[$opt] === 2) {
                    $opts[$opt] = $optArg;
                } else {
                    $opts[$opt] = true;
                }
            } elseif (strpos($arg, "-") === 0) {
                $arg = substr($arg, 1);
                $char = substr($arg, 0, 1);
                if (!isset($optType[$char]))
                    continue;
                if ($optType[$char] === 2) {
                    $opts[$char] = substr($arg, 1);
                } else {
                    $chars = str_split($arg);
                    foreach ($chars as $char) {
                        if (!isset($optType[$char]))
                            continue;
                        if ($optType[$char] === 1) {
                            $opts[$char] = self::fetchNextOptArg($args);
                        } elseif ($optType[$char] === 0) {
                            $opts[$char] = true;
                        } else {
                            throw new \Exception("Invalid Arguments");
                        }
                    }
                }
            } else {
                array_push($posOpts, $arg);
            }
        }
        return new CliOption($posOpts, $opts);
    }

    private static function fetchNextOptArg(array &$array): string {
        $i = 0;
        $len = count($array);
        $find = -1;
        while ($i < $len) {
            if (strpos($array[$i], "-") === 0 && $array !== "--") {
                $i++;
                continue;
            } elseif ($array === "--") {
                array_splice($array, $i, 1);
                break;
            } else {
                $find = $i;
                break;
           }
        }
        if ($find === -1) {
            self::exit("Invalid Format of Input");
        } else {
            $ret = $array[$i];
            array_splice($array, $i, 1);
            return $ret;
        }
    }

    private static function subWord($words, $start, $count = -1) {
        $len = strlen($words);
        if ($count === -1)
            $count = strlen($words) - $start;
        if ($count > $len) {
            return $words;
        } else {
            $count = intdiv($count, 3);
            $str = mb_substr($words, $start, $count);
            return [$str, strlen($str)];
        }
    }
}