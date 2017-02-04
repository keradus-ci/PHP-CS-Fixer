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

namespace PhpCsFixer\Fixer\Comment;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\ConfigurationException\RequiredFixerConfigurationException;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\OptionsResolver;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Symfony\Component\OptionsResolver\Options;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @author SpacePossum
 */
final class HeaderCommentFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface, WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigurationDefinition()
    {
        $whitespaceConfig = $this->whitespacesConfig;
        $configurationDefinition = new OptionsResolver();

        return $configurationDefinition
            ->setRequired('header')
            ->setAllowedTypes('header', 'string')
            ->setNormalizer('header', function (Options $options, $value) use ($whitespaceConfig) {
                if ('' === trim($value)) {
                    return '';
                }

                $lineEnding = $whitespaceConfig->getLineEnding();

                $comment = ('comment' === $options['commentType'] ? '/*' : '/**').$lineEnding;
                $lines = explode("\n", str_replace("\r", '', $value));
                foreach ($lines as $line) {
                    $comment .= rtrim(' * '.$line).$lineEnding;
                }

                return $comment.' */';
            })

            ->setDefault('commentType', 'comment')
            ->setAllowedValues('commentType', array('PHPDoc', 'comment'))

            ->setDefault('location', 'after_declare_strict')
            ->setAllowedValues('location', array('after_open', 'after_declare_strict'))

            ->setDefault('separate', 'both')
            ->setAllowedValues('separate', array('both', 'top', 'bottom', 'none'))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        if (null === $this->configuration['header']) {
            throw new RequiredFixerConfigurationException($this->getName(), 'Configuration is required.');
        }

        // figure out where the comment should be placed
        $headerNewIndex = $this->findHeaderCommentInsertionIndex($tokens);

        // check if there is already a comment
        $headerCurrentIndex = $this->findHeaderCommentCurrentIndex($tokens, $headerNewIndex - 1);

        if (null === $headerCurrentIndex) {
            if ('' === $this->configuration['header']) {
                return; // header not found and none should be set, return
            }

            $this->insertHeader($tokens, $headerNewIndex);
        } elseif ($this->configuration['header'] !== $tokens[$headerCurrentIndex]->getContent()) {
            $tokens->clearTokenAndMergeSurroundingWhitespace($headerCurrentIndex);
            if ('' === $this->configuration['header']) {
                return; // header found and cleared, none should be set, return
            }

            $this->insertHeader($tokens, $headerNewIndex);
        } else {
            $headerNewIndex = $headerCurrentIndex;
        }

        $this->fixWhiteSpaceAroundHeader($tokens, $headerNewIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Add, replace or remove header comment.',
            array(
                new CodeSample(
                    '<?php
declare(strict_types=1);

namespace A\B;

echo 1;
',
                    array(
                        'header' => 'Made with love.',
                    )
                ),
                new CodeSample(
                    '<?php
declare(strict_types=1);

namespace A\B;

echo 1;
',
                    array(
                        'header' => 'Made with love.',
                        'commentType' => 'PHPDoc',
                        'location' => 'after_open',
                        'separate' => 'bottom',
                    )
                ),
                new CodeSample(
                    '<?php
declare(strict_types=1);

namespace A\B;

echo 1;
',
                    array(
                        'header' => 'Made with love.',
                        'commentType' => 'comment',
                        'location' => 'after_declare_strict',
                    )
                ),
            ),
            null,
            'The following configuration options are allowed:
- header       proper header content here, this option is required
- commentType  PHPDoc|comment*
- location     after_open|after_declare_strict*
- separate     top|bottom|none|both*

* is the default when the item is omitted',
            $this->getDefaultConfiguration()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens[0]->isGivenKind(T_OPEN_TAG) && $tokens->isMonolithicPhp();
    }

    /**
     * Find the header comment index.
     *
     * @param Tokens $tokens
     * @param int    $headerNewIndex
     *
     * @return int|null
     */
    private function findHeaderCommentCurrentIndex(Tokens $tokens, $headerNewIndex)
    {
        $index = $tokens->getNextNonWhitespace($headerNewIndex);

        return null === $index || !$tokens[$index]->isComment() ? null : $index;
    }

    /**
     * Find the index where the header comment must be inserted.
     *
     * @param Tokens $tokens
     *
     * @return int
     */
    private function findHeaderCommentInsertionIndex(Tokens $tokens)
    {
        if ('after_open' === $this->configuration['location']) {
            return 1;
        }

        $index = $tokens->getNextMeaningfulToken(0);
        if (null === $index) {
            // file without meaningful tokens but an open tag, comment should always be placed directly after the open tag
            return 1;
        }

        if (!$tokens[$index]->isGivenKind(T_DECLARE)) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($index);
        if (null === $next || !$tokens[$next]->equals('(')) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($next);
        if (null === $next || !$tokens[$next]->equals(array(T_STRING, 'strict_types'), false)) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($next);
        if (null === $next || !$tokens[$next]->equals('=')) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($next);
        if (null === $next || !$tokens[$next]->isGivenKind(T_LNUMBER)) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($next);
        if (null === $next || !$tokens[$next]->equals(')')) {
            return 1;
        }

        $next = $tokens->getNextMeaningfulToken($next);
        if (null === $next || !$tokens[$next]->equals(';')) { // don't insert after close tag
            return 1;
        }

        return $next + 1;
    }

    /**
     * @param Tokens $tokens
     * @param int    $headerIndex
     */
    private function fixWhiteSpaceAroundHeader(Tokens $tokens, $headerIndex)
    {
        $lineEnding = $this->whitespacesConfig->getLineEnding();

        // fix lines after header comment
        $expectedLineCount = 'both' === $this->configuration['separate'] || 'bottom' === $this->configuration['separate'] ? 2 : 1;
        if ($headerIndex === count($tokens) - 1) {
            $tokens->insertAt($headerIndex + 1, new Token(array(T_WHITESPACE, str_repeat($lineEnding, $expectedLineCount))));
        } else {
            $afterCommentIndex = $tokens->getNextNonWhitespace($headerIndex);
            $lineBreakCount = $this->getLineBreakCount($tokens, $headerIndex + 1, null === $afterCommentIndex ? count($tokens) : $afterCommentIndex);
            if ($lineBreakCount < $expectedLineCount) {
                $missing = str_repeat($lineEnding, $expectedLineCount - $lineBreakCount);
                if ($tokens[$headerIndex + 1]->isWhitespace()) {
                    $tokens[$headerIndex + 1]->setContent($missing.$tokens[$headerIndex + 1]->getContent());
                } else {
                    $tokens->insertAt($headerIndex + 1, new Token(array(T_WHITESPACE, $missing)));
                }
            }
        }

        // fix lines before header comment
        $expectedLineCount = 'both' === $this->configuration['separate'] || 'top' === $this->configuration['separate'] ? 2 : 1;
        $lineBreakCount = $this->getLineBreakCount($tokens, $tokens->getPrevNonWhitespace($headerIndex), $headerIndex);
        if ($lineBreakCount < $expectedLineCount) {
            // because of the way the insert index was determined for header comment there cannot be an empty token here
            $tokens->insertAt($headerIndex, new Token(array(T_WHITESPACE, str_repeat($lineEnding, $expectedLineCount - $lineBreakCount))));
        }
    }

    /**
     * @param Tokens $tokens
     * @param int    $indexStart
     * @param int    $indexEnd
     *
     * @return int
     */
    private function getLineBreakCount(Tokens $tokens, $indexStart, $indexEnd)
    {
        $lineCount = 0;
        for ($i = $indexStart; $i < $indexEnd; ++$i) {
            $lineCount += substr_count($tokens[$i]->getContent(), "\n");
        }

        return $lineCount;
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     */
    private function insertHeader(Tokens $tokens, $index)
    {
        $tokens->insertAt($index, new Token(array('comment' === $this->configuration['commentType'] ? T_COMMENT : T_DOC_COMMENT, $this->configuration['header'])));
    }
}
