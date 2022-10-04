<?php

declare(strict_types=1);

namespace Mdz;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use Michelf\MarkdownExtra;

/**
 * Analyze PHP class and docBlock to generate API Documentation.
 *
 * DocTracer helps you to create meaningful API documentation reports.
 * Reflecting PHP class information and show markdown formatted documentation on one page.
 *
 * How to use:
 *
 * ```php
 * $tracer = new \Mdz\DocTracer($baseDir);
 * $tracer->inspect($targetDir);
 * echo $tracer->render();
 * ```
 * @link      [DocTracer](https://github.com/qaharmdz/doctracer)
 *
 * @author    Mudzakkir <hello@mdzstack.com>
 * @copyright Copyright (c) 2021 Mudzakkir
 * @license   [https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT)
 */
class DocTracer
{
    /**
     * @var string
     */
    const VERSION = '1.0.2';

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
     * @param string $baseDir
     */
    public function __construct(string $baseDir = './')
    {
        $this->baseDir = rtrim($baseDir, '\\/') . DIRECTORY_SEPARATOR;
    }

    /**
     * Find, check and parse all PHP files.
     *
     * PHP ReflectionClass require autoloader or class must exist.
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

            if (class_exists($fqnClass)) {
                $refClass    = new \ReflectionClass($fqnClass);
                $parentClass = $refClass->getParentClass();

                $classData[$refClass->getNamespaceName()][$refClass->getShortName()] = [
                    'name'         => $refClass->getShortName(),
                    'fullname'     => $refClass->getName(),
                    'file'         => str_replace($this->baseDir, '', $refClass->getFileName()),
                    'modifier'     => \Reflection::getModifierNames($refClass->getModifiers()),
                    'is_trait'     => $refClass->isTrait(),
                    'is_interface' => $refClass->isInterface(),
                    'extend'       => ($parentClass ? $parentClass->getName() : ''),
                    'interfaces'   => $refClass->getInterfaceNames(),
                    'docblock'     => $this->parseDocBlock($refClass),
                    'constants'    => $this->getConstants($refClass, $refClass->getName()),
                    'properties'   => $this->getProperties($refClass, $refClass->getName()),
                    'methods'      => $this->getMethods($refClass, $refClass->getName()),
                ];
            }
        }

        $this->data = array_merge($this->data, $classData);

        return $this;
    }

    /**
     * Return reports information.
     *
     * @return array
     */
    public function getResults(): array
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

