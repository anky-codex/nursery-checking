# Nursery Check-in API (Laravel)

This project answers one simple question: **who is in this room right now?**

A nursery has rooms, like "Sunflowers" or "Little Explorers". When a child arrives in a room, staff **check them in**. When the child leaves, staff **check them out**. The API can then tell you which children are in a room at this moment.

Many nursery groups use this same system. Each group is called a **tenant**. The most important rule is: **one group must never see or change another group's data.**

## What the API can do

There are three endpoints (URLs):

| What you want | Request | Good answer |
| --- | --- | --- |
| Put a child in a room | `POST /rooms/{room}/check-ins` with `{"child_id": 1}` | `201` |
| Take a child out of a room | `POST /check-ins/{id}/check-out` | `200` |
| See who is in a room now | `GET /rooms/{room}/occupants` | `200` |

Every request must also send a header called `X-Tenant-ID`. It says which nursery group is asking, for example `X-Tenant-ID: 1`.

(Login is not part of this project. We assume it is done somewhere else.)

---

## 1. How to set it up

### What you need first

| Tool | Which version | How to check |
| --- | --- | --- |
| PHP | 8.2 or newer | `php -v` |
| PHP SQLite support | `pdo_sqlite` and `sqlite3` | `php -m` (both names should be in the list) |
| Composer | version 2 | `composer --version` |

You do **not** need MySQL. The project saves data in one small file (SQLite).

### The steps

Open a terminal in the project folder (the folder that has a file called `artisan`).

**Windows (PowerShell)**
```powershell
composer install
copy .env.example .env
php artisan key:generate
New-Item database\database.sqlite -ItemType File -Force
php artisan migrate --seed
php artisan serve
```

**Mac / Linux**
```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

What each step does:

| Step | What it does |
| --- | --- |
| `composer install` | Downloads the code libraries the project needs (including Laravel) |
| copy `.env.example` to `.env` | Makes your own settings file. It is already set to use SQLite |
| `php artisan key:generate` | Creates a secret key that Laravel needs |
| create `database.sqlite` | Makes the empty database file |
| `php artisan migrate --seed` | Builds the database tables and adds sample data |
| `php artisan serve` | Starts the API at http://127.0.0.1:8000. Keep this window open |

Now open a **second** terminal and check that it worked:

```bash
php artisan route:list
```

You should see exactly **3 routes**. They should not have `/api` at the start.

### The sample data

`--seed` adds two nursery groups so you can try things:

| Group | Rooms | Children |
| --- | --- | --- |
| 1 - Bright Beginnings | 1 Sunflowers, 2 Little Explorers | 1 Amara, 2 Ben, 3 Chloe |
| 2 - Tiny Steps | 3 Sunflowers, 4 Little Explorers | 4 Dev, 5 Elena, 6 Farid |

To delete everything and start again with fresh sample data:

```bash
php artisan migrate:fresh --seed
```

### Want to use MySQL instead? (optional)

SQLite is easier, so you can skip this. If you do want MySQL:

1. Make an empty database in MySQL.
2. Open the `.env` file and set `DB_CONNECTION=mysql`.
3. Fill in `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.
4. Run `php artisan migrate --seed`.

Note: the database rule "one open check-in per child" only works on SQLite and PostgreSQL. On MySQL the code still follows the rule, but the database does not double-check it.

The tests always use a temporary SQLite database, whatever is in your `.env`.

### If something goes wrong

| Problem | What to do |
| --- | --- |
| `could not find driver` | SQLite is turned off. Open `php.ini`, remove the `;` before `extension=pdo_sqlite` and `extension=sqlite3`, then try again |
| `No application encryption key has been specified` | Run `php artisan key:generate` |
| `vendor/autoload.php` not found | Run `composer install` |
| `route:list` shows no routes, or routes start with `/api` | The files `routes/api.php` or `bootstrap/app.php` are missing or wrong. Copy them again from the project |
| Port 8000 is already used | Run `php artisan serve --port=8001` and use 8001 in the examples |
| Strange errors after you change a file | Run `php artisan optimize:clear` |

---

## 2. Try it yourself

Keep `php artisan serve` running. Use a second terminal for these. (On Windows PowerShell, type `curl.exe` instead of `curl`, or use Postman.)

### Test with Postman

Create each request in Postman using the method and URL shown below. For **all three requests**, open the **Headers** tab and add:

| Key | Value |
| --- | --- |
| `X-Tenant-ID` | `1` |
| `Accept` | `application/json` |

