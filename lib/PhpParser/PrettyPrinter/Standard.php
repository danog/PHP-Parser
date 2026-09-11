<?php declare(strict_types=1);

namespace PhpParser\PrettyPrinter;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinterAbstract;

class Standard extends PrettyPrinterAbstract {
    // Special nodes

    protected function pParam(Node\Param $node): string {
        return $this->pAttrGroups($node->attrGroups, $this->phpVersion->supportsAttributes())
             . $this->pModifiers($node->flags)
             . ($node->type ? $this->p($node->type) . ' ' : '')
             . ($node->byRef ? '&' : '')
             . ($node->variadic ? '...' : '')
             . $this->p($node->var)
             . ($node->default ? ' = ' . $this->p($node->default) : '')
             . ($node->hooks ? ' {' . $this->pStmts($node->hooks) . $this->nl . '}' : '');
    }

    protected function pArg(Node\Arg $node): string {
        return ($node->name ? $node->name->toString() . ': ' : '')
             . ($node->byRef ? '&' : '') . ($node->unpack ? '...' : '')
             . $this->p($node->value);
    }

    protected function pVariadicPlaceholder(Node\VariadicPlaceholder $node): string {
        return '...';
    }

    protected function pConst(Node\Const_ $node): string {
        return $node->name . ' = ' . $this->p($node->value);
    }

    protected function pNullableType(Node\NullableType $node): string {
        return '?' . $this->p($node->type);
    }

    protected function pUnionType(Node\UnionType $node): string {
        $types = [];
        foreach ($node->types as $typeNode) {
            if ($typeNode instanceof Node\IntersectionType) {
                $types[] = '('. $this->p($typeNode) . ')';
                continue;
            }
            $types[] = $this->p($typeNode);
        }
        return implode('|', $types);
    }

    protected function pIntersectionType(Node\IntersectionType $node): string {
        return $this->pImplode($node->types, '&');
    }

    protected function pIdentifier(Node\Identifier $node): string {
        return $node->name;
    }

    protected function pVarLikeIdentifier(Node\VarLikeIdentifier $node): string {
        return '$' . $node->name;
    }

    protected function pAttribute(Node\Attribute $node): string {
        return $this->p($node->name)
             . ($node->args ? '(' . $this->pCommaSeparated($node->args) . ')' : '');
    }

    protected function pAttributeGroup(Node\AttributeGroup $node): string {
        return '#[' . $this->pCommaSeparated($node->attrs) . ']';
    }

    // Names

    protected function pName(Name $node): string {
        return $node->name;
    }

    protected function pName_FullyQualified(Name\FullyQualified $node): string {
        return '\\' . $node->name;
    }

    protected function pName_Relative(Name\Relative $node): string {
        return 'namespace\\' . $node->name;
    }

    // Magic Constants

    protected function pScalar_MagicConst_Class(MagicConst\Class_ $node): string {
        return '__CLASS__';
    }

    protected function pScalar_MagicConst_Dir(MagicConst\Dir $node): string {
        return '__DIR__';
    }

    protected function pScalar_MagicConst_File(MagicConst\File $node): string {
        return '__FILE__';
    }

    protected function pScalar_MagicConst_Function(MagicConst\Function_ $node): string {
        return '__FUNCTION__';
    }

    protected function pScalar_MagicConst_Line(MagicConst\Line $node): string {
        return '__LINE__';
    }

    protected function pScalar_MagicConst_Method(MagicConst\Method $node): string {
        return '__METHOD__';
    }

    protected function pScalar_MagicConst_Namespace(MagicConst\Namespace_ $node): string {
        return '__NAMESPACE__';
    }

    protected function pScalar_MagicConst_Trait(MagicConst\Trait_ $node): string {
        return '__TRAIT__';
    }

    protected function pScalar_MagicConst_Property(MagicConst\Property $node): string {
        return '__PROPERTY__';
    }

    // Scalars

    private function indentString(string $str): string {
        return str_replace("\n", $this->nl, $str);
    }

    protected function pScalar_String(Scalar\String_ $node): string {
        $kind = ($node->attrs()->kind ?? Scalar\String_::KIND_SINGLE_QUOTED);
        switch ($kind) {
            case Scalar\String_::KIND_NOWDOC:
                $label = $node->attrs()->docLabel;
                if ($label && !$this->containsEndLabel($node->value, $label)) {
                    $shouldIdent = $this->phpVersion->supportsFlexibleHeredoc();
                    $nl = $shouldIdent ? $this->nl : $this->newline;
                    if ($node->value === '') {
                        return "<<<'$label'$nl$label{$this->docStringEndToken}";
                    }

                    // Make sure trailing \r is not combined with following \n into CRLF.
                    if ($node->value[strlen($node->value) - 1] !== "\r") {
                        $value = $shouldIdent ? $this->indentString($node->value) : $node->value;
                        return "<<<'$label'$nl$value$nl$label{$this->docStringEndToken}";
                    }
                }
                /* break missing intentionally */
                // no break
            case Scalar\String_::KIND_SINGLE_QUOTED:
                return $this->pSingleQuotedString($node->value);
            case Scalar\String_::KIND_HEREDOC:
                $label = $node->attrs()->docLabel;
                $escaped = $this->escapeString($node->value, null);
                if ($label && !$this->containsEndLabel($escaped, $label)) {
                    $nl = $this->phpVersion->supportsFlexibleHeredoc() ? $this->nl : $this->newline;
                    if ($escaped === '') {
                        return "<<<$label$nl$label{$this->docStringEndToken}";
                    }

                    return "<<<$label$nl$escaped$nl$label{$this->docStringEndToken}";
                }
                /* break missing intentionally */
                // no break
            case Scalar\String_::KIND_DOUBLE_QUOTED:
                return '"' . $this->escapeString($node->value, '"') . '"';
        }
        throw new \Exception('Invalid string kind');
    }

    protected function pScalar_InterpolatedString(Scalar\InterpolatedString $node): string {
        if ($node->attrs()->kind === Scalar\String_::KIND_HEREDOC) {
            $label = $node->attrs()->docLabel;
            if ($label && !$this->encapsedContainsEndLabel($node->parts, $label)) {
                $nl = $this->phpVersion->supportsFlexibleHeredoc() ? $this->nl : $this->newline;
                if (count($node->parts) === 1
                    && $node->parts[0] instanceof Node\InterpolatedStringPart
                    && $node->parts[0]->value === ''
                ) {
                    return "<<<$label$nl$label{$this->docStringEndToken}";
                }

                return "<<<$label$nl" . $this->pEncapsList($node->parts, null)
                     . "$nl$label{$this->docStringEndToken}";
            }
        }
        return '"' . $this->pEncapsList($node->parts, '"') . '"';
    }

