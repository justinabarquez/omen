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

2. **Set up your agent:**
   ```bash
   php artisan agent:install
   ```

3. **Add your API key to `.env`:**
   ```env
   ANTHROPIC_API_KEY=your_api_key_here
   ```

4. **Start chatting:**
   ```bash
   php artisan agent
   ```

## Usage

### Creating Custom Tools

Tools use PHP attributes for clean, minimal configuration:

```php
<?php

namespace App\Agent\Tools;

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

The `bootstrap/agent.php` file is automatically created with `agent:install`:

```php
<?php

use Omen\Agent;
use Omen\Model;
use App\Agent\Tools\ReadFile;

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
$agent = require 'bootstrap/agent.php';

$agent->addUserMessage('What time is it?');
$response = $agent->send();

echo $response->content;
```

## Commands

- `php artisan agent:install` - Set up the agent structure
- `php artisan agent` - Start interactive chat with your AI agent

## Features

- Clean attribute-based tool definitions
- Automatic parameter extraction via reflection
- Type-safe tool execution
- Support for Anthropic Claude models
- Fluent configuration API
- Conversation history management

## License

MIT