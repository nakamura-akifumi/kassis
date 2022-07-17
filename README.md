# kassis

PDFやらエクセルファイルなどを検索するためのアプリです。

# 必要なもの

- Java Runtime Environment (JRE) version 1.8 以上
- Apache Solr 8.11.x
- Apache Tika 2.4.x
- OS： 以下動作確認済み
  - Windows系: Windows 11
  - Linux系: Ubuntu Server 22.04 LTS
- Go 1.18.x

# 実行環境の構築方法 

## Ubuntu Server 22.04 LTS 

### 準備

```shell
sudo apt-get update
sudo apt install openjdk-17-jre-headless
sudo apt install golang-go
git clone https://github.com/nakamura-akifumi/kassis.git
cd kasis
go run cmd/configrator/main.go -generate-default-configset
go run cmd/configrator/main.go -download-app
go run cmd/configrator/main.go -start-solr
go run cmd/configrator/main.go -setup-solr
```

## Windows 11

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
