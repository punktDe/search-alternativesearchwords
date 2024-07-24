<?php
declare(strict_types=1);

namespace PunktDe\Neos\AdvancedSearch\Tests\Functional;

use Neos\Flow\Tests\FunctionalTestCase;
use PunktDe\Neos\AdvancedSearch\TextTokenizer;

class TextTokenizerTestCase extends FunctionalTestCase
{
    protected ?TextTokenizer $textTokenizer = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->textTokenizer = $this->objectManager->get(TextTokenizer::class);
    }

    /**
     * @return mixed[]
     */
    public function stringDataProvider(): array
    {
        return [
            'de_simpleTitle' => [
                'language' => 'de',
                'input' => 'CGM MEDIKAMENTEN-AMPEL',
                'expected' => ['MEDIKAMENTEN-AMPEL']
            ],
            'en_pureNumbersAreRemoved' => [
                'language' => 'en',
                'input' => 'The year 2022',
                'expected' => ['year'],
            ],
            'gen_shortTokens' => [
                'language' => 'en',
                'input' => 'ax ox fo ePA',
                'expected' => ['ePA'],
            ],
            'de_punctuations' => [
                'language' => 'de',
                'input' => '„TI erleben – Info-Forum rund um ePA, eAU, E-Rezept, KIM & CO“: CGM MEDISTAR ist dabei',
                'expected' => ['erleben', 'Info-Forum', 'ePA', 'eAU', 'E-Rezept', 'KIM', 'MEDISTAR'],
            ],
            'fr_punctation' => [
                'language' => 'fr',
                'input' => 'CompuGroup Medical « engagé pour la e-santé » signe la charte du Ministère des Solidarités et de la Santé',
                'expected' => ['engagé', 'e-santé', 'signe', 'charte', 'Ministère', 'Solidarités', 'Santé'],
            ],
            'unconfigured_language' => [
                'language' => 'xx',
                'input' => 'Should not tokenize anything',
                'expected' => [],
            ],
            'filter-unicode-characters' => [
                'language' => 'de',
                'input' => '✔ 🤦🏻‍',
                'expected' => [],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider stringDataProvider
     *
     * @param string $language
     * @param string $input
     * @param string[] $expected
     */
    public function tokenize(string $language, string $input, array $expected): void
    {
        self::assertEquals(array_values($expected), array_values($this->textTokenizer->tokenize($input, $language)));
    }
}
