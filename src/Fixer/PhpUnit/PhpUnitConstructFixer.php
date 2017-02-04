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

namespace PhpCsFixer\Fixer\PhpUnit;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\OptionsResolver;
use PhpCsFixer\Tokenizer\Tokens;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
final class PhpUnitConstructFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    private static $assertionFixers = array(
        'assertSame' => 'fixAssertPositive',
        'assertEquals' => 'fixAssertPositive',
        'assertNotEquals' => 'fixAssertNegative',
        'assertNotSame' => 'fixAssertNegative',
    );

    /**
     * {@inheritdoc}
     */
    public function configure(array $configuration = null)
    {
        if (is_array($configuration) && count($configuration) && !array_key_exists('assertions', $configuration)) {
            @trigger_error(
                'Passing assertions at the root of the configuration is deprecated and will not be supported in 3.0, use "assertions" => array(...) option.',
                E_USER_DEPRECATED
            );

            $configuration = array('assertions' => $configuration);
        }

        parent::configure($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationDefinition()
    {
        $fixers = self::$assertionFixers;
        $configurationDefinition = new OptionsResolver();

        return $configurationDefinition
            ->setDefault('assertions', array(
                'assertEquals',
                'assertSame',
                'assertNotEquals',
                'assertNotSame',
            ))
            ->setAllowedTypes('assertions', 'array')
            ->setNormalizer('assertions', function (Options $options, $value) use ($fixers) {
                foreach ($value as $method) {
                    if (!array_key_exists($method, $fixers)) {
                        throw new InvalidOptionsException(sprintf(
                            'Configured method "%s" cannot be fixed by this fixer.',
                            $method
                        ));
                    }
                }

                return $value;
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    /**
     * {@inheritdoc}
     */
    public function isRisky()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        // no assertions to be fixed - fast return
        if (empty($this->configuration['assertions'])) {
            return;
        }

        foreach ($this->configuration['assertions'] as $assertionMethod) {
            $assertionFixer = self::$assertionFixers[$assertionMethod];

            for ($index = 0, $limit = $tokens->count(); $index < $limit; ++$index) {
                $index = $this->$assertionFixer($tokens, $index, $assertionMethod);

                if (null === $index) {
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'PHPUnit assertion method calls like "->assertSame(true, $foo)" should be written with dedicated method like "->assertTrue($foo)".',
            array(
                new CodeSample(
                    '<?php
$this->assertEquals(false, $b);
$this->assertSame(true, $a);
$this->assertNotEquals(null, $c);
$this->assertNotSame(null, $d);
'
                ),
                new CodeSample(
                    '<?php
$this->assertEquals(false, $b);
$this->assertSame(true, $a);
$this->assertNotEquals(null, $c);
$this->assertNotSame(null, $d);
',
                    array('assertSame', 'assertNotSame')
                ),
            ),
            null,
            'List of strings which methods should be modified.',
            $this->getDefaultConfiguration(),
            'Fixer could be risky if one is overwritting PHPUnit\'s native methods.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // should be run after the PhpUnitStrictFixer and before PhpUnitDedicateAssertFixer.
        return -10;
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     * @param string $method
     *
     * @return int|null
     */
    private function fixAssertNegative(Tokens $tokens, $index, $method)
    {
        static $map = array(
            'false' => 'assertNotFalse',
            'null' => 'assertNotNull',
            'true' => 'assertNotTrue',
        );

        return $this->fixAssert($map, $tokens, $index, $method);
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     * @param string $method
     *
     * @return int|null
     */
    private function fixAssertPositive(Tokens $tokens, $index, $method)
    {
        static $map = array(
            'false' => 'assertFalse',
            'null' => 'assertNull',
            'true' => 'assertTrue',
        );

        return $this->fixAssert($map, $tokens, $index, $method);
    }

    /**
     * @param array<string, string> $map
     * @param Tokens                $tokens
     * @param int                   $index
     * @param string                $method
     *
     * @return int|null
     */
    private function fixAssert(array $map, Tokens $tokens, $index, $method)
    {
        $sequence = $tokens->findSequence(
            array(
                array(T_VARIABLE, '$this'),
                array(T_OBJECT_OPERATOR, '->'),
                array(T_STRING, $method),
                '(',
            ),
            $index
        );

        if (null === $sequence) {
            return null;
        }

        $sequenceIndexes = array_keys($sequence);
        $sequenceIndexes[4] = $tokens->getNextMeaningfulToken($sequenceIndexes[3]);
        $firstParameterToken = $tokens[$sequenceIndexes[4]];

        if (!$firstParameterToken->isNativeConstant()) {
            return null;
        }

        $sequenceIndexes[5] = $tokens->getNextMeaningfulToken($sequenceIndexes[4]);

        // return if first method argument is an expression, not value
        if (!$tokens[$sequenceIndexes[5]]->equals(',')) {
            return null;
        }

        $tokens[$sequenceIndexes[2]]->setContent($map[$firstParameterToken->getContent()]);
        $tokens->clearRange($sequenceIndexes[4], $tokens->getNextNonWhitespace($sequenceIndexes[5]) - 1);

        return $sequenceIndexes[5];
    }
}
