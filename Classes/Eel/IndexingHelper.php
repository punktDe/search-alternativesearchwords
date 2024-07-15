<?php
declare(strict_types=1);

namespace PunktDe\Search\AlternativeSearchWords\Eel;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\ProtectedContextAwareInterface;
use PunktDe\Search\AlternativeSearchWords\TextTokenizer;

class IndexingHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        private TextTokenizer $textTokenizer,
    ) {
    }

    /**
     * @param string $input
     * @param NodeInterface $node
     * @return string[]
     */
    public function stopWordFilteredTokenize(string $input, NodeInterface $node): array
    {
        $language = current($node->getContext()->getDimensions()['language'] ?? ['']);
        if ($language === '') {
            return [];
        }

        return array_values($this->textTokenizer->tokenize($input, $language));
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
