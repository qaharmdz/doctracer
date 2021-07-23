<?php

declare(strict_types=1);

namespace Mdz;

use phpDocumentor\Reflection\DocBlockFactory;
use Michelf\MarkdownExtra;

/**
 * Analyzing PHP class information and docBlock to generate API Documentation.
 *
 * @author    Mudzakkir
 * @copyright Copyright (c) 2021 Mudzakkir
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/qaharmdz/doctracer
 */
class DocTracer
{
    /**
     * The DocTracer Version number.
     *
     * @var    string
     */
    const VERSION = '0.7.1';

    /**
     * Base directory path.
     *
     * @var string
     */
    protected $baseDir = '';

    /**
     * Holds all reports data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Constructor.
     *
     * @param string $baseDir
     */
    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '\\/') . DIRECTORY_SEPARATOR;
    }

    /**
     * Find, check and parse all PHP files.
     *
     * In order to get Class reflection, autoloader must be available or class already included.
     *
     * @param  string $targetDir Directory to check
     * @param  array  $exclude   Exclude directory
     *
     * @return \Mdz\DocTracer
     */
    public function inspect(string $targetDir, array $exclude = []): DocTracer
    {
        $classData  = [];
        $targetPath = realpath($this->baseDir . $targetDir);

        if (!file_exists($targetPath)) {
            throw new \InvalidArgumentException('DocTracer: Path "' . $targetPath . '" is not available!');
        }

        $files = $this->phpFinder($targetPath, $exclude);

        foreach ($files as $fileInfo) {
            $content     = file_get_contents($fileInfo->getRealPath());
            $fqnClass    = $this->parseNamespace($content);
            $refClass    = new \ReflectionClass($fqnClass);
            $parentClass = $refClass->getParentClass();

            $classData[$refClass->getNamespaceName()][$refClass->getShortName()] = [
                'name'       => $refClass->getShortName(),
                'fullname'   => $refClass->getName(),
                'file'       => str_replace($this->baseDir, '', $refClass->getFileName()),
                'extend'     => ($parentClass ? $parentClass->getName() : ''),
                'interfaces' => $refClass->getInterfaceNames(),
                'docblock'   => $this->parseDocBlock($refClass),
                'constants'  => $this->getConstants($refClass, $refClass->getName()),
                'properties' => $this->getProperties($refClass, $refClass->getName()),
                'methods'    => $this->getMethods($refClass, $refClass->getName()),
            ];
        }

        $this->data = array_merge($this->data, $classData);

        return $this;
    }

    /**
     * Return reports information
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Find PHP files inside directory recursively.
     *
     * @param  string $path
     * @param  array  $exclude
     *
     * @return \Iterator of \SplFileInfo
     */
    protected function phpFinder(string $path, array $exclude = []): \Iterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(
                    $path,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                function ($file, $key, $iterator) use ($exclude) {
                    if ($iterator->hasChildren() && !in_array($file->getFilename(), $exclude)) {
                        return true;
                    }

                    return $file->isFile() && $file->getExtension() === 'php';
                }
            )
        );
    }

    /**
     * Get fully qualified namespace from content.
     *
     * @param  string $content
     *
     * @return string Fully qualified namespace
     */
    protected function parseNamespace(string $content): string
    {
        $items     = [];
        $tokens    = token_get_all($content);
        $numTokens = count($tokens);

        for ($i = 0; $i < $numTokens; $i++) {
            if (in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $i += 2; // Skip "namespace" word and whitespace
                $_namespace = '';

                while (isset($tokens[$i]) && is_array($tokens[$i])) {
                    $_namespace .= trim($tokens[$i++][1]);
                }
                $items[] = $_namespace;
            }

            if (in_array($tokens[$i][0], [T_CLASS, T_TRAIT, T_INTERFACE])) {
                $items[] = $tokens[$i + 2][1];

                break;
            }
        }

        return implode('\\', $items);
    }

    /**
     * Get class constants information.
     *
     * @param  \ReflectionClass $refClass
     * @param  string           $fqnClass
     *
     * @return array
     */
    protected function getConstants(\ReflectionClass $refClass, string $fqnClass): array
    {
        $reports = [];

        foreach ($refClass->getReflectionConstants() as $refConstant) {
            if ($refConstant->getDeclaringClass()->getName() === $fqnClass) {
                $constant = [
                    'name'     => $refConstant->getName(),
                    'modifier' => \Reflection::getModifierNames($refConstant->getModifiers()),
                    'value'    => $this->getValueType($refConstant->getValue()),
                    'docblock' => $this->parseDocBlock($refConstant),
                ];
                $reports[$refConstant->getName()] = $constant;
            }
        }

        return $reports;
    }

    /**
     * Get class properties information.
     *
     * @param  \ReflectionClass $refClass
     * @param  string           $fqnClass
     *
     * @return array
     */
    protected function getProperties(\ReflectionClass $refClass, string $fqnClass): array
    {
        $reports = [];
        
        foreach ($refClass->getProperties() as $refProperty) {
            if ($refProperty->getDeclaringClass()->getName() === $fqnClass) {
                $defaultProperties = $refClass->getDefaultProperties();

                $value = $defaultProperties[$refProperty->getName()] ?? 'n/a';
                if (PHP_VERSION_ID >= 80000) {
                    $value = $refProperty->getDefaultValue();
                    if ($value === null && $refProperty->hasDefaultValue()) {
                        $value = 'null';
                    }
                }

                $type = '';
                if (PHP_VERSION_ID >= 70400 && $refProperty->getType()) {
                    $type = $refProperty->getType()->getName();
                }

                $reports[$refProperty->getName()] = [
                    'name'     => $refProperty->getName(),
                    'modifier' => \Reflection::getModifierNames($refProperty->getModifiers()),
                    'type'     => $type,
                    'value'    => $this->getValueType($value),
                    'docblock' => $this->parseDocBlock($refProperty),
                ];
            }
        }

        return $reports;
    }

    /**
     * Get class methods information.
     *
     * @param  \ReflectionClass $refClass
     * @param  string           $fqnClass
     *
     * @return array
     */
    protected function getMethods(\ReflectionClass $refClass, string $fqnClass): array
    {
        $reports = [];

        foreach ($refClass->getMethods() as $refMethod) {
            if ($refMethod->getDeclaringClass()->getName() === $fqnClass) {
                $reports[$refMethod->getName()] = [
                    'name'       => $refMethod->getName(),
                    'line'       => $refMethod->getStartLine(),
                    'modifier'   => \Reflection::getModifierNames($refMethod->getModifiers()),
                    'params'     => $this->getMethodParams($refMethod),
                    'return'     => $refMethod->getReturnType() ? $refMethod->getReturnType()->getName() : '',
                    'docblock'   => $this->parseDocBlock($refMethod),
                ];
            }
        }

        return $reports;
    }

    /**
     * Get method parameters information.
     *
     * @param  \ReflectionMethod $refMethod
     *
     * @return array
     */
    protected function getMethodParams(\ReflectionMethod $refMethod)
    {
        $params = [];

        foreach ($refMethod->getParameters() as $refParam) {
            $value = 'n/a';
            if ($refParam->isDefaultValueAvailable()) {
                $value = $refParam->getDefaultValueConstantName() ?: $refParam->getDefaultValue();
            }

            $params[$refParam->getName()] = [
                'name'    => $refParam->getName(),
                'type'    => ($refParam->hasType() ? $refParam->getType()->getName() : ''),
                'default' => $this->getValueType($value),
            ];
        }

        return $params;
    }


    /**
     * Check the type of $value, and change to printable value.
     *
     * Word "n/a" to differentiate between the assigned default value "null"
     * and the null returned by reflection when a default value is not assigned.
     *
     * @param  mixed|null  $value
     *
     * @return string|int
     */
    protected function getValueType($value)
    {
        if ($value === 'n/a') {
            return '';
        }

        $print = $value;

        switch (gettype($value)) {
            case 'object':
            case 'array':
                $print = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_NUMERIC_CHECK
                );
                $print = str_replace(',', ', ', $print);
                break;
            case 'boolean':
                $print = $value ? 'true' : 'false';
                break;
            case 'NULL':
                $print = 'null';
                break;
            case 'string':
                $print = "'" . $value . "'";
                break;
            case 'double':
            case 'integer':
            case 'unknown type':
            default:
                $print = (string)$value;
                break;
        }

        return $print;
    }

    /**
     * Get DocBLock informations.
     *
     * @param  \Reflector $ref Has the getDocComment() method
     *
     * @return array
     */
    protected function parseDocBlock(\Reflector $ref): array
    {
        $docs = [];
        $tags = [];
        
        if (!$docComment = $ref->getDocComment()) {
            return $docs;
        }

        $factory  = DocBlockFactory::createInstance();
        $docBlock = $factory->create($docComment);

        $i = 0;
        foreach ($docBlock->getTags() as $docTags) {
            if ($docTags->getName() === 'param') {
                $tags[$docTags->getName()][$i] = [
                    'name'        => '@' . $docTags->getName(),
                    'type'        => (string)$docTags->getType(),
                    'variable'    => '$' . $docTags->getVariableName(),
                    'description' => $docTags->getDescription()->render(),
                ];
            } elseif (in_array($docTags->getName(), ['var', 'return', 'throws'])) {
                $tags[$docTags->getName()][$i] = [
                    'name'        => '@' . $docTags->getName(),
                    'type'        => (string)$docTags->getType(),
                    'description' => $docTags->getDescription()->render(),
                ];
            } else {
                $tags[$docTags->getName()][$i] = [
                    'name'   => '@' . $docTags->getName(),
                ];
            }

            $tags[$docTags->getName()][$i]['render'] = trim(str_replace('@' . $docTags->getName(), '', $docTags->render()));

            $i++;
        }

        return [
            'summary'     => $docBlock->getSummary(),
            'description' => $docBlock->getDescription()->render(),
            'tags'        => $tags,
        ];
    }

    /**
     * Render in complete HTML page.
     *
     * Available settind:
     * - {title}        Page heading (h1) and meta title
     * - {tagline}      Page heading tagline
     * - {description}  Page meta desctiption
     * - {footer}       Footer information
     * - {customstyle}  Customize the report style
     *
     * @param  array  $setting
     *
     * @return string
     */
    public function render(array $setting = []): string
    {
        $vars = array_merge([
            '{template}'    => $this->getTemplate(),
            '{footer}'      => '{title} - {tagline}',
            '{theme_style}' => '',
            '{theme}'       => 'default',
            '{tagline}'     => 'PHP ReflectionClass and API documentation',
            '{title}'       => 'DocTracer',
        ], $setting);

        $vars['{version}']   = static::VERSION;
        $vars['{created}']   = gmdate('c');
        $vars['{styles}']    = $this->getStyle();
        $vars['{datatable}'] = $this->getDataTable();

        return str_replace(array_keys($vars), $vars, $vars['{template}']);
    }

    /**
     * The reports HTML template.
     *
     * @return string
     */
    protected function getTemplate(): string
    {
        return '<!DOCTYPE html>
<html dir="ltr" lang="en" class="docTracer dt-th-{theme}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title} Documentation</title>

    <style>
    {styles}
    {theme_style}
    </style>
</head>

<body>
<div id="docTracer" class="dt-wrapper">
    <h1 class="dt-head">{title} <span class="dt-tagline">{tagline}</span></h1>

    {datatable}

    <div class="dt-footer dt-gray">
        {footer}<br>
        Generated by 
        <a href="https://github.com/qaharmdz/DocTracer" title="DocTracer - Readable API documentation for PHP.">DocTracer v{version}</a>
        at <span class="dt-created">{created}</span>
    </div>
    <a class="dt-toTop" href="#docTracer">^Top</a>
</div>
</body>
</html>';
    }

    /**
     * Generate reports HTML table.
     *
     * @return string
     */
    protected function getDataTable(): string
    {
        $html = '<table class="dt-table">
        <thead>
            <tr>
                <th class="dt-col-namespace">Namespace</th>
                <th class="dt-col-class">Class</th>
                <th class="dt-col-member">Constant | Property | Method</th>
                <th class="dt-col-docs">Documentation</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($this->getData() as $namespace => $classes) {
            $tableTr = '';
            $_iRowspanNamespace = 0;

            $tdNamespace = '
            <td rowspan="{rowspan-namespace}">
                <span id="' . $namespace . '" class="dt-anchor dt-anchor-namespace"></span>
                <div class="dt-namespace">' . $namespace . '</div>
            </td>';

            foreach ($classes as $class) {
                if (!$class['constants'] && !$class['properties'] && !$class['methods']) {
                    continue;
                }

                $_iRowspanClass = 0;

                $tdClass = '
                <td rowspan="{rowspan-class}">
                    <span id="' . $class['fullname'] . '" class="dt-anchor dt-anchor-class"></span>
                    <div class="dt-class">
                        <abbr title="' . $class['file'] . '">' . $class['name'] . '</abbr>
                    </div>'
                    . (
                        $class['extend']
                            ? '<div class="dt-class-extend">
                                <span class="dt-class-modifier">extends</span> ' . $this->wordBreak($class['extend'])
                            . '</div>'
                            : ''
                    ) . (
                        $class['interfaces']
                            ? '<div class="dt-class-interface">
                                <span class="dt-class-modifier">implements</span> ' . $this->wordBreak(implode(', ', $class['interfaces']))
                            . '</div>'
                            : ''
                    ) . '
                </td>';

                if ($class['docblock'] && $_iRowspanClass === 0) {
                    $_iRowspanNamespace++;
                    $_iRowspanClass++;

                    $tableTr .= '
                    <tr class="dt-class-divider">'
                        . $tdNamespace
                        . $tdClass
                        . '<td colspan="2" class="dt-docblock striped">'
                        . ($class['docblock'] ? $this->formatDocBlock($class['docblock']) : '')
                        . '</td>
                    </tr>';
                    
                    $tdNamespace = '';
                    $tdClass = '';
                }

                foreach ($class['constants'] as $constant) {
                    if (!$tdNamespace && !$tdClass) {
                        $tableTr .= '<tr>';
                    } elseif ($_iRowspanNamespace == 0 && $tdNamespace) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdNamespace;
                        $tdNamespace = '';
                    } elseif ($_iRowspanClass == 0 && $tdClass) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdClass;
                        $tdClass = '';
                    }

                    // Column constant
                    $tableTr .= '
                    <td class="dt-constant striped"' . (!$constant['docblock'] ? 'colspan="2"' : '') . '>
                        <span id="' . $class['fullname'] . '\\' . $constant['name'] . '" class="dt-anchor dt-anchor-constant"></span>
                        <span class="dt-constant-modifier">' . implode(' ', $constant['modifier']) . '</span>
                        <span class="dt-constant-keyword">const</span>
                        <span class="dt-constant-name-value">
                            <span class="dt-constant-name">' . $constant['name'] . '</span>'
                            . ($constant['value'] !== ''
                                ? ' = <span class="dt-constant-value">'
                                    . $constant['value']
                                    . '<span class="dt-gray">;</span>
                                    </span>'
                                : '')
                    . '</td>';
                    
                    // Column documentation
                    if ($constant['docblock']) {
                        $tableTr .= '<td class="dt-docblock striped">' . $this->formatDocBlock($constant['docblock']) . '</td>';
                    }

                    $_iRowspanClass++;
                    $_iRowspanNamespace++;
                }

                foreach ($class['properties'] as $property) {
                    if (!$tdNamespace && !$tdClass) {
                        $tableTr .= '<tr>';
                    } elseif ($_iRowspanNamespace == 0 && $tdNamespace) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdNamespace;
                        $tdNamespace = '';
                    } elseif ($_iRowspanClass == 0 && $tdClass) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdClass;
                        $tdClass = '';
                    }

                    // Column property
                    $tableTr .= '
                    <td class="dt-property striped"' . (!$property['docblock'] ? 'colspan="2"' : '') . '>
                        <span id="' . $class['fullname'] . '\\' . $property['name'] . '" class="dt-anchor dt-anchor-property"></span>
                        <span class="dt-property-modifier">' . implode(' ', $property['modifier']) . '</span>
                        <span class="dt-property-type">' . $property['type'] . '</span>
                        <span class="dt-property-name-value">
                            <span class="dt-property-name">$' . $property['name'] . '</span>'
                            . ($property['value'] !== '' ? ' = <span class="dt-property-value">' . $property['value'] . '</span>' : '')
                        . '<span class="dt-gray">;</span>
                        <span>
                    </td>';

                    // Column documentation
                    if ($property['docblock']) {
                        $tableTr .= '<td class="dt-docblock striped">' . $this->formatDocBlock($property['docblock']) . '</td>';
                    }

                    $_iRowspanClass++;
                    $_iRowspanNamespace++;
                }

                foreach ($class['methods'] as $method) {
                    if (!$tdNamespace && !$tdClass) {
                        $tableTr .= '<tr>';
                    } elseif ($_iRowspanNamespace == 0 && $tdNamespace) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdNamespace;
                        $tdNamespace = '';
                    } elseif ($_iRowspanClass == 0 && $tdClass) {
                        $tableTr .= '<tr class="dt-class-divider">';
                        $tableTr .= $tdClass;
                        $tdClass = '';
                    }

                    // Column method
                    $tableTr .= '
                    <td class="dt-method striped"' . (!$method['docblock'] ? 'colspan="2"' : '') . '>
                        <span id="' . $class['fullname'] . '\\' . $method['name'] . '" class="dt-anchor dt-anchor-method"></span>
                        <span class="dt-method-modifier">' . implode(' ', $method['modifier']) . '</span>
                        <span class="dt-method-name">' . $method['name'] . '</span>(';
                    
                    $params = [];
                    foreach ($method['params'] as $param) {
                        $params[] = '<div class="dt-param-row">'
                            . ($param['type'] ? '<span class="dt-param-type">' . $this->wordBreak($param['type']) . '</span> ' : '')
                            . '<span class="dt-param-name">$' . $param['name'] . '</span>'
                            . ($param['default'] !== '' ? '<span class="dt-param-default"> = ' . $param['default'] . '</span>' : '');
                    }
                    if ($params) {
                        $tableTr .= '
                        <div class="dt-param-block">'
                        . implode('<span class="dt-gray">,</span></div>', $params) . '</div>'
                        . '</div>';
                    }
                    $tableTr .= ')'
                        . ($method['return'] ? '<span class="dt-method-return">: ' . $method['return'] . '</span>' : '')
                    . '</td>';

                    // Column documentation
                    if ($method['docblock']) {
                        $tableTr .= '<td class="dt-docblock striped">' . $this->formatDocBlock($method['docblock']) . '</td>';
                    }
                    $tableTr .= '</tr>';

                    $_iRowspanClass++;
                    $_iRowspanNamespace++;
                }
                $tableTr = str_replace('{rowspan-class}', $_iRowspanClass, $tableTr);
            }

            $html .= str_replace('{rowspan-namespace}', $_iRowspanNamespace, $tableTr);
        }

        $html .= '</tbody>
        </table>';

        return $html;
    }

    /**
     * Standarize Dococumentation HTML format.
     *
     * The class, constant, property and method share the same HTML output.
     *
     * @param  array  $docBlock
     *
     * @return string
     */
    protected function formatDocBlock(array $docBlock): string
    {
        $docs = '<div class="dt-doc-summary">' . $this->markdown($docBlock['summary']) . '</div>';

        if ($docBlock['description']) {
            $docs .= '<div class="dt-doc-description">' . $this->markdown($docBlock['description']) . '</div>';
        }

        if ($docBlock['tags']) {
            foreach ($docBlock['tags'] as $name => $tags) {
                $docs .= '<table class="dt-doc-tags-table dt-tags-table-' . $name . '">';

                foreach ($tags as $tag) {
                    $tagTypes = '';
                    if (isset($tag['type'])) {
                        $tagTypes = explode('|', $tag['type']);
                        $tagTypes = '<span>' . implode('</span><span>|', $tagTypes) . '</span>';
                    }

                    if ($name === 'param') {
                        $docs .= '<tr>';
                        $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                        $docs .= '<td class="dt-doc-tag-type">' . $this->wordBreak($tagTypes) . '</td>';
                        $docs .= '<td class="dt-doc-tag-variable">' . $tag['variable'] . '</td>';
                        $docs .= '<td class="dt-doc-tag-description">' . $this->markdown($tag['description']) . '</td>';
                        $docs .= '</tr>';
                    } elseif (in_array($name, ['var', 'return', 'throws'])) {
                        $docs .= '<tr>';
                        $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                        $docs .= '<td class="dt-doc-tag-type">' . $this->wordBreak($tagTypes) . '</td>';
                        $docs .= '<td class="dt-doc-tag-description">' . $this->markdown($tag['description']) . '</td>';
                        $docs .= '</tr>';
                    } else {
                        $docs .= '<tr>';
                        $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                        $docs .= '<td class="dt-doc-tag-render">' . $this->markdown($tag['render']) . '</td>';
                        $docs .= '</tr>';
                    }
                }
                $docs .= '</table>';
            }
        }

        return $docs;
    }

    /**
     * Pretify documentation with Markdown
     *
     * @link   [Markdown basic](https://daringfireball.net/projects/markdown/basics)
     * @link   [Markdown extra](https://michelf.ca/projects/php-markdown/extra/)
     *
     * @param  string $content
     *
     * @return string
     */
    protected function markdown(string $content): string
    {
        return MarkdownExtra::defaultTransform($content);
    }

    /**
     * An attempt to break a long qualified namespace
     *
     * Our options is to use:
     * - `&#8203;` zero-width space (U+200B)
     * - `<wbr>` word break
     *
     * @param  string $words
     *
     * @return string
     */
    protected function wordBreak(string $words): string
    {
        return str_replace('\\', '&#8203;\\', $words);
    }

    /**
     * The reports default style.
     *
     * @return string
     */
    protected function getStyle(): string
    {
        return '
:root {
    --font-family-read: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --font-family-code: Consolas, Menlo, Monaco, Lucida Console, Liberation Mono, DejaVu Sans Mono, Bitstream Vera Sans Mono, Courier New, monospace, serif;
    --base-font-size: 13px;
    --base-color: #333;

    --th-color-name: #9c27b0;
    --th-color-modifier: #ff9800;
    --th-color-keyword: #b71c1c;
    --th-color-type: #0a75e1;

    --th-color-namespace: var(--th-color-name);
    --th-color-class-name: var(--th-color-name);
    --th-color-class-modifier: var(--th-color-modifier);
    --th-color-class-extend: var(--th-color-type);

    --th-color-constant-name: var(--th-color-name);
    --th-color-constant-keyword: var(--th-color-keyword);
    --th-color-constant-modifier: var(--th-color-modifier);

    --th-color-property-name: var(--th-color-name);
    --th-color-property-modifier: var(--th-color-modifier);
    --th-color-property-type: var(--th-color-type);

    --th-color-method-name: var(--th-color-name);
    --th-color-method-modifier: var(--th-color-modifier);
    --th-color-param-name: var(--th-color-name);
    --th-color-param-type: var(--th-color-type);

    --th-color-doc-tag-name: var(--th-color-name);
    --th-color-doc-tag-type: var(--th-color-type);
    --th-color-doc-tag-variable: var(--th-color-keyword);
}

html.dt-th-default {
    --th-color-namespace: #2e9900;
    --th-color-class-name: #112592;
    --th-color-param-name: var(--th-color-keyword);
    --th-color-doc-tag-name: #047a04;
}

html {
    font-family: var(--font-family-code);
    font-size: var(--base-font-size);
    line-height: 1.5rem;
    color: var(--base-color);
    box-sizing: border-box;
    scroll-behavior: smooth;
}
* {
    word-break: break-word;
}
*, *:before, *:after {
    box-sizing: inherit;
}
body {
    margin: 0;
    padding: 0;
    background: #fdfdfd;
}
p {
    margin: 0
}
a:active, a:hover {
    outline: 0
}
a {
    color: #1e87f0;
    text-decoration: none;
    cursor: pointer
}
a:hover {
    color: #0f6ecd;
    text-decoration: underline
}
:not(pre)>code {
    font-size: .9rem;
    color: #f53123;
    padding: 0 5px;
    background: rgba(0,0,0,.05);
    border-radius: 3px;
}

pre {
    font-size: .9rem;
    line-height: 1.4em;
    -moz-tab-size: 4;
    tab-size: 4;
    overflow: auto;
    padding: 6px 9px;
    border-left: 4px solid rgba(81, 61, 120, .5);
    background: rgba(0, 0, 0, .03);
}

.dt-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 2rem  4rem;
}
.dt-head {
    font-family: var(--font-family-read);
    font-size: 2.5rem;
    font-weight: 400;
    line-height: 1.2em;
    margin: 0 0 15px;
}
.dt-tagline {
    font-size: 1.65rem;
    line-height: 1.2em;
    color: #666;
    font-weight: 300;
    letter-spacing: -.5px;
}

