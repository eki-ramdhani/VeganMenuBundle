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