                $reports[$refProperty->getName()] = [
                    'name'     => $refProperty->getName(),
                    'modifier' => \Reflection::getModifierNames($refProperty->getModifiers()),
                    'type'     => $refProperty->hasType() ? $this->getTypeHint($refProperty->getType()) : '',
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
                    'return'     => $refMethod->hasReturnType() ? $this->getTypeHint($refMethod->getReturnType()) : '',
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
    protected function getMethodParams(\ReflectionMethod $refMethod): array
    {
        $params = [];

        foreach ($refMethod->getParameters() as $refParam) {
            $value = 'n/a';
            if ($refParam->isDefaultValueAvailable()) {
                $value = $refParam->getDefaultValueConstantName() ?: $refParam->getDefaultValue();
            }

            $params[$refParam->getName()] = [
                'name'    => $refParam->getName(),
                'type'    => $refParam->hasType() ? $this->getTypeHint($refParam->getType()) : '',
                'default' => $this->getValueType($value),
            ];
        }

        return $params;
    }

    /**
     * Get the type hint
     *
     * @param  \ReflectionType $refType
     *
     * @return string
     */
    protected function getTypeHint(\ReflectionType $refType): string
    {
        $type = '';

        if ($refType->allowsNull()) {
            $type .= '?';
        }

        if ($refType instanceof \ReflectionNamedType) {
            $type .= $refType->getName();
        } elseif ($refType instanceof \ReflectionUnionType) {
            $type .= implode('|', $refType->getTypes());
        }

        return $type;
    }

    /**
     * Check the type of reflection $value to printable value.
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
     * @param  \Reflector $ref Has the `getDocComment()` method
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
            if ($docTags && !$docTags instanceof InvalidTag) {
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
            }

            $tags[$docTags->getName()][$i]['render'] = trim($docTags->render());

            $i++;
        }

        return [
            'summary'     => $docBlock->getSummary(),
            'description' => $docBlock->getDescription()->render(),
            'tags'        => $tags,
        ];
    }

    /**
     * Render in HTML page.
     * Available setting:
     *
     * - `title`      Page heading (h1) and meta title
     * - `tagline`    Page heading tagline
     * - `footer`     Footer information. Default: `{title} - {tagline}`
     * - `theme`      Report theme. Options: `default`, `darkmoon`
     * - `css`        Customize the report style
     * - `template`   Custom template path
     *
     * @param  array  $setting
     *
     * @return string
     */
    public function render(array $params = []): string
    {
        $data = [];
        $data['setting'] = array_merge([
            'title'   => 'DocTracer',
            'tagline' => 'PHP ReflectionClass and API documentation',
            'footer'  => '',
            'theme'   => 'default',
            'css'     => '',
            'template' => __DIR__ . DIRECTORY_SEPARATOR . 'reports.html',
        ], $params);

        $data['version']   = static::VERSION;
        $data['created']   = gmdate('c');

        extract($data, EXTR_SKIP);
        ob_start();
        include($data['setting']['template']);

        return ob_get_clean();
    }

    /**
     * Standarize the documentation HTML format.
     *
     * The class, constant, property and method share the same HTML output.
     *
     * @param  array  $docBlock
     *
     * @return string
     */
    protected function formatDocBlock(array $docBlock): string
    {
        $docs = '';

        if ($docBlock['summary'] || $docBlock['description']) {
            $docs .= '<div class="dt-doc-description">';
            $docs .= $this->markdown($docBlock['summary']);
            $docs .= $this->markdown($docBlock['description']);
            $docs .= '</div>';
        }

        if ($docBlock['tags']) {
            foreach ($docBlock['tags'] as $name => $tags) {
                $docs .= '<table class="dt-doc-tags-table dt-tags-table-' . $name . '">';

                foreach ($tags as $tag) {
                    if ($name === 'param') {
                        if (isset($tag['name'])) {
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                            $docs .= '<td class="dt-doc-tag-type">' . $this->wordbreak($tag['type']) . '</td>';
                            $docs .= '<td class="dt-doc-tag-variable">' . $tag['variable'] . '</td>';
                            $docs .= '<td class="dt-doc-tag-description">' . $this->markdown($tag['description']) . '</td>';
                            $docs .= '</tr>';
                        } else {
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-render" colspan="4">' . $this->markdown($tag['render']) . '</td>';
                            $docs .= '</tr>';
                        }
                    } elseif (in_array($name, ['var', 'return', 'throws'])) {
                        if (isset($tag['name'])) {
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                            $docs .= '<td class="dt-doc-tag-type">' . $this->wordbreak($tag['type']) . '</td>';
                            $docs .= '<td class="dt-doc-tag-description">' . $this->markdown($tag['description']) . '</td>';
                            $docs .= '</tr>';
                        } else {
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-render" colspan="3">' . $this->markdown($tag['render']) . '</td>';
                            $docs .= '</tr>';
                        }
                    } else {
                        if (isset($tag['name'])) {
                            $render = str_replace('@' . $tag['name'], '', $tag['render']);
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-name">' . $tag['name'] . '</td>';
                            $docs .= '<td class="dt-doc-tag-render">' . $this->markdown($render) . '</td>';
                            $docs .= '</tr>';
                        } else {
                            $docs .= '<tr>';
                            $docs .= '<td class="dt-doc-tag-render" colspan="2">' . $this->markdown($tag['render']) . '</td>';
                            $docs .= '</tr>';
                        }
                    }
                }
                $docs .= '</table>';
            }
        }

        return $docs;
    }

    /**
     * Pretify documentation with Markdown.
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
     * Break part namespace and types with `&#8203;` zero-width space.
     *
     * @param  string $content
     *
     * @return string
     */
    protected function wordBreak(string $content): string
    {
        return trim(str_replace(['|', '\\'], ['&#8203;|', '&#8203;\\'], $content), '&#8203;');
    }
}
