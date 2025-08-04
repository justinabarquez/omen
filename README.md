# Omen

An AI agent framework for Laravel applications.

## Installation

```bash
composer require omenphp/omen
```

## Usage

### Configuration

Create a `bootstrap/agent.php` file in your Laravel project:

```php
<?php

use Omen\Agent;
use Omen\Model;
use YourApp\Tools\YourTool;

return Agent::configure()
    ->withModel(Model::Anthropic, 'claude-3-5-sonnet-20241022')
    ->withSystemMessage('You are a helpful assistant.')
    ->withTools([
        new YourTool,
    ])
    ->create();
```

### Creating Tools

Tools use PHP attributes for clean, minimal configuration:

```php
<?php

namespace YourApp\Tools;

use Omen\Tool;
use Omen\Attributes\Description;

#[Description('Read the contents of a file')]
class ReadFile extends Tool
{
    public function handle(
        #[Description('Path to the file')] string $path
    ) {
        return file_get_contents($path);
    }
}
```

### Using the Agent

```php
$agent = require 'bootstrap/agent.php';

$agent->addUserMessage('What time is it?');
$response = $agent->send();

echo $response->content;
```

## Features

- Clean attribute-based tool definitions
- Automatic parameter extraction via reflection
- Type-safe tool execution
- Support for Anthropic Claude models
- Fluent configuration API
- Conversation history management

## License

MIT