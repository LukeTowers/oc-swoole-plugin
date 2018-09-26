<?php

namespace OctoberFly\Server\Traits;

use Symfony\Component\EventDispatcher\GenericEvent;

Trait October
{

    /**
     * For APP_TYPE=='worker', an october application instance living always with a worker, not the server.
     *
     * In Mode Map, it can't be made living always with the server,
     * because most of Coroutine-Friendly Services are made only by \Co::getUid()
     * without using swoole_server::$worker_id, they can not distinguish coroutines in different workers.
     *
     * @var \OctoberFly\Map\Application|\OctoberFly\Backup\Application
     */
    protected $app;

    /**
     * An laravel kernel instance living always with a worker.
     *
     * @var \OctoberFly\Map\Kernel|\OctoberFly\Backup\Kernel
     */
    protected $kernel;

    /**
     * @return \OctoberFly\Map\Application|\OctoberFly\Backup\Application
     */
    public function getApp()
    {
        return $this->app;
    }

    public function _makeOctoberApp()
    {

        /** @var $app \OctoberFly\Map\Application|\OctoberFly\Backup\Application */
        $this->app = $app = new $this->appClass($this->root);

        /** @var \OctoberFly\Server\ServerInterface|\OctoberFly\Server\HttpServer|\OctoberFly\Server\FpmHttpServer $this */
        $app->setServer($this);

        $app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            $this->kernelClass
        );
        $app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            '\October\Rain\Foundation\Console\Kernel'
        );
        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            '\October\Rain\Foundation\Exception\Handler'
        );

        $this->kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

        return $app;

    }

    public function startLaravel(\swoole_http_server $server = null)
    {
        $app = $this->_makeOctoberApp();

        /**
         * instance a fake request then bootstrap
         *
         * new UrlGenerator need a request.
         * In Mode Backup, no worry about it's fake, because
         * app['url']->request will update when app['request'] changes, as rebinding is used
         * <code>
         * <?php
         * $url = new UrlGenerator(
         *  $routes, $app->rebinding(
         *      'request', $this->requestRebinder()
         *  )
         * );
         * ?>
         *  "$app->rebinding( 'request',...)"
         * </code>
         * @see  \Illuminate\Routing\RoutingServiceProvider::registerUrlGenerator()
         *
         */
        $this->app->instance('request', \Illuminate\Http\Request::createFromBase(new \Symfony\Component\HttpFoundation\Request()));

        try {
            $this->kernel->bootstrap();
        } catch (\Throwable $e) {
            $msg=$e->getMessage();
            $trace=$e->getTraceAsString();
            echo "[FLY ERROR] bootstrap: $msg\n$trace";
            $server && $server->shutdown();
        }

        // the fake request is useless, but harmless too
        // $this->app->forgetInstance('request');


        $this->echo("event laravel.ready with $this->appClass in pid ".getmypid());

        // the 'request' here is different form FpmHttpServer
        $event = new GenericEvent(null, ['server' => $this, 'app' => $app, 'request' => null]);
        $this->dispatcher->dispatch('laravel.ready', $event);

    }

    /**
     * convert swoole request info to php global vars
     *
     * only for Mode One
     *
     * @param \swoole_http_request $request
     * @see https://github.com/matyhtf/framework/blob/master/libs/Swoole/Request.php setGlobal()
     */
    protected function setGlobal($request)
    {
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_FILES = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];

        $_SERVER = array();
        foreach ($request->server as $key => $value) {
            $_SERVER[strtoupper($key)] = $value;
        }

        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

        foreach ($request->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$_key] = $value;
        }
    }

    /**
     * @param \swoole_http_request $r
     * @return \Illuminate\Http\Request
     *
     * from: Illuminate\Http\Request\createFromBase
     */
    public function createLaravelRequest(\swoole_http_request $r)
    {
        $server = [];

        foreach ($r->server as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        foreach ($r->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $server[$_key] = $value;
        }


        $request = new \Illuminate\Http\Request(
            $r->get ?? [],
            $r->post ?? [],
            [],
            $r->cookie ?? [],
            $r->files ?? [],
            $server,
            $r->rawContent() ?: null
        );

        /*
         *
         * from: Illuminate\Http\Request\createFromBase
         *      $request->request = $request->getInputSource();
         */
        (function () {
            $this->request = $this->getInputSource();
        })->call($request);


        return $request;

    }

    /**
     * produce swoole response from laravel response
     *
     * @param \swoole_http_response $response
     * @param $laravel_response
     */
    protected function swooleResponse(\swoole_http_response $response, \Symfony\Component\HttpFoundation\Response $laravel_response): void
    {
        foreach ($laravel_response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }

        /** @var  \Symfony\Component\HttpFoundation\Cookie $cookie */
        foreach ($laravel_response->headers->getCookies() as $cookie) {
            $response->cookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }

        $response->status($laravel_response->getStatusCode());

        // gzip use nginx
        // $response->gzip(1);

        $response->end($laravel_response->getContent());
    }
}
