# MockAPI-PHP

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![GitHub release](https://img.shields.io/github/v/release/ka215/MockAPI-PHP)
![GitHub issues](https://img.shields.io/github/issues/ka215/MockAPI-PHP)
![GitHub last commit](https://img.shields.io/github/last-commit/ka215/MockAPI-PHP)

このツールは **PHP開発者向けの軽量モックAPIサーバー** で、迅速なプロトタイピング、テスト、APIファースト開発向けに設計されています。
MockAPI-PHPを使用すると、実際のバックエンドを必要とせずに、JSONまたはテキストファイルを使用してRESTful APIレスポンスをシミュレートできます。

> レスポンスデータに基づくOpenAPI 3.0スキーマの自動生成をサポートします。

<div align="right"><small>

[View English version of README](./README.md)

</small></div>

---

## このツールを使用する理由

- シンプルかつ迅速にセットアップできる **ローカルモック API サーバー** が必要な場合。
- **ファイルベースのモック** が必要な場合（GUI やコードのコンパイルは不要）。
- **柔軟な動的レスポンス**、カスタム遅延、またはエラーシミュレーションが必要な場合。
- 実際のモックレスポンスから **OpenAPI 仕様を自動生成** したい場合。

## 最適な用途

- フロントエンドとバックエンドの統合を構築またはテストする PHP 開発者。
- 実サーバーに依存せずに API レスポンスをテストする QA チーム。
- Swagger、Prism、Postman などのツールを使用して **API ファーストワークフロー** を使用するチーム。

## 特徴

- **エンドポイントの自動登録**
  - `responses/` 以下のフォルダをスキャンし、ディレクトリ構造に応じたエンドポイントを自動登録。
  - APIのベースパスを環境変数で設定可能。
  - `GET users/{group}/{limit}` のような動的パラメータのエンドポイント解析にも対応。
- **レスポンスファイルの動的読み込み**
  - `.json` → JSONレスポンスとして返却。
  - `.txt`  → テキストレスポンスとして返却。
- **ポーリング対応**
  - `1.json`, `2.json` など複数ファイルを用意すればリクエスト回数に応じてレスポンスを変更できる。
  - ポーリングはクライアント毎に実行され、 `POST: /reset_polling` リクエストでリセットできる。
- **カスタムレスポンス**
  - クエリパラメータ `mock_response` を指定することでレスポンスを動的に切り替え可能。
    例: `GET /users?mock_response=success` → `responses/users/get/success.json` を取得。
  - クエリパラメータ `mock_content_type` を指定することでレスポンスの ContentType を指定可能。
    例: `GET /others?mock_response=xml&mock_content_type=application/xml` → `responses/others/get/xml.txt` をXML形式で取得。
- **エラーレスポンス**
  - `responses/errors/404.json` などのエラーレスポンスを定義可能。
- **レスポンスの遅延**
  - JSONファイル内に `"mockDelay: 1000"` （例: 1秒）と設定すると応答を遅らせることができる。
- **カスタムフック**
  - メソッド+エンドポイントの任意のリクエスト毎にカスタムフックを登録してレスポンス内容をオーバーライドできる。
    例: `GET /users` のリクエストに対して `hooks/get_users.php` のカスタムフックファイルを実行してレスポンスを制御可能。
- **OpenAPIスキーマへの対応**
  - JSONレスポンスを元に OpenAPI 3.0 スキーマを自動生成する機能を搭載。
  - `example` 項目も適切にトリミングされて埋め込まれるため、過剰なレスポンスデータを回避。
  - バリデーションには `opis/json-schema` を使用し、スキーマの整合性チェックも自動化。
- **ロギング**
  - `request.log` にリクエスト内容（ヘッダー・クエリ・ボディ）を記録。
  - `response.log` にレスポンス内容を記録。
  - `auth.log` に認証エラー、 `error.log` に汎用エラーの内容を記録。
  - 全てのログはリクエストIDにより紐づけられるため、照合可能。
  - OpenAPIスキーマ自動生成時のバリデーションエラーは `validation-error.log` でトラッキング可能。
- **環境変数による設定保存**
  - 環境変数の読み込みには `vlucas/phpdotenv` を使用。
  - `.env` を使い、ポート番号 `PORT` 等の各種環境変数を管理可能。
  - APIのベースパス、一時ファイル（`cookies.txt` など）の保存ディレクトリ、ログ出力パス等を指定可能。
  - `API_KEY` や `CREDENCIAL` を利用した簡易的な認証機能を実装可能。

## ディレクトリ構成

以下 `responses` ディレクトリ内はあくまで参考例です。利用ケースに準じて自由にカスタマイズ可能です。
```
mock_api_server/
 ├── index.php             # モックサーバーのメインスクリプト
 ├── generate-schema.php   # OpenAPI 3.0 スキーマ生成スクリプト
 ├── http_status.php       # HTTPステータスコードの定義
 ├── start_server.php      # ローカルサーバー起動スクリプト
 ├── .env                  # 設定用（ .env.sample を参考に設定）
 ├── vendor/               # Composer のパッケージ
 ├── composer.json         # PHPパッケージ管理用
 ├── composer.lock         # Composer のロックファイル
 ├── responses/            # レスポンスデータ格納ディレクトリ（下記は初期バンドル構成）
 │   ├── users/
 │   │   ├── get/
 │   │   │   ├── 1.json        # 1回目のリクエスト用レスポンス
 │   │   │   ├── 2.json        # 2回目のリクエスト用レスポンス
 │   │   │   ├── default.json  # デフォルトレスポンス
 │   │   │   └── delay.json    # 遅延レスポンス
 │   │   ├── delete/
 │   │   │   └── default.json  # DELETE時のレスポンス
 │   │   └── post/
 │   │        ├── 400.json      # 400エラーのレスポンス
 │   │        ├── failed.json   # POST失敗時のレスポンス
 │   │        └── success.json  # POST成功時のレスポンス
 │   ├── errors/
 │   │   ├── 404.json           # 404エラーレスポンス（JSON形式）
 │   │   └── 500.txt            # 500エラーのレスポンス（テキスト形式）
 │   └── others/
 │        ├── products/
 │        │   └── put/
 │        │        └── default.json # PUT時のレスポンス
 │        └── get/
 │             ├── default.txt   # CSV形式のテキストデータ
 │             └── userlist.txt  # XML形式のテキストデータ
 ├── hooks/                # カスタムフック格納ディレクトリ
 ├── tests/                # ユニットテスト用のテストケース格納ディレクトリ
 │   └── MockApiTest.php   # 初期テストケース
 ├── phpunit.xml           # ユニットテスト設定ファイル
 ├── version.json          # プロジェクトパッケージのバージョン情報
 ├── schema/               # OpenAPI スキーマ出力ディレクトリ
 └── logs/                 # ログ保存ディレクトリ（.envで変更可能）
      ├── auth.log         # 認証エラーのログ
      ├── error.log        # エラーログ
      ├── request.log      # リクエストのログ
      ├── response.log     # レスポンスのログ
      └── validation-error.log   # OpenAPI スキーマバリデーションエラーログ
```

## 動作環境

- PHP **8.3以上**
- Composer

## 使い方

### 1. Composer のインストール

```bash
composer install
```

### 2. サーバーの起動方法
Mock API Server を起動するには、以下のいずれかの方法を利用してください。

**推奨: `start_server.php` を使用**
このスクリプトを使うと、環境変数 `.env` で指定した `PORT` を自動で反映し、`temp/` 内の `.txt` ファイルもクリアされます。
```bash
php start_server.php
```

**手動で PHP 内蔵サーバーを起動**
```bash
php -S localhost:3030 -t .
```

### 3. APIリクエスト例
- **GETリクエスト**
  ```bash
  curl -X GET http://localhost:3030/api/users
  ```
- **GETリクエスト（ポーリング対応）**
  ```bash
  curl -b temp/cookies.txt -c temp/cookies.txt -X GET http://localhost:3030/api/users
  ```
- **POSTリクエスト**
  ```bash
  curl -X POST http://localhost:3030/api/users -H "Content-Type: application/json" -d '{"name": "New User"}'
  ```
- **PUTリクエスト（データ更新）**
  ```bash
  curl -X PUT http://localhost:3030/api/users/1 -H "Content-Type: application/json" -d '{"name": "Updated Name"}'
  ```
- **DELETEリクエスト**
  ```bash
  curl -X DELETE http://localhost:3030/api/users/1
  ```
- **カスタムレスポンス**
  ```bash
  curl -X GET "http://localhost:3030/api/users?mock_response=success"
  ```
- **バージョン確認用**
  ```bash
  curl -X GET http://localhost:3030/api/version
  ```

### 4. `responses/` の設定方法
モックAPIのレスポンスは `responses/` ディレクトリ内に JSON もしくはテキストファイルとして保存します。

- **レスポンスの構成例**
  ```
  responses/
  ├── products/
  │   ├── get/
  │   │   ├── default.json # デフォルトレスポンス（3～8回目と10回目以降のレスポンス）
  │   │   ├── 1.json # 1回目のリクエストで返すレスポンス
  │   │   ├── 2.json # 2回目のリクエストで返すレスポンス
  │   │   └── 9.json # 9回目のリクエストで返すレスポンス
  │   ├── post/
  │   │   ├── success.json # Product作成成功時のレスポンス
  │   │   └── 400.json # バリデーションエラー時のレスポンス
  │   ├── patch/
  │   │   └── success.json # Product更新成功時のレスポンス
  │   ├── delete/
  │   │   └── success.json # Product削除成功時のレスポンス
  │   └─…
  └─…
  ```

- **エラーレスポンスの設定**
  例: `responses/errors/404.json`
  ```json
  {
    "error": "Resource not found",
    "code": 404
  }
  ```
  例: `responses/errors/500.txt`
  ```
  Internal Server Error
  ```

## 環境変数（.env 設定）

プロジェクト内の `.env` に環境変数を設定することで、各種動作をカスタマイズできます。
パッケージにバンドルされている `.env.sample` がテンプレートとなります。
```env
PORT=3030             # モックAPIサーバーのポート番号
BASE_PATH=/api        # APIのベースパス（例: /api）
LOG_DIR=./logs        # ログ出力ディレクトリ
TEMP_DIR=./temp       # 一時ファイル（cookies.txtなど）の保存ディレクトリ
TIMEZONE=             # ロギング時のタイムゾーン（デフォルトはUTC）
API_KEY=              # 認証用APIキー（アプリケーション単位の簡易的な認証用で長期間有効）
CREDENCIAL=           # 資格情報（ユーザー単位等の単体認証用の期限付きトークン）
```
※ API_KEYとCREDENCIALオプションは本プロジェクトでは簡易的な実装となっており、指定時はリクエストのAuthorizationヘッダからBearerトークンを取得して認証処理が行われます。

## OpenAPI スキーマ自動生成機能
バージョン1.2以降にて `responses/{エンドポイントパス}/{メソッド}/{ステータス名}.json` に配置されたJSONレスポンスファイル群から OpenAPI 3.0 スキーマを自動生成する機能が追加されました。

### 実行方法
**CLI（コマンドライン）**
```bash
php generate-schema.php [format] [title] [version]
```

- `format` （任意）：出力フォーマット（`json` または `yaml`）デフォルト：`yaml`
- `title` （任意）： OpenAPI のタイトル
- `version` （任意）： OpenAPI のバージョン

例：
```bash
php generate-schema.php yaml "My Awesome API" "2.0.0"
```

**Web（ブラウザ経由）**
```http
GET /generate-schema.php?format=json&title=My+Awesome+API&version=2.0.0
```

### 出力ファイル
`schema/openapi.yaml` または `schema/openapi.json` にスキーマが生成されます。

### バリデーション
各 JSON レスポンスファイルは、独自に生成されたスキーマに対して JSON Schema バリデーションを実施します。
不正な構造のレスポンスがあれば `logs/validation-error.log` にエラーメッセージを出力し、処理を中断します。

### example 自動登録
生成される OpenAPI スキーマには、example として元となった JSON の内容が登録されます。

- 配列の場合、最初の要素のみ が example に含まれます（肥大化回避のため）。
- ネストされた配列・オブジェクトにも再帰的に適用されます。

### 環境変数（.env）
`.env` に以下の環境変数を定義することで、デフォルト動作をカスタマイズできます。
```env
LOG_DIR=./logs
SCHEMA_DIR=./schema
SCHEMA_FORMAT=yaml
SCHEMA_TITLE=MockAPI-PHP Auto Schema
SCHEMA_VERSION=1.0.0
```

※ 環境変数で定義された `SCHEMA_FORMAT` `SCHEMA_TITLE` `SCHEMA_VERSION` よりもパラメータで指定した値が優先されます。

### スキーマ自動生成の活用シーン

- 手動で OpenAPI スキーマを書く手間を省きたい場合
- 実装より先にモックを作る「APIファースト」な開発方針に沿うケース
- 他ツール（Prism, SwaggerUI など）との連携に使いたい場合
- 自動テストとの連携でスキーマ整合性を確認したいとき

### CI/CD での自動スキーマ生成例（GitHub Actions）

`.github/workflows/generate-schema.yml`

```yaml
name: Generate OpenAPI Schema

on:
  push:
    paths:
      - 'responses/**'
      - 'generate-schema.php'

jobs:
  generate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev
      - run: php generate-schema.php json
      - name: Upload schema
        if: hashFiles('schema/openapi.yaml') != ''
        uses: actions/upload-artifact@v4
        with:
          name: openapi-schema
          path: schema/openapi.json
```

これにより、レスポンス変更時に常に最新のスキーマが生成・保存されるようになります。



## Tips

### カスタムレスポンス
クエリパラメータ `mock_response` を指定することで、動的にレスポンスを変更できます。

| リクエスト | 取得されるレスポンスファイル |
|------------|------------------------------|
| `GET /users` | `responses/users/get/default.json` |
| `GET /users?mock_response=success` | `responses/users/get/success.json` |
| `POST /users?mock_response=failed` | `responses/users/post/failed.json` |
| `POST /users?mock_response=400` | `responses/users/post/400.json` |

### レスポンスの遅延
JSON ファイル内に `mockDelay` を設定すると、レスポンスを遅延できます。
`responses/users/get/default.json`
```json
{
    "id": 1,
    "name": "John Doe",
    "mockDelay": 1000
}
```
→ 1秒後にレスポンスが返る

### クエリパラメータの取り扱い
- クエリパラメータは全て取得され、リクエストデータ（ `request_data['query_params']` ）に含まれます。
- `mock_response` と `mock_content_type` は内部で処理されるため、リクエストデータには含まれません。
- 例: `GET /users?filter=name&sort=asc` の場合、リクエストデータは以下のようになります：
  ```json
  {
    "query_params": {
      "filter": "name",
      "sort": "asc"
    },
    "body": {}
  }
  ```

### カスタム `Content-Type` の設定
デフォルトのレスポンスは `application/json` もしくは `text/plain` ですが、任意の `Content-Type` を指定することも可能です。

#### CSVファイルを返す場合
レスポンスとして `responses/others/get/default.txt` を登録（内容は下記参照）。
```csv
id,name,email
1,John Doe,john@example.com
2,Jane Doe,jane@example.com
```
リクエストとして `GET others?mock_content_type=text/csv` を呼び出すことで `others.csv` が取得できます（ブラウザ経由であればダウンロードされます）。

#### XMLファイルを返す場合
レスポンスとして `responses/others/get/userlist.txt` を登録（内容は下記参照）。
```xml
<users>
    <user>
        <id>1</id>
        <name>John Doe</name>
    </user>
    <user>
        <id>2</id>
        <name>Jane Doe</name>
    </user>
</users>
```
リクエストとして `GET others?mock_response=userlist&mock_content_type=application/xml` を呼び出すことでXML形式のデータを取得できます。

### カスタムフック
特定のメソッド+エンドポイントに対して既定のレスポンスを返す前にカスタム処理をフックさせることができる機能です。
`hooks/{メソッド}_{エンドポイントのスネークケース文字列}.php` のファイルを設置することで有効化されます。
例: `GET users` のエンドポイント用カスタムフック `hooks/get_users.php`
```php
<?php

// 例: GET メソッドでエンドポイントが `/users` の場合にフック
if (isset($request_data['query_params'])) {
    $filter = $request_data['query_params']['filter'] ?? null;
    // クエリパラメータに `filter` が指定されていた場合
    if ($filter) {
        $sort = strtolower($request_data['query_params']['sort']) === 'desc' ? 'desc' : 'asc';
        header('Content-Type: application/json');
        echo json_encode([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Alice',
                    'age' => 24,
                ],
                [
                    'id' => 2,
                    'name' => 'Bob',
                    'age' => 27,
                ],
            ],
        ]);
        // レスポンスを返したらスクリプトを終了
        exit;
    }
}
```
`GET users?filter=name` のクエリパラメータが付与されたリクエストの場合のみ下記のレスポンスが取得できます。
```json
{
  "data": [
    {
      "id": 1,
      "name": "Alice",
      "age": 24
    },
    {
      "id": 2,
      "name": "Bob",
      "age": 27
    }
  ]
}
```

### 動的パラメータ
`GET users/{group}/{limit}` のような動的パラメータを含むエンドポイントへのリクエストについて、 `responses/users/get` のレスポンスルートでパラメータ部をリクエストパラメータとして取得できます。取得したリクエストパラメータを使用してレスポンスを制御するにはカスタムフックを利用します。
例: `GET users` のエンドポイント用カスタムフック `hooks/get_users.php`
```php
<?php

// 例: 動的パラメータのフック `GET /users/{group}/{limit}`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // 動的パラメータの抽出
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $group = isset($dynamicParam1) ? $dynamicParam1 : 'default';
    $limit = isset($dynamicParam2) ? (int) $dynamicParam2 : 0;

    header('Content-Type: application/json');
    $response = [
        'group' => $group,
        'limit' => $limit,
        'users' => [],
    ];
    for ($i = 1; $i <= $limit; $i++) {
        $response['users'][] = [
            'id' => $i,
            'name' => "User {$i} (Group: {$group})",
        ];
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
```
`GET users/groupA/3` の動的パラメータを含むリクエストの場合に下記のレスポンスが取得できます。
```json
{
    "group": "groupA",
    "limit": 3,
    "users": [
        {
            "id": 1,
            "name": "User 1 (Group: groupA)"
        },
        {
            "id": 2,
            "name": "User 2 (Group: groupA)"
        },
        {
            "id": 3,
            "name": "User 3 (Group: groupA)"
        }
    ]
}
```

## ユニットテスト

このプロジェクトの基本的な動作についてはユニットテストを定義しています。
必要に応じてテストケース（ `tests/MockApiTest.php` ）を拡張することでテストを追加することが可能です。

**テストの実行:**
```bash
php vender/bin/phpunit
```

## 他のモックAPIツールとの比較

以下は、MockAPI-PHP と他の主要なモックAPIツール（json-server, MSW, WireMock, Prism）との比較表です。

| 観点 | **MockAPI-PHP** | **json-server** | **Mock Service Worker (MSW)** | **WireMock** | **Prism (Stoplight)** |
|------|------------------|------------------|------------------------------|---------------|------------------------|
| **使用言語** | PHP | Node.js | JavaScript | Java | Node.js |
| **インストール難易度** | ★☆☆（簡単） | ★☆☆（簡単） | ★★☆（環境依存） | ★★★（重い） | ★★☆ |
| **レスポンス定義方法** | PHPコード＋JSONファイル | JSONファイル（静的） | JavaScriptで定義 | JSON or Java設定 | OpenAPI仕様ベース |
| **ルーティング制御** | ✅ 柔軟（PHPで自由） | △ パスパターン定義 | ✅ `rest.get()`等で定義 | ✅ URLパターンマッチ | ✅ OpenAPI準拠 |
| **動的レスポンス生成** | ✅ PHPロジックで自由 | △ 限定的 | ✅ JavaScript可 | ✅ スクリプトで可 | △ 難しい |
| **クエリ/パラメータ分岐** | ✅ 任意に処理可 | △ 限定的 | ✅ 完全対応 | ✅ 完全対応 | △ スキーマ駆動 |
| **ヘッダー/メソッド制御** | ✅ 完全対応 | △ 限定対応 | ✅ 完全対応 | ✅ 完全対応 | ✅ |
| **エラーや認証の再現** | ✅ 自由自在に処理記述 | ✕ 難しい | ✅ ロジックで対応可 | ✅ 詳細制御可 | △ 条件分岐は難 |
| **応答遅延・タイミング制御** | ✅ `sleep()`などで柔軟対応 | ✕ | ✅ `setTimeout()`等で対応可 | ✅ `fixedDelay`など | △ 難しい |
| **OpenAPI連携** | ✅ 自動生成機能あり | ✕ | ✕ | △ エクスポート可能 | ✅ 主目的 |
| **スキーマ自動生成** | ✅ JSONレスポンスから生成 | ✕ | ✕ | △ （変換ツールあり） | ✅ |
| **example 自動埋込** | ✅ 自動（大きな配列は1件のみ） | ✕ | ✕ | ✕ | ✅ |
| **開発対象との相性** | PHPプロジェクトに最適 | Node.js系と相性良 | フロント専用（Vue/Reactなど） | Javaプロジェクト向け | OpenAPI中心の組織向け |
| **学習コスト** | ★☆☆（PHP経験者には低い） | ★☆☆ | ★★☆ | ★★★ | ★★☆ |
| **ログ/トラッキング機能** | ✅ ログ実装可（バリデーション含む） | ✕ | ✅（DevToolsで可） | ✅ 詳細ログあり | △ |
| **用途の柔軟性** | ◎（コードで完全制御・自由度が高い） | ○（簡単なAPIモックに最適） | △（UI開発特化） | ○（高機能） | △（制約あり） |

### 特徴まとめ

- **MockAPI-PHPは、動的ロジックを含むモックレスポンスをPHPで直接定義可能** です。
- 特に PHPプロジェクトとの親和性が高く、バックエンド未完成時の開発やAPI分離開発において柔軟に対応可能です。
- OpenAPIベースやGUIツールとは異なり、コード駆動でのモックAPI開発に最適化されています。
- JSONレスポンスの構造を元に OpenAPI 3.0 スキーマを自動生成する機能を搭載しています（v1.2以降）。

## ライセンス

このプロジェクトは [MIT License](LICENSE) のもとで公開されています。

## Author

- **名前**: Katsuhiko Maeno
- **GitHub**: [github.com/ka215](https://github.com/ka215)  
