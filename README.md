# kassisV2

kassisV2 は、書籍などを管理するアプリです。

## できること

- ISBNからの取り込み(国立国会図書館サーチ APIからの取得 https://ndlsearch.ndl.go.jp/api/sru)
- Amazonの購入履歴ファイル(Your Orders.zip)からの取り込み
- CSVまたはエクセル形式でのインポート
- CSVまたはエクセル形式でのエクスポート
- 検索および検索結果の表示
- ファイル添付機能（資料に対して添付ファイルのアップロード）
- 棚卸機能
- 予約貸出返却機能

## 必要なミドルウェア等

PHP 8.3以上

## 動作環境の構築方法

### さくらインターネット

エクセルファイルやAmazonの購入履歴ファイル(Your Order.zip)からデータを取り込む場合は、
設定を変更する必要があります。ファイルサイズを確認してください。
該当する php.ini の設定を変更してください。

``` /etc/php/8.4/cli/php.ini
upload_max_filesize = 50M
post_max_size = 60M
```

あわせて、大きめファイルだと処理時間も伸びるので必要に応じて修正してください。

``` /etc/php/8.4/cli/php.ini
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

アプリのルートフォルダに .env.local を新規作成して、以下のようにDB接続先を記載。

```
DATABASE_URL="mysql://kassisv2:kassisv2passwd@localhost:3306/kassisv2db?serverVersion=8.0.32&charset=utf8mb4"
```

```
MAILER_DSN="smtp://YOUR_GMAIL_ADDRESS:APP_PASSWORD@smtp.gmail.com:587"
MAILER_FROM_EMAIL="your.address@gmail.com"
MAILER_FROM_NAME="Library"

MAILER_DSN="smtp://example.user%40gmail.com:ab%20cd%20ef%20gh%20ij%20kl%20mn%20op@smtp.gmail.com:587"
MAILER_FROM_EMAIL="example.user@gmail.com"
MAILER_FROM_NAME="Library"
```

Googleアカウントのセキュリティ設定を開く
画面上部にある**「設定を検索」**という検索バーに、直接 「アプリ パスワード」 と入力してください。
候補に出てくる「アプリ パスワード」をクリックします。

アプリ名: 自由に決めてOK（例: kassis）

作成: ボタンを押すと 16桁のコード（例: abcd efgh ijkl mnop）が表示されます。

php bin/console mailer:test foobar@example.com

Slack
Incoming Webhook

送信したいチャンネルにIncomingWebhookを追加する。
Webhook URL が表示されるので、.env.local に設定する。
例：
https://hooks.slack.com/services/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX

### Ubuntu 24.04 LTS

sudo apt install php8.4-mysql
sudo apt install php8.4-zip php8.4-intl php8.4-mbstring
sudo apt install php8.4-xml php8.4-xmlwriter php8.4-simplexml php8.4-dom
sudo apt install php8.4-sqlite3 


composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

### DBスキーマおよびインポートマッピング

https://docs.google.com/spreadsheets/d/17KYRc5HVzcFlP_6cMGh88QEuyNG9tBeC0ikRgiFZC74/edit?usp=sharing

### 参考情報として開発環境

Ubuntu 24.04 LTS
PHP 8.4.15 + Mysql 8.0.44 で開発
E2Eテスト用に node.js 18.9.1

## その他開発時メモ

### DBとか
sudo mysql -u root
CREATE USER 'kassisv2'@'localhost' IDENTIFIED BY 'kassisv2passwd';
CREATE DATABASE kassisv2db;
GRANT ALL ON kassisv2db.* TO 'kassisv2'@'localhost';

DROP DATABASE kassisv2db;

sudo apt install nodejs npm
npm init playwright@latest

npx playwright test

簡易Webサーバで起動する
symfony server:start --allow-all-ip

## テンプレートキャッシュをクリアする

php bin/console cache:clear --no-warmup
php bin/console cache:warmup
