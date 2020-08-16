<?php
/**
 * PHAR Compiler Library
 * Copyright (C) 2020 Christian Neff
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 *
 * @package  Secondtruth\Compiler
 * @version  1.2
 * @link     https://www.secpndtruth.de
 * @license  https://opensource.org/licenses/MIT MIT License
 */

namespace Secondtruth\Compiler;

/**
 * The Compiler class creates PHAR archives
 *
 * @author   Fabien Potencier <fabien@symfony.com>
 * @author   Jordi Boggiano <j.boggiano@seld.be>
 * @author   Christian Neff <christian.neff@gmail.com>
 */
class Compiler
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $files = array();

    /**
     * @var array
     */
    protected $index = array();

    /**
     * Creates a Compiler instance.
     *
     * @param string $path The root path of the project
     * @throws \LogicException if the creation of Phar archives is disabled in php.ini.
     */
    public function __construct($path)
    {
        if (ini_get('phar.readonly')) {
            throw new \LogicException('Creation of Phar archives is disabled in php.ini. Please make sure that "phar.readonly" is set to "off".');
        }

        $this->path = realpath($path);
    }

    /**
     * Compiles all files into a single PHAR file.
     *
     * @param string $outputFile The full name of the file to create
     * @throws \LogicException if no index files are defined.
     */
    public function compile($outputFile)
    {
        if (empty($this->index)) {
            throw new \LogicException('Cannot compile when no index files are defined.');
        }

        if (file_exists($outputFile)) {
            unlink($outputFile);
        }

        $name = basename($outputFile);
        $phar = new \Phar($outputFile, 0, $name);
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();

        foreach ($this->files as $virtualFile => $fileInfo) {
            list($realFile, $strip) = $fileInfo;
            $content = file_get_contents($realFile);

            if ($strip) {
                $content = $this->stripWhitespace($content);
            }

            $phar->addFromString($virtualFile, $content);
        }

        foreach ($this->index as $type => $fileInfo) {
            list($virtualFile, $realFile) = $fileInfo;
            $content = file_get_contents($realFile);

            if ($type == 'cli') {
                $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
            }

            $phar->addFromString($virtualFile, $content);
        }

        $stub = $this->generateStub($name);
        $phar->setStub($stub);

        $phar->stopBuffering();
        unset($phar);
    }

    /**
     * Gets the root path of the project.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Gets list of all added files.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Adds a file.
     *
     * @param string $file The name of the file relative to the project root
     * @param bool $strip Strip whitespace (Default: TRUE)
     */
    public function addFile($file, $strip = true)
    {
        $realFile = realpath($this->path . DIRECTORY_SEPARATOR . $file);
        $this->files[$file] = [$realFile, (bool) $strip];
    }

    /**
     * Adds files of the given directory recursively.
     *
     * @param string $directory The name of the directory relative to the project root
     * @param string|array $exclude List of file name patterns to exclude (optional)
     * @param bool $strip Strip whitespace (Default: TRUE)
     */
    public function addDirectory($directory, $exclude = null, $strip = true)
    {
        $realPath = realpath($this->path . DIRECTORY_SEPARATOR . $directory);
        $iterator = new \RecursiveDirectoryIterator(
            $realPath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::CURRENT_AS_SELF
        );

        if ((is_string($exclude) || is_array($exclude)) && !empty($exclude)) {
            $exclude = (array) $exclude;
            $iterator = new \RecursiveCallbackFilterIterator($iterator, function (\RecursiveDirectoryIterator $current) use ($exclude) {
                if ($current->isDir()) {
                    return true;
                }

                return $this->filter($current->getSubPathname(), $exclude);
            });
        }

        $iterator = new \RecursiveIteratorIterator($iterator);
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $virtualFile = substr($file->getPathName(), strlen($this->path) + 1);
            $this->addFile($virtualFile, $strip);
        }
    }

    /**
     * Gets list of defined index files.
     *
     * @return array
     */
    public function getIndexFiles()
    {
        return $this->index;
    }

    /**
     * Adds an index file.
     *
     * @param string $file The name of the file relative to the project root
     * @param string $type The SAPI type (Default: 'cli')
     */
    public function addIndexFile($file, $type = 'cli')
    {
        $type = strtolower($type);

        if (!in_array($type, ['cli', 'web'])) {
            throw new \InvalidArgumentException(sprintf('Index file type "%s" is invalid, must be one of: cli, web', $type));
        }

        $this->index[$type] = [$file, realpath($this->path . DIRECTORY_SEPARATOR . $file)];
    }

    /**
     * Gets list of all supported SAPIs.
     *
     * @return array
     */
    public function getSupportedSapis()
    {
        return array_keys($this->index);
    }

    /**
     * Returns whether the compiled program will support the given SAPI type.
     *
     * @param string $sapi The SAPI type
     * @return bool
     */
    public function supportsSapi($sapi)
    {
        return in_array((string) $sapi, $this->getSupportedSapis());
    }

    /**
     * Generates the stub.
     *
     * @param string $name The internal Phar name
     * @return string
     */
    protected function generateStub($name)
    {
        $stub = ['#!/usr/bin/env php', '<?php'];
        $stub[] = "Phar::mapPhar('$name');";
        $stub[] = "if (PHP_SAPI == 'cli') {";

        if (isset($this->index['cli'])) {
            $file = $this->index['cli'][0];
            $stub[] = " require 'phar://$name/$file';";
        } else {
            $stub[] = " exit('This program can not be invoked via the CLI version of PHP, use the Web interface instead.'.PHP_EOL);";
        }

        $stub[] = '} else {';

        if (isset($this->index['web'])) {
            $file = $this->index['web'][0];
            $stub[] = " require 'phar://$name/$file';";
        } else {
            $stub[] = " exit('This program can not be invoked via the Web interface, use the CLI version of PHP instead.'.PHP_EOL);";
        }

        $stub[] = '}';
        $stub[] = '__HALT_COMPILER();';

        return join("\n", $stub);
    }

    /**
     * Matches the given path.
     *
     * @param string $path
     * @param string $pattern
     * @return bool
     */
    protected function match($path, $pattern)
    {
        $inverted = false;

        if ($pattern[0] == '!') {
            $pattern = substr($pattern, 1);
            $inverted = true;
        }

        return fnmatch($pattern, $path) == ($inverted ? false : true);
    }

    /**
     * Filters the given path.
     *
     * @param string $path
     * @param array $patterns
     * @return bool
     */
    protected function filter($path, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if ($this->match($path, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    protected function stripWhitespace($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }
}
