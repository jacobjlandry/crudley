<?php

namespace JacobLandry\CRUDley;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Storage;

class CRUD
{
    /**
     * private variables used to set up this resource
     *
     * @var $baseName
     * @var $resourceName
     * @var $modelName
     * @var $controllerName
     * @var $viewDirectory
     * @var $viewTempale
     * @var $views
     * @var $registrar
     */
    private $baseName, $resourceName, $modelName, $controllerName, $viewDirectory, $views, $registrar;

    public function __construct()
    {
        //
    }

    /**
     * Set resource base name
     *
     * @param $name
     */
    public function setBaseName($name)
    {
        $this->baseName = $name;
    }

    /**
     * set resource name
     *
     * @param $name
     */
    public function setResourceName($name)
    {
        $this->resourceName = $name;
    }

    /**
     * set model name
     *
     * @param $name
     */
    public function setModelName($name)
    {
        $this->modelName = $name;
    }

    /**
     * set controller name
     *
     * @param $name
     */
    public function setControllerName($name)
    {
        $this->controllerName = $name;
    }

    /**
     * set view folder
     *
     * @param $folder
     */
    public function setViewFolder($folder)
    {
        $this->viewDirectory = $folder;
    }

    /**
     * set views list
     *
     * @param $views
     */
    public function setViews($views)
    {
        $this->views = $views;
    }

    /**
     * Grab history file and set the registrar variable
     * This contains a history of items that we have used CRUDley to create and not rolled back
     */
    public function retrieveRegistrar()
    {
        // grab the registrar
        include(Storage::disk('local')->path("/crud/history.php"));
        $this->registrar = $registrar;
    }

    /**
     * Reset the history file using the current registrar
     */
    public function setRegistrar()
    {
        $contents = "<?php";
        Storage::disk('local')->put('crud/history.php', $contents);
        Storage::disk('local')->append('crud/history.php', '$registrar = [');

        foreach($this->registrar as $baseName => $contents) {
            Storage::disk('local')->append('crud/history.php', "  '$baseName' => [");
            Storage::disk('local')->append('crud/history.php', "    'viewDirectory' => '{$contents['viewDirectory']}',");
            Storage::disk('local')->append('crud/history.php', "    'views' => [");
            foreach($contents['views'] as $viewName => $viewTemplate) {
                Storage::disk('local')->append('crud/history.php', "      '$viewName' => '$viewTemplate',");
            }
            Storage::disk('local')->append('crud/history.php', "    ],");
            Storage::disk('local')->append('crud/history.php', "    'modelName' => '{$contents['modelName']}',");
            Storage::disk('local')->append('crud/history.php', "    'controllerName' => '{$contents['controllerName']}',");
            Storage::disk('local')->append('crud/history.php', "    'resourceName' => '{$contents['resourceName']}',");
            Storage::disk('local')->append('crud/history.php', "  ],");
        }

        Storage::disk('local')->append('crud/history.php', '];');
    }

    /**
     * Register a work function
     */
    public function register($param)
    {
        // set up this object if needed
        if(!isset($this->registrar[$this->baseName])) {
            $this->registrar[$this->baseName] = [];
        }

        // register this item
        $this->registrar[$this->baseName][$param] = $this->$param;
    }

    /**
     * Make View directory
     *
     * @return bool
     */
    public function makeViewDirectory()
    {
        $filesystem = new Filesystem();

        // View dir already exists
        if($filesystem->exists(resource_path("views/{$this->viewDirectory}"))) {
            return false;
        }
        else {
            $this->register("viewDirectory");
            $filesystem->makeDirectory(resource_path("views/{$this->viewDirectory}"));
        }

        return true;
    }

    /**
     * Copy Views to new directory
     */
    public function copyViews()
    {
        $filesystem = new Filesystem();

        $this->views->each(function($viewTemplate, $viewName) use($filesystem) {
            $templateName = str_replace(".", "/", $viewTemplate);

            // copy template
            $filesystem->copy(
                resource_path("views/$templateName.blade.php"),
                resource_path("views/{$this->viewDirectory}/$viewName.blade.php")
            );
        });

        $this->register("views");
    }

    /**
     * Make Model
     *
     * @return bool
     */
    public function makeModel()
    {
        $filesystem = new Filesystem();

        // model already exists
        if($filesystem->exists(base_path("app/{$this->modelName}.php"))) {
            return false;
        }
        else {
            Artisan::call('make:model', ['name' => $this->modelName]);
        }

        $this->register("modelName");

        return true;
    }

