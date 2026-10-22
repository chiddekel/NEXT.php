<?php

declare(strict_types=1);

namespace Next\Cli;

class Application
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        private readonly string $projectDir = '.',
        $stdout = null,
        $stderr = null
    ) {
        $this->stdout = $stdout ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
        $this->stderr = $stderr ?? (defined('STDERR') ? STDERR : fopen('php://stderr', 'w'));

        $this->addCommand(new DevCommand($this->projectDir));
        $this->addCommand(new BuildCommand($this->projectDir));
        $this->addCommand(new StartCommand($this->projectDir));
    }

    public function addCommand(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? 'dev';

        if ($commandName === '--help' || $commandName === '-h' || $commandName === 'help') {
            $this->printHelp();
            return 0;
        }

        if ($commandName === '--version' || $commandName === '-v') {
            fwrite($this->stdout, Banner::icon() . " NEXT.php v1.0.0\n");
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            fwrite($this->stderr, "Unknown command: {$commandName}\n\n");
            $this->printHelp();
            return 1;
        }

        $command = $this->commands[$commandName];
        $args = array_slice($argv, 2);

        return $command->execute($args);
    }

    private function printHelp(): void
    {
        $banner = Banner::render();
        $help = <<<TXT
{$banner}
Usage
  $ next <command> [options]

Available Commands
  dev     Start NEXT.php in development mode (default)
  build   Create an optimized production build
  start   Start NEXT.php in production mode

Options
  --help, -h       Displays this help message
  --version, -v    Displays version

For more information, see https://github.com/chiddekel/NEXT.php

TXT;
        fwrite($this->stdout, $help);
    }
}
