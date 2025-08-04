<?php

namespace Omen\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Omen\Agent;
use Omen\MethodTool;

class ChatCommand extends Command
{
    protected $signature = 'omen';
    protected $description = 'Start a chat session with your AI agent';

    protected Agent $agent;
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        // Check if installation is needed
        if ($this->needsInstallation()) {
            if (!$this->performInstallation()) {
                return 1;
            }
        }

        // Check API key
        if (!$this->hasApiKey()) {
            if (!$this->promptForApiKey()) {
                return 1;
            }
        }

        $this->info('🔮 Omen AI Agent');
        $this->info('Type "exit" to quit, "clear" to reset conversation');
        $this->info('────────────────────────────────────────────────');
        $this->newLine();

        // Load agent configuration
        $this->agent = require base_path('bootstrap/omen.php');
        
        // Load tools dynamically
        $this->loadTools();

        $this->chatLoop();
        return 0;
    }

    protected function needsInstallation(): bool
    {
        return !file_exists(base_path('bootstrap/omen.php')) || 
               !is_dir(base_path('app/Omen/Tools'));
    }

    protected function performInstallation(): bool
    {
        $this->newLine();
        $this->info('🔧 Agent Setup Required');
        $this->comment('It looks like this is your first time using the agent.');
        $this->newLine();

        if (!$this->confirm('Would you like to set up the agent in your application?', true)) {
            $this->comment('Setup cancelled. You can run this command again anytime.');
            return false;
        }

        $this->newLine();
        $this->info('Setting up Omen AI Agent...');
        $this->newLine();

        $this->createDirectories();
        $this->createBootstrapFile();
        $this->createReadFileToolStub();

        $this->info('✅ Agent setup completed successfully!');
        $this->newLine();

        return true;
    }

    protected function hasApiKey(): bool
    {
        // Refresh the environment after potential .env changes
        if (function_exists('app')) {
            app('config')->offsetUnset('app.env');
        }
        
        return !empty(env('ANTHROPIC_API_KEY'));
    }

    protected function promptForApiKey(): bool
    {
        $this->newLine();
        $this->info('🔑 API Key Required');
        $this->comment('You need an Anthropic API key to use the agent.');
        $this->newLine();

        $apiKey = $this->ask('Please enter your Anthropic API key');

        if (empty($apiKey)) {
            $this->error('API key is required to continue.');
            return false;
        }

        if (!$this->addApiKeyToEnv($apiKey)) {
            $this->error('Failed to save API key to .env file.');
            return false;
        }

        // Reload environment variables
        $this->reloadEnvironment();

        $this->info('✅ API key saved successfully!');
        $this->newLine();

        return true;
    }

    protected function addApiKeyToEnv(string $apiKey): bool
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            $this->warn('.env file not found. Creating one...');
            $this->files->put($envPath, '');
        }

        $envContent = $this->files->get($envPath);
        
        // Check if ANTHROPIC_API_KEY already exists
        if (str_contains($envContent, 'ANTHROPIC_API_KEY=')) {
            // Replace existing key
            $envContent = preg_replace(
                '/^ANTHROPIC_API_KEY=.*$/m',
                "ANTHROPIC_API_KEY={$apiKey}",
                $envContent
            );
        } else {
            // Add new key
            $envContent .= "\nANTHROPIC_API_KEY={$apiKey}\n";
        }

        return $this->files->put($envPath, $envContent) !== false;
    }

    protected function reloadEnvironment(): void
    {
        // Clear Laravel's cached environment
        if (function_exists('app')) {
            app()->bootstrapWith([
                \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class
            ]);
        }
    }

    protected function createDirectories(): void
    {
        $omenPath = base_path('app/Omen');
        $toolsPath = base_path('app/Omen/Tools');

        if (!$this->files->isDirectory($omenPath)) {
            $this->files->makeDirectory($omenPath, 0755, true);
            $this->line('✅ Created app/Omen/ directory');
        }

        if (!$this->files->isDirectory($toolsPath)) {
            $this->files->makeDirectory($toolsPath, 0755, true);
            $this->line('✅ Created app/Omen/Tools/ directory');
        }
    }

    protected function createBootstrapFile(): void
    {
        $bootstrapPath = base_path('bootstrap/omen.php');

        if ($this->files->exists($bootstrapPath)) {
            if (!$this->confirm('bootstrap/omen.php already exists. Overwrite?', false)) {
                return;
            }
        }

        $stub = $this->getBootstrapStub();
        $this->files->put($bootstrapPath, $stub);
        $this->line('✅ Created bootstrap/omen.php');
    }

    protected function createReadFileToolStub(): void
    {
        $toolPath = base_path('app/Omen/Tools/ReadFile.php');

        if ($this->files->exists($toolPath)) {
            if (!$this->confirm('app/Omen/Tools/ReadFile.php already exists. Overwrite?', false)) {
                return;
            }
        }

        $stub = $this->getReadFileToolStub();
        $this->files->put($toolPath, $stub);
        $this->line('✅ Created app/Omen/Tools/ReadFile.php');
    }

    protected function getBootstrapStub(): string
    {
        return <<<'PHP'
<?php

use Omen\Agent;
use Omen\Model;
use App\Omen\Tools\ReadFile;

return Agent::configure()
    ->withModel(Model::Anthropic, 'claude-3-5-sonnet-20241022')
    ->withSystemMessage('You are a helpful assistant that can read files and answer questions.')
    ->withTools([
        new ReadFile,
    ])
    ->create();
PHP;
    }

    protected function getReadFileToolStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Omen\Tools;

