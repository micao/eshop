# eShop Storefront & E-Commerce Platform Documentation

This project is a modern, fully-featured, and containerized e-commerce system. The backend is built using **Laravel 11+**, and the frontend utilizes **Vue 3 + Inertia.js 3.0 + Tailwind CSS**. The system integrates **Elasticsearch** for high-performance product searches, **RabbitMQ** for asynchronous job queueing, **Stripe** for online checkout payments, **MinIO** for S3-compatible cloud storage, and **Redis** for state caching.

The entire development and execution environment is containerized via **Docker**, backed by a strict automated testing suite (PHPUnit).

---

## Directory
1. [Environment Setup & Installation](#1-environment-setup--installation)
2. [Database Schema & Migrations](#2-database-schema--migrations)
3. [Data Seeding (Seeder)](#3-data-seeding-seeder)
4. [Backend Code Generators & Architecture](#4-backend-code-generators--architecture)
5. [Shopping Cart Module & Guest-to-User Merge](#5-shopping-cart-module--guest-to-user-merge)
6. [Address Book & Dynamic Shipping Rates Calculation](#6-address-book--dynamic-shipping-rates-calculation)
7. [Stripe / Bancontact Payment Gateway & Asynchronous Webhooks](#7-stripe--bancontact-payment-gateway--asynchronous-webhooks)
8. [Elasticsearch Search Engine & RabbitMQ Synced Queues](#8-elasticsearch-search-engine--rabbitmq-synced-queues)
9. [API Token Authorization (Sanctum) & Swagger UI](#9-api-token-authorization-sanctum--swagger-ui)
10. [Automated Testing (PHPUnit)](#10-automated-testing-phpunit)

---

## 1. Environment Setup & Installation

All platform services are orchestrated inside `docker-compose.yml`:
* **web (Nginx)**: The primary frontend/backend web server proxy (Port `80`).
* **php**: PHP 8.4 runtime container executing Eloquent models and controller routes.
* **queue-worker**: Background queue consumer daemon.
* **mariadb**: MariaDB 10.11 database server (Port `3306`).
* **redis**: Redis 7 cache and session data store (Port `6379`).
* **minio**: S3-compatible local object storage (Port `9000` / Console `9001`).
* **rabbitmq**: Message broker queue middleware (Port `5672` / Console `15672`).
* **elasticsearch**: Full-text indexing search server (Port `9200`).

### Spinnin up Containers
Run the following command in the project root directory:
```bash
docker compose up -d
```

### Accessing PHP Container Terminal
To run php artisan commands inside the container network, run:
```bash
docker compose exec php bash
```

---

## 2. Database Schema & Migrations

### Entity Relationships
* `users` 1 ➡️ 🌟 `user_addresses` (Address Book)
* `users` 1 ➡️ 1 `carts` (Shopping Cart)
* `carts` 1 ➡️ 🌟 `cart_items` (Items inside shopping cart)
* `products` 1 ➡️ 🌟 `variants` (Product options/SKU configurations, One-to-Many)
* `orders` 1 ➡️ 🌟 `order_items` (Historical order snapshot with shipping label and tracking links)

### Executing Full Migration and Seeding
```bash
# Execute from your host terminal:
docker compose exec php php artisan migrate:fresh --seed
```

---

## 3. Data Seeding (Seeder)

We have created the `ProductSeeder` class to populate premium product models (Electronics, Audio headsets, Game consoles, and related options variants) to facilitate quick development and debugging.
```bash
# Seed products and variants only:
docker compose exec php php artisan db:seed --class=ProductSeeder
```

---

## 4. Backend Code Generators & Architecture

We decoupled business features into modular abstractions:
* **`CartService`**: Manages real-time item pricing validations, stock limits check, and cart merges.
* **`ShippingManager`**: Standardizes shipping methods. Features a `FlatRateDriver` (computes €5 base delivery costs, and applies `FREE` shipping for orders above €50). Prepared with extendable sockets for real shipping carriers (GLS, bpost).
* **`PaymentManager`**: Resolves Stripe Element checkout payments or Mock transaction simulations for testing.

---

## 5. Shopping Cart Module & Guest-to-User Merge

The storefront cart caters to unregistered shoppers:
1. **Guests**: Added cart items are stored inside the browser's `LocalStorage`.
2. **On Authentication**: Once a user logs in or registers successfully, a custom event script inside the layout's mounting phase (`StorefrontLayout.vue` `onMounted()`) triggers `POST /api/cart/merge`. This merges the local items into the database cart and clears the browser storage.
3. **Remember Me**: Standard checkbox inputs are used for remember tokens to keep the user session cookie persistent.

---

## 6. Address Book & Dynamic Shipping Rates Calculation

* **Profile Addresses**: All registered users can manage shipping destinations under **My Profile ➡️ Addresses**. The view allows adding new addresses, deleting existing entries, and setting default options.
* **Faceted Delivery Estimations**: Selecting a shipping address in the `/checkout` view fires `GET /api/checkout/shipping-rates`. The API calculates rates and arrival days dynamically using the underlying GLS / bpost rules.

---

## 7. Stripe / Bancontact Payment Gateway & Asynchronous Webhooks

The checkout process utilizes a secure two-stage payment sequence:
1. **Stage 1 (Intent Creation)**: Clicking pay creates a `pending` order in the database and requests a Stripe PaymentIntent session, returning a `client_secret` to frontend Elements. **The stock and cart items remain untouched** to prevent stock-locking during cart abandonment.
2. **Stage 2 (Webhook Processing)**: Upon successful payment, Stripe triggers `payment_intent.succeeded` against **`StripeWebhookController`**. The backend:
   * Validates webhook signature keys.
   * Executes a **database transaction** to switch order status to `processing` (paid), decrements variant inventory amounts, links carrier tracking codes, and empties the cart.

---

## 8. Elasticsearch Search Engine & RabbitMQ Synced Queues

Elasticsearch manages search queries for products and SKU options:
* **Scout Integration**: Uses `laravel/scout` with `babenkoivan/elastic-scout-driver`.
* **Asynchronous Syncing**: Model modifications trigger Scout observers to push indexing jobs to **RabbitMQ**. The `queue-worker` container handles these tasks in the background.
* **Query Re-routing**: Catalog searches redirect keyword strings to Elasticsearch matching IDs, then apply MySQL queries for pagination and dynamic filtering.
* **Manual Re-indexing**:
  ```bash
  docker compose exec php php artisan scout:flush "App\Models\Product"
  docker compose exec php php artisan scout:import "App\Models\Product"
  ```

---

## 9. API Token Authorization (Sanctum) & Swagger UI

* **Sanctum Middleware**: Secure REST endpoints are protected under `auth:sanctum`.
* **Swagger OpenAPI Documentation**:
  * Compile docs:
    ```bash
    docker compose exec php php artisan l5-swagger:generate
    ```
  * Access URL:
    👉 **`http://localhost/api/documentation`**

---

## 10. Automated Testing (PHPUnit)

We provide comprehensive coverage including unit, feature APIs, and frontend Inertia page renderings.
To ensure tests are isolated and don't depend on physical Elasticsearch network connections, `phpunit.xml` overrides the driver configuration:
`<env name="SCOUT_DRIVER" value="collection"/>`

This routes search tests to in-memory collection engines, keeping CI/CD integrations fast and stable.

### Run Tests
```bash
make test
```
