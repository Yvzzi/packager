<?php
namespace cli;

class CliOption {
    private $positionalOpts;
    private $opts;

    public function __construct(array $posOpts, array $opts) {
        $this->positionalOpts = $posOpts;
        $this->opts = $opts;
    }

    public function option(string $opt, $default = false) {
        return $this->opts[$opt] === false ? $default : $this->opts[$opt];
    }
    
    public function has(string $opt) {
        return $this->opts[$opt] === false;
    }

    public function position(int $index) {
        $len = count($this->positionalOpts);
        if ($index >= $len)
            return null;
        return $this->positionalOpts[$index];
    }
    
    public function length() {
        return count($this->positionalOpts);
    }
}
