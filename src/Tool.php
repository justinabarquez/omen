<?php

namespace Omen;

use Omen\Attributes\Description;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

abstract class Tool
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?array $parameters = null;

    public function getName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }
        
        $className = (new ReflectionClass($this))->getShortName();
        $this->name = $this->camelToSnake($className);
        return $this->name;
    }

    public function getDescription(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }
        
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(Description::class);
        
        if (!empty($attributes)) {
            $this->description = $attributes[0]->newInstance()->value;
        } else {
            $this->description = 'No description provided';
        }
        
        return $this->description;
    }

    public function getInputSchema(): array
    {
        if ($this->parameters === null) {
            $this->extractParameters();
        }
        
        $properties = [];
        $required = [];
        
        foreach ($this->parameters as $name => $param) {
            $properties[$name] = [
                'type' => $param['type'],
                'description' => $param['description'],
            ];
            
            if ($param['required']) {
                $required[] = $name;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    public function execute(array $input)
    {
        if ($this->parameters === null) {
            $this->extractParameters();
        }
        
        $method = new ReflectionMethod($this, 'handle');
        $args = [];
        
        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            
            if (array_key_exists($paramName, $input)) {
                $args[] = $input[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Required parameter '{$paramName}' is missing");
            }
        }
        
        return $this->handle(...$args);
    }

    protected function extractParameters(): void
    {
        $this->parameters = [];
        $method = new ReflectionMethod($this, 'handle');
        
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $jsonType = 'string';
            
            if ($type instanceof ReflectionNamedType) {
                $jsonType = $this->phpTypeToJsonType($type->getName());
            }
            
            $description = 'No description provided';
            $attributes = $param->getAttributes(Description::class);
            if (!empty($attributes)) {
                $description = $attributes[0]->newInstance()->value;
            }
            
            $this->parameters[$param->getName()] = [
                'type' => $jsonType,
                'description' => $description,
                'required' => !$param->isDefaultValueAvailable() && !($type && $type->allowsNull()),
            ];
        }
    }

    protected function phpTypeToJsonType(string $phpType): string
    {
        return match($phpType) {
            'int', 'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            'object', 'stdClass' => 'object',
            default => 'string',
        };
    }

    protected function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}