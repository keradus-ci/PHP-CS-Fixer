<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests;

use PhpCsFixer\Tests\Test\TestCase;
use PhpCsFixer\WhitespacesFixerConfig;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\WhitespacesFixerConfig
 */
final class WhitespacesFixerConfigTest extends TestCase
{
    /**
     * @param string      $indent
     * @param string      $lineEnding
     * @param null|string $exceptionRegExp
     *
     * @dataProvider provideTestCases
     */
    public function testCases($indent, $lineEnding, $exceptionRegExp = null)
    {
        if (null !== $exceptionRegExp) {
            $this->setExpectedExceptionRegExp('InvalidArgumentException', $exceptionRegExp);
        }

        $config = new WhitespacesFixerConfig($indent, $lineEnding);

        $this->assertSame($indent, $config->getIndent());
        $this->assertSame($lineEnding, $config->getLineEnding());
    }

    public function provideTestCases()
    {
        return array(
            array('    ', "\n"),
            array("\t", "\n"),
            array('    ', "\r\n"),
            array("\t", "\r\n"),
            array('    ', 'asd', '/lineEnding/'),
            array('    ', array(), '/lineEnding/'),
            array('std', "\n", '/indent/'),
            array(array(), "\n", '/indent/'),
        );
    }
}
