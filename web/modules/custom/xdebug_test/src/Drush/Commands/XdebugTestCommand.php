<?php

namespace Drupal\xdebug_test\Drush\Commands;

use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the Xdebug test Drush command.
 */
#[AsCommand(
  name: 'xdebug-test',
  description: 'Test Xdebug debugging through Drush.',
)]
class XdebugTestCommand extends Command {

  use AutowireTrait;

  /**
   * Executes the Xdebug test command.
   */
  protected function execute(
    InputInterface $input,
    OutputInterface $output,
  ): int {
    $message = 'Xdebug + Drush works!';

    $output->writeln($message);

    return Command::SUCCESS;
  }

}
