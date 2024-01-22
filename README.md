# Robo Validate Commands

**A group of [Robo](https://robo.li) commands that run various validation tasks on local environments or pipelines**

* Coding standards (``validate:coding-standards``)
  * Uses PHPCS to validate code.
  * Zero config for Drupal projects.
* Composer lock (``validate:composer-lock``)
  * Ensures you don't get this message during ``composer install``:
> Warning: The lock file is not up to date with the latest changes in composer.json. You may be getting outdated dependencies. Run update to update them.
* Commit messages (``validate:commit-messages``)
  * Validate commit messages against a regular expression.
* Branch name (``validate:branch-name``)
  * Validate a branch name against a regular expression.
  * Note: This command has an optional single parameter which is the branch name if the current branch name cannot be determined automatically.
* Run all the above (``validate:all)``

## Installing

``composer require mattsqd/robovalidate``

## Usage

Use `vendor/bin/robo` to execute Robo tasks.

## Configuration

There are two ways to configure the commands.
1. Pass options to the command as they run.
2. Create (or update) a robo.yml in your root directory that holds the options.

Number two is the easiest as some options are arrays, and it gets very ugly trying to pass arrays at run time.

All the options live under the `command.validate.options` namespace in robo.yml.

Some commands share the same option, such as project-id. So changing it will affect all commands unless you move that key under the specific commands section. You can see an example of this with 'pattern' which is used in two commands.

If you'd like to initialize your robo.yml with what's in robo.example.yml, please use the command:

`vendor/bin/robo validate:init-robo-yml`

### Quick Start

The quick start assumes:
* This is a Drupal project using Drupal and DrupalPractice coding standards.
* You want commit messages like: 'ABC-1234: A short message' and you're merging into origin/develop.
* You want branches named like: main, develop, hotfix/3.3.1, release/3.3.0, and feature/ABC-123-a-short-message.
* Your composer.lock lives in the root directory and composer lives at vendor/bin/composer.

This can be configured by creating ``robo.yml`` in your project root with the following content:
``` yml
command:
  validate:
    options:
      project-id: ABC
```

And run `vendor/bin/robo validate:all`

If any of the above does not apply you can either:
* Call individual commands instead of ``validate:all`` or
* Configure your robo.yml as in robo.example.yml.

Please see robo.example.yml for an example of the defaults configured explicitly.

For each option, you only need to override the ones you want to change, you don't need to copy the entire file, although you can.
