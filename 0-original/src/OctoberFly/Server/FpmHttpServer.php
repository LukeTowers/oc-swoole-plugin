<?php

namespace OctoberFly\Server;

use Symfony\Component\EventDispatcher\GenericEvent;

class FpmHttpServer extends Common implements ServerInterface
{

    function setListeners()
    {
        $this->swoole->on('WorkerStart', array($this, 'onWorkerStart'));

        $this->swoole->on('WorkerStop', array($this, 'onWorkerStop'));

        $this->swoole->on('request', array($this, 'onRequest'));
    }


    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        go(function () use ($request, $response) {

        /** @var \LaravelFly\FpmLike\Application $app */
        $app = new $this->appClass($this->root);

	$app->setServer($this);

        $app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            '\October\Rain\Foundation\Http\Kernel'
        );
        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            '\October\Rain\Foundation\Exception\Handler'
        );

        $event = new GenericEvent(null, ['server' => $this, 'app' => $app, 'request' => $request]);
        $this->dispatcher->dispatch('laravel.ready', $event);
        $this->echo("event laravel.ready with $this->appClass instanced in pid ".getmypid());

        $this->setGlobal($request);

        $laravel_request = \Illuminate\Http\Request::createFromBase(\Symfony\Component\HttpFoundation\Request::createFromGlobals());

        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

        $laravel_response = $kernel->handle(
        // $request = \Illuminate\Http\Request::capture()
            $laravel_request
        );

        $this->swooleResponse($response, $laravel_response);

        $kernel->terminate($laravel_request, $laravel_response);

        });
    }

}
