<?php

namespace Cinch\Console\Command;

use Cinch\Command\RollbackBy;
use Cinch\Console\Command;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rollback:date', 'Roll back to a specific date')]
class RollbackDate extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dateTime = $this->parseDateTime($input->getArgument('date'));
        $title = 'rolling back to ' . $dateTime->format(DateTimeInterface::RFC3339);
        $rollbackBy = RollbackBy::date($dateTime->setTimezone(new DateTimeZone('UTC')));
        $this->executeRollback($input, $rollbackBy, $title);
        return self::SUCCESS;
    }

    protected function configure()
    {
        $tz = system_time_zone();
        $this
            ->addProjectArgument()
            ->addArgument('date', InputArgument::REQUIRED, "Roll back to this date")
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('dry-run')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
All changes since (greater than) <info><date></info>, will be rolled back. <info><date></info> can be in the 
below formats:

     date 2022-09-28 - default time is 00:00:00     
     time 09:32:10 - default date is today (seconds can be omitted 09:32 -> 09:32:00)
 datetime 2022-09-28T09:32:10 - the 'T' separator is required
time zone 09:32:10-0400, 09:32:10+04:00, etc. - defaults to system (currently '$tz')

<code-comment># roll back all changes since 7am today system time</>
<code>cinch rollback:date project 07:00 --tag=clear-the-day</>
HELP
            );
    }
}