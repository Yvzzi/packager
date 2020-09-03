<?php

require_once __DIR__ . "/../autoload@module.php";
try_require_once(module() . "/lib/autoload@lib.php");
try_require_once(inner() . "/lib/autoload@lib.php");
use cli\Cli;

const HELP = <<<EOF
This programs contains serveral subfunctions, you can enable by following.

Basic Usage:
-t java,php ... Module of php and java to make packager
-p ... Php Tools to create project in development
EOF;

$param = getopt("t:p");
if (isset($param["t"]) && ($param["t"] === "php" || $param["t"] === "java")) {
    if ($param["t"] === "java") {
        require_once __DIR__ . "/packager_java_cli.php";
    } else {
        require_once __DIR__ . "/packager_cli.php";
    }
} elseif (isset($param["p"])) {
    require_once __DIR__ . "/php_manager.php";
} else {
    Cli::tell(HELP);
}
