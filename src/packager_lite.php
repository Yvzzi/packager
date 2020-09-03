<?php
/**
 *  @file packager_lite.php
 *  @brief A easy tool
 *  @author Yvzzi
 */

require_once __DIR__ . "/../autoload@module.php";
try_require_once(inner() . "/lib/autoload@lib.php");
try_require_once(module() . "/lib/autoload@lib.php");

use phar\PharBuilder;
use common\util\FileIO;
use json5\JSON5;
use cli\Cli;

const SHEBANG = "#!/bin/env php";
const STUB = <<<EOF
<?php

/**
 *
 *    ___       ___       ___       ___       ___       ___       ___       ___
 *   /\  \     /\  \     /\  \     /\__\     /\  \     /\  \     /\  \     /\  \
 *  /::\  \   /::\  \   /::\  \   /:/ _/_   /::\  \   /::\  \   /::\  \   /::\  \
 * /::\:\__\ /::\:\__\ /:/\:\__\ /::-"\__\ /::\:\__\ /:/\:\__\ /::\:\__\ /::\:\__\
 * \/\::/  / \/\::/  / \:\ \/__/ \;:;-",-" \/\::/  / \:\:\/__/ \:\:\/  / \;:::/  /
 *    \/__/    /:/  /   \:\__\    |:|  |     /:/  /   \::/  /   \:\/  /   |:\/__/
 *             \/__/     \/__/     \|__|     \/__/     \/__/     \/__/     \|__|
 *
 * Phared by Packager v{{version}}
 * Created at {{date}}
 */

\$web = '{{web}}';

if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', 0)) {
Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());
Phar::webPhar(null, \$web);
include 'phar://' . __FILE__ . '/' . Extract_Phar::START;
return;
}

if (@(isset(\$_SERVER['REQUEST_URI']) && isset(\$_SERVER['REQUEST_METHOD']) && (\$_SERVER['REQUEST_METHOD'] == 'GET' || \$_SERVER['REQUEST_METHOD'] == 'POST'))) {
Extract_Phar::go(true);
\$mimes = array(
'phps' => 2,
'c' => 'text/plain',
'cc' => 'text/plain',
'cpp' => 'text/plain',
'c++' => 'text/plain',
'dtd' => 'text/plain',
'h' => 'text/plain',
'log' => 'text/plain',
'rng' => 'text/plain',
'txt' => 'text/plain',
'xsd' => 'text/plain',
'php' => 1,
'inc' => 1,
'avi' => 'video/avi',
'bmp' => 'image/bmp',
'css' => 'text/css',
'gif' => 'image/gif',
'htm' => 'text/html',
'html' => 'text/html',
'htmls' => 'text/html',
'ico' => 'image/x-ico',
'jpe' => 'image/jpeg',
'jpg' => 'image/jpeg',
'jpeg' => 'image/jpeg',
'js' => 'application/x-javascript',
'midi' => 'audio/midi',
'mid' => 'audio/midi',
'mod' => 'audio/mod',
'mov' => 'movie/quicktime',
'mp3' => 'audio/mp3',
'mpg' => 'video/mpeg',
'mpeg' => 'video/mpeg',
'pdf' => 'application/pdf',
'png' => 'image/png',
'swf' => 'application/shockwave-flash',
'tif' => 'image/tiff',
'tiff' => 'image/tiff',
'wav' => 'audio/wav',
'xbm' => 'image/xbm',
'xml' => 'text/xml',
);

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

\$basename = basename(__FILE__);
if (!strpos(\$_SERVER['REQUEST_URI'], \$basename)) {
chdir(Extract_Phar::\$temp);
include \$web;
return;
}
\$pt = substr(\$_SERVER['REQUEST_URI'], strpos(\$_SERVER['REQUEST_URI'], \$basename) + strlen(\$basename));
if (!\$pt || \$pt == '/') {
\$pt = \$web;
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . \$_SERVER['REQUEST_URI'] . '/' . \$pt);
exit;
}
\$a = realpath(Extract_Phar::\$temp . DIRECTORY_SEPARATOR . \$pt);
if (!\$a || strlen(dirname(\$a)) < strlen(Extract_Phar::\$temp)) {
header('HTTP/1.0 404 Not Found');
echo "<html>\n <head>\n  <title>File Not Found<title>\n </head>\n <body>\n  <h1>404 - File Not Found</h1>\n </body>\n</html>";
exit;
}
\$b = pathinfo(\$a);
if (!isset(\$b['extension'])) {
header('Content-Type: text/plain');
header('Content-Length: ' . filesize(\$a));
readfile(\$a);
exit;
}
if (isset(\$mimes[\$b['extension']])) {
if (\$mimes[\$b['extension']] === 1) {
include \$a;
exit;
}
if (\$mimes[\$b['extension']] === 2) {
highlight_file(\$a);
exit;
}
header('Content-Type: ' .\$mimes[\$b['extension']]);
header('Content-Length: ' . filesize(\$a));
readfile(\$a);
exit;
}
}

