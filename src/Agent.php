<?php

namespace Omen;

use Illuminate\Support\Facades\Http;

class Agent
{
    protected array $messages = [];
    protected array $tools = [];
    protected string $model = 'claude-3-5-sonnet-20241022';
    protected string $provider = 'anthropic';
    protected ?string $apiKey;
    protected int $maxTokens = 4096;
    protected ?string $systemMessage = null;

    public function __construct()
    {
        $this->apiKey = env('ANTHROPIC_API_KEY');
    }

    public static function configure(): self
    {
        return new static();
    }

    public function withModel(string $provider, string $model): self
    {
        $this->provider = strtolower($provider);
        $this->model = $model;
        return $this;
    }

    public function withSystemMessage(string $message): self
    {
        $this->systemMessage = $message;
        return $this;
    }

    public function create(): self
    {
        // System message is stored separately and sent as a top-level parameter
        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function setMaxTokens(int $tokens): self
    {
        $this->maxTokens = $tokens;
        return $this;
    }

    public function withTools(array $tools): self
    {
        $this->tools = $tools;
        return $this;
    }

    public function addMessage(string $role, $content): self
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $content,
        ];
        return $this;
    }

    public function addSystemMessage(string $content): self
    {
        return $this->addMessage('system', $content);
    }

    public function addUserMessage(string $content): self
    {
        return $this->addMessage('user', $content);
    }

    public function addAssistantMessage($content): self
    {
        return $this->addMessage('assistant', $content);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clearMessages(): self
    {
        $this->messages = [];
        return $this;
    }

    public function send(): AgentResponse
    {
        $payload = $this->buildPayload();
        
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \Exception('API request failed: ' . $response->body());
        }

        $data = $response->json();
        return $this->parseResponse($data);
    }

    protected function buildPayload(): array
    {
        // Filter out system messages from the messages array
        $messages = array_filter($this->messages, function($msg) {
            return $msg['role'] !== 'system';
        });
        
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => array_values($messages), // Re-index array
        ];

        // Add system message as a top-level parameter
        if ($this->systemMessage) {
            $payload['system'] = $this->systemMessage;
        }

        if (!empty($this->tools)) {
            $payload['tools'] = $this->formatToolsForApi();
        }

        return $payload;
    }

    protected function formatToolsForApi(): array
    {
        $formattedTools = [];
        
        foreach ($this->tools as $tool) {
            $formattedTools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => $tool->getInputSchema(),
            ];
        }
        
        return $formattedTools;
    }

    protected function parseResponse(array $data): AgentResponse
    {
        $response = new AgentResponse();
        $response->id = $data['id'] ?? null;
        $response->model = $data['model'] ?? null;
        $response->role = $data['role'] ?? null;
        $response->stopReason = $data['stop_reason'] ?? null;
        
        $fullContent = [];
        $toolCalls = [];
        $assistantContent = [];
        
        foreach ($data['content'] ?? [] as $content) {
            if ($content['type'] === 'text') {
                $fullContent[] = $content['text'];
                $assistantContent[] = $content;
            } elseif ($content['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $content['id'],
                    'name' => $content['name'],
                    'input' => $content['input'],
                ];
                $assistantContent[] = $content;
            }
        }
        
        $response->content = implode("\n", $fullContent);
        $response->toolCalls = $toolCalls;
        
        // Add the assistant's message with all content (text + tool uses)
        if (!empty($assistantContent)) {
            // Normalize tool_use inputs to ensure they're always objects, not null
            $normalizedContent = array_map(function($content) {
                if ($content['type'] === 'tool_use' && ($content['input'] === null || (is_array($content['input']) && empty($content['input'])))) {
                    $content['input'] = (object)[]; // Empty object that will serialize as {}
                }
                return $content;
            }, $assistantContent);
            
            $this->addAssistantMessage($normalizedContent);
        }
        
        // Execute tools and add results if there were tool calls
        if (!empty($toolCalls)) {
            $response->toolResults = $this->executeTools($toolCalls);
            $this->addToolResultsToMessages($toolCalls, $response->toolResults);
        }
        
        return $response;
    }


    protected function executeTools(array $toolCalls): array
    {
        $results = [];
        
        foreach ($toolCalls as $call) {
            $tool = $this->findTool($call['name']);
            if ($tool) {
                try {
                    $result = $tool->execute($call['input']);
                    $results[] = [
                        'tool_use_id' => $call['id'],
                        'content' => $result,
                        'is_error' => false,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'tool_use_id' => $call['id'],
                        'content' => 'Error: ' . $e->getMessage(),
                        'is_error' => true,
                    ];
                }
            }
        }
        
        return $results;
    }

    protected function addToolResultsToMessages(array $toolCalls, array $toolResults): void
    {
        $content = [];
        foreach ($toolResults as $result) {
            // Ensure content is always a string for the API
            $resultContent = $result['content'];
            if (is_array($resultContent) || is_object($resultContent)) {
                $resultContent = json_encode($resultContent);
            }
            
            $content[] = [
                'type' => 'tool_result',
                'tool_use_id' => $result['tool_use_id'],
                'content' => (string) $resultContent,
                'is_error' => $result['is_error'] ?? false,
            ];
        }
        
        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];
    }

    protected function findTool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        return null;
    }

    public function continueWithToolResults(): AgentResponse
    {
        return $this->send();
    }
}

class AgentResponse
{
    public ?string $id = null;
    public ?string $model = null;
    public ?string $role = null;
    public ?string $stopReason = null;
    public string $content = '';
    public array $toolCalls = [];
    public array $toolResults = [];
    
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }
    
    public function getText(): string
    {
        return $this->content;
    }
}