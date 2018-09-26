<?php
/**
 * Dict
 * plus
 * gatherRouteTerminateMiddleware // search 'hack' in this file
 */

namespace OctoberFly\Map;

use Exception;
use Illuminate\Routing\Router;
use Throwable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Foundation\Http\Events;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    use \LaravelFly\Map\Util\Dict;
    protected static $normalAttriForObj = [];
    protected static $arrayAttriForObj = ['middleware'];

    /**
     * The application implementation.
     *
     * @var \OctoberFly\Map\Application
     */
    protected $app;

    protected $bootstrappers = [
        '\October\Rain\Foundation\Bootstrap\RegisterClassLoader',
        '\October\Rain\Foundation\Bootstrap\LoadEnvironmentVariables',

        // \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \OctoberFly\Map\Bootstrap\LoadConfiguration::class,

        '\October\Rain\Foundation\Bootstrap\LoadTranslation',

        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,

        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,

        '\October\Rain\Foundation\Bootstrap\RegisterOctober',

        // \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        // \Illuminate\Foundation\Bootstrap\BootProviders::class,
        \OctoberFly\Map\Bootstrap\RegisterAcrossProviders::class,
        \OctoberFly\Map\Bootstrap\OnWork::class,
        \OctoberFly\Map\Bootstrap\ResolveSomeFacadeAliases::class,
        \OctoberFly\Map\Bootstrap\CleanOnWorker::class,

    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // 'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        // 'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // 'can' => \Illuminate\Auth\Middleware\Authorize::class,
        // 'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \October\Rain\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            // \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    protected $middlewarePriority = [
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // \Illuminate\Auth\Middleware\Authenticate::class,
        // \Illuminate\Session\Middleware\AuthenticateSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // \Illuminate\Auth\Middleware\Authorize::class,
    ];


    /*  coroutine start. This part only for coroutine */

    public function __construct(\Illuminate\Contracts\Foundation\Application $app, Router $router)
    {
        parent::__construct($app, $router);

        $this->initOnWorker(true);

        static::$corDict[WORKER_COROUTINE_ID]['middleware'] = $this->middleware;
    }

    public function hasMiddleware($middleware)
    {
        return in_array($middleware, static::$corDict[\Co::getUid()]['middleware']);
    }

    public function prependMiddleware($middleware)
    {
        if (array_search($middleware, static::$corDict[\Co::getUid()]['middleware']) === false) {
            array_unshift(static::$corDict[\Co::getUid()]['middleware'], $middleware);
        }

        return $this;
    }

    public function pushMiddleware($middleware)
    {
        if (array_search($middleware, static::$corDict[\Co::getUid()]['middleware']) === false) {
            static::$corDict[\Co::getUid()]['middleware'][] = $middleware;
        }

        return $this;
    }

    /*  coroutine END */


    public function handle($request)
    {
        try {
            // moved to LaravelFlyServer::initAfterStart
            // $request::enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);

        } catch (Exception $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {

            $this->reportException($e = new FatalThrowableError($e));

            $response = $this->renderException($request, $e);
        }

        $this->app['events']->dispatch(
            new Events\RequestHandled($request, $response)
        );

        return $response;
    }

    protected function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        // moved to \OctoberFly\Map\Bootstrap\CleanOnWorker. After that, no need to clear in each request.
        // Facade::clearResolvedInstance('request');

        // replace $this->bootstrap();
        $this->app->bootInRequest();

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->app->shouldSkipMiddleware() ? [] : static::$corDict[\Co::getUid()]['middleware'])
            ->then($this->dispatchToRouter());
    }

    //hack
    protected function terminateMiddleware($request, $response)
    {
        $middlewares = $this->app->shouldSkipMiddleware() ? [] : array_merge(
            // hack
            // $this->gatherRouteMiddleware($request),
            $this->app->gatherRouteTerminateMiddleware($request),

            // $this->middleware
            // no cache for kernel middlewares when !LARAVELFLY_SERVICES['kernel'], it's different from src/OctoberFly/Map/Kernel.php
            static::$corDict[\Co::getUid()]['middleware']
        );

        foreach ($middlewares as $middleware) {
            /**
             * hack: middlewares not only string, maybe objects now,
             */
            if (is_string($middleware)) {
                list($name) = $this->parseMiddleware($middleware);

                $instance = $this->app->make($name);

            } elseif (is_object($middleware)) {
                $instance = $middleware;
            } else {
                continue;
            }

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }


}
