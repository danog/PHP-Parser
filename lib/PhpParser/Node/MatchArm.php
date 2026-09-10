<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;
use PhpParser\NodeAbstract;

class MatchArm extends NodeAbstract {
    /** @var null|list<Node\Expr> */
    public ?array $conds;
    public Expr $body;

    /**
     * @param null|list<Node\Expr> $conds
     */
    public function __construct(?array $conds, Node\Expr $body, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->conds = $conds;
        $this->body = $body;
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
    }

    public function getSubNodeNames(): array {
        return ['conds', 'body'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'conds' => $this->conds,
            'body' => $this->body,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

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
