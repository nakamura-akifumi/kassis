# kassis

PDFやらエクセルファイルなどを検索するためのアプリです。

# 必要なもの

- Java Runtime Environment (JRE) version 1.8 or higher
- Apache Solr 8.11.2
- Apache Tika 2.4.1
- OS：
  - Windows系: Windows 11（テスト確認済み）
  - Linux系: Ubuntu 20.04

# 実行環境の構築方法 

(TODO: SolrとTikaの構築方法)

- Apache Solr と Apache Tika (server版）は、起動しているものとし、利用ポートは、それぞれ 8983 と 9998 とします。
- 実行ファイルをダウンロードし圧縮ファイルから展開しておきます。 展開先は、例として C:\tools\kassis とします。プラットフォームに合わせて読み替えてください。

（圧縮ファイルのダウンロード先はリリース時に記載）

```shell
cd c:\tools\kassis
.\bin\configurator -generate-default-configset
.\bin\configurator -download-app
.\bin\configurator -start-solr
.\bin\configurator -setup-solr
```

## 開発中のメモ
solrのデータ再構築手順

```shell
.\bin\solr delete -c kassiscore
.\bin\configurator -setup-solr
```

（ディレクトリ構成図を書く）
- root
  - bin
  - config
  - tools
  - web
    - assets
    - public
    - views

# 実行方法

```
$ go run cmd/import/main.go
```

# 開発およびビルド方法

実行環境に加えて以下のものを準備する必要がある

- Go 1.17以上（開発者は1.17.8で実装と単体試験）
  (windowsで実行する場合は以下のツールが必要)
- Git Bash
- Make for windows
http://gnuwin32.sourceforge.net/packages/make.htm

## Windows以外の場合

```
make  
```

## テスト

### Goでのテスト
```
go test github.com/nakamura-akifumi/kassis/...
```

### 実行ファイルをテスト

（あとで考える。自動テストしたい）

# Author, Contributor

Akifumi NAKAMURA (@tmpz84)

# LICENSE

MIT
