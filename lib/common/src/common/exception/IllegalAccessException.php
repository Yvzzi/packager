<?php
namespace common\exception;

class IllegalAccessException extends \Exception {
    public function __construct(string $message = "", int $code = 0, \Throwable $prev = null) {
        parent::__construct($message);
    }
}
?>