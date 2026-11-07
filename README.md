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

`build.ini` is the default configuration file. Each section names a file glob,
and its command executes when matching files change. `build.json` remains supported.

```ini
[script/**/*.es6]
name=Babel transpile
require=node ^8.4
execute=./node_modules/.bin/babel script/main.es6 -o www/script.js

[style/**/*.scss]
name=Sass compilation
require=sass >=3.5
execute=sass style/main.scss www/style.css
```

Run a task once per session
---------------------------

Use `run-once = true` for setup commands such as creating asset symlinks:

```ini
[asset/**/*]
require=vendor/bin/sync >=1.3.0
execute=vendor/bin/sync ./asset ./www/asset --symlink
run-once=true
```

The task runs when its glob first matches. After a successful execution it stops
scanning files and running commands for the rest of the watch session. Other tasks
continue watching normally. Restarting the build allows it to run again. An empty
glob waits until matching files appear. A failed execution does not complete the
task; it can retry when matching files subsequently change. Omitting the option or
setting it to `false` preserves normal rebuilding behaviour.

The equivalent `build.json` configuration is:

```json
{
  "asset/**/*": {
    "require": {"vendor/bin/sync": ">=1.3.0"},
    "execute": {
      "command": "vendor/bin/sync",
      "arguments": ["./asset", "./www/asset", "--symlink"]
    },
    "run-once": true
  }
}
```

INI accepts `true` or `false`; JSON requires a boolean. Mode-specific configuration
files can override this option, including setting it back to `false`.

Installing client-side packages
-------------------------------

For projects with a `package.json`, the build runner checks direct dependencies with
`npm ls --json --depth=0 --include=dev` before checking build requirements. Missing
packages or versions that do not satisfy `package.json` trigger `npm install
--include=dev` automatically, with progress shown in the terminal. Installation
must succeed and satisfy the dependency check before the build continues.
Extraneous packages alone do not trigger installation. This checks installed
dependencies, rather than whether `npm install` has ever been run; it does not
validate every transitive dependency or compare the installation against a lockfile.

The project's `node_modules/.bin` is added to PATH during the build, so commands
such as `webpack` and `sass` can use their locally installed binaries.

Install [Node.js and npm](https://docs.npmjs.com/downloading-and-installing-node-js-and-npm)
first. See [npm's installation documentation](https://docs.npmjs.com/cli/commands/npm-install)
for installing the packages declared by your project. Missing build commands and
installation failures include these links in the terminal.

Projects without a `package.json` continue to use their chosen dependency manager
and installed tools without invoking npm.

Features at a glance
--------------------

+ One-off builds
+ Background builds (watching the matching files and re-building where necessary)
+ Bring your own client-side dependency manager

# Proudly sponsored by

[JetBrains Open Source sponsorship program](https://www.jetbrains.com/community/opensource/)

[![JetBrains logo.](https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.svg)](https://www.jetbrains.com/community/opensource/)
