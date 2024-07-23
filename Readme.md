# Neos alternative searchwords

This package provides code for alternative words in elasticsearch based search in Neos.

## Integration 

Tokenize and ingest the alternatives within your document

```yaml
    esAlternativeSearchword:
        search:
            elasticSearchMapping:
                type: keyword
            indexing: "${PunktDe.Search.AlternativeSearchWords.stopWordFilteredTokenize(q(node).property('title') + ' ' + q(node).property('metaKeywords') + ' ' + q(node).property('metaDescription'), node)}"
```
