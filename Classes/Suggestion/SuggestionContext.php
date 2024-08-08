<?php
declare(strict_types=1);

namespace PunktDe\Neos\AdvancedSearch\Suggestion;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use \Flowpack\SearchPlugin\Suggestion\SuggestionContext as FlowpackSuggestionContext;

class SuggestionContext extends FlowpackSuggestionContext
{

    public function buildForIndex(NodeInterface $node): self
    {
        $this->contextValues = [
            'siteName' => $this->getSiteName($node),
            'workspace' => $node->getWorkspace()->getName(),
        ];

        if ($node->isHidden() ||
            (bool)$node->getProperty('metaRobotsNoindex') === true ||
            $node->getNodeType()->isOfType('PunktDe.Neos.AdvancedSearch:Mixin.HiddenFromInternalSearch') ||
            (bool)$node->getProperty('internalSearchNoIndex') === true
        ) {
            $this->contextValues['isHidden'] = 'hidden';
        } else {
            $this->contextValues['isHidden'] = 'visible';
        }

        return $this;
    }
}
