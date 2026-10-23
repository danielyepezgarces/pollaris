# Translatewiki Integration Directory

> [!WARNING]
> **DO NOT EDIT TRANSLATION FILES DIRECTLY IN THIS DIRECTORY.**
> **NO EDITE MANUALMENTE LOS ARCHIVOS DE ESTE DIRECTORIO.**

This directory holds the MediaWiki-format JSON files (`$1`, `$2`, `{{PLURAL:$1|...}}`) synchronized with [Translatewiki.net](https://translatewiki.net/wiki/Translating:Pollaris).

### Help Translate / Colaborar con la traducción
Anyone can contribute translations for Pollaris online at:
👉 **[https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris)**

### Synchronization Workflow

- **Exporting (Symfony YAML -> Translatewiki JSON):**
  When new strings are added to English in `translations/*+intl-icu.en_GB.yaml`, run:
  ```bash
  php bin/console app:translatewiki:export
  ```
  Then commit and push `translatewiki/messages.en_GB.json` so Translatewiki picks up new messages.

- **Importing (Translatewiki JSON -> Symfony YAML):**
  When Translatewiki pushes localization updates to this directory, compile them into Symfony ICU YAML format with:
  ```bash
  php bin/console app:translatewiki:import
  ```
  Then commit the updated YAML files in `translations/`.