.dt-table,
.dt-doc-tags-table {
    table-layout: fixed;
}
.dt-table {
    font-size: 1rem;
    border-collapse: collapse;
    border-spacing: 0;
    width: 100%;
    border: 1px solid #d8d8d8
}
.dt-table thead {
    position: sticky;
    top: -1px;
    background: #e2e3e4;
    z-index: 99;
}
.dt-table tr {
    position: relative;
}
.dt-table th {
    font-family: var(--font-family-read);
    font-weight: 500;
    color: #222;
    letter-spacing: .5px;
}
.dt-table th,
.dt-table td {
    padding: 6px 10px;
    vertical-align: top;
    border: 1px solid #d4d4d4;
    border-top: 1px solid #ddd;
    border-bottom: 0;
}
.dt-table tr:nth-of-type(even) td.striped {
    background: #f2f3f4;
}
.dt-table .dt-namespace,
.dt-table .dt-class {
    position: sticky;
    top: 35px;
    background: rgba(253, 253, 253, .9);
    margin: -6px -10px 6px;
    padding: 6px 10px;
}

.dt-col-namespace {
    width: 200px;
    min-width: 150px;
}
.dt-col-class {
    width: 180px;
    min-width: 130px;
}
.dt-col-member {
    width: 280px;
    min-width:200px;
}
.dt-col-docs {
    min-width:400px;
}

