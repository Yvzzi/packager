<?php
declare(strict_types = 1);

namespace common\util;

class JSONArray extends JSONObject {
    /** Read Only */
    private $length = 0;
    private $arrayData = [];
    
    public function __construct() {
        parent::__construct();
        $this->length = 0;
        $this->set("&origin", "Array");
    }
    
    public function getOrigin():string {
        return "Array";
    }

    public static function create(array $arr, $clazz = null):JSONObject {
        return parent::create(array_merge($arr, ["&origin" => "Array"]), $clazz);
    }
    
    public function set($k, $v):void {
        if (is_int($k)) {
            $this->arrayData[$k] = $v;
            parent::set($k, $v);
        }
        if (is_string($k) && strpos($k, "&") === 0) {
            parent::set($k, $v);
        }
        $this->updateLength();
    }

    public function remove($k):void {
        // index of array_splice is not the real index of array
        // and it will remove flag
        if (is_int($k)) {
            array_splice($this->arrayData, $k, 1);
            $this->updateData();
        }
        if (is_string($k) && strpos($k, "&")) {
            parent::remove($k);
        }
        $this->updateLength();
    }
    
    public function getArrayData():array {
        return $this->arrayData;
    }

    public function push($v):void {
        array_push($this->arrayData, $v);
        array_push($this->data, $v);
        $this->updateLength();
    }

    public function pop() {
        $ret = $this->get($this->length() - 1);
        $this->remove($this->length() - 1);
        $this->updateLength();
        return $ret;
    }
    
    public function shift() {
        $ret = $this->data[0];
        $this->remove(0);
        $this->updateLength();
        return $ret;
    }

    public function unshift($v):void {
        array_unshift($this->arrayData, $v);
        array_unshift($this->data, $v);
    }

    public function search($haystack, $strict) {
        return array_search($this->data, $haystack, $strict);
    }

    public function merge(JSONArray $arr):void {
        array_merge($this->arrayData, $arr->getArrayData());
        $this->updateData();
        $this->updateLength();
    }

    public function slice($offset, $length):JSONArray {
        $arr = new JSONArray();
        $newData =  array_slice($this->arrayData, $offset, $length);
        foreach ($newData as $k => $v) {
            $arr->set($k, $v);
        }
        return $arr;
    }

    public function splice($offset, int $length = -1, $replacement = null):void {
        array_splice($this->arrayData, $offset, $length < 0 ? count($this->data) : $length, $replacement ?? array());
        $this->updateData();
        $this->updateLength();
    }

    private function updateData() {
        $this->data = array_filter($this->data, function ($v, $k) {
            return !is_int($k);
        }, ARRAY_FILTER_USE_BOTH);
        $this->data = array_merge($this->data, $this->arrayData);
    }

    private function updateLength() {
        $this->length = -1;
    }

    public function length():int {
        if ($this->length >= 0) return $this->length;
        return ($this->length = count($this->arrayData));
    }
}
?>