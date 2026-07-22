# ShopVote-Votes-API — repo conventions

- All changes go through pull requests into `main` (the default branch); no direct pushes to `main`.
- Build release zips only with `./build-release.sh`. Never zip the working tree by hand: a dev vendor ships nikic/php-parser v5, which shadows PrestaShop's v4 and crashes the BO, and PowerShell's Compress-Archive writes backslash path separators that PrestaShop's module uploader rejects.
- Deploy to eichenhain.com by uploading the built zip — never by copying the working tree to the server.
- Run tests from `shopvotereviews/` with `php vendor/bin/phpunit` (needs `composer install` first).