.dt-namespace {
    color: var(--th-color-namespace);
}
.dt-class {
    color: var(--th-color-class-name);
}
.dt-class-extend,
.dt-class-interface {
    color: var(--th-color-class-extend);
}
.dt-class-modifier {
    color: var(--th-color-class-modifier);
}

.dt-constant-name {
    color: var(--th-color-constant-name);
}
.dt-constant-name-value {
    display: inline-block;
}
.dt-constant-modifier{
    color: var(--th-color-constant-modifier);
}
.dt-constant-keyword {
    color: var(--th-color-constant-keyword)
}

.dt-property-name {
    color: var(--th-color-property-name);
}
.dt-property-modifier {
    color: var(--th-color-property-modifier);
}
.dt-property-type {
    color: var(--th-color-property-type);
}

.dt-method-name {
    color: var(--th-color-method-name);
}
.dt-method-modifier {
    color: var(--th-color-method-modifier);
}
.dt-method-return {
    font-style: italic;
}
.dt-param-block {
    margin-left: 12px;
    color: var(--base-color);
}
.dt-param-name {
    color: var(--th-color-param-name);
}
.dt-param-type {
    color: var(--th-color-param-type);
    font-style: italic;
}

.dt-method,
.dt-docblock {
    line-height: 1.4em;
}
.dt-class-divider > td {
    border-top: 5px solid #d4d4d4 !important;
}

