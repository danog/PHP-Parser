<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;

interface FunctionLike extends Node {
    /**
     * Whether to return by reference
     */
    public function returnsByRef(): bool;

    /**
     * List of parameters
     *
     * @return list<Param>
     */
    public function getParams(): array;

    /**
     * Get the declared return type or null
     *
     * @return null|Identifier|Name|ComplexType
     */
    public function getReturnType();

    /**
     * The function body
     *
     * @return list<Stmt>|null
     */
    public function getStmts(): ?array;

    /**
     * Get PHP attribute groups.
     *
     * @return list<AttributeGroup>
     */
    public function getAttrGroups(): array;
}
