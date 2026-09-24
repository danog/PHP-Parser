<?php declare(strict_types=1);

namespace PhpParser\Parser;

use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\ErrorHandler;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeAttributes;

/**
 * Thrown by the descent parser when the input does not match the grammar; parse() then falls back to
 * the table parser, which produces the diagnostics.
 */
final class DescentFail extends \RuntimeException {
}

/**
 * Hand-written recursive-descent parser for PHP 8 producing exactly the AST (nodes, attributes, error
 * messages) of the generated LALR parser in Php8. The grammar it follows is grammar/php.y; rule comments
 * below name the productions they implement.
 *
 * The table parser remains the fallback: any input the descent parser rejects is re-parsed with it, so
 * error recovery and error messages come from one place only.
 */
class Descent extends Php8 {
    /** Number of parses that fell back to the table parser (diagnostics for harnesses). */
    public static int $fallbacks = 0;
    public static ?string $lastError = null;

    // single-character token ids
    private const int SEMI = 59;        // ;
    private const int COMMA = 44;       // ,
    private const int LPAREN = 40;      // (
    private const int RPAREN = 41;      // )
    private const int LBRACKET = 91;    // [
    private const int RBRACKET = 93;    // ]
    private const int LBRACE = 123;     // {
    private const int RBRACE = 125;     // }
    private const int COLON = 58;       // :
    private const int QUESTION = 63;    // ?
    private const int EQUALS = 61;      // =
    private const int DOLLAR = 36;      // $
    private const int DQUOTE = 34;      // "
    private const int BACKTICK = 96;    // `
    private const int PLUS = 43;
    private const int MINUS = 45;
    private const int MUL = 42;
    private const int DIV = 47;
    private const int MOD = 37;
    private const int DOT = 46;
    private const int PIPE = 124;
    private const int CARET = 94;
    private const int LT = 60;
    private const int GT = 62;
    private const int NOT = 33;         // !
    private const int TILDE = 126;
    private const int AT = 64;

    // primary kinds (which postfix operations the grammar allows on the node)
    private const int K_NONE = 0;       // plain expr
    private const int K_VAR = 1;        // variable
    private const int K_DEREF = 2;      // fully_dereferenceable that is not a variable
    private const int K_CONST = 3;      // constant
    private const int K_CLASSNAME = 4;  // class_name awaiting ::

    // precedence levels (grammar/php.y, PHP 8 section), higher binds tighter
    private const int P_VOID_CAST = 1;
    private const int P_THROW = 2;
    private const int P_INCLUDE = 3;
    private const int P_LOGICAL_OR = 5;
    private const int P_LOGICAL_XOR = 6;
    private const int P_LOGICAL_AND = 7;
    private const int P_PRINT = 8;
    private const int P_YIELD = 9;
    private const int P_DOUBLE_ARROW = 10;
    private const int P_YIELD_FROM = 11;
    private const int P_ASSIGN = 12;
    private const int P_TERNARY = 13;
    private const int P_COALESCE = 14;
    private const int P_BOOLEAN_OR = 15;
    private const int P_BOOLEAN_AND = 16;
    private const int P_BIT_OR = 17;
    private const int P_BIT_XOR = 18;
    private const int P_BIT_AND = 19;
    private const int P_EQUALITY = 20;
    private const int P_COMPARISON = 21;
    private const int P_PIPE = 22;
    private const int P_CONCAT = 23;
    private const int P_SHIFT = 24;
    private const int P_ADD = 25;
    private const int P_MUL = 26;
    private const int P_NOT = 27;
    private const int P_INSTANCEOF = 28;
    private const int P_UNARY = 29;
    private const int P_POW = 30;
    private const int P_NEW = 32;

    /** @var array<int, bool> levels that are %right */
    private const array RIGHT_ASSOC = [
        self::P_VOID_CAST => true, self::P_THROW => true, self::P_PRINT => true, self::P_YIELD => true,
        self::P_DOUBLE_ARROW => true, self::P_YIELD_FROM => true, self::P_COALESCE => true,
        self::P_NOT => true, self::P_UNARY => true, self::P_POW => true,
    ];
    /** @var array<int, bool> levels that are %nonassoc */
    private const array NONASSOC = [self::P_EQUALITY => true, self::P_COMPARISON => true];

    /** The precedence level of a binary operator token, or -1 when the token is not one (a match, not a table lookup: this runs once per token of every expression). */
    private static function binaryPrec(int $id): int {
        return match ($id) {
            \T_BOOLEAN_OR => self::P_BOOLEAN_OR,
            \T_BOOLEAN_AND => self::P_BOOLEAN_AND,
            \T_LOGICAL_OR => self::P_LOGICAL_OR,
            \T_LOGICAL_AND => self::P_LOGICAL_AND,
            \T_LOGICAL_XOR => self::P_LOGICAL_XOR,
            self::PIPE => self::P_BIT_OR,
            \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => self::P_BIT_AND,
            self::CARET => self::P_BIT_XOR,
            self::DOT => self::P_CONCAT,
            self::PLUS, self::MINUS => self::P_ADD,
            self::MUL, self::DIV, self::MOD => self::P_MUL,
            \T_SL, \T_SR => self::P_SHIFT,
            \T_POW => self::P_POW,
            \T_IS_IDENTICAL, \T_IS_NOT_IDENTICAL, \T_IS_EQUAL, \T_IS_NOT_EQUAL, \T_SPACESHIP => self::P_EQUALITY,
            self::LT, \T_IS_SMALLER_OR_EQUAL, self::GT, \T_IS_GREATER_OR_EQUAL => self::P_COMPARISON,
            \T_PIPE => self::P_PIPE,
            \T_COALESCE => self::P_COALESCE,
            \T_INSTANCEOF => self::P_INSTANCEOF,
            self::QUESTION => self::P_TERNARY,
            default => -1,
        };
    }

    /** @var array<int, bool> compound assignment tokens */
    private const array ASSIGN_OPS = [
        \T_PLUS_EQUAL => true, \T_MINUS_EQUAL => true, \T_MUL_EQUAL => true, \T_DIV_EQUAL => true,
        \T_CONCAT_EQUAL => true, \T_MOD_EQUAL => true, \T_AND_EQUAL => true, \T_OR_EQUAL => true,
        \T_XOR_EQUAL => true, \T_SL_EQUAL => true, \T_SR_EQUAL => true, \T_POW_EQUAL => true,
        \T_COALESCE_EQUAL => true,
    ];

    /** @var array<int, bool> reserved_non_modifiers */
    private const array RESERVED_NON_MODIFIERS = [
        \T_INCLUDE => true, \T_INCLUDE_ONCE => true, \T_EVAL => true, \T_REQUIRE => true, \T_REQUIRE_ONCE => true,
        \T_LOGICAL_OR => true, \T_LOGICAL_XOR => true, \T_LOGICAL_AND => true, \T_INSTANCEOF => true, \T_NEW => true,
        \T_CLONE => true, \T_EXIT => true, \T_IF => true, \T_ELSEIF => true, \T_ELSE => true, \T_ENDIF => true,
        \T_DO => true, \T_WHILE => true, \T_ENDWHILE => true, \T_FOR => true, \T_ENDFOR => true, \T_FOREACH => true,
        \T_ENDFOREACH => true, \T_DECLARE => true, \T_ENDDECLARE => true, \T_AS => true, \T_TRY => true,
        \T_CATCH => true, \T_FINALLY => true, \T_THROW => true, \T_USE => true, \T_INSTEADOF => true,
        \T_GLOBAL => true, \T_VAR => true, \T_UNSET => true, \T_ISSET => true, \T_EMPTY => true,
        \T_CONTINUE => true, \T_GOTO => true, \T_FUNCTION => true, \T_CONST => true, \T_RETURN => true,
        \T_PRINT => true, \T_YIELD => true, \T_LIST => true, \T_SWITCH => true, \T_ENDSWITCH => true,
        \T_CASE => true, \T_DEFAULT => true, \T_BREAK => true, \T_ARRAY => true, \T_CALLABLE => true,
        \T_EXTENDS => true, \T_IMPLEMENTS => true, \T_NAMESPACE => true, \T_TRAIT => true, \T_INTERFACE => true,
        \T_CLASS => true, \T_CLASS_C => true, \T_TRAIT_C => true, \T_FUNC_C => true, \T_METHOD_C => true,
        \T_LINE => true, \T_FILE => true, \T_DIR => true, \T_NS_C => true, \T_FN => true, \T_MATCH => true,
        \T_ENUM => true, \T_ECHO => true,
    ];

    /** @var array<int, bool> semi_reserved minus reserved_non_modifiers */
    private const array MODIFIER_WORDS = [
        \T_STATIC => true, \T_ABSTRACT => true, \T_FINAL => true, \T_PRIVATE => true, \T_PROTECTED => true,
        \T_PUBLIC => true, \T_READONLY => true,
    ];

    /** @var array<int, int> member_modifier token => flag */
    private const array MEMBER_MODIFIERS = [
        \T_PUBLIC => Modifiers::PUBLIC, \T_PROTECTED => Modifiers::PROTECTED, \T_PRIVATE => Modifiers::PRIVATE,
        \T_PUBLIC_SET => Modifiers::PUBLIC_SET, \T_PROTECTED_SET => Modifiers::PROTECTED_SET,
        \T_PRIVATE_SET => Modifiers::PRIVATE_SET, \T_STATIC => Modifiers::STATIC, \T_ABSTRACT => Modifiers::ABSTRACT,
        \T_FINAL => Modifiers::FINAL, \T_READONLY => Modifiers::READONLY,
    ];

    /** @var array<int, int> property_modifier token => flag (parameters) */
    private const array PROPERTY_MODIFIERS = [
        \T_PUBLIC => Modifiers::PUBLIC, \T_PROTECTED => Modifiers::PROTECTED, \T_PRIVATE => Modifiers::PRIVATE,
        \T_PUBLIC_SET => Modifiers::PUBLIC_SET, \T_PROTECTED_SET => Modifiers::PROTECTED_SET,
        \T_PRIVATE_SET => Modifiers::PRIVATE_SET, \T_READONLY => Modifiers::READONLY, \T_FINAL => Modifiers::FINAL,
    ];

    /** @var array<int, int> class_modifier token => flag */
    private const array CLASS_MODIFIERS = [
        \T_ABSTRACT => Modifiers::ABSTRACT, \T_FINAL => Modifiers::FINAL, \T_READONLY => Modifiers::READONLY,
    ];

    /** @var array<int, bool> magic constant tokens */
    private const array MAGIC_CONSTS = [
        \T_LINE => true, \T_FILE => true, \T_DIR => true, \T_CLASS_C => true, \T_TRAIT_C => true,
        \T_METHOD_C => true, \T_FUNC_C => true, \T_NS_C => true, \T_PROPERTY_C => true,
    ];

    /** @var array<int, bool> tokens that can begin an expr */
    private const array EXPR_START = [
        \T_VARIABLE => true, self::DOLLAR => true, \T_STRING => true, \T_NAME_QUALIFIED => true,
        \T_NAME_FULLY_QUALIFIED => true, \T_NAME_RELATIVE => true, \T_STATIC => true, \T_READONLY => true,
        \T_LINE => true, \T_FILE => true, \T_DIR => true, \T_CLASS_C => true, \T_TRAIT_C => true,
        \T_METHOD_C => true, \T_FUNC_C => true, \T_NS_C => true, \T_PROPERTY_C => true,
        \T_LNUMBER => true, \T_DNUMBER => true, \T_CONSTANT_ENCAPSED_STRING => true, self::DQUOTE => true,
        self::BACKTICK => true, \T_START_HEREDOC => true, \T_ARRAY => true, self::LBRACKET => true,
        self::LPAREN => true, \T_NEW => true, \T_LIST => true, \T_MATCH => true, \T_FUNCTION => true,
        \T_FN => true, \T_ATTRIBUTE => true, \T_ISSET => true, \T_EMPTY => true, \T_EVAL => true,
        \T_EXIT => true, \T_INCLUDE => true, \T_INCLUDE_ONCE => true, \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true, \T_CLONE => true, \T_PRINT => true, \T_YIELD => true, \T_YIELD_FROM => true,
        \T_THROW => true, \T_INT_CAST => true, \T_DOUBLE_CAST => true, \T_STRING_CAST => true,
        \T_ARRAY_CAST => true, \T_OBJECT_CAST => true, \T_BOOL_CAST => true, \T_UNSET_CAST => true,
        \T_VOID_CAST => true, self::PLUS => true, self::MINUS => true, self::NOT => true, self::TILDE => true,
        self::AT => true, \T_INC => true, \T_DEC => true,
    ];

    /** @var array<int, bool> prefix operators whose precedence is below T_YIELD: `yield` does not take them as operand */
    private const array BELOW_YIELD = [
        \T_PRINT => true, \T_THROW => true, \T_INCLUDE => true, \T_INCLUDE_ONCE => true, \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true, \T_VOID_CAST => true,
    ];

    /** @var array<int, NodeAttributes> start token => attributes of the outermost node built there so far */
    private array $commentHolders = [];
    /** @var array<int, list<Comment>> start token => the comments directly before it */
    private array $pendingComments = [];
    private bool $fellBack = false;

    private int $pos = 0;     // index of the lookahead token
    private int $id = 0;      // its id (T_CLOSE_TAG read as ';', T_OPEN_TAG_WITH_ECHO as T_ECHO)
    private int $last = -1;   // index of the last consumed token
    private int $kind = 0;    // kind of the last primary parsed

    public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array {
        $this->errorHandler = $errorHandler ?: new ErrorHandler\Throwing();
        $this->createdArrays = new \SplObjectStorage();
        $this->parenthesizedArrowFunctions = new \SplObjectStorage();
        $this->tokens = $this->lexer->tokenize($code, $this->errorHandler);
        $this->fellBack = false;
        // a parse that ends in a thrown Error leaves the comment state behind: start clean
        $this->commentHolders = [];
        $this->pendingComments = [];
        $result = $this->doParse();
        $createdArrays = $this->createdArrays;
        if ($createdArrays !== null) {
            foreach ($createdArrays as $node) {
                foreach ($node->items as $item) {
                    if ($item !== null && $item->value instanceof Expr\Error) {
                        $this->errorHandler->handleError(
                            new Error('Cannot use empty array elements in arrays', $item->getAttributes()));
                    }
                }
            }
        }
        $this->tokenStartStack = [];
        $this->tokenEndStack = [];
        $this->semStack = [];
        $this->semValue = null;
        $this->createdArrays = null;
        $this->parenthesizedArrowFunctions = null;
        $this->commentHolders = [];
        $this->pendingComments = [];
        if ($result !== null && $this->fellBack) {
            // the table parser does not attach comments while parsing
            $traverser = new \PhpParser\NodeTraverser(new \PhpParser\NodeVisitor\CommentAnnotatingVisitor($this->tokens));
            $traverser->traverse($result);
        }
        return $result;
    }

