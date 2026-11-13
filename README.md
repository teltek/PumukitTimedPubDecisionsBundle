# TimedPubDecisionsBundle

Bundle for the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher. It lets the
editors make a publication decision (for example *Featured TV*) temporary, with a start and an end date, and
publishes the video automatically when the start date is reached.

## Installation

```bash
composer require teltek/pumukit-timed-pub-decisions-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\TimedPubDecisionsBundle\PumukitTimedPubDecisionsBundle::class => ['all' => true],
```

Create the default temporized decisions, `PUDERADIO` and `PUDETV`, under `PUBDECISIONS` with
`php bin/console pumukit:setup` of PuMuKIT, which runs the setup of every enabled bundle, or only with:

```bash
php bin/console timedpubdecisions:init:tags
```

Run the publication every few minutes, for example in the crontab:

```
*/5 * * * * /usr/local/bin/php /srv/pumukit/bin/console timedpubdecisions:publish:objects --env=prod
```

## Configuration

```yaml
pumukit_timed_pub_decisions:
    enable: true
```

| Option | Default | Description |
|--------|---------|-------------|
| `enable` | `true` | If false, the decisions are not temporized, the pages return 404 and nothing else of the bundle is loaded: no commands, listener, menu or permission |
| `default_group_temporized` | `year` | Not used; kept so that the existing configurations still load |

## How it works

A publication decision is temporized when its tag is under `PUBDECISIONS` and has the property
`route: pumukit_timed_pub_decisions_index`, which `timedpubdecisions:init:tags` sets. The publication tab, the
command, the timeline and the portal use the same rule, so another decision can be temporized by giving its tag that
property.

In the **Publication** tab of a video, each temporized decision that is checked can be **Permanent** or **Temporary**.
A temporary decision needs a start and an end, with the end after the start; they are local dates and times in the
timezone of the server.

`timedpubdecisions:publish:objects` (with `--dry-run` to only list them):

- publishes the blocked or hidden videos whose temporary decision has started;
- applies each start date only once: it saves when it was applied (`temporized_published_<CODE>`), so a video that is
  unpublished by hand afterwards is not published again. Changing the start date, or making the decision permanent and
  temporary again, allows a new publication;
- does not unpublish the video at the end date: the end only takes it out of the decision, for example out of the
  featured list of the portal.

The page **Timed Publishing Decisions Timeframes** of the back-office, with the `ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE`
permission, shows the decisions of a 90-day period, filtered by decision and status. The portal lists the active
videos of each decision at `/destacados/<CODE>/`.

## Upgrade from 5.x

**Run this once, before the first publication with this version:**

```bash
php bin/console timedpubdecisions:upgrade --dry-run
php bin/console timedpubdecisions:upgrade
```

It normalizes the saved dates and marks the decisions whose start date has passed as already applied. Without it,
the first run of `timedpubdecisions:publish:objects` would publish every blocked or hidden video with a started
decision: the ones that were unpublished by hand and, on installations that never ran the command, all the old ones.

- The bundle requires PHP 8.5 and PuMuKIT 6.0.
- The options of the publication tab and the timeline are Stimulus controllers with Bootstrap 5; the timeline no
  longer uses Chart.js. The JSON route `pumukit_newadmin_timeframes_json` is removed.
- The options route moves from `/pubchannel/option/{id}/{pub}`, which was public, to
  `/admin/timedpubdecisions/option/{id}/{pub}`, and needs `ROLE_ACCESS_MULTIMEDIA_SERIES`. The timeline checks its
  permission.
- Invalid dates, or an end before the start, are rejected when the publication tab is saved.
- `timedpubdecisions:init:tags` only creates the tags that do not exist; before, it deleted them and created them again
  on every run when no multimedia object used them. `--force` keeps that behaviour. `pumukit:setup` runs it on every
  deployment.
