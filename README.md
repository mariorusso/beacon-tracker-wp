# Beacon Tracker for WordPress

WordPress plugin for [beacon-tracker](https://github.com/mariorusso/beacon-tracker). It loads the script on the front end, saves daily impression/click counts through a REST endpoint, and shows a report under Tools > Beacon Tracker.

On block themes you don't need to touch templates, posts in Query Loop blocks get `data-track-id="{post ID}"` added automatically. That gives you how often each post was seen in a listing vs how often it got clicked.

## Install

Download the repo as a zip (or clone it into `wp-content/plugins/`) and activate it. It creates one table, `{prefix}beacon_tracker_daily`, and removes it again if you delete the plugin.

Requires WordPress 6.3+ and PHP 7.4+.

To try it without a local WordPress, [WordPress Playground](https://wordpress.github.io/wordpress-playground/) works well:

```sh
npx @wp-playground/cli server --mount-dir . /wordpress/wp-content/plugins/beacon-tracker-wp --login
```

(and activate it under Plugins)

## Tracking things

**Block themes:** posts in Query Loop blocks are tagged for you. Turn that off with:

```php
add_filter( 'beacon_tracker_tag_query_loops', '__return_false' );
```

**Classic themes:** add the attributes in your loop template:

```php
<article <?php beacon_tracker_attributes(); ?> <?php post_class(); ?>>
```

It defaults to the current post ID. You can pass your own id and an optional sub item, `beacon_tracker_attributes( 'promo-banner', 'spring-sale' )`, or use `beacon_tracker_get_attributes()` to get the string instead of echoing it.

Anything else works the same as the plain script: add `data-track-id`, `data-track-item` or `data-track` in your markup. See the [beacon-tracker README](https://github.com/mariorusso/beacon-tracker#readme).

## Filters

| Filter | Default | |
|---|---|---|
| `beacon_tracker_enabled` | `true` | Load the script on the current page or not |
| `beacon_tracker_config` | `[ 'endpoint' => rest_url(...) ]` | Options passed to `BeaconTracker.init()` |
| `beacon_tracker_tag_query_loops` | `true` | Tag posts in Query Loop blocks |
| `beacon_tracker_allowed_types` | `null` (any) | Event types to accept |
| `beacon_tracker_rows` | | Rows about to be saved |

The endpoint (`POST /wp-json/beacon-tracker/v1/collect`) has to be public since anonymous visitors send to it, which also means anyone can send made-up data. Bots are dropped and everything is validated, but if your ids are post IDs it's worth only keeping posts that exist:

```php
add_filter(
	'beacon_tracker_rows',
	function ( $rows ) {
		return array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return ctype_digit( $row['id'] ) && 'publish' === get_post_status( (int) $row['id'] );
				}
			)
		);
	}
);
```

I'd usually also skip tracking for logged-in editors so the site's own team doesn't skew the numbers: `add_filter( 'beacon_tracker_enabled', fn() => ! current_user_can( 'edit_posts' ) );`.

## Notes

The batch validation (`includes/class-batch-parser.php`) is a port of `server/collect.js` from the JS repo. I kept it free of WordPress calls so it can be tested with plain PHPUnit.

Each beacon is saved with a single `INSERT ... ON DUPLICATE KEY UPDATE`, so one page visit is one query no matter how many cards were on it.

For Query Loops I used `WP_HTML_Tag_Processor` to add the attribute, and get the post ID from the `post-{ID}` class core already adds to each item.

`assets/beacon-tracker.min.js` is just the build output from the JS repo, copied over.

## Development

```sh
composer install
composer lint     # PHPCS with WordPress Coding Standards + PHPCompatibility (7.4+)
composer format   # phpcbf
composer test     # PHPUnit
```

## License

GPL-2.0-or-later
