<?php

namespace Acquia\Blt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Custom setup function for Custom setup function.
 */
class SetupCustomCommand extends BltTasks {

  /**
   * Install dependencies, builds docroot, installs Drupal.
   *
   * @see \Acquia\Blt\Robo\Commands\Setup\AllCommand::setup()
   *
   * @command custom:setup
   */
  public function setup() {
    $this->say("Setting up local environment for site '{$this->getConfigValue('site')}' using drush alias @{$this->getConfigValue('drush.alias')}");

    $commands = [
      'setup:build',
      'setup:hash-salt',
    ];

    $strategy = $this->getConfigValue('setup.strategy');
    $this->say('Installing site using strategy ' . $strategy);

    switch ($strategy) {
      case 'install':
        $commands[] = 'setup:drupal:install';
        break;

      case 'sync':
        $commands[] = 'sync:db';
        break;

      case 'import':
        $commands[] = 'custom:import';
        $commands[] = 'setup:update';
        break;
    }

    $commands[] = 'setup:toggle-modules';
    $commands[] = 'install-alias';
    $commands[] = 'setup:config-import';

    $this->invokeCommands($commands);
  }

  /**
   * Refresh the project without db and files.
   *
   * @command custom:refresh
   */
  public function customRefreshNoDb() {
    $commands = [
      'setup:build',
      'setup:hash-salt',
      'setup:toggle-modules',
      'setup:config-import',
    ];

    $this->invokeCommands($commands);
  }

  /**
   * Refresh the project with db and files.
   *
   * @command custom:refresh-db
   */
  public function customRefreshDb() {
    $commands = [
      'setup:build',
      'setup:hash-salt',
      'sync:db',
      'setup:config-import',
      'setup:toggle-modules',
    ];

    $this->invokeCommands($commands);
  }

  /**
   * Refresh the project without db and files.
   *
   * @command deploy:refresh
   */
  public function deployRefresh() {
    $commands = [
      'setup:hash-salt',
      'setup:config-import',
      'setup:toggle-modules',
    ];

    $this->invokeCommands($commands);
  }

  /**
   * Imports a .sql file into the Drupal database.
   *
   * @command custom:import
   */
  public function import() {
    $task = $this->taskDrush()
      ->drush('sql-drop -y')
      ->drush('sql-cli < ' . $this->getConfigValue('setup.dump-file'));
    $result = $task->run();
    $exit_code = $result->getExitCode();

    if ($exit_code) {
      throw new BltException("Unable to import setup.dump-file.");
    }
  }

}
