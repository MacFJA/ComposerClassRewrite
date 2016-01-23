<?php

namespace MacFJA\ClassRewrite\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Class Rewriter.
 * A PhpParse Visitor to rewrite a class
 *
 * @package MacFJA\ClassRewrite\Visitor
 * @author  MacFJA
 * @license MIT
 */
class Rewriter extends NodeVisitorAbstract
{
    /**
     * The new namespace to use (if null, no change are made)
     *
     * @var null|string
     */
    protected $newNamespace;
    /**
     * The new class name to use (if null, no change are made)
     *
     * @var null|string
     */
    protected $newClassname;
    /**
     * The new parent to extends (if null, no change are made)
     *
     * @var null|string
     */
    protected $newExtends;

    /**
     * Visitor constructor.
     *
     * @param null|string $newNamespace The new namespace to use (if null, no change will be made)
     * @param null|string $newClassname The new class name to use (if null, no change will be made)
     * @param null|string $newExtends   The new parent to extends (if null, no change will be made)
     */
    public function __construct($newNamespace = null, $newClassname = null, $newExtends = null)
    {
        $this->newNamespace = $newNamespace;
        $this->newClassname = $newClassname;
        $this->newExtends   = $newExtends;
    }

    /**
     * The visitor method
     *
     * @param Node $node The visited node
     *
     * @return false|null|Node|\PhpParser\Node[]|void
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_ && null !== $this->newNamespace) {
            $node->name = new Node\Name($this->newNamespace);
        } elseif ($node instanceof Node\Stmt\Class_) {
            if (null !== $this->newClassname) {
                $node->name = $this->newClassname;
            }
            if (null !== $this->newExtends) {
                $node->extends = new Node\Name($this->newExtends);
            }
        }
    }
}