    protected function pScalar_Int(Scalar\Int_ $node): string {
        if ($node->attrs()->shouldPrintRawValue === true) {
            return $node->attrs()->rawValue;
        }

        if ($node->value === -\PHP_INT_MAX - 1) {
            // PHP_INT_MIN cannot be represented as a literal,
            // because the sign is not part of the literal
            return '(-' . \PHP_INT_MAX . '-1)';
        }

        $kind = ($node->attrs()->kind ?? Scalar\Int_::KIND_DEC);

        if (Scalar\Int_::KIND_DEC === $kind) {
            return (string) $node->value;
        }

        if ($node->value < 0) {
            $sign = '-';
            $str = (string) -$node->value;
        } else {
            $sign = '';
            $str = (string) $node->value;
        }
        switch ($kind) {
            case Scalar\Int_::KIND_BIN:
                return $sign . '0b' . base_convert($str, 10, 2);
            case Scalar\Int_::KIND_OCT:
                return $sign . '0' . base_convert($str, 10, 8);
            case Scalar\Int_::KIND_HEX:
                return $sign . '0x' . base_convert($str, 10, 16);
        }
        throw new \Exception('Invalid number kind');
    }

    protected function pScalar_Float(Scalar\Float_ $node): string {
        if (!is_finite($node->value)) {
            if ($node->value === \INF) {
                return '1.0E+1000';
            }
            if ($node->value === -\INF) {
                return '-1.0E+1000';
            } else {
                return '\NAN';
            }
        }

        // Try to find a short full-precision representation
        $stringValue = sprintf('%.16G', $node->value);
        if ($node->value !== (float) $stringValue) {
            $stringValue = sprintf('%.17G', $node->value);
        }

        // %G is locale dependent and there exists no locale-independent alternative. We don't want
        // mess with switching locales here, so let's assume that a comma is the only non-standard
        // decimal separator we may encounter...
        $stringValue = str_replace(',', '.', $stringValue);

        // ensure that number is really printed as float
        return preg_match('/^-?[0-9]+$/', $stringValue) ? $stringValue . '.0' : $stringValue;
    }

    // Assignments