    protected function doParse(): ?array {
        $realHandler = $this->errorHandler;
        $collecting = new ErrorHandler\Collecting();
        $this->errorHandler = $collecting;
        $this->tokenStartStack = [0];
        $this->tokenEndStack = [0];
        $this->pos = -1;
        $this->last = -1;
        $this->advance();
        try {
            $stmts = $this->topStatementList(0);
            $result = $this->handleNamespaces($stmts);
        } catch (DescentFail | Error $e) {
            self::$fallbacks++;
            $this->fellBack = true;
            self::$lastError = $e->getMessage();
            $this->errorHandler = $realHandler;
            $this->createdArrays = new \SplObjectStorage();
            $this->parenthesizedArrowFunctions = new \SplObjectStorage();
            return parent::doParse();
        }
        $this->errorHandler = $realHandler;
        foreach ($this->commentHolders as $start => $attrs) {
            $attrs->comments = $this->pendingComments[$start];
        }
        foreach ($collecting->getErrors() as $error) {
            $realHandler->handleError($error);
        }
        return $result;
    }

    // ---- token cursor ----

    private function advance(): void {
        $this->last = $this->pos;
        $p = $this->pos;
        $tokens = $this->tokens;
        do {
            $id = $tokens[++$p]->id;
        } while (isset($this->dropTokens[$id]));
        $this->pos = $p;
        if ($id === \T_CLOSE_TAG) {
            $id = self::SEMI;
        } elseif ($id === \T_OPEN_TAG_WITH_ECHO) {
            $id = \T_ECHO;
        }
        $this->id = $id;
    }

    /** Id of the k-th significant token after the lookahead. */
    private function peek(int $k): int {
        $p = $this->pos;
        $tokens = $this->tokens;
        $id = 0;
        while ($k-- > 0) {
            do {
                if (!isset($tokens[++$p])) {
                    return 0;
                }
                $id = $tokens[$p]->id;
            } while (isset($this->dropTokens[$id]));
        }
        if ($id === \T_CLOSE_TAG) {
            return self::SEMI;
        }
        if ($id === \T_OPEN_TAG_WITH_ECHO) {
            return \T_ECHO;
        }
        return $id;
    }

    private function fail(string $what): never {
        $token = $this->tokens[$this->pos];
        throw new DescentFail(sprintf('%s, got token %d (%s) on line %d', $what, $token->id, $token->text, $token->line));
    }

    private function expect(int $id): void {
        if ($this->id !== $id) {
            $this->fail('Expected token ' . $id);
        }
        $this->advance();
    }

    private function is(int $id): bool {
        return $this->id === $id;
    }

    private function accept(int $id): bool {
        if ($this->id === $id) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function text(): string {
        return $this->tokens[$this->pos]->text;
    }

    /** Attributes of the construct that started at token $start and ended at the last consumed token. */
    private function attrs(int $start): NodeAttributes {
        $attrs = $this->getAttributes($start, $this->last);
        if ($start > 0) {
            $id = $this->tokens[$start - 1]->id;
            if ($id === \T_WHITESPACE || $id === \T_COMMENT || $id === \T_DOC_COMMENT) {
                $this->noteAttrs($attrs, $start);
            }
        }
        return $attrs;
    }

    private function tokAttrs(int $pos): NodeAttributes {
        $attrs = $this->getAttributes($pos, $pos);
        if ($pos > 0) {
            $id = $this->tokens[$pos - 1]->id;
            if ($id === \T_WHITESPACE || $id === \T_COMMENT || $id === \T_DOC_COMMENT) {
                $this->noteAttrs($attrs, $pos);
            }
        }
        return $attrs;
    }

    /**
     * Comment attachment, done here instead of by CommentAnnotatingVisitor after the parse: the comments
     * directly before a node (only whitespace between) belong to the outermost node starting there. Nodes
     * are built inside out, so the newest attributes at a start position become the holder, and the
     * comments are written into the holders once the parse is complete.
     */
    private function noteAttrs(NodeAttributes $attrs, int $start): void {
        if (isset($this->pendingComments[$start])) {
            $this->commentHolders[$start] = $attrs;
            return;
        }
        $comments = [];
        $pos = $start;
        while (--$pos >= 0) {
            $token = $this->tokens[$pos];
            $id = $token->id;
            if ($id === \T_DOC_COMMENT) {
                $comments[] = new Comment\Doc($token->text, $token->line, $token->pos, $pos,
                    $token->getEndLine(), $token->getEndPos() - 1, $pos);
            } elseif ($id === \T_COMMENT) {
                $comments[] = new Comment($token->text, $token->line, $token->pos, $pos,
                    $token->getEndLine(), $token->getEndPos() - 1, $pos);
            } elseif ($id !== \T_WHITESPACE) {
                break;
            }
        }
        if ($comments !== []) {
            $this->pendingComments[$start] = array_reverse($comments);
            $this->commentHolders[$start] = $attrs;
        }
    }

    /** A node built from another one at the same start takes over as comment holder. */
    private function rehold(NodeAttributes $old, NodeAttributes $new): void {
        $start = $old->startTokenPos ?? -1;
        if (($this->commentHolders[$start] ?? null) === $old) {
            $this->commentHolders[$start] = $new;
        }
    }

    protected function handleBuiltinTypes(Name $name) {
        $type = parent::handleBuiltinTypes($name);
        if ($type !== $name) {
            $this->rehold($name->attrs(), $type->attrs());
        }
        return $type;
    }

    protected function fixupArrayDestructuring(Expr\Array_ $node): Expr\List_ {
        if ($this->createdArrays !== null) {
            $this->createdArrays->offsetUnset($node);
        }
        $items = [];
        foreach ($node->items as $item) {
            if ($item === null || $item->value instanceof Expr\Error) {
                $items[] = null;
            } elseif ($item->value instanceof Expr\Array_) {
                $inner = $this->fixupArrayDestructuring($item->value);
                $itemAttrs = $item->getAttributes();
                $this->rehold($item->attrs(), $itemAttrs);
                $items[] = new Node\ArrayItem($inner, $item->key, $item->byRef, $itemAttrs);
            } else {
                $items[] = $item;
            }
        }
        $listAttrs = $node->getAttributes();
        $listAttrs->kind = Expr\List_::KIND_ARRAY;
        $this->rehold($node->attrs(), $listAttrs);
        return new Expr\List_($items, $listAttrs);
    }

    /** A synthetic stack slot for the ParserAbstract helpers that take stack positions. */
    private function slot(int $start, int $end): int {
        $this->tokenStartStack[0] = $start;
        $this->tokenEndStack[0] = $end;
        return 0;
    }

    private function semi(): void {
        if ($this->id !== self::SEMI) {
            $this->fail('Expected ;');
        }
        $this->advance();
    }

    /** no_comma: a trailing comma is reported but consumed */
    private function noComma(): void {
        if ($this->is(self::COMMA)) {
            $this->emitError(new Error('A trailing comma is not allowed here', $this->tokAttrs($this->pos)));
            $this->advance();
        }
    }

    private static function isAmpersand(int $id): bool {
        return $id === \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $id === \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    private function isIdentifierMaybeReserved(int $id): bool {
        return $id === \T_STRING || isset(self::RESERVED_NON_MODIFIERS[$id]) || isset(self::MODIFIER_WORDS[$id]);
    }

    private function isNameToken(int $id): bool {
        return $id === \T_STRING || $id === \T_NAME_QUALIFIED || $id === \T_NAME_FULLY_QUALIFIED
            || $id === \T_NAME_RELATIVE;
    }

    // ---- statement lists ----

    /** top_statement_list up to $end (0 = end of file, '}' = braced namespace) */
    private function zeroLengthNop(): ?Stmt\Nop {
        $nop = $this->maybeCreateZeroLengthNop($this->pos);
        if ($nop !== null) {
            $this->noteAttrs($nop->attrs(), $nop->attrs()->startTokenPos ?? 0);
        }
        return $nop;
    }

    /** @return list<Stmt> */
    private function topStatementList(int $end): array {
        $stmts = [];
        while (!$this->is($end)) {
            if ($this->is(0)) {
                $this->fail('Unexpected end of file');
            }
            $stmt = $this->topStatement();
            if ($stmt !== null) {
                $stmts[] = $stmt;
            }
        }
        $nop = $this->zeroLengthNop();
        if ($nop !== null) {
            $stmts[] = $nop;
        }
        return $stmts;
    }

    /** inner_statement_list, ending at any of $ends (token id => true) */
    /**
     * @param array<int, bool> $ends
     * @return list<Stmt>
     */
    private function innerStatementList(array $ends): array {
        $stmts = $this->innerStatementListEx($ends);
        $nop = $this->zeroLengthNop();
        if ($nop !== null) {
            $stmts[] = $nop;
        }
        return $stmts;
    }

    private const array END_BRACE = [self::RBRACE => true];

    /**
     * @param array<int, bool> $ends
     * @return list<Stmt>
     */
    private function innerStatementListEx(array $ends): array {
        $stmts = [];
        while (!isset($ends[$this->id])) {
            if ($this->is(0)) {
                $this->fail('Unexpected end of file');
            }
            $stmt = $this->innerStatement();
            if ($stmt !== null) {
                $stmts[] = $stmt;
            }
        }
        return $stmts;
    }

    /** @return list<Stmt> */
    private function block(): array {
        $this->expect(self::LBRACE);
        $stmts = $this->innerStatementList(self::END_BRACE);
        $this->expect(self::RBRACE);
        return $stmts;
    }

    private function topStatement(): ?Stmt {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case \T_HALT_COMPILER:
                $this->advance();
                $this->expect(self::LPAREN);
                $this->expect(self::RPAREN);
                $this->expect(self::SEMI);
                // handleHaltCompiler: the rest of the file is the payload
                $next = $this->tokens[$this->last + 1];
                $this->pos = \count($this->tokens) - 1;
                $this->id = 0;
                return new Stmt\HaltCompiler($next->id === \T_INLINE_HTML ? $next->text : '', $this->attrs($start));
            case \T_NAMESPACE:
                $this->advance();
                if ($this->is(self::LBRACE)) {
                    $this->advance();
                    $stmts = $this->topStatementList(self::RBRACE);
                    $this->expect(self::RBRACE);
                    $ns = new Stmt\Namespace_(null, $stmts, $this->attrs($start));
                    $ns->attrs()->kind = Stmt\Namespace_::KIND_BRACED;
                    $this->checkNamespace($ns);
                    return $ns;
                }
                $id = $this->id;
                if ($id !== \T_STRING && $id !== \T_NAME_QUALIFIED && !isset(self::RESERVED_NON_MODIFIERS[$id])
                    && !isset(self::MODIFIER_WORDS[$id])) {
                    $this->fail('Expected namespace name');
                }
                $this->checkEchoIdentifier();
                $name = new Name($this->text(), $this->tokAttrs($this->pos));
                $this->advance();
                if ($this->is(self::LBRACE)) {
                    $this->advance();
                    $stmts = $this->topStatementList(self::RBRACE);
                    $this->expect(self::RBRACE);
                    $ns = new Stmt\Namespace_($name, $stmts, $this->attrs($start));
                    $ns->attrs()->kind = Stmt\Namespace_::KIND_BRACED;
                } else {
                    $this->semi();
                    $ns = new Stmt\Namespace_($name, null, $this->attrs($start));
                    $ns->attrs()->kind = Stmt\Namespace_::KIND_SEMICOLON;
                }
                $this->checkNamespace($ns);
                return $ns;
            case \T_USE:
                return $this->useStatement();
            case \T_CONST:
                $this->advance();
                $consts = $this->constantDeclarationList();
                $this->semi();
                return new Stmt\Const_($consts, $this->attrs($start), []);
            case \T_ATTRIBUTE:
                if ($this->attributedTopLevelIsConst()) {
                    $attrGroups = $this->attributes();
                    $this->expect(\T_CONST);
                    $consts = $this->constantDeclarationList();
                    $this->semi();
                    $const = new Stmt\Const_($consts, $this->attrs($start), $attrGroups);
                    $this->checkConstantAttributes($const);
                    return $const;
                }
                // fall through
            default:
                return $this->innerStatement();
        }
    }

    /** Whether the attribute groups at the lookahead are followed by T_CONST (scans balanced brackets). */
    private function attributedTopLevelIsConst(): bool {
        $p = $this->pos;
        $tokens = $this->tokens;
        $n = \count($tokens) - 1;
        while ($p < $n && $tokens[$p]->id === \T_ATTRIBUTE) {
            $depth = 1;
            while (++$p < $n && $depth > 0) {
                $id = $tokens[$p]->id;
                if ($id === self::LBRACKET || $id === \T_ATTRIBUTE) {
                    $depth++;
                } elseif ($id === self::RBRACKET) {
                    $depth--;
                }
            }
            while ($p < $n && isset($this->dropTokens[$tokens[$p]->id])) {
                $p++;
            }
        }
        return $p < $n && $tokens[$p]->id === \T_CONST;
    }

    private function innerStatement(): ?Stmt {
        $id = $this->id;
        switch ($id) {
            case \T_HALT_COMPILER:
                throw new Error('__HALT_COMPILER() can only be used from the outermost scope', $this->tokAttrs($this->pos));
            case \T_FUNCTION:
                if ($this->functionIsDeclaration(1)) {
                    return $this->functionDeclaration($this->pos, []);
                }
                return $this->statement();
            case \T_ABSTRACT:
            case \T_FINAL:
            case \T_CLASS:
                return $this->classDeclaration($this->pos, []);
            case \T_READONLY:
                if ($this->classAhead(1)) {
                    return $this->classDeclaration($this->pos, []);
                }
                return $this->statement();
            case \T_INTERFACE:
                return $this->interfaceDeclaration($this->pos, []);
            case \T_TRAIT:
                return $this->traitDeclaration($this->pos, []);
            case \T_ENUM:
                return $this->enumDeclaration($this->pos, []);
            case \T_ATTRIBUTE:
                $start = $this->pos;
                $attrGroups = $this->attributes();
                $id = $this->id;
                switch ($id) {
                    case \T_FUNCTION:
                        if ($this->functionIsDeclaration(1)) {
                            return $this->functionDeclaration($start, $attrGroups);
                        }
                        return $this->expressionStatementFrom($start, $this->closureWithAttrs($start, $attrGroups));
                    case \T_FN:
                    case \T_STATIC:
                        return $this->expressionStatementFrom($start, $this->closureWithAttrs($start, $attrGroups));
                    case \T_ABSTRACT:
                    case \T_FINAL:
                    case \T_READONLY:
                    case \T_CLASS:
                        return $this->classDeclaration($start, $attrGroups);
                    case \T_INTERFACE:
                        return $this->interfaceDeclaration($start, $attrGroups);
                    case \T_TRAIT:
                        return $this->traitDeclaration($start, $attrGroups);
                    case \T_ENUM:
                        return $this->enumDeclaration($start, $attrGroups);
                }
                $this->fail('Unexpected token after attributes');
                // no break
            default:
                return $this->statement();
        }
    }

    /** `function` at lookahead+$k-1: a declaration (named) rather than a closure? */
    private function functionIsDeclaration(int $k): bool {
        $id = $this->peek($k);
        if ($id === \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $id === \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
            $id = $this->peek($k + 1);
        }
        return $id !== self::LPAREN;
    }

    /** class modifiers starting at lookahead+$k are followed by T_CLASS */
    private function classAhead(int $k): bool {
        $id = $this->peek($k);
        while (isset(self::CLASS_MODIFIERS[$id])) {
            $id = $this->peek(++$k);
        }
        return $id === \T_CLASS;
    }

    private function expressionStatementFrom(int $start, Expr $expr): Stmt {
        $expr = $this->binaryLoop($expr, $start, 0);
        $this->semi();
        return new Stmt\Expression($expr, $this->attrs($start));
    }

    private function statement(): ?Stmt {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case self::LBRACE:
                $this->advance();
                $stmts = $this->innerStatementList(self::END_BRACE);
                $this->expect(self::RBRACE);
                return new Stmt\Block($stmts, $this->attrs($start));
            case self::SEMI:
                $this->advance();
                if ($this->getCommentBeforeToken($start) === null) {
                    return null;
                }
                return new Stmt\Nop($this->attrs($start));
            case \T_IF:
                return $this->ifStatement();
            case \T_WHILE:
                $this->advance();
                $this->expect(self::LPAREN);
                $cond = $this->expr(0);
                $this->expect(self::RPAREN);
                if ($this->is(self::COLON)) {
                    $this->advance();
                    $stmts = $this->innerStatementList([\T_ENDWHILE => true]);
                    $this->expect(\T_ENDWHILE);
                    $this->semi();
                } else {
                    $stmts = $this->blocklikeStatement();
                }
                return new Stmt\While_($cond, $stmts, $this->attrs($start));
            case \T_DO:
                $this->advance();
                $stmts = $this->blocklikeStatement();
                $this->expect(\T_WHILE);
                $this->expect(self::LPAREN);
                $cond = $this->expr(0);
                $this->expect(self::RPAREN);
                $this->expect(self::SEMI);
                return new Stmt\Do_($cond, $stmts, $this->attrs($start));
            case \T_FOR:
                $this->advance();
                $this->expect(self::LPAREN);
                $init = $this->forExpr(self::SEMI);
                $this->expect(self::SEMI);
                $cond = $this->forExpr(self::SEMI);
                $this->expect(self::SEMI);
                $loop = $this->forExpr(self::RPAREN);
                $this->expect(self::RPAREN);
                if ($this->is(self::COLON)) {
                    $this->advance();
                    $stmts = $this->innerStatementList([\T_ENDFOR => true]);
                    $this->expect(\T_ENDFOR);
                    $this->semi();
                } else {
                    $stmts = $this->blocklikeStatement();
                }
                return new Stmt\For_(['init' => $init, 'cond' => $cond, 'loop' => $loop, 'stmts' => $stmts], $this->attrs($start));
            case \T_SWITCH:
                return $this->switchStatement();
            case \T_BREAK:
                $this->advance();
                $num = $this->optionalExpr();
                $this->semi();
                return new Stmt\Break_($num, $this->attrs($start));
            case \T_CONTINUE:
                $this->advance();
                $num = $this->optionalExpr();
                $this->semi();
                return new Stmt\Continue_($num, $this->attrs($start));
            case \T_RETURN:
                $this->advance();
                $expr = $this->optionalExpr();
                $this->semi();
                return new Stmt\Return_($expr, $this->attrs($start));
            case \T_GLOBAL:
                $this->advance();
                $vars = [];
                do {
                    $vars[] = $this->simpleVariable();
                } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
                $this->noComma();
                $this->semi();
                return new Stmt\Global_($vars, $this->attrs($start));
            case \T_STATIC:
                if ($this->peek(1) !== \T_VARIABLE) {
                    break;
                }
                $this->advance();
                $vars = [];
                do {
                    $vstart = $this->pos;
                    $var = $this->plainVariable();
                    $default = $this->accept(self::EQUALS) ? $this->expr(0) : null;
                    $vars[] = new Stmt\StaticVar($var, $default, $this->attrs($vstart));
                } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
                $this->noComma();
                $this->semi();
                return new Stmt\Static_($vars, $this->attrs($start));
            case \T_ECHO:
                $this->advance();
                $exprs = $this->exprListForbidComma();
                $this->semi();
                return new Stmt\Echo_($exprs, $this->attrs($start));
            case \T_INLINE_HTML:
                $text = $this->text();
                $this->advance();
                $html = new Stmt\InlineHTML($text, $this->attrs($start));
                if ($start > 0) {
                    $prev = $this->tokens[$start - 1];
                    $html->attrs()->hasLeadingNewline = false !== strpos($prev->text, "\n") || false !== strpos($prev->text, "\r");
                } else {
                    $html->attrs()->hasLeadingNewline = true;
                }
                return $html;
            case \T_UNSET:
                $this->advance();
                $this->expect(self::LPAREN);
                $vars = [];
                do {
                    $vars[] = $this->variable();
                } while ($this->accept(self::COMMA) && !$this->is(self::RPAREN));
                $this->expect(self::RPAREN);
                $this->semi();
                return new Stmt\Unset_($vars, $this->attrs($start));
            case \T_FOREACH:
                return $this->foreachStatement();
            case \T_DECLARE:
                $this->advance();
                $this->expect(self::LPAREN);
                $declares = [];
                do {
                    $dstart = $this->pos;
                    $key = $this->identifierNotReserved();
                    $this->expect(self::EQUALS);
                    $declares[] = new Node\DeclareItem($key, $this->expr(0), $this->attrs($dstart));
                } while ($this->is(self::COMMA) && $this->peek(1) !== self::RPAREN && $this->accept(self::COMMA));
                $this->noComma();
                $this->expect(self::RPAREN);
                if ($this->is(self::SEMI)) {
                    $this->advance();
                    $stmts = null;
                } elseif ($this->is(self::COLON)) {
                    $this->advance();
                    $stmts = $this->innerStatementList([\T_ENDDECLARE => true]);
                    $this->expect(\T_ENDDECLARE);
                    $this->semi();
                } else {
                    $stmts = $this->toBlock($this->statement());
                }
                return new Stmt\Declare_($declares, $stmts, $this->attrs($start));
            case \T_TRY:
                $this->advance();
                $stmts = $this->block();
                $catches = [];
                while ($this->is(\T_CATCH)) {
                    $cstart = $this->pos;
                    $this->advance();
                    $this->expect(self::LPAREN);
                    $types = [$this->name()];
                    while ($this->accept(self::PIPE)) {
                        $types[] = $this->name();
                    }
                    $var = $this->is(\T_VARIABLE) ? $this->plainVariable() : null;
                    $this->expect(self::RPAREN);
                    $body = $this->block();
                    $catches[] = new Stmt\Catch_($types, $var, $body, $this->attrs($cstart));
                }
                $finally = null;
                if ($this->is(\T_FINALLY)) {
                    $fstart = $this->pos;
                    $this->advance();
                    $body = $this->block();
                    $finally = new Stmt\Finally_($body, $this->attrs($fstart));
                }
                $try = new Stmt\TryCatch($stmts, $catches, $finally, $this->attrs($start));
                $this->checkTryCatch($try);
                return $try;
            case \T_GOTO:
                $this->advance();
                $name = $this->identifierNotReserved();
                $this->semi();
                return new Stmt\Goto_($name, $this->attrs($start));
            case \T_STRING:
                if ($this->peek(1) === self::COLON) {
                    $name = $this->identifierNotReserved();
                    $this->advance();
                    return new Stmt\Label($name, $this->attrs($start));
                }
                break;
        }
        $expr = $this->expr(0);
        $this->semi();
        return new Stmt\Expression($expr, $this->attrs($start));
    }

