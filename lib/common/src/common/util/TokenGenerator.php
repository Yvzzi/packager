<?php
namespace common\util;

/**
 *  JSON Web Token Generator
 *  Header {
 *		"alg": "HS256",
 *		"typ": "JWT"
 *	}
 *	Payload {
 *		"iss": Issuer,
 *		"exp": Expiration Time,
 *		"sub": Subject,
 *		"aud": Audience,
 *		"nbf": Not Before,
 *		"iat": Issue At,
 *		"jti": JWT ID,
 *	}
 *  Signature
 */
class TokenGenerator {
    private const DEFAULT_SALT = "75d13e40-599c-4007-9c40-3278365b261c";

    private $presetLoad;
    /** Prevent fake jwt be verified by additional salt */
    private $salt;
    /** Prevent fake jwt be verified by store jti */
    private $storeMode;
    private $storePath;

    public function __construct($salt = "", bool $storeMode = false, string $storePath = "") {
        $this->presetLoad = [
            "iss" => "localhost",
            "sub" => "all",
            "exp" => time() + 60 * 30,
            "nbf" => time(),
            "jti" => uniqid(rand() . "" . time(), true)
        ];
        $this->salt = empty($salt) ? self::DEFAULT_SALT : $salt;
        $this->storeMode = $storeMode;
        $this->storePath = $storePath;
        if ($storeMode) {
            if (!file_exists($storePath)) {
                $file = fopen($storePath, "w");
                if ($file === false) throw new \Exception("Cannot create file ${storePath}");
                fclose($file);
            }
            $data = json_decode(file_get_contents($storePath), true);
            if ($data === null || array_keys(array_keys($data)) != array_keys($data)) {
                file_put_contents($storePath, "[]");
            }
        }
    }

	public function issue(string $issuer) {
        $this->presetLoad["iss"] = $issuer;
        return $this;
    }

    public function expiration(int $expiration) {
        $this->presetLoad["exp"] = $expiration;
        return $this;
    }

    public function subject(string $subject) {
        $this->presetLoad["sub"] = $subject;
        return $this;
    }

    public function audience(array $audience) {
        $this->presetLoad["aud"] = $audience;
        return $this;
    }

    public function notBefore(int $notBefore) {
        $this->presetLoad["nbf"] = $notBefore;
        return $this;
    }

    public function issueAt(string $issueAt) {
        $this->presetLoad["iat"] = $issueAt;
        return $this;
    }

    public function jwtId(string $jwtId) {
        $this->presetLoad["jti"] = $jwtId;
        return $this;
    }

    public function sessionId($sessionId) {
        $this->presetLoad["sessionId"] = $sessionId;
        return $this;
    }

    public function payload(array $payload) {
        $this->presetLoad = array_merge($this->presetLoad, $payload);
        return $this;
    }

    public function publish():string {
        if ($this->storeMode) {
            $data = json_decode(file_get_contents($this->storePath), true);
            array_push($data, $this->presetLoad["jti"]);
            file_put_contents($this->storePath, json_encode($data));
        }
        return self::generate($this->presetLoad, $this->salt);
    }

	public function verify($token, $callable = null):bool {
        $els = explode(".", $token);
        if (count($els) != 3) return false;
        if (self::hashSalt($els[0] . $els[1], $this->salt) != $els[2]) return false;
        $payload = json_decode(self::base64UrlDecode($els[1]), true);
        if (is_null($payload)) return false;
        if ((int) $payload["exp"] - time() <= 0) return false;
        if ((int) $payload["nbf"] - time() >= 0) return false;
        if ($this->storeMode) {
            $data = json_decode(file_get_contents($this->storePath), true);
            if (!in_array($payload["jti"], $data)) return false;
        }
        return $callable === null ? true : $callable($payload);
	}

	protected static function generate(array $payload, $salt = ""):string {
		$header = '{"alg":"HS256","typ":"JWT"}';
		$payload = json_encode($payload);
		$header = self::base64UrlEncode($header);
        $payload = self::base64UrlEncode($payload);
		$signature = self::hashSalt($header . $payload, $salt);
		return $header . "." . $payload . "." . $signature;
	}

    public static function base64UrlEncode(string $data):string {
        $encode = base64_encode($data);
        $encode = str_replace("+", "-", $encode);
        $encode = str_replace("/", "_", $encode);
        $encode = str_replace("=", "", $encode);
        return $encode;
    }

    public static function base64UrlDecode(string $data):string {
        $data = str_replace("-", "+", $data);
        $data = str_replace("_", "/", $data);
        $len = strlen($data) * 4 % 3;
        if ($len == 2) {
            $data .= "==";
        } elseif ($len == 3) {
            $data .= "=";
        }
        return base64_decode($data);
    }

	/**
     * HASH SHA256 with salt
	 */
	private static function hashSalt($data, $salt) {
		return hash("sha256", $data . $salt . self::DEFAULT_SALT);
	}
}
?>