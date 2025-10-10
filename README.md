Client-side build system for PHP projects.
==========================================

This project provides a system for defining and running client-side build processes automatically, using tools already installed by your favourite client-side dependency manager.

***

<a href="https://github.com/PhpGt/Build/actions" target="_blank">
	<img src="https://badge.status.php.gt/build-build.svg" alt="Build status" />
</a>
<a href="https://app.codacy.com/gh/PhpGt/Build" target="_blank">
	<img src="https://badge.status.php.gt/build-quality.svg" alt="Code quality" />
</a>
<a href="https://app.codecov.io/gh/PhpGt/Build" target="_blank">
	<img src="https://badge.status.php.gt/build-coverage.svg" alt="Code coverage" />
</a>
<a href="https://packagist.org/packages/PhpGt/Build" target="_blank">
	<img src="https://badge.status.php.gt/build-version.svg" alt="Current version" />
</a>
<a href="http://www.php.gt/Build" target="_blank">
	<img src="https://badge.status.php.gt/build-docs.svg" alt="PHP.G/Build documentation" />
</a>

Example usage
-------------

An example `build.json` below shows three different usages:

1) `npm` has been used to install babel into the node_modules directory. The command to run is the `babel` binary within the node_modules directory. The command will execute whenever a `*.es6` file changes within the script directory.
2) `sass` has been installed to the system. The `sass` command is available on the environment PATH, and the developer has stated that at least version 3.5 is required for the build. The command will execute whenever a `*.scss` file changes within the style directory.
3) A custom PHP script is called whenever any HTML or PHP file is edited in the page directory. This assumes that the command `vendor/bin/sitemap` is installed via a composer package.

`build.json`:

```json
{
	"script/**/*.es6": {
		"name": "Babel transpile",
		"command": "./node_modules/.bin/babel",
		"args": "script/main.js -o www/script.js",
		"require": {
			"node": "^8.4",
			"@command": "^6.0"
		}
	},
	
	"style/**/*.scss": {
		"name": "Sass compilation",
		"command": "sass",
		"args": "style/main.scss www/style.css",
		"require": {
			"@command": ">=3.5"
		}
	},
	
	"page/**/*.{html|php}": {
		"name": "Sitemap generation",
		"command": "php vendor/bin/sitemap",
		"args": "src/page www/sitemap.xml"
	}
}
```

Not a dependency manager
------------------------

This library assumes the configuration of the system is already configured.

The primary objective is to provide a client-side build system that is automatically configured for PHP projects, leaving the configuration of the system down to the developer's choice of client-side dependency management software.

Features at a glance
--------------------

+ One-off builds
+ Background builds (watching the matching files and re-building where necessary)
+ Bring your own client-side dependency manager

### Proudly sponsored by

[JetBrains Open Source sponsorship program](https://www.jetbrains.com/community/opensource/)

[![JetBrains logo.](https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.svg)](https://www.jetbrains.com/community/opensource/)
