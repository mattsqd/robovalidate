<?php

namespace RoboValidate\Robo\Plugin\Commands;

use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Yaml\Yaml;

/**
 * Run validation commands.
 *
 * @class RoboFile
 */
class ValidateCommands extends Tasks
{

    /**
     * Say a message but with a '---' wrapper around it.
     */
    protected function sayWithWrapper($message)
    {
        $this->say('');
        $this->say(str_repeat('-', strlen($message)));
        $this->say($message);
        $this->say(str_repeat('-', strlen($message)));
        $this->say('');
    }

    protected function printError($message)
    {
        $this->yell($message, 40, 'red');
    }

    /**
     * Is the current working directory a git repo?
     *
     * @return bool
     */
    protected function isGitRepo(): bool
    {
        return exec('git rev-parse --is-inside-work-tree 2>/dev/null') === 'true';
    }

    /**
     * Retrieve the name of the current Git branch.
     *
     * @return string|null
     *   Null if not on a branch.
     */
    protected function getGitBranch(): ?string
    {
        if (!$this->isGitRepo()) {
            $this->printError(
                'The current directory is not a git repo, cannot retrieve branch name'
            );

            return null;
        }
        $branch_name = exec('git branch --show-current');

        return $branch_name !== '' ? $branch_name : null;
    }

    /**
     * Run a robo command and return the result.
     *
     * @param string $command
     *   A command string to run in robo, except don't start with the robo path.
     *
     * @return \Robo\Result
     */
    protected function runRoboCommand(string $command): ResultData
    {
        global $argv;

        return $this->_exec($argv[0].' '.$command);
    }

