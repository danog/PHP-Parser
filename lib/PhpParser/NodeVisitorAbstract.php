<?php declare(strict_types=1);

namespace PhpParser;

/**
 * @codeCoverageIgnore
 */
abstract class NodeVisitorAbstract implements NodeVisitor {
    /**
     * @param list<Node> $nodes
     * @return null|list<Node>
     */
    public function beforeTraverse(array $nodes) {
        return null;
    }

    /** @return null|int|Node|list<Node> */
    public function enterNode(Node $node) {
        return null;
    }

    /** @return null|int|Node|list<Node> */
    public function leaveNode(Node $node) {
        return null;
    }

    /**
     * @param list<Node> $nodes
     * @return null|list<Node>
     */
    public function afterTraverse(array $nodes) {
        return null;
    }
}
