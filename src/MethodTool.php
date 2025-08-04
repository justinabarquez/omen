<?php

namespace Omen;

use Omen\Attributes\Description;
use ReflectionMethod;
use ReflectionNamedType;

class MethodTool extends Tool
{
    protected object $instance;
    protected ReflectionMethod $method;
    protected string $toolName;

    public function __construct(object $instance, ReflectionMethod $method)
    {
        $this->instance = $instance;
        $this->method = $method;
        
        // Generate tool name: ClassName_methodName -> class_name_method_name
        $className = (new \ReflectionClass($instance))->getShortName();
        $this->toolName = $this->camelToSnake($className) . '_' . $this->camelToSnake($method->getName());
    }

    public function getName(): string
    {
        return $this->toolName;
    }

    public function getDescription(): string
    {
        $attributes = $this->method->getAttributes(Description::class);
        if (!empty($attributes)) {
            return $attributes[0]->newInstance()->value;
        }
        return 'No description provided';
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
        
        $args = [];
        
        foreach ($this->method->getParameters() as $param) {
            $paramName = $param->getName();
            
            if (array_key_exists($paramName, $input)) {
                $args[] = $input[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Required parameter '{$paramName}' is missing");
            }
        }
        
        return $this->method->invokeArgs($this->instance, $args);
    }

    protected function extractParameters(): void
    {
        $this->parameters = [];
        
        foreach ($this->method->getParameters() as $param) {
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