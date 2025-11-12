# Wallet Service (Laravel 9)

A simple, idempotent, double‑entry **wallet management API** built with
Laravel 9.5.2.

------------------------------------------------------------------------

## 🧩 Requirements

-   PHP 8.0+
-   Composer
-   MySQL (or any supported DB)
-   Laravel 9.5.2

------------------------------------------------------------------------

## ⚙️ Installation

``` bash
composer install
cp .env.example .env
# Edit .env with your DB settings

php artisan key:generate
php artisan migrate
php artisan serve
```

Server runs at: <http://localhost:8000>

------------------------------------------------------------------------

## 🧭 API Endpoints

  --------------------------------------------------------------------------------------------------------------------------
  Method              Endpoint                           Description
  ------------------- ---------------------------------- -------------------------------------------------------------------
  **GET**             `/api/health`                      Health check

  **POST**            `/api/wallets`                     Create new wallet `<br>`{=html}Body:
                                                         `{ "owner_name": "Alice", "currency": "USD" }`

  **GET**             `/api/wallets`                     List all wallets (optional filters: `owner`, `currency`)

  **GET**             `/api/wallets/{id}`                Get wallet details (shows readable balance)

  **GET**             `/api/wallets/{id}/balance`        Get wallet balance `<br>`{=html}**Response:**
                                                         `{ "wallet_id":1, "balance":"10.50" }`

  **POST**            `/api/wallets/{id}/deposit`        Deposit amount `<br>`{=html}Header: `Idempotency-Key` (optional)
                                                         `<br>`{=html}Body: `{ "amount":"10.50", "metadata":{...} }`

  **POST**            `/api/wallets/{id}/withdraw`       Withdraw amount `<br>`{=html}Header: `Idempotency-Key` (optional)
                                                         `<br>`{=html}Body: `{ "amount":"5.00" }`

  **POST**            `/api/transfers`                   Transfer between wallets `<br>`{=html}Header: `Idempotency-Key`
                                                         (optional) `<br>`{=html}Body:
                                                         `{ "source_wallet_id":1, "target_wallet_id":2, "amount":"2.00" }`

  **GET**             `/api/wallets/{id}/transactions`   Paginated transaction history (filters: `type`, `from`, `to`)
                                                         `<br>`{=html}All amounts returned as formatted decimals
  --------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 💰 Data model

Amounts are stored internally in **integer minor units** (e.g., cents)
for accuracy but **returned as readable decimals** (e.g., `"10.50"`).\
- Deposit `10.50` → stored as `1050` cents\
- Withdraw/Transfer responses also show `"amount":"10.50"` etc.

------------------------------------------------------------------------

## 🪄 Example responses

### Deposit

``` json
{
  "transaction_id": 15,
  "wallet_id": 1,
  "amount": "10.50",
  "new_balance": "10.50"
}
```

### Transfer

``` json
{
  "transfer_id": 3,
  "amount": "5.00",
  "debit_tx": 10,
  "credit_tx": 11,
  "source_new_balance": "5.50",
  "target_new_balance": "5.00"
}
```

### Transaction history

``` json
{
  "current_page": 1,
  "data": [
    {
      "id": 21,
      "wallet_id": 1,
      "type": "deposit",
      "amount": "10.50",
      "related_wallet_id": null,
      "related_wallet": null,
      "created_at": "2025-11-12T22:10:33.000000Z"
    },
    {
      "id": 22,
      "wallet_id": 1,
      "type": "transfer_debit",
      "amount": "-5.00",
      "related_wallet_id": 2,
      "related_wallet": "Bob",
      "created_at": "2025-11-12T22:11:40.000000Z"
    }
  ]
}
```

------------------------------------------------------------------------

## 🧱 Key features

-   Atomic operations using DB transactions
-   Deterministic locking for concurrency safety
-   Idempotency for all money operations (via header `Idempotency-Key`)
-   Double‑entry transaction model for transfers
-   Accurate integer accounting with readable decimal output
-   Pagination + filtering on history
-   Simple REST‑based JSON API

------------------------------------------------------------------------

## 🧪 Testing with Postman

Import the included **`WalletService.postman_collection.json`** file.

located in main directory

Example environment variables:

``` json
{
  "baseUrl": "http://localhost:8000",
  "idemp_key": "demo-key-123"
}
```

------------------------------------------------------------------------