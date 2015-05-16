# VeganMenuBundle
Build your Menus simply!

Contains object-oriented **`MenuBuilder`** with powerful caching tool helpful for database menus. Also contains database driven **`DynamicMenuBuilder`**.

#### 1. Install it by composer
```
composer require vegan/menu-bundle
```

#### 2. Configuration in /app/AppKernel.php

```php
/* /app/AppKernel.php */
public function registerBundles()
{
    $bundles = array(
        new Vegan\MenuBundle\VeganMenuBundle()
        // ... your custom bundles
    );
    
    // ... require dev bundles ...
    
    return $bundles;
}
```

#### 3. Create your own Menu (manual version)

Use service **`vegan.menu.builder`** in Symfony2 container and build custom Menu!

```php
 $builder = $this->container->get('vegan.menu.builder');
 // $builder->enableCache();     // you can turn on menu caching (very powerfull tool if you load menu from database)
 // $builder->disableCache();    // of course you can turn off menu caching
 
 // $defaultMenuOptions = $builder->getDefaultMenuOptions();   // you can dump whole menu options and you will see what is possible to change in Menu
 
 $builder
    ->createMenu($menuAnchor = 'main-menu',             // menu `anchor` is unique key whereby we can grab the menu in the template (irrespective of language translation)
                 $defaultRouteName = '_my_route_name',  // default route name (will be used when we'll not specify MenuItem option `route_name`)
                 $defaultLocale = 'en_US',              // locale is necessary for caching menus
                 $optionalMenuOptions = array()         // will be merged with default Menu options (as we dumped from $defaultMenuOptions)
    )
    ->createItem('item-1', array(           // first required parameter is `anchor` = it's simply the unique key, whereby you can grab that MenuItem and manipulate with (very useful because you can grab any translation of MenuItem by this anchor) 
        'name' => 'My root menu item 1',    // option `name` will be displayed in frontend
        'slug' => 'my-root-menu-item-1',    // option `slug` can be unique URI identification key
        'permalink_generate' => true,       // option `permalink_generate` will auto generate `permalink` (it is unique URI identificator with whole menu tree parents of this item)
    ))
    ->createItem('item-2', array(
        'name' => 'My menu item number 2!',
        'route_name' => '_my_route',    // `route_name` from Symfony Router or from VeganDynamicRouter
        'slug_generate' => true,        // `slug` is menu item URI identifier
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
 
 return $this->render(':your-custom-template.html.twig', array(
    'mainMenu' => $mainMenu,
 ));
 
```
#### 4. Render menu with macro

We have a variable mainMenu inside template, so at first we will render it by default macro from VeganMenuBundle:

```
{% if mainMenu is defined %}
    {% import 'VeganMenuBundle:macros.html.twig' as macros %}
    {{ macros.vegan_menu_render(mainMenu, true) }}
{% endif %}
```

#### 5. Write your own macro to render your custom Menu

Great, we rendered simple Menu by default macro. Now we will write own macro and render Menu with more features!

Create file macros.html.twig in your template directory
```
{% macro custom_menu_render(menu, displayChildren) %}
    {% set menuItems = (menu.items.children is defined) ? menu.items.children : menu %} {# don't change this line #}
    {% set displayChildren = (displayChildren is not same as(false)) ? true : false %}  {# and this line #}

    {% if menuItems|length > 0 %}
        <ul class="my-menu-list{% if menu.active is defined and menu.active %} active{% endif %}">
            {% for item in menuItems %}
                <li class="my-item-class{% if item.active is defined and item.active %} active{% endif %}">
                    <a href="{{ item.uri }}"{% if item.special.linkClass is defined %} class="{{ item.special.linkClass }}"{% endif %}>
                        {% if item.special.linkIcon is defined %}<i class="{{ item.special.linkIcon }}"></i>{% endif %} {# we can set MenuItem own custom special attributes, by this: $menuItem->special->add('linkIcon', 'fa fa-user'); #}
                        {{ item.name }}
                    </a>
                    {% if displayChildren %}
                        {% if item.children is defined %}
                            {{ _self.custom_menu_render(item.children, displayChildren) }}
                        {% endif %}
                    {% endif %}
                </li>
            {% endfor %}
        </ul>
    {% endif %}
{% endmacro %}
```
and next you can render your menu with your custom macro
```
{% import 'YourBundle:macros.html.twig' as macros %}
{{ macros.custom_menu_render(mainMenu, true) }}
```
And we did it :-)

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

##### 1. Install database structure

You can dump SQL in console command:
```
php app/console doctrine:schema:update --dump-sql
```

Or you can force update your database schema:
```
php app/console doctrine:schema:update --force
```
##### 2. configure EntityManager for use inside VeganMenuBundle

```
# /app/config/config.yml

```

```php
$loadMenuAnchors = array('main-menu', 'left-menu', 'footer-menu');
/*
 * if you want to load root node for main-menu, we can use following feature:
 * $loadRootNodes = array('main-menu' => 'root-menu-item-anchor');
 */
$builder = $this->container->get('vegan.menu.dynamic.builder');
```

