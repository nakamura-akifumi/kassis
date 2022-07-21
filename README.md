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

## Ubuntu 22.04 LTS 向け

### 準備

```shell
sudo apt update && sudo apt upgrade -y
sudo apt install -y openjdk-17-jre-headless
sudo apt install -y golang-go
sudo apt install -y git
git clone https://github.com/nakamura-akifumi/kassis.git
cd kasis
go run cmd/configrator/main.go -generate-default-configset
go run cmd/configrator/main.go -download-app
go run cmd/configrator/main.go -start-solr
go run cmd/configrator/main.go -setup-solr
go run cmd/configrator/main.go -start-tika
```

## Windows 11

### 準備

- JDK のインストール
  - 以下のページより JDK 17 をダウンロードする（ファイル名：jdk-17.0.3.1_windows-x64_bin.msi）
    - https://www.oracle.com/java/technologies/javase/jdk17-archive-downloads.html
  - インストールする
  - JAVA_HOMEを設定する
  - PATHを設定する
- Go のインストール
  - 以下のページより Go 1.18 をダウンロードする（ファイル名：go1.18.4.windows-amd64.msi)
    - https://go.dev/dl/
  - インストールする
  - PATHを設定する
- git for windows のインストール
  - 以下のページより git for windows をダウンロードする（ファイル名：Git-2.37.1-64-bit.exe）
    - https://gitforwindows.org/
  - インストールする
- ここまでにインストールしたアプリケーションがインストールされているかを確認する。
  - コマンドプロンプトを起動し、java と go が起動するか確認する。

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
