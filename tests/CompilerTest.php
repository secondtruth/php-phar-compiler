<?php
/**
 * Compiler Library
 * Copyright (C) 2015 secondtruth
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
 * @version  1.0-dev
 * @link     http://www.secpndtruth.de
 * @license  ISC License <http://opensource.org/licenses/ISC>
 */

namespace Secondtruth\Compiler\Tests;

use Secondtruth\Compiler\Compiler;

/**
 * Test class for Compiler
 *
 * @author   Christian Neff <christian.neff@gmail.com>
 */
class CompilerTest extends \PHPUnit_Framework_TestCase
{
    protected $testPhar;

    public function setUp()
    {
        $testDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        $testPharName = time().mt_rand(0, 1000).'.phar';
        $this->testPhar = $testDir.DIRECTORY_SEPARATOR.$testPharName;
    }

    public function tearDown()
    {
        unlink($this->testPhar);
    }

    public function testAddFile()
    {
        $path = realpath(__DIR__.'/fixtures');
        $compiler = new Compiler($path);
        $compiler->addIndexFile('index.php');
        $compiler->addFile('dir/abc.php');
        $compiler->compile($this->testPhar);

        $this->assertFileExists($this->testPhar);

        $phar = new \Phar($this->testPhar);
        $this->assertTrue(isset($phar['dir/abc.php']));
    }

    public function testAddDirectory()
    {
        $path = realpath(__DIR__.'/fixtures');
        $compiler = new Compiler($path);
        $compiler->addIndexFile('index.php');
        $compiler->addDirectory('dir');
        $compiler->compile($this->testPhar);

        $this->assertFileExists($this->testPhar);

        $phar = new \Phar($this->testPhar);
        $this->assertTrue(isset($phar['dir/abc.php']));
        $this->assertTrue(isset($phar['dir/def.txt']));
    }

    public function testAddDirectoryWithFilter()
    {
        $path = realpath(__DIR__.'/fixtures');
        $compiler = new Compiler($path);
        $compiler->addIndexFile('index.php');
        $compiler->addDirectory('dir', '!*php');
        $compiler->compile($this->testPhar);

        $this->assertFileExists($this->testPhar);

        $phar = new \Phar($this->testPhar);
        $this->assertTrue(isset($phar['dir/abc.php']));
        $this->assertFalse(isset($phar['dir/def.txt']));
    }
}
