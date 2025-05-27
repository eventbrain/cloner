# Cloner
A trait for Laravel Eloquent models that lets you clone a model and it's relationships, including spatie/media-library - files.


## Installation

To get started with Cloner, use Composer to add the package to your project's dependencies:

```
composer require eventbrain/cloner
```
<br>

> Note: The Below step is optional in Laravel 5.5 or above!

After installing the cloner package, register the service provider.

```php
Eventbrain\Cloner\ServiceProvider::class,
```
in your `config/app.php` configuration file:

```php
'providers' => [
   /*
   * Package Service Providers...
   */
   Eventbrain\Cloner\ServiceProvider::class,
],
```

### Publishing assets
To publish migrations and the cloner config to your project's application directory, use the commands below:

```php
php artisan vendor:publish --tag=cloner-config
php artisan vendor:publish --tag=cloner-migrations
```

### Running migrations

```php
php artisan migrate
```


## Config
In config/cloner.php, you can configure the cloner behavior.

### Trait's cloneable_relations
Model relationships defined in a trait need to be added to `$cloneable_relations` aswell. Cloneable relations for traits can be centrally defined via the `trait_cloneable_relations` key:

```php
"trait_cloneable_relations" => [
   App\Traits\BelongsToTeam::class => ["team"],
   ...
]
```

## Usage

Your model should now look like this:

```php
class Article extends Eloquent {

   use \Eventbrain\Cloner\Cloneable;
}
```

You can clone an Article model like so:

```php
$clone = Article::first()->duplicate();
```

In this example, `$clone` is a new `Article` that has been saved to the database. To clone to a different database:

```php
$clone = Article::first()->duplicateTo('production');
```

