# VeganMenuBundle
VeganMenuBundle for easy building menus

Simple MenuBuilder which you can simply create your menu, cache it and write custom twig template by recursive macro.

### Simply installation in your project:
```
composer require vegan/menu-bundle
```

### Allow VeganMenuBundle inside AppKernel.php

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

### Simply menu builder

Now in your container is services:
vegan.menu.builder
vegan.menu.database.builder
vegan.menu.simple.builder

```php
 $builder = $this->container->get('vegan.menu.builder');
 // $builder->enableCache();     // you can turn on menu caching (very powerfull tool if you load menu from database)
 // $builder->disableCache();    // of course you can turn off menu caching
 
 $defaultMenuOptions = $builder->getDefaultMenuOptions();   // you can dump this menu options and you will see how is possible to change anything
 
 $builder
    ->createMenu($menuAnchor = 'main-menu', $defaultRouteName = '_my_route_name', $defaultLocale = 'en_US', $defaultMenuOptions = array());
    
 $builder
    ->createItem('item-1', array(
        'name' => 'My root menu item 1',    // option `name` will be displayed in frontend
        'slug' => 'my-root-menu-item-1',    // option `slug` can be unique URI identification key
        'permalink_generate' => true,       // option `permalink_generate` will auto generate `permalink` (it is unique URI identificator with whole menu tree parents of this item)
    ))
    ->createItem('item-2', array(
        'name' => 'My menu item number 2!',
        'slug_generate' => true,        // `slug` is unique menu ite
        'permalink_generate' => true,   // 
        'parent' => 'item-3',           // yeah, parent is not created yet! but we will create it after this menu item:
    ))
    ->createItem('item-3', array(       // now we have MenuItem with anchor `item-3` and it will have child `item-2`!
        'parent' => 'item-1',           // parent is item-1
        'slug_generate' => true,
        'permalink_generate' => true,
    ));
 
 $mainMenu = $builder->getMenu();       // now we have menu ready for use in templates, of course we can do more options with menu
 
 /* How to move position? */
 $mainMenu->moveItemToPosition('item-3', 10);
 $mainMenu->moveItemToFirstPosition('item-1');
 $mainMenu->moveItemToLastPosition('item-2');
 
 $item2 = $mainMenu->findMenuItem('item-2');    // findMenuItem will return instance of MenuItem or false if nothing will be found
 
```
