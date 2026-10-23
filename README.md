# Nursery Check-in API (Laravel)

Track chosen: Track A (Laravel/PHP), with the AWS note. This is an API only, so there is no frontend.

This API answers one question: **who is in this room right now?** Staff check a child into a room and check them out when they leave. Many nursery groups ("tenants") use the same system. One group must never see or change another group's data.

| Request | What it does | Success |
| --- | --- | --- |
| `POST /rooms/{room}/check-ins` with `{"child_id": 1}` | Puts a child in a room | `201` |
| `POST /check-ins/{id}/check-out` | Takes the child out | `200` |
| `GET /rooms/{room}/occupants` | Lists children in the room now | `200` |

Every request needs the header `X-Tenant-ID` (which nursery group is asking). Login is assumed to be done elsewhere.

## Set up

You need PHP 8.2+ (with `pdo_sqlite`) and Composer. No database server is needed. Data is saved in one SQLite file.

```bash
composer install
cp .env.example .env                  # Windows: copy .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: New-Item database\database.sqlite -ItemType File -Force
php artisan migrate --seed
php artisan serve                     # http://127.0.0.1:8000
```

Check it: `php artisan route:list` should show exactly 3 routes, with no `/api` at the start.

`--seed` adds two groups: **1 Bright Beginnings** (rooms 1-2, children 1-3) and **2 Tiny Steps** (rooms 3-4, children 4-6). To start again: `php artisan migrate:fresh --seed`.

Want MySQL? Set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`. The database-level "one open check-in" rule works only on SQLite and PostgreSQL.

Problems: `could not find driver` means turn on `pdo_sqlite` in `php.ini`. Odd errors after changing files: run `php artisan optimize:clear`.

## Try it with Postman

For **every** request, open the **Headers** tab and add `X-Tenant-ID: 1` and `Accept: application/json`. For `POST` requests with a body, choose **Body**, then **raw**, then **JSON**.

1. **Check Amara in:** `POST http://127.0.0.1:8000/rooms/1/check-ins` with body `{"child_id": 1}`. You get `201`. Note the `id` in the answer.
2. **Who is in Sunflowers:** `GET http://127.0.0.1:8000/rooms/1/occupants`. No body. You get `200` with Amara.
3. **Put her in a second room (should fail):** `POST http://127.0.0.1:8000/rooms/2/check-ins` with body `{"child_id": 1}`. You get `409`, because she is still in room 1.
4. **Check Amara out:** `POST http://127.0.0.1:8000/check-ins/{id}/check-out`. Use the `id` from step 1. No body. You get `200`.
5. **Tenant isolation:** `GET http://127.0.0.1:8000/rooms/1/occupants` but change the header to `X-Tenant-ID: 2`. You get `404`, because room 1 belongs to group 1.

If step 1 gives `409`, Amara is already checked in. Check her out first, or run `php artisan migrate:fresh --seed`.

Errors: `400` header missing or not a number. `404` not found (or it belongs to another group). `409` conflict (child already in a room, or already checked out). `422` wrong input.

## Run the tests

```bash
php artisan test
```

The tests use a temporary in-memory database, so your data is safe. They check the normal paths, wrong input, the double-room rule, double check-out, and **tenant isolation**: group A cannot read or change group B's data on any endpoint.

## How it works

- `routes/api.php` holds the three routes. `IdentifyTenant` runs first and reads `X-Tenant-ID`.
- `app/Tenancy/` makes every search on rooms, children and check-ins add "only this group's rows" automatically. The group id never comes from the request body. If no group is known, the code stops instead of showing everything.
- `app/Actions/` holds the rules: `CheckInChild`, `CheckOutChild`, `ListRoomOccupants`. Controllers stay short.
- Another group's id gives the same `404` as a missing one, so nothing shows that it exists.

## The open question: a child already in a room

I **refuse** the request with `409 Conflict`. The answer also says which check-in to close:

```json
{ "code": "child_already_checked_in", "current_check_in": { "id": 7, "room_id": 1 } }
```

**Why:** the headcount in a room matters for safety and staffing. Moving a child automatically could hide a mistake, and a child could vanish from the room they are really in. A warning is easy to miss. Refusing costs staff one extra tap (check out, then check in), and the data never goes wrong unnoticed. The rule is checked in the code and also by a database rule (one open check-in per child), so two requests at the same moment cannot both win.

## Assumptions and what I left out

- The tenant id is a number that exists in the `tenants` table. Times are in UTC. Checking out twice gives `409`, so a double tap is visible.
- Left out on purpose: login and permissions, a "nursery" table (rooms belong straight to a group), creating rooms or children, paging, rate limits, Docker.

## With more time

Nurseries and staff permissions. A real two-requests-at-once test on PostgreSQL. A MySQL version of the database rule. Safe retries for flaky tablets. A history of who checked whom in and out. API docs, and tests that run on every push.

## Running this on AWS (ECS/Fargate)

**Container.** Built in two steps: Composer installs only the packages needed to run, then a small PHP image with nginx runs the app as a normal user. It holds code only: no `.env`, no SQLite file, no secrets. Logs go to standard error and ECS sends them to CloudWatch. Database migrations run as a separate one-time task during each release, not when a container starts, so containers never change the database at the same time.

**Secrets.** The app key and database password live in AWS Secrets Manager. ECS gives them to the container at start, so they are never in the code. RDS can change the password on a schedule. Normal settings such as `APP_ENV` and `DB_HOST` are plain environment variables. The container gets only the permissions it needs.

**Database.** Amazon RDS or Aurora PostgreSQL in private subnets. Only the app's security group can reach port 5432, and the connection is encrypted. PostgreSQL supports the "one open check-in per child" rule directly. RDS Proxy would help if many containers run.

**Logs and alerts.** JSON logs with a request id and group id, never a child's name. Alarms for server errors and slow answers on the load balancer, unhealthy containers, restarts and memory, database CPU and disk space, and any ERROR log line. `/up` is the health check. Expected `409` answers are not errors, but a jump in 409s or 404s for one group goes on a dashboard. Alarms notify on-call through SNS.

## How I used AI tools

I used Claude (an AI assistant) to write the first version of the code, the tests and this README. It helped most with the project layout, the tenant-separation code and the test list.

I did not accept everything as it came:

- I chose the "refuse with 409" rule myself, after comparing it with moving the child automatically and moving with a warning.
- Claude could not run Laravel in its own workspace, so I ran the setup steps and the tests on my own computer to check them.
- I asked for the README to be rewritten in simpler words and made shorter, and I added the Postman steps myself.

I read the code and I can explain it.