# MockAPI-PHP

このプロジェクトは、PHP製の軽量なモックAPIサーバーです。  
開発・テスト環境で実際のAPIを使用せずに、リクエストのシミュレーションが可能です。  
環境変数 `.env` を使用し、動的なレスポンスやポーリング機能を簡単に設定できます。

---

## 特徴

- **エンドポイントの自動登録**
  - `responses/` 以下のフォルダをスキャンし、ディレクトリ構造に応じたエンドポイントを自動登録。
  - APIのベースパスを環境変数で設定可能。
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
- **ロギング**
  - `request.log` にリクエスト内容（ヘッダー・クエリ・ボディ）を記録。
  - `response.log` にレスポンス内容を記録。
  - リクエストとレスポンスのログはリクエストIDにより紐づけられる。
  - ログ出力のパスは環境変数で設定可能。
- **環境変数による設定保存**
  - `.env` を使い、ポート番号 `PORT` 等の各種環境変数を管理可能。
  - 環境変数の読み込みには `vlucas/phpdotenv` を使用。
  - 一時ファイル（`cookies.txt` など）の保存ディレクトリ `TEMP_DIR` を `.env` で指定可能。

## ディレクトリ構成

以下 `responses` ディレクトリ内はあくまで参考例です。利用ケースに準じて自由にカスタマイズ可能です。
```
mock_api_server/
 ├── index.php             # モックサーバーのメインスクリプト
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
 │   │   └── post/
 │   │        ├── 400.json      # 400エラーのレスポンス
 │   │        ├── failed.json   # POST失敗時のレスポンス
 │   │        └── success.json  # POST成功時のレスポンス
 │   ├── errors/
 │   │   ├── 404.json           # 404エラーレスポンス（JSON形式）
 │   │   └── 500.txt            # 500エラーのレスポンス（テキスト形式）
 │   └── others/
 │        └── get/
 │             ├── default.txt   # CSV形式のテキストデータ
 │             └── userlist.txt  # XML形式のテキストデータ
 ├── hooks/                # カスタムフック格納ディレクトリ
 ├── tests/                # ユニットテスト用のテストケース格納ディレクトリ
 │   └── MockApiTest.php  # 初期テストケース
 ├── phpunit.xml           # ユニットテスト設定ファイル
 └── logs/                 # ログ保存ディレクトリ（.envで変更可能）
      ├── request.log      # リクエストのログ
      └── response.log     # レスポンスのログ
```

## 使い方

1. #### Composer のインストール
    ```bash
    composer install
    ```
2. #### サーバーの起動方法
    Mock API Server を起動するには、以下のいずれかの方法を利用してください。
    ##### 推奨: `start_server.php` を使用
    このスクリプトを使うと、環境変数 `.env` で指定した `PORT` を自動で反映し、`temp/` 内の `.txt` ファイルもクリアされます。
    ```bash
    php start_server.php
    ```
    ##### 手動で PHP 内蔵サーバーを起動
    ```bash
    php -S localhost:3030 -t .
    ```
3. #### APIリクエスト例
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
4. #### `responses/` の設定方法
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
API_KEY=              # 認証用APIキー（アプリケーション単位の簡易的な認証用で長期間有効）
CREDENCIAL=           # 資格情報（ユーザー単位等の単体認証用の期限付きトークン）
```
※ API_KEYとCREDENCIALオプションは本プロジェクトでは簡易的な実装となっており、指定時はリクエストのAuthorizationヘッダからBearerトークンを取得して認証処理が行われます。

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
リクエストとして `GET others?mock_content_type=text/csv` を呼び出すことで `others.csv` が取得できます（ダウンロードされます）。

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

## ユニットテスト

このプロジェクトの基本的な動作についてはユニットテストを定義しています。
必要に応じてテストケース（ `tests/MockApiTest.php` ）を拡張することでテストを追加することが可能です。

**テストの実行:**
```bash
php vender/bin/phpunit
```

## ライセンス

このプロジェクトは [MIT License](LICENSE) のもとで公開されています。

## Author

- **名前**: Katsuhiko Maeno
- **GitHub**: [github.com/ka215](https://github.com/ka215)  
