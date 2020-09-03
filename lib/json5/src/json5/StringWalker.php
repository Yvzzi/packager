<?php
namespace json5;

class StringWalker {
    public $contents;
    public $index;

    public function __construct(string $contents, $index = 0) {
        $this->contents = $contents;
        $this->index = $index;
    }

    public function get(int $len): string {
        return substr($this->contents, $this->index, $len);
    }

    public function walkUtil(callable $callback, $step = 1): void {
        do {
            $this->index += $step;
            $substr = $this->get($step);
        } while (!$callback($substr));
    }

    public function matchString(): int {
        $left = $this->get(1);
        do {
            $this->index++;
            $char = $this->get(1);
            if ($char === "\\") {
                $this->index += 2;
                $char = $this->get(1);
            }
        } while ($char !== $left);
        return $this->index;
    }

    public function matchPair(string $left, string $right): int {
        $deep = 1;
        $walker = new StringWalker($this->contents, $this->index);
        do {
            $walker->index++;
            $char = $walker->get(1);
            if ($char === "\"" || $char === "'") {
                $walker->matchString();
                $walker->index++;
                $char = $walker->get(1);
            }
            if ($char === $left) {
                $deep++;
            } elseif ($char === $right) {
                $deep--;
            }
        } while ($deep !== 0);
        return $walker->index;
    }
}