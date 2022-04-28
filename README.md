# kassis orange

PDFやらエクセルファイルなどを検索するためのアプリです。

# 必要なもの

- Java Runtime Environment (JRE) version 1.8 or higher
- Apache Solr 8.11
  - https://www.apache.org/dyn/closer.lua/lucene/solr/8.11.1/solr-8.11.1.zip?action=download
- Apache Tika 2.3.0
  - https://www.apache.org/dyn/closer.lua/tika/2.3.0/tika-server-standard-2.3.0.jar
- OS：
  - Windows系: Windows 11（テスト確認済み）
  - Linux系: Ubuntu 20.04

# 実行環境の構築方法 

windowsで実行する場合は、curl を curl.exe と置き換えてください。

SolrとApacheTikaは、tools/archivesにあるものとします。

```
$ ./bin/solr start
$ ./bin/solr create_core -c kassiscore -d _default
$ ./bin/solr config -c kassiscore -p 8983 -action set-user-property -property update.autoCreateFields -value false
$ curl -X POST -H 'Content-type:application/json' --data-binary @tools/kassis-solr-schema.json  http://localhost:8983/solr/kassiscore/schema
```

# 実行方法

```
$ javaw -jar tools/archives/tika-server-standard-2.3.0.jar 
$ go run cmd/import/main.go
```

# 開発およびビルド方法

実行環境に加えて以下のものを準備する必要がある

- Go 1.17以上（開発者は1.17.8で実装と単体試験）

## Windowsの場合

- Git Bash
- Make for windows
http://gnuwin32.sourceforge.net/packages/make.htm

## テスト

### Goでのテスト
```
go test github.com/nakamura-akifumi/kassis/...
```

### 実行ファイルをテスト

（あとで考える）

# LICENSE

MIT

# Author, Contributor

Akifumi NAKAMURA (@tmpz84)
