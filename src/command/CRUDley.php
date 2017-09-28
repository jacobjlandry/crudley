<?php

namespace JacobLandry\CRUDley\Commands;

use Illuminate\Console\Command;

class CRUDley extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud {name : The name of the resource to be created} 
                            {--rollback : Delete all files that were created for a resource} 
                            {--resourceName= : Name to be used for routes} 
                            {--model= : Name of the model to be created} 
                            {--controller= : Name of the controller to be created} 
                            {--viewDirectory= : The folder to store the views in (defaults to name provided for CRUD objects)} 
                            {--viewTemplate= : The template to copy views from (default: CRUDTemplates.template)}  
                            {--list= : Custom template for list view} 
                            {--show= : Custom template for show view} 
                            {--create= : Custom template for create view} 
                            {--edit= : Custom template for edit view}
                            {--view=* : Specific view to create (defaults to list, show, create, edit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a full set of files for a CRUD entity. This will create Model/View/Controller/Routes with the specified name.';
    protected $crud, $baseName, $resourceName, $modelName, $controllerName, $viewDirectory, $viewTemplate, $views;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // set up crud handler
        $this->crud = new \JacobLandry\CRUDley\CRUD();

        // grab options
        $this->handleOptions();

        // handle rollback command
        if($this->option('rollback')) {
            $this->comment("Rolling back {$this->argument('name')}...");
            $this->crud->rollback(true);
            $this->info("Successfully rolled back changes for {$this->baseName}");
            return;
        }

        // start process
        $this->info("Creating {$this->argument('name')}...");

        try {
            // build views directory
            if($this->crud->makeViewDirectory()) {
                $this->comment("Created views directory");
                $this->crud->copyViews();
                $this->comment("Created views");
            }
            else if(!$this->confirm("View directory already exists and cannot be created. Continue?")) {
                $this->rollback();
                return;
            }

            // build model
            if($this->crud->makeModel()) {
                $this->comment("Created model");
            }
            else if(!$this->confirm("Model already exists and cannot be created. Continue?")) {
                $this->rollback();
                return;
            }

            // build controller
            if($this->crud->makeController()) {
                $this->comment("Created controller");
            }
            else if(!$this->confirm("Controller already exists and cannot be created.  Continue?")) {
                $this->rollback();
                return;
            }

            // add routes
            if($this->crud->writeRoutes()) {
                $this->comment("Resource Route created");
            }
            else if(!$this->confirm("Routes already exist and cannot be created. Continue?")) {
                $this->rollback();
                return;
            }

            $this->crud->setRegistrar();
            $this->info("Complete!");
        } catch(\Exception $exception) {
            // display error
            $this->error($exception->getMessage());

            // roll back anything we have already done
            $this->crud->rollback();
            $this->info("Successfully rolled back recent changes.");
        }
    }

    /**
     * Set options into protected variables for easy access
     */
    private function handleOptions()
    {
        $this->crud->setBaseName($this->argument('name'));
        $this->crud->setResourceName($this->option('resourceName') ?: strtolower($this->argument('name')));
        $this->crud->setModelName($this->option('model') ?: ucfirst($this->argument('name')));
        $this->crud->setControllerName($this->option('controller') ?: ucfirst($this->argument('name')) . 'Controller');
        $this->crud->setViewFolder($this->option('viewDirectory') ?: $this->argument('name'));

        // handle special view options
        $views = $this->option('view') ? collect($this->option('view')) : collect(['list', 'show', 'create', 'edit']);
        $views = $views->mapWIthKeys(function($view) {
            if($this->hasOption($view) && $this->option($view) != null) {
                return [$view => $this->option($view)];
            }
            else {
                return [$view => ($this->option('viewTemplate') ?: 'CRUDTemplates/template')];
            }
        });
        $this->crud->setViews($views);

        // get history
        $this->crud->retrieveRegistrar();
    }

    /**
     * Rollback changes
     *
     * @param bool $force
     */
    private function rollback($force = false)
    {
        $this->crud->rollback($force);
        $this->info("Successfully rolled back recent changes.");
    }
}
