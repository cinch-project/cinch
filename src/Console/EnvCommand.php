<?php

namespace Cinch\Console;

use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig;

#[AsCommand('env', 'Lists all environments')]
class EnvCommand extends AbstractCommand
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly Twig $twig)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('This does cool stuff')->addProjectArgument();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $map = $this->projectRepository->get($this->projectId)->getEnvironmentMap();

        echo $this->twig->render('env-list.twig', [
            'default' => $map->getDefaultName(),
            'environments' => $map->all()
        ]);

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }
}