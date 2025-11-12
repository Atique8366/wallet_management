(Optional) Notes — Improvements & Scaling Considerations
These are professional-grade enhancements and scaling ideas for production hardening.

1. Idempotency Storage Optimization
    - Add a cleanup job (e.g., scheduled cron) to delete old Idempotency-Key entries (e.g., older than X days).
    - Add index on (created_at) for faster purging.
    - Optionally store only a hash of the key for privacy/smaller index size.

2. Eventual Balance Consistency (Ledger-first model)
    - Make transactions the single source of truth: SELECT SUM(amount) FROM transactions WHERE wallet_id = ?
    - Treat wallet_balances as a denormalized cache.
    - Use background jobs (queue/cron) to reconcile and update cached balances periodically.

3. Optimistic Locking / Queue-based Concurrency
    - For high-contention operations (heavy concurrent access):
        - Implement Redis locks (atomic Lua script-based lock around each wallet)
        - OR use Job queues (serialized operations per wallet_id)

4. High-performance setup
    - Use read replicas for GET endpoints (balance, transactions).
    - Use master DB for writes (transfers, deposits, withdrawals).
    - Cache static wallet data (currency, owner) in Redis.
    - Add pagination caching for the /transactions endpoint.

5. API enhancements
    - Add optional webhooks or events when transfers/transactions occur.
    - Include reference_id or external_ref in deposit/withdraw requests for external system alignment (banks, payment gateways).
    - Add description field in transactions for a clearer audit trail.

6. Observability
    - Add Laravel logging for each transaction start/finish.
    - Add a custom middleware to trace every Idempotency-Key request.
    - Use UUIDs for all transactions (instead of numeric IDs) for safer external references.

7. Security
    - Ensure the service is always behind HTTPS.
    - Integrate simple token-based authentication for external clients (API key middleware).
    - Validate currencies strictly (ISO 4217 uppercase, only supported currencies).

8. Currency Precision Map
    - Support currencies with non-2 decimal places (e.g., JPY=0, KWD=3) using a map:
        'precision' => ['USD' => 2, 'JPY' => 0, 'KWD' => 3, ...]
    - Dynamically determine precision from wallet currency in toMinorUnits() and fromMinorUnits().

9. Testing
    - Use PHPUnit to create integration tests for:
        - Deposit and withdraw
        - Transfer (same currency)
        - Idempotent replay (asserts same transaction_id)
        - Concurrency simulation (race conditions)

10. Horizontal Scalability
    - Use Laravel Horizon for queues.
    - Use Laravel Octane for persistent workers.
    - Ensure the API is stateless across multiple servers.
    - Ensure the idempotency_keys table is shared across all instances.