1. **Check Amara in**
   - Method: `POST`
   - URL: `http://127.0.0.1:8000/rooms/1/check-ins`
   - Open **Body**, select **raw** and choose **JSON**, then enter:

     ```json
     {
       "child_id": 1
     }
     ```

   - Click **Send**. The response should be `201 Created`. Note the `id` in the response; it identifies this check-in.

2. **See who is in Sunflowers**
   - Method: `GET` (the occupants endpoint does not accept `POST`)
   - URL: `http://127.0.0.1:8000/rooms/1/occupants`
   - No request body is needed. Click **Send**; the response should be `200 OK` and include Amara.

3. **Check Amara out**
   - Method: `POST`
   - URL: `http://127.0.0.1:8000/check-ins/{id}/check-out`
   - Replace `{id}` with the check-in `id` returned in step 1. For example, if the response had `"id": 7`, use `http://127.0.0.1:8000/check-ins/7/check-out`.
   - No request body is needed. Click **Send**; the response should be `200 OK`.

If step 1 returns `409 Conflict`, Amara already has an open check-in. Check her out using that check-in's `id` first, or reset the local sample data with `php artisan migrate:fresh --seed` (this deletes and recreates the local database data).

### Test with curl

```bash
# 1. Put Amara in Sunflowers
curl -i -X POST http://127.0.0.1:8000/rooms/1/check-ins \
  -H 'X-Tenant-ID: 1' -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"child_id": 1}'

# 2. Who is in Sunflowers?
curl -s http://127.0.0.1:8000/rooms/1/occupants -H 'X-Tenant-ID: 1'

# 3. Try to put Amara in another room without checking her out (you get a 409 error)
curl -i -X POST http://127.0.0.1:8000/rooms/2/check-ins \
  -H 'X-Tenant-ID: 1' -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"child_id": 1}'

# 4. Check her out (use the "id" from step 1)
curl -s -X POST http://127.0.0.1:8000/check-ins/1/check-out -H 'X-Tenant-ID: 1'

# 5. Group 2 asks about Group 1's room (you get 404, as if the room does not exist)
curl -i http://127.0.0.1:8000/rooms/1/occupants -H 'X-Tenant-ID: 2'
```

What the error numbers mean:

| Number | Meaning |
| --- | --- |
| `400` | The `X-Tenant-ID` header is missing or is not a number |
| `404` | Not found. The group, room or check-in does not exist, or it belongs to another group |
| `409` | Conflict. The child is already in a room, or was already checked out |
| `422` | The input is wrong, for example `child_id` is missing or the child does not exist |

---

## 3. How to run the tests

```bash
php artisan test
```

The tests use a temporary database, so your real data is safe. Two useful extras:

```bash
php artisan test --testdox                        # shows readable test names
php artisan test --filter=TenantIsolationTest     # runs only one test file
```

| Test file | What it checks |
| --- | --- |
| `CheckInTest` | Checking in works. Wrong input is refused. A child cannot be in two rooms |
| `CheckOutTest` | Checking out works. Checking out twice is refused |
| `RoomOccupantsTest` | Only the children in that room are listed, earliest arrival first |
| `TenantIsolationTest` | **Group A cannot see or change Group B's data**, on all three endpoints |
| `OpenCheckInConstraintTest` | The database itself refuses a second open check-in for one child |

---

## 4. How the code is organised

```
routes/api.php                       The three routes
bootstrap/app.php                    Loads the routes (without /api) and makes errors come back as JSON
app/Http/Middleware/IdentifyTenant   A check that runs first: reads X-Tenant-ID and finds the group
app/Tenancy/                         The code that keeps each group's data apart
app/Models/                          Tenant, Room, Child, CheckIn
app/Actions/                         The rules: check in, check out, list who is in a room
app/Http/Controllers/                Short files: find things, call an action, send the answer
app/Http/Requests, Resources/        Checks the input and shapes the answer
app/Exceptions/                      The two "409 conflict" errors
database/migrations/                 Builds the tables
database/seeders/                    The sample data
tests/Feature/                       The tests
```

### What happens when you check a child in

1. The request arrives at `routes/api.php`. It goes through `IdentifyTenant` first.
2. `IdentifyTenant` reads `X-Tenant-ID` and finds the nursery group. If the header is missing or wrong, the request stops here with an error.
3. `StoreCheckInRequest` checks that `child_id` is a real child of this group.
4. `CheckInController` finds the room and the child. It can only find rows that belong to this group.
5. `CheckInChild` (in `app/Actions`) checks that the child is not already in a room. Then it saves the check-in.
6. `CheckInResource` builds the JSON answer.

### How one group is kept away from another

