<?php namespace October\Swoole;

use Backend;
use System\Classes\PluginBase;

/**
 * Swoole Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Swoole',
            'description' => 'No description provided yet...',
            'author'      => 'October',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {

    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'October\Swoole\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'october.swoole.some_permission' => [
                'tab' => 'Swoole',
                'label' => 'Some permission'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'swoole' => [
                'label'       => 'Swoole',
                'url'         => Backend::url('october/swoole/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['october.swoole.*'],
                'order'       => 500,
            ],
        ];
    }
}
