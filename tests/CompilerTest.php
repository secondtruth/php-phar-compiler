<?php
/**
 * PHAR Compiler Library
 * Copyright (C) 2017 Christian Neff
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 *
 * @package  Secondtruth\Compiler
 * @version  1.1
 * @link     http://www.secpndtruth.de
 * @license  http://opensource.org/licenses/ISC ISC License
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
    protected static $testDir;
    protected static $fixturesDir;

    public static function setUpBeforeClass()
    {
        self::$testDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        self::$fixturesDir = realpath(__DIR__.DIRECTORY_SEPARATOR.'fixtures');
    }

    public function setUp()
    {
        $testPharName = time().mt_rand(100, 999).'.phar';
        $this->testPhar = self::$testDir.DIRECTORY_SEPARATOR.$testPharName;
    }

    public function tearDown()
    {
        unlink($this->testPhar);
        unset($this->testPhar);
    }

    public function testAddFile()
    {
        $compiler = new Compiler(self::$fixturesDir);
        $compiler->addIndexFile('index.php');
        $compiler->addFile('dir/abc.php');
        $compiler->compile($this->testPhar);

        $this->assertFileExists($this->testPhar);

        $phar = new \Phar($this->testPhar);
        $this->assertTrue(isset($phar['dir/abc.php']));
    }

    public function testAddDirectory()
    {
        $compiler = new Compiler(self::$fixturesDir);
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
        $compiler = new Compiler(self::$fixturesDir);
        $compiler->addIndexFile('index.php');
        $compiler->addDirectory('dir', '!*.php');
        $compiler->compile($this->testPhar);

        $this->assertFileExists($this->testPhar);

        $phar = new \Phar($this->testPhar);
        $this->assertTrue(isset($phar['dir/abc.php']));
        $this->assertFalse(isset($phar['dir/def.txt']));
    }
}
