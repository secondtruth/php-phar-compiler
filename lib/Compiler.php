<?php
/**
 * Compiler Library
 * Copyright (C) 2014 secondtruth
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE
 * FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY
 * DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER
 * IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING
 * OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 * @package  Secondtruth\Compiler
 * @version  0.1
 * @link     http://www.secpndtruth.de
 * @license  ISC License <http://opensource.org/licenses/ISC>
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
    private $path;

    /**
     * @var array
     */
    private $files = array();

    /**
     * @var array
     */
    private $index = array();

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
     * @param string $outputfile The full name of the file to create
     * @throws \LogicException if no index files are defined.
     */
    public function compile($outputfile)
    {
        if (empty($this->index)) {
            throw new \LogicException('Cannot compile when no index files are defined.');
        }

        if (file_exists($outputfile)) {
            unlink($outputfile);
        }

        $name = basename($outputfile);
        $phar = new \Phar($outputfile, 0, $name);
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();

        foreach ($this->files as $virtualfile => $fileinfo) {
            list($realfile, $strip) = $fileinfo;
            $content = file_get_contents($realfile);

            if ($strip) {
                $content = $this->stripWhitespace($content);
            }

            $phar->addFromString($virtualfile, $content);
        }

        foreach ($this->index as $type => $fileinfo) {
            list($virtualfile, $realfile) = $fileinfo;
            $content = file_get_contents($realfile);

            if ($type == 'cli') {
                $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
            }

            $phar->addFromString($virtualfile, $content);
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
        $realfile = realpath($this->path . DIRECTORY_SEPARATOR . $file);
        $this->files[$file] = [$realfile, (bool) $strip];
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
        $realpath = realpath($this->path . DIRECTORY_SEPARATOR . $directory);
        $iterator = new \RecursiveDirectoryIterator($realpath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS);

        if ((is_string($exclude) || is_array($exclude)) && !empty($exclude)) {
            $iterator = new \RecursiveCallbackFilterIterator($iterator, function ($current) use ($exclude, $realpath) {
                if ($current->isDir()) {
                    return true;
                }

                $subpath = substr($current->getPathName(), strlen($realpath) + 1);

                foreach ((array) $exclude as $pattern) {
                    if ($pattern[0] == '!' ? !fnmatch(substr($pattern, 1), $subpath) : fnmatch($pattern, $subpath)) {
                        return false;
                    }
                }

                return true;
            });
        }

        $iterator = new \RecursiveIteratorIterator($iterator);
        foreach ($iterator as $file) {
            $virtualfile = substr($file->getPathName(), strlen($this->path) + 1);
            $this->files[$virtualfile] = [$file->getRealPath(), (bool) $strip];
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
        $stub = array('#!/usr/bin/env php', '<?php');
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
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace($source)
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
