# Changelog

## unreleased

### Migration notes

The worker now runs scheduled messages.
You must add `scheduler_default` to the list of consumed queues of the worker.
See the `pollaris-worker.service` example in [the documentation](/docs/administrators/install.md).

You now can set the `POLL_EXPIRES_COMPLETED` and `POLL_EXPIRES_INCOMPLETE` environment variable to automatically clean expired polls.
See [`env.sample`](/env.sample) for more information.
You'll need to restart the worker after setting these variables.

## 2026-01-25 - 1.1.3

### Features

- Add Italian translation ([c0e3f5f](https://framagit.org/pollaris/pollaris/-/commit/c0e3f5f), [59313fc](https://framagit.org/pollaris/pollaris/-/commit/59313fc))
- Add German translation ([abeaf40](https://framagit.org/pollaris/pollaris/-/commit/abeaf40), [567dbc3](https://framagit.org/pollaris/pollaris/-/commit/567dbc3))

### Bug fixes

- Send email notifications using user's locale ([4af100c](https://framagit.org/pollaris/pollaris/-/commit/4af100c))

### Maintenance

- Update the dependencies ([627427b](https://framagit.org/pollaris/pollaris/-/commit/627427b))
- Update code to comply with Rector recommandations ([e02b91f](https://framagit.org/pollaris/pollaris/-/commit/e02b91f))
- Update copyright year ([e93b256](https://framagit.org/pollaris/pollaris/-/commit/e93b256))

## 2025-11-21 - 1.1.2

### Bug fixes

- Enable Occitan translation in the calendar ([14b1a92](https://framagit.org/pollaris/pollaris/-/commit/14b1a92))

### Documentation

- Add documentation about translating Pollaris ([5d0aa72](https://framagit.org/pollaris/pollaris/-/commit/5d0aa72))

### Maintenance

- Refactor supported locales in `Locales.php` ([f82cf0f](https://framagit.org/pollaris/pollaris/-/commit/f82cf0f))

## 2025-11-20 - 1.1.1

### Features

- Add support for the Occitan translation ([96d93c0](https://framagit.org/pollaris/pollaris/-/commit/96d93c0))

## 2025-11-16 - 1.1.0

### Improvements

- Add a link to the poll's results ([a1303ef](https://framagit.org/pollaris/pollaris/-/commit/a1303ef))
- Improve separation between comments and results ([f8d2170](https://framagit.org/pollaris/pollaris/-/commit/f8d2170))
- Move explanations of "My votes" in a modal ([a0d8d46](https://framagit.org/pollaris/pollaris/-/commit/a0d8d46))
- Humanise the CSRF error message ([2852eb0](https://framagit.org/pollaris/pollaris/-/commit/2852eb0))

### Documentation

- Add "clear cache" instruction when updating to the admin documentation ([2939335](https://framagit.org/pollaris/pollaris/-/commit/2939335))

### Maintenance

- Update the dependencies ([d6f4ec7](https://framagit.org/pollaris/pollaris/-/commit/d6f4ec7), [b572369](https://framagit.org/pollaris/pollaris/-/commit/b572369))
- Update the license in composer.json ([ac8970b](https://framagit.org/pollaris/pollaris/-/commit/ac8970b))

## 2025-10-11 - 1.0.0

### Features

- Allow to disable the "maybe" votes ([b899dcb](https://framagit.org/pollaris/pollaris/-/commit/b899dcb))
- Make default "no" vote optional ([cb94199](https://framagit.org/pollaris/pollaris/-/commit/cb94199))
- Allow to select a period of dates in the calendar ([0aaa1dc](https://framagit.org/pollaris/pollaris/-/commit/0aaa1dc))
- Redirect to admin/summary after editing previous poll form step ([8421070](https://framagit.org/pollaris/pollaris/-/commit/8421070), [51ac337](https://framagit.org/pollaris/pollaris/-/commit/51ac337))
- Provide a dark theme ([fcddda9](https://framagit.org/pollaris/pollaris/-/commit/fcddda9), [7dee514](https://framagit.org/pollaris/pollaris/-/commit/7dee514))
- Interpret poll description and comments as Markdown ([d6841b0](https://framagit.org/pollaris/pollaris/-/commit/d6841b0), [ef80202](https://framagit.org/pollaris/pollaris/-/commit/ef80202))

### Improvements

- Add a shadow over the proposals table scrollable sides ([8dc967f](https://framagit.org/pollaris/pollaris/-/commit/8dc967f))
- Rename "maybe" votes in "if needed" ([3757ae3](https://framagit.org/pollaris/pollaris/-/commit/3757ae3))
- Rename nickname in name ([e61b00f](https://framagit.org/pollaris/pollaris/-/commit/e61b00f))
- Add explanations about time slots ([6424bc7](https://framagit.org/pollaris/pollaris/-/commit/6424bc7))
- Add explanations about the poll types ([4ceb3d3](https://framagit.org/pollaris/pollaris/-/commit/4ceb3d3))
- Increase the density of the admin page ([d6bdb8c](https://framagit.org/pollaris/pollaris/-/commit/d6bdb8c))
- Move the language form in a preferences modal ([880b67a](https://framagit.org/pollaris/pollaris/-/commit/880b67a))
- Focus calendar on the first selected date on loading ([3e917a1](https://framagit.org/pollaris/pollaris/-/commit/3e917a1))
- Customise the error pages ([bc05da3](https://framagit.org/pollaris/pollaris/-/commit/bc05da3))

### Bug fixes

- Fix detection of preferred language ([d8193b6](https://framagit.org/pollaris/pollaris/-/commit/d8193b6))
- Fix overflow of author names ([c24e1f6](https://framagit.org/pollaris/pollaris/-/commit/c24e1f6))

### Maintenance

- Add missing PHP extensions to the requirements ([13a0e1e](https://framagit.org/pollaris/pollaris/-/commit/13a0e1e))
- Update the dependencies ([d8a5a45](https://framagit.org/pollaris/pollaris/-/commit/d8a5a45))

### Documentation

- Fix the deployment instructions ([a7553c5](https://framagit.org/pollaris/pollaris/-/commit/a7553c5), [ce69287](https://framagit.org/pollaris/pollaris/-/commit/ce69287))
- Document Pollaris installed behind a reverse proxy ([0c78a75](https://framagit.org/pollaris/pollaris/-/commit/0c78a75))
- Add a screenshot to the README ([caaa338](https://framagit.org/pollaris/pollaris/-/commit/caaa338))

### Developers

- Rename "Process" in "Flow" ([2a5e982](https://framagit.org/pollaris/pollaris/-/commit/2a5e982))
- Refactor handling summary/admin pages ([ce66f65](https://framagit.org/pollaris/pollaris/-/commit/ce66f65))
- Recommend to get code with SSH in development ([911aa9d](https://framagit.org/pollaris/pollaris/-/commit/911aa9d))

## 2025-09-28 - 0.7.0

### Features

- Remember created polls and votes in the browser ([e85d7a1](https://framagit.org/pollaris/pollaris/-/commit/e85d7a1))
- Allow to vote even if the user has already voted ([cf7e38d](https://framagit.org/pollaris/pollaris/-/commit/cf7e38d))
- Add a closing date to the polls ([bd8f094](https://framagit.org/pollaris/pollaris/-/commit/bd8f094))
- Provide a results chart ([f34c4c4](https://framagit.org/pollaris/pollaris/-/commit/f34c4c4))
- Allow to disable the vote edition ([fa8f590](https://framagit.org/pollaris/pollaris/-/commit/fa8f590))
- Allow to print the polls ([d1a1bb1](https://framagit.org/pollaris/pollaris/-/commit/d1a1bb1))
- Allow to export polls as CSV ([4b0b3d6](https://framagit.org/pollaris/pollaris/-/commit/4b0b3d6))
- Allow to make poll results private ([3bea392](https://framagit.org/pollaris/pollaris/-/commit/3bea392))
- Allow to delete a poll ([3a6dc7f](https://framagit.org/pollaris/pollaris/-/commit/3a6dc7f))
- Display sum of "yes" in the table view ([782232e](https://framagit.org/pollaris/pollaris/-/commit/782232e))
- Ask to confirm page closing while vote is not submitted ([a9c214b](https://framagit.org/pollaris/pollaris/-/commit/a9c214b))

### Improvements

- Increase the density of polls' list view ([3f2b0da](https://framagit.org/pollaris/pollaris/-/commit/3f2b0da))
- Target form when accessing the vote edition ([6aed527](https://framagit.org/pollaris/pollaris/-/commit/6aed527))
- Move the polls search form to the "my" page ([48e59fb](https://framagit.org/pollaris/pollaris/-/commit/48e59fb))
- Keep the dates visible when voting ([1dac379](https://framagit.org/pollaris/pollaris/-/commit/1dac379))
- Improve the readibility of poll votes ([dd11698](https://framagit.org/pollaris/pollaris/-/commit/dd11698))
- Move the results below the poll proposals ([756d11d](https://framagit.org/pollaris/pollaris/-/commit/756d11d))
- Rename the "preferred choices" section into "results" ([9b18d6b](https://framagit.org/pollaris/pollaris/-/commit/9b18d6b))
- Increase slightly the width of small wrapper ([4717fce](https://framagit.org/pollaris/pollaris/-/commit/4717fce))
- Improve the look of the poll actions buttons ([03302aa](https://framagit.org/pollaris/pollaris/-/commit/03302aa))
- Remove the comment form when editing a vote ([67b8758](https://framagit.org/pollaris/pollaris/-/commit/67b8758))
- Add a "clone" icon on the "apply same slot" button ([77317d4](https://framagit.org/pollaris/pollaris/-/commit/77317d4))
- Move the "go to poll" link to the top of the admin page ([6c15640](https://framagit.org/pollaris/pollaris/-/commit/6c15640))
- Keep hover style when locale menu is opened ([366a517](https://framagit.org/pollaris/pollaris/-/commit/366a517))
- Decrease margin between vote author and submit ([d820943](https://framagit.org/pollaris/pollaris/-/commit/d820943))

### Bug fixes

- Consider newlines in polls descriptions ([182afdc](https://framagit.org/pollaris/pollaris/-/commit/182afdc))
- Allow to switch between table and list views without losing answers ([2cac5eb](https://framagit.org/pollaris/pollaris/-/commit/2cac5eb))
- Display slots in their initial order ([b7d5c34](https://framagit.org/pollaris/pollaris/-/commit/b7d5c34))
- Fix clicking on buttons containing a SVG icon ([61434c9](https://framagit.org/pollaris/pollaris/-/commit/61434c9))
- Fix the "cancel link" URL in poll settings ([908b982](https://framagit.org/pollaris/pollaris/-/commit/908b982))
- Fix the outline color of danger buttons ([62c96de](https://framagit.org/pollaris/pollaris/-/commit/62c96de))

### Technical

- Add a robots.txt ([3ce8fed](https://framagit.org/pollaris/pollaris/-/commit/3ce8fed))
- Add logs above the warning level under var/log in production ([a6e1c7f](https://framagit.org/pollaris/pollaris/-/commit/a6e1c7f))
- Update the dependencies ([7c51898](https://framagit.org/pollaris/pollaris/-/commit/7c51898), [59d0b02](https://framagit.org/pollaris/pollaris/-/commit/59d0b02))

### Developers

- Add utility function to escape HTML ([7c1f3e4](https://framagit.org/pollaris/pollaris/-/commit/7c1f3e4))
- Add methods to store info in localStorage ([c6e773e](https://framagit.org/pollaris/pollaris/-/commit/c6e773e))
- Add a protected button Stimulus controller ([c5bcb8a](https://framagit.org/pollaris/pollaris/-/commit/c5bcb8a))
- Add style for disabled buttons and danger elements ([eb58716](https://framagit.org/pollaris/pollaris/-/commit/eb58716))
- Handle fetched modal content ([af60c91](https://framagit.org/pollaris/pollaris/-/commit/af60c91))
- Refactor grouping proposals by dates ([12fa5aa](https://framagit.org/pollaris/pollaris/-/commit/12fa5aa))
- Add thumbtack icons ([09e8a2f](https://framagit.org/pollaris/pollaris/-/commit/09e8a2f))
- Fix closing button markup in admin ([36d9d0b](https://framagit.org/pollaris/pollaris/-/commit/36d9d0b))

## 2025-07-23 - 0.6.2

### Bug fixes

- Fix loading timeouts ([8d342e9](https://framagit.org/pollaris/pollaris/-/commit/8d342e9))

### Technical

- Update the dependencies ([c9e17b1](https://framagit.org/pollaris/pollaris/-/commit/c9e17b1))

### Developers

- Remove useless image name in docker-compose.yml ([35a1f67](https://framagit.org/pollaris/pollaris/-/commit/35a1f67))

## 2025-07-16 - 0.6.1

### Bug fixes

- Fix the order of the date proposals ([294746e](https://framagit.org/pollaris/pollaris/-/commit/294746e))
- Order polls' votes by their creation dates ([89d4625](https://framagit.org/pollaris/pollaris/-/commit/89d4625))

### Developers

- Update the dependencies ([29df41e](https://framagit.org/pollaris/pollaris/-/commit/29df41e))

## 2025-06-29 - 0.6.0

### Migration notes

A new `APP_REQUIRE_EMAILS` is available to force the polls' authors to enter an email.
See the [`env.sample`](/env.sample) file.

You can now create an admin to search for polls.
Create an admin with:

```console
www-data$ php bin/console app:user:create
```

Then, open the login page at `https://pollaris.example.org/login`.

### New

- Provide an admin to search polls ([4fd28ba](https://framagit.org/pollaris/pollaris/-/commit/4fd28ba))
- Allow to force polls' authors to enter an email ([df01b70](https://framagit.org/pollaris/pollaris/-/commit/df01b70))
- Send the admin link to author email ([076be01](https://framagit.org/pollaris/pollaris/-/commit/076be01))

### Improvements

- Allow polls' authors to find link to admin more easily ([ddde37d](https://framagit.org/pollaris/pollaris/-/commit/ddde37d))
- Add a modal to share the poll ([6134d88](https://framagit.org/pollaris/pollaris/-/commit/6134d88))
- Rename "advanced settings" in "poll options" ([cbe2134](https://framagit.org/pollaris/pollaris/-/commit/cbe2134))
- Enable notifications by default ([4ea5ddc](https://framagit.org/pollaris/pollaris/-/commit/4ea5ddc))
- Explain time slots can be selected at step 3 ([b136a7d](https://framagit.org/pollaris/pollaris/-/commit/b136a7d))
- Move the button to edit the vote ([486fbd8](https://framagit.org/pollaris/pollaris/-/commit/486fbd8))
- Move the "copy edit vote link" on the edit page ([c03bbac](https://framagit.org/pollaris/pollaris/-/commit/c03bbac))
- Add a link to the source code ([24a31a4](https://framagit.org/pollaris/pollaris/-/commit/24a31a4))

### Bug fixes

- Make sure the poll table has a white background ([cf8b9fa](https://framagit.org/pollaris/pollaris/-/commit/cf8b9fa))

### Technical

- Improve the performance of the poll page ([73adc5c](https://framagit.org/pollaris/pollaris/-/commit/73adc5c))
- Allow to create administrators ([a4a9d7d](https://framagit.org/pollaris/pollaris/-/commit/a4a9d7d))
- Allow to customise the home page ([8d53971](https://framagit.org/pollaris/pollaris/-/commit/8d53971))
- Allow to provide custom CSS and JS ([ff99fa8](https://framagit.org/pollaris/pollaris/-/commit/ff99fa8))
- Update the dependencies ([5c8bd80](https://framagit.org/pollaris/pollaris/-/commit/5c8bd80), [69b3878](https://framagit.org/pollaris/pollaris/-/commit/69b3878), [651890a](https://framagit.org/pollaris/pollaris/-/commit/651890a), [21f8ad0](https://framagit.org/pollaris/pollaris/-/commit/21f8ad0))

### Developers

- Provide a `ILIKE` DQL function ([7a8df8a](https://framagit.org/pollaris/pollaris/-/commit/7a8df8a))
- Provide a Pagination component ([2774a5e](https://framagit.org/pollaris/pollaris/-/commit/2774a5e))
- Fix the `make db-reset` command ([40b36be](https://framagit.org/pollaris/pollaris/-/commit/40b36be))
- Update the Twig extensions ([0db834e](https://framagit.org/pollaris/pollaris/-/commit/0db834e))
- Rename EsbuildAssetExtension into AssetExtension ([6175dc7](https://framagit.org/pollaris/pollaris/-/commit/6175dc7))
- Sort the translation files ([786af10](https://framagit.org/pollaris/pollaris/-/commit/786af10))
- Remove `--no-fill` from make translations command ([86772c9](https://framagit.org/pollaris/pollaris/-/commit/86772c9))

## 2025-05-18 - 0.5.0

### Migration notes

You must configure a mail server to send notifications.
See the `MAILER_` environment variables in the `.env.sample` file and update your `.env.local` file.
You also need to configure a Messenger worker to send the emails asynchronously.
Read [the installation documentation](/docs/administrators/install.md).

You can also change the default name of the application from Pollaris to anything else by changing the `APP_NAME` environment variable.

### New

- Allow to change the language ([3e659ba](https://framagit.org/pollaris/pollaris/-/commit/3e659ba))
- Allow to protect polls with a password ([72cc4ce](https://framagit.org/pollaris/pollaris/-/commit/72cc4ce), [58a3bce](https://framagit.org/pollaris/pollaris/-/commit/58a3bce), [37b4611](https://framagit.org/pollaris/pollaris/-/commit/37b4611))
- Allow to comment on the polls ([978d20b](https://framagit.org/pollaris/pollaris/-/commit/978d20b))
- Allow to receive notifications on new votes ([5ae38dd](https://framagit.org/pollaris/pollaris/-/commit/5ae38dd))
- Allow to receive emails on new comments ([54ef73c](https://framagit.org/pollaris/pollaris/-/commit/54ef73c))
- Provide an admin page to delete votes and comments ([8dcff9f](https://framagit.org/pollaris/pollaris/-/commit/8dcff9f))
- Allow to find polls associated to an email ([bd5e80c](https://framagit.org/pollaris/pollaris/-/commit/bd5e80c), [94c510e](https://framagit.org/pollaris/pollaris/-/commit/94c510e))

### Improvements

- Rework and simplify the poll creation process ([bcfb276](https://framagit.org/pollaris/pollaris/-/commit/bcfb276), [02efb58](https://framagit.org/pollaris/pollaris/-/commit/02efb58))
- Provide a button to copy the vote edition link ([3f1bad9](https://framagit.org/pollaris/pollaris/-/commit/3f1bad9))
- Maintain display mode after voting ([81662da](https://framagit.org/pollaris/pollaris/-/commit/81662da))
- Change header background color to primary ([4f3d996](https://framagit.org/pollaris/pollaris/-/commit/4f3d996))
- Change button--success by button--primary ([ecfeb03](https://framagit.org/pollaris/pollaris/-/commit/ecfeb03))
- Add an illustration on the home page ([444e460](https://framagit.org/pollaris/pollaris/-/commit/444e460))
- Improve the look of the Turbo progress bar ([fc8be15](https://framagit.org/pollaris/pollaris/-/commit/fc8be15), [664de12](https://framagit.org/pollaris/pollaris/-/commit/664de12))
- Decrease panel padding on mobile ([e57bbb3](https://framagit.org/pollaris/pollaris/-/commit/e57bbb3))

### Bug fixes

- Fix editing votes failing after adding a proposal ([33acb8d](https://framagit.org/pollaris/pollaris/-/commit/33acb8d))
- Fix display of wrong answers in table view ([c5db468](https://framagit.org/pollaris/pollaris/-/commit/c5db468))
- Fix spacing between proposals in list mode ([4c56a1f](https://framagit.org/pollaris/pollaris/-/commit/4c56a1f))
- Fix the text overflow in tables ([4bf6efd](https://framagit.org/pollaris/pollaris/-/commit/4bf6efd))

### Technical

- Allow to customise the application name ([fe75b19](https://framagit.org/pollaris/pollaris/-/commit/fe75b19))
- Add support for PHP 8.4 ([7f93108](https://framagit.org/pollaris/pollaris/-/commit/7f93108))
- Update the dependencies ([5e4d275](https://framagit.org/pollaris/pollaris/-/commit/5e4d275))
- Setup the mailer component ([839e670](https://framagit.org/pollaris/pollaris/-/commit/839e670), [c647f6b](https://framagit.org/pollaris/pollaris/-/commit/c647f6b))

### Developers

- Add style to disabled inputs ([c8867cd](https://framagit.org/pollaris/pollaris/-/commit/c8867cd))
- Add style for checkboxes ([832c7a0](https://framagit.org/pollaris/pollaris/-/commit/832c7a0))
- Remove unused CSS for disabled radios ([c00194b](https://framagit.org/pollaris/pollaris/-/commit/c00194b))

## 2025-04-16 - 0.4.0

### New

- Allow to customise the links to the polls ([78010c3](https://framagit.org/pollaris/pollaris/-/commit/78010c3))
- Allow to limit the number of "yes" answers ([57ceb96](https://framagit.org/pollaris/pollaris/-/commit/57ceb96))
- Allow to display the polls as tables ([510a027](https://framagit.org/pollaris/pollaris/-/commit/510a027))

### Improvements

- Move the vote on the poll page ([32e9a40](https://framagit.org/pollaris/pollaris/-/commit/32e9a40))
- Always display poll details and summary ([fa79228](https://framagit.org/pollaris/pollaris/-/commit/fa79228))
- Change the vote cursor by a pointer ([8e3b2ca](https://framagit.org/pollaris/pollaris/-/commit/8e3b2ca))
- Display count of preferred choices in bold ([ed71e8b](https://framagit.org/pollaris/pollaris/-/commit/ed71e8b))
- Improve the look of the vote icons ([0192af6](https://framagit.org/pollaris/pollaris/-/commit/0192af6))
- Increase contrast of "maybe" votes ([ef60fe7](https://framagit.org/pollaris/pollaris/-/commit/ef60fe7))

### Bug fixes

- Allow to vote without selecting options ([2430897](https://framagit.org/pollaris/pollaris/-/commit/2430897))

### Technical

- Update the dependencies ([1e1976f](https://framagit.org/pollaris/pollaris/-/commit/1e1976f), [a1d89d7](https://framagit.org/pollaris/pollaris/-/commit/a1d89d7), [9312639](https://framagit.org/pollaris/pollaris/-/commit/9312639), [43a26d3](https://framagit.org/pollaris/pollaris/-/commit/43a26d3), [fb9f0f5](https://framagit.org/pollaris/pollaris/-/commit/fb9f0f5), [d573ffb](https://framagit.org/pollaris/pollaris/-/commit/d573ffb), [33e86f9](https://framagit.org/pollaris/pollaris/-/commit/33e86f9), [f4f5436](https://framagit.org/pollaris/pollaris/-/commit/f4f5436), [15243c1](https://framagit.org/pollaris/pollaris/-/commit/15243c1), [2933866](https://framagit.org/pollaris/pollaris/-/commit/2933866))

### Developers

- Add a `.no-mobile` class ([05c9426](https://framagit.org/pollaris/pollaris/-/commit/05c9426))
- Move `.radio--vote` class to custom/votes.css ([a4f649d](https://framagit.org/pollaris/pollaris/-/commit/a4f649d))
- Extract a `proposals/_list` partial template ([bcdfc67](https://framagit.org/pollaris/pollaris/-/commit/bcdfc67))
- Fix running local tests ([fb8b5df](https://framagit.org/pollaris/pollaris/-/commit/fb8b5df))

## 2025-01-12 - 0.3.0

### New

- Allow to select dates with a calendar ([c874ec3](https://framagit.org/pollaris/pollaris/-/commit/c874ec3))
- Add a summary screen to the poll creation steps ([ad6fa64](https://framagit.org/pollaris/pollaris/-/commit/ad6fa64))

### Improvements

- Improve the design of the poll screen ([87d441f](https://framagit.org/pollaris/pollaris/-/commit/87d441f))
- Add a button to switch between simple/details vote views ([089b5cc](https://framagit.org/pollaris/pollaris/-/commit/089b5cc))
- Redesign the vote form ([762438b](https://framagit.org/pollaris/pollaris/-/commit/762438b))
- Improve the workflow after voting ([daadabd](https://framagit.org/pollaris/pollaris/-/commit/daadabd))
- Allow to edit poll's title and description ([5882bdc](https://framagit.org/pollaris/pollaris/-/commit/5882bdc))
- Block access to poll while creation is not complete ([1ba84b2](https://framagit.org/pollaris/pollaris/-/commit/1ba84b2))
- Replace the submit labels by "Next" ([d537836](https://framagit.org/pollaris/pollaris/-/commit/d537836))
- Add a "previous" buttons to the forms ([f58f6b1](https://framagit.org/pollaris/pollaris/-/commit/f58f6b1))
- Explain how the email is used in the interface ([24bd672](https://framagit.org/pollaris/pollaris/-/commit/24bd672))
- Increase the global border radius ([e6dc7c0](https://framagit.org/pollaris/pollaris/-/commit/e6dc7c0))
- Make "Apply same slot to all dates" more visible ([d2b802b](https://framagit.org/pollaris/pollaris/-/commit/d2b802b))
- Validate dates are not empty in form ([20211f3](https://framagit.org/pollaris/pollaris/-/commit/20211f3))

### Developers

- Add a notification component ([172c4ae](https://framagit.org/pollaris/pollaris/-/commit/172c4ae))
- Add a copy-to-clipboard Stimulus controller ([6459a1a](https://framagit.org/pollaris/pollaris/-/commit/6459a1a))
- Add more icons ([d1a2a61](https://framagit.org/pollaris/pollaris/-/commit/d1a2a61), [799509b](https://framagit.org/pollaris/pollaris/-/commit/799509b), [c2b1f4c](https://framagit.org/pollaris/pollaris/-/commit/c2b1f4c))
- Add a warning color to CSS ([d12fe12](https://framagit.org/pollaris/pollaris/-/commit/d12fe12))
- Generate fieldsets for compound form rows ([e082e1a](https://framagit.org/pollaris/pollaris/-/commit/e082e1a))
- Add CSS for the "vote" radio buttons ([5028201](https://framagit.org/pollaris/pollaris/-/commit/5028201))
- Remove padding/margin on fieldsets in fieldset ([2ec3a4b](https://framagit.org/pollaris/pollaris/-/commit/2ec3a4b))
- Add a text-block CSS class ([cc6b0a5](https://framagit.org/pollaris/pollaris/-/commit/cc6b0a5))
- Add CSS classes for panels ([0014002](https://framagit.org/pollaris/pollaris/-/commit/0014002))
- Add style to h4 titles ([0887839](https://framagit.org/pollaris/pollaris/-/commit/0887839))
- Change style of h3 titles ([491bb07](https://framagit.org/pollaris/pollaris/-/commit/491bb07))
- Add a CSS text--success class ([ca94254](https://framagit.org/pollaris/pollaris/-/commit/ca94254))
- Add a separator to the panel component ([24e72e1](https://framagit.org/pollaris/pollaris/-/commit/24e72e1))
- Improve style of dl lists ([b6f6796](https://framagit.org/pollaris/pollaris/-/commit/b6f6796))
- Fix icons to better adapt to adjacent text ([fc57375](https://framagit.org/pollaris/pollaris/-/commit/fc57375))
- Add style for a "success" button ([e4ce252](https://framagit.org/pollaris/pollaris/-/commit/e4ce252))
- Add functions to Stimulus "collection" controller ([0a325d8](https://framagit.org/pollaris/pollaris/-/commit/0a325d8))
- Add a CSS class text--secondary ([9e9fb97](https://framagit.org/pollaris/pollaris/-/commit/9e9fb97))
- Allow to rotate the icons ([63c1e9f](https://framagit.org/pollaris/pollaris/-/commit/63c1e9f))
- Refactor the handling of poll creation process ([0f4ee8d](https://framagit.org/pollaris/pollaris/-/commit/0f4ee8d))
- Set the look of "help" messages in forms ([ea69b4f](https://framagit.org/pollaris/pollaris/-/commit/ea69b4f))
- Update the copyright year ([6abf59a](https://framagit.org/pollaris/pollaris/-/commit/6abf59a))

## 2024-11-01 - 0.2.0

### New

- Add support for date polls ([6c2f670](https://framagit.org/pollaris/pollaris/-/commit/6c2f670), [3f7b5b9](https://framagit.org/pollaris/pollaris/-/commit/3f7b5b9))
- Allow to edit a vote ([6cc0a1f](https://framagit.org/pollaris/pollaris/-/commit/6cc0a1f))

### Improvements

- Display the preferred proposals ([d3a67c3](https://framagit.org/pollaris/pollaris/-/commit/d3a67c3), [e3ff053](https://framagit.org/pollaris/pollaris/-/commit/e3ff053), [d41e14a](https://framagit.org/pollaris/pollaris/-/commit/d41e14a), [2bafede](https://framagit.org/pollaris/pollaris/-/commit/2bafede))
- Add primary color to the main titles (`h1`) ([f19e5c0](https://framagit.org/pollaris/pollaris/-/commit/f19e5c0))
- Increase the size of the radio buttons ([50c07f8](https://framagit.org/pollaris/pollaris/-/commit/50c07f8))
- Improve the design of proposals buttons ([12ad303](https://framagit.org/pollaris/pollaris/-/commit/12ad303))

### Bug fixes

- Use fieldsets in votes form ([4719b48](https://framagit.org/pollaris/pollaris/-/commit/4719b48))
- Add missing errors in polls ([aeae910](https://framagit.org/pollaris/pollaris/-/commit/aeae910))
- Make assets URLs absolute ([1539d7e](https://framagit.org/pollaris/pollaris/-/commit/1539d7e))
- Check that poll is created before voting ([055f003](https://framagit.org/pollaris/pollaris/-/commit/055f003))
- Put new vote "cancel" and "submit" buttons on same line ([d6ee85a](https://framagit.org/pollaris/pollaris/-/commit/d6ee85a))
- Fix initialization of Answer ([702a67a](https://framagit.org/pollaris/pollaris/-/commit/702a67a))

### Documentation

- Setup the full documentation ([2a1a068](https://framagit.org/pollaris/pollaris/-/commit/2a1a068))

### Technical

- Make sure to require PostgreSQL >= 15 everywhere ([ce32379](https://framagit.org/pollaris/pollaris/-/commit/ce32379))
- Update the dependencies ([a91b478](https://framagit.org/pollaris/pollaris/-/commit/a91b478), [8cd582f](https://framagit.org/pollaris/pollaris/-/commit/8cd582f), [d6c7ba6](https://framagit.org/pollaris/pollaris/-/commit/d6c7ba6))

### Developers

- Provide a modal system ([cd9497d](https://framagit.org/pollaris/pollaris/-/commit/cd9497d))
- Provide the icons system ([a192bbb](https://framagit.org/pollaris/pollaris/-/commit/a192bbb))
- Provide tests ([6630c52](https://framagit.org/pollaris/pollaris/-/commit/6630c52), [2107fb3](https://framagit.org/pollaris/pollaris/-/commit/2107fb3), [6ddb1e7](https://framagit.org/pollaris/pollaris/-/commit/6ddb1e7), [552e2c6](https://framagit.org/pollaris/pollaris/-/commit/552e2c6), [1263d9e](https://framagit.org/pollaris/pollaris/-/commit/1263d9e))
- Replace Parcel by esbuild ([49100a8](https://framagit.org/pollaris/pollaris/-/commit/49100a8))
- Configure GitLab CI ([f95db57](https://framagit.org/pollaris/pollaris/-/commit/f95db57))
- Add a merge request template ([86d18d5](https://framagit.org/pollaris/pollaris/-/commit/86d18d5))
- Improve the make commands ([9f2078d](https://framagit.org/pollaris/pollaris/-/commit/9f2078d), [c356474](https://framagit.org/pollaris/pollaris/-/commit/c356474))
- Declare Docker Compose project name in docker-compose.yml ([037d6f0](https://framagit.org/pollaris/pollaris/-/commit/037d6f0))
- Declare an `app.public_directory` parameter in config ([ca8be30](https://framagit.org/pollaris/pollaris/-/commit/ca8be30))
- Fix the warnings of PHPStan ([5389397](https://framagit.org/pollaris/pollaris/-/commit/5389397))
- Change ProposalForm into ProposalType ([0db291e](https://framagit.org/pollaris/pollaris/-/commit/0db291e))
- Configure Rector with `RectorConfig::configure()` ([57be612](https://framagit.org/pollaris/pollaris/-/commit/57be612))

## 2024-08-23 - 0.1.0

### New

- Allow to create polls ([e4f2784](https://framagit.org/pollaris/pollaris/-/commit/e4f2784))
- Allow to add proposals to polls ([6b4ac05](https://framagit.org/pollaris/pollaris/-/commit/6b4ac05))
- Allow to fill the identity of a poll's author ([ca49412](https://framagit.org/pollaris/pollaris/-/commit/ca49412))
- Allow to answer to a poll ([2c08c8d](https://framagit.org/pollaris/pollaris/-/commit/2c08c8d))
- Display the results of a poll ([ff6dec1](https://framagit.org/pollaris/pollaris/-/commit/ff6dec1))
