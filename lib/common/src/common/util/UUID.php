<?php
declare(strict_types = 1);

namespace common\util;

class UUID {
	public static function isDomain($uuid, $domain):bool {
		return explode("-", $uuid)[3] == substr(md5($domain), 0, 4);
	}

	public static function equalsOnDomain($uuid, $des):bool {
		return explode("-", $uuid)[3] == explode("-", $des)[3];
	}

    public static function generate($domain = "") {
        // 系统时间戳用8 bytes/64 bits表示
        $uuid_pre = substr(md5(uniqid("" . microtime(true), true)), 0, 16);
        // 8 bytes=>16
        $uuid_mid = substr(md5($domain), 0, 4);
        // 2 bytes=>4
        $uuid_sub = substr(md5(self::getMacAddr()), 0, 12);
        // 6 bytes=>12
		$uid_sub = $uid_sub ?? md5(uniqid("" . microtime(true), true));
        return substr($uuid_pre, 0, 8) . "-" . substr($uuid_pre, 8, 4) . "-" . substr($uuid_pre, 12, 4) . "-" . $uuid_mid . "-" . $uuid_sub;
        //32 bytes
    }

    private static function getMacAddr():string {
        $temp = array();
        switch (strtolower(PHP_OS)) {
            case "linux":
                $temp = self::forLinux();
                break;
            case "solaris":
                break;
            case "unix":
                break;
            case "aix":
                break;
            default:
                $temp = self::forWindows();
                break;
        }
        $temp_array = array();
        $mac_addr = "";
        foreach ($temp as $value) {
            if (preg_match("/[0-9a-f][0-9a-f][:-]" . "[0-9a-f][0-9a-f][:-]" . "[0-9a-f][0-9a-f][:-]" . "[0-9a-f][0-9a-f][:-]" . "[0-9a-f][0-9a-f][:-]" . "[0-9a-f][0-9a-f]/i", $value, $temp_array)) {
                $mac_addr = $temp_array[0];
                break;
            }
        }
        return empty($mac_addr) ? null : $mac_addr;
    }

    private static function forWindows():array {
        @exec("ipconfig /all", $result);
        if ($result) {
            return $result;
        } else {
            $ipconfig = $_SERVER["WINDIR"] . "\\system32\\ipconfig.exe";
            if (is_file($ipconfig)) {
                @exec($ipconfig . " /all", $result);
            } else {
                @exec($_SERVER["WINDIR"] . "\\system\\ipconfig.exe /all", $result);
            }
            return $result;
        }
    }

    private static function forLinux():array {
        @exec("ifconfig -a", $result);
        return $result;
    }
}
?>