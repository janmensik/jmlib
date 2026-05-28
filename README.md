# jmlib
PHP objects and functions for base app


## HOWTO release

phpcs src
./vendor/bin/pest
composer validate
git tag _version_ // (just number, like 1.5.13)
commit
git push origin _version_ // (just number, like 1.5.13)

git push