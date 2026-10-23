# Translate Pollaris

**You can help to translate Pollaris at [translatewiki.net](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris).**

## Adding or updating translations

Translations are managed collaboratively through [Translatewiki.net](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris).

> [!WARNING]
> **Do not edit translation files in `translations/` or `translatewiki/` directly for target languages.** All translations are imported from Translatewiki and any manual changes will be overwritten.

### Translation Workflow:

1. **New strings in English:**
   - Add new strings **only** to the English source files:
     - `translations/messages+intl-icu.en_GB.yaml`
     - `translations/validators+intl-icu.en_GB.yaml`
2. **Export to Translatewiki format:**
   - Run the console command to export English strings to JSON:
     ```bash
     php bin/console app:translatewiki:export
     ```
   - Commit and push to Git so Translatewiki can pick up the new strings.
3. **Translating:**
   - Translators localize strings on [translatewiki.net](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris).
4. **Importing translations into Symfony:**
   - When Translatewiki pushes localization updates to `translatewiki/*.json`, import them into Symfony YAML files:
     ```bash
     php bin/console app:translatewiki:import
     ```
   - Commit the generated YAML files to Git.

**Note to the maintainers:** when accepting a new translation language, you must:

- add the language to the supported languages in [`src/Utils/Locales.php`](/src/Utils/Locales.php);
- enable the language in [`assets/js/dayjs_locale.js`](/assets/js/dayjs_locale.js) ([see supported locales](https://github.com/iamkun/dayjs/tree/dev/src/locale)).

