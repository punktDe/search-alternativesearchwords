<?php
declare(strict_types=1);

namespace PunktDe\Neos\AdvancedSearch\Aspect\ESCRA;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameService;
use Flowpack\ElasticSearch\Exception;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class DetermineConfigurationAspect
{

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @Flow\Around("method(Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer->getIndex())")
     *
     * @param JoinPointInterface $joinPoint The current joinPoint
     * @return Index
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws ConfigurationException
     * @throws Exception
     */
    public function getIndex(JoinPointInterface $joinPoint): Index
    {
        /** @var NodeIndexer $proxy */
        $proxy = $joinPoint->getProxy();

        $indexName = $proxy->getIndexName();
        $index = $this->searchClient->findIndex($indexName);

        $splitIndexName = explode(IndexNameService::INDEX_PART_SEPARATOR, $indexName);
        $genericConfigurationKey = current(explode('_', $indexName));
        $languageSpecificConfigurationKey = str_replace($splitIndexName[0], $genericConfigurationKey, $this->searchClient->getIndexName());

        $perDimensionConfiguration = $this->indexConfiguration[$this->searchClient->getBundle()][$languageSpecificConfigurationKey] ?? null;
        if ($perDimensionConfiguration !== null) {
            $index->setSettingsKey($languageSpecificConfigurationKey);
        } else {
            $index->setSettingsKey($genericConfigurationKey);
        }

        return $index;
    }
}
