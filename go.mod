module tika

go 1.17

require (
	github.com/PuerkitoBio/goquery v1.8.0
	github.com/google/go-tika v0.2.0
	github.com/nakamura-akifumi/kassis/package/solr v0.0.0-00010101000000-000000000000
)

require (
	github.com/andybalholm/cascadia v1.3.1 // indirect
	github.com/hashicorp/go-cleanhttp v0.5.2 // indirect
	github.com/hashicorp/go-retryablehttp v0.7.0 // indirect
	github.com/mecenat/solr v1.3.2 // indirect
	golang.org/x/net v0.0.0-20211020060615-d418f374d309 // indirect
)

replace github.com/nakamura-akifumi/kassis/package/solr => ../solr
