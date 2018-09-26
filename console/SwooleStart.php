<?php namespace October\Swoole\Console;

use Throwable;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to start the Swoole server.
 *
 * @package october\swoole-plugin
 * @author Tamer Hassan, Luke Towers
 */
class SwooleStart extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'swoole:start';

    /**
     * @var string The console command description.
     */
    protected $description = 'Starts the Swoole service';


    /**
     * @var string The method signature, used in information output
     */
    protected $signature = '
    {action? : start|stop|reload|restart}
    {--c|conf : server conf file, default is <laravel_app_root>/fly.conf.php}
    {--h|help}
    ';

}