    /** @return list<Stmt> */
    /** @return list<Stmt> */
    private function toBlock(?Stmt $stmt): array {
        if ($stmt instanceof Stmt\Block) {
            return $stmt->stmts;
        }
        if ($stmt === null) {
            return [];
        }
        return [$stmt];
    }

    /** @return list<Stmt> */
    private function blocklikeStatement(): array {
        return $this->toBlock($this->statement());
    }

    private function optionalExpr(): ?Expr {
        if ($this->is(self::SEMI)) {
            return null;
        }
        return $this->expr(0);
    }

    /** @return list<Expr> */
    private function forExpr(int $end): array {
        if ($this->is($end)) {
            return [];
        }
        return $this->exprListForbidComma();
    }

    /** @return list<Expr> */
    private function exprListForbidComma(): array {
        $exprs = [$this->expr(0)];
        while ($this->is(self::COMMA)) {
            if (!isset(self::EXPR_START[$this->peek(1)])) {
                break;
            }
            $this->advance();
            $exprs[] = $this->expr(0);
        }
        $this->noComma();
        return $exprs;
    }

    private function ifStatement(): Stmt {
        $start = $this->pos;
        $this->advance();
        $this->expect(self::LPAREN);
        $cond = $this->expr(0);
        $this->expect(self::RPAREN);
        if ($this->is(self::COLON)) {
            $this->advance();
            $ends = [\T_ELSEIF => true, \T_ELSE => true, \T_ENDIF => true];
            $stmts = $this->innerStatementList($ends);
            $elseifs = [];
            while ($this->is(\T_ELSEIF)) {
                $estart = $this->pos;
                $this->advance();
                $this->expect(self::LPAREN);
                $econd = $this->expr(0);
                $this->expect(self::RPAREN);
                $this->expect(self::COLON);
                $estmts = $this->innerStatementList($ends);
                $elseif = new Stmt\ElseIf_($econd, $estmts, $this->attrs($estart));
                $this->fixupAlternativeElse($elseif);
                $elseifs[] = $elseif;
            }
            $else = null;
            if ($this->is(\T_ELSE)) {
                $estart = $this->pos;
                $this->advance();
                $this->expect(self::COLON);
                $estmts = $this->innerStatementList([\T_ENDIF => true]);
                $else = new Stmt\Else_($estmts, $this->attrs($estart));
                $this->fixupAlternativeElse($else);
            }
            $this->expect(\T_ENDIF);
            $this->semi();
            return new Stmt\If_($cond, ['stmts' => $stmts, 'elseifs' => $elseifs, 'else' => $else], $this->attrs($start));
        }
        $stmts = $this->blocklikeStatement();
        $elseifs = [];
        while ($this->is(\T_ELSEIF)) {
            $estart = $this->pos;
            $this->advance();
            $this->expect(self::LPAREN);
            $econd = $this->expr(0);
            $this->expect(self::RPAREN);
            $estmts = $this->blocklikeStatement();
            $elseifs[] = new Stmt\ElseIf_($econd, $estmts, $this->attrs($estart));
        }
        $else = null;
        if ($this->is(\T_ELSE)) {
            $estart = $this->pos;
            $this->advance();
            $estmts = $this->blocklikeStatement();
            $else = new Stmt\Else_($estmts, $this->attrs($estart));
        }
        return new Stmt\If_($cond, ['stmts' => $stmts, 'elseifs' => $elseifs, 'else' => $else], $this->attrs($start));
    }

    private function switchStatement(): Stmt {
        $start = $this->pos;
        $this->advance();
        $this->expect(self::LPAREN);
        $cond = $this->expr(0);
        $this->expect(self::RPAREN);
        if ($this->is(self::COLON)) {
            $this->advance();
            $end = \T_ENDSWITCH;
        } else {
            $this->expect(self::LBRACE);
            $end = self::RBRACE;
        }
        $this->accept(self::SEMI);
        $cases = [];
        $ends = [\T_CASE => true, \T_DEFAULT => true, $end => true];
        while (!$this->is($end)) {
            $cstart = $this->pos;
            if ($this->is(\T_CASE)) {
                $this->advance();
                $ccond = $this->expr(0);
            } elseif ($this->is(\T_DEFAULT)) {
                $this->advance();
                $ccond = null;
            } else {
                $this->fail('Expected case or default');
            }
            if (!$this->is(self::COLON) && !$this->is(self::SEMI)) {
                $this->fail('Expected case separator');
            }
            $this->advance();
            $stmts = $this->innerStatementListEx($ends);
            $cases[] = new Stmt\Case_($ccond, $stmts, $this->attrs($cstart));
        }
        $this->advance();
        if ($end === \T_ENDSWITCH) {
            $this->semi();
        }
        return new Stmt\Switch_($cond, $cases, $this->attrs($start));
    }

