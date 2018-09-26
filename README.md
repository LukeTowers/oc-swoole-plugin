# BIG WARNING

**STOP!** This plugin is so deep in alpha that none shall survive its use! Please don't use this and expect it to work at the present time!

# About

Adds support for [Swoole](https://www.swoole.co.uk/) within OctoberCMS.

## Version Compatibility

- OctoberCMS (Build 444)
- PHP >7.0
- Swoole >4.0

Based on [LaravelFly](https://github.com/scil/LaravelFly)

## Installation

1. Install the [Swoole PHP extension](http://php.net/manual/en/book.swoole.php): `pecl install swoole`

2. Enable the Swoole PHP extension (include it in the `php.ini` file: `extension=swoole.so`).

3. (Recommended): Install the [Inotify PHP extension](http://php.net/manual/en/book.inotify.php): `pecl install inotify`

4. Add the October.Swoole plugin to your project:

- To install from the [Marketplace](https://octobercms.com/plugin/october-swoole), click on the "Add to Project" button and then select the project you wish to add it to before updating the project to pull in the plugin.

- To install from the backend, go to **Settings -> Updates & Plugins -> Install Plugins** and then search for `October.Swoole`.

- To install from [the repository](https://github.com/octoberrain/swoole-plugin), clone it into **plugins/october/swoole** and then run `composer update` from your project root in order to pull in the dependencies.

- To install it with Composer, run `composer require october/swoole-plugin` from your project root.

5. Start the server! `php artisan swoole:start`
