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

use PhpCsFixer\Test\AccessibleObject;
use PhpCsFixer\Tests\Test\TestCase;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\AbstractFunctionReferenceFixer
 */
final class AbstractFunctionReferenceFixerTest extends TestCase
{
    /**
     * @param string $code
     * @param int    $openIndex
     * @param int    $closeIndex
     * @param array  $arguments
     *
     * @dataProvider provideCases
     */
    public function testCountArguments($code, $openIndex, $closeIndex, array $arguments)
    {
        $tokens = Tokens::fromCode($code);
        $mock = new AccessibleObject($this->getMockForAbstractClass('\\PhpCsFixer\\AbstractFunctionReferenceFixer'));

        $this->assertSame(count($arguments), $mock->countArguments($tokens, $openIndex, $closeIndex));
        $this->assertSame($arguments, $mock->getArguments($tokens, $openIndex, $closeIndex));
    }

    public function provideCases()
    {
        return array(
            array('<?php fnc();', 2, 3, array()),
            array('<?php fnc($a);', 2, 4, array(3 => 3)),
            array('<?php fnc($a, $b);', 2, 7, array(3 => 3, 5 => 6)),
            array('<?php fnc($a, $b = array(1,2), $c = 3);', 2, 23, array(3 => 3, 5 => 15, 17 => 22)),
        );
    }
}
