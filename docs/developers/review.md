# Reviewing the merge requests

Each change to Pollaris must be reviewed through a code review.
A template with a checklist is provided and filled for each merge request.

Some items of the checklist may feel out of context: please check them anyway.

If you don’t know how to do a specific task of the checklist, please take a look at the following explanations.

## “Code is manually tested”

This is to remind you to test the changes yourself.
The idea is to follow the instructions given in the “How to test manually” section, and to verify that it works as expected.
Sometimes, you’ll realize at this stage that something important has been forgotten.

## “Interface works on both mobile and big screen”

The application must work on desktop and mobiles.
If the interface is changed, you should check that it still works on mobile.
For that, you can use the “mobile mode” of your browser (e.g. <kbd>CTRL + ALT + M</kbd> in Firefox).

## “Interface works on both Firefox and Chrome”

The application must work well on all major browsers.
We strongly support Firefox and it is our standard web browser as we develop with it.
However, Chrome is the number one brower in terms of market share.
Please always try your changes with at least Firefox and Chrome (or Chromium, or at least a Webkit browser).

## “Accessibility has been tested”

An inaccessible application is a broken one and an accessibility issue must be considered as a bug.
However, testing the accessibility can be pretty hard when you’re not an expert.
To get started, you can use the [WAVE browser extension](https://wave.webaim.org/extension/).
This extension highlight some usual issues.
Please try to fix the ones related to the changes of the merge request.

Additional testing can include testing with a screen reader, or directly with people who need accessibility features.
However, these solutions are more difficult to get right.

## “Translations are synchronized”

The application is provided in English and in French.
The French translation must be synchronized with the English one.

Run the command:

```console
$ make translations
```

## “Tests are up-to-date”

Everything in the application is not tested, but we try to write the most pertinent tests.
If a change impacts a controller, it’s likely that some tests must be written about the change.
The tests are located under [the `tests/` folder](/tests) and are written with [PHPUnit](https://docs.phpunit.de).
[Learn how to execute the tests.](/docs/developers/tests.md)

## “Copyright notices are up to date”

At the top of each file, a small comment indicates the copyright of the file and the license.
If you’ve made a significant change to the file, please add a line:

```
Copyright <year> <your name>
```

## “Documentation is up to date”

Keeping the documentation up to date is a difficult task.
We try to make it easy by making sure that we’ve documented everything relevant in each merge request.
The documentation includes everything under [`docs/`](/docs), the [“readme”](/README.md), and the migration notes in the [changelog](/CHANGELOG.md).
The migration notes are important information that needs to be carried out when upgrading to the next version of the application.
It’s not often that we have to document this, though.

## “Merge request has been reviewed and approved”

This is to remind you to review the changes in the code and to approve the merge request.
