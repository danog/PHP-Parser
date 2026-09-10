<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt;

class Static_ extends Stmt {
    /** @var StaticVar[] Variable definitions */
    public array $vars;

    /**
     * Constructs a static variables list node.
     *
     * @param StaticVar[] $vars Variable definitions
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $vars, array $attributes = []) {
        $this->attributes = $attributes;
        $this->vars = $vars;
    }

    public function getSubNodeNames(): array {
        return ['vars'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'vars' => $this->vars,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'vars':
                $this->vars = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Static';
    }
}
