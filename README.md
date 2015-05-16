# VeganMenuBundle
VeganMenuBundle for easy menus with Symfony2!

Contains object-oriented MenuBuilder with powerful caching tool helpful for database menus. MenuBuilder 

#### 1. Install it by composer:
```
composer require vegan/menu-bundle
```

#### 2. allow in your AppKernel:

```php
/* /app/AppKernel.php */
public function registerBundles()
{
    $bundles = array(
        new Vegan\MenuBundle\VeganMenuBundle()
        // ... your bundles
    );
    
    // ... require dev bundles ...
    
    return $bundles;
}
```

#### 3. Create you own Menu!

* Use service 'vegan.menu.builder' in container and build custom Menu! *

```php
 $builder = $this->container->get('vegan.menu.builder');
 // $builder->enableCache();     // you can turn on menu caching (very powerfull tool if you load menu from database)
 // $builder->disableCache();    // of course you can turn off menu caching
 
 // $defaultMenuOptions = $builder->getDefaultMenuOptions();   // you can dump this menu options and you will see how is possible to change anything
 
 $builder
    ->createMenu($menuAnchor = 'main-menu',
                 $defaultRouteName = '_my_route_name',
                 $defaultLocale = 'en_US',
                 $defaultMenuOptions = array()
    );

 $builder
    ->createItem('item-1', array(           // first required parameter is `anchor` = it's simply the unique key, by you can grab that menu item and manipulate with (very useful because you can grab 
        'name' => 'My root menu item 1',    // option `name` will be displayed in frontend
        'slug' => 'my-root-menu-item-1',    // option `slug` can be unique URI identification key
        'permalink_generate' => true,       // option `permalink_generate` will auto generate `permalink` (it is unique URI identificator with whole menu tree parents of this item)
    ))
    ->createItem('item-2', array(
        'name' => 'My menu item number 2!',
        'slug_generate' => true,        // `slug` is unique menu item URI identifier
        'permalink_generate' => true,   // `permalink` is unique URI identifier based on whole menu tree slugs (e.g. main-item/secondary-item/third-item) 
        'parent' => 'item-3',           // yeah, parent is not created yet! but we will create it after this menu item:
    ))
    ->createItem('item-3', array(       // now we have MenuItem with anchor `item-3` and it will have child `item-2`!
        'parent' => 'item-1',           // parent is item-1
        'slug_generate' => true,
        'permalink_generate' => true,
    ));
 
 $mainMenu = $builder->getMenu();       // now we have menu ready for use in templates, of course we can do more options with menu
 
 /* now you can pass $mainMenu from your Controller to the Twig template! */
 
```

#### Manipulations with Menu

When we construct a Menu, we can do anything with that instance.

 * Position moving
```php
 $mainMenu->moveItemToPosition('item-3', 10);   // will move item with anchor 'item-3' to the 10. position
 $mainMenu->moveItemToFirstPosition('item-1');  // will move 'item-1' to the first position
 $mainMenu->moveItemToLastPosition('item-2');   // will move 'item-2' to the last position
```

 * How to find MenuItem inside Menu?
```php
 $myMenuItem = $mainMenu->findMenuItem('item-2');    // findMenuItem will return instance of MenuItem or false if nothing will be found
 if (false === $myMenuItem) {
    // MenuItem with anchor 'item-2' was not found in Menu tree
 }
```

 * How can I clean Menu from cache?
If you want to rebuild your menu (like from database), you can use powerful caching tool nette/caching like this:

```php
$cache = $this->container->get('nette.caching');    // is installed with VeganMenuBundle
$cache->remove('vegan.menu.[YOUR MENU ANCHOR]');    // if you used method $builder->enableCache() then your menu is in cache 'vegan.menu.' + menu anchor
```

## DynamicMenuBuilder
In many cases we want to build menus from database. 
It's great to have dynamic Menus and manipulate with them by CMS or Administration system. 

Great, now I will show you how you can generate 3 separate menus (main-menu, left-menu and footer-menu) and every request will return only cached results without database queries!

```php
$loadMenuAnchors = array('main-menu', 'left-menu', 'footer-menu');
/*
 * if you want to load root node for main-menu, we can use following feature:
 * $loadRootNodes = array('main-menu' => 'root-menu-item-anchor');
 */
$builder = $this->container->get('vegan.menu.dynamic.builder');
```

