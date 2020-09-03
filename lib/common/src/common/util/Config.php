<?php
namespace common\util;

/**
 *  @file Config.php
 *  @brief A config Util
 *  @author Christy_
 */

use common\util\FileIO;
use common\exception\UnexpectedException;

/**
 *  A config Util
 *  Supported data structure:
 *  	index array, array, object(structure), string map, binary
 *  TODO. add object map
 *  Usage:
 *  	set(namespace, value)
 *  	get(namespace)
 *  	namespace format:
 *  		[]	used for array
 *  		.	used for object
 *  		<>	used for satellite data
 *  	example:
 *  		{"a":[233,244]}	a.b[0] => 233
 *  		[{"a":2235}]	[0].a => 2235
 *  		<a><div a="255"></div></a> a.div<a> => 255
 */
class Config {
	public const DETECT = -1;
	public const YAML = 0;
	public const JSON = 1;
	public const JSON_OBJECT = 1;
	public const JSON_ARRAY = 2;
	public const PROPERTIES = 3;
	public const SERIALIZED = 4;
	public const ENUM = 5;
	public const XML = 6;

	private const NAMESPACE_OBJECT = 0;
	private const NAMESPACE_ARRAY = 1;
	private const NAMESPACE_ATRRIBUTE = 2;

	public static $NIL = null;
	
	private static $ext = [
		["yml", "yaml"],
		["json", "js"],
		["json", "js"],
		["properties", "cnf"],
		["sl", ""],
		["txt", "enum", "list"],
		["xml"]
	];
	
	private $fileIO; // FileIO
	private $type; // int
	private $config = null;

	/**
	 *  @brief Constructor
	 *  
	 *  @param string path 
	 *  @param int type
	 *  @param array source init with source if file dose not exist
	 */
	public function __construct($path, $type = -1, $source = null) {
		if (Config::$NIL == null)
			Config::$NIL = new \StdClass();
		$this->type = $type != -1 ? $type : Config::detectType($path);
		$default = "";
		switch ($this->type) {
			case 1:
				$default = "{}";
				break;
			case 2:
				$default = "[]";
				break;
			case 6:
				$default = "<config></config>";
		}
		$this->fileIO = new FileIO($path, FileIO::OVERWRITE, $default);
		$this->load();
		$this->setAllDefault($source == null ? [] : $source);
	}

	public function __destruct() {
		unset($this->fileIO);
		unset($this->type);
		unset($this->config);
	}
	
	public function getType():int {
		return $this->type;
	}
	
	public static function detectType($path):int {
		$path = basename($path);
		$index = strpos($path, ".");
		if ($index == -1) {
			return Config::SERIALIZED;
		} else {
			$subfix = substr($path, $index + 1);
			foreach (Config::$ext as $k => $v) {
				foreach ($v as $vv) {
					if ($vv == $subfix) {
						if ($subfix == "json") {
							if (!file_exists($path))
								return 1;
							$tmp = trim(file_get_contents($path));
							if (strpos($tmp, "[") == 0) {
								return 2;
							} else {
								return 1;
							}
						}
						return $k;
					}
				}
			}
		}
		throw new \InvalidArgumentException($path." is not a valid config file.");
	}

	public function load():void {
		$str = $this->fileIO->read();
		switch ($this->type) {
			case 0:
				$this->config = yaml_parse($str);
				break;
			case 1:
			case 2:
				$this->config = json_decode($str, true);
				break;
			case 3:
				$this->decodePropertise($str);
				break;
			case 4:
				$this->config = unserialize($str);
				break;
			case 5:
				$this->decodeEnum($str);
				break;
			case 6:
				$this->config = new \DOMDocument();
				$this->loadXML($str);
				break;
		}
		// don't use == , which cause a bug array(0) == null
		if ($this->config === null)
			throw new UnexpectedException("Fails to load config.");
	}

	public function setAllDefault($source):void {
		foreach ($source as $k => $v) {
			if (!$this->has($k)) $this->set($v);
		}
	}

	public function setAll($source):void {
		$this->config = $source;
	}

	public function getAll() {
		return $this->config;
	}

	public function save():void {
		$str = "";
		switch ($this->type) {
			case 0:
				$str = yaml_emit($this->config);
				break;
			case 1:
			case 2:
				$str = json_encode($this->config);
				break;
			case 3:
				$str = $this->encodePropertise($this->config);
				break;
			case 4:
				$str = serialize($this->config);
				break;
			case 5:
				$str = $this->encodeEnum($this->config);
				break;
			case 6:
				$str = $this->config->saveXML();
				break;
		}
		$this->fileIO->rewrite($str);
	}

	public function has($k):bool {
		return $this->get($k) != null;
	}

