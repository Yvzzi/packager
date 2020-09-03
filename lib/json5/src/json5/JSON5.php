<?php
namespace json5;

use json5\JSON5ParseException;

class JSON5 {
    public const JSON = 0;
    public const JSON_PRETTY = 1;
    public const JSON5 = 2;
    public const JSON5_PRETTY = 3;

    public static function parse(string $contents, int $mode = self::JSON5): array {
        if ($mode === self::JSON5) {
            return self::parseJson5($contents);
        } else {
            $obj = json_decode($contents, true);
            return self::jsonToJson5($obj);
        }
    }

    public static function stringify(array $obj, int $mode = self::JSON5_PRETTY): string {
        if ($mode === self::JSON5 || $mode === self::JSON5_PRETTY) {
            $deep = $mode === self::JSON5_PRETTY ? 0 : -1;
            self::json5ToJson($obj);
            return self::sprintJson5($obj, $deep);
        } else {
            return json_encode($obj, $mode === self::JSON_PRETTY ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : 0);
        }
    }

    public static function sprintJson5(array &$obj, int $deep = 0): string {
        $keys = array_keys($obj);
        $numericalIndex = false;
        if (array_keys($keys) === $keys) $numericalIndex = true;

        $indent = str_repeat(" ", $deep === -1 ? 0 : ($deep + 1) * 4);
        $buf = "";
        if ($numericalIndex) $buf .= "[\n";
        else $buf .= "{\n";
        foreach ($obj as $key => $value) {
            if (strpos($key, "\$comment") === 0) {
                if (strpos($value, "\n") !== false) {
                    $values = explode("\n", $value);

                    $buf .= $indent . "/**\n";
                    foreach ($values as $v) {
                        $buf .= $indent . " * {$v}\n";
                    }
                    $buf .= $indent . " */\n";
                } else {
                    $buf .= $indent . "// {$value}\n";
                }
            } else {
                if (is_string($value)) $value = "\"{$value}\"";
                if (is_null($value)) $value = "null";
                if (!preg_match("#^[A-Za-z_][\w\\.]*$#", $key)) {
                    $key = "\"" . str_replace("\"", "\\\"", $key) . "\"";
                }

                if (is_array($value)) {
                    if ($numericalIndex)
                        $buf .= $indent . self::sprintJson5($value, $deep === -1 ? -1 : $deep + 1);
                    else $buf .= $indent . "{$key}: " . self::sprintJson5($value, $deep === -1 ? -1 : $deep + 1);
                } else {
                    if ($numericalIndex)
                        $buf .= $indent . "{$value}";
                    else $buf .= $indent . "{$key}: {$value}";
                }
                $buf .= ",\n";
            }
        }
        if (strrpos($buf, ",\n") === strlen($buf) - 2) $buf = substr($buf, 0, strlen($buf) - 2);
        $lastIndex = str_repeat(" ", $deep * 4);
        if ($numericalIndex) $buf .= "\n{$lastIndex}]";
        else $buf .= "\n{$lastIndex}}";
        return $buf;
    }

    public static function json5ToJson(array &$obj): void {
        foreach ($obj as $key => $value) {
            if (is_double($value) && is_infinite($value)) {
                if ($value < 0) $obj[$key] = "-Infinity";
                elseif ($value > 0) $obj[$key] = "Infinity";
            }
            if (is_float($value) && is_nan($value)) $obj[$key] = "NaN";
            if (is_array($value)) {
                self::json5ToJson($obj[$key]);
            }
        }
    }

    public static function jsonToJson5(array &$obj): void {
        foreach ($obj as $key => $value) {
            if (is_string($value)) {
                if ($value === "Infinity") $value = INF;
                if ($value === "-Infinity") $value = -INF;
                if ($value === "NaN") $value = NAN;
            }
            if (is_array($value)) {
                self::json5ToJson($obj[$key]);
            }
        }
    }

    public static function parseJson5(string $contents) {
        $contents = trim($contents);
        $contents = str_replace("\r\n", "\n", $contents);
        $contents = preg_replace("#\"\s*\\\\\n\s*\"#", "\\n", $contents);
        $contents = preg_replace("#'\s*\\\\\n\s*'#", "\\n", $contents);
        $contents = str_replace("\\\n", "\\n", $contents);

        return self::parseNest($contents, 0, strlen($contents) - 1);
    }