use Omen\Tool;
use Omen\Attributes\Description;

#[Description('Read the contents of a file at the given path')]
class ReadFile extends Tool
{
    public function handle(
        #[Description('The path to the file to read (e.g., "composer.json", "README.md")')] string $path
    ) {
        $fullPath = base_path($path);
        
        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$path}");
        }
        
        if (!is_readable($fullPath)) {
            throw new \Exception("File is not readable: {$path}");
        }
        
        return file_get_contents($fullPath);
    }
}
PHP;
    }

    protected function loadTools(): void
    {
        $toolsPath = base_path('app/Omen/Tools');
        if (!is_dir($toolsPath)) {
            return;
        }

        $tools = [];
        $files = glob($toolsPath . '/*.php');

        foreach ($files as $file) {
            $className = 'App\\Omen\\Tools\\' . basename($file, '.php');

            if (class_exists($className)) {
                try {
                    $toolInstance = new $className();
                    if ($toolInstance instanceof \Omen\Tool) {
                        // Check if this tool uses the single handle() method pattern
                        if (method_exists($toolInstance, 'handle')) {
                            $tools[] = $toolInstance;
                        } else {
                            // Multi-method pattern - create separate tools for each method
                            $reflection = new \ReflectionClass($toolInstance);
                            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                // Skip constructor and inherited methods
                                if ($method->getName() === '__construct' || 
                                    $method->getDeclaringClass()->getName() !== $className) {
                                    continue;
                                }

                                // Check if method has Description attribute
                                $attributes = $method->getAttributes(\Omen\Attributes\Description::class);
                                if (!empty($attributes)) {
                                    $tools[] = new MethodTool($toolInstance, $method);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("Could not load tool {$className}: " . $e->getMessage());
                }
            }
        }

        if (!empty($tools)) {
            $this->agent->withTools($tools);
            $toolNames = array_map(fn($tool) => $tool->getName(), $tools);
            $this->comment('Loaded tools: ' . implode(', ', $toolNames));
            $this->newLine();
        }
    }

    protected function chatLoop(): void
    {
        $prompt = $this->ask('<fg=green>You</fg=green>');

        if (strtolower($prompt) === 'exit') {
            $this->info('👋 Goodbye!');
            return;
        }

        if (strtolower($prompt) === 'clear') {
            $this->agent->clearMessages();
            $this->comment('🗑️  Conversation cleared');
            $this->newLine();
            $this->chatLoop();
            return;
        }

        $this->processMessage($prompt);
        $this->chatLoop();
    }

    protected function processMessage(string $prompt): void
    {
        try {
            $this->agent->addUserMessage($prompt);

            $this->newLine();
            $this->line('<fg=cyan>Assistant:</fg=cyan>');

            $response = $this->agent->send();

            if ($response->content) {
                $this->line($response->content);
            }

            if ($response->hasToolCalls()) {
                $this->newLine();
                $this->comment('🔧 Using tools...');

                foreach ($response->toolResults as $index => $result) {
                    $toolCall = $response->toolCalls[$index];
                    $this->line("<fg=yellow>Tool:</fg=yellow> {$toolCall['name']}");

                    if ($result['is_error']) {
                        $this->error("❌ Error: {$result['content']}");
                    } else {
                        $this->comment('✅ Result: ' . $this->truncateOutput($result['content']));
                    }
                }

                $this->newLine();
                $this->comment('⏳ Continuing with tool results...');

                $continuedResponse = $this->agent->continueWithToolResults();

                if ($continuedResponse->content) {
                    $this->newLine();
                    $this->line('<fg=cyan>Assistant (continued):</fg=cyan>');
                    $this->line($continuedResponse->content);
                }
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'API_KEY')) {
                $this->warn('💡 Make sure you have set ANTHROPIC_API_KEY in your .env file');
            }

            $this->newLine();
        }
    }

    protected function truncateOutput(string $output, int $maxLength = 500): string
    {
        if (strlen($output) <= $maxLength) {
            return $output;
        }

        return substr($output, 0, $maxLength) . '... (truncated)';
    }
}