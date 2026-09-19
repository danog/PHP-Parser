<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class FuncCall extends CallLike {
    /** @var Node\Name|Expr Function name */
    public Node $name;
    /** @var list<Node\Arg|Node\VariadicPlaceholder> Arguments */
    public array $args;

    /**
     * Constructs a function call node.
     *
     * @param Node\Name|Expr $name Function name
     * @param list<Node\Arg|Node\VariadicPlaceholder> $args Arguments
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Node $name, array $args = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->name = $name;
        $this->args = $args;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name', 'args'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            'args' => $this->args,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'name':
                $this->name = $value;
                return;
            case 'args':
                $this->args = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_FuncCall';
    }

    /** @return list<\PhpParser\Node\Arg|\PhpParser\Node\VariadicPlaceholder> */
    public function getRawArgs(): array {
        return $this->args;
    }
}
