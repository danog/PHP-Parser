<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Static_ extends Stmt {
    /** @var list<StaticVar> Variable definitions */
    public array $vars;

    /**
     * Constructs a static variables list node.
     *
     * @param list<StaticVar> $vars Variable definitions
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $vars, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->vars = $vars;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['vars'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['vars' => $this->vars];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->vars = $traverser->traverseArray($this->vars);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'vars' => $this->vars,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
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
