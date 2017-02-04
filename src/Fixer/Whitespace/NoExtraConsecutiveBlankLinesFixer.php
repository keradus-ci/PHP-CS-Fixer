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

namespace PhpCsFixer\Fixer\Whitespace;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\VersionSpecification;
use PhpCsFixer\FixerDefinition\VersionSpecificCodeSample;
use PhpCsFixer\OptionsResolver;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author SpacePossum
 */
final class NoExtraConsecutiveBlankLinesFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface, WhitespacesAwareFixerInterface
{
    /**
     * @var string[]
     */
    private $availableTokens = array(
        'break',
        'continue',
        'extra',
        'return',
        'throw',
        'use',
        'useTrait',
        'curly_brace_block',
        'parenthesis_brace_block',
        'square_brace_block',
    );

    /**
     * @var array<int, string> key is token id, value is name of callback
     */
    private $tokenKindCallbackMap;

    /**
     * @var array<string, string> token prototype, value is name of callback
     */
    private $tokenEqualsMap;

    /**
     * @var Tokens
     */
    private $tokens;

    /**
     * @var TokensAnalyzer
     */
    private $tokensAnalyzer;

    /**
     * {@inheritdoc}
     */
    public function configure(array $configuration = null)
    {
        if (is_array($configuration) && count($configuration) && !array_key_exists('tokens', $configuration)) {
            @trigger_error(
                'Passing tokens at the root of the configuration is deprecated and will not be supported in 3.0, use "tokens" => array(...) option.',
                E_USER_DEPRECATED
            );

            $configuration = array('tokens' => $configuration);
        }

        parent::configure($configuration);

        $this->tokenKindCallbackMap = array();
        $this->tokenEqualsMap = array();
        foreach ($this->configuration['tokens'] as $item) {
            switch ($item) {
                case 'break':
                    $this->tokenKindCallbackMap[T_BREAK] = 'fixAfterToken';
                    break;
                case 'continue':
                    $this->tokenKindCallbackMap[T_CONTINUE] = 'fixAfterToken';
                    break;
                case 'extra':
                    $this->tokenKindCallbackMap[T_WHITESPACE] = 'removeMultipleBlankLines';
                    break;
                case 'return':
                    $this->tokenKindCallbackMap[T_RETURN] = 'fixAfterToken';
                    break;
                case 'throw':
                    $this->tokenKindCallbackMap[T_THROW] = 'fixAfterToken';
                    break;
                case 'use':
                    $this->tokenKindCallbackMap[T_USE] = 'removeBetweenUse';
                    break;
                case 'useTrait':
                    $this->tokenKindCallbackMap[CT::T_USE_TRAIT] = 'removeBetweenUse';
                    break;
                case 'curly_brace_block':
                    $this->tokenEqualsMap['{'] = 'fixStructureOpenCloseIfMultiLine'; // i.e. not: CT::T_ARRAY_INDEX_CURLY_BRACE_OPEN
                    break;
                case 'parenthesis_brace_block':
                    $this->tokenEqualsMap['('] = 'fixStructureOpenCloseIfMultiLine'; // i.e. not: CT::T_BRACE_CLASS_INSTANTIATION_OPEN
                    break;
                case 'square_brace_block':
                    $this->tokenKindCallbackMap[CT::T_ARRAY_SQUARE_BRACE_OPEN] = 'fixStructureOpenCloseIfMultiLine'; // typeless '[' tokens should not be fixed (too rare)
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationDefinition()
    {
        $tokens = $this->availableTokens;
        $configurationDefinition = new OptionsResolver();

        return $configurationDefinition
            ->setDefault('tokens', array('extra'))
            ->setAllowedTypes('tokens', 'array')
            ->setNormalizer('tokens', function (Options $options, $value) use ($tokens) {
                foreach ($value as $token) {
                    if (!is_string($token)) {
                        throw new InvalidOptionsException(sprintf(
                            'Token must be a string, %s given.',
                            is_object($token) ? get_class($token) : gettype($token)
                        ));
                    }

                    if (!in_array($token, $tokens, true)) {
                        throw new InvalidOptionsException(sprintf('Unknown token "%s".', $token));
                    }
                }

                return $value;
            })
            ->setDescription('tokens', 'list of tokens to fix')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        $this->tokens = $tokens;
        $this->tokensAnalyzer = new TokensAnalyzer($this->tokens);
        for ($index = $tokens->getSize() - 1; $index > 0; --$index) {
            $this->fixByToken($tokens[$index], $index);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        $values = array(
            'break',
            'continue',
            'curly_brace_block',
            'extra',
            'parenthesis_brace_block',
            'return',
            'square_brace_block',
            'throw',
            'use',
            'useTrait',
        );

        return new FixerDefinition(
            'Removes extra blank lines and/or blank lines following configuration.',
            array(
                new CodeSample(
'<?php

$foo = array("foo");


$bar = "bar";'
                ),
                new CodeSample(
'<?php

switch ($foo) {
    case 41:
        echo "foo";
        break;

    case 42:
        break;
}',
                    array('break')
                ),
                new CodeSample(
'<?php

for ($i = 0; $i < 9000; ++$i) {
    if (true) {
        continue;

    }
}',
                    array('continue')
                ),
                new CodeSample(
'<?php

for ($i = 0; $i < 9000; ++$i) {

    echo $i;

}',
                    array('curly_brace_block')
                ),
                new CodeSample(
'<?php

$foo = array("foo");


$bar = "bar";',
                    array('extra')
                ),
                new CodeSample(
'<?php

$foo = array(

    "foo"

);',
                    array('parenthesis_brace_block')
                ),
                new CodeSample(
'<?php

function foo($bar)
{
    return $bar;

}',
                    array('return')
                ),
                new VersionSpecificCodeSample(
'<?php

$foo = [

    "foo"

];',
                    new VersionSpecification(50400),
                    array('square_brace_block')
                ),
                new CodeSample(
'<?php

function foo($bar)
{
    throw new \Exception("Hello!");

}',
                    array('throw')
                ),
                new CodeSample(
'<?php

function foo($bar)
{
    throw new \Exception("Hello!");

}',
                    array('throw')
                ),
                new CodeSample(
'<?php

namespace Foo;

use Bar\Baz;

use Baz\Bar;

class Bar
{
}',
                    array('use')
                ),
                new CodeSample(
'<?php

class Foo
{
    use Bar;

    use Baz;
}',
                    array('useTrait')
                ),
            ),
            null,
            sprintf(
                'Configure to use any combination of "%s"',
                implode('", "', $values)
            ),
            $this->getDefaultConfiguration()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // should be run after the NoUnusedImportsFixer, NoEmptyPhpdocFixer, CombineConsecutiveUnsetsFixer and NoUselessElseFixer
        return -20;
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return true;
    }

    private function fixByToken(Token $token, $index)
    {
        foreach ($this->tokenKindCallbackMap as $kind => $callback) {
            if (!$token->isGivenKind($kind)) {
                continue;
            }

            $this->$callback($index);

            return;
        }

        foreach ($this->tokenEqualsMap as $equals => $callback) {
            if (!$token->equals($equals)) {
                continue;
            }

            $this->$callback($index);

            return;
        }
    }

    private function removeBetweenUse($index)
    {
        $next = $this->tokens->getNextTokenOfKind($index, array(';', T_CLOSE_TAG));
        if (null === $next || $this->tokens[$next]->isGivenKind(T_CLOSE_TAG)) {
            return;
        }

        $nextUseCandidate = $this->tokens->getNextMeaningfulToken($next);
        if (null === $nextUseCandidate || 1 === $nextUseCandidate - $next || !$this->tokens[$nextUseCandidate]->isGivenKind($this->tokens[$index]->getId())) {
            return;
        }

        return $this->removeEmptyLinesAfterLineWithTokenAt($next);
    }

    private function removeMultipleBlankLines($index)
    {
        $token = $this->tokens[$index];
        $content = '';
        $count = 0;
        $parts = explode("\n", $token->getContent());

        for ($i = 0, $last = count($parts) - 1; $i <= $last; ++$i) {
            if ('' === $parts[$i] || "\r" === $parts[$i]) {
                // if part is empty then we are between two "\n"
                ++$count;
            } else {
                $content .= $parts[$i];
            }

            if ($i !== $last && $count < 3) {
                $content .= $this->whitespacesConfig->getLineEnding();
            }
        }

        $token->setContent($content);
    }

    private function fixAfterToken($index)
    {
        for ($i = $index - 1; $i > 0; --$i) {
            if ($this->tokens[$i]->isGivenKind(T_FUNCTION) && $this->tokensAnalyzer->isLambda($i)) {
                return;
            }

            if ($this->tokens[$i]->isGivenKind(T_CLASS) && $this->tokensAnalyzer->isAnonymousClass($i)) {
                return;
            }

            if ($this->tokens[$i]->isWhitespace() && false !== strpos($this->tokens[$i]->getContent(), "\n")) {
                break;
            }
        }

        $this->removeEmptyLinesAfterLineWithTokenAt($index);
    }

    /**
     * Remove white line(s) after the index of a block type,
     * but only if the block is not on one line.
     *
     * @param int $index body start
     */
    private function fixStructureOpenCloseIfMultiLine($index)
    {
        $blockTypeInfo = Tokens::detectBlockType($this->tokens[$index]);
        $bodyEnd = $this->tokens->findBlockEnd($blockTypeInfo['type'], $index);

        for ($i = $bodyEnd - 1; $i >= $index; --$i) {
            if (false !== strpos($this->tokens[$i]->getContent(), "\n")) {
                $this->removeEmptyLinesAfterLineWithTokenAt($i);
                $this->removeEmptyLinesAfterLineWithTokenAt($index);
                break;
            }
        }
    }

    private function removeEmptyLinesAfterLineWithTokenAt($index)
    {
        // find the line break
        $tokenCount = count($this->tokens);
        for ($end = $index; $end < $tokenCount; ++$end) {
            if (
                $this->tokens[$end]->equals('}')
                || false !== strpos($this->tokens[$end]->getContent(), "\n")
            ) {
                break;
            }
        }

        if ($end === $tokenCount) {
            return; // not found, early return
        }

        for ($i = $end; $i < $tokenCount && $this->tokens[$i]->isWhitespace(); ++$i) {
            $content = $this->tokens[$i]->getContent();
            if (substr_count($content, "\n") < 1) {
                continue;
            }

            $ending = $this->whitespacesConfig->getLineEnding();

            $pos = strrpos($content, "\n");
            if ($pos + 2 < strlen($content)) { // preserve indenting where possible
                $this->tokens[$i]->setContent($ending.substr($content, $pos + 1));
            } else {
                $this->tokens[$i]->setContent($ending);
            }
        }
    }
}