class Extract_Phar
{
static \$temp;
static \$origdir;
const GZ = 0x1000;
const BZ2 = 0x2000;
const MASK = 0x3000;
const START = '{{cli}}';
const LEN = 6644;

static function go(\$return = false)
{
\$fp = fopen(__FILE__, 'rb');
fseek(\$fp, self::LEN);
\$L = unpack('V', \$a = fread(\$fp, 4));
\$m = '';

do {
\$read = 8192;
if (\$L[1] - strlen(\$m) < 8192) {
\$read = \$L[1] - strlen(\$m);
}
\$last = fread(\$fp, \$read);
\$m .= \$last;
} while (strlen(\$last) && strlen(\$m) < \$L[1]);

if (strlen(\$m) < \$L[1]) {
die('ERROR: manifest length read was "' .
strlen(\$m) .'" should be "' .
\$L[1] . '"');
}

\$info = self::_unpack(\$m);
\$f = \$info['c'];

if (\$f & self::GZ) {
if (!function_exists('gzinflate')) {
die('Error: zlib extension is not enabled -' .
' gzinflate() function needed for zlib-compressed .phars');
}
}

if (\$f & self::BZ2) {
if (!function_exists('bzdecompress')) {
die('Error: bzip2 extension is not enabled -' .
' bzdecompress() function needed for bz2-compressed .phars');
}
}

\$temp = self::tmpdir();

if (!\$temp || !is_writable(\$temp)) {
\$sessionpath = session_save_path();
if (strpos (\$sessionpath, ";") !== false)
\$sessionpath = substr (\$sessionpath, strpos (\$sessionpath, ";")+1);
if (!file_exists(\$sessionpath) || !is_dir(\$sessionpath)) {
die('Could not locate temporary directory to extract phar');
}
\$temp = \$sessionpath;
}

\$temp .= '/pharextract/'.basename(__FILE__, '.phar');
self::\$temp = \$temp;
self::\$origdir = getcwd();
@mkdir(\$temp, 0777, true);
\$temp = realpath(\$temp);

if (!file_exists(\$temp . DIRECTORY_SEPARATOR . md5_file(__FILE__))) {
self::_removeTmpFiles(\$temp, getcwd());
@mkdir(\$temp, 0777, true);
@file_put_contents(\$temp . '/' . md5_file(__FILE__), '');

foreach (\$info['m'] as \$path => \$file) {
\$a = !file_exists(dirname(\$temp . '/' . \$path));
@mkdir(dirname(\$temp . '/' . \$path), 0777, true);
clearstatcache();

if (\$path[strlen(\$path) - 1] == '/') {
@mkdir(\$temp . '/' . \$path, 0777);
} else {
file_put_contents(\$temp . '/' . \$path, self::extractFile(\$path, \$file, \$fp));
@chmod(\$temp . '/' . \$path, 0666);
}
}
}

chdir(\$temp);

if (!\$return) {
include self::START;
}
}

static function tmpdir()
{
if (strpos(PHP_OS, 'WIN') !== false) {
if (\$var = getenv('TMP') ? getenv('TMP') : getenv('TEMP')) {
return \$var;
}
if (is_dir('/temp') || mkdir('/temp')) {
return realpath('/temp');
}
return false;
}
if (\$var = getenv('TMPDIR')) {
return \$var;
}
return realpath('/tmp');
}

static function _unpack(\$m)
{
\$info = unpack('V', substr(\$m, 0, 4));
 \$l = unpack('V', substr(\$m, 10, 4));
\$m = substr(\$m, 14 + \$l[1]);
\$s = unpack('V', substr(\$m, 0, 4));
\$o = 0;
\$start = 4 + \$s[1];
\$ret['c'] = 0;

for (\$i = 0; \$i < \$info[1]; \$i++) {
 \$len = unpack('V', substr(\$m, \$start, 4));
\$start += 4;
 \$savepath = substr(\$m, \$start, \$len[1]);
\$start += \$len[1];
   \$ret['m'][\$savepath] = array_values(unpack('Va/Vb/Vc/Vd/Ve/Vf', substr(\$m, \$start, 24)));
\$ret['m'][\$savepath][3] = sprintf('%u', \$ret['m'][\$savepath][3]
& 0xffffffff);
\$ret['m'][\$savepath][7] = \$o;
\$o += \$ret['m'][\$savepath][2];
\$start += 24 + \$ret['m'][\$savepath][5];
\$ret['c'] |= \$ret['m'][\$savepath][4] & self::MASK;
}
return \$ret;
}

static function extractFile(\$path, \$entry, \$fp)
{
\$data = '';
\$c = \$entry[2];

while (\$c) {
if (\$c < 8192) {
\$data .= @fread(\$fp, \$c);
\$c = 0;
} else {
\$c -= 8192;
\$data .= @fread(\$fp, 8192);
}
}

if (\$entry[4] & self::GZ) {
\$data = gzinflate(\$data);
} elseif (\$entry[4] & self::BZ2) {
\$data = bzdecompress(\$data);
}

if (strlen(\$data) != \$entry[0]) {
die("Invalid internal .phar file (size error " . strlen(\$data) . " != " .
\$stat[7] . ")");
}

if (\$entry[3] != sprintf("%u", crc32(\$data) & 0xffffffff)) {
die("Invalid internal .phar file (checksum error)");
}

return \$data;
}

static function _removeTmpFiles(\$temp, \$origdir)
{
chdir(\$temp);

foreach (glob('*') as \$f) {
if (file_exists(\$f)) {
is_dir(\$f) ? @rmdir(\$f) : @unlink(\$f);
if (file_exists(\$f) && is_dir(\$f)) {
self::_removeTmpFiles(\$f, getcwd());
}
}
}

@rmdir(\$temp);
clearstatcache();
chdir(\$origdir);
}
}
Extract_Phar::go();
__HALT_COMPILER(); ?>
EOF;

