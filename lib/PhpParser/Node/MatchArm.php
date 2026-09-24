<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;
use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class MatchArm extends NodeAbstract {
    /** @var null|list<Node\Expr> */
    public ?array $conds;
    public Expr $body;

    /**
     * @param null|list<Node\Expr> $conds
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(?array $conds, Node\Expr $body, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->conds = $conds;
        $this->body = $body;
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['conds', 'body'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['conds' => $this->conds, 'body' => $this->body];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        if ($this->conds !== null) {
            $this->conds = $traverser->traverseArray($this->conds);
            if ($traverser->stopTraversal) {
                return;
            }
        }
        $this->body = $traverser->traverseRequiredChildNode($this->body);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'conds' => $this->conds,
            'body' => $this->body,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'conds':
                $this->conds = $value;
                return;
            case 'body':
                $this->body = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'MatchArm';
    }
}
