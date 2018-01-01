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

namespace PhpCsFixer\Tests\Fixer\PhpUnit;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\PhpUnit\PhpUnitStrictFixer
 */
final class PhpUnitStrictFixerTest extends AbstractFixerTestCase
{
    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @dataProvider provideTestFixCases
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);

        $this->fixer->configure(['assertions' => [
            'assertAttributeEquals',
            'assertAttributeNotEquals',
            'assertEquals',
            'assertNotEquals',
        ]]);
        $this->doTest($expected, $input);
    }

    public function provideTestFixCases()
    {
        $methodsMap = [
            'assertAttributeEquals' => 'assertAttributeSame',
            'assertAttributeNotEquals' => 'assertAttributeNotSame',
            'assertEquals' => 'assertSame',
            'assertNotEquals' => 'assertNotSame',
        ];

        $cases = [
            ['<?php $self->foo();'],
        ];

        foreach ($methodsMap as $methodBefore => $methodAfter) {
            $cases[] = ["<?php \$sth->${methodBefore}(1, 1);"];
            $cases[] = ["<?php \$sth->${methodAfter}(1, 1);"];
            $cases[] = [
                "<?php \$this->${methodAfter}(1, 2);",
                "<?php \$this->${methodBefore}(1, 2);",
            ];
            $cases[] = [
                "<?php \$this->${methodAfter}(1, 2); \$this->${methodAfter}(1, 2);",
                "<?php \$this->${methodBefore}(1, 2); \$this->${methodBefore}(1, 2);",
            ];
            $cases[] = [
                "<?php \$this->${methodAfter}(1, 2, 'descr');",
                "<?php \$this->${methodBefore}(1, 2, 'descr');",
            ];
            $cases[] = [
                "<?php \$this->/*aaa*/${methodAfter} \t /**bbb*/  ( /*ccc*/1  , 2);",
                "<?php \$this->/*aaa*/${methodBefore} \t /**bbb*/  ( /*ccc*/1  , 2);",
            ];
            $cases[] = [
                "<?php \$this->${methodAfter}(\$expectedTokens->count() + 10, \$tokens->count() ? 10 : 20 , 'Test');",
                "<?php \$this->${methodBefore}(\$expectedTokens->count() + 10, \$tokens->count() ? 10 : 20 , 'Test');",
            ];
        }

        return $cases;
    }

    public function testInvalidConfig()
    {
        $this->expectException(\PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException::class);
        $this->expectExceptionMessageRegExp('/^\[php_unit_strict\] Invalid configuration: The option "assertions" .*\.$/');

        $this->fixer->configure(['assertions' => ['__TEST__']]);
    }
}
