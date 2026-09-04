# Investment Ledger

A small Laravel backend that keeps track of client accounts at an investment firm.

Every movement on an account — depositing money, withdrawing money, buying an instrument, selling an
instrument — is written into an append-only ledger. The available cash and the holdings of a client are
never stored as numbers; they are always recalculated from that ledger, so they cannot drift out of sync.

Two rules are enforced on every movement:

1. A client can never spend or withdraw more cash than they have. There is no account "in the red".
2. A client can never sell more units of an instrument than they actually own.

If a movement breaks either rule it is rejected with a clear message and **nothing** is written, so the
account stays exactly as it was.

This is a JSON API only. There is no frontend.

---

## Requirements

- PHP 8.2 or newer, with the `pdo_sqlite` extension enabled
- Composer

The database is SQLite, which is a single file. There is no database server to install.

---

## Setup

```bash
git clone <repository-url>
cd invest_app

composer install

cp .env.example .env
php artisan key:generate
```

Create the empty SQLite file:

```bash
# macOS / Linux
touch database/database.sqlite

# Windows (PowerShell)
New-Item database/database.sqlite -ItemType File
```

Create the tables and load the sample data:

```bash
php artisan migrate --seed
```

Start the server:

```bash
php artisan serve
```

The API is now at `http://127.0.0.1:8000/api`.

### Sample data

`--seed` creates three clients so the project can be tried immediately:

| ID | Client | Cash | Holdings |
|----|--------|------|----------|
| 1 | Ana Petrova | 860.00 | AAPL x2 |
| 2 | Marko Ilievski | 500.00 | MSFT x10, TSLA x4 |
| 3 | Elena Stojanova | 2300.00 | *(none — she sold everything)* |

Ana is the exact example from the task description: she deposited 1000, bought 5 shares at 100,
then sold 3 shares at 120.

---

## API

All requests and responses are JSON. Send these headers:

