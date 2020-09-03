<?php
namespace common\util;

class Util {
	/**
	 * Json Indented format
	 * @param array | object $json
	 * @return string
	 */
	public static function jsonIndentFormat($jsonStr):string {
		$result = '';
		$indentCount = 0;
		$strLen = strlen($jsonStr);
		$indentStr = '    ';
		$newLine = "\n";
		$isInQuotes = false;
		$prevChar = '';
		for($i = 0; $i <= $strLen; $i++) {
			$char = substr($jsonStr, $i, 1);

			if($isInQuotes){
				$result .= $char;
				if(($char=='"' && $prevChar!='\\')){
					$isInQuotes = false;
				}
			}
			else{
				if(($char=='"' && $prevChar!='\\')){
					$isInQuotes = true;
					if ($prevChar != ':'){
						$result .= $newLine;
						for($j = 0; $j < $indentCount; $j++) {
							$result .= $indentStr;
						}
					}
					$result .= $char;
				}
				elseif(($char=='{' || $char=='[')){
					if ($prevChar != ':'){
						$result .= $newLine;
						for($j = 0; $j < $indentCount; $j++) {
							$result .= $indentStr;
						}
					}
					$result .= $char;
					$indentCount = $indentCount + 1;
				}
				elseif(($char=='}' || $char==']')){
					$indentCount = $indentCount - 1;
					$result .= $newLine;
					for($j = 0; $j < $indentCount; $j++) {
						$result .= $indentStr;
					}
					$result .= $char;
				}
				else{
					$result .= $char;
				}
			}
			$prevChar = $char;
		}
		return $result;
	}

	public static function isLinuxOS(): bool {
		return strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN';
	}

	public static function unicodeEncode(string $str, bool $htmlMode = false): string {
		$prefix = "\\u";
		if ($htmlMode)
			$prefix = "&#";
		// split word
		preg_match_all('/./u', $str, $matches);

		$unicodeStr = "";
		foreach($matches[0] as $m){
			$unicodeStr .= $prefix.base_convert(bin2hex(iconv('UTF-8', "UCS-4", $m)), 16, 10);
		}
		return $unicodeStr;
	}

    public static function unicodeDecode(string $str, bool $htmlMode = false): string {
		if ($htmlMode)
			$str = str_replace("&#", "\\u", $str);
        $str = str_replace('"', '\"', $str);
        $str = str_replace("'", "\'", $str);
        $json = '{"str":"'.$str.'"}';
        $arr = json_decode($json, true);
        if (empty($arr)) return '';
        return $arr['str'];
	}

    public static function getProtocol():string {
        return (!empty($_SERVER["HTTPS"] ?? "") && $_SERVER["HTTPS"] != "off") ? "https" : "http";
    }

	public static function getDomain():string {
		return $_SERVER["HTTP_HOST"] . ($_SERVER["SERVER_PORT"] == 80 ? '' : ':' . $_SERVER["SERVER_PORT"]);
	}

	public static function getQuery():array {
		$ret = [];
		parse_str($_SERVER["QUERY_STRING"], $ret);
		return $ret;
	}

	public static function buildQuery($arr):string {
		return http_build_query($arr);
	}

	public static function destory(&$var):void {
		if (is_array($var)) {
			foreach ($var as $k => $_) {
				unset($var[$k]);
			}
			array_slice($var, 0, null, true);
		}
	}
}
?>