const USAGE = <<<EOF
Usage:
1. (Un)pack package
    {--pack, --unpack, -p, -u} <path> [-s, -o, -m, -z]
    Details:
    -u <path>, --unpack=<path>                      Unphar a archive
    -p <path>, --pack=<path>                        Phar a archive
    -s, --shebang                                   Use shebang
    -d, --default                                   Generate a default manifest
    -o <path>, --output=<path>                      Specific name of output
    -m <path>, --manifest=<path>                    Specific manifest for phar
    -z, --zip                                       Output with zip format
2. Generate autoload
    Details:
    -a [name], --autoload[=name]                    Create autoload file of current directory with
                                                        autoload@<name>.php or autoload.php
3. Replace old version autoload of current path
    Details:
    -r, --replace                                   Update and replace the old autoload file of current directory
4. Create new module/bare project
    Details:
    -n, --new {module, bare}  <name>:<namespace>    Create module/bare project template
    -h, --help                                      Get Help

EOF;

function generateAutoload($name, $path = null) {
    $path = $path === null ? getcwd() : $path;
    $path = $path . "/autoload{$name}.php";
    $content = file_get_contents(__DIR__ . "/../autoload@module.php");
    file_put_contents($path, $content);
}

function updateAutoload($directory) {
    $autoloadFile = __DIR__ . "/../autoload@module.php";
    $path = $directory . "/autoload.php";
    if (file_exists($path)) {
        Cli::tell("Update {$path}");
        $content = file_get_contents($autoloadFile);
        file_put_contents($path, $content);
        return;
    }

    $path = $directory . "/autoload@bare.php";
    if (file_exists($path)) {
        Cli::tell("Update {$path}");
        $content = file_get_contents($autoloadFile);
        file_put_contents($path, $content);
        return;
    }

    $path = $directory . "/autoload@module.php";
    if (file_exists($path)) {
        Cli::tell("Update {$path}");
        $content = file_get_contents($autoloadFile);
        file_put_contents($path, $content);
        return;
    }

    $path = $directory . "/autoload@lib.php";
    if (file_exists($path)) {
        Cli::tell("Update {$path}");
        $content = file_get_contents($autoloadFile);
        file_put_contents($path, $content);
        $dirs = scandir($directory);
        foreach ($dirs as $dir) {
            if ($dir === ".." || $dir === ".") continue;
            $path = $directory . "/" . $dir;
            updateAutoload($path);
        }
    }
}

function create($type, $name) {
    $ret = explode(":", $name);
    if (count($ret) === 2)
        [$name, $namespace] = $ret;
    else $namespace = "";
    $namespace = str_replace("\\", "/", $namespace);
    if ($type === "module") {
        if (!file_exists(getcwd() . "/$name/src/$namespace"))
            mkdir(getcwd() . "/$name/src/$namespace", 0755, true);
        generateAutoload("@module", getcwd() . "/$name");
        Cli::tell("Success to create module");
    } elseif ($type === "project") {
        if (!file_exists(getcwd() . "/$name/$namespace"))
            mkdir(getcwd() . "/$name/$namespace", 0755, true);
        generateAutoload("@bare", getcwd() . "/$name");
        Cli::tell("Success to create bare project");
    }
}