1. `IdentifyTenant` finds the group from the `X-Tenant-ID` header.
2. Rooms, children and check-ins all follow one rule: **every database search automatically adds "only rows for this group"**. Nobody has to remember to add it.
3. The group number on new rows comes from the header. It never comes from the request body. If someone sends a different `tenant_id` in the body, it is ignored.
4. If you ask for another group's room, you get the same "not found" answer as for a room that does not exist. So nobody can even tell that it is there.
5. If the code does not know the group, it stops with an error. It never falls back to showing everything.

---

## 5. The open question: what if a child is already in a room?

The task did not say what should happen if staff check a child into a second room without checking them out of the first one. I chose to **refuse the request**. The API answers with `409 Conflict`:

```json
{
  "message": "This child is already checked into a room. Check them out first.",
  "code": "child_already_checked_in",
  "current_check_in": { "id": 7, "room_id": 1 }
}
```

**Why I chose this:**

- The whole point of this system is to know who is in each room. In a nursery this matters for safety, and for having enough staff for the number of children.
- If I **moved the child automatically**, a mistake could go unnoticed. A child could disappear from the room they are really in, and nobody would be told.
- If I **moved the child and showed a warning**, the warning is easy to miss on a busy day, and the data has already changed.
- If I **refuse**, staff need one extra tap (check out, then check in). The error also tells them exactly which check-in to close. The data can never become wrong by accident.

This rule is checked in two places: in the code, and in the database (a unique rule that allows only one open check-in per child). So even if two requests arrive at the same moment, only one works. The other gets the same `409`.

If the business wants a one-tap "move" later, it is easy to add on top of this.

## 6. Things I assumed

- `X-Tenant-ID` is a number that exists in the `tenants` table. Missing or not a number gives `400`. A group that does not exist gives `404`.
- Rooms, children and groups are created somewhere else. Here they come from the sample data.
- A child belongs to one group. The rule "only one room at a time" applies inside that group.
- Times are saved and shown in UTC.
- Checking out something that is already checked out gives `409`. I did not make it quietly succeed, so a double tap is visible.
- The URLs have no `/api` at the start, to match the task.

## 7. Things I left out on purpose

- Login and permissions (the task says to assume this is done).
- A separate "nursery" table. Rooms belong straight to a group. That is enough for these three endpoints.
- Creating rooms or children, splitting long lists into pages, limits on how often someone can call the API, and caching.
- Docker and infrastructure code. The AWS part below is only a written plan.

## 8. What I would do with more time

- Add nurseries and staff permissions, and test those limits too.
- Test two requests really arriving at the same moment, on PostgreSQL.
- Add a version of the "one open check-in" rule that works on MySQL.
- Make a repeated request from a faulty tablet safe, so it does not cause a confusing `409`.
- Keep a history of who checked each child in and out.
- Write API documentation and run the tests automatically on every code push.

## 9. Running this on AWS (ECS/Fargate)

**The container.** The image is built in two steps. First, Composer installs only the packages needed to run the app. Then a small PHP image with nginx runs the app as a normal user, not as admin. The image contains the code only. It has no `.env` file, no SQLite file and no secrets. Logs go to standard error, and ECS sends them to CloudWatch. Database changes (migrations) run as a separate one-time task during each release, not when a container starts, so containers never change the database at the same time.

**Secrets.** The app key and the database password are kept in AWS Secrets Manager. ECS gives them to the container when it starts, so they are never in the code. RDS can change the password on a schedule. Normal settings, such as `APP_ENV` and `DB_HOST`, are plain environment variables. The container gets only the AWS permissions it needs.

**The database.** Amazon RDS or Aurora with PostgreSQL, in private subnets, which have no direct internet access. Only the app's security group can reach the database port (5432), and the connection is encrypted. I prefer PostgreSQL because it supports the "one open check-in per child" rule directly. If many containers run, I would add RDS Proxy to share database connections.

**Logs and alerts.** Logs are in JSON and include a request id and a group id, but never a child's name. I would set alarms for: server errors and slow answers on the load balancer, unhealthy containers, container restarts and memory, database CPU and free disk space, and any ERROR log line. The `/up` address is the health check. Expected `409` answers are not counted as errors, but a jump in 409s or 404s for one group goes on a dashboard. Alarms notify the on-call person through SNS.

## 10. How I used AI tools

> **Change this text so it is true before you submit.**
>
> I used Claude to help write the project structure, code, tests and this README. I read every file and ran the tests myself. Things I changed or rejected: *(write your own list here, for example: why you chose "refuse" instead of "move", anything you made simpler, anything that failed on your computer and how you fixed it)*. I can explain every part of the code.