# kassis

本アプリ（kassis) は、PDFやらエクセルファイルなどを検索するためのものです。

### 概要

ファイルを読んで索引化し、ブラウザで検索することが出来ます。

サポートしているファイルは以下のフォーマットです。
- Excel (拡張子xlsx)
- Word (拡張子docx)
- PDF (拡張子pdf)
- Text (拡張子txt)

### 読み

「カシス」です。カシスオレンジ(Cassis and Orange)のカシスと同じです。

# 必要なもの

- Java Runtime Environment (JRE) version 1.8 以上
- Go 1.18
- OS： 以下動作確認済み
  - Windows系: Windows 11
  - Linux系: Ubuntu Server 22.04 LTS

MAC OS X 系については、確認環境が無いため不明ですが、たぶん動くかと。

以下のミドルウェアは、準備段階で本アプリがダウンロードします。
既存で構築していあるものに追加は可能です。
- Apache Solr 8.11.x
- Apache Tika 2.4.x (standard server版）

検索および結果を見るためにはブラウザが必要です。
- Google Chrome 最新版
（Firefox や Edge では確認していません）

# 環境構築

## 準備その１

### Ubuntu 22.04 LTS 向け

```shell
sudo apt update && sudo apt upgrade -y
sudo apt install -y openjdk-17-jre-headless
sudo apt install -y golang-go
sudo apt install -y git
```

JAVA_HOMEを設定してください。

### Windows 11 向け

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

## 準備その２

本アプリ（kassis） をダウンロードし、設定を行います。
Apache Tika と Apache Solr は、準備中にダウンロードするので別途ダウンロードする必要はありません。

```
git clone -b v0.1.0 https://github.com/nakamura-akifumi/kassis.git
cd kasis
go run cmd/configurator/main.go makeconfigset
go run cmd/configurator/main.go downloadapp
go run cmd/configurator/main.go startsolr
go run cmd/configurator/main.go setupsolr
go run cmd/configurator/main.go starttika
```

以下のコマンドを実行して、問題が無いか確認します。

```
go run cmd/configurator/main.go
```

# 実行

## ファイルのインポート

go run cmd/import/main.go <ファイル名もしくはフォルダ名>

## 検索および結果表示

go run cmd/webserver/main.go

ブラウザで localhost:1323 にアクセスします。

## 技術的内容

### solrのデータ再構築手順

```shell
.\tools\app\solr-8.11.2\bin\solr delete -c kassiscore_test
go run cmd/configurator/main.go setupsolr
```

### ディレクトリ構成図
- root
  - bin
  - config
  - tools
  - web
    - assets
    - public
    - views

### データフロー
（あとで記載予定）

# 実行ファイルの作成方法（ビルド方法）

make が動くこと。
実行ファイル（バイナリファイル）は、bin に作成されます。

## 準備（Windows の場合）

実行環境に加えて以下のものを準備する必要がある

  (windowsで実行する場合は以下のツールが必要)
- Git Bash
- Make for windows
http://gnuwin32.sourceforge.net/packages/make.htm

## 準備（Ubuntu 22.04 LTS の場合）

```
sudo apt install make 
```

## ビルド

windowsの場合は、git bash 上で実行してください。

```
make build 
```

## テスト

### Goでのテスト
```
go test github.com/nakamura-akifumi/kassis/...
```

### 実行ファイルをテスト

（あとで考える。自動テストしたい）

## 開発メモ

- DCNDL対応表
https://docs.google.com/spreadsheets/d/1KuHi0rj-0NL1ta5oZxP3PvGH_meK2cD4sfAjtMTfajA/edit#gid=0

- 開発内容一覧、ロードマップ
  https://docs.google.com/spreadsheets/d/1M6EUMdPJlRbo9A6nIU-483PFpbZe8WPBb7aFlBHBYe4/edit?usp=sharing

# Author, Contributor

Akifumi NAKAMURA (@tmpz84)

# LICENSE

MIT