    /**
     * Make Controller
     *
     * @return bool
     */
    public function makeController()
    {
        $filesystem = new Filesystem();

        // controller already exists
        if($filesystem->exists(base_path("app/Http/Controllers/{$this->controllerName}.php"))) {
            return false;
        }
        else {
            Artisan::call('make:controller', [
                'name' => $this->controllerName,
                '--resource' => true,
                '--model' => $this->modelName
            ]);
        }

        $this->register("controllerName");

        return true;
    }

    /**
     * Write resource route
     *
     * @return bool
     */
    public function writeRoutes()
    {
        $filesystem = new Filesystem();

        // route already exists
        $routesContents = $filesystem->get(base_path('routes/web.php'));
        if(preg_match("/\\r\\nRoute::resource\('{$this->resourceName}', '{$this->controllerName}'\);/", $routesContents)) {
            return false;
        }
        else {
            $filesystem->append(
                base_path('routes/web.php'),
                "\r\n/** Created by CRUDley at " . date('Y-m-d H:i:s') . " **/\r\nRoute::resource('{$this->resourceName}', '{$this->controllerName}');"
            );
        }

        $this->register("resourceName");

        return true;
    }

    /**
     * Roll back anything that has been created so far
     * This should be run every time there is an error so we aren't creating rogue files
     *
     * @param bool $force if the --rollback option was supplied we should force a rollback via history, otherwise we are rolling back recent changes due to errors
     * @throws \Exception
     */
    public function rollback($force = false)
    {
        // if this is a forced rollback, grab history
        if($force) {
            $this->retrieveRegistrar();
            if(!isset($this->registrar[$this->baseName])) {
                throw new \Exception("Cannot rollback, this resource was not created by CRUDley");
            }
        }

        // set up filesystem object
        $filesystem = new Filesystem();

        // remove all views and directory created for views
        if(isset($this->registrar[$this->baseName]['viewDirectory'])) {
            if($this->recentlyEdited(resource_path("views/{$this->registrar[$this->baseName]['viewDirectory']}"), $force)) {
                $filesystem->cleanDirectory(resource_path("views/{$this->registrar[$this->baseName]['viewDirectory']}"));
                $filesystem->deleteDirectory(resource_path("views/{$this->registrar[$this->baseName]['viewDirectory']}"));
            }
        }

        // remove Model
        if(isset($this->registrar[$this->baseName]['modelName'])) {
            if($this->recentlyEdited(base_path("app/{$this->registrar[$this->baseName]['modelName']}.php"), $force)) {
                $filesystem->delete(base_path("app/{$this->registrar[$this->baseName]['modelName']}.php"));
            }
        }

        // remove controller
        if(isset($this->registrar[$this->baseName]['controllerName'])) {
            if($this->recentlyEdited(base_path("app/Http/Controllers/{$this->registrar[$this->baseName]['controllerName']}.php"), $force)) {
                $filesystem->delete(base_path("app/Http/Controllers/{$this->registrar[$this->baseName]['controllerName']}.php"));
            }
        }

        // clean routes
        if(isset($this->registrar[$this->baseName]['resourceName'])) {
            $routesContents = $filesystem->get(base_path('routes/web.php'));
            // check the comment to see if this was a recent change
            preg_match("/Created by CRUDley at \d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/", $routesContents, $matches);
            collect($matches)->each(function($match) use($routesContents, $filesystem, $force) {
                // grab time from comment
                $time = strtotime(preg_replace("/Created by CRUDley at /", "", $match));
                // compare time
                if((time() - $time < 5) || $force) {
                    // replace comment
                    $routesContents = str_replace("/** $match **/", "", $routesContents);
                    // replace route
                    $routesContents = str_replace("Route::resource('" . $this->registrar[$this->baseName]['resourceName']. "', '" . $this->registrar[$this->baseName]['controllerName'] . "');", "", $routesContents);
                    // save
                    $filesystem->put(base_path("routes/web.php"), trim($routesContents));
                }
            });
        }

        // if you're forcing a rollback then reset the history
        if($force) {
            unset($this->registrar[$this->baseName]);
            $this->setRegistrar();
        }
    }

    /**
     * Determine whether or not this file was modified this command-session
     *
     * @param $path
     * @return bool
     */
    private function recentlyEdited($path, $force)
    {
        // force rollback
        if($force) {
            return true;
        }

        // set up filesystem object
        $filesystem = new Filesystem();

        // seconds now
        $now = time();

        // return whether or not this is a recent edit
        if($filesystem->exists($path)) {
            return $now - $filesystem->lastModified($path) <= 60;
        }
        else {
            return false;
        }
    }
}
