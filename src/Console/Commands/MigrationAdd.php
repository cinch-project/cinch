<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Migration\AddMigration;
use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\StorePath;
use Cinch\Common\MigratePolicy;
use Cinch\Project\ProjectRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:add', 'Adds a migration')]
class MigrationAdd extends AbstractCommand
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->handle(new AddMigration(
            $this->projectRepository->get($this->projectId)->getMigrationStoreDsn(),
            new StorePath($input->getArgument('path')),
            MigratePolicy::from($input->getOption('migrate-policy')),
            new Author($input->getOption('author') ?: get_system_user()),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC')),
            new Description($input->getArgument('description'))
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        $defaultPolicy = MigratePolicy::ONCE->value;
        $policies = "'" . implode("', '", array_map(fn($v) => $v->value, MigratePolicy::cases())) . "'";

        // cinch add <project> <path> <description> --author= --migrate-policy=
        $this
            ->addProjectArgument()
            ->addArgument('path', InputArgument::REQUIRED, 'Migration store path (relative to migration store root)')
            ->addArgument('description', InputArgument::REQUIRED, 'Migration description')
            ->addOption('migrate-policy', 'm', InputOption::VALUE_REQUIRED, "Migrate policy: $policies", $defaultPolicy)
            ->addOption('author', 'a', InputOption::VALUE_REQUIRED, 'Migration author [default: current system user]')
            ->addOptionByName('env');
    }
}