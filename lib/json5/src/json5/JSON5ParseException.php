<?php
namespace json5;

class JSON5ParseException extends \Exception {
    public function __construct(string $message) {
        parent::__construct($message);
    }
}