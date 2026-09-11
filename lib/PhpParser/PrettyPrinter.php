<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Node\Expr;

interface PrettyPrinter {
    /**
     * Pretty prints an array of statements.
     *
     * @param list<Node> $stmts Array of statements
     *
     * @return string Pretty printed statements
     */
    public function prettyPrint(array $stmts): string;

    /**
     * Pretty prints an expression.
     *
     * @param Expr $node Expression node
     *
     * @return string Pretty printed node
     */
    public function prettyPrintExpr(Expr $node): string;

    /**
     * Pretty prints a file of statements (includes the opening <?php tag if it is required).
     *
     * @param list<Node> $stmts Array of statements
     *
     * @return string Pretty printed statements
     */
    public function prettyPrintFile(array $stmts): string;

    /**
     * Perform a format-preserving pretty print of an AST.
     *
     * The format preservation is best effort. For some changes to the AST the formatting will not
     * be preserved (at least not locally).
     *
     * In order to use this method a number of prerequisites must be satisfied:
     *  * The startTokenPos and endTokenPos attributes in the lexer must be enabled.
     *  * The CloningVisitor must be run on the AST prior to modification.
     *  * The original tokens must be provided, using the getTokens() method on the lexer.
     *
     * @param list<Node> $stmts Modified AST with links to original AST
     * @param list<Node> $origStmts Original AST with token offset information
     * @param list<Token> $origTokens Tokens of the original code
     */
    public function printFormatPreserving(array $stmts, array $origStmts, array $origTokens): string;
}
