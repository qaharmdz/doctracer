DocTracer
==================

DocTracer helps you to create meaningful API documentation reports. 
Reflecting PHP class information and show markdown formatted documentation on one page.


Installation
------------

```bash
composer require qaharmdz/doctracer
```

Usage
-----

Create the DocTracer instance with a base directory.

```php
$tracer  = new \Mdz\DocTracer($baseDir = './');
```

Inspect all PHP codes inside target directory (relative to the `$baseDir`).

```php
$tracer->inspect('./Library/');
$tracer->inspect('./../vendor/');
```

Get the reports data.

```php
$reports = $tracer->getReports();
var_dump($reports);
```

Output the HTML report.

```php
echo $output = $tracer->render([
    '{title}'   => 'DocTracer',
    '{tagline}' => 'PHP ReflectionClass and API documentation',
]);

// and save it to file
file_put_contents('example-api.html', $output);
```

> For more examples review the scripts in the [`/examples`](/examples) folder.
