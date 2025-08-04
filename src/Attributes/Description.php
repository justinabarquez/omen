<?php

namespace Omen\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PARAMETER)]
class Description
{
    public function __construct(
        public string $value
    ) {}
}