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

### Configuring the robo.yml after running validate:init-robo-yml

#### Project ID
The first choice is whether to use project ID or not. Project ID is used in validating commit messages and branch names. Project ID is up to you if you want it. It's a good reference so you know you're committing to the correct project and also handy if you use BitBucket because issues have a project ID in them. If you do NOT want to use project ID, remove `{$project_id}` from robo.yml. This project's robo.yml does not use project ID, you can use [it as an example](https://github.com/mattsqd/robovalidate/blob/1.x/robo.yml).

#### Branch Names
Branch name schemes usually vary between projects, so make sure that all the branch names that you could use have a corresponding entry in `valid-branch-names`. For example:
 * If you `dev` instead of `develop`, you'd want to rename the entry.
 * If you have a `stage` branch, you'd want to add another `explicit|stage` entry.
 * If you don't want to have regex matching used by the `pattern` and `custom-help` properties, you can just remove the `custom|` entry from `valid-branch-names`.

#### Commit Messages
Commit messages are defaulted to more of a BitBucket style. GitHub uses a [special set of words](https://docs.github.com/en/issues/tracking-your-work-with-issues/linking-a-pull-request-to-an-issue) to link to an issue. You can view this projects [robo.yml](https://github.com/mattsqd/robovalidate/blob/1.x/robo.yml#L18) to see how enforcing those keywords would be possible. If you use project ID, you'll need to add that back into the pattern and help text.

### Running in Continuous Integration

There is a working GitHub Action for this project that you should be able to copy into your project if you use GitHub Actions as well. It can be found at https://github.com/mattsqd/robovalidate/blob/1.x/.github/workflows/run-validation.yml.

[![Run validation with RoboValidate](https://github.com/mattsqd/robovalidate/actions/workflows/run-validation.yml/badge.svg)](https://github.com/mattsqd/robovalidate/actions/workflows/run-validation.yml)

The main thing to note is that `vendor/bin/robo validate:commit-messages` needs a target branch so it goes over only commits that are not in the current branch. By default, this is set to `develop`. If the branch your developers merge into is not `develop`, you can either change the value or pass a value like the same GitHub action does and pass in the target branch which is only availble during pull request events. That is why there are 2 separate steps and `vendor/bin/robo validate:all` is not called. Note that current branch is also passed because on PRs, in GitHub, it does not checkout the current branch cleanly. Instead, you are left at a new hash which is the merge commit of the current branch into the target branch. Therefore, all commits will be processed and the last commit will probably not pass validation, since GitHub creates a commit.
