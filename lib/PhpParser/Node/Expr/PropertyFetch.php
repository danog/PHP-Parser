<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;

class PropertyFetch extends Expr {
    /** @var Expr Variable holding object */
    public Expr $var;
    /** @var Identifier|Expr Property name */
    public Node $name;

    /**
     * Constructs a function call node.
     *
     * @param Expr $var Variable holding object
     * @param string|Identifier|Expr $name Property name
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(Expr $var, $name, array $attributes = []) {
        $this->attributes = $attributes;
        $this->var = $var;
        $this->name = \is_string($name) ? new Identifier($name) : $name;
    }

    public function getSubNodeNames(): array {
        return ['var', 'name'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'var' => $this->var,
            'name' => $this->name,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'var':
                $this->var = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_PropertyFetch';
    }
}