    protected function pExpr_Assign(Expr\Assign $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\Assign::class, $this->p($node->var) . ' = ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignRef(Expr\AssignRef $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\AssignRef::class, $this->p($node->var) . ' =& ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Plus(AssignOp\Plus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Plus::class, $this->p($node->var) . ' += ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Minus(AssignOp\Minus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Minus::class, $this->p($node->var) . ' -= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Mul(AssignOp\Mul $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Mul::class, $this->p($node->var) . ' *= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Div(AssignOp\Div $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Div::class, $this->p($node->var) . ' /= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Concat(AssignOp\Concat $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Concat::class, $this->p($node->var) . ' .= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Mod(AssignOp\Mod $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Mod::class, $this->p($node->var) . ' %= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_BitwiseAnd(AssignOp\BitwiseAnd $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\BitwiseAnd::class, $this->p($node->var) . ' &= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_BitwiseOr(AssignOp\BitwiseOr $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\BitwiseOr::class, $this->p($node->var) . ' |= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_BitwiseXor(AssignOp\BitwiseXor $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\BitwiseXor::class, $this->p($node->var) . ' ^= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_ShiftLeft(AssignOp\ShiftLeft $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\ShiftLeft::class, $this->p($node->var) . ' <<= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_ShiftRight(AssignOp\ShiftRight $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\ShiftRight::class, $this->p($node->var) . ' >>= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Pow(AssignOp\Pow $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Pow::class, $this->p($node->var) . ' **= ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_AssignOp_Coalesce(AssignOp\Coalesce $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(AssignOp\Coalesce::class, $this->p($node->var) . ' ??= ', $node->expr, $precedence, $lhsPrecedence);
    }

    // Binary expressions

    protected function pExpr_BinaryOp_Plus(BinaryOp\Plus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Plus::class, $node->left, ' + ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Minus(BinaryOp\Minus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Minus::class, $node->left, ' - ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Mul(BinaryOp\Mul $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Mul::class, $node->left, ' * ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Div(BinaryOp\Div $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Div::class, $node->left, ' / ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Concat(BinaryOp\Concat $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Concat::class, $node->left, ' . ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Mod(BinaryOp\Mod $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Mod::class, $node->left, ' % ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_BooleanAnd(BinaryOp\BooleanAnd $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\BooleanAnd::class, $node->left, ' && ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_BooleanOr(BinaryOp\BooleanOr $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\BooleanOr::class, $node->left, ' || ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_BitwiseAnd(BinaryOp\BitwiseAnd $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\BitwiseAnd::class, $node->left, ' & ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_BitwiseOr(BinaryOp\BitwiseOr $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\BitwiseOr::class, $node->left, ' | ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_BitwiseXor(BinaryOp\BitwiseXor $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\BitwiseXor::class, $node->left, ' ^ ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_ShiftLeft(BinaryOp\ShiftLeft $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\ShiftLeft::class, $node->left, ' << ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_ShiftRight(BinaryOp\ShiftRight $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\ShiftRight::class, $node->left, ' >> ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Pow(BinaryOp\Pow $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Pow::class, $node->left, ' ** ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_LogicalAnd(BinaryOp\LogicalAnd $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\LogicalAnd::class, $node->left, ' and ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_LogicalOr(BinaryOp\LogicalOr $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\LogicalOr::class, $node->left, ' or ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_LogicalXor(BinaryOp\LogicalXor $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\LogicalXor::class, $node->left, ' xor ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Equal(BinaryOp\Equal $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Equal::class, $node->left, ' == ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_NotEqual(BinaryOp\NotEqual $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\NotEqual::class, $node->left, ' != ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Identical(BinaryOp\Identical $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Identical::class, $node->left, ' === ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_NotIdentical(BinaryOp\NotIdentical $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\NotIdentical::class, $node->left, ' !== ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Spaceship(BinaryOp\Spaceship $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Spaceship::class, $node->left, ' <=> ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Greater(BinaryOp\Greater $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Greater::class, $node->left, ' > ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_GreaterOrEqual(BinaryOp\GreaterOrEqual $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\GreaterOrEqual::class, $node->left, ' >= ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Smaller(BinaryOp\Smaller $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Smaller::class, $node->left, ' < ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_SmallerOrEqual(BinaryOp\SmallerOrEqual $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\SmallerOrEqual::class, $node->left, ' <= ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Coalesce(BinaryOp\Coalesce $node, int $precedence, int $lhsPrecedence): string {
        return $this->pInfixOp(BinaryOp\Coalesce::class, $node->left, ' ?? ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_Pipe(BinaryOp\Pipe $node, int $precedence, int $lhsPrecedence): string {
        if ($node->right instanceof Expr\ArrowFunction) {
            // Force parentheses around arrow functions.
            $lhsPrecedence = $this->precedenceMap[Expr\ArrowFunction::class][0];
        }
        return $this->pInfixOp(BinaryOp\Pipe::class, $node->left, ' |> ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Instanceof(Expr\Instanceof_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPostfixOp(
            Expr\Instanceof_::class, $node->expr,
            ' instanceof ' . $this->pNewOperand($node->class),
            $precedence, $lhsPrecedence);
    }

    // Unary expressions

    protected function pExpr_BooleanNot(Expr\BooleanNot $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\BooleanNot::class, '!', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BitwiseNot(Expr\BitwiseNot $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\BitwiseNot::class, '~', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_UnaryMinus(Expr\UnaryMinus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\UnaryMinus::class, '-', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_UnaryPlus(Expr\UnaryPlus $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\UnaryPlus::class, '+', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_PreInc(Expr\PreInc $node): string {
        return '++' . $this->p($node->var);
    }

    protected function pExpr_PreDec(Expr\PreDec $node): string {
        return '--' . $this->p($node->var);
    }

    protected function pExpr_PostInc(Expr\PostInc $node): string {
        return $this->p($node->var) . '++';
    }

    protected function pExpr_PostDec(Expr\PostDec $node): string {
        return $this->p($node->var) . '--';
    }

    protected function pExpr_ErrorSuppress(Expr\ErrorSuppress $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\ErrorSuppress::class, '@', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_YieldFrom(Expr\YieldFrom $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\YieldFrom::class, 'yield from ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Print(Expr\Print_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\Print_::class, 'print ', $node->expr, $precedence, $lhsPrecedence);
    }

    // Casts

    protected function pExpr_Cast_Int(Cast\Int_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Int_::class, '(int) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Double(Cast\Double $node, int $precedence, int $lhsPrecedence): string {
        $kind = ($node->attrs()->kind ?? Cast\Double::KIND_DOUBLE);
        if ($kind === Cast\Double::KIND_DOUBLE) {
            $cast = '(double)';
        } elseif ($kind === Cast\Double::KIND_FLOAT) {
            $cast = '(float)';
        } else {
            assert($kind === Cast\Double::KIND_REAL);
            $cast = '(real)';
        }
        return $this->pPrefixOp(Cast\Double::class, $cast . ' ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_String(Cast\String_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\String_::class, '(string) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Array(Cast\Array_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Array_::class, '(array) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Object(Cast\Object_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Object_::class, '(object) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Bool(Cast\Bool_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Bool_::class, '(bool) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Unset(Cast\Unset_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Unset_::class, '(unset) ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Cast_Void(Cast\Void_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Cast\Void_::class, '(void) ', $node->expr, $precedence, $lhsPrecedence);
    }

    // Function calls and similar constructs

    protected function pExpr_FuncCall(Expr\FuncCall $node): string {
        return $this->pCallLhs($node->name)
             . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_MethodCall(Expr\MethodCall $node): string {
        return $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name)
             . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_NullsafeMethodCall(Expr\NullsafeMethodCall $node): string {
        return $this->pDereferenceLhs($node->var) . '?->' . $this->pObjectProperty($node->name)
            . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_StaticCall(Expr\StaticCall $node): string {
        return $this->pStaticDereferenceLhs($node->class) . '::'
             . ($node->name instanceof Expr
                ? ($node->name instanceof Expr\Variable
                   ? $this->p($node->name)
                   : '{' . $this->p($node->name) . '}')
                : $node->name)
             . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_Empty(Expr\Empty_ $node): string {
        return 'empty(' . $this->p($node->expr) . ')';
    }

    protected function pExpr_Isset(Expr\Isset_ $node): string {
        return 'isset(' . $this->pCommaSeparated($node->vars) . ')';
    }

    protected function pExpr_Eval(Expr\Eval_ $node): string {
        return 'eval(' . $this->p($node->expr) . ')';
    }

    protected function pExpr_Include(Expr\Include_ $node, int $precedence, int $lhsPrecedence): string {
        /** @var array<int, string> $map */
        $map = [
            Expr\Include_::TYPE_INCLUDE      => 'include',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            Expr\Include_::TYPE_REQUIRE      => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
        ];

        return $this->pPrefixOp(Expr\Include_::class, $map[$node->type] . ' ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_List(Expr\List_ $node): string {
        $syntax = ($node->attrs()->kind ?? ($this->phpVersion->supportsShortArrayDestructuring() ? Expr\List_::KIND_ARRAY : Expr\List_::KIND_LIST));
        if ($syntax === Expr\List_::KIND_ARRAY) {
            return '[' . $this->pMaybeMultiline($node->items, true) . ']';
        } else {
            return 'list(' . $this->pMaybeMultiline($node->items, true) . ')';
        }
    }

    // Other

    protected function pExpr_Error(Expr\Error $node): string {
        throw new \LogicException('Cannot pretty-print AST with Error nodes');
    }

    protected function pExpr_Variable(Expr\Variable $node): string {
        if ($node->name instanceof Expr) {
            return '${' . $this->p($node->name) . '}';
        } else {
            return '$' . $node->name;
        }
    }

    protected function pExpr_Array(Expr\Array_ $node): string {
        $syntax = ($node->attrs()->kind ?? ($this->shortArraySyntax ? Expr\Array_::KIND_SHORT : Expr\Array_::KIND_LONG));
        if ($syntax === Expr\Array_::KIND_SHORT) {
            return '[' . $this->pMaybeMultiline($node->items, true) . ']';
        } else {
            return 'array(' . $this->pMaybeMultiline($node->items, true) . ')';
        }
    }

    protected function pKey(?Node $node): string {
        if ($node === null) {
            return '';
        }

        // => is not really an operator and does not typically participate in precedence resolution.
        // However, there is an exception if yield expressions with keys are involved:
        // [yield $a => $b] is interpreted as [(yield $a => $b)], so we need to ensure that
        // [(yield $a) => $b] is printed with parentheses. We approximate this by lowering the LHS
        // precedence to that of yield (which will also print unnecessary parentheses for rare low
        // precedence unary operators like include).
        $yieldPrecedence = $this->precedenceMap[Expr\Yield_::class][0];
        return $this->p($node, self::MAX_PRECEDENCE, $yieldPrecedence) . ' => ';
    }

    protected function pArrayItem(Node\ArrayItem $node): string {
        return $this->pKey($node->key)
             . ($node->byRef ? '&' : '')
             . ($node->unpack ? '...' : '')
             . $this->p($node->value);
    }

    protected function pExpr_ArrayDimFetch(Expr\ArrayDimFetch $node): string {
        return $this->pDereferenceLhs($node->var)
             . '[' . (null !== $node->dim ? $this->p($node->dim) : '') . ']';
    }

    protected function pExpr_ConstFetch(Expr\ConstFetch $node): string {
        return $this->p($node->name);
    }

    protected function pExpr_ClassConstFetch(Expr\ClassConstFetch $node): string {
        return $this->pStaticDereferenceLhs($node->class) . '::' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_PropertyFetch(Expr\PropertyFetch $node): string {
        return $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_NullsafePropertyFetch(Expr\NullsafePropertyFetch $node): string {
        return $this->pDereferenceLhs($node->var) . '?->' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_StaticPropertyFetch(Expr\StaticPropertyFetch $node): string {
        return $this->pStaticDereferenceLhs($node->class) . '::$' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_ShellExec(Expr\ShellExec $node): string {
        return '`' . $this->pEncapsList($node->parts, '`') . '`';
    }

    protected function pExpr_Closure(Expr\Closure $node): string {
        return $this->pAttrGroups($node->attrGroups, true)
             . $this->pStatic($node->static)
             . 'function ' . ($node->byRef ? '&' : '')
             . '(' . $this->pParams($node->params) . ')'
             . (!empty($node->uses) ? ' use (' . $this->pCommaSeparated($node->uses) . ')' : '')
             . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
             . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pExpr_Match(Expr\Match_ $node): string {
        return 'match (' . $this->p($node->cond) . ') {'
            . $this->pCommaSeparatedMultiline($node->arms, true)
            . $this->nl
            . '}';
    }

    protected function pMatchArm(Node\MatchArm $node): string {
        $result = '';
        if ($node->conds) {
            for ($i = 0, $c = \count($node->conds); $i + 1 < $c; $i++) {
                $result .= $this->p($node->conds[$i]) . ', ';
            }
            $result .= $this->pKey($node->conds[$i]);
        } else {
            $result = 'default => ';
        }
        return $result . $this->p($node->body);
    }

    protected function pExpr_ArrowFunction(Expr\ArrowFunction $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(
            Expr\ArrowFunction::class,
            $this->pAttrGroups($node->attrGroups, true)
            . $this->pStatic($node->static)
            . 'fn' . ($node->byRef ? '&' : '')
            . '(' . $this->pParams($node->params) . ')'
            . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
            . ' => ',
            $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pClosureUse(Node\ClosureUse $node): string {
        return ($node->byRef ? '&' : '') . $this->p($node->var);
    }

    protected function pExpr_New(Expr\New_ $node): string {
        if ($node->class instanceof Stmt\Class_) {
            $args = $node->args ? '(' . $this->pMaybeMultiline($node->args) . ')' : '';
            return 'new ' . $this->pClassCommon($node->class, $args);
        }
        return 'new ' . $this->pNewOperand($node->class)
            . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_Clone(Expr\Clone_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\Clone_::class, 'clone ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Ternary(Expr\Ternary $node, int $precedence, int $lhsPrecedence): string {
        // a bit of cheating: we treat the ternary as a binary op where the ?...: part is the operator.
        // this is okay because the part between ? and : never needs parentheses.
        return $this->pInfixOp(Expr\Ternary::class,
            $node->cond, ' ?' . (null !== $node->if ? ' ' . $this->p($node->if) . ' ' : '') . ': ', $node->else,
            $precedence, $lhsPrecedence
        );
    }

    protected function pExpr_Exit(Expr\Exit_ $node): string {
        $kind = ($node->attrs()->kind ?? Expr\Exit_::KIND_DIE);
        return ($kind === Expr\Exit_::KIND_EXIT ? 'exit' : 'die')
             . (null !== $node->expr ? '(' . $this->p($node->expr) . ')' : '');
    }

    protected function pExpr_Throw(Expr\Throw_ $node, int $precedence, int $lhsPrecedence): string {
        return $this->pPrefixOp(Expr\Throw_::class, 'throw ', $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Yield(Expr\Yield_ $node, int $precedence, int $lhsPrecedence): string {
        if ($node->value === null) {
            $opPrecedence = $this->precedenceMap[Expr\Yield_::class][0];
            return $opPrecedence >= $lhsPrecedence ? '(yield)' : 'yield';
        } else {
            if (!$this->phpVersion->supportsYieldWithoutParentheses()) {
                return '(yield ' . $this->pKey($node->key) . $this->p($node->value) . ')';
            }
            return $this->pPrefixOp(
                Expr\Yield_::class, 'yield ' . $this->pKey($node->key),
                $node->value, $precedence, $lhsPrecedence);
        }
    }

    // Declarations

    protected function pStmt_Namespace(Stmt\Namespace_ $node): string {
        if ($this->canUseSemicolonNamespaces) {
            return 'namespace ' . $this->p($node->name) . ';'
                 . $this->nl . $this->pStmts($node->stmts, false);
        } else {
            return 'namespace' . (null !== $node->name ? ' ' . $this->p($node->name) : '')
                 . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
        }
    }

    protected function pStmt_Use(Stmt\Use_ $node): string {
        return 'use ' . $this->pUseType($node->type)
             . $this->pCommaSeparated($node->uses) . ';';
    }

    protected function pStmt_GroupUse(Stmt\GroupUse $node): string {
        return 'use ' . $this->pUseType($node->type) . $this->pName($node->prefix)
             . '\{' . $this->pCommaSeparated($node->uses) . '};';
    }

    protected function pUseItem(Node\UseItem $node): string {
        return $this->pUseType($node->type) . $this->p($node->name)
             . (null !== $node->alias ? ' as ' . $node->alias : '');
    }

    protected function pUseType(int $type): string {
        return $type === Stmt\Use_::TYPE_FUNCTION ? 'function '
            : ($type === Stmt\Use_::TYPE_CONSTANT ? 'const ' : '');
    }

    protected function pStmt_Interface(Stmt\Interface_ $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . 'interface ' . $node->name
             . (!empty($node->extends) ? ' extends ' . $this->pCommaSeparated($node->extends) : '')
             . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Enum(Stmt\Enum_ $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . 'enum ' . $node->name
             . ($node->scalarType ? ' : ' . $this->p($node->scalarType) : '')
             . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
             . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Class(Stmt\Class_ $node): string {
        return $this->pClassCommon($node, ' ' . $node->name);
    }

    protected function pStmt_Trait(Stmt\Trait_ $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . 'trait ' . $node->name
             . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_EnumCase(Stmt\EnumCase $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . 'case ' . $node->name
             . ($node->expr ? ' = ' . $this->p($node->expr) : '')
             . ';';
    }

    protected function pStmt_TraitUse(Stmt\TraitUse $node): string {
        return 'use ' . $this->pCommaSeparated($node->traits)
             . (empty($node->adaptations)
                ? ';'
                : ' {' . $this->pStmts($node->adaptations) . $this->nl . '}');
    }

    protected function pStmt_TraitUseAdaptation_Precedence(Stmt\TraitUseAdaptation\Precedence $node): string {
        return $this->p($node->trait) . '::' . $node->method
             . ' insteadof ' . $this->pCommaSeparated($node->insteadof) . ';';
    }

    protected function pStmt_TraitUseAdaptation_Alias(Stmt\TraitUseAdaptation\Alias $node): string {
        return (null !== $node->trait ? $this->p($node->trait) . '::' : '')
             . $node->method . ' as'
             . (null !== $node->newModifier ? ' ' . rtrim($this->pModifiers($node->newModifier), ' ') : '')
             . (null !== $node->newName ? ' ' . $node->newName : '')
             . ';';
    }

    protected function pStmt_Property(Stmt\Property $node): string {
        return $this->pAttrGroups($node->attrGroups)
            . (0 === $node->flags ? 'var ' : $this->pModifiers($node->flags))
            . ($node->type ? $this->p($node->type) . ' ' : '')
            . $this->pCommaSeparated($node->props)
            . ($node->hooks ? ' {' . $this->pStmts($node->hooks) . $this->nl . '}' : ';');
    }

    protected function pPropertyItem(Node\PropertyItem $node): string {
        return '$' . $node->name
             . (null !== $node->default ? ' = ' . $this->p($node->default) : '');
    }

    protected function pPropertyHook(Node\PropertyHook $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . $this->pModifiers($node->flags)
             . ($node->byRef ? '&' : '') . $node->name
             . ($node->params ? '(' . $this->pParams($node->params) . ')' : '')
             . (\is_array($node->body) ? ' {' . $this->pStmts($node->body) . $this->nl . '}'
                : ($node->body !== null ? ' => ' . $this->p($node->body) : '') . ';');
    }

    protected function pStmt_ClassMethod(Stmt\ClassMethod $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . $this->pModifiers($node->flags)
             . 'function ' . ($node->byRef ? '&' : '') . $node->name
             . '(' . $this->pParams($node->params) . ')'
             . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
             . (null !== $node->stmts
                ? $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}'
                : ';');
    }

    protected function pStmt_ClassConst(Stmt\ClassConst $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . $this->pModifiers($node->flags)
             . 'const '
             . (null !== $node->type ? $this->p($node->type) . ' ' : '')
             . $this->pCommaSeparated($node->consts) . ';';
    }

    protected function pStmt_Function(Stmt\Function_ $node): string {
        return $this->pAttrGroups($node->attrGroups)
             . 'function ' . ($node->byRef ? '&' : '') . $node->name
             . '(' . $this->pParams($node->params) . ')'
             . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
             . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Const(Stmt\Const_ $node): string {
        return $this->pAttrGroups($node->attrGroups)
            . 'const '
            . $this->pCommaSeparated($node->consts) . ';';
    }

    protected function pStmt_Declare(Stmt\Declare_ $node): string {
        return 'declare (' . $this->pCommaSeparated($node->declares) . ')'
             . (null !== $node->stmts ? ' {' . $this->pStmts($node->stmts) . $this->nl . '}' : ';');
    }

    protected function pDeclareItem(Node\DeclareItem $node): string {
        return $node->key . '=' . $this->p($node->value);
    }

    // Control flow

    protected function pStmt_If(Stmt\If_ $node): string {
        return 'if (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . $this->nl . '}'
             . ($node->elseifs ? ' ' . $this->pImplode($node->elseifs, ' ') : '')
             . (null !== $node->else ? ' ' . $this->p($node->else) : '');
    }

    protected function pStmt_ElseIf(Stmt\ElseIf_ $node): string {
        return 'elseif (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Else(Stmt\Else_ $node): string {
        if (\count($node->stmts) === 1 && $node->stmts[0] instanceof Stmt\If_) {
            // Print as "else if" rather than "else { if }"
            return 'else ' . $this->p($node->stmts[0]);
        }
        return 'else {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_For(Stmt\For_ $node): string {
        return 'for ('
             . $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
             . $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
             . $this->pCommaSeparated($node->loop)
             . ') {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Foreach(Stmt\Foreach_ $node): string {
        return 'foreach (' . $this->p($node->expr) . ' as '
             . (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '')
             . ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ') {'
             . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_While(Stmt\While_ $node): string {
        return 'while (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Do(Stmt\Do_ $node): string {
        return 'do {' . $this->pStmts($node->stmts) . $this->nl
             . '} while (' . $this->p($node->cond) . ');';
    }

    protected function pStmt_Switch(Stmt\Switch_ $node): string {
        return 'switch (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->cases) . $this->nl . '}';
    }

    protected function pStmt_Case(Stmt\Case_ $node): string {
        return (null !== $node->cond ? 'case ' . $this->p($node->cond) : 'default') . ':'
             . $this->pStmts($node->stmts);
    }

    protected function pStmt_TryCatch(Stmt\TryCatch $node): string {
        return 'try {' . $this->pStmts($node->stmts) . $this->nl . '}'
             . ($node->catches ? ' ' . $this->pImplode($node->catches, ' ') : '')
             . ($node->finally !== null ? ' ' . $this->p($node->finally) : '');
    }

    protected function pStmt_Catch(Stmt\Catch_ $node): string {
        return 'catch (' . $this->pImplode($node->types, '|')
             . ($node->var !== null ? ' ' . $this->p($node->var) : '')
             . ') {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Finally(Stmt\Finally_ $node): string {
        return 'finally {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Break(Stmt\Break_ $node): string {
        return 'break' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    protected function pStmt_Continue(Stmt\Continue_ $node): string {
        return 'continue' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    protected function pStmt_Return(Stmt\Return_ $node): string {
        return 'return' . (null !== $node->expr ? ' ' . $this->p($node->expr) : '') . ';';
    }

    protected function pStmt_Label(Stmt\Label $node): string {
        return $node->name . ':';
    }

    protected function pStmt_Goto(Stmt\Goto_ $node): string {
        return 'goto ' . $node->name . ';';
    }

    // Other

    protected function pStmt_Expression(Stmt\Expression $node): string {
        return $this->p($node->expr) . ';';
    }

    protected function pStmt_Echo(Stmt\Echo_ $node): string {
        return 'echo ' . $this->pCommaSeparated($node->exprs) . ';';
    }

    protected function pStmt_Static(Stmt\Static_ $node): string {
        return 'static ' . $this->pCommaSeparated($node->vars) . ';';
    }

    protected function pStmt_Global(Stmt\Global_ $node): string {
        return 'global ' . $this->pCommaSeparated($node->vars) . ';';
    }

    protected function pStaticVar(Node\StaticVar $node): string {
        return $this->p($node->var)
             . (null !== $node->default ? ' = ' . $this->p($node->default) : '');
    }

    protected function pStmt_Unset(Stmt\Unset_ $node): string {
        return 'unset(' . $this->pCommaSeparated($node->vars) . ');';
    }

    protected function pStmt_InlineHTML(Stmt\InlineHTML $node): string {
        $newline = ($node->attrs()->hasLeadingNewline ?? true) ? $this->newline : '';
        return '?>' . $newline . $node->value . '<?php ';
    }

    protected function pStmt_HaltCompiler(Stmt\HaltCompiler $node): string {
        return '__halt_compiler();' . $node->remaining;
    }

    protected function pStmt_Nop(Stmt\Nop $node): string {
        return '';
    }

    protected function pStmt_Block(Stmt\Block $node): string {
        return '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    // Helpers

    protected function pClassCommon(Stmt\Class_ $node, string $afterClassToken): string {
        return $this->pAttrGroups($node->attrGroups, $node->name === null)
            . $this->pModifiers($node->flags)
            . 'class' . $afterClassToken
            . (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '')
            . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
            . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pObjectProperty(Node $node): string {
        if ($node instanceof Expr) {
            return '{' . $this->p($node) . '}';
        } else {
            assert($node instanceof Node\Identifier);
            return $node->name;
        }
    }

    /** @param list<Expr|Node\InterpolatedStringPart> $encapsList */
    protected function pEncapsList(array $encapsList, ?string $quote): string {
        $return = '';
        foreach ($encapsList as $element) {
            if ($element instanceof Node\InterpolatedStringPart) {
                $return .= $this->escapeString($element->value, $quote);
            } else {
                $return .= '{' . $this->p($element) . '}';
            }
        }

        return $return;
    }

    protected function pSingleQuotedString(string $string): string {
        // It is idiomatic to only escape backslashes when necessary, i.e. when followed by ', \ or
        // the end of the string ('Foo\Bar' instead of 'Foo\\Bar'). However, we also don't want to
        // produce an odd number of backslashes, so '\\\\a' should not get rendered as '\\\a', even
        // though that would be legal.
        $regex = '/\'|\\\\(?=[\'\\\\]|$)|(?<=\\\\)\\\\/';
        return '\'' . preg_replace($regex, '\\\\$0', $string) . '\'';
    }

    protected function escapeString(string $string, ?string $quote): string {
        if (null === $quote) {
            // For doc strings, don't escape newlines
            $escaped = addcslashes($string, "\t\f\v$\\");
            // But do escape isolated \r. Combined with the terminating newline, it might get
            // interpreted as \r\n and dropped from the string contents.
            $escaped = preg_replace('/\r(?!\n)/', '\\r', $escaped);
            if ($this->phpVersion->supportsFlexibleHeredoc()) {
                $escaped = $this->indentString($escaped);
            }
        } else {
            $escaped = addcslashes($string, "\n\r\t\f\v$" . $quote . "\\");
        }

        // Escape control characters and non-UTF-8 characters.
        // Regex based on https://stackoverflow.com/a/11709412/385378.
        $regex = '/(
              [\x00-\x08\x0E-\x1F] # Control characters
            | [\xC0-\xC1] # Invalid UTF-8 Bytes
            | [\xF5-\xFF] # Invalid UTF-8 Bytes
            | \xE0(?=[\x80-\x9F]) # Overlong encoding of prior code point
            | \xF0(?=[\x80-\x8F]) # Overlong encoding of prior code point
            | [\xC2-\xDF](?![\x80-\xBF]) # Invalid UTF-8 Sequence Start
            | [\xE0-\xEF](?![\x80-\xBF]{2}) # Invalid UTF-8 Sequence Start
            | [\xF0-\xF4](?![\x80-\xBF]{3}) # Invalid UTF-8 Sequence Start
            | (?<=[\x00-\x7F\xF5-\xFF])[\x80-\xBF] # Invalid UTF-8 Sequence Middle
            | (?<![\xC2-\xDF]|[\xE0-\xEF]|[\xE0-\xEF][\x80-\xBF]|[\xF0-\xF4]|[\xF0-\xF4][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{2})[\x80-\xBF] # Overlong Sequence
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) # Short 3 byte sequence
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) # Short 4 byte sequence
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) # Short 4 byte sequence (2)
        )/x';
        return preg_replace_callback($regex, function ($matches): string {
            assert(strlen($matches[0]) === 1);
            $hex = dechex(ord($matches[0]));
            return '\\x' . str_pad($hex, 2, '0', \STR_PAD_LEFT);
        }, $escaped);
    }

    protected function containsEndLabel(string $string, string $label, bool $atStart = true): bool {
        $start = $atStart ? '(?:^|[\r\n])[ \t]*' : '[\r\n][ \t]*';
        return false !== strpos($string, $label)
            && preg_match('/' . $start . $label . '(?:$|[^_A-Za-z0-9\x80-\xff])/', $string);
    }

    /** @param list<Expr|Node\InterpolatedStringPart> $parts */
    protected function encapsedContainsEndLabel(array $parts, string $label): bool {
        foreach ($parts as $i => $part) {
            if ($part instanceof Node\InterpolatedStringPart
                && $this->containsEndLabel($this->escapeString($part->value, null), $label, $i === 0)
            ) {
                return true;
            }
        }
        return false;
    }

    protected function pDereferenceLhs(Node $node): string {
        if (!$this->dereferenceLhsRequiresParens($node)) {
            return $this->p($node);
        } else {
            return '(' . $this->p($node) . ')';
        }
    }

    protected function pStaticDereferenceLhs(Node $node): string {
        if (!$this->staticDereferenceLhsRequiresParens($node)) {
            return $this->p($node);
        } else {
            return '(' . $this->p($node) . ')';
        }
    }

    protected function pCallLhs(Node $node): string {
        if (!$this->callLhsRequiresParens($node)) {
            return $this->p($node);
        } else {
            return '(' . $this->p($node) . ')';
        }
    }

    protected function pNewOperand(Node $node): string {
        if (!$this->newOperandRequiresParens($node)) {
            return $this->p($node);
        } else {
            return '(' . $this->p($node) . ')';
        }
    }

    /**
     * @param list<Node> $nodes
     */
    protected function hasNodeWithComments(array $nodes): bool {
        foreach ($nodes as $node) {
            if ($node && $node->getComments()) {
                return true;
            }
        }
        return false;
    }

    /** @param list<Node> $nodes */
    protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string {
        if (!$this->hasNodeWithComments($nodes)) {
            return $this->pCommaSeparated($nodes);
        } else {
            return $this->pCommaSeparatedMultiline($nodes, $trailingComma) . $this->nl;
        }
    }

    /** @param list<Node\Param> $params
     */
    private function hasParamWithAttributes(array $params): bool {
        foreach ($params as $param) {
            if ($param->attrGroups) {
                return true;
            }
        }
        return false;
    }

    /** @param list<Node\Param> $params */
    protected function pParams(array $params): string {
        if ($this->hasNodeWithComments($params) ||
            ($this->hasParamWithAttributes($params) && !$this->phpVersion->supportsAttributes())
        ) {
            return $this->pCommaSeparatedMultiline($params, $this->phpVersion->supportsTrailingCommaInParamList()) . $this->nl;
        }
        return $this->pCommaSeparated($params);
    }

    /** @param list<Node\AttributeGroup> $nodes */
    protected function pAttrGroups(array $nodes, bool $inline = false): string {
        $result = '';
        $sep = $inline ? ' ' : $this->nl;
        foreach ($nodes as $node) {
            $result .= $this->p($node) . $sep;
        }

        return $result;
    }

    /**
     * Prints a node by its class (generated; replaces the $this->{'p' . $node->getType()} lookup).
     */
    protected function pDispatch(Node $node, int $precedence, int $lhsPrecedence): string {
        return match ($node::class) {
            \PhpParser\Node\Arg::class => $this->pArg($node),
            \PhpParser\Node\ArrayItem::class => $this->pArrayItem($node),
            \PhpParser\Node\Attribute::class => $this->pAttribute($node),
            \PhpParser\Node\AttributeGroup::class => $this->pAttributeGroup($node),
            \PhpParser\Node\ClosureUse::class => $this->pClosureUse($node),
            \PhpParser\Node\Const_::class => $this->pConst($node),
            \PhpParser\Node\DeclareItem::class => $this->pDeclareItem($node),
            \PhpParser\Node\Expr\ArrayDimFetch::class => $this->pExpr_ArrayDimFetch($node),
            \PhpParser\Node\Expr\Array_::class => $this->pExpr_Array($node),
            \PhpParser\Node\Expr\ArrowFunction::class => $this->pExpr_ArrowFunction($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Assign::class => $this->pExpr_Assign($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\BitwiseAnd::class => $this->pExpr_AssignOp_BitwiseAnd($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\BitwiseOr::class => $this->pExpr_AssignOp_BitwiseOr($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\BitwiseXor::class => $this->pExpr_AssignOp_BitwiseXor($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Coalesce::class => $this->pExpr_AssignOp_Coalesce($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Concat::class => $this->pExpr_AssignOp_Concat($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Div::class => $this->pExpr_AssignOp_Div($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Minus::class => $this->pExpr_AssignOp_Minus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Mod::class => $this->pExpr_AssignOp_Mod($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Mul::class => $this->pExpr_AssignOp_Mul($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Plus::class => $this->pExpr_AssignOp_Plus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\Pow::class => $this->pExpr_AssignOp_Pow($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\ShiftLeft::class => $this->pExpr_AssignOp_ShiftLeft($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignOp\ShiftRight::class => $this->pExpr_AssignOp_ShiftRight($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\AssignRef::class => $this->pExpr_AssignRef($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\BitwiseAnd::class => $this->pExpr_BinaryOp_BitwiseAnd($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\BitwiseOr::class => $this->pExpr_BinaryOp_BitwiseOr($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\BitwiseXor::class => $this->pExpr_BinaryOp_BitwiseXor($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\BooleanAnd::class => $this->pExpr_BinaryOp_BooleanAnd($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\BooleanOr::class => $this->pExpr_BinaryOp_BooleanOr($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Coalesce::class => $this->pExpr_BinaryOp_Coalesce($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Concat::class => $this->pExpr_BinaryOp_Concat($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Div::class => $this->pExpr_BinaryOp_Div($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Equal::class => $this->pExpr_BinaryOp_Equal($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Greater::class => $this->pExpr_BinaryOp_Greater($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\GreaterOrEqual::class => $this->pExpr_BinaryOp_GreaterOrEqual($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Identical::class => $this->pExpr_BinaryOp_Identical($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\LogicalAnd::class => $this->pExpr_BinaryOp_LogicalAnd($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\LogicalOr::class => $this->pExpr_BinaryOp_LogicalOr($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\LogicalXor::class => $this->pExpr_BinaryOp_LogicalXor($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Minus::class => $this->pExpr_BinaryOp_Minus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Mod::class => $this->pExpr_BinaryOp_Mod($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Mul::class => $this->pExpr_BinaryOp_Mul($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\NotEqual::class => $this->pExpr_BinaryOp_NotEqual($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\NotIdentical::class => $this->pExpr_BinaryOp_NotIdentical($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Pipe::class => $this->pExpr_BinaryOp_Pipe($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Plus::class => $this->pExpr_BinaryOp_Plus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Pow::class => $this->pExpr_BinaryOp_Pow($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\ShiftLeft::class => $this->pExpr_BinaryOp_ShiftLeft($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\ShiftRight::class => $this->pExpr_BinaryOp_ShiftRight($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Smaller::class => $this->pExpr_BinaryOp_Smaller($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\SmallerOrEqual::class => $this->pExpr_BinaryOp_SmallerOrEqual($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BinaryOp\Spaceship::class => $this->pExpr_BinaryOp_Spaceship($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BitwiseNot::class => $this->pExpr_BitwiseNot($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\BooleanNot::class => $this->pExpr_BooleanNot($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Array_::class => $this->pExpr_Cast_Array($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Bool_::class => $this->pExpr_Cast_Bool($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Double::class => $this->pExpr_Cast_Double($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Int_::class => $this->pExpr_Cast_Int($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Object_::class => $this->pExpr_Cast_Object($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\String_::class => $this->pExpr_Cast_String($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Unset_::class => $this->pExpr_Cast_Unset($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Cast\Void_::class => $this->pExpr_Cast_Void($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\ClassConstFetch::class => $this->pExpr_ClassConstFetch($node),
            \PhpParser\Node\Expr\Clone_::class => $this->pExpr_Clone($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Closure::class => $this->pExpr_Closure($node),
            \PhpParser\Node\Expr\ConstFetch::class => $this->pExpr_ConstFetch($node),
            \PhpParser\Node\Expr\Empty_::class => $this->pExpr_Empty($node),
            \PhpParser\Node\Expr\Error::class => $this->pExpr_Error($node),
            \PhpParser\Node\Expr\ErrorSuppress::class => $this->pExpr_ErrorSuppress($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Eval_::class => $this->pExpr_Eval($node),
            \PhpParser\Node\Expr\Exit_::class => $this->pExpr_Exit($node),
            \PhpParser\Node\Expr\FuncCall::class => $this->pExpr_FuncCall($node),
            \PhpParser\Node\Expr\Include_::class => $this->pExpr_Include($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Instanceof_::class => $this->pExpr_Instanceof($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Isset_::class => $this->pExpr_Isset($node),
            \PhpParser\Node\Expr\List_::class => $this->pExpr_List($node),
            \PhpParser\Node\Expr\Match_::class => $this->pExpr_Match($node),
            \PhpParser\Node\Expr\MethodCall::class => $this->pExpr_MethodCall($node),
            \PhpParser\Node\Expr\New_::class => $this->pExpr_New($node),
            \PhpParser\Node\Expr\NullsafeMethodCall::class => $this->pExpr_NullsafeMethodCall($node),
            \PhpParser\Node\Expr\NullsafePropertyFetch::class => $this->pExpr_NullsafePropertyFetch($node),
            \PhpParser\Node\Expr\PostDec::class => $this->pExpr_PostDec($node),
            \PhpParser\Node\Expr\PostInc::class => $this->pExpr_PostInc($node),
            \PhpParser\Node\Expr\PreDec::class => $this->pExpr_PreDec($node),
            \PhpParser\Node\Expr\PreInc::class => $this->pExpr_PreInc($node),
            \PhpParser\Node\Expr\Print_::class => $this->pExpr_Print($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\PropertyFetch::class => $this->pExpr_PropertyFetch($node),
            \PhpParser\Node\Expr\ShellExec::class => $this->pExpr_ShellExec($node),
            \PhpParser\Node\Expr\StaticCall::class => $this->pExpr_StaticCall($node),
            \PhpParser\Node\Expr\StaticPropertyFetch::class => $this->pExpr_StaticPropertyFetch($node),
            \PhpParser\Node\Expr\Ternary::class => $this->pExpr_Ternary($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Throw_::class => $this->pExpr_Throw($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\UnaryMinus::class => $this->pExpr_UnaryMinus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\UnaryPlus::class => $this->pExpr_UnaryPlus($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Variable::class => $this->pExpr_Variable($node),
            \PhpParser\Node\Expr\YieldFrom::class => $this->pExpr_YieldFrom($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Expr\Yield_::class => $this->pExpr_Yield($node, $precedence, $lhsPrecedence),
            \PhpParser\Node\Identifier::class => $this->pIdentifier($node),
            \PhpParser\Node\IntersectionType::class => $this->pIntersectionType($node),
            \PhpParser\Node\MatchArm::class => $this->pMatchArm($node),
            \PhpParser\Node\Name::class => $this->pName($node),
            \PhpParser\Node\Name\FullyQualified::class => $this->pName_FullyQualified($node),
            \PhpParser\Node\Name\Relative::class => $this->pName_Relative($node),
            \PhpParser\Node\NullableType::class => $this->pNullableType($node),
            \PhpParser\Node\Param::class => $this->pParam($node),
            \PhpParser\Node\PropertyHook::class => $this->pPropertyHook($node),
            \PhpParser\Node\PropertyItem::class => $this->pPropertyItem($node),
            \PhpParser\Node\Scalar\Float_::class => $this->pScalar_Float($node),
            \PhpParser\Node\Scalar\Int_::class => $this->pScalar_Int($node),
            \PhpParser\Node\Scalar\InterpolatedString::class => $this->pScalar_InterpolatedString($node),
            \PhpParser\Node\Scalar\MagicConst\Class_::class => $this->pScalar_MagicConst_Class($node),
            \PhpParser\Node\Scalar\MagicConst\Dir::class => $this->pScalar_MagicConst_Dir($node),
            \PhpParser\Node\Scalar\MagicConst\File::class => $this->pScalar_MagicConst_File($node),
            \PhpParser\Node\Scalar\MagicConst\Function_::class => $this->pScalar_MagicConst_Function($node),
            \PhpParser\Node\Scalar\MagicConst\Line::class => $this->pScalar_MagicConst_Line($node),
            \PhpParser\Node\Scalar\MagicConst\Method::class => $this->pScalar_MagicConst_Method($node),
            \PhpParser\Node\Scalar\MagicConst\Namespace_::class => $this->pScalar_MagicConst_Namespace($node),
            \PhpParser\Node\Scalar\MagicConst\Property::class => $this->pScalar_MagicConst_Property($node),
            \PhpParser\Node\Scalar\MagicConst\Trait_::class => $this->pScalar_MagicConst_Trait($node),
            \PhpParser\Node\Scalar\String_::class => $this->pScalar_String($node),
            \PhpParser\Node\StaticVar::class => $this->pStaticVar($node),
            \PhpParser\Node\Stmt\Block::class => $this->pStmt_Block($node),
            \PhpParser\Node\Stmt\Break_::class => $this->pStmt_Break($node),
            \PhpParser\Node\Stmt\Case_::class => $this->pStmt_Case($node),
            \PhpParser\Node\Stmt\Catch_::class => $this->pStmt_Catch($node),
            \PhpParser\Node\Stmt\ClassConst::class => $this->pStmt_ClassConst($node),
            \PhpParser\Node\Stmt\ClassMethod::class => $this->pStmt_ClassMethod($node),
            \PhpParser\Node\Stmt\Class_::class => $this->pStmt_Class($node),
            \PhpParser\Node\Stmt\Const_::class => $this->pStmt_Const($node),
            \PhpParser\Node\Stmt\Continue_::class => $this->pStmt_Continue($node),
            \PhpParser\Node\Stmt\Declare_::class => $this->pStmt_Declare($node),
            \PhpParser\Node\Stmt\Do_::class => $this->pStmt_Do($node),
            \PhpParser\Node\Stmt\Echo_::class => $this->pStmt_Echo($node),
            \PhpParser\Node\Stmt\ElseIf_::class => $this->pStmt_ElseIf($node),
            \PhpParser\Node\Stmt\Else_::class => $this->pStmt_Else($node),
            \PhpParser\Node\Stmt\EnumCase::class => $this->pStmt_EnumCase($node),
            \PhpParser\Node\Stmt\Enum_::class => $this->pStmt_Enum($node),
            \PhpParser\Node\Stmt\Expression::class => $this->pStmt_Expression($node),
            \PhpParser\Node\Stmt\Finally_::class => $this->pStmt_Finally($node),
            \PhpParser\Node\Stmt\For_::class => $this->pStmt_For($node),
            \PhpParser\Node\Stmt\Foreach_::class => $this->pStmt_Foreach($node),
            \PhpParser\Node\Stmt\Function_::class => $this->pStmt_Function($node),
            \PhpParser\Node\Stmt\Global_::class => $this->pStmt_Global($node),
            \PhpParser\Node\Stmt\Goto_::class => $this->pStmt_Goto($node),
            \PhpParser\Node\Stmt\GroupUse::class => $this->pStmt_GroupUse($node),
            \PhpParser\Node\Stmt\HaltCompiler::class => $this->pStmt_HaltCompiler($node),
            \PhpParser\Node\Stmt\If_::class => $this->pStmt_If($node),
            \PhpParser\Node\Stmt\InlineHTML::class => $this->pStmt_InlineHTML($node),
            \PhpParser\Node\Stmt\Interface_::class => $this->pStmt_Interface($node),
            \PhpParser\Node\Stmt\Label::class => $this->pStmt_Label($node),
            \PhpParser\Node\Stmt\Namespace_::class => $this->pStmt_Namespace($node),
            \PhpParser\Node\Stmt\Nop::class => $this->pStmt_Nop($node),
            \PhpParser\Node\Stmt\Property::class => $this->pStmt_Property($node),
            \PhpParser\Node\Stmt\Return_::class => $this->pStmt_Return($node),
            \PhpParser\Node\Stmt\Static_::class => $this->pStmt_Static($node),
            \PhpParser\Node\Stmt\Switch_::class => $this->pStmt_Switch($node),
            \PhpParser\Node\Stmt\TraitUse::class => $this->pStmt_TraitUse($node),
            \PhpParser\Node\Stmt\TraitUseAdaptation\Alias::class => $this->pStmt_TraitUseAdaptation_Alias($node),
            \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence::class => $this->pStmt_TraitUseAdaptation_Precedence($node),
            \PhpParser\Node\Stmt\Trait_::class => $this->pStmt_Trait($node),
            \PhpParser\Node\Stmt\TryCatch::class => $this->pStmt_TryCatch($node),
            \PhpParser\Node\Stmt\Unset_::class => $this->pStmt_Unset($node),
            \PhpParser\Node\Stmt\Use_::class => $this->pStmt_Use($node),
            \PhpParser\Node\Stmt\While_::class => $this->pStmt_While($node),
            \PhpParser\Node\UnionType::class => $this->pUnionType($node),
            \PhpParser\Node\UseItem::class => $this->pUseItem($node),
            \PhpParser\Node\VarLikeIdentifier::class => $this->pVarLikeIdentifier($node),
            \PhpParser\Node\VariadicPlaceholder::class => $this->pVariadicPlaceholder($node),
            default => throw new \LogicException('Unknown node type ' . $node->getType()),
        };
    }
}
