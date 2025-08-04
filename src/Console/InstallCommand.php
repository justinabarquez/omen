<?php

namespace Omen\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'omen:install';
    protected $description = 'Install Omen AI agent framework';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $this->info('Installing Omen AI Agent Framework...');
        $this->newLine();

        $this->createDirectories();
        $this->createBootstrapFile();
        $this->createToolsDirectory();
        $this->createReadFileToolStub();

        $this->info('✅ Omen installed successfully!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('1. Add your ANTHROPIC_API_KEY to your .env file');
        $this->line('2. Run: php artisan omen:chat');
        $this->line('3. Create custom tools in agent/Tools/');
    }

    protected function createDirectories()
    {
        $agentPath = base_path('agent');
        $toolsPath = base_path('agent/Tools');

        if (!$this->files->isDirectory($agentPath)) {
            $this->files->makeDirectory($agentPath, 0755, true);
            $this->line('✅ Created agent/ directory');
        }

        if (!$this->files->isDirectory($toolsPath)) {
            $this->files->makeDirectory($toolsPath, 0755, true);
            $this->line('✅ Created agent/Tools/ directory');
        }
    }

    protected function createBootstrapFile()
    {
        $bootstrapPath = base_path('bootstrap/agent.php');

        if ($this->files->exists($bootstrapPath)) {
            if (!$this->confirm('bootstrap/agent.php already exists. Overwrite?')) {
                return;
            }
        }

        $stub = $this->getBootstrapStub();
        $this->files->put($bootstrapPath, $stub);
        $this->line('✅ Created bootstrap/agent.php');
    }

    protected function createToolsDirectory()
    {
        // Already created in createDirectories()
    }

    protected function createReadFileToolStub()
    {
        $toolPath = base_path('agent/Tools/ReadFile.php');

        if ($this->files->exists($toolPath)) {
            if (!$this->confirm('agent/Tools/ReadFile.php already exists. Overwrite?')) {
                return;
            }
        }

        $stub = $this->getReadFileToolStub();
        $this->files->put($toolPath, $stub);
        $this->line('✅ Created agent/Tools/ReadFile.php');
    }

    protected function getBootstrapStub(): string
    {
        return <<<'PHP'
<?php

use Omen\Agent;
use Omen\Model;
use Agent\Tools\ReadFile;

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

namespace Agent\Tools;

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
}