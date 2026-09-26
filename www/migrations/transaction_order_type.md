# Dine-in / take-away transaction data

`order_items.order_type` stores `dine-in` or `take-away` per Cafe item. A mixed order may contain both. Carwash and detailing have no dining type.

Apply `20260926_transaction_order_type.sql` before deploying the PHP changes. The migration is repeatable and leaves existing records `NULL` (unknown). Do not backfill old orders as dine-in.

## Server / Android contract

- Upload to `/api/mobile/index?path=orders`: each Cafe item includes `orderType: "dine-in"` or `orderType: "take-away"`.
- The server also accepts `order_type` and normalizes `dine_in` / `take_away` to the canonical hyphenated values.
- Missing or explicit `null` remains unknown for older Android clients and queued transactions; other values return HTTP 422 before the transaction is written.
- `/api/mobile/index?path=report-history` returns each item's `orderType`. Keep it in Android's local transaction/queue object when downloading and uploading.
- Retry with the original `client_order_id`; the existing order is returned without duplicating or rewriting its items.
- Web detail JSON uses `order_type`; POS import maps this to cart `orderType` without converting unknown history to dine-in.
- Settlement and cancellation only change the order header, preserving item types.

The separate `backend-api` used by `mobile-apk` preserves the same `orderType` values on `POST /transactions` and transaction reads. Its persistence is a separate JSON store; it is not the MySQL mobile synchronization endpoint. The Android source includes a Dine In / Take Away selector for new Cafe items. Build and install a new APK to use that selector on devices that do not already send `orderType`.

## Verification

```sh
docker exec lampp_web php /var/www/html/tests/order_type_regression.php
docker exec lampp_web php /var/www/html/tests/order_type_sync_http.php
php tests/android_backend_order_type.php
```

The HTTP synchronization test uses a disposable database and validates upload, download, retry, settlement, and invalid inputs. It does not insert sales into the live database.