    private function foreachStatement(): Stmt {
        $start = $this->pos;
        $this->advance();
        $this->expect(self::LPAREN);
        $expr = $this->expr(0);
        $this->expect(\T_AS);
        $keyVar = null;
        [$valueVar, $byRef] = $this->foreachVariable();
        if ($this->is(\T_DOUBLE_ARROW)) {
            if ($byRef || !($this->kind === self::K_VAR)) {
                $this->fail('Foreach key must be a variable');
            }
            $this->advance();
            $keyVar = $valueVar;
            [$valueVar, $byRef] = $this->foreachVariable();
        }
        $this->expect(self::RPAREN);
        if ($this->is(self::COLON)) {
            $this->advance();
            $stmts = $this->innerStatementList([\T_ENDFOREACH => true]);
            $this->expect(\T_ENDFOREACH);
            $this->semi();
        } else {
            $stmts = $this->blocklikeStatement();
        }
        return new Stmt\Foreach_($expr, $valueVar, ['keyVar' => $keyVar, 'byRef' => $byRef, 'stmts' => $stmts], $this->attrs($start));
    }

    /** foreach_variable; sets $this->kind to K_VAR when the result is a plain variable */
    /** @return array{Expr, bool} */
    private function foreachVariable(): array {
        if (self::isAmpersand($this->id)) {
            $this->advance();
            $var = $this->variable();
            $this->kind = self::K_NONE;
            return [$var, true];
        }
        if ($this->is(\T_LIST)) {
            $list = $this->listExpr();
            $this->kind = self::K_NONE;
            return [$list, false];
        }
        if ($this->is(self::LBRACKET)) {
            $start = $this->pos;
            $array = $this->shortArray();
            $id = $this->id;
            if ($id === self::LBRACKET || $id === \T_OBJECT_OPERATOR || $id === \T_NULLSAFE_OBJECT_OPERATOR
                || $id === \T_PAAMAYIM_NEKUDOTAYIM || $id === self::LPAREN) {
                $this->createdArrays->offsetSet($array);
                $this->kind = self::K_DEREF;
                $var = $this->postfixChain($array, $start);
                if ($this->kind !== self::K_VAR) {
                    $this->fail('Expected variable');
                }
                return [$var, false];
            }
            $this->kind = self::K_NONE;
            return [$this->fixupArrayDestructuring($array), false];
        }
        return [$this->variable(), false];
    }

    // ---- declarations ----

    /** attributes: one or more attribute groups */
    /** @return list<Node\AttributeGroup> */
    private function attributes(): array {
        $groups = [];
        do {
            $start = $this->pos;
            $this->expect(\T_ATTRIBUTE);
            $attrs = [];
            do {
                $astart = $this->pos;
                $name = $this->className();
                $args = $this->is(self::LPAREN) ? $this->argumentList() : [];
                $attrs[] = new Node\Attribute($name, $args, $this->attrs($astart));
            } while ($this->accept(self::COMMA) && !$this->is(self::RBRACKET));
            $this->expect(self::RBRACKET);
            $groups[] = new Node\AttributeGroup($attrs, $this->attrs($start));
        } while ($this->is(\T_ATTRIBUTE));
        return $groups;
    }

    /** @return list<Node\AttributeGroup> */
    private function optionalAttributes(): array {
        return $this->is(\T_ATTRIBUTE) ? $this->attributes() : [];
    }

    private function useStatement(): Stmt {
        $start = $this->pos;
        $this->advance();
        $type = Stmt\Use_::TYPE_NORMAL;
        if ($this->is(\T_FUNCTION)) {
            $type = Stmt\Use_::TYPE_FUNCTION;
            $this->advance();
        } elseif ($this->is(\T_CONST)) {
            $type = Stmt\Use_::TYPE_CONSTANT;
            $this->advance();
        }
        // group use: legacy_namespace_name T_NS_SEPARATOR '{'
        if ($this->isNameToken($this->id) && $this->peek(1) === \T_NS_SEPARATOR) {
            $prefix = $this->legacyNamespaceName();
            $this->expect(\T_NS_SEPARATOR);
            $this->expect(self::LBRACE);
            $uses = [];
            do {
                $ustart = $this->pos;
                $itemType = Stmt\Use_::TYPE_NORMAL;
                if ($type === Stmt\Use_::TYPE_NORMAL) {
                    // inline_use_declaration: [use_type] unprefixed_use_declaration
                    if ($this->is(\T_FUNCTION)) {
                        $itemType = Stmt\Use_::TYPE_FUNCTION;
                        $this->advance();
                    } elseif ($this->is(\T_CONST)) {
                        $itemType = Stmt\Use_::TYPE_CONSTANT;
                        $this->advance();
                    }
                    $ustart = $this->pos;
                }
                $item = $this->useDeclaration($ustart, false);
                if ($type === Stmt\Use_::TYPE_NORMAL) {
                    $item->type = $itemType;
                }
                $uses[] = $item;
            } while ($this->accept(self::COMMA) && !$this->is(self::RBRACE));
            $this->expect(self::RBRACE);
            $this->semi();
            return new Stmt\GroupUse($prefix, $uses, $type === Stmt\Use_::TYPE_NORMAL ? Stmt\Use_::TYPE_UNKNOWN : $type, $this->attrs($start));
        }
        $uses = [];
        do {
            $uses[] = $this->useDeclaration($this->pos, true);
        } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
        $this->noComma();
        $this->semi();
        return new Stmt\Use_($uses, $type, $this->attrs($start));
    }

    private function useDeclaration(int $start, bool $legacy): Node\UseItem {
        $namePos = $this->pos;
        $name = $legacy ? $this->legacyNamespaceName() : $this->namespaceName();
        $alias = null;
        $aliasPos = $namePos;
        if ($this->is(\T_AS)) {
            $this->advance();
            $aliasPos = $this->pos;
            $alias = $this->identifierNotReserved();
        }
        $item = new Node\UseItem($name, $alias, Stmt\Use_::TYPE_UNKNOWN, $this->attrs($start));
        $this->checkUseUse($item, $this->slot($aliasPos, $aliasPos));
        return $item;
    }

    private function namespaceName(): Name {
        if (!$this->is(\T_STRING) && !$this->is(\T_NAME_QUALIFIED)) {
            $this->fail('Expected namespace name');
        }
        $name = new Name($this->text(), $this->tokAttrs($this->pos));
        $this->advance();
        return $name;
    }

    private function legacyNamespaceName(): Name {
        if ($this->is(\T_NAME_FULLY_QUALIFIED)) {
            $name = new Name(substr($this->text(), 1), $this->tokAttrs($this->pos));
            $this->advance();
            return $name;
        }
        return $this->namespaceName();
    }

