# kassis orange

PDFやらエクセルファイルなどを検索するためのアプリです。

# 必要なもの

Java 8 以上
Apache Solr 8.11
Apache Tika 2.3

# 構築方法 (Ubuntu)

$ ./bin/solr start
$ ./bin/solr create_core -c kassiscore -d _default
$ ./bin/solr config -c kassiscore -p 8983 -action set-user-property -property update.autoCreateFields -value false
$ curl -X POST -H 'Content-type:application/json' --data-binary @ext/kassis-solr-schema.json  http://localhost:8983/solr/kassiscore/schema

# データ登録方法



# LICENSE

MIT

# Author, Contributor

Akifumi NAKAMURA (@tmpz84)
