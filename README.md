openclerk/metrics
=================

A library for simple metrics (page, database) capture in PHP.

## Installing

Include `openclerk/metrics` as a requirement in your project `composer.json`,
and run `composer update` to install it into your project:

```json
{
  "require": {
    "openclerk/metrics": "dev-master"
  }
}
```

Make sure that you run all of the migrations that can be discovered
through [component-discovery](https://github.com/soundasleep/component-discovery);
see the documentation on [openclerk/db](https://github.com/openclerk/db) for more information.

```php
$migrations = new AllMigrations(db());
if ($migrations->hasPending(db())) {
  $migrations->install(db(), $logger);
}
```

## Features

1. Capture runtime metrics of pages and databases (through openclerk/db)
1. Optionally store runtime metrics into the database (requires migrations through component-discovery)
1. Optionally generate performance reports (requires openclerk/jobs)

## Using

This project uses [openclerk/db](https://github.com/openclerk/db) for database management,
[openclerk/events](https://github.com/openclerk/events) for capturing and processing events,
and [openclerk/config](https://github.com/openclerk/config) for config management.

Configure the component if necessary:

```php
Openclerk\Config::merge(array(
  // these are default values
  "metrics_enabled" => true,
  "metrics_db_enabled" => true,
  "metrics_page_enabled" => true,

  // store reports into the database
  "metrics_store" => false,
));
```

You now need to register the metrics events handlers, and trigger the page events
as necessary:

```php
// set up metrics
Openclerk\MetricsHandler::init(db());

// trigger page load metrics
Openclerk\Events::trigger('page_init', null);

// when rendering a page...
Openclerk\Events::trigger('page_start', null);
// do things
Openclerk\Events::trigger('page_end', null);

// print out metrics stats
print_r(Openclerk\MetricsHandler::getInstance()->printResults());
```

## TODO

1. Tests
1. CURL report jobs
1. How to extend your metrics capture (e.g. graphs metrics)
