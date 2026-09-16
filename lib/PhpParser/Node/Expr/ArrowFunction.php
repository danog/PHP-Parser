<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;

class ArrowFunction extends Expr implements FunctionLike {
    /** @var bool Whether the closure is static */
    public bool $static;

    /** @var bool Whether to return by reference */
    public bool $byRef;

    /** @var list<Node\Param> */
    public array $params = [];

    /** @var null|Node\Identifier|Node\Name|Node\ComplexType */
    public ?Node $returnType;

    /** @var Expr Expression body */
    public Expr $expr;
    /** @var list<Node\AttributeGroup> */
    public array $attrGroups;

    /**
     * @param array{
     *     expr: Expr,
     *     static?: bool,
     *     byRef?: bool,
     *     params?: list<Node\Param>,
     *     returnType?: null|Node\Identifier|Node\Name|Node\ComplexType,
     *     attrGroups?: list<Node\AttributeGroup>
     * } $subNodes Array of the following subnodes:
     *             'expr'                  : Expression body
     *             'static'     => false   : Whether the closure is static
     *             'byRef'      => false   : Whether to return by reference
     *             'params'     => array() : Parameters
     *             'returnType' => null    : Return type
     *             'attrGroups' => array() : PHP attribute groups
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(array $subNodes, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->static = $subNodes['static'] ?? false;
        $this->byRef = $subNodes['byRef'] ?? false;
        $this->params = $subNodes['params'] ?? [];
        $this->returnType = $subNodes['returnType'] ?? null;
        $this->expr = $subNodes['expr'];
        $this->attrGroups = $subNodes['attrGroups'] ?? [];
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrGroups', 'static', 'byRef', 'params', 'returnType', 'expr'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'static' => $this->static,
            'byRef' => $this->byRef,
            'params' => $this->params,
            'returnType' => $this->returnType,
            'expr' => $this->expr,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrGroups':
                $this->attrGroups = $value;
                return;
            case 'static':
                $this->static = $value;
                return;
            case 'byRef':
                $this->byRef = $value;
                return;
            case 'params':
                $this->params = $value;
                return;
            case 'returnType':
                $this->returnType = $value;
                return;
            case 'expr':
                $this->expr = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function returnsByRef(): bool {
        return $this->byRef;
    }

    /** @return list<\PhpParser\Node\Param> */
    public function getParams(): array {
        return $this->params;
    }

    /** @return null|Node\Identifier|Node\Name|Node\ComplexType */
    public function getReturnType() {
        return $this->returnType;
    }

    /** @return list<\PhpParser\Node\AttributeGroup> */
    public function getAttrGroups(): array {
        return $this->attrGroups;
    }

    /**
     * @return list<Node\Stmt\Return_>
     */
    public function getStmts(): array {
        return [new Node\Stmt\Return_($this->expr)];
    }

    public function getType(): string {
        return 'Expr_ArrowFunction';
    }
}