```
Content-Type: application/json
Accept: application/json
```

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/clients` | List all clients |
| `POST` | `/api/clients` | Create a client |
| `GET` | `/api/clients/{id}` | Client with cash balance and holdings |
| `GET` | `/api/clients/{id}/transactions` | The client's full ledger |
| `POST` | `/api/clients/{id}/transactions` | Record a movement |

### Create a client

```bash
curl -X POST http://127.0.0.1:8000/api/clients \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Ana Petrova"}'
```

```json
{ "data": { "id": 1, "name": "Ana Petrova" } }
```

### Record a movement

A movement always has a `type`. Which other fields are required depends on that type:

| Type | Required fields |
|------|-----------------|
| `deposit` | `amount` |
| `withdrawal` | `amount` |
| `buy` | `instrument`, `quantity`, `price_per_unit` |
| `sell` | `instrument`, `quantity`, `price_per_unit` |

For a `buy` or a `sell` you do **not** send `amount` — the server calculates it as
`quantity * price_per_unit`.

**Deposit:**

```bash
curl -X POST http://127.0.0.1:8000/api/clients/1/transactions \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"type":"deposit","amount":1000}'
```

**Buy:**

```bash
curl -X POST http://127.0.0.1:8000/api/clients/1/transactions \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"type":"buy","instrument":"AAPL","quantity":5,"price_per_unit":100}'
```

The response contains the recorded movement and the resulting state of the account, so no second
request is needed:

```json
{
  "data": {
    "id": 2,
    "type": "buy",
    "amount": "500.00",
    "instrument": "AAPL",
    "quantity": 5,
    "price_per_unit": "100.00",
    "recorded_at": "2026-09-04T16:58:31+00:00"
  },
  "account": {
    "cash_balance": "500.00",
    "holdings": [ { "instrument": "AAPL", "quantity": 5 } ]
  }
}
```

### Read an account

```bash
curl http://127.0.0.1:8000/api/clients/1 -H "Accept: application/json"
```

```json
{
  "data": {
    "id": 1,
    "name": "Ana Petrova",
    "cash_balance": "860.00",
    "holdings": [ { "instrument": "AAPL", "quantity": 2 } ]
  }
}
```

### Read the ledger

```bash
curl http://127.0.0.1:8000/api/clients/1/transactions -H "Accept: application/json"
```

```json
{
  "data": [
    { "id": 1, "type": "deposit", "amount": "1000.00", "instrument": null,
      "quantity": null, "price_per_unit": null, "recorded_at": "2026-09-04T16:58:31+00:00" },
    { "id": 2, "type": "buy", "amount": "500.00", "instrument": "AAPL",
      "quantity": 5, "price_per_unit": "100.00", "recorded_at": "2026-09-04T16:58:31+00:00" }
  ]
}
```

---

## Errors

Everything that fails returns HTTP `422`, except an unknown client or route which returns `404`.

**A broken business rule** returns a message and a stable `error` code:

```json
{
  "message": "Insufficient funds: the account holds 500.00 but this movement requires 700.00.",
  "error": "insufficient_funds"
}
```

```json
{
  "message": "Insufficient holdings: the client owns 5 unit(s) of AAPL but tried to sell 8.",
  "error": "insufficient_holdings"
}
```

**Invalid input** returns Laravel's standard validation format:

```json
{
  "message": "The amount field must be greater than 0.",
  "errors": { "amount": ["The amount field must be greater than 0."] }
}
```

**Unknown client or route:**

```json
{ "message": "The requested resource was not found.", "error": "not_found" }
```

In every one of these cases nothing is written to the ledger.

---

## Tests

```bash
php artisan test
```

39 tests. They run against an in-memory SQLite database, so they never touch `database/database.sqlite`.

| File | Covers |
|------|--------|
| `tests/Feature/ClientApiTest.php` | Creating, listing and reading clients, duplicate names, 404 |
| `tests/Feature/TransactionApiTest.php` | All four movement types, holdings maths, the ledger |
| `tests/Feature/BusinessRuleTest.php` | The two rules, and that a rejection changes nothing |
| `tests/Feature/TransactionValidationTest.php` | Invalid and nonsensical input |

The two cases named in the task are `test_a_withdrawal_above_the_balance_is_rejected` and
`test_a_sale_above_the_owned_quantity_is_rejected`. There is also
`test_the_scenario_from_the_specification`, which walks through the Ana example from the task
step by step, including both movements that must be refused.

---

## Project structure

```
app/Enums/TransactionType.php          the four movement types
app/Models/Client.php                  a client
app/Models/Transaction.php             one row of the ledger
app/Services/AccountService.php        balance, holdings, and the two rules
app/Exceptions/                        the two rule violations
app/Http/Requests/                     input validation
app/Http/Controllers/Api/              the endpoints
routes/api.php                         the route list
database/seeders/DatabaseSeeder.php    the sample clients
```

---

## Why this way

**Cash and holdings are calculated, not stored.** This was the most important decision. The problem
with the spreadsheets was that a number gets written down once and then slowly stops matching reality.
If I kept a `balance` column I would have the same problem: any bug, crash or forgotten update leaves
it wrong forever, and nothing would notice. Instead the ledger is the only truth, and the balance is a
sum over it. It cannot be wrong unless the movements themselves are wrong. For a system of this size
the cost of recalculating is nothing.

**The ledger can only be added to.** Movements are never edited or deleted, exactly like a notebook
where you only add new lines. I enforce this in the `Transaction` model itself, which throws if
anything tries to update or delete a row, so it is a real rule and not just a promise.

**The rules live in one class.** `AccountService` is the only place a movement can be written. The
check and the insert happen there together inside a database transaction, so there is no path into the
database that skips the rules — the API uses it, and so does the seeder.

**Checking and writing happen inside one locked transaction.** Without this, two withdrawals arriving
at the same moment could both read "the balance is 500", both decide that is enough, and both be
written, leaving the client below zero. Locking the client row means the second one waits and then sees
the real balance. It costs one line and removes a bug that would be very hard to find later.

**Validation and business rules are separate on purpose.** Validation answers "is this input even
sensible" — is the amount positive, is the quantity a whole number. That needs no database. The rules
answer "is this allowed for this client right now", which needs the ledger. Keeping them apart means
each one stays easy to read.

**The client does not send `amount` for a buy or a sell.** The server computes
`quantity * price_per_unit`. If the caller could send all three, they could send numbers that
contradict each other and the ledger would be nonsense. Sending it is rejected rather than ignored, so
a caller doing it by mistake finds out.

**Money is returned as a string like `"500.00"`.** Decimal numbers in JSON can lose precision in some
languages. A fixed two-decimal string is unambiguous. Quantities stay real integers, because units are
whole things.

**An instrument is stored exactly as it was typed.** The task describes it as a free label with no
fixed list to choose from, so I do not change the case or map it to anything. This does mean `AAPL` and
`aapl` would count as two different instruments; if the firm wanted them treated as one, the natural
place to normalise would be the validation layer.

**SQLite, not MySQL.** The whole database is one file, so the project can be cloned and run without
installing or configuring a database server. Nothing in the code is SQLite-specific — the connection
can be swapped in `.env`.

**No authentication.** The task does not mention users, logins or permissions, so adding them would
have been guessing at a requirement that was not there.
