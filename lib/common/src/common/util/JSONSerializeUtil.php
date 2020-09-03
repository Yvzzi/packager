<?php
namespace common\util;

use common\util\UUID;
use common\util\JSONObject;
use common\util\JSONArray;

class JSONSerializeUtil {
    private $objPool = [];
    private $visited = [];
    private $classMap = [];
    public $uuidGenerator;
    
    /**
     * $classMap is the map from origin to class
     * @param array $classMap
     * @param callable|null $uuidGenerator
     */
    public function __construct($classMap = [], $uuidGenerator = null) {
        $this->classMap = $classMap;
        $this->uuidGenerator = $uuidGenerator ?? function($obj) {
            return UUID::generate();
        };
    }

    private function getClass($origin) {
        return isset($this->classMap[$origin]) ? $this->classMap[$origin] : null;
    }
    
    private function getOrigin($clazz) {
        return array_search($clazz, $this->classMap);
    }
    
    private function getPool(string $ref) {
        $ref = substr($ref, 5);
        foreach ($this->objPool as $k => $v) {
            if ($v->getId() == $ref) return $this->objPool[$k];
        }
        return null;
    }
    
    private function containsPool(JSONObject $obj):bool {
        return $obj->getId() != null;
    }
    
    private function putPool(JSONObject $v):void {
        $this->objPool[count($this->objPool)] = $v;
    }
    
    private function hasVisited(string $ref):bool {
        foreach ($this->visited as $v) {
            if ($v == $ref) return true;
        }
        return false;
    }
    
    private function setVisited(string $ref):void {
        array_push($this->visited, $ref);
    }

    /**
     * @param JSONObject|array $obj
     */
    public function serialize($obj) {
        $obj = $obj instanceof JSONObject ? $obj : JSONObject::toJSONObject($obj);
        $obj->setId(($this->uuidGenerator)($obj));
        $this->putPool($obj);
        $this->serializeObj($obj);
        foreach ($this->objPool as $k => $v) {
            $this->objPool[$k] = JSONObject::toArray($v);
        }
        $ret = array(
            '&' => '$ref:' . $obj->getId(),
            '&data' => &$this->objPool
        );
        return json_encode($ret);
    }
    
    private function serializeObj(JSONObject $obj):void {
        $origin = $this->getOrigin(get_class($obj));
        if ($origin != null) $obj->set("&origin", $origin);
        foreach ($obj->getData() as $k => $v) {
            if ($v instanceof JSONObject && !JSONSerializeUtil::isPlainObj($v)) {
                if (!$this->containsPool($v)) {
                    $v->setId(($this->uuidGenerator)($v));
                    $this->putPool($v);
                    $this->serializeObj($v);
                }
                $obj->set($k, '&ref:' . $v->getId());
            }
        }
    }
    
    public function unserialize($str) {
        $this->objPool = [];
        $this->visited = [];
        $obj = json_decode($str, true);
        foreach ($obj["&data"] as $v) {
            array_push($this->objPool, JSONObject::toJSONObject($v, $this->classMap));
        }
        $this->unserializeObj($obj['&']);
        return $this->getPool($obj['&']);
    }
    
    private function unserializeObj($ref):void {
        $this->setVisited($ref);
        $obj = $this->getPool($ref);
        foreach ($obj->getData() as $k => $v) {
            if (JSONSerializeUtil::isRef($v)) {
                $obj->set($k, $this->getPool($v));
                if (!$this->hasVisited($v)) {
                    $this->unserializeObj($v);
                }
            }
        }
    }
    
    public static function isRef($v):bool {
        return is_string($v) && strpos($v, '&ref:') === 0;
    }
    
    public static function isPlainObj(JSONObject $v):bool {
        foreach ($v->getData() as $vv) {
            // FIXME. JSONObject
            if ($vv instanceof JSONObject) return false;
        }
        return !is_subclass_of($v, "JSONObject");
    }
    
    public static function isPlain($v):bool {
         // integer, float, string or boolean
        return is_scalar($v);
    }
}
?>