	public function set($k, $v = true):void {
		$keys = $this->parseKey($k);
		$handle = & $this->config;
		$key = "";
		foreach ($keys["key"] as $i => $key) {
			if (!Config::hasNext($handle, $key, $keys["type"][$i], $this->type));
				Config::createNext($handle, $key, $keys["type"][$i], $this->type);
			$handle = & Config::getNext($handle, $key, $keys["type"][$i], $this->type);
		}
		Config::setValue($handle, $v);
	}

	public function get($k, $default = null) {
		$keys = $this->parseKey($k);
		$handle = & $this->config;
		$key = "";
		foreach ($keys["key"] as $i => $key) {
			if (!Config::hasNext($handle, $key, $keys["type"][$i], $this->type))
				return null;
			$handle = & Config::getNext($handle, $key, $keys["type"][$i], $this->type);
		}
		$ret = Config::getValue($handle);
		return $ret === Config::$NIL ? $default : $ret;
	}

	public static function setValue(&$handle, $v): void {
		if ($handle instanceof \DOMElement) {
			$handle->textContent = $v;
		} elseif ($handle instanceof \DOMAttr) {
			$handle->value = $v;
		}
		$handle = $v;
	}

	public static function getValue(&$handle) {
		if ($handle instanceof \DOMElement) {
			return $handle->textContent;
		} elseif ($handle instanceof \DOMAttr) {
			return $handle->value;
		}
		return $handle;
	}

	public static function hasNext(&$handle, string $k, int $type, int $configType):bool {
		$a = Config::getNext($handle, $k, $type, $configType);
		return $a != Config::$NIL;
	}

	public static function createNext(&$handle, string $k, int $type, int $configType):void {
		if ($configType == Config::XML) {
			if ($type == Config::NAMESPACE_ATRRIBUTE) {
				$handle->appendChild($this->config->createAttribute($k));
			} else {
				$handle->appendChild($this->config->createElement($k));
			}
		} else {
			// set it to array, cause isset($a = null) == false
			$handle[$k] = [];
		}
	}

	public static function &getNext(&$handle, string $k, int $type, int $configType) {
		if ($configType == Config::XML) {
			if ($type == Config::NAMESPACE_ATRRIBUTE) {
				if (!$handle->hasAttribute($k))
					return Config::$NIL;
				return $handle->getAttributeNode($k);
			} else {
				foreach ($handle->childNodes as $v) {
					if ($v->nodeName == $k) {
						return $v;
					}
				}
				return Config::$NIL;
			}
		} else {
			if (isset($handle[$k]))
				return $handle[$k];
			return Config::$NIL;
		}
	}

	public function remove($k):void {
		$keys = $this->parseKey($k);
		$last = null;
		$handle = $this->config;
		$key = "";
		foreach ($keys as $i => $key) {
			if (isset($handle[$key])) {
				$last = $handle;
				$handle = & $handle[$key];
			} else {
				return;
			}
		}
		if ($last == null) {
			$this->config = [];
		} else {
			unset($last[$key]);
		}
	}

	private function parseKey(string $k):array {
		$k = trim($k);
		$tuple = [];
		preg_match_all("((<|\[)?\w+(\]|>)?)", $k, $tmp);
		foreach ($tmp[0] as $k => $v) {
			$vv = $v;
			if (strpos($v, "<") === 0) {
				$tuple["type"][$k] = Config::NAMESPACE_ATRRIBUTE;
				$vv = substr($k, 1, strlen($k) - 2);
			} elseif (strpos($v, "[") === 0) {
				$tuple["type"][$k] = Config::NAMESPACE_ARRAY;
				$vv = substr($k, 1, strlen($k) - 2);
			} else {
				$tuple["type"][$k] = Config::NAMESPACE_OBJECT;
			}
				$tuple["key"][$k] = $vv;

		}
		return $tuple;
	}

	private function encodePropertise():string {
		$str = "#Properties Config file\n#".date("D M j H:i:s T Y")."\n";
		foreach ($this->config as $k => $v) {
			if (is_bool($v)) {
				$v = $v ? "on" : "off";
			} elseif (is_array($v)) {
				$v = implode(";", $v);
			}
			$str .= $k."="."\n";
		}
		return $str;
	}

	private function decodePropertise(string $v):void {
		$tmp = [];
		preg_match_all('/([a-zA-Z0-9\-_\.]+)\s*=\s*([^\r\n]*)/u', $v, $tmp);
		foreach ($tmp[1] as $i => $k) {
			$v = trim($tmp[2][$i]);
			switch (strtolower($v)) {
				case "on":
				case "true":
				case "yes":
					$v = true;
					break;
				case "off":
				case "false":
				case "no":
					$v = false;
					break;
			}
			$this->config[$k] = $v;
		}
	}

	private function encodeEnum():string {
		$tmp = "";
		foreach ($this->config as $v) {
			$tmp .= $v."\n";
		}
		return $tmp;
	}

	private function decodeEnum(string $v):void {
		$this->config = explode("\n", trim(str_replace("\r\n", "\n", $v)));
	}
}
?>