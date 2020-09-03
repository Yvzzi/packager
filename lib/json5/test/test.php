<?php

require_once __DIR__ . "/../autoload@module.php";

use json5\JSON5;

$obj = JSON5::parse(file_get_contents("a.json5"));
var_dump($obj);