    /**
     * Ensure that the $keys in $opts have a value.
     *
     * @param array|string $keys
     *   An array of keys in $opts or just a single string key.
     * @param array $opts
     *   The options and their values but numerically indexed.
     *
     * @return array|string
     *
     * @throws \Exception
     */
    protected function getOptions(
        array|string $keys,
        array $opts,
        bool $required = true,
    ): string|array {
        $was_string = is_string($keys);
        $keys = (array)$keys;
        $return = [];
        $errors = [];
        foreach ($keys as $key) {
            if ($required && !isset($opts[$key])) {
                $errors[] = sprintf('The option --%s is required.', $key);
            } elseif ($required &&
                (
                    is_scalar($opts[$key]) && 0 === strlen($opts[$key]) ||
                    !is_scalar($opts[$key]) && empty(array_filter($opts[$key]))
                )
            ) {
                $errors[] = sprintf('The option --%s must have a value.', $key);
            } else {
                $return[] = $opts[$key];
            }
        }
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->printError($error);
            }
            throw new \Exception('Invalid option(s) given.');
        }

        return $was_string ? $return[0] : $return;
    }

    /**
     * Run all validations.
     *
     * @command validate:all
     */
    public function validateAll(): ResultData
    {
        $table = new Table($this->output());
        $table
            ->setHeaders(
                [
                    'Test Name',
                    'Valid?',
                    'Command (use this to diagnose individual tests without running all)',
                ]
            );
        $output_data[] = [
            'Coding Standards',
            $this->runRoboCommand('validate:coding-standards')->wasSuccessful(
            ) ? 'Yes' : 'No',
            'validate:coding-standards',
        ];
        $output_data[] = [
            'Composer Lock File',
            $this->runRoboCommand('validate:composer-lock')->wasSuccessful(
            ) ? 'Yes' : 'No',
            'validate:composer-lock',
        ];
        $output_data[] = [
            'Commit Messages',
            $this->runRoboCommand('validate:commit-messages')->wasSuccessful(
            ) ? 'Yes' : 'No',
            "validate:commit-messages",
        ];
        $output_data[] = [
            'Branch Name',
            $this->runRoboCommand('validate:branch-name')->wasSuccessful(
            ) ? 'Yes' : 'No',
            "validate:branch-name",
        ];
        $table->setRows($output_data);
        $success = true;
        foreach ($output_data as $datum) {
            if ($success && trim($datum[1]) === 'No') {
                $success = false;
            }
        }
        $table->render();
        if ($success) {
            $this->sayWithWrapper('All tests are valid');
        } else {
            $this->printError('At least one test has failed');
        }

        return new ResultData();
    }

    /**
     * Ensure that coding standards pass.
     *
     * This is initially configured for Drupal which requires the
     * 'drupal/core-dev' composer package. However, it can be used with other
     * standards. See https://github.com/PHPCSStandards/composer-installer for
     * more information.
     *
     * @command validate:coding-standards
     *
     * @option standards If multiple standards are set, it will run both
     *   standards on all $paths.
     * @option paths By default, this will look in $composer_json_path for where
     *   your custom code is placed. If it does not find it there, it will
     *   default to {$web_root}/modules/custom, {$web_root}/modules/custom,
     *   {$web_root}/themes/custom. If any directory does not exist, it will not
     *   run. Note all that paths is in the form: ['path/on/disk' => [options]].
     *   If options is empty, it will use $similar_options.
     * @option similar-options These are the options passed to phpcs.
     * @option composer-json-path The path to composer.json on disk.
     *
     * @return \Robo\ResultData
     */
    public function validateCodingStandards(
        array $opts = [
            'paths' => [],
            'similar-options' => [
                'standard' => 'Drupal,DrupalPractice',
                'extensions' => 'php,module,inc,install,test,profile,theme,css,info',
                'ignore' => '*node_modules/*,*bower_components/*,*vendor/*,*.min.js,*.min.css',
            ],
            'composer-json-path' => 'composer.json',
        ]
    ): ResultData {
        [
            $paths,
            $similar_options,
        ] = $this->getOptions([
            'paths',
            'similar-options',
        ], $opts, false);
        [
            $composer_json_path,
        ] = $this->getOptions([
            'composer-json-path',
        ], $opts);
        $this->sayWithWrapper('Checking for coding standards issues.');
        // Load composer.json and determine where web root and paths are.
        if (empty($paths)) {
            if (!empty($composer_json_path) &&
                file_exists($composer_json_path)
            ) {
                $composer = json_decode(
                    file_get_contents($composer_json_path),
                    true
                );
                if (!empty($composer['extra']['installer-paths'])) {
                    foreach ($composer['extra']['installer-paths'] as $key => $installer_paths) {
                        if (!empty($installer_paths)) {
                            switch ($installer_paths[0] ?? '') {
                                case 'type:drupal-custom-module':
                                    $custom_modules_path = str_replace(
                                        '/{$name}',
                                        '',
                                        $key
                                    );
                                    continue 2;

                                case 'type:drupal-custom-profile':
                                    $custom_profiles_path = str_replace(
                                        '/{$name}',
                                        '',
                                        $key
                                    );
                                    continue 2;

                                case 'type:drupal-custom-theme':
                                    $custom_theme_path = str_replace(
                                        '/{$name}',
                                        '',
                                        $key
                                    );
                                    continue 2;
                            }
                        }
                    }
                }
            }
            $web_root = $composer['extra']['drupal-scaffold']['locations']['web-root'] ?? 'web/';
            // Ensure web root ends in "/".
            $web_root = !str_ends_with(
                $web_root,
                '/'
            ) ? $web_root.'/' : $web_root;

            // Set defaults if not found in composer.json.
            $custom_modules_path = $custom_modules_path ?? $web_root . 'modules/custom';
            $custom_profiles_path = $custom_profiles_path ?? $web_root . 'profiles/custom';
            $custom_theme_path = $custom_theme_path ?? $web_root . 'themes/custom';

            $paths = [
                $custom_modules_path => [],
                $custom_profiles_path => [],
                $custom_theme_path => [],
            ];
        }
        // Set similar option defaults on the paths.
        foreach ($paths as &$options) {
            $options += $similar_options;
        }
        unset($options);
        // Remove any path that does not actually exist.
        foreach ($paths as $path => $options) {
            if (!is_dir($path)) {
                unset($paths[$path]);
            }
        }
        if (empty($paths)) {
            $this->printError(
                'Unable to find any folders to run coding standards checks on.'
            );

            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $one_failed = false;
        foreach ($paths as $path => $path_options) {
            $success = $this->taskExec('./vendor/bin/phpcs')
                ->options($path_options, '=')
                ->arg($path)
                ->run()->wasSuccessful();
            if (!$one_failed && !$success) {
                $one_failed = true;
            }
        }
        if ($one_failed) {
            $this->printError(
                'ERROR: Coding standards failed, please see the output above.'
            );

            return new ResultData(ResultData::EXITCODE_ERROR);
        } else {
            $this->sayWithWrapper('SUCCESS: Coding standards passed.');

            return new ResultData();
        }
    }

    /**
     * Ensure that the 'composer.lock' file is valid.
     *
     * @command validate:composer-lock
     *
     * @option string $composer-path The path to the composer binary.
     * @option string $working-directory In case composer is not in the same
     *   directory as RoboFile.php.
     *
     * @return \Robo\ResultData
     */
    public function validateComposerLock(
        array $opts = [
            'composer-path' => 'vendor/bin/composer',
            'working-directory' => './',
        ]
    ): ResultData {
        [
            $composer_path,
            $working_directory,
        ] = $this->getOptions(['composer-path', 'working-directory'], $opts);
        $this->sayWithWrapper('Ensuring valid composer.lock file.');
        if (!$this->taskExec($composer_path)
            ->arg('validate')
            ->option('working-dir', $working_directory)
            ->option('no-check-all')
            ->option('no-check-publish')
            ->option('no-interaction')
            ->run()->wasSuccessful()) {
            $this->printError(
                sprintf(
                    'Composer lock is invalid, please see the output above. Also note, that running "%s update' .
                    ' --lock" should be able to help without updating all dependencies.',
                    $opts['composer-path']
                )
            );

            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $this->sayWithWrapper('SUCCESS: composer.lock is valid.');

        return new ResultData();
    }

    /**
     * Validate the text of all commit messages missing from $target-branch.
     *
     * @command validate:commit-messages
     *
     * @option string $project-id Used as a token replacement in
     *   pattern. Defaults to ''.
     * @option string $target-branch The branch that these commits will be
     *   merged into. Defaults to 'develop'.
     * @option string $git-remote The name of the git remote to fetch the latest
     *   commits on $target-branch.
     * @option string pattern The regular expression to use validate all
     *   commits not on $target-branch. {$project_id} can be used as token in
     *   this value.
     * @option string $short-help A short description when invalid commits are
     *   found. {$project_id} can be used as a token.
     * @option string[] $long-help An array of strings shown to help describe
     *   how exactly commit messages should be. {$project_id} can be used as a
     *   token in any of these values.
     *
     * @return \Robo\ResultData
     */
    public function validateCommitMessages(
        array $opts = [
            'project-id' => '',
            'target-branch' => 'develop',
            'git-remote' => 'origin',
            'pattern' => '/^{$project_id}-(\d+): /',
            'short-help' => 'Commit messages must start with: \'{$project_id}-x:y\'',
            'long-help' => [
                'Where x is the ticket number.',
                'And y is a space.',
                '',
                'How do I update my commit messages?',
                'See https://www.atlassian.com/git/tutorials/rewriting-history',
                '',
                'After re-rewriting the history, you should --force-with-lease the push.',
                'https://stackoverflow.com/questions/52823692/git-push-force-with-lease-vs-force',
            ],
        ]
    ): ResultData {
        [
            $project_id,
            $long_help,
        ] = $this->getOptions(['project-id', 'long-help'], $opts, false);
        [
            $target_branch,
            $git_remote,
            $pattern,
            $short_help,
        ] = $this->getOptions(
            [
                'target-branch',
                'git-remote',
                'pattern',
                'short-help',
            ],
            $opts
        );
        $replace = static function ($subject) use ($project_id) {
            return str_replace(
                '{$project_id}',
                preg_quote($project_id),
                $subject
            );
        };
        $this->sayWithWrapper(
            "Validating commit messages to be added to '$target_branch'"
        );
        // Fetch the latest in the target branch.
        if (!$this->_exec(
            "git fetch $git_remote $target_branch:refs/remotes/$git_remote/$target_branch"
        )->wasSuccessful()) {
            $this->printError('Unable to fetch the target branch.');

            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $git_command = "git log $git_remote/$target_branch...HEAD --pretty=format:%s --no-merges";
        exec($git_command, $output, $result_code);
        if ($result_code !== 0) {
            $this->printError('Unable to git log data.');

            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $bad_commits = [];
        foreach ($output as $git_message) {
            // Allow $project_id to be used as a token in the message pattern.
            if (!preg_match($replace($pattern), $git_message)) {
                $bad_commits[] = $git_message;
            }
        }
        if (!empty($bad_commits)) {
            $this->printError('The following commits are not valid:');
            foreach ($bad_commits as $bad_commit) {
                $this->say($bad_commit);
            }
            $short_help = $replace($short_help);
            $this->sayWithWrapper(" $short_help ");
            foreach ($long_help as $item) {
                $this->say($replace($item));
            }
            $this->say('');
            $this->say('To see all the commits on your branch:');
            $this->say($git_command);

            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $this->sayWithWrapper('SUCCESS: Commit messages are valid.');

        return new ResultData();
    }

    /**
     * Validate a branch name.
     *
     * @command validate:branch-name
     *
     * @arg string $branch_name The branch name to validate. If not given it
     *   the current branch will attempt to be detected.
     *
     * @option string $project-id Used as a token replacement in $pattern.
     *   Defaults to ''.
     * @option string $pattern The regular expression to use validate
     *   $branch_name. {$project_id} can be used as token in this value.
     * @option string custom-help A help message when the 'custom' type branch
     *   is invalid. {$project_id} can be used as token in this value.
     * @option string[] $valid-branch-names These are all branch names that will
     *   be valid.
     *
     * @return \Robo\ResultData
     */
    public function validateBranchName(
        string $branch_name = '',
        array $opts = [
            'project-id' => '',
            'pattern' => '/^feature\/{$project_id}-([\d]{1,})-(?!.*--)([a-z\d]{1})([a-z\d-]{3,})([a-z\d]{1})$/',
            'custom-help' => [
                'feature/{$project_id}-* where * can be:',
                ' - Always lower case.',
                ' - Starts and end with a letter or integer.',
                ' - Contains letters, integers, or dashes (non-consecutive).',
                ' - At least 5 characters long.',
            ],
            'valid-branch-names' => [
                // Matches a branch named 'develop'.
                'explicit|develop',
                // Matches a branch named 'main'.
                'explicit|main',
                // Matches a custom regular expression found in $pattern.
                'custom|',
                // Matches a branch like: hotfix/2.1.3.
                'semantic|hotfix',
                // Matches a branch like (the last number MUST be a 0): release/2.1.0.
                'semantic_end_0|release',
            ],
        ]
    ): ResultData {
        $this->sayWithWrapper("Validating branch name");
        [
            $project_id,
            $custom_help,
        ] = $this->getOptions(['project-id', 'custom-help'], $opts, false);
        [
            $pattern,
            $valid_branch_names,
        ] = $this->getOptions(
            [
                'pattern',
                'valid-branch-names',
            ],
            $opts
        );

        if ($branch_name === '') {
            $branch_name = $this->getGitBranch();
            if (null === $branch_name) {
                $this->printError(
                    'A Git branch cannot be automatically detected. Please pass as the first argument.'
                );

                return new ResultData(ResultData::EXITCODE_ERROR);
            }
        }
        $replace = static function (
            string $subject,
            bool $quote
        ) use (
            $project_id
        ) {
            if ($quote) {
                $project_id = preg_quote($project_id);
            }

            return str_replace('{$project_id}', $project_id, $subject);
        };
        $table = new Table($this->output());
        $table
            ->setHeaders(
                [
                    'Branch Type',
                    'Help',
                ]
            );
        $help_rows = [];
        $success = false;
        // Test all the $valid_branch_names against the current branch name. Each
        // valid branch name does not throw an error if it does not succeed, because
        // we don't know which of the valid branch name types it was trying to be.
        // Instead, stop on a successful match of any valid branch type then show
        // the help for all types that are valid.
        foreach ($valid_branch_names as $valid_branch_name) {
            [$type, $value] = explode('|', $valid_branch_name);
            switch ($type) {
                case 'explicit':
                    $help_rows[] = [
                        $type,
                        "The branch name: $value",
                    ];
                    if ($value === $branch_name) {
                        $success = true;
                        break 2;
                    }
                    break;

                // This 'custom' type lets you match the branch name against a custom
                // regular expression ($pattern).
                case 'custom':
                    if (empty(array_filter($custom_help))) {
                        $this->printError("If 'custom' branch type is used then --custom-help must be given. Otherwise,"
                            . " pass custom --valid-branch-names without 'custom'.");
                        return new ResultData(ResultData::EXITCODE_ERROR);
                    }
                    foreach ($custom_help as &$item) {
                        $item = $replace($item, false);
                    }
                    $help_rows[] = [
                        $type,
                        implode("\n", $custom_help),
                    ];
                    if (preg_match($replace($pattern, true), $branch_name)) {
                        $success = true;
                        break 2;
                    }
                    break;

                case 'semantic':
                case 'semantic_end_0':
                    $semantic_help = [
                        "\"$value/*\" where * can be:",
                        " - Start with an integer greater than 0.",
                        " - Then a period.",
                        " - Then an integer.",
                        " - Then a period.",
                        $type === 'semantic_end_0' ? ' - Then 0.' : ' - Then an integer greater than 0.',
                    ];
                    $help_rows[] = [
                        $type,
                        implode("\n", $semantic_help),
                    ];
                    $value = preg_quote($value);
                    $end = $type === 'semantic_end_0' ? '0' : '([1-9]+)';
                    if (preg_match(
                        "/^$value\/(\d+)\.(\d+)\.$end$/",
                        $branch_name
                    )) {
                        $success = true;
                        break 2;
                    }
                    break;

                default:
                    $this->printError("$type is not a valid branch type");
                    break 2;
            }
        }

        if (!$success) {
            $this->printError(
                "Invalid branch name '$branch_name'"
            );

            $table->setRows($help_rows);
            $table->render();
            return new ResultData(ResultData::EXITCODE_ERROR);
        }
        $this->sayWithWrapper("SUCCESS: Branch name '$branch_name' is valid.");

        return new ResultData();
    }

    /**
     * Initialize a robo.yml file with configuration for this package.
     *
     * @command validate:init-robo-yml
     *
     * @return \Robo\ResultData
     */
    public function validateInitRoboYml()
    {
        if (!$robo_example_path = $this->findFirstFileUp('robo.example.yml')) {
            throw new \Exception('Unable to load a robo.example.yml from this project.');
        }

        $project_robo_yml_relative_path = './robo.yml';
        $robo_example_relative_path = str_replace(getcwd(), '.', $robo_example_path);
        if (!file_exists('robo.yml') &&
            $this->confirm("Would you like to copy $project_robo_yml_relative_path to your project root?")) {
            $this->taskFilesystemStack()->copy($robo_example_path, $project_robo_yml_relative_path)->run();
        }
        if (file_exists($project_robo_yml_relative_path)) {
            $parsed_yml_array = Yaml::parseFile($project_robo_yml_relative_path);
        } else {
            $parsed_yml_array = [];
        }
        $project_id =& $parsed_yml_array['command']['validate']['options']['project-id'];

        if ($this->confirm("Would you like to update some settings in your $project_robo_yml_relative_path" .
            " using prompts? WARNING: This will remove all comments from the file.")) {
            $format_comments = static function (array $comments) use ($robo_example_relative_path): string {
                return "\n\nAdditional Context from $robo_example_relative_path:\n\n" . implode("\n", $comments);
            };
            $updated_site_robo = false;
            $default = $project_id ?? '[Not Set]';
            $comments = $this->getYmlFileCommentsForKey('command:validate:options:project-id', $robo_example_path);
            if ($this->confirm("Optional project-id is '$default', would you like to update it?" .
                $format_comments($comments))) {
                $updated_site_robo = true;
                $project_id = (string) $this->ask('Set a value:');
            }
            if ($updated_site_robo) {
                $notice = "# Automatically Generated from 'robo validate:init-robo-yml'.\n# See " .
                    "$robo_example_relative_path for additional context.\n";
                file_put_contents($project_robo_yml_relative_path, $notice . Yaml::dump($parsed_yml_array, 10));
            }
        }

        if (empty($project_id)) {
            $this->say("You are not using a project-id.");
            $this->say("It's highly recommended that you copy all options that include '{\$project_id}' in " .
                "$robo_example_relative_path to your robo.yml and configure them to meet your needs.");
            $this->say("Please look at $project_robo_yml_relative_path for an example as well.");
        }

        $this->sayWithWrapper('The default configuration is built around Jira issue numbers for how it integrates ' .
            'with with the commit message and branch naming validation.');
        if (!$this->confirm('Are you using Jira for issue tracking?')) {
            $this->say("If you're using GitHub issue tracker, please take a look at " .
                "$project_robo_yml_relative_path for an example.");
            $this->say("If you're using GitLab issue tracker, take a look at this documentation " .
                'https://docs.gitlab.com/ee/user/project/issues/managing_issues.html#closing-issues-automatically');
        }
    }

    /**
     * Fron the current dir, keep going up until you find $file_name.
     *
     * @param string $file_name
     *   A file name with no paths.
     *
     * @return null|string
     *   The absolute path to the found file or null if none found.
     */
    protected function findFirstFileUp(string $file_name) :?string
    {
        $dirs = explode(DIRECTORY_SEPARATOR, __DIR__);
        while ($current_dir = array_pop($dirs)) {
            $possible_abs_file_path = implode(DIRECTORY_SEPARATOR, $dirs) .
                DIRECTORY_SEPARATOR . $current_dir . DIRECTORY_SEPARATOR . $file_name;
            if (file_exists($possible_abs_file_path)) {
                return $possible_abs_file_path;
            }
        }
        return null;
    }

    /**
     * Given the path to a YML key in a YML file, return all that key's comments.
     *
     * THIS ONLY WORKS FOR YML OBJECTS, NOT ON ARRAYS.
     * For example key.child.0 will not work as '0' array items can not be accessed.
     *
     * @param string $key_path
     *   A nested parent child key of a YML file. Like top:child1:child2:final. Where final is the comment you want.
     * @param $file_path
     *   A path to a YML file.
     *
     * @return array
     *   An array of lines of comments of $key_path.
     *
     * @throws \Exception
     *   If the file is not readable or a parseable YML file.
     */
    protected function getYmlFileCommentsForKey(string $key_path, string $file_path) :array
    {
        // Use the Symfony Yaml parser to:
        // * Ensure proper formed YML file.
        // * Find the $key_path they asked for to ensure it's there before we search for its comments.
        $parsed_yml_array = Yaml::parseFile($file_path);
        $results = $parsed_yml_array;
        $current_debug = '';
        // Turn the $key_path into an array and use that as the array keys to search in the parsed YML file.
        $yml_keys_descending = explode(':', $key_path);
        foreach ($yml_keys_descending as $yml_key) {
            // As it nests down, keep track of where it is at currently in a more viewable way.
            $current_debug .= " > $yml_key";
            if (array_key_exists($yml_key, $results)) {
                // Results now becomes the 'one level down' value that will be looked in.
                $results = $results[$yml_key];
                // If the key exists, but it has no value, still make it an array for the next loop around to determine
                // if there are more keys to find down.
                if (null === $results) {
                    $results = [];
                }
            } else {
                $array_keys_found = implode(', ', array_keys($results));
                if (!strlen($array_keys_found)) {
                    $array_keys_found = '[There are no keys here, you may have gone too far]';
                }
                throw new \Exception("The YML key $yml_key does not exist in $file_path. Could not find a " .
                    " key at $current_debug. Possible array key(s) at this level: $array_keys_found.");
            }
        }
        // If it has made it this var, the $key_path was found, otherwise an exception is thrown.
        // We don't use the values from above, as we don't care about the values of the key, only that it was found.

        // Initialize the comments for $key_path.
        $lines_in_comment_block = [];

        // Initialize the YML key to look for starting at the top most parent.
        $current_yml_key = array_shift($yml_keys_descending);

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new \Exception("Could not open $file_path to be read.");
        }
        // Go through each line of the file looking for comments and then determining if those comments belong to the
        // $key_path.
        while (($line = fgets($handle)) !== false) {
            // Check if the line starts with #, that is a comment. Capture all the comments...
            if (preg_match('/^\s*#/', $line)) {
                $lines_in_comment_block[] = trim($line);
            // Until we get to a non-comment. Unless that line is the final key that we're looking for, discard
            // all previous comments found.
            } else {
                // Check if the line starts with empty space + $current_yml_key + :
                // If yes, it means all comments found before are for this key.
                if (preg_match('/^\s*' . preg_quote($current_yml_key) . ':/', $line)) {
                    // If there are more parent keys to search, the final key has not been found, do not capture
                    // the comments yet. Instead, get the next parent as the current.
                    if (!empty($yml_keys_descending)) {
                        $current_yml_key = array_shift($yml_keys_descending);
                    // The final key has been found, $lines_in_comment_block is now only its comments.
                    } else {
                        break;
                    }
                }
                // Discard all the comments if it was not for a key being looked for.
                $lines_in_comment_block = [];
            }
        }
        fclose($handle);
        if (!empty($yml_keys_descending)) {
            throw new \Exception('The YML comment parser did not make it to the last key in question, ' .
                'it could not find the final keys of: ' . implode(' > ', $yml_keys_descending));
        }
        return $lines_in_comment_block;
    }
}