    /** @return list<Node\Const_> */
    private function constantDeclarationList(): array {
        $consts = [];
        do {
            $start = $this->pos;
            $name = $this->identifierNotReserved();
            $this->expect(self::EQUALS);
            $consts[] = new Node\Const_($name, $this->expr(0), $this->attrs($start));
        } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
        $this->noComma();
        return $consts;
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function functionDeclaration(int $start, array $attrGroups): Stmt {
        $this->expect(\T_FUNCTION);
        $byRef = $this->optionalRef();
        $id = $this->id;
        if ($id !== \T_STRING && $id !== \T_READONLY && $id !== \T_EXIT && $id !== \T_CLONE) {
            $this->fail('Expected function name');
        }
        $name = new Identifier($this->text(), $this->tokAttrs($this->pos));
        $this->advance();
        $this->expect(self::LPAREN);
        $params = $this->parameterList();
        $this->expect(self::RPAREN);
        $returnType = $this->optionalReturnType();
        $stmts = $this->block();
        return new Stmt\Function_($name, [
            'byRef' => $byRef, 'params' => $params, 'returnType' => $returnType, 'stmts' => $stmts,
            'attrGroups' => $attrGroups,
        ], $this->attrs($start));
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function classDeclaration(int $start, array $attrGroups): Stmt {
        $flags = $this->classEntryType();
        $namePos = $this->pos;
        $name = $this->identifierNotReserved();
        $extends = $this->accept(\T_EXTENDS) ? $this->className() : null;
        $implements = $this->accept(\T_IMPLEMENTS) ? $this->classNameList() : [];
        $stmts = $this->classBody();
        $class = new Stmt\Class_($name, [
            'type' => $flags, 'extends' => $extends, 'implements' => $implements, 'stmts' => $stmts,
            'attrGroups' => $attrGroups,
        ], $this->attrs($start));
        $this->checkClass($class, $this->slot($namePos, $namePos));
        return $class;
    }

    /** class_entry_type: T_CLASS | class_modifiers T_CLASS */
    private function classEntryType(): int {
        $flags = 0;
        while (isset(self::CLASS_MODIFIERS[$this->id])) {
            $flag = self::CLASS_MODIFIERS[$this->id];
            if ($flags !== 0) {
                $this->checkClassModifier($flags, $flag, $this->slot($this->pos, $this->pos));
            }
            $flags |= $flag;
            $this->advance();
        }
        $this->expect(\T_CLASS);
        return $flags;
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function interfaceDeclaration(int $start, array $attrGroups): Stmt {
        $this->expect(\T_INTERFACE);
        $namePos = $this->pos;
        $name = $this->identifierNotReserved();
        $extends = $this->accept(\T_EXTENDS) ? $this->classNameList() : [];
        $stmts = $this->classBody();
        $iface = new Stmt\Interface_($name, ['extends' => $extends, 'stmts' => $stmts, 'attrGroups' => $attrGroups], $this->attrs($start));
        $this->checkInterface($iface, $this->slot($namePos, $namePos));
        return $iface;
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function traitDeclaration(int $start, array $attrGroups): Stmt {
        $this->expect(\T_TRAIT);
        $name = $this->identifierNotReserved();
        $stmts = $this->classBody();
        return new Stmt\Trait_($name, ['stmts' => $stmts, 'attrGroups' => $attrGroups], $this->attrs($start));
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function enumDeclaration(int $start, array $attrGroups): Stmt {
        $this->expect(\T_ENUM);
        $namePos = $this->pos;
        $name = $this->identifierNotReserved();
        $scalarType = $this->accept(self::COLON) ? $this->type(true) : null;
        $implements = $this->accept(\T_IMPLEMENTS) ? $this->classNameList() : [];
        $stmts = $this->classBody();
        $enum = new Stmt\Enum_($name, [
            'scalarType' => $scalarType, 'implements' => $implements, 'stmts' => $stmts, 'attrGroups' => $attrGroups,
        ], $this->attrs($start));
        $this->checkEnum($enum, $this->slot($namePos, $namePos));
        return $enum;
    }

    /** @return list<Name> */
    private function classNameList(): array {
        $names = [$this->className()];
        while ($this->is(self::COMMA)) {
            $next = $this->peek(1);
            if (!$this->isNameToken($next) && $next !== \T_STATIC) {
                break;
            }
            $this->advance();
            $names[] = $this->className();
        }
        $this->noComma();
        return $names;
    }

    /** @return list<Stmt> */
    private function classBody(): array {
        $this->expect(self::LBRACE);
        $stmts = [];
        while (!$this->is(self::RBRACE)) {
            if ($this->is(0)) {
                $this->fail('Unexpected end of file in class body');
            }
            $stmt = $this->classStatement();
            if ($stmt !== null) {
                $stmts[] = $stmt;
            }
        }
        $nop = $this->zeroLengthNop();
        if ($nop !== null) {
            $stmts[] = $nop;
        }
        $this->expect(self::RBRACE);
        return $stmts;
    }

    private function classStatement(): ?Stmt {
        $start = $this->pos;
        if ($this->is(\T_USE)) {
            $this->advance();
            $traits = $this->classNameList();
            $adaptations = $this->traitAdaptations();
            return new Stmt\TraitUse($traits, $adaptations, $this->attrs($start));
        }
        $attrGroups = $this->optionalAttributes();
        if ($this->is(\T_CASE)) {
            $this->advance();
            $name = $this->identifierMaybeReserved();
            $expr = $this->accept(self::EQUALS) ? $this->expr(0) : null;
            $this->semi();
            return new Stmt\EnumCase($name, $expr, $attrGroups, $this->attrs($start));
        }
        $prevEnd = $this->last;
        $modStart = $this->pos;
        $flags = 0;
        $isVar = false;
        if ($this->is(\T_VAR)) {
            $isVar = true;
            $this->advance();
        } else {
            while (isset(self::MEMBER_MODIFIERS[$this->id])) {
                $flag = self::MEMBER_MODIFIERS[$this->id];
                if ($flags !== 0) {
                    $this->checkModifier($flags, $flag, $this->slot($this->pos, $this->pos));
                }
                $flags |= $flag;
                $this->advance();
            }
        }
        $hasModifiers = $isVar || $flags !== 0;
        $modPos = $hasModifiers ? $this->slot($modStart, $this->last) : $this->slot($this->pos, $prevEnd);
        if ($this->is(\T_CONST) && !$isVar) {
            $this->advance();
            $type = null;
            if ($this->classConstHasType()) {
                $type = $this->typeExpr(true);
            }
            $consts = [];
            do {
                $cstart = $this->pos;
                if (!$this->isIdentifierMaybeReserved($this->id)) {
                    $this->fail('Expected constant name');
                }
                $this->checkEchoIdentifier();
                $name = new Identifier($this->text(), $this->tokAttrs($this->pos));
                $this->advance();
                $this->expect(self::EQUALS);
                $consts[] = new Node\Const_($name, $this->expr(0), $this->attrs($cstart));
            } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
            $this->noComma();
            $this->semi();
            $const = new Stmt\ClassConst($consts, $flags, $this->attrs($start), $attrGroups, $type);
            $this->checkClassConst($const, $modPos);
            return $const;
        }
        if ($this->is(\T_FUNCTION) && !$isVar) {
            $this->advance();
            $byRef = $this->optionalRef();
            $name = $this->identifierMaybeReserved();
            $this->expect(self::LPAREN);
            $params = $this->parameterList();
            $this->expect(self::RPAREN);
            $returnType = $this->optionalReturnType();
            $stmts = $this->accept(self::SEMI) ? null : $this->block();
            $method = new Stmt\ClassMethod($name, [
                'type' => $flags, 'byRef' => $byRef, 'params' => $params, 'returnType' => $returnType,
                'stmts' => $stmts, 'attrGroups' => $attrGroups,
            ], $this->attrs($start));
            $this->checkClassMethod($method, $modPos);
            return $method;
        }
        if (!$hasModifiers) {
            $this->fail('Expected class member');
        }
        $type = $this->optionalTypeWithoutStatic();
        $props = [];
        do {
            $pstart = $this->pos;
            if (!$this->is(\T_VARIABLE)) {
                $this->fail('Expected property name');
            }
            $name = new Node\VarLikeIdentifier(substr($this->text(), 1), $this->tokAttrs($this->pos));
            $this->advance();
            $default = $this->accept(self::EQUALS) ? $this->expr(0) : null;
            $props[] = new Node\PropertyItem($name, $default, $this->attrs($pstart));
        } while ($this->is(self::COMMA) && $this->peek(1) !== self::SEMI && $this->accept(self::COMMA));
        $this->noComma();
        if ($this->is(self::LBRACE)) {
            $hookPos = $this->pos;
            $this->advance();
            $hooks = $this->propertyHookList();
            $this->expect(self::RBRACE);
            $prop = new Stmt\Property($flags, $props, $this->attrs($start), $type, $attrGroups, $hooks);
            $this->checkPropertyHooksForMultiProperty($prop, $this->slot($hookPos, $hookPos));
            $this->checkEmptyPropertyHookList($hooks, $this->slot($hookPos, $hookPos));
            $this->addPropertyNameToHooks($prop);
            return $prop;
        }
        $this->semi();
        return new Stmt\Property($flags, $props, $this->attrs($start), $type, $attrGroups);
    }

    /** After T_CONST: is there a type before the constant name? */
    private function classConstHasType(): bool {
        $id = $this->id;
        if ($id === self::QUESTION || $id === self::LPAREN) {
            return true;
        }
        return $this->peek(1) !== self::EQUALS;
    }

    /** @return list<Stmt\TraitUseAdaptation> */
    private function traitAdaptations(): array {
        if ($this->accept(self::SEMI)) {
            return [];
        }
        $this->expect(self::LBRACE);
        $adaptations = [];
        while (!$this->is(self::RBRACE)) {
            $start = $this->pos;
            $trait = null;
            if ($this->isNameToken($this->id) && $this->peek(1) === \T_PAAMAYIM_NEKUDOTAYIM) {
                $trait = $this->name();
                $this->advance();
            }
            $method = $this->identifierMaybeReserved();
            if ($this->is(\T_INSTEADOF)) {
                if ($trait === null) {
                    $this->fail('insteadof needs a qualified method reference');
                }
                $this->advance();
                $insteadof = $this->classNameList();
                $this->expect(self::SEMI);
                $adaptations[] = new Stmt\TraitUseAdaptation\Precedence($trait, $method, $insteadof, $this->attrs($start));
                continue;
            }
            $this->expect(\T_AS);
            if (isset(self::MEMBER_MODIFIERS[$this->id])) {
                $modifier = self::MEMBER_MODIFIERS[$this->id];
                $this->advance();
                $newName = $this->is(self::SEMI) ? null : $this->identifierMaybeReserved();
                $this->expect(self::SEMI);
                $adaptations[] = new Stmt\TraitUseAdaptation\Alias($trait, $method, $modifier, $newName, $this->attrs($start));
                continue;
            }
            if (!$this->is(\T_STRING) && !isset(self::RESERVED_NON_MODIFIERS[$this->id])) {
                $this->fail('Expected alias name');
            }
            $newName = $this->identifierMaybeReserved();
            $this->expect(self::SEMI);
            $adaptations[] = new Stmt\TraitUseAdaptation\Alias($trait, $method, null, $newName, $this->attrs($start));
        }
        $this->advance();
        return $adaptations;
    }

    /** @return list<Node\PropertyHook> */
    private function propertyHookList(): array {
        $hooks = [];
        while (!$this->is(self::RBRACE)) {
            $start = $this->pos;
            $attrGroups = $this->optionalAttributes();
            $flags = 0;
            while (isset(self::MEMBER_MODIFIERS[$this->id])) {
                $flag = self::MEMBER_MODIFIERS[$this->id];
                $this->checkPropertyHookModifiers($flags, $flag, $this->slot($this->pos, $this->pos));
                $flags |= $flag;
                $this->advance();
            }
            $byRef = $this->optionalRef();
            $name = $this->identifierNotReserved();
            $params = [];
            $paramListPos = null;
            if ($this->is(self::LPAREN)) {
                $paramListPos = $this->slot($this->pos, $this->pos);
                $this->advance();
                $params = $this->parameterList();
                $this->expect(self::RPAREN);
            }
            if ($this->accept(self::SEMI)) {
                $body = null;
            } elseif ($this->is(self::LBRACE)) {
                $body = $this->block();
            } else {
                $this->expect(\T_DOUBLE_ARROW);
                $body = $this->expr(0);
                $this->expect(self::SEMI);
            }
            $hook = new Node\PropertyHook($name, $body, [
                'flags' => $flags, 'byRef' => $byRef, 'params' => $params, 'attrGroups' => $attrGroups,
            ], $this->attrs($start));
            $this->checkPropertyHook($hook, $paramListPos);
            $hooks[] = $hook;
        }
        return $hooks;
    }

    private function optionalRef(): bool {
        if (self::isAmpersand($this->id)) {
            $this->advance();
            return true;
        }
        return false;
    }

    /** @return list<Node\Param> */
    private function parameterList(): array {
        if ($this->is(self::RPAREN)) {
            return [];
        }
        $params = [];
        do {
            $params[] = $this->parameter();
        } while ($this->accept(self::COMMA) && !$this->is(self::RPAREN));
        return $params;
    }

    private function parameter(): Node\Param {
        $start = $this->pos;
        $attrGroups = $this->optionalAttributes();
        $flags = 0;
        while (isset(self::PROPERTY_MODIFIERS[$this->id])) {
            $flag = self::PROPERTY_MODIFIERS[$this->id];
            if ($flags !== 0) {
                $this->checkModifier($flags, $flag, $this->slot($this->pos, $this->pos));
            }
            $flags |= $flag;
            $this->advance();
        }
        $type = $this->optionalTypeWithoutStatic();
        $byRef = false;
        if ($this->is(\T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG)) {
            $byRef = true;
            $this->advance();
        }
        $variadic = $this->accept(\T_ELLIPSIS);
        if (!$this->is(\T_VARIABLE)) {
            $this->fail('Expected parameter variable');
        }
        $var = $this->plainVariable();
        $default = $this->accept(self::EQUALS) ? $this->expr(0) : null;
        $hooks = [];
        if ($this->is(self::LBRACE)) {
            $hookPos = $this->pos;
            $this->advance();
            $hooks = $this->propertyHookList();
            $this->expect(self::RBRACE);
            $this->checkEmptyPropertyHookList($hooks, $this->slot($hookPos, $hookPos));
        }
        $param = new Node\Param($var, $default, $type, $byRef, $variadic, $this->attrs($start), $flags, $attrGroups, $hooks);
        $this->checkParam($param);
        $this->addPropertyNameToHooks($param);
        return $param;
    }

    private function optionalReturnType(): Identifier|Name|Node\ComplexType|null {
        if (!$this->is(self::COLON)) {
            return null;
        }
        $this->advance();
        return $this->typeExpr(true);
    }

    // ---- types ----

    private function typeStartsHere(bool $allowStatic): bool {
        $id = $this->id;
        return $id === self::QUESTION || $id === self::LPAREN || $this->isNameToken($id) || $id === \T_ARRAY
            || $id === \T_CALLABLE || ($allowStatic && $id === \T_STATIC);
    }

    private function optionalTypeWithoutStatic(): Identifier|Name|Node\ComplexType|null {
        return $this->typeStartsHere(false) ? $this->typeExpr(false) : null;
    }

    /** type_expr / type_expr_without_static */
    private function typeExpr(bool $allowStatic): Identifier|Name|Node\ComplexType {
        $start = $this->pos;
        if ($this->is(self::QUESTION)) {
            $this->advance();
            $type = $this->type($allowStatic);
            return new Node\NullableType($type, $this->attrs($start));
        }
        if ($this->is(self::LPAREN)) {
            $types = [$this->parenIntersectionType($allowStatic)];
            if (!$this->is(self::PIPE)) {
                $this->fail('Expected | after DNF group');
            }
            while ($this->accept(self::PIPE)) {
                $types[] = $this->is(self::LPAREN) ? $this->parenIntersectionType($allowStatic) : $this->type($allowStatic);
            }
            return new Node\UnionType($types, $this->attrs($start));
        }
        $first = $this->type($allowStatic);
        if ($this->is(self::PIPE)) {
            $types = [$first];
            while ($this->accept(self::PIPE)) {
                $types[] = $this->is(self::LPAREN) ? $this->parenIntersectionType($allowStatic) : $this->type($allowStatic);
            }
            return new Node\UnionType($types, $this->attrs($start));
        }
        if ($this->is(\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
            $types = [$first];
            while ($this->accept(\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                $types[] = $this->type($allowStatic);
            }
            return new Node\IntersectionType($types, $this->attrs($start));
        }
        return $first;
    }

    private function parenIntersectionType(bool $allowStatic): Node\IntersectionType {
        $this->expect(self::LPAREN);
        $start = $this->pos;
        $types = [$this->type($allowStatic)];
        if (!$this->is(\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
            $this->fail('Expected & in DNF group');
        }
        while ($this->accept(\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
            $types[] = $this->type($allowStatic);
        }
        $node = new Node\IntersectionType($types, $this->attrs($start));
        $this->expect(self::RPAREN);
        return $node;
    }

    /** type / type_without_static */
    private function type(bool $allowStatic): Identifier|Name {
        $id = $this->id;
        if ($id === \T_ARRAY) {
            $node = new Identifier('array', $this->tokAttrs($this->pos));
            $this->advance();
            return $node;
        }
        if ($id === \T_CALLABLE) {
            $node = new Identifier('callable', $this->tokAttrs($this->pos));
            $this->advance();
            return $node;
        }
        if ($id === \T_STATIC && $allowStatic) {
            $node = new Name('static', $this->tokAttrs($this->pos));
            $this->advance();
            return $node;
        }
        if (!$this->isNameToken($id)) {
            $this->fail('Expected type');
        }
        return $this->handleBuiltinTypes($this->name());
    }

    // ---- names and identifiers ----

    private function name(): Name {
        $pos = $this->pos;
        $text = $this->text();
        $id = $this->id;
        switch ($id) {
            case \T_STRING:
            case \T_NAME_QUALIFIED:
                $this->advance();
                return new Name($text, $this->tokAttrs($pos));
            case \T_NAME_FULLY_QUALIFIED:
                $this->advance();
                return new Name\FullyQualified(substr($text, 1), $this->tokAttrs($pos));
            case \T_NAME_RELATIVE:
                $this->advance();
                return new Name\Relative(substr($text, 10), $this->tokAttrs($pos));
        }
        $this->fail('Expected name');
    }

    /** class_name: T_STATIC | name */
    private function className(): Name {
        if ($this->is(\T_STATIC)) {
            $name = new Name('static', $this->tokAttrs($this->pos));
            $this->advance();
            return $name;
        }
        return $this->name();
    }

    private function identifierNotReserved(): Identifier {
        if (!$this->is(\T_STRING)) {
            $this->fail('Expected identifier');
        }
        $ident = new Identifier($this->text(), $this->tokAttrs($this->pos));
        $this->advance();
        return $ident;
    }

    private function identifierMaybeReserved(): Identifier {
        if (!$this->isIdentifierMaybeReserved($this->id)) {
            $this->fail('Expected identifier');
        }
        $this->checkEchoIdentifier();
        $ident = new Identifier($this->text(), $this->tokAttrs($this->pos));
        $this->advance();
        return $ident;
    }

    /** reserved_non_modifiers: T_ECHO spelled "<?=" cannot be an identifier */
    private function checkEchoIdentifier(): void {
        if ($this->is(\T_ECHO) && $this->tokens[$this->pos]->text === '<?=') {
            $this->emitError(new Error('Cannot use "<?=" as an identifier', $this->tokAttrs($this->pos)));
        }
    }

    private function plainVariable(): Expr\Variable {
        if (!$this->is(\T_VARIABLE)) {
            $this->fail('Expected variable');
        }
        $var = new Expr\Variable(substr($this->text(), 1), $this->tokAttrs($this->pos));
        $this->advance();
        return $var;
    }

    /** simple_variable: $x | ${expr} | $$x */
    private function simpleVariable(): Expr\Variable {
        if ($this->is(\T_VARIABLE)) {
            return $this->plainVariable();
        }
        $start = $this->pos;
        $this->expect(self::DOLLAR);
        if ($this->is(self::LBRACE)) {
            $this->advance();
            $expr = $this->expr(0);
            $this->expect(self::RBRACE);
            return new Expr\Variable($expr, $this->attrs($start));
        }
        $inner = $this->simpleVariable();
        return new Expr\Variable($inner, $this->attrs($start));
    }

    /** static_member_prop_name */
    private function staticMemberPropName(): Node\VarLikeIdentifier|Expr {
        $start = $this->pos;
        $var = $this->simpleVariable();
        $name = $var->name;
        return \is_string($name) ? new Node\VarLikeIdentifier($name, $this->attrs($start)) : $name;
    }

    /** variable: a primary with its postfix chain that is assignable */
    private function variable(): Expr {
        $start = $this->pos;
        $node = $this->postfixChain($this->primary(), $start);
        if ($this->kind !== self::K_VAR) {
            $this->fail('Expected variable');
        }
        return $node;
    }

    // ---- expressions ----

    private function expr(int $ctx): Expr {
        $start = $this->pos;
        return $this->binaryLoop($this->unary($ctx), $start, $ctx);
    }

    /** The binary-operator loop of precedence climbing: extends $lhs (started at $start) while the operator binds in $ctx. */
    private function binaryLoop(Expr $lhs, int $start, int $ctx): Expr {
        for (;;) {
            $id = $this->id;
            $prec = self::binaryPrec($id);
            if ($prec < $ctx) {
                return $lhs;
            }
            if ($prec === $ctx) {
                if (isset(self::NONASSOC[$prec])) {
                    $this->fail('Non-associative operator');
                }
                if (!isset(self::RIGHT_ASSOC[$prec])) {
                    return $lhs;
                }
            }
            $this->advance();
            if ($id === self::QUESTION) {
                if ($this->accept(self::COLON)) {
                    $else = $this->expr(self::P_TERNARY);
                    $lhs = new Expr\Ternary($lhs, null, $else, $this->attrs($start));
                } else {
                    $if = $this->expr(0);
                    $this->expect(self::COLON);
                    $else = $this->expr(self::P_TERNARY);
                    $lhs = new Expr\Ternary($lhs, $if, $else, $this->attrs($start));
                }
                continue;
            }
            if ($id === \T_INSTANCEOF) {
                $class = $this->classNameReference();
                $lhs = new Expr\Instanceof_($lhs, $class, $this->attrs($start));
                continue;
            }
            $rhs = $this->expr($prec);
            $lhs = $this->binaryNode($id, $lhs, $rhs, $this->attrs($start));
            if ($id === \T_PIPE) {
                $this->checkPipeOperatorParentheses($rhs);
            }
        }
    }

    private function binaryNode(int $id, Expr $lhs, Expr $rhs, NodeAttributes $attrs): Expr {
        switch ($id) {
            case \T_BOOLEAN_OR: return new Expr\BinaryOp\BooleanOr($lhs, $rhs, $attrs);
            case \T_BOOLEAN_AND: return new Expr\BinaryOp\BooleanAnd($lhs, $rhs, $attrs);
            case \T_LOGICAL_OR: return new Expr\BinaryOp\LogicalOr($lhs, $rhs, $attrs);
            case \T_LOGICAL_AND: return new Expr\BinaryOp\LogicalAnd($lhs, $rhs, $attrs);
            case \T_LOGICAL_XOR: return new Expr\BinaryOp\LogicalXor($lhs, $rhs, $attrs);
            case self::PIPE: return new Expr\BinaryOp\BitwiseOr($lhs, $rhs, $attrs);
            case \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG:
            case \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG: return new Expr\BinaryOp\BitwiseAnd($lhs, $rhs, $attrs);
            case self::CARET: return new Expr\BinaryOp\BitwiseXor($lhs, $rhs, $attrs);
            case self::DOT: return new Expr\BinaryOp\Concat($lhs, $rhs, $attrs);
            case self::PLUS: return new Expr\BinaryOp\Plus($lhs, $rhs, $attrs);
            case self::MINUS: return new Expr\BinaryOp\Minus($lhs, $rhs, $attrs);
            case self::MUL: return new Expr\BinaryOp\Mul($lhs, $rhs, $attrs);
            case self::DIV: return new Expr\BinaryOp\Div($lhs, $rhs, $attrs);
            case self::MOD: return new Expr\BinaryOp\Mod($lhs, $rhs, $attrs);
            case \T_SL: return new Expr\BinaryOp\ShiftLeft($lhs, $rhs, $attrs);
            case \T_SR: return new Expr\BinaryOp\ShiftRight($lhs, $rhs, $attrs);
            case \T_POW: return new Expr\BinaryOp\Pow($lhs, $rhs, $attrs);
            case \T_IS_IDENTICAL: return new Expr\BinaryOp\Identical($lhs, $rhs, $attrs);
            case \T_IS_NOT_IDENTICAL: return new Expr\BinaryOp\NotIdentical($lhs, $rhs, $attrs);
            case \T_IS_EQUAL: return new Expr\BinaryOp\Equal($lhs, $rhs, $attrs);
            case \T_IS_NOT_EQUAL: return new Expr\BinaryOp\NotEqual($lhs, $rhs, $attrs);
            case \T_SPACESHIP: return new Expr\BinaryOp\Spaceship($lhs, $rhs, $attrs);
            case self::LT: return new Expr\BinaryOp\Smaller($lhs, $rhs, $attrs);
            case \T_IS_SMALLER_OR_EQUAL: return new Expr\BinaryOp\SmallerOrEqual($lhs, $rhs, $attrs);
            case self::GT: return new Expr\BinaryOp\Greater($lhs, $rhs, $attrs);
            case \T_IS_GREATER_OR_EQUAL: return new Expr\BinaryOp\GreaterOrEqual($lhs, $rhs, $attrs);
            case \T_PIPE: return new Expr\BinaryOp\Pipe($lhs, $rhs, $attrs);
            case \T_COALESCE: return new Expr\BinaryOp\Coalesce($lhs, $rhs, $attrs);
        }
        $this->fail('Unknown binary operator');
    }

    private function assignOpNode(int $id, Expr $var, Expr $expr, NodeAttributes $attrs): Expr {
        switch ($id) {
            case \T_PLUS_EQUAL: return new Expr\AssignOp\Plus($var, $expr, $attrs);
            case \T_MINUS_EQUAL: return new Expr\AssignOp\Minus($var, $expr, $attrs);
            case \T_MUL_EQUAL: return new Expr\AssignOp\Mul($var, $expr, $attrs);
            case \T_DIV_EQUAL: return new Expr\AssignOp\Div($var, $expr, $attrs);
            case \T_CONCAT_EQUAL: return new Expr\AssignOp\Concat($var, $expr, $attrs);
            case \T_MOD_EQUAL: return new Expr\AssignOp\Mod($var, $expr, $attrs);
            case \T_AND_EQUAL: return new Expr\AssignOp\BitwiseAnd($var, $expr, $attrs);
            case \T_OR_EQUAL: return new Expr\AssignOp\BitwiseOr($var, $expr, $attrs);
            case \T_XOR_EQUAL: return new Expr\AssignOp\BitwiseXor($var, $expr, $attrs);
            case \T_SL_EQUAL: return new Expr\AssignOp\ShiftLeft($var, $expr, $attrs);
            case \T_SR_EQUAL: return new Expr\AssignOp\ShiftRight($var, $expr, $attrs);
            case \T_POW_EQUAL: return new Expr\AssignOp\Pow($var, $expr, $attrs);
            case \T_COALESCE_EQUAL: return new Expr\AssignOp\Coalesce($var, $expr, $attrs);
        }
        $this->fail('Unknown assignment operator');
    }

    /** Prefix operators and everything that is an expr but not a variable chain. */
    private function unary(int $ctx): Expr {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case self::PLUS:
                $this->advance();
                return new Expr\UnaryPlus($this->expr(self::P_UNARY), $this->attrs($start));
            case self::MINUS:
                $this->advance();
                return new Expr\UnaryMinus($this->expr(self::P_UNARY), $this->attrs($start));
            case self::NOT:
                $this->advance();
                return new Expr\BooleanNot($this->expr(self::P_NOT), $this->attrs($start));
            case self::TILDE:
                $this->advance();
                return new Expr\BitwiseNot($this->expr(self::P_UNARY), $this->attrs($start));
            case self::AT:
                $this->advance();
                return new Expr\ErrorSuppress($this->expr(self::P_UNARY), $this->attrs($start));
            case \T_INC:
            case \T_DEC:
                $this->advance();
                $var = $this->variable();
                return $id === \T_INC
                    ? new Expr\PreInc($var, $this->attrs($start))
                    : new Expr\PreDec($var, $this->attrs($start));
            case \T_INT_CAST:
                $text = $this->text();
                $this->advance();
                $expr = $this->expr(self::P_UNARY);
                $attrs = $this->attrs($start);
                $attrs->kind = $this->getIntCastKind($text);
                return new Expr\Cast\Int_($expr, $attrs);
            case \T_DOUBLE_CAST:
                $text = $this->text();
                $this->advance();
                $expr = $this->expr(self::P_UNARY);
                $attrs = $this->attrs($start);
                $attrs->kind = $this->getFloatCastKind($text);
                return new Expr\Cast\Double($expr, $attrs);
            case \T_STRING_CAST:
                $text = $this->text();
                $this->advance();
                $expr = $this->expr(self::P_UNARY);
                $attrs = $this->attrs($start);
                $attrs->kind = $this->getStringCastKind($text);
                return new Expr\Cast\String_($expr, $attrs);
            case \T_BOOL_CAST:
                $text = $this->text();
                $this->advance();
                $expr = $this->expr(self::P_UNARY);
                $attrs = $this->attrs($start);
                $attrs->kind = $this->getBoolCastKind($text);
                return new Expr\Cast\Bool_($expr, $attrs);
            case \T_ARRAY_CAST:
                $this->advance();
                return new Expr\Cast\Array_($this->expr(self::P_UNARY), $this->attrs($start));
            case \T_OBJECT_CAST:
                $this->advance();
                return new Expr\Cast\Object_($this->expr(self::P_UNARY), $this->attrs($start));
            case \T_UNSET_CAST:
                $this->advance();
                return new Expr\Cast\Unset_($this->expr(self::P_UNARY), $this->attrs($start));
            case \T_VOID_CAST:
                $this->advance();
                return new Expr\Cast\Void_($this->expr(self::P_VOID_CAST), $this->attrs($start));
            case \T_PRINT:
                $this->advance();
                return new Expr\Print_($this->expr(self::P_PRINT), $this->attrs($start));
            case \T_THROW:
                $this->advance();
                return new Expr\Throw_($this->expr(self::P_THROW), $this->attrs($start));
            case \T_YIELD_FROM:
                $this->advance();
                return new Expr\YieldFrom($this->expr(self::P_YIELD_FROM), $this->attrs($start));
            case \T_YIELD:
                $this->advance();
                if (!isset(self::EXPR_START[$this->id]) || isset(self::BELOW_YIELD[$this->id])) {
                    return new Expr\Yield_(null, null, $this->attrs($start));
                }
                $value = $this->expr(self::P_YIELD);
                if ($this->is(\T_DOUBLE_ARROW)) {
                    $this->advance();
                    $key = $value;
                    $value = $this->expr(self::P_DOUBLE_ARROW);
                    return new Expr\Yield_($value, $key, $this->attrs($start));
                }
                return new Expr\Yield_($value, null, $this->attrs($start));
            case \T_INCLUDE:
            case \T_INCLUDE_ONCE:
            case \T_REQUIRE:
            case \T_REQUIRE_ONCE:
                $this->advance();
                $expr = $this->expr(self::P_INCLUDE);
                $type = $id === \T_INCLUDE ? Expr\Include_::TYPE_INCLUDE
                    : ($id === \T_INCLUDE_ONCE ? Expr\Include_::TYPE_INCLUDE_ONCE
                    : ($id === \T_REQUIRE ? Expr\Include_::TYPE_REQUIRE : Expr\Include_::TYPE_REQUIRE_ONCE));
                return new Expr\Include_($expr, $type, $this->attrs($start));
            case \T_CLONE:
                return $this->cloneExpr();
            case \T_ISSET:
                $this->advance();
                $this->expect(self::LPAREN);
                $vars = [];
                do {
                    $vars[] = $this->expr(0);
                } while ($this->accept(self::COMMA) && !$this->is(self::RPAREN));
                $this->expect(self::RPAREN);
                return new Expr\Isset_($vars, $this->attrs($start));
            case \T_EMPTY:
                $this->advance();
                $this->expect(self::LPAREN);
                $expr = $this->expr(0);
                $this->expect(self::RPAREN);
                return new Expr\Empty_($expr, $this->attrs($start));
            case \T_EVAL:
                $this->advance();
                $this->expect(self::LPAREN);
                $expr = $this->expr(0);
                $this->expect(self::RPAREN);
                return new Expr\Eval_($expr, $this->attrs($start));
            case \T_EXIT:
                $text = $this->text();
                $this->advance();
                $args = $this->is(self::LPAREN) ? $this->argumentList() : [];
                return $this->createExitExpr($text, $this->slot($start, $start), $args, $this->attrs($start));
            case self::BACKTICK:
                $this->advance();
                if ($this->is(self::BACKTICK)) {
                    $parts = [];
                } elseif ($this->is(\T_ENCAPSED_AND_WHITESPACE) && $this->peek(1) === self::BACKTICK) {
                    $parts = [$this->encapsStringPart()];
                    $this->parseEncapsed($parts, '`');
                } else {
                    $parts = $this->encapsList(self::BACKTICK);
                    $this->parseEncapsed($parts, '`');
                }
                $this->expect(self::BACKTICK);
                return new Expr\ShellExec($parts, $this->attrs($start));
            case \T_MATCH:
                return $this->matchExpr();
            case \T_FUNCTION:
            case \T_FN:
                return $this->closure($start, [], false);
            case \T_STATIC:
                $next = $this->peek(1);
                if ($next === \T_FUNCTION || $next === \T_FN) {
                    $this->advance();
                    return $this->closure($start, [], true);
                }
                break;
            case \T_ATTRIBUTE:
                $attrGroups = $this->attributes();
                return $this->closureWithAttrs($start, $attrGroups);
            case \T_LNUMBER:
                $text = $this->text();
                $this->advance();
                return $this->parseLNumber($text, $this->attrs($start), $this->phpVersion->allowsInvalidOctals());
            case \T_DNUMBER:
                $text = $this->text();
                $this->advance();
                return Scalar\Float_::fromString($text, $this->attrs($start));
            case \T_START_HEREDOC:
                return $this->heredoc();
            case \T_LIST:
                $list = $this->listExpr();
                $this->expect(self::EQUALS);
                return new Expr\Assign($list, $this->expr(self::P_ASSIGN), $this->attrs($start));
        }
        return $this->postfixExpr();
    }

    /** @param list<Node\AttributeGroup> $attrGroups */
    private function closureWithAttrs(int $start, array $attrGroups): Expr {
        if ($this->is(\T_STATIC)) {
            $this->advance();
            return $this->closure($start, $attrGroups, true);
        }
        return $this->closure($start, $attrGroups, false);
    }

    /** Closure or arrow function; the `static` keyword (if any) has been consumed. */
    /** @param list<Node\AttributeGroup> $attrGroups */
    private function closure(int $start, array $attrGroups, bool $static): Expr {
        if ($this->is(\T_FN)) {
            $this->advance();
            $byRef = $this->optionalRef();
            $this->expect(self::LPAREN);
            $params = $this->parameterList();
            $this->expect(self::RPAREN);
            $returnType = $this->optionalReturnType();
            $this->expect(\T_DOUBLE_ARROW);
            $expr = $this->expr(self::P_THROW);
            return new Expr\ArrowFunction([
                'static' => $static, 'byRef' => $byRef, 'params' => $params, 'returnType' => $returnType,
                'expr' => $expr, 'attrGroups' => $attrGroups,
            ], $this->attrs($start));
        }
        $this->expect(\T_FUNCTION);
        $byRef = $this->optionalRef();
        $this->expect(self::LPAREN);
        $params = $this->parameterList();
        $this->expect(self::RPAREN);
        $uses = [];
        if ($this->is(\T_USE)) {
            $this->advance();
            $this->expect(self::LPAREN);
            do {
                $ustart = $this->pos;
                $uByRef = $this->optionalRef();
                $var = $this->plainVariable();
                $uses[] = new Node\ClosureUse($var, $uByRef, $this->attrs($ustart));
            } while ($this->accept(self::COMMA) && !$this->is(self::RPAREN));
            $this->expect(self::RPAREN);
        }
        $returnType = $this->optionalReturnType();
        $stmts = $this->block();
        return new Expr\Closure([
            'static' => $static, 'byRef' => $byRef, 'params' => $params, 'uses' => $uses,
            'returnType' => $returnType, 'stmts' => $stmts, 'attrGroups' => $attrGroups,
        ], $this->attrs($start));
    }

    private function matchExpr(): Expr {
        $start = $this->pos;
        $this->advance();
        $this->expect(self::LPAREN);
        $cond = $this->expr(0);
        $this->expect(self::RPAREN);
        $this->expect(self::LBRACE);
        $arms = [];
        while (!$this->is(self::RBRACE)) {
            $astart = $this->pos;
            if ($this->is(\T_DEFAULT)) {
                $this->advance();
                $this->accept(self::COMMA);
                $conds = null;
            } else {
                $conds = [$this->expr(0)];
                while ($this->accept(self::COMMA)) {
                    if ($this->is(\T_DOUBLE_ARROW)) {
                        break;
                    }
                    $conds[] = $this->expr(0);
                }
            }
            $this->expect(\T_DOUBLE_ARROW);
            $body = $this->expr(0);
            $arms[] = new Node\MatchArm($conds, $body, $this->attrs($astart));
            if (!$this->accept(self::COMMA)) {
                break;
            }
        }
        $this->expect(self::RBRACE);
        return new Expr\Match_($cond, $arms, $this->attrs($start));
    }

    /** T_CLONE clone_argument_list | T_CLONE expr */
    private function cloneExpr(): Expr {
        $start = $this->pos;
        $text = $this->text();
        $this->advance();
        if (!$this->is(self::LPAREN)) {
            return new Expr\Clone_($this->expr(self::P_NEW), $this->attrs($start));
        }
        $parenStart = $this->pos;
        $this->advance();
        $funcName = new Name($text, $this->tokAttrs($start));
        if ($this->is(self::RPAREN)) {
            $this->advance();
            return new Expr\FuncCall($funcName, [], $this->attrs($start));
        }
        if ($this->argumentStartsNoExpr($this->id)) {
            $args = [$this->argumentNoExpr()];
            while ($this->accept(self::COMMA) && !$this->is(self::RPAREN)) {
                $args[] = $this->argument();
            }
            $this->expect(self::RPAREN);
            return new Expr\FuncCall($funcName, $args, $this->attrs($start));
        }
        $exprStart = $this->pos;
        $expr = $this->expr(0);
        $exprEnd = $this->last;
        if ($this->is(self::COMMA)) {
            $this->advance();
            if ($this->is(self::RPAREN)) {
                $this->advance();
                // '(' expr ',' ')': the argument spans the whole list
                $args = [new Node\Arg($expr, false, false, $this->attrs($parenStart))];
                return new Expr\FuncCall($funcName, $args, $this->attrs($start));
            }
            $args = [new Node\Arg($expr, false, false, $this->getAttributes($exprStart, $exprEnd))];
            $args[] = $this->argument();
            while ($this->accept(self::COMMA) && !$this->is(self::RPAREN)) {
                $args[] = $this->argument();
            }
            $this->expect(self::RPAREN);
            return new Expr\FuncCall($funcName, $args, $this->attrs($start));
        }
        $this->expect(self::RPAREN);
        if ($expr instanceof Expr\ArrowFunction) {
            $this->parenthesizedArrowFunctions->offsetSet($expr);
        }
        $this->kind = self::K_DEREF;
        $operand = $this->afterPrimary($this->postfixChain($expr, $parenStart), $parenStart);
        $operand = $this->binaryLoop($operand, $parenStart, self::P_NEW);
        return new Expr\Clone_($operand, $this->attrs($start));
    }

    private function heredoc(): Expr {
        $start = $this->pos;
        $startText = $this->text();
        $this->advance();
        if ($this->is(\T_END_HEREDOC)) {
            $endPos = $this->pos;
            $endText = $this->text();
            $this->advance();
            return $this->parseDocString($startText, '', $endText, $this->attrs($start), $this->tokAttrs($endPos), true);
        }
        if ($this->is(\T_ENCAPSED_AND_WHITESPACE) && $this->peek(1) === \T_END_HEREDOC) {
            $contents = $this->text();
            $this->advance();
        } else {
            $contents = $this->encapsList(\T_END_HEREDOC);
        }
        if (!$this->is(\T_END_HEREDOC)) {
            $this->fail('Expected end of heredoc');
        }
        $endPos = $this->pos;
        $endText = $this->text();
        $this->advance();
        return $this->parseDocString($startText, $contents, $endText, $this->attrs($start), $this->tokAttrs($endPos), true);
    }

    private function encapsStringPart(): Node\InterpolatedStringPart {
        $pos = $this->pos;
        $text = $this->text();
        $this->advance();
        $attrs = $this->tokAttrs($pos);
        $attrs->rawValue = $text;
        return new Node\InterpolatedStringPart($text, $attrs);
    }

    /** @param list<Node\InterpolatedStringPart|Expr> $parts */
    private function parseEncapsed(array $parts, string $quote): void {
        $unicode = $this->phpVersion->supportsUnicodeEscapes();
        foreach ($parts as $part) {
            if ($part instanceof Node\InterpolatedStringPart) {
                $part->value = Scalar\String_::parseEscapeSequences($part->value, $quote, $unicode);
            }
        }
    }

    /** @return list<Node\InterpolatedStringPart|Expr> */
    private function encapsList(int $end): array {
        $parts = [];
        while (!$this->is($end)) {
            if ($this->is(\T_ENCAPSED_AND_WHITESPACE)) {
                $parts[] = $this->encapsStringPart();
            } else {
                $parts[] = $this->encapsVar();
            }
        }
        if (\count($parts) === 0 || (\count($parts) === 1 && $parts[0] instanceof Node\InterpolatedStringPart)) {
            $this->fail('Malformed interpolated string');
        }
        return $parts;
    }

    private function encapsVar(): Expr {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case \T_VARIABLE:
                $var = $this->plainVariable();
                if ($this->is(self::LBRACKET)) {
                    $this->advance();
                    $offset = $this->encapsVarOffset();
                    $this->expect(self::RBRACKET);
                    return new Expr\ArrayDimFetch($var, $offset, $this->attrs($start));
                }
                if ($this->is(\T_OBJECT_OPERATOR)) {
                    $this->advance();
                    $name = $this->identifierNotReserved();
                    return new Expr\PropertyFetch($var, $name, $this->attrs($start));
                }
                if ($this->is(\T_NULLSAFE_OBJECT_OPERATOR)) {
                    $this->advance();
                    $name = $this->identifierNotReserved();
                    return new Expr\NullsafePropertyFetch($var, $name, $this->attrs($start));
                }
                return $var;
            case \T_DOLLAR_OPEN_CURLY_BRACES:
                $this->advance();
                if ($this->is(\T_STRING_VARNAME)) {
                    $next = $this->peek(1);
                    if ($next === self::RBRACE) {
                        $name = $this->text();
                        $this->advance();
                        $this->advance();
                        return new Expr\Variable($name, $this->attrs($start));
                    }
                    if ($next === self::LBRACKET) {
                        $var = new Expr\Variable($this->text(), $this->tokAttrs($this->pos));
                        $this->advance();
                        $this->advance();
                        $dim = $this->expr(0);
                        $this->expect(self::RBRACKET);
                        $this->expect(self::RBRACE);
                        return new Expr\ArrayDimFetch($var, $dim, $this->attrs($start));
                    }
                }
                $expr = $this->expr(0);
                $this->expect(self::RBRACE);
                return new Expr\Variable($expr, $this->attrs($start));
            case \T_CURLY_OPEN:
                $this->advance();
                $var = $this->variable();
                $this->expect(self::RBRACE);
                return $var;
        }
        $this->fail('Expected interpolated variable');
    }

    private function encapsVarOffset(): Expr {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case \T_STRING:
                $text = $this->text();
                $this->advance();
                return new Scalar\String_($text, $this->attrs($start));
            case \T_NUM_STRING:
                $text = $this->text();
                $this->advance();
                return $this->parseNumString($text, $this->attrs($start));
            case self::MINUS:
                $this->advance();
                if (!$this->is(\T_NUM_STRING)) {
                    $this->fail('Expected numeric string offset');
                }
                $text = $this->text();
                $this->advance();
                return $this->parseNumString('-' . $text, $this->attrs($start));
            case \T_VARIABLE:
                return $this->plainVariable();
        }
        $this->fail('Expected string offset');
    }

    /** A variable-ish expression: primary, postfix chain, then assignment / inc / dec. */
    private function postfixExpr(): Expr {
        $start = $this->pos;
        $node = $this->primary();
        return $this->afterPrimary($this->postfixChain($node, $start), $start);
    }

    /** Assignment, compound assignment and postfix inc/dec on a variable; anything else passes through. */
    private function afterPrimary(Expr $node, int $start): Expr {
        if ($this->kind !== self::K_VAR) {
            return $node;
        }
        $id = $this->id;
        if ($id === self::EQUALS) {
            $this->advance();
            if (self::isAmpersand($this->id)) {
                $this->advance();
                if ($this->is(\T_NEW)) {
                    $rhs = $this->newExpr();
                    $ref = new Expr\AssignRef($node, $rhs, $this->attrs($start));
                    if (!$this->phpVersion->allowsAssignNewByReference()) {
                        $this->emitError(new Error('Cannot assign new by reference', $ref->getAttributes()));
                    }
                    return $ref;
                }
                $rhs = $this->variable();
                return new Expr\AssignRef($node, $rhs, $this->attrs($start));
            }
            $rhs = $this->expr(self::P_ASSIGN);
            return new Expr\Assign($node, $rhs, $this->attrs($start));
        }
        if (isset(self::ASSIGN_OPS[$id])) {
            $this->advance();
            $rhs = $this->expr(self::P_ASSIGN);
            return $this->assignOpNode($id, $node, $rhs, $this->attrs($start));
        }
        if ($id === \T_INC) {
            $this->advance();
            return new Expr\PostInc($node, $this->attrs($start));
        }
        if ($id === \T_DEC) {
            $this->advance();
            return new Expr\PostDec($node, $this->attrs($start));
        }
        return $node;
    }

    /** The postfix operations the grammar allows after $node (per $this->kind); updates $this->kind. */
    private function postfixChain(Expr|Name $node, int $start): Expr {
        for (;;) {
            $kind = $this->kind;
            $id = $this->id;
            switch ($id) {
                case self::LBRACKET:
                    if ($kind === self::K_NONE || $node instanceof Name) {
                        break 2;
                    }
                    $this->advance();
                    $dim = $this->is(self::RBRACKET) ? null : $this->expr(0);
                    $this->expect(self::RBRACKET);
                    $node = new Expr\ArrayDimFetch($node, $dim, $this->attrs($start));
                    $this->kind = self::K_VAR;
                    continue 2;
                case \T_OBJECT_OPERATOR:
                case \T_NULLSAFE_OBJECT_OPERATOR:
                    if ($kind === self::K_NONE || $node instanceof Name) {
                        break 2;
                    }
                    $nullsafe = $this->is(\T_NULLSAFE_OBJECT_OPERATOR);
                    $this->advance();
                    $name = $this->propertyName();
                    if ($this->is(self::LPAREN)) {
                        $args = $this->argumentList();
                        $node = $nullsafe
                            ? new Expr\NullsafeMethodCall($node, $name, $args, $this->attrs($start))
                            : new Expr\MethodCall($node, $name, $args, $this->attrs($start));
                    } else {
                        $node = $nullsafe
                            ? new Expr\NullsafePropertyFetch($node, $name, $this->attrs($start))
                            : new Expr\PropertyFetch($node, $name, $this->attrs($start));
                    }
                    $this->kind = self::K_VAR;
                    continue 2;
                case \T_PAAMAYIM_NEKUDOTAYIM:
                    if ($kind === self::K_NONE || $kind === self::K_CONST) {
                        break 2;
                    }
                    $this->advance();
                    $node = $this->staticMember($node, $start);
                    continue 2;
                case self::LPAREN:
                    if ($kind === self::K_DEREF
                        || ($kind === self::K_VAR && !($node instanceof Expr\PropertyFetch)
                            && !($node instanceof Expr\NullsafePropertyFetch) && !($node instanceof Expr\StaticPropertyFetch))) {
                        $args = $this->argumentList();
                        $node = new Expr\FuncCall($node, $args, $this->attrs($start));
                        $this->kind = self::K_VAR;
                        continue 2;
                    }
                    break 2;
            }
            break;
        }
        if ($node instanceof Name) {
            $this->fail('Expected :: after class name');
        }
        return $node;
    }

    /** After `::`: static_member, class_constant or a static call. */
    private function staticMember(Expr|Name $class, int $start): Expr {
        $id = $this->id;
        if ($id === \T_VARIABLE || $id === self::DOLLAR) {
            $memberStart = $this->pos;
            $var = $this->simpleVariable();
            if ($this->is(self::LPAREN)) {
                $args = $this->argumentList();
                $this->kind = self::K_VAR;
                return new Expr\StaticCall($class, $var, $args, $this->attrs($start));
            }
            $name = $var->name;
            $prop = \is_string($name) ? new Node\VarLikeIdentifier($name, $this->getAttributes($memberStart, $this->last)) : $name;
            $this->kind = self::K_VAR;
            return new Expr\StaticPropertyFetch($class, $prop, $this->attrs($start));
        }
        if ($id === self::LBRACE) {
            $this->advance();
            $expr = $this->expr(0);
            $this->expect(self::RBRACE);
            if ($this->is(self::LPAREN)) {
                $args = $this->argumentList();
                $this->kind = self::K_VAR;
                return new Expr\StaticCall($class, $expr, $args, $this->attrs($start));
            }
            $this->kind = self::K_DEREF;
            return new Expr\ClassConstFetch($class, $expr, $this->attrs($start));
        }
        $name = $this->identifierMaybeReserved();
        if ($this->is(self::LPAREN)) {
            $args = $this->argumentList();
            $this->kind = self::K_VAR;
            return new Expr\StaticCall($class, $name, $args, $this->attrs($start));
        }
        $this->kind = self::K_DEREF;
        return new Expr\ClassConstFetch($class, $name, $this->attrs($start));
    }

    /** property_name: identifier | {expr} | simple_variable */
    private function propertyName(): Identifier|Expr {
        if ($this->is(\T_STRING)) {
            return $this->identifierNotReserved();
        }
        if ($this->is(self::LBRACE)) {
            $this->advance();
            $expr = $this->expr(0);
            $this->expect(self::RBRACE);
            return $expr;
        }
        if ($this->is(\T_VARIABLE) || $this->is(self::DOLLAR)) {
            return $this->simpleVariable();
        }
        $this->fail('Expected property name');
    }

    /** The head of a variable chain; sets $this->kind. */
    private function primary(): Expr|Name {
        $start = $this->pos;
        $id = $this->id;
        switch ($id) {
            case \T_VARIABLE:
            case self::DOLLAR:
                $this->kind = self::K_VAR;
                return $this->simpleVariable();
            case \T_STRING:
            case \T_NAME_QUALIFIED:
            case \T_NAME_FULLY_QUALIFIED:
            case \T_NAME_RELATIVE:
                $name = $this->name();
                if ($this->is(self::LPAREN)) {
                    $args = $this->argumentList();
                    $this->kind = self::K_VAR;
                    return new Expr\FuncCall($name, $args, $this->attrs($start));
                }
                if ($this->is(\T_PAAMAYIM_NEKUDOTAYIM)) {
                    $this->kind = self::K_CLASSNAME;
                    return $name;
                }
                $this->kind = self::K_CONST;
                return new Expr\ConstFetch($name, $this->attrs($start));
            case \T_STATIC:
                $name = new Name('static', $this->tokAttrs($start));
                $this->advance();
                $this->kind = self::K_CLASSNAME;
                return $name;
            case \T_READONLY:
                $name = new Name($this->text(), $this->tokAttrs($start));
                $this->advance();
                if (!$this->is(self::LPAREN)) {
                    $this->fail('Expected ( after readonly');
                }
                $args = $this->argumentList();
                $this->kind = self::K_VAR;
                return new Expr\FuncCall($name, $args, $this->attrs($start));
            case self::LPAREN:
                $this->advance();
                $expr = $this->expr(0);
                $this->expect(self::RPAREN);
                if ($expr instanceof Expr\ArrowFunction) {
                    $this->parenthesizedArrowFunctions->offsetSet($expr);
                }
                $this->kind = self::K_DEREF;
                return $expr;
            case \T_NEW:
                return $this->newExpr();
            case \T_CONSTANT_ENCAPSED_STRING:
                $text = $this->text();
                $this->advance();
                $this->kind = self::K_DEREF;
                return Scalar\String_::fromString($text, $this->attrs($start), $this->phpVersion->supportsUnicodeEscapes());
            case self::DQUOTE:
                $this->advance();
                $parts = $this->encapsList(self::DQUOTE);
                $this->expect(self::DQUOTE);
                $attrs = $this->attrs($start);
                $attrs->kind = Scalar\String_::KIND_DOUBLE_QUOTED;
                $this->parseEncapsed($parts, '"');
                $this->kind = self::K_DEREF;
                return new Scalar\InterpolatedString($parts, $attrs);
            case \T_ARRAY:
                $this->advance();
                $this->expect(self::LPAREN);
                $items = $this->arrayPairList(self::RPAREN);
                $this->expect(self::RPAREN);
                $attrs = $this->attrs($start);
                $attrs->kind = Expr\Array_::KIND_LONG;
                $array = new Expr\Array_($items, $attrs);
                $this->createdArrays->offsetSet($array);
                $this->kind = self::K_DEREF;
                return $array;
            case self::LBRACKET:
                $array = $this->shortArray();
                if ($this->is(self::EQUALS)) {
                    $this->advance();
                    $list = $this->fixupArrayDestructuring($array);
                    $rhs = $this->expr(self::P_ASSIGN);
                    $this->kind = self::K_NONE;
                    return new Expr\Assign($list, $rhs, $this->attrs($start));
                }
                $this->createdArrays->offsetSet($array);
                $this->kind = self::K_DEREF;
                return $array;
        }
        if (isset(self::MAGIC_CONSTS[$id])) {
            $this->advance();
            $this->kind = self::K_CONST;
            $attrs = $this->attrs($start);
            switch ($id) {
                case \T_LINE: return new Scalar\MagicConst\Line($attrs);
                case \T_FILE: return new Scalar\MagicConst\File($attrs);
                case \T_DIR: return new Scalar\MagicConst\Dir($attrs);
                case \T_CLASS_C: return new Scalar\MagicConst\Class_($attrs);
                case \T_TRAIT_C: return new Scalar\MagicConst\Trait_($attrs);
                case \T_METHOD_C: return new Scalar\MagicConst\Method($attrs);
                case \T_FUNC_C: return new Scalar\MagicConst\Function_($attrs);
                case \T_NS_C: return new Scalar\MagicConst\Namespace_($attrs);
                default: return new Scalar\MagicConst\Property($attrs);
            }
        }
        $this->fail('Expected expression');
    }

    private function shortArray(): Expr\Array_ {
        $start = $this->pos;
        $this->expect(self::LBRACKET);
        $items = $this->arrayPairList(self::RBRACKET);
        $this->expect(self::RBRACKET);
        $attrs = $this->attrs($start);
        $attrs->kind = Expr\Array_::KIND_SHORT;
        return new Expr\Array_($items, $attrs);
    }

    /** array_pair_list: inner_array_pair_list with a trailing empty element dropped */
    /** @return list<Node\ArrayItem> */
    private function arrayPairList(int $end): array {
        $items = $this->innerArrayPairList($end);
        $last = \count($items) - 1;
        if ($last >= 0 && $items[$last]->value instanceof Expr\Error) {
            array_pop($items);
        }
        return $items;
    }

    /** @return list<Node\ArrayItem> */
    private function innerArrayPairList(int $end): array {
        $items = [];
        for (;;) {
            if ($this->is(self::COMMA) || $this->is($end)) {
                $attrs = $this->createEmptyElemAttributes($this->pos);
                $items[] = new Node\ArrayItem(new Expr\Error($attrs), null, false, $attrs);
                $this->noteAttrs($attrs, $this->pos);
            } else {
                $items[] = $this->arrayPair();
            }
            if (!$this->is(self::COMMA)) {
                return $items;
            }
            $this->advance();
        }
    }

    private function arrayPair(): Node\ArrayItem {
        $start = $this->pos;
        if (self::isAmpersand($this->id)) {
            $this->advance();
            $var = $this->variable();
            return new Node\ArrayItem($var, null, true, $this->attrs($start));
        }
        if ($this->is(\T_LIST)) {
            $list = $this->listExpr();
            return new Node\ArrayItem($list, null, false, $this->attrs($start));
        }
        if ($this->is(\T_ELLIPSIS)) {
            $this->advance();
            $expr = $this->expr(0);
            return new Node\ArrayItem($expr, null, false, $this->attrs($start), true);
        }
        $expr = $this->expr(0);
        if (!$this->is(\T_DOUBLE_ARROW)) {
            return new Node\ArrayItem($expr, null, false, $this->attrs($start));
        }
        $this->advance();
        if (self::isAmpersand($this->id)) {
            $this->advance();
            $var = $this->variable();
            return new Node\ArrayItem($var, $expr, true, $this->attrs($start));
        }
        if ($this->is(\T_LIST)) {
            $list = $this->listExpr();
            return new Node\ArrayItem($list, $expr, false, $this->attrs($start));
        }
        $value = $this->expr(0);
        return new Node\ArrayItem($value, $expr, false, $this->attrs($start));
    }

    private function listExpr(): Expr\List_ {
        $start = $this->pos;
        $this->expect(\T_LIST);
        $this->expect(self::LPAREN);
        $items = $this->innerArrayPairList(self::RPAREN);
        $this->expect(self::RPAREN);
        $list = new Expr\List_($items, $this->attrs($start));
        $list->attrs()->kind = Expr\List_::KIND_LIST;
        $this->postprocessList($list);
        return $list;
    }

    /** new_expr; sets $this->kind (K_DEREF with arguments or an anonymous class, K_NONE otherwise) */
    private function newExpr(): Expr {
        $start = $this->pos;
        $this->expect(\T_NEW);
        $id = $this->id;
        if ($id === \T_ATTRIBUTE || $id === \T_CLASS || (isset(self::CLASS_MODIFIERS[$id]) && $this->classAhead(1))) {
            $cstart = $this->pos;
            $attrGroups = $this->optionalAttributes();
            $flags = $this->classEntryType();
            $args = $this->is(self::LPAREN) ? $this->argumentList() : [];
            $extends = $this->accept(\T_EXTENDS) ? $this->className() : null;
            $implements = $this->accept(\T_IMPLEMENTS) ? $this->classNameList() : [];
            $stmts = $this->classBody();
            $class = new Stmt\Class_(null, [
                'type' => $flags, 'extends' => $extends, 'implements' => $implements, 'stmts' => $stmts,
                'attrGroups' => $attrGroups,
            ], $this->attrs($cstart));
            $this->checkClass($class, -1);
            $this->kind = self::K_DEREF;
            return new Expr\New_($class, $args, $this->attrs($start));
        }
        $class = $this->classNameReference();
        if ($this->is(self::LPAREN)) {
            $args = $this->argumentList();
            $this->kind = self::K_DEREF;
            return new Expr\New_($class, $args, $this->attrs($start));
        }
        $this->kind = self::K_NONE;
        return new Expr\New_($class, [], $this->attrs($start));
    }

    /** class_name_reference: class_name | new_variable | '(' expr ')' */
    private function classNameReference(): Name|Expr {
        $start = $this->pos;
        $id = $this->id;
        if ($id === \T_STATIC || $this->isNameToken($id)) {
            $name = $this->className();
            if (!$this->is(\T_PAAMAYIM_NEKUDOTAYIM)) {
                return $name;
            }
            $this->advance();
            $prop = $this->staticMemberPropName();
            return $this->newVariableChain(new Expr\StaticPropertyFetch($name, $prop, $this->attrs($start)), $start);
        }
        if ($id === self::LPAREN) {
            $this->advance();
            $expr = $this->expr(0);
            $this->expect(self::RPAREN);
            return $expr;
        }
        if ($id === \T_VARIABLE || $id === self::DOLLAR) {
            return $this->newVariableChain($this->simpleVariable(), $start);
        }
        $this->fail('Expected class reference');
    }

    /** new_variable postfix operations: [dim], ->prop, ?->prop, ::$prop */
    private function newVariableChain(Expr $node, int $start): Expr {
        for (;;) {
            $id = $this->id;
            switch ($id) {
                case self::LBRACKET:
                    $this->advance();
                    $dim = $this->is(self::RBRACKET) ? null : $this->expr(0);
                    $this->expect(self::RBRACKET);
                    $node = new Expr\ArrayDimFetch($node, $dim, $this->attrs($start));
                    continue 2;
                case \T_OBJECT_OPERATOR:
                    $this->advance();
                    $name = $this->propertyName();
                    $node = new Expr\PropertyFetch($node, $name, $this->attrs($start));
                    continue 2;
                case \T_NULLSAFE_OBJECT_OPERATOR:
                    $this->advance();
                    $name = $this->propertyName();
                    $node = new Expr\NullsafePropertyFetch($node, $name, $this->attrs($start));
                    continue 2;
                case \T_PAAMAYIM_NEKUDOTAYIM:
                    $this->advance();
                    $prop = $this->staticMemberPropName();
                    $node = new Expr\StaticPropertyFetch($node, $prop, $this->attrs($start));
                    continue 2;
            }
            return $node;
        }
    }

    // ---- arguments ----

    /** @return list<Node\Arg|Node\ArgPlaceholder|Node\VariadicPlaceholder> */
    private function argumentList(): array {
        $this->expect(self::LPAREN);
        if ($this->is(self::RPAREN)) {
            $this->advance();
            return [];
        }
        $args = [];
        do {
            $args[] = $this->argument();
        } while ($this->accept(self::COMMA) && !$this->is(self::RPAREN));
        $this->expect(self::RPAREN);
        return $args;
    }

    private function argumentStartsNoExpr(int $id): bool {
        if ($id === \T_ELLIPSIS || $id === self::QUESTION || self::isAmpersand($this->id)) {
            return true;
        }
        return $this->isIdentifierMaybeReserved($id) && $this->peek(1) === self::COLON;
    }

    /** @return Node\Arg|Node\ArgPlaceholder|Node\VariadicPlaceholder */
    private function argument(): Node {
        if ($this->argumentStartsNoExpr($this->id)) {
            return $this->argumentNoExpr();
        }
        $start = $this->pos;
        $expr = $this->expr(0);
        return new Node\Arg($expr, false, false, $this->attrs($start));
    }

    /** @return Node\Arg|Node\ArgPlaceholder|Node\VariadicPlaceholder */
    private function argumentNoExpr(): Node {
        $start = $this->pos;
        $id = $this->id;
        if (self::isAmpersand($this->id)) {
            $this->advance();
            $var = $this->variable();
            return new Node\Arg($var, true, false, $this->attrs($start));
        }
        if ($id === \T_ELLIPSIS) {
            $this->advance();
            if (!isset(self::EXPR_START[$this->id])) {
                return new Node\VariadicPlaceholder($this->attrs($start));
            }
            $expr = $this->expr(0);
            return new Node\Arg($expr, false, true, $this->attrs($start));
        }
        if ($id === self::QUESTION) {
            $this->advance();
            return new Node\ArgPlaceholder(null, $this->attrs($start));
        }
        $name = $this->identifierMaybeReserved();
        $this->expect(self::COLON);
        if ($this->is(self::QUESTION)) {
            $this->advance();
            return new Node\ArgPlaceholder($name, $this->attrs($start));
        }
        $expr = $this->expr(0);
        return new Node\Arg($expr, false, false, $this->attrs($start), $name);
    }
}
