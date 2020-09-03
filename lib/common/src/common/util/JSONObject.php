<?php
namespace common\util;

class JSONObject {
    protected $data = null;

    public function __construct() {
        $this->data = array();
    }

    public static function create(array $arr, array $clazz = null):JSONObject {
        $c = isset($arr["&origin"]) ? $arr["&origin"]  : "Object";
        $obj = null;
        if ($clazz != null) {
            foreach ($clazz as $k => $v) {
                if ($k == $c) {
                    $reflectClass = new \ReflectionClass($v);
                    $obj = $reflectClass->newInstance();
                }
            }
        }
        $obj = $obj ?? ($c == "Array" ? new JSONArray() : new JSONObject);
        foreach ($arr as $k => $v) {
            $obj->set($k, $v);
        }
        return $obj;
    }

    /**
     * Convert a pure array or a array with item instanceof JSONObject to Standard struct
     * @param array|JSONObject $obj
     */
    public static function toJSONObject($obj, array $clazz = null):JSONObject {
        if (!is_array($obj) && !$obj instanceof JSONObject) return null;
        // new JSON Obj
        if (is_array($obj)) $obj = JSONObject::create($obj, $clazz);
        // recursively search keys
        foreach ($obj->getData() as $k => $v) {
            if (is_array($v) || $v instanceof JSONObject) $obj->set($k, JSONObject::toJSONObject($v, $clazz));
        }
        return $obj;
    }

    public static function toArray(JSONObject $obj):array {
        $arr = array();
        foreach ($obj->getData() as $k => $v) {
            if ($v instanceof JSONObject) {
                $v = JSONObject::toArray($v);
            }
            $arr[$k] = $v;
        }
        return $arr;
    }

    public function get($k, $defaullt = null) {
        return isset($this->data[$k]) ? $this->data[$k] : $defaullt;
    }

    public function set($k, $v):void {
        $this->data[$k] = $v;
    }

    public function remove($k):void {
        unset($this->data[$k]);
    }

    public function contains($k) {
        return isset($this->data[$k]);
    }

    public function getOrigin() {
        return isset($this->data["&origin"]) ? $this->data["&origin"] : null;
    }

    public function setId(string $id):void {
        $this->data["&id"] = $id;
    }

    public function getId() {
        return isset($this->data["&id"]) ? $this->data["&id"] : null;
    }

    public function getData():array {
        return $this->data;
    }
}
?>