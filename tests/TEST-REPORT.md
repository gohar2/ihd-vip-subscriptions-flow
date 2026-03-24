# IHD VIP Subscriptions — Audit Logger Test Report

**Date:** 2026-03-24
**Tester:** Claude Code Agent
**Test User:** syed.gohar@homelifemedia.com (ID: 439065)
**Plugin Version:** 1.0.3
**Environment:** Staging (iheartdogs.com)

---

## Summary

| Metric | Value |
|--------|-------|
| Total Tests | 116 |
| Passed | 116 |
| Failed | 0 |
| Warnings | 0 |
| Pass Rate | **100%** |
| Structural Issues Found | 8 |

---

## Test Categories & Results

### 1. Pre-Flight Checks (13 tests) — ALL PASSED
- WooCommerce, WooCommerce Subscriptions, and plugin classes verified active
- Audit table schema validated (all 5 columns present)
- Test user confirmed

### 2. Direct Audit Logger — Insert & Retrieve (12 tests) — ALL PASSED
- Basic insert/retrieve works correctly
- Intentional flag (true/false) stored accurately
- System events (by_user_id = 0) logged correctly
- Log ordering by created_at DESC verified (with sleep to differentiate timestamps)
- Empty reason handled correctly
- Long reason truncated to 255 chars by DB column
- Non-existent subscription IDs accepted (no FK constraint)

### 3. Manual Intentional Cancellation (6 tests) — ALL PASSED
- Created test subscription, simulated cancel handler flow
- Dedup meta flag (`_ihd_cancel_logged`) prevented double logging
- Only 1 audit entry created (from cancel handler, not event tracker)
- Entry correctly marked as `intentional = 1`
- Dedup flag cleared after event tracker processed it

### 4. Admin-Initiated Status Changes (5 tests) — ALL PASSED
- Admin on-hold: logged with reason "Admin On-hold"
- Admin cancel from on-hold: logged with reason "Admin Cancelled"
- Both entries correctly marked `intentional = 0` (system-detected)

### 5. Auto-Expiration Simulation (4 tests) — ALL PASSED
- Status change to `expired` with `wp_set_current_user(0)` (system context)
- Logged with reason "Subscription Expired"
- `by_user_id = 0`, `intentional = 0`

### 6. Renewal Payment Failure (4 tests) — ALL PASSED
- Created mock failed renewal order with decline note
- `woocommerce_subscription_renewal_payment_failed` hook triggered
- Reason: "Renewal Payment Failed"
- Correctly non-intentional, system-triggered

### 7. Gateway/Integration Error (3 tests) — ALL PASSED
- Mock renewal with CONFIGURATION_ERROR + E00044 in order notes
- Classified as "Integration/Gateway Error" (not customer fault)

### 8. Invalid Payment Card Scenarios (7 tests) — ALL PASSED
Each scenario created its own subscription + renewal order:

| Scenario | Error Simulated | Expected Reason | Result |
|----------|----------------|-----------------|--------|
| Insufficient Funds | `INSUFFICIENT FUNDS` | Renewal Payment Failed | PASS |
| Expired Card | `Card expired` | Renewal Payment Failed | PASS |
| Card Declined | `DECLINED - Do not honor` | Renewal Payment Failed | PASS |
| CVV Mismatch | `CVV verification failed` | Renewal Payment Failed | PASS |
| INVALID_CARD_DATA | `INVALID_CARD_DATA` | Integration/Gateway Error | PASS |
| Authorize.Net E00003 | `E00003 XML parse error` | Integration/Gateway Error | PASS |
| PayPal INSTRUMENT_DECLINED | `INSTRUMENT_DECLINED` | Integration/Gateway Error | PASS |

### 9. Deduplication — Cancel Modal Flag (3 tests) — ALL PASSED
- Cancel handler logs + sets `_ihd_cancel_logged = yes`
- Event tracker detects flag, skips logging, clears flag
- Only 1 audit entry total (the intentional one)

### 10. Transient Dedup — Payment Failure Double-Fire (2 tests) — ALL PASSED
- `woocommerce_subscription_payment_failed` fired twice with same subscription/order
- Only 1 audit entry created (transient dedup key with 5-min window)

### 11. Scope Gate Access Control (4 tests) — ALL PASSED
- Scoped user (439065) → allowed
- Non-scoped user → denied
- Anonymous (user 0) → denied
- Empty scoped list → denies everyone

### 12. Error Classification Accuracy (20 tests) — ALL PASSED
Tested via PHP Reflection on private `classify_error()` method:

| Category | Patterns Tested | Result |
|----------|----------------|--------|
| Integration Error | INVALID_CARD_DATA, CONFIGURATION_ERROR, E00003, E00044, GATEWAY_ERROR, INSTRUMENT_DECLINED, GATEWAY_UNAVAILABLE, API_ERROR | ALL PASS |
| Insufficient Funds | INSUFFICIENT, NSF | ALL PASS |
| Expired Card | EXPIRED, CARD_EXPIRED | ALL PASS |
| Payment Declined | DECLINED, Do not honor, CVV, FRAUD, AVS_FAILED | ALL PASS |
| Unknown | empty string, unrecognized, generic | ALL PASS |