Where `production` is the [connection name](https://laravel.com/docs/6.x/database#using-multiple-database-connections) of a different Laravel database connection.


#### Cloning Relationships

Lets say your `Article` has many `Photos` (a one to many relationship) and can have more than one `Authors` (a many to many relationship). Now, your `Article` model should look like this:

```php
class Article extends Eloquent {
   use \Eventbrain\Cloner\Cloneable;

   protected $cloneable_relations = ['photos', 'authors'];

   public function photos() {
       return $this->hasMany('Photo');
   }

   public function authors() {
        return $this->belongsToMany('Author');
   }
}
```

The `$cloneable_relations` informs the `Cloneable` as to which relations it should follow when cloning.
Now when you call `Article::first()->duplicate()`, all of the `Photo` rows of the original will be copied and associated with the new `Article`.
And new pivot rows will be created associating the new `Article` with the `Authors` of the original (because it is a many to many relationship, no new `Author` rows are created).
Furthermore, if the `Photo` model has many of some other model, you can specify `$cloneable_relations` in its class and `Cloner` will continue replicating them as well.

> **Note:** Many to many relationships will not be cloned to a _different_ database because the related instance may not exist in the other database or could have a different primary key.

### Customizing the cloned attributes

By default, `Cloner` does not copy the `id` (or whatever you've defined as the `key` for the model) field; it assumes a new value will be auto-incremented.
It also does not copy the `created_at` or `updated_at`.
You can add additional attributes to ignore as follows:

```php
class Photo extends Eloquent {
   use \Eventbrain\Cloner\Cloneable;

   protected $clone_exempt_attributes = ['uid', 'source'];

   public function article() {
        return $this->belongsTo('Article');
   }

   public function onCloning($src, $child = null) {
        $this->uid = str_random();
        if($child) echo 'This was cloned as a relation!';
        echo 'The original key is: '.$src->getKey();
   }
}
```

The `$clone_exempt_attributes` adds to the defaults.
If you want to replace the defaults altogether, override the trait's `getCloneExemptAttributes()` method and return an array.

Also, note the `onCloning()` method in the example.
It is being used to make sure a unique column stays unique.
The `Cloneable` trait adds to no-op callbacks that get called immediately before a model is saved during a duplication and immediately after: `onCloning()` and `onCloned()`.
The `$child` parameter allows you to customize the behavior based on if it's being cloned as a relation or direct.

In addition, Cloner fires the following Laravel events during cloning:

- `cloner::cloning: ModelClass`
- `cloner::cloned: ModelClass`

`ModelClass` is the classpath of the model being cloned.
The event payload contains the clone and the original model instances.

### BeforeCloneCallback

It is possible to define a callback to the Cloner that will be called before cloning any model. When this callback throws an `\Error()`, the clone will be skipped and the source model will be used for any relationships.

**Here, we're making sure that only models belonging to a certain Team will be cloned**
```php
$cloner = \App::make('cloner');
$cloner->setBeforeCloneCallback(function($model) use ($oldTeam) {
   if(($model instanceof Team && !$model->is($oldTeam))
      || (filled($model->team_id) && $model->team_id != $oldTeam->id) 
   ){
      throw new \Error("Do not clone this model");
   }
});

$newTeam = $cloner->duplicate(
      model: $oldTeam, 
      modelClone: $modelClone, 
);
```

### Cloning files

If you use `spatie/laravel-medialibrary`, attached Media objects will automatically be cloned using the [`$mediaItem->copy()`](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/moving-media) function. This takes care of copying the database entry but also the filesystem copy, no matter which filesystem the media is on.

~~If your model references files saved disk, you'll probably want to duplicate those files and update the references.
Otherwise, if the clone is deleted and it cascades delets, you will delete files referenced by your original model.  `Cloner` allows you to specify a file attachment adapter and ships with support for [Bkwld\Upchuck](https://github.com/BKWLD/upchuck).
Here's some example usage:~~
```php
class Photo extends Eloquent {
   use \Eventbrain\Cloner\Cloneable;

   protected $cloneable_file_attributes = ['image'];

   public function article() {
        return $this->belongsTo('Article');
   }
}
```

~~The `$cloneable_file_attributes` property is used by the `Cloneable` trait to identify which columns contain files.  Their values are passed to the attachment adapter, which is responsible for duplicating the files and returning the path to the new file.~~

~~If you don't use [Bkwld\Upchuck](https://github.com/BKWLD/upchuck) you can write your own implementation of the `Eventbrain\Cloner\AttachmentAdapter` trait and wrap it in a Laravel IoC container named 'cloner.attachment-adapter'.
For instance, put this in your `app/start/global.php`:~~

```php
App::singleton('cloner.attachment-adapter', function($app) {
   return new CustomAttachmentAdapter;
});
```

## Database Persistance

Cloner persists the clone progress in the database using the following Models. Persistance prevents duplicate clones and makes debugging easier.

### ModelClone 
Represents a clone operation consisting of Many `ModelCloneProgress`.

ModelClone has a json column `additional_attributes` which can be used to save additional data for use during the cloning process.
Whole ModelClasses can be exempt from cloning by adding them to `additional_attributes.exempted_classes`.

```php
$modelClone = ModelClone::create([
   "user_id" => 1,
   "additional_attributes" => [
      "exempted_classes" => [
         User::class,
      ]
      /*Your custom data*/
   ]
]);
```

#### Clone Exempt Models
Any model can be added as clone exempt to a ModelClone. CloneExempt Models will be not be cloned, relationship of cloned related models will be kept to the CloneExempt source model.

```php
$modelClone = ModelClone::create();
$modelClone->cloneExempts(Role::class)->attach(Role::where("name", "Super-Admin")->first());
```

#### Retrieving Source-/Clone Models

To make retrieval of source- and clone models by their resp. counterpart easier, there are two helper functions `getSourceByClone(Model $clone)` and `getCloneBySource(Model $source)`:

```php
$clone = $modelClone->getCloneBySource($source);
$source = $modelClone->getSourceByClone($clone);
```


### ModelCloneProgress
Represents the atomatic clone progress of one model. It saves source- and clone model ids via Polymorphic relationship
- `model_type`: Class of the source/cloned model
- `source_id`: Foreign Key of the source model
- `clone_id`: Foreign Key of the clone model