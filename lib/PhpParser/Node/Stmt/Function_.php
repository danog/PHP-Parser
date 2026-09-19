<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Function_ extends Node\Stmt implements FunctionLike {
    /** @var bool Whether function returns by reference */
    public bool $byRef;
    /** @var Node\Identifier Name */
    public Node\Identifier $name;
    /** @var list<Node\Param> Parameters */
    public array $params;
    /** @var null|Node\Identifier|Node\Name|Node\ComplexType Return type */
    public ?Node $returnType;
    /** @var list<Node\Stmt> Statements */
    public array $stmts;
    /** @var list<Node\AttributeGroup> PHP attribute groups */
    public array $attrGroups;

    /** @var Node\Name|null Namespaced name (if using NameResolver) */
    public ?Node\Name $namespacedName;

    /**
     * Constructs a function node.
     *
     * @param string|Node\Identifier $name Name
     * @param array{
     *     byRef?: bool,
     *     params?: list<Node\Param>,
     *     returnType?: null|Node\Identifier|Node\Name|Node\ComplexType,
     *     stmts?: list<Node\Stmt>,
     *     attrGroups?: list<Node\AttributeGroup>,
     * } $subNodes Array of the following optional subnodes:
     *             'byRef'      => false  : Whether to return by reference
     *             'params'     => array(): Parameters
     *             'returnType' => null   : Return type
     *             'stmts'      => array(): Statements
     *             'attrGroups' => array(): PHP attribute groups
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, array $subNodes = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->byRef = $subNodes['byRef'] ?? false;
        $this->name = \is_string($name) ? new Node\Identifier($name) : $name;
        $this->params = $subNodes['params'] ?? [];
        $this->returnType = $subNodes['returnType'] ?? null;
        $this->stmts = $subNodes['stmts'] ?? [];
        $this->attrGroups = $subNodes['attrGroups'] ?? [];
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrGroups', 'byRef', 'name', 'params', 'returnType', 'stmts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'byRef' => $this->byRef,
            'name' => $this->name,
            'params' => $this->params,
            'returnType' => $this->returnType,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrGroups':
                $this->attrGroups = $value;
                return;
            case 'byRef':
                $this->byRef = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
            case 'params':
                $this->params = $value;
                return;
            case 'returnType':
                $this->returnType = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
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

    /** @return list<Node\Stmt> */
    public function getStmts(): array {
        return $this->stmts;
    }

    public function getType(): string {
        return 'Stmt_Function';
    }
}
