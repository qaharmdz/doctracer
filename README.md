DocTracer
==================

DocTracer helps you to create meaningful API documentation reports. 
Reflecting PHP class information and show markdown formatted documentation on one page.

![DocTracer preview](https://qaharmdz.github.io/doctracer/images/doctracer-theme-darkmoon-s.png)

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

### License (MIT)

*Copyright (c) 2021 Mudzakkir.*

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
