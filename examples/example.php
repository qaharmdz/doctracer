<?php

include './../vendor/autoload.php';

// Required for example
include './Library/ParentClass.php';
include './Library/Acme.php';

$tracer = new \Mdz\DocTracer($baseDirectory = './');

//=== Check directories, relative to base directory
$tracer->inspect('./../src');
$tracer->inspect('./Library/');
// $tracer->inspect('./../vendor/webmozart/assert/src');

//=== Print output
echo $output = $tracer->render([
    'title'   => 'DocTracer',
    'tagline' => 'PHP ReflectionClass and API Documentation Generator',
    'theme'   => 'darkmoon',
]);

// Save to file
// file_put_contents('example-api.html', $output);

//=== Dump all results
// var_dump($tracer->getResults());
