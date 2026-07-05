# eShop 電商後台與購物商城項目說明文檔 (README_CN)

本項目是一個現代化、全功能且容器化的電子商務系統。後端基於 **Laravel 11+**，前端採用 **Vue 3 + Inertia.js 3.0 + Tailwind CSS**，並集成了 **Elasticsearch** 全文檢索、**RabbitMQ** 消息隊列、**Stripe** 支付網關、**MinIO** 對象存儲及 **Redis** 緩存。

整個開發與運行環境完全基於 **Docker** 進行容器化管理，並配備了完整且嚴格的自動化測試套件（PHPUnit）。

---

## 目錄
1. [環境搭建與啟動](#1-環境搭建與啟動)
2. [數據庫表設計與遷移 (Migration)](#2-數據庫表設計與遷移-migration)
3. [數據填充與生成 (Seeder)](#3-數據填充與生成-seeder)
4. [代碼生成與架構設計](#4-代碼生成與架構設計)
5. [購物車模組與登錄自動合並機制](#5-購物車模組與登錄自動合並機制)
6. [收貨地址薄與快遞運費動態估算](#6-收貨地址薄與快遞運費動態估算)
7. [Stripe / Bancontact 支付網關與 Webhook 異步確認](#7-stripe--bancontact-支付網關與-webhook-異步確認)
8. [Elasticsearch 全文檢索與 RabbitMQ 隊列同步](#8-elasticsearch-全文檢索與-rabbitmq-隊列同步)
9. [API Token 認證 (Sanctum) 與 Swagger 文檔](#9-api-token-認證-sanctum-與-swagger-文檔)
10. [自動化測試 (PHPUnit)](#10-自動化測試-phpunit)

---

## 1. 環境搭建與啟動

項目所有的服務都定義在 `docker-compose.yml` 中：
* **web (Nginx)**: 商城與後台的 Web 服務器 (Port `80`)。
* **php**: PHP 8.4 運行容器。
* **queue-worker**: Background 隊列消費者，自動消費任務。
* **mariadb**: MariaDB 10.11 數據庫 (Port `3306`)。
* **redis**: Redis 緩存與狀態同步 (Port `6379`)。
* **minio**: S3 兼容的雲存儲 (Port `9000` / Console `9001`)。
* **rabbitmq**: 隊列消息中間件 (Port `5672` / Console `15672`)。
* **elasticsearch**: 搜索引擎服務器 (Port `9200`)。

### 啟動容器
在項目根目錄下執行：
```bash
docker compose up -d
```

### 進入 PHP 容器終端
日常開發中，許多命令需要在容器內部執行。你可以通過以下命令進入 PHP 容器：
```bash
docker compose exec php bash
```

---

## 2. 數據庫表設計與遷移 (Migration)

### 表結構關係
* `users` 1 ➡️ 🌟 `user_addresses` (地址薄，多個)
* `users` 1 ➡️ 1 `carts` (購物車)
* `carts` 1 ➡️ 🌟 `cart_items` (購物車項目，多個)
* `products` 1 ➡️ 🌟 `variants` (商品多規格/變體，一對多)
* `orders` 1 ➡️ 🌟 `order_items` (訂單項目，帶有配送/支付/運單快照)

### 執行全新數據庫遷移與數據填充
```bash
# 宿主機執行：
docker compose exec php php artisan migrate:fresh --seed
```

---

## 3. 數據填充與生成 (Seeder)

我們設計了 `ProductSeeder` 來批量填充真實的電子類、音頻類及遊戲類商品和變體，方便本地調試與演示。
```bash
# 僅運行商品數據填充
docker compose exec php php artisan db:seed --class=ProductSeeder
```

---

## 4. 代碼生成與架構設計

為了解耦業務，系統設計了多個自定義的 **Manager / Gateway / Service 模式**：
* **`CartService`**：處理購物車的實時價格校驗、合併及庫存檢查。
* **`ShippingManager`**：物流網關管理器，支持 `FlatRateDriver`（固定運費驅動，可配置滿 €50 免郵，不足則收取 €5 基礎運費），為將來接入 `GLS`、`bpost` 等預留統一接口。
* **`PaymentManager`**：在線支付網關管理器，驅動支持 `stripe` 在線支付與 `mock` 虛擬支付。

---

## 5. 購物車模組與登錄自動合並機制

購物車設計充分考慮了遊客體驗：
1. **遊客狀態下**：商品被安全地儲存在瀏覽器的 `LocalStorage` 中。
2. **用戶登錄時**：系統在公共前台佈局 `StorefrontLayout.vue` 的 `onMounted` 生命週期中自動檢測。若有未登錄購物車數據，則發送 `POST /api/cart/merge` 接口，將遊客購物車合併至用戶的數據庫購物車中，隨即清空 `LocalStorage`。
3. **Remember Me 功能**：登錄頁面的複選框採用標準的原生 HTML `<input type="checkbox" name="remember" />`，保證 Session Cookie 能被正確序列化並長時間維持登錄態。

---

## 6. 收貨地址薄與快遞運費動態估算

* **地址薄管理**：所有用戶（包含 Admin）均可通過右上角頭像菜單進入 **My Profile ➡️ Addresses**。頁面提供動態的新增地址表單、刪除地址及設為默認（Default）收貨地址功能。
* **運費動態估算**：當用戶在 `/checkout` 頁面選中收貨地址時，前端發送 `GET /api/checkout/shipping-rates` 請求，系統動態獲取 `GLS` 與 `bpost` 的快遞方案。符合包郵門檻時，運費自動顯示為 `FREE`。

---

## 7. Stripe / Bancontact 支付網關與 Webhook 異步確認

系統支持安全的兩階段支付模式：
1. **第一階段（創建 Intent）**：用戶在 `/checkout` 頁面點擊下單，`CheckoutController` 在數據庫中創建一個狀態為 `pending` 的訂單，並向 Stripe API 發送請求獲取 `client_secret`，**此時不會扣減庫存，也不會清空購物車**。
2. **第二階段（Webhook 異步回調）**：用戶完成付款（信用卡或掃碼）後，Stripe 會異步發送 `payment_intent.succeeded` 請求給 **`StripeWebhookController`**：
   * 驗證 Webhook 密鑰簽名。
   * 在**數據庫事務**中：更新訂單狀態為 `processing` (已付款)、扣減對應商品規格庫存、生成物流單號並清空用戶購物車。

---

## 8. Elasticsearch 全文檢索與 RabbitMQ 隊列同步

為了解決大量商品的檢索性能問題，我們集成了 Elasticsearch 作為商品搜索引擎：
* **Scout 對接**：使用 `laravel/scout` 配合 `babenkoivan/elastic-scout-driver`。
* **背景異步同步**：當商品或規格寫入數據庫後，Scout 會向 **RabbitMQ** 發送異步任務。後台運行的 `queue-worker` 容器會自動消費並將資料同步寫入 Elasticsearch，保證寫入的高性能與實時搜尋。
* **重構搜索過濾**：當有 `search` 關鍵詞傳入時，系統引導 Elasticsearch 全文檢索；無關鍵詞時則降級為常規數據庫過濾，保證極致的頁面響應。
* **手動同步命令**：
  ```bash
  # 宿主機執行：
  docker compose exec php php artisan scout:flush "App\Models\Product"
  docker compose exec php php artisan scout:import "App\Models\Product"
  ```

---

## 9. API Token 認證 (Sanctum) 與 Swagger 文檔

我們為移動端與外部接口提供了支持：
* **Sanctum 認證**：接口使用 `auth:sanctum` 中間件進行保護。
* **Swagger 接口文檔**：
  * 編譯生成文檔命令：
    ```bash
    docker compose exec php php artisan l5-swagger:generate
    ```
  * 瀏覽器訪問地址：
    👉 **`http://localhost/api/documentation`**

---

## 10. 自動化測試 (PHPUnit)

項目覆蓋了全方位的測試（單元測試、API 接口功能測試、前台頁面渲染測試）。
為了保證測試的高效與自閉環，我們在 `phpunit.xml` 中指定了 `SCOUT_DRIVER` 使用 **`collection`** 驅動，測試時在內存中模擬搜索引擎，**無需依賴物理 Elasticsearch 的網絡連接**，從而保障本地測試和 CI/CD 流程的流暢與穩定。

### 運行測試
```bash
# 宿主機運行完整測試：
make test
```
