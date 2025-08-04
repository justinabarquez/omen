<?php

namespace Omen\Console;

use Illuminate\Console\Command;
use Omen\Agent;

class ChatCommand extends Command
{
    protected $signature = 'omen';
    protected $description = 'Start a chat session with your AI agent';

    protected Agent $agent;

    public function handle()
    {
        $this->info('🔮 Omen AI Agent');
        $this->info('Type "exit" to quit, "clear" to reset conversation');
        $this->info('────────────────────────────────────────────────');
        $this->newLine();

        // Load agent configuration
        $configFile = base_path('bootstrap/agent.php');
        if (!file_exists($configFile)) {
            $this->error('❌ No agent configuration found.');
            $this->comment('Run "php artisan omen:install" first to set up your agent.');
            return 1;
        }

        $this->agent = require $configFile;
        
        // Load tools dynamically
        $this->loadTools();

        $this->chatLoop();
        return 0;
    }

    protected function loadTools(): void
    {
        $toolsPath = base_path('agent/Tools');
        if (!is_dir($toolsPath)) {
            return;
        }

        $tools = [];
        $files = glob($toolsPath . '/*.php');

        foreach ($files as $file) {
            $className = 'Agent\\Tools\\' . basename($file, '.php');

            if (class_exists($className)) {
                try {
                    $tool = new $className();
                    if ($tool instanceof \Omen\Tool) {
                        $tools[] = $tool;
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