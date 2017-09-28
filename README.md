# CRUDley
CRUDley is a command line tool for creatind CRUD entities in your Laravel application.  Laravel handles most of the gruntwork
for creating CRUD applications for you, however you still have to go through the motions of creating a resource controller with a model,
creating your views, then creating a resource route. CRUDley handles all of those steps for you and allows for a high level of customization in the process.

## Setup
Setup for CRUDley is simple.  First, require the package via composer (I suggest using require-dev since this is for asset-generation and isn't 
necessary in a production environment).
```bash
composer require --dev jacoblandry/crudley
```

Next you need to run vendor:publish to publish the file that tracks the history of your CRUD creations (for rollback) as well as the view template
```php
php artisan vendor:publish
```

Last you need to register the command in your app/Console/Kernal.php file
```php
protected $commands = [
    \JacobLandry\CRUDley\Commands\CRUDley::class
];
```

That's it, now you're in business!

## Usage
I've designed CRUDley to be as simple as possible but it allows for some customization which can become complicated.
As a reminder, you can always view the documentation via artisan
```bash
php artisan help make:crud
```

*Note: You can edit the template that is used to copy these resources in thew views/CRUDTemplates directory*

### Simple Usage Example
In its simplest form, you can simply call make:crud and receive an MVC setup for a resource.
```bash
php artisan make:crud Test
```
This will create the following
- Controller: TestController
- Model: Test
- Views: Test/show.blade.php,list.blade.php,create.blade.php,edit.blade.php
- Routes: resource('test', 'TestController')

### Advanced Usage
In a more custom fashion you can use the following arguments:
- --model = Name of the model to be created
- --controller = Name of the controller to be created
- --viewDirector = Name of the directory to place the views in
- --viewTemplate = The tamplate to use to copy views
- --view = An input array of custom view names to create (defaults to show,list,create,edit)
- --list = Custom view template for the list view
- --show = Custom view template for the show view
- --create = Custom view template for the create view
- --edit = Custom view template for the edit view
- --rollback = Undo changes for this resource