<?php
/**
 * PHP-Scoper configuration for Deploy Forge.
 *
 * Scopes the Sentry SDK and its dependencies under the DeployForge\Vendor
 * namespace to avoid conflicts with other plugins (e.g. wp-sentry).
 *
 * Usage: composer scope
 *
 * @package Deploy_Forge
 */

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix'               => 'DeployForge\\Vendor',
	'finders'              => array(
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/sentry' ),
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/guzzlehttp' ),
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/psr' ),
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/jean85' ),
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/symfony/options-resolver' ),
	),
	'patchers'             => array(),
	'exclude-namespaces'   => array(
		'Composer',
		'WordPress',
		'WP_CLI',
	),
	'exclude-files'        => array(),
	'expose-global-classes' => false,
	'expose-global-functions' => false,
);
