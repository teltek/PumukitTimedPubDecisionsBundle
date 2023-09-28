Timed Publication Decisions Bundle
==================================

This bundle provides automatic timed publication video decisions.

```bash
composer require teltek/pumukit-timed-pub-decisions-bundle
```

if not, add this to config/bundles.php

```
Pumukit\TimedPubDecisionsBundle\PumukitTimedPubDecisionsBundle::class => ['all' => true]
```

Then execute the following commands

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console assets:install
php bin/console timedpubdecisions:init:tags
```
