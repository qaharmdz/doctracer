DocTracer
==================

DocTracer helps you to create meaningful API documentation reports. 
Reflecting PHP class information and show markdown formatted documentation on one page.


Installation
------------

```bash
composer require qaharmdz/doctracer --dev
```

Usage
-----

Create the DocTracer instance.

```php
$tracer = new \Mdz\DocTracer($baseDirectory = './');
```

Inspect all PHP codes inside target directory (relative to the `$baseDir`).

```php
$tracer->inspect('./../src');
$tracer->inspect('./Library/');
```

### Inspection Results

Get the inspection results data.

```php
$reports = $tracer->getResults();
var_dump($reports);
```

### HTML Report

Output the HTML report.

```php
echo $output = $tracer->render([
    'title'   => 'DocTracer Test',
    'tagline' => 'PHP ReflectionClass and API documentation',
    'theme'   => 'darkmoon',
]);
```

Save the output to file.

```php
file_put_contents('example-api.html', $output);
```


**Render options**

- `title`      Page heading (h1) and meta title
- `tagline`    Page heading tagline
- `footer`     Footer information. _Default_: `{title} - {tagline}`
- `theme`      Report theme. _Options_: `default`, `darkmoon`
- `css`        Customize the report style
- `template`   Custom template path

> For more examples review the scripts in the [`/examples`](/examples) folder.
