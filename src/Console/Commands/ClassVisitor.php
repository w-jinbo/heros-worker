<?php
/**
 * This file is part of Heros-Worker.
 * @contact  chenzf@pvc123.com
 */
namespace Framework\Console\Commands;

use Carbon\Carbon;
use PhpParser\Builder\Property;
use PhpParser\Builder\Use_;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class ClassVisitor
 * @package framework\database\command\makeEntity
 */
class ClassVisitor extends NodeVisitorAbstract
{
    /** @var Field $primaryKeyField */
    private $primaryKeyField;

    private $fields;

    private $newCommentText = [];

    private $table;

    private $fillable = [];

    private $connect;

    public function __construct(array $fields, string $table, string $connect = 'default')
    {
        $this->fields = $fields;
        $this->table = $table;
        $this->connect = $connect;
        $this->init();
    }

    /**
     * @param Node $node
     * @return mixed
     */
    public function leaveNode(Node $node)
    {
        switch ($node) {
            case $node instanceof Node\Stmt\Class_:
                $this->replaceComment($node);
                break;
            case $node instanceof Node\Stmt\Property && $node->props[0]->name->name === 'incrementing':
                $propertyProperty = $node->props[0];
                $propertyProperty->default = new Node\Expr\ConstFetch(new Node\Name(['false'], $propertyProperty->getAttributes()), $propertyProperty->default->getAttributes());
                if ($this->primaryKeyField && $this->primaryKeyField->isAutoIncrement()) {
                    return NodeTraverser::REMOVE_NODE;
                }
                break;
            case $node instanceof Node\Stmt\Property && $node->props[0]->name->name === 'keyType':
                if ($this->primaryKeyField && $this->primaryKeyField->getTypeMap() == 'int') {
                    return NodeTraverser::REMOVE_NODE;
                }
                break;
            case $node instanceof Node\Stmt\Property && $node->props[0]->name->name === 'connection':
                $propertyProperty = $node->props[0];
                if ($this->connect === 'default') {
                    return NodeTraverser::REMOVE_NODE;
                }
                $propertyProperty->default = new Node\Scalar\String_($this->connect, $propertyProperty->default->getAttributes());
                break;
            case $node instanceof Node\Stmt\Property && $node->props[0]->name->name === 'fillable':
                $newNode = new Property('fillable');
                $newNode->setDefault($this->fillable);
                $newNode->makeProtected();
                $newNode->setDocComment(new Doc('/** @var array $fillable */'));
                $newNode = $newNode->getNode();
                $usbNode = $newNode->props[0]->default;
                if ($usbNode instanceof Node\Expr\Array_) {
                    $usbNode->setAttribute('kind', Node\Expr\Array_::KIND_SHORT);
                }
                return $newNode;
            case $node instanceof Node\Expr\Array_:
                break;
            case $node instanceof Node\Stmt\PropertyProperty && $node->name->name === 'table':
                $node->default = new Node\Scalar\String_($this->table, $node->default->getAttributes());
                break;
            case $node instanceof Node\Stmt\Namespace_:
                $useCarbon = false;
                foreach ($node->stmts as $subNode) {
                    if ($subNode instanceof Node\Stmt\Use_ && $subNode->uses[0]->name->toCodeString() === 'Carbon\Carbon') {
                        $useCarbon = true;
                    }
                }
                if (! $useCarbon) {
                    $carbonStmt = (new Use_(Carbon::class, Node\Stmt\Use_::TYPE_NORMAL))->getNode();
                    array_unshift($node->stmts, $carbonStmt);
                    unset($carbonStmt);
                }
                break;
        }
        return null;
    }

    /**
     * @param Node $node
     */
    private function replaceComment(Node $node): void
    {
        array_unshift($this->newCommentText, sprintf("* Class %s\n", $node->name->name));
        $docComment = $node->getComments()[0] ?? null;
        $cusComment = [];
        if ($docComment) {
            $oldCommentTxt = $docComment->getReformattedText();
            $oldCommentTxtArr = explode("\n", $oldCommentTxt);
            $startCus = false;
            foreach ($oldCommentTxtArr as $line) {
                $line = trim($line);
                if ($line == '/**' || $line == '/*' || strpos($line, '*/') !== false) {
                    continue;
                }
                if ($line === '' || $line === '*') {
                    $startCus = true;
                    continue;
                }
                if ($startCus) {
                    $cusComment[] = $line . "\n";
                }
            }
        }
        if ($cusComment) {
            $this->newCommentText[] = "*\n";
            foreach ($cusComment as $cusLine) {
                $this->newCommentText[] = $cusLine;
            }
        }
        foreach ($this->newCommentText as $key => $value) {
            $this->newCommentText[$key] = ' ' . $value;
        }
        $newCommentTxt = sprintf("/**\n%s */", implode('', $this->newCommentText));
        $node->setDocComment(new Doc($newCommentTxt));
    }

    private function init(): void
    {
        /** @var Field $field */
        foreach ($this->fields as $field) {
            if ($field->isPrimaryKey()) {
                $this->primaryKeyField = $field;
            }
            $this->newCommentText[] = $field->toPropertyString();
            if (in_array($field->name(), ['created_at', 'updated_at'])) {
                continue;
            }
            $this->fillable[] = $field->name();
        }
    }
}
