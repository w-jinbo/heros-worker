<?php
declare(strict_types=1);
/**
 * This file is part of Heros-Worker.
 * @contact  chenzf@pvc123.com
 */
namespace Framework\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Message
{
    /**
     * @param string $value
     */
    public function __construct(public string $value = '')
    {
    }
}
