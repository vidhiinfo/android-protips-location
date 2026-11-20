# State summary across division installations

For a whole state (for example the 18 divisions of Uttar Pradesh), each division runs its own installation and database.
The **state site** collects only totals from every division and shows them on one screen: **Analytics > State summary**
(permission *State summary*, given to the Administrator role).

No personal data leaves a division: the feed has counts per assembly only (voters, male / female, booths, mobile numbers
collected, surveyed, support / neutral / opposition, workers, panna pramukhs, open grievances, poll-day votes and supporters
who voted). No names, EPIC numbers or mobile numbers.

## 1. On every division site (`.env`)

```
STATE_FEED_TOKEN=a-long-random-secret-for-this-division
STATE_FEED_NAME="Lucknow division"
```

* Use a different long random token for every division (for example 40 letters and digits). Anyone with the token can read the totals.
* The feed is `https://<division-site>/state-feed`. Without the token it answers 403; with no `STATE_FEED_TOKEN` it does not exist (404).
* The totals are worked out at most every 2 minutes (`STATE_FEED_SECONDS`), so a busy state site does not load the division.

## 2. On the state site (`.env`)

One line, divisions separated by `;`, each as `Name|address of the division site|its token`:

```
STATE_SOURCES="Lucknow division|https://lko.example.in|token1;Ayodhya division|https://ayd.example.in|token2"
```

* Always use `https://` addresses: the token travels with each request.
* Optional: `STATE_REFRESH_SECONDS=180` (how old the figures may be before they are fetched again).

## 3. Keep the figures fresh

Best: a cron job on the state site every 5 minutes:

```
php /path/to/state-site/artisan vis:state-pull >> /dev/null 2>&1
```

Without cron, the page fetches all divisions itself when its figures are older than `STATE_REFRESH_SECONDS`
(all divisions at the same time, 25 seconds at most each). **Refresh now** on the page fetches immediately.

## What the page shows

* State totals: voters, assemblies / booths, surveyed %, support / neutral / opposition (% of surveyed), workers and panna pramukhs,
  open grievances, poll-day turnout and supporters who voted.
* One row per division with its status: **Online**, or **Offline** with the last figures received (hover for the reason).
* Click a division for its assemblies; **Open division site** goes to that division.
* **Excel**: every assembly of every division in one sheet.
* The page refreshes itself every few minutes (useful on a war-room screen).
