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

use PhpCsFixer\ConfigurationException\InvalidForEnvFixerConfigurationException;
use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet;
use PhpCsFixer\Test\AccessibleObject;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\RuleSet
 */
final class RuleSetTest extends TestCase
{
    public function testCreate()
    {
        $ruleSet = RuleSet::create();

        $this->assertInstanceOf('PhpCsFixer\RuleSet', $ruleSet);
    }

    /**
     * @param string     $ruleName
     * @param string     $setName
     * @param array|bool $ruleConfig
     *
     * @dataProvider provideAllRulesFromSetsCases
     */
    public function testIfAllRulesInSetsExists($setName, $ruleName, $ruleConfig)
    {
        $factory = new FixerFactory();
        $factory->registerBuiltInFixers();

        $fixers = array();

        foreach ($factory->getFixers() as $fixer) {
            $fixers[$fixer->getName()] = $fixer;
        }

        $this->assertArrayHasKey($ruleName, $fixers, sprintf('RuleSet "%s" contains unknown rule.', $setName));

        if (true === $ruleConfig) {
            return; // rule doesn't need configuration.
        }

        $fixer = $fixers[$ruleName];
        $this->assertInstanceOf('PhpCsFixer\Fixer\ConfigurableFixerInterface', $fixer, sprintf('RuleSet "%s" contains configuration for rule "%s" which cannot be configured.', $setName, $ruleName));

        try {
            $fixer->configure($ruleConfig); // test fixer accepts the configuration
        } catch (InvalidForEnvFixerConfigurationException $exception) {
            // ignore
        }
    }

    public function provideAllRulesFromSetsCases()
    {
        $cases = array();
        foreach (RuleSet::create()->getSetDefinitionNames() as $setName) {
            foreach (RuleSet::create(array($setName => true))->getRules() as $rule => $config) {
                $cases[] = array(
                    $setName,
                    $rule,
                    $config,
                );
            }
        }

        return $cases;
    }

    public function testGetBuildInSetDefinitionNames()
    {
        $setNames = RuleSet::create()->getSetDefinitionNames();

        $this->assertInternalType('array', $setNames);
        $this->assertNotEmpty($setNames);
    }

    /**
     * @dataProvider provideSetDefinitionNameCases
     *
     * @param mixed $setName
     */
    public function testBuildInSetDefinitionNames($setName)
    {
        $this->assertInternalType('string', $setName);
        $this->assertSame('@', substr($setName, 0, 1));
    }

