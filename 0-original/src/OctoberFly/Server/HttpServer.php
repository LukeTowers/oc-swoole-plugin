<?php

namespace OctoberFly\Server;

class HttpServer extends Common implements ServerInterface
{

    function setListeners()
    {
        $this->swoole->on('WorkerStart', array($this, 'onWorkerStart'));

        $this->swoole->on('WorkerStop', array($this, 'onWorkerStop'));

        if ($this->getConfig('mode') === 'Backup') {
            $this->swoole->on('request', array($this, 'onBackupRequest'));
        } else {
            $this->swoole->on('request', array($this, 'onRequest'));
        }
    }

    public function start()
    {
        parent::start();
        \Illuminate\Http\Request::enableHttpMethodParameterOverride();
    }

    public function onWorkerStart(\swoole_server $server, int $worker_id)
    {

        $this->workerStartHead($server, $worker_id);

        if (!$this->getConfig('early_laravel')) $this->startLaravel();

        if (0 == $worker_id) {
            $this->workerZeroStartTail($server);
        }

        $this->workerStartTail($server, $worker_id);
//        eval(tinker());

    }

    /**
     * handle request for Mode Backup
     *
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     *
     */
    public function onBackupRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        go(function () use ($request, $response) {

            /**
             * @see \Symfony\Component\HttpFoundation\Request::createFromGlobals() use global vars, and
             * this static method is alse used by {@link \Illuminate\Auth\SessionGuard }
             */
            $this->setGlobal($request);

            /**
             * @var \Illuminate\Http\Request
             * @see \Illuminate\Http\Request::capture
             */
            $laravel_request = \Illuminate\Http\Request::createFromBase(\Symfony\Component\HttpFoundation\Request::createFromGlobals());


            /**
             * @var \Illuminate\Http\Response
             * @see \Illuminate\Foundation\Http\Kernel::handle
             */
            $laravel_response = $this->kernel->handle($laravel_request);


            $this->swooleResponse($response, $laravel_response);


            $this->kernel->terminate($laravel_request, $laravel_response);

            $this->app->restoreAfterRequest();

        });
    }

    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        go(function () use ($request, $response) {

//        static $i = 0; $TARGET = 200;$i++;if ($i == $TARGET) memprof_enable();

            $cid = \Co::getUid();

            $this->app->initForRequestCorontine($cid);


            if (LARAVELFLY_COROUTINE)
                $laravel_request = $this->createLaravelRequest($request);
            else {
                $this->setGlobal($request);
                $laravel_request = \Illuminate\Http\Request::createFromBase(\Symfony\Component\HttpFoundation\Request::createFromGlobals());
            }

            $laravel_response = $this->kernel->handle($laravel_request);

            $this->swooleResponse($response, $laravel_response);

            $this->kernel->terminate($laravel_request, $laravel_response);


            $this->app->unsetForRequestCorontine($cid);

//        if ($i == $TARGET) {$dump = memprof_dump_array();ob_start();print_r($dump);$d=ob_get_clean();file_put_contents("/vagrant/callgrind.$i.out", $d);}

        });
    }

}
