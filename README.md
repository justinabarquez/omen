# Omen

An AI agent framework for Laravel applications.

## Installation

```bash
composer require omenphp/omen
```

## Quick Start

1. **Install the package:**
   ```bash
   composer require omenphp/omen
   ```

2. **Run the agent:**
   ```bash
   php artisan omen
   ```
   
   The first time you run this command, it will:
   - Prompt you to set up the agent structure
   - Ask for your Anthropic API key
   - Create the necessary files and directories
   - Start the chat session

## Usage

### Creating Custom Tools

Tools use PHP attributes for clean, minimal configuration:

```php
<?php

namespace App\Omen\Tools;

use Omen\Tool;
use Omen\Attributes\Description;

#[Description('Get the weather for a city')]
class GetWeather extends Tool
{
    public function handle(
        #[Description('The city name')] string $city
    ) {
        // Your weather API logic here
        return "The weather in {$city} is sunny.";
    }
}
```

### Configuration

The `bootstrap/omen.php` file is automatically created when you first run `php artisan omen`:

```php
<?php

use Omen\Agent;
use Omen\Model;
use App\Omen\Tools\ReadFile;

return Agent::configure()
    ->withModel(Model::Anthropic, 'claude-3-5-sonnet-20241022')
    ->withSystemMessage('You are a helpful assistant.')
    ->withTools([
        new ReadFile,
        // Add your custom tools here
    ])
    ->create();
```

### Programmatic Usage

```php
$agent = require 'bootstrap/omen.php';

$agent->addUserMessage('What time is it?');
$response = $agent->send();

echo $response->content;
```

## Commands

- `php artisan omen` - Start interactive chat with your AI agent (auto-installs on first run)

## Features

- Clean attribute-based tool definitions
- Automatic parameter extraction via reflection
- Type-safe tool execution
- Support for Anthropic Claude models
- Fluent configuration API
- Conversation history management

## License

MIT