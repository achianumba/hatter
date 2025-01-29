<?php

namespace LinkORB\Component\Hatter\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use LinkORB\Component\Hatter\Factory\HatterFactory;
use Symfony\Component\Yaml\Yaml;
use Connector\Connector;
use Symfony\Component\Dotenv\Dotenv;

class HatterLoadCommand extends Command
{
    protected static $defaultName = 'hatter:load';
    private string $dsn;

    // get environment variable `dsn` during construction
    public function __construct()
    {
        $projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
        $envFile = $projectRoot . '/.env.local';

        if (!file_exists($envFile)) {
            fwrite(STDERR, PHP_EOL . 'Missing file: ' . $envFile . ' does not exist.' . PHP_EOL . PHP_EOL);
            exit(1);
        }

        $dotenv = new Dotenv();
        $dotenv->load($envFile);

        if (!array_key_exists('HATTER_DSN', $_ENV)) {
            fwrite(STDERR, PHP_EOL . 'Error: Missing environment variable: Make sure to set the "HATTER_DSN" environment variable to your database connection string in the .env.local file.' . PHP_EOL . PHP_EOL);
            exit(1);
        }

        $this->dsn = $_ENV['HATTER_DSN'];
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reads and outputs a YAML file')
            ->addArgument('filenames', InputArgument::IS_ARRAY, 'The YAML file(s) to load');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filenames = $input->getArgument('filenames');

        $hatter = HatterFactory::fromFilenames($filenames);

        $connector = new Connector();
        $config = $connector->getConfig($this->dsn);
        if (!$connector->exists($config)) {
            throw new \InvalidArgumentException('Database does not exist: ' . $config['dbname']);
        }
        $pdo = $connector->getPdo($config);

        $hatter->write($pdo);
        $config = $hatter->serialize();
        $output->write(Yaml::dump($config, 10, 2));
        return Command::SUCCESS;
    }
}
