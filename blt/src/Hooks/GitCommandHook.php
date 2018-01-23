<?php

namespace Acquia\Blt\Custom\Hooks;

use Acquia\Blt\Robo\BltTasks;

/**
 * Class GitCommandHook implements custom specific git command overrides.
 *
 * @package Acquia\Blt\Custom\Hooks
 */
class GitCommandHook extends BltTasks {

  /**
   * Override git commit hook.
   *
   * @param string $message
   *   Git message string.
   *
   * @hook replace-command git:commit-msg
   *
   * @return int
   *   status 0 means success
   */
  public function commitMsgHook($message) {
    $pattern = $this->getConfigValue('git.validation.commit-msg.pattern',
      '/\#\d+/');
    $example = $this->getConfigValue('git.validation.commit-msg.example',
      "Message pattern $pattern doesn't match");
    $this->say('Validating commit message syntax...');
    if (!preg_match($pattern, $message)) {
      $this->logger->error("Invalid commit message!");
      $this->say($example);
      return 1;
    }

    return 0;
  }

  /**
   * Validates staged files.
   *
   * @param string $changed_files
   *   A list of staged files, separated by \n.
   *
   * @hook replace-command git:pre-commit
   */
  public function preCommitHook($changed_files) {
    $files = explode("\n", $changed_files);
    $php_files = implode("\n", preg_grep('/\.php$/i', $files));

    $this->invokeCommands([
      // Passing a file list to be PHPCS will cause all specified files to
      // be sniffed, regardless of the extensions or patterns defined in
      // phpcs.xml. So, we do not use validate:phpcs:files.
      'validate:phpcs:files' => ['file_list' => $php_files],
      'validate:twig:files' => ['file_list' => $changed_files],
      'validate:yaml:files' => ['file_list' => $changed_files],
    ]);

    $changed_files_list = explode("\n", $changed_files);
    if (in_array(['composer.json', 'composer.lock'], $changed_files_list)) {
      $this->invokeCommand('validate:composer', ['file_list' => $changed_files]);
    }

    $this->invokeHook('pre-commit');
    $this->say("<info>Your local code has passed git pre-commit validation.</info>");
  }

  /**
   * Executes PHP Code Sniffer against a list of files, if in phpcs.filesets.
   *
   * Changed to scan only the files, not directory as this blt command.
   * \Acquia\Blt\Robo\Commands\Validate\PhpcsCommand::sniffFileList()
   *
   * @param string $file_list
   *   A list of files to scan, separated by \n.
   *
   * @return int
   *   exit code.
   *
   * @hook replace-command validate:phpcs:files
   */
  public function sniffFileList($file_list) {
    $this->say("Sniffing list of files...");
    $files = explode("\n", $file_list);
    $files = array_filter($files);
    $exit_code = $this->doSniffFileList($files);

    if ($exit_code) {
      $this->say('You can use @codingStandardsIgnoreFile at the beginning
        of file to skip code sniffing');
    }

    return $exit_code;
  }

  /**
   * Executes PHP Code Sniffer against an array of files.
   *
   * \Acquia\Blt\Robo\Commands\Validate\PhpcsCommand::doSniffFileList()
   *
   * @param array $files
   *   A flat array of absolute file paths.
   *
   * @return int
   *   exit code.
   */
  protected function doSniffFileList(array $files) {
    if ($files) {
      $temp_path = $this->getConfigValue('repo.root') . '/tmp/phpcs-fileset';
      $this->taskWriteToFile($temp_path)
        ->lines($files)
        ->run();

      $bin = $this->getConfigValue('composer.bin') . '/phpcs';
      $result = $this->taskExecStack()
        ->exec("'$bin' --file-list='$temp_path' -l")
        ->printMetadata(FALSE)
        ->run();

      unlink($temp_path);

      return $result->getExitCode();
    }

    return 0;
  }

}
