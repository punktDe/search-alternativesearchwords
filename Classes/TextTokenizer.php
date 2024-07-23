<?php
declare(strict_types=1);

namespace PunktDe\Search\AlternativeSearchWords;

use Neos\Utility\Files;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class TextTokenizer
{
    /**
     * @var string[]
     */
    #[Flow\InjectConfiguration(path: "stopWordFolders", package: "PunktDe.Search.AlternativeSearchWords")]
    protected array $stopWordFolders = [];

    private const REMOVE_CHARACTERS = ['„', '“', '«', '»', ')', '(', '!', '?', '&'];
    private const WORD_RIGHT_TRIM_CHARACTERS = "\n\t.:,";

    /**
     * @var string[][]
     */
    private array $stopWords = [];

    /**
     * @var bool[]
     */
    private array $loadedStopWordFiles = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function initializeObject(): void
    {
        if (is_dir($this->stopWordFolders['customerSpecificFolder'] ?? '')) {
            foreach (Files::readDirectoryRecursively($this->stopWordFolders['customerSpecificFolder']) as $file) {
                $this->loadStopWordsFromFile($file);
            }
        }
    }

    /**
     * @param string $input
     * @param string $languageCode
     * @param int $minWordLength
     * @return string[]
     */
    public function tokenize(string $input, string $languageCode, int $minWordLength = 3): array
    {
        $languageCodeFilePath = Files::concatenatePaths([$this->stopWordFolders['languageFolder'], $languageCode . '.txt']);

        if (!$this->loadStopWordsFromFile($languageCodeFilePath)) {
            $this->logger->warning(sprintf('Could not load stopWords for language %s from file: %s', $languageCode, $languageCodeFilePath));
            return [];
        }

        $tokens = explode(' ', $input);

        $tokens = array_map(function ($token) {
            return $this->removeEmoji(rtrim(str_replace(self::REMOVE_CHARACTERS, '', $token), self::WORD_RIGHT_TRIM_CHARACTERS));
        }, $tokens);

        return array_filter($tokens, function ($token) use ($minWordLength) {
            return
                $token !== ''
                && !is_numeric($token)
                && mb_strlen($token) >= $minWordLength
                && !array_key_exists(strtolower($token), $this->stopWords);
        });
    }

    private function removeEmoji(string $input): string
    {
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clearString = preg_replace($regexEmoticons, '', $input);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clearString = preg_replace($regex_symbols, '', $clearString);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clearString = preg_replace($regex_transport, '', $clearString);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clearString = preg_replace($regex_misc, '', $clearString);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        return preg_replace($regex_dingbats, '', $clearString);
    }

    private function loadStopWordsFromFile(string $filePath): bool
    {
        if (array_key_exists($filePath, $this->loadedStopWordFiles)) {
            return $this->loadedStopWordFiles[$filePath];
        }

        if (!file_exists($filePath)) {
            return $this->loadedStopWordFiles[$filePath] = false;
        }

        $this->stopWords = array_merge($this->stopWords, array_flip(array_filter(file($filePath, FILE_IGNORE_NEW_LINES))));

        return $this->loadedStopWordFiles[$filePath] = true;
    }
}
