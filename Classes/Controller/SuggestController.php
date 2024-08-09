<?php
declare(strict_types=1);

namespace PunktDe\Neos\AdvancedSearch\Controller;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Flowpack\SearchPlugin\Suggestion\SuggestionContextInterface;
use Flowpack\SearchPlugin\Utility\SearchTerm;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Neos\Controller\CreateContentContextTrait;

class SuggestController extends ActionController
{
    use CreateContentContextTrait;

    protected ElasticSearchClient $elasticSearchClient;

    /**
     * @var ElasticSearchQueryBuilder
     */
    protected ElasticSearchQueryBuilder $elasticSearchQueryBuilder;

    /**
     * @var string[]
     */
    protected $viewFormatToObjectNameMap = [
        'json' => JsonView::class
    ];

    /**
     * @var string[]
     */
    #[Flow\InjectConfiguration(path: "searchAsYouType", package: "Flowpack.SearchPlugin")]
    protected array $searchAsYouTypeSettings = [];

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $elasticSearchQueryTemplateCache;

    public function __construct(
        private readonly SuggestionContextInterface $suggestionContext,
    ){
    }

    public function initializeObject(): void
    {
        if ($this->objectManager->isRegistered(ElasticSearchClient::class)) {
            $this->elasticSearchClient = $this->objectManager->get(ElasticSearchClient::class);
            $this->elasticSearchQueryBuilder = $this->objectManager->get(ElasticSearchQueryBuilder::class);
        }
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws QueryBuildingException
     * @throws \JsonException
     */
    public function indexAction(string $term = '', string $contextNodeIdentifier = '', string $dimensionCombination = null): void
    {
        if ($this->elasticSearchClient === null) {
            throw new \RuntimeException('The SuggestController needs an ElasticSearchClient, it seems you run without the flowpack/elasticsearch-contentrepositoryadaptor package, though.', 1487189823);
        }

        $result = [
            'completions' => [],
            'suggestions' => []
        ];

        if (!is_string($term)) {
            $result['errors'] = ['term has to be a string'];
            $this->view->assign('value', $result);
            return;
        }

        $requestJson = $this->buildRequestForTerm($term, $contextNodeIdentifier, $dimensionCombination);

        try {
            $response = $this->elasticSearchClient->getIndex()->request('POST', '/_search', [], $requestJson)->getTreatedContent();
            $result['completions'] = $this->extractCompletions($response);
            $result['suggestions'] = $this->extractSuggestions($response);
        } catch (\Exception $e) {
            $result['errors'] = ['Could not execute query: ' . $e->getMessage()];
        }

        $this->view->assign('value', $result);
    }

    /**
     * @throws QueryBuildingException
     * @throws IllegalObjectTypeException
     * @throws \JsonException
     */
    protected function buildRequestForTerm(string $term, string $contextNodeIdentifier, string $dimensionCombination = null): string
    {
        $cacheKey = $contextNodeIdentifier . '-' . md5($dimensionCombination);
        $termPlaceholder = '---term-soh2gufuNi---';
        $firstWordTermPlaceholder = '---term-dae5kaJ1ie---';

        $term = strtolower($term);

        // The suggest function only works well with one word
        // special search characters are escaped
        $firstWordSuggestTerm = SearchTerm::sanitize(explode(' ', $term)[0]);
        $suggestTerm = SearchTerm::sanitize($term);

        if (!$this->elasticSearchQueryTemplateCache->has($cacheKey)) {
            $contentContext = $this->createContentContext('live', $dimensionCombination ? json_decode($dimensionCombination, true, 512, JSON_THROW_ON_ERROR) : []);
            $contextNode = $contentContext->getNodeByIdentifier($contextNodeIdentifier);

            $sourceFields = array_filter($this->searchAsYouTypeSettings['suggestions']['sourceFields'] ?? ['neos_path']);

            /** @var ElasticSearchQueryBuilder $query */
            $query = $this->elasticSearchQueryBuilder
                ->query($contextNode)
                ->queryFilter('prefix', [
                    'neos_completion' => $termPlaceholder
                ])
                ->limit(0);

            if (($this->searchAsYouTypeSettings['autocomplete']['enabled'] ?? false) === true) {
                $query->aggregation('autocomplete', [
                    'terms' => [
                        'field' => 'neos_completion',
                        'order' => [
                            '_count' => 'desc'
                        ],
                        'include' => $termPlaceholder . '.*',
                        'size' => $this->searchAsYouTypeSettings['autocomplete']['size'] ?? 10
                    ]
                ]);
            }

            if (($this->searchAsYouTypeSettings['suggestions']['enabled'] ?? false) === true) {
                $query->suggestions('suggestions', [
                    'prefix' => $firstWordTermPlaceholder,
                    'completion' => [
                        'field' => 'neos_suggestion',
                        'fuzzy' => true,
                        'size' => $this->searchAsYouTypeSettings['suggestions']['size'] ?? 10,
                        'contexts' => [
                            'suggestion_context' => $this->suggestionContext->buildForSearch($contextNode)->getContextIdentifier(),
                        ]
                    ]
                ]);
            }

            $request = $query->getRequest()->toArray();

            $request['_source'] = $sourceFields;

            $requestTemplate = json_encode($request, JSON_THROW_ON_ERROR);

            $this->elasticSearchQueryTemplateCache->set($contextNodeIdentifier, $requestTemplate);
        } else {
            $requestTemplate = $this->elasticSearchQueryTemplateCache->get($cacheKey);
        }
        return str_replace([$termPlaceholder, $firstWordTermPlaceholder], [$suggestTerm, $firstWordSuggestTerm], $requestTemplate);
    }

    /**
     * @param array<string, mixed> $response
     * @return array
     */
    protected function extractCompletions(array $response): array
    {
        $aggregations = $response['aggregations'] ?? [];

        return array_map(static function ($option) {
            return $option['key'];
        }, $aggregations['autocomplete']['buckets']);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    protected function extractSuggestions(array $response): array
    {
        $suggestionOptions = $response['suggest']['suggestions'][0]['options'] ?? [];

        if (empty($suggestionOptions)) {
            return [];
        }

        return array_map(static function ($option) {
            return $option['_source'];
        }, $suggestionOptions);
    }
}