    public function testResolveRulesWithInvalidSet()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Set "@foo" does not exist.'
        );

        RuleSet::create(array(
            '@foo' => true,
        ));
    }

    public function testResolveRulesWithMissingRuleValue()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Missing value for "braces" rule/set.'
        );

        RuleSet::create(array(
            'braces',
        ));
    }

    public function testResolveRulesWithSet()
    {
        $ruleSet = RuleSet::create(array(
            '@PSR1' => true,
            'braces' => true,
            'encoding' => false,
            'line_ending' => true,
            'strict_comparison' => true,
        ));

        $this->assertSameRules(
            array(
                'braces' => true,
                'full_opening_tag' => true,
                'line_ending' => true,
                'strict_comparison' => true,
            ),
            $ruleSet->getRules()
        );
    }

    public function testResolveRulesWithNestedSet()
    {
        $ruleSet = RuleSet::create(array(
            '@PSR2' => true,
            'strict_comparison' => true,
        ));

        $this->assertSameRules(
            array(
                'blank_line_after_namespace' => true,
                'braces' => true,
                'class_definition' => true,
                'elseif' => true,
                'encoding' => true,
                'full_opening_tag' => true,
                'function_declaration' => true,
                'indentation_type' => true,
                'line_ending' => true,
                'lowercase_constants' => true,
                'lowercase_keywords' => true,
                'method_argument_space' => true,
                'no_closing_tag' => true,
                'no_spaces_after_function_name' => true,
                'no_spaces_inside_parenthesis' => true,
                'no_trailing_whitespace' => true,
                'no_trailing_whitespace_in_comment' => true,
                'single_blank_line_at_eof' => true,
                'single_class_element_per_statement' => array('elements' => array('property')),
                'single_import_per_statement' => true,
                'single_line_after_imports' => true,
                'strict_comparison' => true,
                'switch_case_semicolon_to_colon' => true,
                'switch_case_space' => true,
                'visibility_required' => true,
            ),
            $ruleSet->getRules()
        );
    }

    public function testResolveRulesWithDisabledSet()
    {
        $ruleSet = RuleSet::create(array(
            '@PSR2' => true,
            '@PSR1' => false,
            'encoding' => true,
        ));

        $this->assertSameRules(
            array(
                'blank_line_after_namespace' => true,
                'braces' => true,
                'class_definition' => true,
                'elseif' => true,
                'encoding' => true,
                'function_declaration' => true,
                'indentation_type' => true,
                'line_ending' => true,
                'lowercase_constants' => true,
                'lowercase_keywords' => true,
                'method_argument_space' => true,
                'no_closing_tag' => true,
                'no_spaces_after_function_name' => true,
                'no_spaces_inside_parenthesis' => true,
                'no_trailing_whitespace' => true,
                'no_trailing_whitespace_in_comment' => true,
                'single_blank_line_at_eof' => true,
                'single_class_element_per_statement' => array('elements' => array('property')),
                'single_import_per_statement' => true,
                'single_line_after_imports' => true,
                'switch_case_semicolon_to_colon' => true,
                'switch_case_space' => true,
                'visibility_required' => true,
            ),
            $ruleSet->getRules()
        );
    }

    /**
     * @dataProvider provideSetDefinitionNameCases
     *
     * @param string $setDefinitionName
     */
    public function testSetDefinitionsAreSorted($setDefinitionName)
    {
        $ruleSet = RuleSet::create();

        $method = new \ReflectionMethod(
            'PhpCsFixer\RuleSet',
            'getSetDefinition'
        );

        $method->setAccessible(true);

        $setDefinition = $method->invoke(
            $ruleSet,
            $setDefinitionName
        );

        $sortedSetDefinition = $setDefinition;

        $this->sort($sortedSetDefinition);

        $this->assertSame($sortedSetDefinition, $setDefinition, sprintf(
            'Failed to assert that the set definition for "%s" is sorted by key',
            $setDefinitionName
        ));
    }

    /**
     * @return array
     */
    public function provideSetDefinitionNameCases()
    {
        $setDefinitionNames = RuleSet::create()->getSetDefinitionNames();

        return array_map(function ($setDefinitionName) {
            return array($setDefinitionName);
        }, $setDefinitionNames);
    }

    /**
     * @param array $set
     * @param bool  $safe
     *
     * @dataProvider provideSafeSetCases
     */
    public function testRiskyRulesInSet(array $set, $safe)
    {
        try {
            $fixers = FixerFactory::create()
                ->registerBuiltInFixers()
                ->useRuleSet(new RuleSet($set))
                ->getFixers()
            ;
        } catch (InvalidForEnvFixerConfigurationException $exception) {
            $this->markTestSkipped($exception->getMessage());
        }

        $fixerNames = array();
        foreach ($fixers as $fixer) {
            if ($safe === $fixer->isRisky()) {
                $fixerNames[] = $fixer->getName();
            }
        }

        $this->assertCount(
            0,
            $fixerNames,
            sprintf(
                'Set should only contain %s fixers, got: \'%s\'.',
                $safe ? 'safe' : 'risky',
                implode('\', \'', $fixerNames)
            )
        );
    }

    public function provideSafeSetCases()
    {
        $sets = array();

        $ruleSet = new RuleSet();

        foreach ($ruleSet->getSetDefinitionNames() as $name) {
            $sets[$name] = array(
                array($name => true),
                false === strpos($name, ':risky'),
            );
        }

        $sets['@Symfony:risky_and_@Symfony'] = array(
            array(
                '@Symfony:risky' => true,
                '@Symfony' => false,
            ),
            false,
        );

        return $sets;
    }

    public function testInvalidConfigNestedSets()
    {
        $this->setExpectedExceptionRegExp(
            '\UnexpectedValueException',
            '#^Nested rule set "@PSR1" configuration must be a boolean\.$#'
        );

        new RuleSet(
            array('@PSR1' => array('@PSR2' => 'no'))
        );
    }

    public function testGetSetDefinitionNames()
    {
        $ruleSet = $this->createRuleSetToTestWith(array());

        $this->assertSame(
            array_keys(self::getRuleSetDefinitionsToTestWith()),
            $ruleSet->getSetDefinitionNames()
        );
    }

    /**
     * @param array $expected
     * @param array $rules
     *
     * @dataProvider provideResolveRulesCases
     */
    public function testResolveRules(array $expected, array $rules)
    {
        $ruleSet = $this->createRuleSetToTestWith($rules);

        $this->assertSameRules($expected, $ruleSet->getRules());
    }

    public function provideResolveRulesCases()
    {
        return array(
            '@Foo + C\' -D' => array(
                array('A' => true, 'B' => true, 'C' => 56),
                array('@Foo' => true, 'C' => 56, 'D' => false),
            ),
            '@Foo + @Bar' => array(
                array('A' => true, 'B' => true, 'D' => 34, 'E' => true),
                array('@Foo' => true, '@Bar' => true),
            ),
            '@Foo - @Bar' => array(
                array('B' => true),
                array('@Foo' => true, '@Bar' => false),
            ),
            '@A - @E (set in set)' => array(
                array('AA' => true), // 'AB' => false, 'AC' => false
                array('@A' => true, '@E' => false),
            ),
            '@A + @E (set in set)' => array(
                array('AA' => true, 'AB' => '_AB', 'AC' => 'b', 'Z' => true),
                array('@A' => true, '@E' => true),
            ),
            '@E + @A (set in set) + rule override' => array(
                array('AC' => 'd', 'AB' => true, 'Z' => true, 'AA' => true),
                array('@E' => true, '@A' => true, 'AC' => 'd'),
            ),
            'nest single set' => array(
                array('AC' => 'b', 'AB' => '_AB', 'Z' => 'E'),
                array('@F' => true),
            ),
            'Set reconfigure rule in other set, reconfigure rule.' => array(
                array(
                    'AA' => true,
                    'AB' => true,
                    'AC' => 'abc',
                ),
                array(
                    '@A' => true,
                    '@D' => true,
                    'AC' => 'abc',
                ),
            ),
            'Set reconfigure rule in other set.' => array(
                array(
                    'AA' => true,
                    'AB' => true,
                    'AC' => 'b',
                ),
                array(
                    '@A' => true,
                    '@D' => true,
                ),
            ),
            'Set minus two sets minus rule' => array(
                array(
                    'AB' => true,
                ),
                array(
                    '@A' => true,
                    '@B' => false,
                    '@C' => false,
                    'AC' => false,
                ),
            ),
            'Set minus two sets' => array(
                array(
                    'AB' => true,
                    'AC' => 'a',
                ),
                array(
                    '@A' => true,
                    '@B' => false,
                    '@C' => false,
                ),
            ),
            'Set minus rule test.' => array(
                array(
                    'AA' => true,
                    'AC' => 'a',
                ),
                array(
                    '@A' => true,
                    'AB' => false,
                ),
            ),
            'Set minus set test.' => array(
                array(
                    'AB' => true,
                    'AC' => 'a',
                ),
                array(
                    '@A' => true,
                    '@B' => false,
                ),
            ),
            'Set to rules test.' => array(
                array(
                    'AA' => true,
                    'AB' => true,
                    'AC' => 'a',
                ),
                array(
                    '@A' => true,
                ),
            ),
            '@A - @C' => array(
                array(
                    'AB' => true,
                    'AC' => 'a',
                ),
                array(
                    '@A' => true,
                    '@C' => false,
                ),
            ),
            '@A - @D' => array(
                array(
                    'AA' => true,
                    'AB' => true,
                ),
                array(
                    '@A' => true,
                    '@D' => false,
                ),
            ),
        );
    }

    public function testGetMissingRuleConfiguration()
    {
        $ruleSet = new RuleSet();

        $this->setExpectedExceptionRegExp(
            'InvalidArgumentException',
            '#^Rule "_not_exists" is not in the set\.$#'
        );

        $ruleSet->getRuleConfiguration('_not_exists');
    }

    private function assertSameRules(array $expected, array $actual, $message = '')
    {
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Sorts an array of rule set definitions recursively.
     *
     * Sometimes keys are all string, sometimes they are integers - we need to account for that.
     *
     * @param array $data
     */
    private function sort(array &$data)
    {
        $keys = array_keys($data);

        if ($this->allInteger($keys)) {
            sort($data);
        } else {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->sort($data[$key]);
            }
        }
    }

    /**
     * @param array $values
     *
     * @return bool
     */
    private function allInteger(array $values)
    {
        foreach ($values as $value) {
            if (!is_int($value)) {
                return false;
            }
        }

        return true;
    }

    private function createRuleSetToTestWith(array $rules)
    {
        $ruleSet = new RuleSet();
        $reflection = new AccessibleObject($ruleSet);
        $reflection->setDefinitions = self::getRuleSetDefinitionsToTestWith();
        $reflection->set = $rules;
        $reflection->resolveSet();

        return $ruleSet;
    }

    private static function getRuleSetDefinitionsToTestWith()
    {
        static $testSet = array(
            '@A' => array(
                'AA' => true,
                'AB' => true,
                'AC' => 'a',
            ),
            '@B' => array(
                'AA' => true,
            ),
            '@C' => array(
                'AA' => false,
            ),
            '@D' => array(
                'AC' => 'b',
            ),
            '@E' => array(
                '@D' => true,
                'AB' => '_AB',
                'Z' => true,
            ),
            '@F' => array(
                '@E' => true,
                'Z' => 'E',
            ),
            '@Foo' => array('A' => true, 'B' => true, 'C' => true, 'D' => 12),
            '@Bar' => array('A' => true, 'C' => false, 'D' => 34, 'E' => true, 'F' => false),
        );

        return $testSet;
    }
}
