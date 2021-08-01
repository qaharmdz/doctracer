<?php

declare(strict_types=1);

namespace Example;

/**
 * This is the title/ summary for a DocBlock.
 *
 * This is the description for a DocBlock. This text may contain
 * multiple lines and even some _markdown_.
 *
 * Unordered list:
 *
 * - **One** before two
 * - **Two** after one
 * - `&#8203;` entities (U+200B)
 *
 * The section after the description contains the tags; which provide
 * structured meta-data concerning the given element.
 *
 * @link      [phpDocumentor tags](https://docs.phpdoc.org/guide/references/phpdoc/tags/)
 * @link      [Markdown basic](https://daringfireball.net/projects/markdown/basics)
 * @link      [Markdown extra](https://michelf.ca/projects/php-markdown/extra/)
 *
 * @not-exist A non-existant PHPDoc tag
 *
 * @author    Original Author <alpha@example.com>
 * @author    Another Author <delta@example.com>
 * @copyright 2021 (c) Acme Corporation
 * @license   [https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT)
 *
 * @return    [type]    Show invalid tags as is
 */
class Acme extends ParentClass implements \Countable
{
    const VERSION = '0.1.0-b';
    const CHARSET = 'UTF-8';

    /**
     * Options: 7bit, 8bit
     */
    protected const ENCODING_BIT = '8bit';

    public int $priority = 0;
    protected static array $data = [101, ['number' => '101']];
    private $neighbour;

    public function __construct(Neighbour $nextDoor)
    {
        $this->neighbour = $nextDoor;
    }

    /**
     * Say hello to your neighbour.
     *
     * Bring some food if necessary. It's one of a good excuse to start a conversation.
     *
     * @param  string $name   No harm to ask if you don't know
     *
     * @return string         Remember, smile.. :)
     */
    public function hello(string $name): string
    {
        return sprintf('Greetings %s', $name);
    }

    /**
     * DocBLock title summary
     *
     * Use markdown to pretify the information like `inline code`, **bold**, _italic_, etc.
     * Or show code in a block:
     *
     * ```php
     * $tracer = new \Mdz\DocTracer($baseDir);
     * $tracer->inspect($targetDir);
     * echo $tracer->render();
     * ```
     *
     * @see https://en.wikipedia.org/wiki/Docblock
     *
     * @param string|null $unset
     * @param Example\NextClass $parent
     * @param int       $int0       It's a zero
     * @param int       $int1
     * @param float     $float
     * @param bool      $true       Not a false
     * @param bool      $false      Not necessary true
     * @param string    $string
     * @param string    $number
     * @param array     $array
     * @param null      $null
     * @param array|int|string      $mixed
     *
     * @throws  \InvalidArgumentException
     *
     * @return string|null
     */
    public function methodParams(
        $unset,
        NextClass $next,
        int $int0 = 0,
        int $int1 = 1,
        float $float = 0.9,
        bool $true = true,
        bool $false = false,
        string $string = 'acme',
        string $number = '123',
        array $array = ['foo', 'bar'],
        $null = null,
        $mixed = ''
    ): ?string
    {
        return 'awesome';
    }

    public static function checkModifier(int $number): int
    {
        return $number++;
    }
}
