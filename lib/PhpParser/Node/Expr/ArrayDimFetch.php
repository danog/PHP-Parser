<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class ArrayDimFetch extends Expr {
    /** @var Expr Variable */
    public Expr $var;
    /** @var null|Expr Array index / dim */
    public ?Expr $dim;

    /**
     * Constructs an array index fetch node.
     *
     * @param Expr $var Variable
     * @param null|Expr $dim Array index / dim
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Expr $var, ?Expr $dim = null, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->var = $var;
        $this->dim = $dim;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['var', 'dim'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['var' => $this->var, 'dim' => $this->dim];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->var = $traverser->traverseRequiredChildNode($this->var);
        if ($traverser->stopTraversal) {
            return;
        }
        if ($this->dim !== null) {
            $this->dim = $traverser->traverseChildNode($this->dim);
            if ($traverser->stopTraversal) {
                return;
            }
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'var' => $this->var,
            'dim' => $this->dim,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'var':
                $this->var = $value;
                return;
            case 'dim':
                $this->dim = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_ArrayDimFetch';
    }
}