const VERSION = "lite-2.0.1";

// dont use getopt since it is useless in files which are required by the index file
$option = Cli::getCliOption($argv, [
    "u:", "unpack:",
    "p:", "pack:",
    "z", "zip", "m:", "manifest:", "s", "shebang",
    "d", "default",
    "o:", "output:",
    "a?", "autoload?",
    "r", "replace",
    "n", "new",
    "h", "help"
]);

function assertParams($expr) {
    if ($expr) return;
    Cli::tell(USAGE);
    exit;
}

if ($option->option("h") || $option->option("help")) {
    Cli::tell(USAGE);
    exit;
}

if ($option->option("d") || $option->option("default")) {
    file_put_contents("manifest.default.json5", JSON5::stringify([
        "\$comment_note" => "Please rename this file to manifest.json5 after editing",
        "\$comment_ignore" => "File list here will be ignore and it will not be packed into phar",
        "ignore" => [],
        "\$comment_mode" =>
            "There are 2 kinds of modes: default, none\n"
            . "If use mode 'default'. When you require it, it will load 'autoload@module.php' ('main.cli') when you use the phar.\n"
            . "When you access it by web browser, it will load 'src/index.php' ('main.web') when you use the phar.\n"
            . "If use mode 'none', you should write 'main.web', 'main.cli' by yourselft",
        "main.mode" => "default",
        "main.web" => "src/index.php",
        "main.cli" => "autoload@module.php"
    ]));
} elseif ($option->option("u") || $option->option("unpack")) {
    if ($option->option("o") || $option->option("output"))
        $output = FileIO::getAbsolutePath($option->option("o") ? $option->option("o") : $option->option("output"));
    else {
        $output = basename($option->option("u") ? $option->option("u") : $option->option("unpack"));
        $index = strrpos($output, ".");
        $output = substr($output, 0, $index);
    }
    PharBuilder::unphar(
        FileIO::getAbsolutePath($option->option("u") ? $option->option("u") : $option->option("unpack")),
        ($option->option("z") || $option->option("zip")) ? $output . ".zip" : $output,
        $option->option("z") || $option->option("zip")
    );
} elseif ($option->option("p") || $option->option("pack")) {
    $ignore = [];
    $projectDir = FileIO::getAbsolutePath($option->option("p") ? $option->option("p") : $option->option("pack"));
    if ($option->option("m") || $option->option("manifest")) {
        $obj = file_get_contents($projectDir);
        $obj = json_decode($obj, true);
    } elseif (file_exists($projectDir . "/manifest.json")) {
        $obj = file_get_contents($projectDir . "/manifest.json");
        $obj = json_decode($obj, true);
    } elseif (file_exists($projectDir . "/manifest.json5")) {
        $obj = file_get_contents($projectDir . "/manifest.json5");
        $obj = JSON5::parse($obj);
    } else {
        $obj = [
            "ignore" => [],
            "main" => [
                "mode" => "default",
                "web" => "autoload@module.php",
                "cli" => "src/index.php"
            ]
        ];
    }
    if ($obj["main"]["mode"] === "default") {
        $obj["main"]["cli"] = "autoload@module.php";
        $obj["main"]["web"] = "src/index.php";
    }
    $ignore = $obj["ignore"];
    $pharBuilder = new PharBuilder($projectDir, $ignore);
    $stubCli = $obj["main"]["cli"];
    $stubWeb = $obj["main"]["web"];
    
    $stub = STUB;
    if ($option->option("s") || $option->option("shebang"))
        $stub = SHEBANG . "\n" . $stub;
    $stub = str_replace("{{version}}", VERSION, $stub);
    $stub = str_replace("{{date}}", date("Y/m/d H:i:s"), $stub);
    $stub = str_replace("{{web}}", $stubWeb, $stub);
    $stub = str_replace("{{cli}}", $stubCli, $stub);
    $pharBuilder->setStub($stub);

    if ($option->option("o") || $option->option("output"))
        $output = FileIO::getAbsolutePath($option->option("o") ? $option->option("o") : $option->option("output"));
    else {
        $output = basename($projectDir) . ".phar";
    }
    $pharBuilder->build($output);
} elseif ($option->option("a") !== false || $option->option("autoload") !== false) {
    $name = $option->option("a") != ""
        ? "@" . $option->option("a")
        : ($option->option("autoload") != "" ? "@" . $option->option("autoload") : "");
    generateAutoload($name);
} elseif ($option->option("r") !== false || $option->option("replace") !== false) {
    updateAutoload(getcwd());
} elseif (($option->option("n") || $option->option("new")) && $option->position(1) && $option->position(2)) {
    $type = $option->position(1);
    $name = $option->position(2);
    create($type, $name);
}