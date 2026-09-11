<?php declare(strict_types=1);

namespace PhpParser;

interface NodeTraverserInterface {
    /**
     * Adds a visitor.
     *
     * @param NodeVisitor $visitor Visitor to add
     */
    public function addVisitor(NodeVisitor $visitor): void;

    /**
     * Removes an added visitor.
     */
    public function removeVisitor(NodeVisitor $visitor): void;

    /**
     * Traverses an array of nodes using the registered visitors.
     *
     * @param list<Node> $nodes Array of nodes
     *
     * @return list<Node> Traversed array of nodes
     */
    public function traverse(array $nodes): array;
}
