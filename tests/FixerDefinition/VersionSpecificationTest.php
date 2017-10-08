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

namespace PhpCsFixer\Tests\FixerDefinition;

use PhpCsFixer\FixerDefinition\VersionSpecification;
use PhpCsFixer\Tests\TestCase;

/**
 * @author Andreas Möller <am@localheinz.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\FixerDefinition\VersionSpecification
 */
final class VersionSpecificationTest extends TestCase
{
    public function testConstructorRequiresEitherMinimumOrMaximum()
    {
        $this->setExpectedException('InvalidArgumentException');

        new VersionSpecification();
    }

    /**
     * @dataProvider provideInvalidVersionCases
     *
     * @param mixed $minimum
     */
    public function testConstructorRejectsInvalidMinimum($minimum)
    {
        $this->setExpectedException('InvalidArgumentException');

        new VersionSpecification($minimum);
    }

    /**
     * @dataProvider provideInvalidVersionCases
     *
     * @param mixed $maximum
     */
    public function testConstructorRejectsInvalidMaximum($maximum)
    {
        $this->setExpectedException('InvalidArgumentException');

        new VersionSpecification(
            PHP_VERSION_ID,
            $maximum
        );
    }

    /**
     * @return array
     */
    public function provideInvalidVersionCases()
    {
        return array(
            'negative' => array(-1),
            'zero' => array(0),
            'float' => array(3.14),
            'string' => array('foo'),
            'integerish' => array('9000'),
            'array' => array(array()),
            'object' => array(new \stdClass()),
        );
    }

    public function testConstructorRejectsMaximumLessThanMinimum()
    {
        $this->setExpectedException('InvalidArgumentException');

        new VersionSpecification(
            PHP_VERSION_ID,
            PHP_VERSION_ID - 1
        );
    }

    /**
     * @dataProvider provideIsSatisfiedByReturnsTrueCases
     *
     * @param null|int $minimum
     * @param null|int $maximum
     * @param int      $actual
     */
    public function testIsSatisfiedByReturnsTrue($minimum, $maximum, $actual)
    {
        $versionSpecification = new VersionSpecification(
            $minimum,
            $maximum
        );

        $this->assertTrue($versionSpecification->isSatisfiedBy($actual));
    }

    /**
     * @return array
     */
    public function provideIsSatisfiedByReturnsTrueCases()
    {
        return array(
            'version-same-as-maximum' => array(null, PHP_VERSION_ID, PHP_VERSION_ID),
            'version-same-as-minimum' => array(PHP_VERSION_ID, null, PHP_VERSION_ID),
            'version-between-minimum-and-maximum' => array(PHP_VERSION_ID - 1, PHP_VERSION_ID + 1, PHP_VERSION_ID),
            'version-same-as-minimum-and-maximum' => array(PHP_VERSION_ID, PHP_VERSION_ID, PHP_VERSION_ID),
        );
    }

    /**
     * @dataProvider provideIsSatisfiedByReturnsFalseCases
     *
     * @param null|int $minimum
     * @param null|int $maximum
     * @param int      $actual
     */
    public function testIsSatisfiedByReturnsFalse($minimum, $maximum, $actual)
    {
        $versionSpecification = new VersionSpecification(
            $minimum,
            $maximum
        );

        $this->assertFalse($versionSpecification->isSatisfiedBy($actual));
    }

    /**
     * @return array
     */
    public function provideIsSatisfiedByReturnsFalseCases()
    {
        return array(
            'version-greater-than-maximum' => array(null, PHP_VERSION_ID, PHP_VERSION_ID + 1),
            'version-less-than-minimum' => array(PHP_VERSION_ID, null, PHP_VERSION_ID - 1),
        );
    }
}
