# Contributing

If you have any questions please pester us in [yunity slack](https://slackin.yunity.org/) in #foodsharing-dev channel.

## Coding guidelines

We do not have specific coding guidelines yet, please use your common sense and make it fit with the existing code.

## Found an issue?

Submit an issue in our [issues](https://gitlab.com/foodsharing-dev/foodsharing/issues)!

If you feel comfortable submitting a fix too, follow the next section.

## Submitting a change

* ensure there is an issue for the change in the [issues](https://gitlab.com/foodsharing-dev/foodsharing/issues)
    (this is a seperate project as it is public, and the repo is not)
    (If you are just submitting a very small change or a doc fix don't worry about creating an issue)
* make your changes in a new git branch
    (rather than fork the repo, as it makes permissions trickier)
* ensure the tests pass locally `./scripts/test`
* create a merge request for your branch prefixed with the issue number
    e.g. issue number `56` would have a branch named `56-some-descriptive-words`
    (optionally add your name, e.g. `56-nicksellen-some-descriptive-words`)
    Prefix the name of the merge request with `WIP:` if it is not ready to merge yet
* wait! somebody will ready it and ask you questions, or will go ahead and merge

## Testing

You can run the tests with `./scripts/test`,
once you have run them once you can use `./scripts/test-rerun` which runs much quicker
(so long as we keep writing the tests to run idempotently, please do!).

So far end to end testing is working nicely (called acceptance tests in codeception).
They run with a headless firefox and selenium inside the docker setup, they are run on CI build too.

We are working on [restructing the code](https://gitlab.com/foodsharing-dev/foodsharing/issues/68)
to enable unit testing.

The test contains stay around after running, and you can visit the test app
[in your browser](http://localhost:28080/), and it has
[it's own phpmyadmin](http://localhost:28081/).

If you want to run with debug mode turned on use: `./scripts/test --debug`.

If you just want to run one test pass the path to that test as an argument,
e.g. `./scripts/test tests/acceptance/LoginCept.php`