    protected static function parseNest(string $contents, int $start, int $end): array {
        $obj = [];
        $contents = trim($contents);

        $i = $start;
        $char = substr($contents, $i, 1);
        if ($char !== "{" && $char !== "[")
            throw new JSON5ParseException("Unexpected {$char} at {$i}");
        $i = $end;
        $char = substr($contents, $i, 1);
        if ($char !== "}" && $char !== "]")
            throw new JSON5ParseException("Unexpected {$char} at {$i}");
        if ($char === "}") {
            $isArray = false;
        } else {
            $isArray = true;
        }

        $lastI = -1;
        for ($i = $start + 1; $i < $end;) {
            $char = substr($contents, $i, 1);
            if ($i === $lastI) {
                throw new JSON5ParseException("Unexpected {$char} at {$i}");
            } else {
                $lastI = $i;
            }
            if (preg_match("#\s#", $char)) {
                $i++;
                continue;
            }
            // short comment
            if ($char == "/" && substr($contents, $i + 1, 1) === "/") {
                while ($char !== "\n") {
                    $i++;
                    $char = substr($contents, $i, 1);
                }
                $i++;
                continue;
            }
            // long comment
            if ($char == "/" && substr($contents, $i + 1, 1) === "*") {
                $token = "/*";
                while ($token !== "*/") {
                    $i += 2;
                    $token = substr($contents, $i, 2);
                    if (substr($token, 1) === "*") $i--;
                }
                $i += 2;
                continue;
            }

            if (!$isArray) {
                // key
                $isChain = false;
                if ($char === "\"" || $char === "'" || preg_match("#\w#", $char)) {
                    $key = "";
                    while ($char !== ":" && !preg_match("#\s#", $char)) {
                        $key .= $char;
                        $i++;
                        $char = substr($contents, $i, 1);
                    }

                    if (strpos($key, "\"") === 0) {
                        $key = substr($key, 1, strlen($key) - 2);
                        $key = str_replace("\\\"", "\"", $key);
                    }
                    if (strpos($key, "'") === 0) {
                        $key = substr($key, 1, strlen($key) - 2);
                        $key = str_replace("\\'", "'", $key);
                    }

                    if (strpos($key, ".") !== false) $isChain = true;
                    // if (!$isChain && !preg_match("#^\w+$#", $key))
                    //     throw new JSON5ParseException("Invalid identitifier {$key} at {$i}");
                    if ($char !== ":") {
                        while ($char !== ":") {
                            $i++;
                            $char = substr($contents, $i, 1);
                        }
                    }
                    $i++;
                    $char = substr($contents, $i, 1);
                    while (preg_match("#\s#", $char)) {
                        $i++;
                        $char = substr($contents, $i, 1);
                    }
                    $value = "";
                    if ($char === "{") {
                        $walker = new StringWalker($contents, $i);
                        $endIndex = $walker->matchPair("{", "}");
                        $value = self::parseNest($contents, $i, $endIndex);
                        $i = $endIndex + 1;
                        $char = substr($contents, $i, 1);
                    } elseif ($char === "[") {
                        $walker = new StringWalker($contents, $i);
                        $endIndex = $walker->matchPair("[", "]");
                        $value = self::parseNest($contents, $i, $endIndex);
                        $i = $endIndex + 1;
                        $char = substr($contents, $i, 1);
                    } else {
                        if ($char === "\"" || $char === "'" || $char === "r") {
                            $unEscape = false;
                            if ($char === "r") {
                                $unEscape = true;
                                $i++;
                                $char = substr($contents, $i, 1);
                            }
                            $walker = new StringWalker($contents, $i);
                            $endIndex = $walker->matchString();
                            $value = substr($contents, $i + 1, $endIndex - $i - 1);
                            $value = str_replace("\\{$char}", $char, $value);
                            if ($unEscape)
                                $value = preg_replace("/\\\\([^u])/", "\\\\\\\\$1", $value);
                            $value = json_decode("[\"{$value}\"]", true)[0];
                            $i = $endIndex + 1;
                            $char = substr($contents, $i, 1);
                        } else {
                            while (preg_match("#[^,\s]#", $char)) {
                                $value .= $char;
                                $i++;
                                $char = substr($contents, $i, 1);
                            }
                            if ($value === "-Infinity") {
                                $value = -INF;
                            } elseif ($value === "Infinity") {
                                $value = INF;
                            } elseif ($value === "NaN") {
                                $value = NAN;
                            } elseif ($value === "null") {
                                $value = null;
                            } elseif ($value === "false") {
                                $value = false;
                            } elseif ($value === "true") {
                                $value = true;
                            } elseif (preg_match("#^\\.[\\d_]+$#", $value)) {
                                $value = str_replace("_", "", $value);
                                $value = floatval("0.{$value}");
                            } elseif (preg_match("#^[\\d_]+\\.$#", $value)) {
                                $value = str_replace("_", "", $value);
                                $value = floatval("{$value}.0");
                            } elseif (preg_match("#^0x([A-F0-9a-f_]{2})+$#", $value)) {
                                $value = str_replace("_", "", $value);
                                $value = hexdec(substr($value, 2));
                            } elseif (preg_match("#^0b[0-1_]+$#", $value)) {
                                $value = str_replace("_", "", $value);
                                $value = bindec(substr($value, 2));
                            } else {
                                $value = str_replace("_", "", $value);
                                $value = intval($value);
                            }
                        }
                    }
                    if ($isChain) {
                        self::setChain($obj, $key, $value);
                    } else {
                        $obj[$key] = $value;
                    }
                    while ($char !== "," && $char !== "}") {
                        $i++;
                        $char = substr($contents, $i, 1);
                    }
                    if ($char === ",") $i++;
                    if ($char === "}") break;
                }
            } else {
                $value = "";
                if ($char === "{") {
                    $walker = new StringWalker($contents, $i);
                    $endIndex = $walker->matchPair("{", "}");
                    $value = self::parseNest($contents, $i, $endIndex);
                    $i = $endIndex + 1;
                    $char = substr($contents, $i, 1);
                } elseif ($char === "[") {
                    $walker = new StringWalker($contents, $i);
                    $endIndex = $walker->matchPair("[", "]");
                    $value = self::parseNest($contents, $i, $endIndex);
                    $i = $endIndex + 1;
                    $char = substr($contents, $i, 1);
                } else {
                    if ($char === "\"" || $char === "'" || $char === "r") {
                        $unEscape = false;
                        if ($char === "r") {
                            $unEscape = true;
                            $i++;
                            $char = substr($contents, $i, 1);
                        }
                        $walker = new StringWalker($contents, $i);
                        $endIndex = $walker->matchString();
                        $value = substr($contents, $i + 1, $endIndex - $i - 1);
                        $value = str_replace("\\{$char}", $char, $value);
                        if ($unEscape)
                            $value = preg_replace("/\\\\([^u])/", "\\\\\\\\$1", $value);
                        $value = json_decode("[\"{$value}\"]", true)[0];
                        $i = $endIndex + 1;
                        $char = substr($contents, $i, 1);
                    } else {
                        while (preg_match("#[^,\s]#", $char)) {
                            $value .= $char;
                            $i++;
                            $char = substr($contents, $i, 1);
                        }

                        if ($value === "-Infinity") {
                            $value = -INF;
                        } elseif ($value === "Infinity") {
                            $value = INF;
                        } elseif ($value === "null") {
                            $value = null;
                        } elseif ($value === "false") {
                            $value = false;
                        } elseif ($value === "true") {
                            $value = true;
                        } elseif (preg_match("#^\\.[\\d_]+$#", $value)) {
                            $value = str_replace("_", "", $value);
                            $value = floatval("0.{$value}");
                        } elseif (preg_match("#^[\\d_]+\\.$#", $value)) {
                            $value = str_replace("_", "", $value);
                            $value = floatval("{$value}.0");
                        } elseif (preg_match("#^0x([A-F0-9a-f_]{2})+$#", $value)) {
                            $value = str_replace("_", "", $value);
                            $value = hexdec(substr($value, 2));
                        } elseif (preg_match("#^0b[0-1_]+$#", $value)) {
                            $value = str_replace("_", "", $value);
                            $value = bindec(substr($value, 2));
                        } else {
                            $value = str_replace("_", "", $value);
                            $value = intval($value);
                        }
                    }
                }
                array_push($obj, $value);
                while ($char !== "," && $char !== "]") {
                    $i++;
                    $char = substr($contents, $i, 1);
                }
                if ($char === ",") $i++;
                if ($char === "]") break;
            }
        }
        return $obj;
    }