### 13. Pending Cancellation (2 tests) — ALL PASSED
- Status → `pending-cancel` logged with reason "Pending Cancellation"

### 14. On-Hold → Cancelled Auto-Cancel (2 tests) — ALL PASSED
- On-hold → cancelled transition: reason "Auto-cancelled (Payment Failed)"

### 15. Max Retries Exceeded (1 test) — ALL PASSED
- Set `_wcs_retry_count` meta → cancel → reason "Auto-cancelled (Max Retries Exceeded)"

### 16. Non-Tracked Status Changes — Negative Test (1 test) — ALL PASSED
- `on-hold → active` reactivation does NOT create an audit entry (correct behavior)

### 17. Plugin Structural Integrity (16 tests) — ALL PASSED
- All 8 required files exist
- Plugin version constant defined correctly (1.0.3)
- Shortcode registered
- All AJAX actions hooked
- All WC Subscription event hooks registered
- Rewrite endpoint registration hooked to init

### 18. Edge Cases (2 tests) — ALL PASSED
- `get_logs(0)` returns empty array
- `get_logs(non-existent-id)` returns empty array

### 19. All Cancel Modal Reason Options (6 tests) — ALL PASSED
- All 5 predefined reasons stored and retrieved correctly
- Each reason round-trips through insert → retrieve accurately

---

## Structural Issues Found

### MEDIUM Severity

1. **Audit table lacks `event_type` and status transition columns**
   - The table only stores `reason` (VARCHAR 255) but has no dedicated `event_type` column (cancellation, expiration, payment_failure, on-hold) or `old_status`/`new_status` columns
   - Impact: Harder to filter/query by event type programmatically; admin reporting is limited to string-matching on `reason`

2. **`build_status_change_detail()` is unused dead code**
   - This method in `class-subscription-event-tracker.php` computes detailed context (status transition, who triggered it, payment method, last renewal order, error classification, subscription amount) but is never called
   - Impact: Valuable diagnostic data is computed but discarded; the audit log stores only a short reason string

### LOW Severity

3. **No `detail`/`context` column for full error messages**
   - Related to issue #2: even if `build_status_change_detail()` were called, there's no column to store its output (reason is capped at 255 chars)

4. **Cancel handler AJAX has no rate limiting**
   - The `ihd_vip_cancel_subscription` endpoint could be spammed. Mitigated by nonce + ownership check, but a determined user could still create many audit entries.

5. **`reason` column VARCHAR(255) silently truncates**
   - Gateway error messages can exceed 255 chars. The DB truncates silently without warning.

6. **`on_payment_failed` delegates to `on_renewal_payment_failed`**
   - The `woocommerce_subscription_payment_failed` hook may fire with non-renewal order types, but the handler assumes renewal order context.

### INFO (By Design / Low Impact)

7. **Scope gate is file-based**
   - Development mode = file exists, production mode = file deleted. Works as intended but could confuse developers unfamiliar with the pattern. Consider an `wp_option` flag.

8. **Reactivation events are not tracked**
   - When subscriptions go from on-hold/cancelled back to active, no audit entry is created. The audit log shows only negative events, not full lifecycle.

### BUG FINDING

9. **`get_logs()` uses `ORDER BY created_at DESC` — non-deterministic for same-second inserts**
   - If multiple events fire in the same second (common during automated processing), the order of returned logs is undefined. Should use `ORDER BY id DESC` for deterministic ordering.

---

## Active Plugins Involved in Subscriptions/Memberships

| Plugin | Version | Status |
|--------|---------|--------|
| WooCommerce | 10.4.3 | Active |
| WooCommerce Subscriptions | 8.2.1 | Active |
| WooCommerce Memberships | 1.27.4 | Active |
| Buy Once or Subscribe | 5.2.1 | Active |
| Autoship Cloud | 2.10.5.2 | Active |
| WooCommerce Gateway Authorize.Net CIM | 3.10.14 | Active |
| WooCommerce PayPal Payments | 3.3.1 | Active |
| WooCommerce Square | 5.1.2 | Active |
| IHD VIP Subscriptions | 1.0.3 | Active |

---

## Recommendations

1. **Add `event_type` and `detail` columns** to the audit table for structured querying and full error context storage
2. **Wire up `build_status_change_detail()`** — it's already written, just needs to be called and stored
3. **Change `get_logs()` to `ORDER BY id DESC`** to fix the non-deterministic ordering bug
4. **Consider adding a `old_status` / `new_status` column pair** for clearer lifecycle tracking
5. **Add rate limiting** to the cancel AJAX handler (e.g., 1 cancellation per subscription per minute)

---

## Test Script Location

```
wp-content/plugins/ihd-vip-subscriptions/tests/test-audit-logger.php
```

Re-run anytime with:
```bash
wp eval-file wp-content/plugins/ihd-vip-subscriptions/tests/test-audit-logger.php --user=439065
```
