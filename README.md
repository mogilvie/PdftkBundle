# Mediavince Pdftk Bundle

A Symfony2 bundle to fill interactive PDF document forms

https://www.pdflabs.com/tools/pdftk-the-pdf-toolkit/

## Installation on Ubuntu

```
$ sudo apt-get install pdftk
```

## Activation

-> Update AppKernel.php as follows

```php
<?php
    public function registerBundles()
    {
        $bundles = array(
            //...
            new Mediavince\Bundle\MediavincePdftkBundle(),
        );
        return $bundles;
    }
```

## Implementation of the service %pdftk% parameter

To ensure the parameter pdftk is well integrated, update your parameters as such (example with YML in parameters.yml)

```yaml
    pdftk: {}
```

or by indicating possible options
    
```yaml
    pdftk:
        pdf_boolean_yes: "Oui"  # edit in your parameters.php for other locales translation: e.g Yes
        cmds:
            fill_form: "pdftk %s fill_form - output - flatten"  # here we add 'flatten' option to disable interactivity
            generate_fdf: "pdftk %s generate_fdf output -"
            dump_data_fields: "pdftk %s dump_data_fields output -"
```

 or in parameters.php

```php
<?php
    $parameters = array(
        'pdftk' => array(
            'pdf_boolean_yes' => 'Oui',
            'cmds' => array(
                'fill_form' => 'pdftk %s fill_form - output - flatten',
                'generate_fdf' => 'pdftk %s generate_fdf output -',
                'dump_data_fields' => 'pdftk %s dump_data_fields output -',
            ),
        ),
    );
```

Here is a way to implement it in a service (injection in services.yml for a ficticious manager for documents)

```yaml
    # example to implement in your own services.yml
        app.manager.document:
            class: AppBundle\Manager\DocumentManager
            arguments:
                - "@pdftk.managers.pdftk"
```

## Tests

Launch tests with PhpUnit

```
$ phpunit -c app src/MediavincePdftkBundle
```

This will output a PDF filled with utf8 fields to cache/test dir, furthermore should errors occur, a log file will be left behind at the same location.

## @todo implement tests to check other options and multi page