    private static function setChain(array &$array, string $key, $value): void {
        $keys = explode(".", $key);
        $len = count($keys);
        $handle = &$array;
        for ($i = 0; $i < $len - 1; $i++) {
            if (!isset($handle[$keys[$i]]))
                $handle[$keys[$i]] = [];
            $handle = &$handle[$keys[$i]];
        }
        $handle[$keys[$i]] = $value;
    }
}

// $contents = <<<EOF
// {
//     'a': {
//        's': Infinity,
//        b : 555
//     },
//     "a.d": 666,
//     b: "assa"\
//        "sasasas",
//     'b': [
//            2, // asas
//            3, "ss\"'s"
//     ],
//     "d": 666
// }
// EOF;
// $a = <<< EOF
// [
//     'a': {
//         's': Infinity,
//         b : 555
//      },
//      666,
//      // 连续字符串
//      "assa"\
//      "sasasas",
//      [
//         2, // asas
//         3, "ss\"'s"
//      ],
//      "d"
// ]
// EOF;
// require_once __DIR__ . "/JSON5ParseException.php";
// require_once __DIR__ . "/StringWalker.php";
// $obj = JSON5::parse($a);
// var_dump($obj);
// $str = JSON5::stringify($obj);
// echo $str . "\n";
// $str = JSON5::stringify([
//     "s" => "sasas",
//     "\$comment" => "ok!!!\nI will help you"
// ]);
// echo $str . "\n";