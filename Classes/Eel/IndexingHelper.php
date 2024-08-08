<?php
declare(strict_types=1);

namespace PunktDe\Neos\AdvancedSearch\Eel;

use Flowpack\SearchPlugin\EelHelper\SuggestionIndexHelper;
use Flowpack\SearchPlugin\Exception;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Eel\ProtectedContextAwareInterface;
use PunktDe\Neos\AdvancedSearch\NodeTypeDefinitionInterface;
use PunktDe\Neos\AdvancedSearch\TextTokenizer;


class IndexingHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        private readonly TextTokenizer         $textTokenizer,
        private readonly SuggestionIndexHelper $suggestionIndexHelper,
    ) {
    }

    /**
     * @param NodeInterface $node
     * @param string[] $properties
     * @return string
     * @throws NodeException
     */
    public function indexCompletionIfSearchable(NodeInterface $node, array $properties): string
    {
        if ($node->getNodeType()->isOfType(NodeTypeDefinitionInterface::MIXIN_HIDDEN_FROM_INTERNAL_SEARCH)) {
            return '';
        }

        return implode(' ', $this->stopWordFilteredTokenize(implode(' ',$this->extractNodeProperties($properties, $node)), $node));
    }

    /**
     * @param NodeInterface $node
     * @param string[] $properties
     * @param int $weight
     * @return string[]
     * @throws NodeException
     * @throws Exception
     */
    public function indexSuggestionIfSearchable(NodeInterface $node, array $properties, int $weight = 1): array
    {
        if ($node->isHidden() || $node->getNodeType()->isOfType(NodeTypeDefinitionInterface::MIXIN_HIDDEN_FROM_INTERNAL_SEARCH)) {
            return [];
        }
        return $this->suggestionIndexHelper->build($this->stopWordFilteredTokenize(implode(' ',$this->extractNodeProperties($properties, $node)), $node), $weight);
    }

    /**
     * @param string[] $properties
     * @param NodeInterface $node
     * @return string[]
     * @throws NodeException
     */
    private function extractNodeProperties(array $properties, NodeInterface $node): array
    {
        $completionContent = [];

        foreach ($properties as $propertyName) {
            if (is_string($node->getProperty($propertyName))) {
                $completionContent[] = strip_tags($node->getProperty($propertyName));
            }
        }
        return $completionContent;
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
