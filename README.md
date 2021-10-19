PuMuKIT TimedPubDecisions Bundle
================================

This bundle allows publishing and unpublished media configuring dates or range dates.

How to install bundle
```bash
composer require teltek/pumukit-timed-pub-decisions-bundle
```

if not, add this to config/bundles.php

```
Pumukit\TimedPubDecisionsBundle\PumukitTimedPubDecisionsBundle::class => ['all' => true]
```

Initialize bundle
```
php bin/console pumukit:timed:pub:decisions:init:tags
```

Then execute the following commands

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console assets:install
```