.dt-docblock {
    font-size: .96rem;
    line-height: 1.3em;
    padding: 8px 10px !important;
}
.dt-doc-summary,
.dt-doc-description {
    margin-bottom: 8px;
}
.dt-doc-summary:last-child,
.dt-doc-description:last-child {
    margin: 0;
}
.dt-doc-na {
    color: #888;
}

.dt-doc-tags-table {
    width: 100%;
    border: 0;
    border-spacing: 0;
    border-collapse: collapse;
    margin-top: 5px;
    margin-bottom: 5px;
}
.dt-doc-tags-table:last-child {
    margin: 0;
}
.dt-doc-tags-table td {
    padding: 0;
    border: 0;
}
.dt-doc-tag-name {
    color: var(--th-color-doc-tag-name);
    width: 80px;
}
.dt-doc-tag-type {
    color: var(--th-color-doc-tag-type);
    width: 180px;
}
.dt-tags-table-param .dt-doc-tag-type {
    width: 85px;
}
.dt-doc-tag-type span {
    display: inline-block;
}
.dt-doc-tag-variable {
    color: var(--th-color-doc-tag-variable);
    width: 95px;
}
.dt-gray {
    color: #999;
}

.dt-footer {
    font-family: var(--font-family-read);
    font-size: .96rem;
    line-height: 1.4em;
    margin-top: 10px;
}
dt-footer a {
    color: #777;
    text-decoration: underline dotted;
}
dt-footer a:hover {
    color: #1d66d2;
    font-style: italic;
}

.dt-anchor {
    position: absolute;
    top: -32px;
}

.dt-toTop {
    position: fixed;
    right: 0;
    bottom: 30px;

    color: #666;
    background: #f4f4f4;
    text-decoration: none;
    line-height: 1.2em;
    padding: 3px 6px;
    border-bottom: 2px solid #999;
    border-radius: 10px 0 0 12px;
}
.dt-toTop:hover {
    color: #1d66d2;
    text-decoration: none;
    border-bottom: 1px solid #777;
}';
    }
}
